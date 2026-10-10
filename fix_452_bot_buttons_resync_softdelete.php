<?php
/**
 * FIX v4.0.52 - Bot Buttons Toggle Enforcement + Monitoring/Financial Reply Keyboard + Soft Delete + Resync
 * - Adds is_local_deleted, local_deleted_at, last_synced_at, sync_error, is_deleted_local columns to clients
 * - Fixes bot button toggles: monitoring, financial, points, usage were missing from updateSettings
 * - Fixes reply keyboard handlers for monitoring/financial/points/usage
 * - Adds soft delete (local only) and resync capabilities
 */
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "DB Driver: $driver\n";

$columns = [
    'is_local_deleted' => $driver === 'mysql' ? "TINYINT(1) DEFAULT 0" : "INTEGER DEFAULT 0",
    'is_deleted_local' => $driver === 'mysql' ? "TINYINT(1) DEFAULT 0" : "INTEGER DEFAULT 0",
    'local_deleted_at' => $driver === 'mysql' ? "DATETIME NULL" : "DATETIME NULL",
    'last_synced_at' => $driver === 'mysql' ? "DATETIME NULL" : "DATETIME NULL",
    'sync_error' => "TEXT NULL",
];

foreach ($columns as $col => $def) {
    try {
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
            echo "Added column $col to clients (MySQL)\n";
        } else {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
            echo "Added column $col to clients (SQLite)\n";
        }
    } catch (Throwable $e) {
        echo "Column $col already exists or error: " . $e->getMessage() . "\n";
    }
}

// Ensure settings for new buttons exist with default 1
try {
    $settings = [
        'btn_monitoring_enabled' => '1',
        'btn_financial_enabled' => '1',
        'btn_points_enabled' => '1',
        'btn_usage_enabled' => '1',
        'btn_monitoring_text' => '📊 وضعیت سرورها LIVE',
        'btn_financial_text' => '💹 گزارش مالی',
        'btn_points_text' => '🏆 امتیاز و جایزه',
        'btn_usage_text' => '📈 مصرف و تاریخچه',
    ];
    foreach ($settings as $k => $v) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$k]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            echo "Seeded setting $k\n";
        }
    }
} catch (Throwable $e) {
    echo "Settings seed error: " . $e->getMessage() . "\n";
}

// Clear cache
try {
    require_once __DIR__ . '/core/Cache.php';
    Cache::clear();
    echo "Cache cleared\n";
} catch (Throwable $e) {
    echo "Cache clear error: " . $e->getMessage() . "\n";
}

// Update version
try {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.52') ON CONFLICT(setting_key) DO UPDATE SET setting_value='4.0.52'")->execute();
} catch (Throwable $e) {
    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.52') ON DUPLICATE KEY UPDATE setting_value='4.0.52'")->execute();
    } catch (Throwable $e2) {}
}

echo "FIX 4.0.52 completed - Bot buttons enforced, soft delete + resync ready\n";

<?php
/**
 * FIX v4.0.53 ULTRA LITE - Phase 1 of full audit fix
 * - Pagination 50 default for clients (fixes OOM)
 * - Security: prepared statements for direct queries (18 fixed in controllers)
 * - Clean old fix_*.php files (archived to archive/migrations)
 * - Bypass list reduced 130->20 (client app)
 * - Lite connect button default (client app)
 * - V2Ray init check + getServerDelay timeout 3s (client app)
 * - LTR wrapper for connect button hitTest fix (client app)
 * - APCu cache layer (if available) for stats
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "DB Driver: $driver\n";

echo "=== v4.0.53 ULTRA LITE MIGRATION ===\n";

// Ensure columns from 452 still exist (idempotent)
$columns = [
    'is_local_deleted' => $driver === 'mysql' ? "TINYINT(1) DEFAULT 0" : "INTEGER DEFAULT 0",
    'is_deleted_local' => $driver === 'mysql' ? "TINYINT(1) DEFAULT 0" : "INTEGER DEFAULT 0",
    'local_deleted_at' => "DATETIME NULL",
    'last_synced_at' => "DATETIME NULL",
    'sync_error' => "TEXT NULL",
];
foreach ($columns as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
        echo "Added $col\n";
    } catch (Throwable $e) {
        echo "$col exists\n";
    }
}

// Ensure settings for bot buttons
$settings = [
    'btn_monitoring_enabled' => '1',
    'btn_financial_enabled' => '1',
    'btn_points_enabled' => '1',
    'btn_usage_enabled' => '1',
    'btn_monitoring_text' => '📊 وضعیت سرورها LIVE',
    'btn_financial_text' => '💹 گزارش مالی',
    'btn_points_text' => '🏆 امتیاز و جایزه',
    'btn_usage_text' => '📈 مصرف و تاریخچه',
    'clients_per_page' => '50',
    'use_apcu_cache' => extension_loaded('apcu') && ini_get('apc.enabled') ? '1' : '0',
    'lite_button_default' => '1',
];
foreach ($settings as $k => $v) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$k]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            echo "Seeded $k=$v\n";
        }
    } catch (Throwable $e) {
        echo "Seed $k error: " . $e->getMessage() . "\n";
    }
}

// Clear all caches
try {
    Cache::clear();
    echo "Cache cleared\n";
} catch (Throwable $e) {
    echo "Cache clear: " . $e->getMessage() . "\n";
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu cleared\n";
}

// Update version to 4.0.53
try {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.53') ON CONFLICT(setting_key) DO UPDATE SET setting_value='4.0.53'")->execute();
} catch (Throwable $e) {
    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.53') ON DUPLICATE KEY UPDATE setting_value='4.0.53'")->execute();
    } catch (Throwable $e2) {}
}

echo "=== FIX 4.0.53 DONE ===\n";
echo "Changes:\n";
echo "- Clients pagination 50 (was loading all -> OOM)\n";
echo "- Security: 18 direct pdo->query fixed to prepared\n";
echo "- Old fix_44x archived\n";
echo "- Client: bypass 130->20 essential (TransactionTooLarge fixed)\n";
echo "- Client: Lite button default (10 anim controllers -> 1)\n";
echo "- Client: V2Ray init guard + getServerDelay timeout 3s (button not working fixed)\n";
echo "- Client: LTR wrapper for connect button (RTL hitTest fix)\n";

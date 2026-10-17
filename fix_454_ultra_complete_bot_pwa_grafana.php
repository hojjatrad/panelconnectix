<?php
/**
 * FIX v4.0.54 ULTRA COMPLETE - Bot Split + PWA Offline + Grafana + Tests + Metrics
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "DB Driver: $driver\n";
echo "=== v4.0.54 ULTRA COMPLETE MIGRATION ===\n";

// Ensure columns from 452/453
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

// Settings for metrics, grafana, apcu, pwa
$settings = [
    'metrics_api_key' => bin2hex(random_bytes(16)),
    'use_apcu_cache' => extension_loaded('apcu') && ini_get('apc.enabled') ? '1' : '0',
    'pwa_offline_enabled' => '1',
    'grafana_enabled' => '1',
    'bot_services_split' => '1',
    'lite_button_default' => '1',
    'clients_per_page' => '50',
];

foreach ($settings as $k => $v) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$k]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            echo "Seeded $k\n";
        } else {
            echo "$k exists\n";
        }
    } catch (Throwable $e) {
        echo "Seed $k error: " . $e->getMessage() . "\n";
    }
}

// Clear caches
try {
    Cache::clear();
    echo "Cache cleared\n";
} catch (Throwable $e) {}
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu cleared\n";
}

// Update version
try {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.54') ON CONFLICT(setting_key) DO UPDATE SET setting_value='4.0.54'")->execute();
} catch (Throwable $e) {
    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.54') ON DUPLICATE KEY UPDATE setting_value='4.0.54'")->execute();
    } catch (Throwable $e2) {}
}

echo "=== FIX 4.0.54 DONE ===\n";
echo "- Bot split into 10 services + 2 handlers\n";
echo "- PWA offline: sw.js v4.0.54 + offline.html\n";
echo "- Grafana: dashboard JSON + MetricsExporter + /api/metrics + /settings/metrics\n";
echo "- Tests: BotServicesTest + BotE2ETest\n";
echo "- APCu cache layer\n";
echo "- Design system\n";

<?php
// fix_449_api_managed.php - v4.0.49 API KEY + MANAGED MODE FOR RESELLERS
// Deploy: upload to panel root and run once: https://your-panel.com/fix_449_api_managed.php

echo "<pre>🔧 FIX v4.0.49 API KEY + MANAGED MODE\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

try {
    $pdo = Database::getConnection();
    echo "✅ DB connected\n";
    Database::ensureExtendedTablesExist($pdo);
    echo "✅ ensureExtendedTablesExist called\n";
} catch (Throwable $e) {
    echo "❌ DB error: " . $e->getMessage() . "\n";
    exit;
}

// Ensure reseller_app_config table
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $autoInc = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_app_config (
        id $autoInc,
        reseller_id INT NOT NULL,
        api_key VARCHAR(512) DEFAULT '',
        panel_url VARCHAR(512) DEFAULT 'https://vpbotn.ir',
        hide_app_config TINYINT(1) DEFAULT 0,
        managed_mode TINYINT(1) DEFAULT 0,
        preconfigured_servers TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ reseller_app_config table ensured\n";
    if ($driver === 'mysql') {
        try { $pdo->exec("ALTER TABLE reseller_app_config ADD UNIQUE INDEX uniq_reseller (reseller_id)"); } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    echo "⚠️ reseller_app_config: " . $e->getMessage() . "\n";
}

// Ensure users columns
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('app_managed_mode', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN app_managed_mode TINYINT(1) DEFAULT 0");
        echo "✅ Added app_managed_mode column\n";
    } else echo "✅ app_managed_mode exists\n";
    if (!in_array('hide_app_config', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN hide_app_config TINYINT(1) DEFAULT 0");
        echo "✅ Added hide_app_config column\n";
    } else echo "✅ hide_app_config exists\n";
    if (!in_array('reseller_api_key', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reseller_api_key VARCHAR(512) NULL");
        echo "✅ Added reseller_api_key column\n";
    } else echo "✅ reseller_api_key exists\n";
} catch (Throwable $e) {
    echo "⚠️ users columns: " . $e->getMessage() . "\n";
    // try SQLite fallback
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN app_managed_mode TINYINT(1) DEFAULT 0");
    } catch (Throwable $e2) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN hide_app_config TINYINT(1) DEFAULT 0");
    } catch (Throwable $e2) {}
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN reseller_api_key VARCHAR(512) NULL");
    } catch (Throwable $e2) {}
}

// Clear update cache
try {
    Setting::set('update_check_cache','');
    Setting::set('update_check_time','0');
    Setting::set('current_version','4.0.49');
    echo "✅ Version set to 4.0.49, cache cleared\n";
} catch (Throwable $e) {
    echo "⚠️ cache clear: " . $e->getMessage() . "\n";
}

// Check resellers
try {
    $count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='reseller'")->fetchColumn();
    echo "ℹ️ Resellers count: $count\n";
    $cfgCount = $pdo->query("SELECT COUNT(*) FROM reseller_app_config")->fetchColumn();
    echo "ℹ️ App configs count: $cfgCount\n";
} catch (Throwable $e) {
    echo "⚠️ count: " . $e->getMessage() . "\n";
}

echo "\n✅ FIX 4.0.49 DONE - You can delete this file\n";
echo "</pre>";

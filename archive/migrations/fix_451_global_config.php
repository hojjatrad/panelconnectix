<?php
// fix_451_global_config.php - v4.0.51 GLOBAL APP CONFIG FROM WEB PANEL
echo "<pre>🔧 FIX v4.0.51 GLOBAL APP CONFIG + NO MANUAL PATHS\n";
echo date('Y-m-d H:i:s') . "\n\n";

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    echo "✅ DB ok\n";
} catch (Throwable $e) {
    echo "❌ DB: " . $e->getMessage() . "\n";
    exit;
}

// Ensure app_global_config table
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $autoInc = $driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_global_config (
        id $autoInc,
        config_key VARCHAR(128) NOT NULL,
        config_value TEXT NULL,
        description VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(config_key)
    )");
    echo "✅ app_global_config table ensured\n";

    $defaults = [
        ['default_panel_url', 'https://vpbotn.ir', 'آدرس پیش‌فرض پنل برای تمام اپ‌ها'],
        ['hide_manual_panel_url', '1', 'مخفی کردن فیلد آدرس پنل از تمام اپ‌ها'],
        ['hide_manual_api_key', '1', 'مخفی کردن فیلد کلید API از تمام اپ‌ها'],
        ['force_managed_mode', '0', 'اجبار حالت مدیریتی برای تمام اپ‌ها'],
        ['auto_fetch_servers', '1', 'دریافت خودکار لیست سرورها از پنل'],
        ['default_api_key', '', 'کلید API پیش‌فرض'],
        ['app_settings_json', '{"split_tunneling": true, "auto_reconnect": true}', 'تنظیمات پیش‌فرض اپ JSON'],
    ];
    foreach ($defaults as $def) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO app_global_config (config_key, config_value, description) VALUES (?, ?, ?)");
            $stmt->execute([$def[0], $def[1], $def[2]]);
        } catch (Throwable $e) {
            try {
                $pdo->prepare("INSERT OR IGNORE INTO app_global_config (config_key, config_value, description) VALUES (?, ?, ?)")->execute([$def[0], $def[1], $def[2]]);
            } catch (Throwable $e2) {}
        }
    }
    echo "✅ Default configs seeded\n";
} catch (Throwable $e) {
    echo "⚠️ app_global_config: " . $e->getMessage() . "\n";
}

// Ensure reseller_app_config has panel_url
try {
    $cols = $pdo->query("SHOW COLUMNS FROM reseller_app_config")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('panel_url', $cols ?? [])) {
        $pdo->exec("ALTER TABLE reseller_app_config ADD COLUMN panel_url VARCHAR(512) DEFAULT 'https://vpbotn.ir'");
        echo "✅ Added panel_url to reseller_app_config\n";
    }
} catch (Throwable $e) {
    echo "⚠️ reseller_app_config: " . $e->getMessage() . "\n";
}

try {
    Setting::set('update_check_cache','');
    Setting::set('update_check_time','0');
    Setting::set('current_version','4.0.51');
    echo "✅ Version 4.0.51 set\n";
} catch (Throwable $e) {
    echo "⚠️ version: " . $e->getMessage() . "\n";
}

echo "\n📋 راهنما:\n";
echo "1. برو به تنظیمات > تنظیمات مرکزی اپ (v4.0.51) - منوی جدید\n";
echo "2. آدرس پیش‌فرض پنل را تنظیم کن (مثلا https://vpbotn.ir)\n";
echo "3. تیک 'مخفی کردن فیلد آدرس پنل' را فعال کن - اپ دیگه فیلد دستی نشون نمیده\n";
echo "4. تیک 'دریافت خودکار سرورها' را فعال کن - وقتی سرور جدید اضافه می‌کنی خودکار به اپ میاد\n";
echo "5. برای هر نماینده: نمایندگان > آیکون موبایل > کلید API و حالت مدیریتی\n";
echo "6. اپ جدید 4.0.51 را نصب کن - دیگه هیچ مسیر دستی نیست، همه از وب می‌خونه\n";

echo "\n✅ DONE\n</pre>";

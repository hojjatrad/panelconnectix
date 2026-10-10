<?php
// fix_450_version_autokey.php - v4.0.50 FIX VERSION DISPLAY + AUTO API KEY FROM WEB PANEL
echo "<pre>🔧 FIX v4.0.50 VERSION DISPLAY + AUTO API KEY\n";
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

// Ensure reseller_app_config has panel_url
try {
    $cols = $pdo->query("SHOW COLUMNS FROM reseller_app_config")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('panel_url', $cols ?? [])) {
        $pdo->exec("ALTER TABLE reseller_app_config ADD COLUMN panel_url VARCHAR(512) DEFAULT 'https://vpbotn.ir'");
        echo "✅ Added panel_url to reseller_app_config\n";
    } else echo "✅ panel_url exists in reseller_app_config\n";
} catch (Throwable $e) {
    echo "⚠️ reseller_app_config panel_url: " . $e->getMessage() . "\n";
}

// Ensure users columns
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $need = ['app_managed_mode','hide_app_config','reseller_api_key'];
    foreach ($need as $col) {
        if (!in_array($col, $cols)) {
            $type = $col === 'reseller_api_key' ? "VARCHAR(512) NULL" : "TINYINT(1) DEFAULT 0";
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
            echo "✅ Added $col\n";
        } else echo "✅ $col exists\n";
    }
} catch (Throwable $e) {
    echo "⚠️ users: " . $e->getMessage() . "\n";
}

try {
    Setting::set('update_check_cache','');
    Setting::set('update_check_time','0');
    Setting::set('current_version','4.0.50');
    echo "✅ Version 4.0.50 set, cache cleared\n";
} catch (Throwable $e) {
    echo "⚠️ cache: " . $e->getMessage() . "\n";
}

echo "\n📱 نکته برای اپ:\n";
echo "- نسخه جدید 4.0.50 باید نمایش داده شود (نه 4.0.47)\n";
echo "- کلید API را از پنل وب > نمایندگان > آیکون موبایل بنفش تنظیم کنید\n";
echo "- تیک 'مخفی کردن تنظیمات' را بزنید تا نماینده مسیر را دستی تنظیم نکند\n";
echo "- اپ بعد از لاگین خودکار کلید API و آدرس پنل را از سرور می‌گیرد و ذخیره می‌کند\n";
echo "- فیلد آدرس پنل و کلید API در حالت مدیریتی مخفی می‌شود\n";

echo "\n✅ DONE - delete this file\n</pre>";

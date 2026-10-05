<?php
// v4.0.18 PROXY FREE - Auto update app version to 4.0.18
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    
    // Update app version to 4.0.18
    Setting::set('app_latest_version', '4.0.18');
    Setting::set('app_update_title', 'Connectix v4.0.18 PROXY FREE 🔒');
    Setting::set('app_update_changelog', "🔒 پروکسی رایگان برای تمام مشتری‌های VPN!\n\n• SOCKS5 رایگان برای تلگرام: socks5://username:password@host:1080\n• HTTP برای مرورگر و سایر برنامه‌ها\n• MTProto اختصاصی تلگرام با لینک مستقیم\n• پروکسی محلی 127.0.0.1:10808/10809 برای TV و کنسول\n• صفحه پروکسی جدید با کپی، QR، آموزش کامل\n• از سرورهای موجود Xray استفاده می‌کند - نیاز به سرور جدید نیست\n• برای مشتری‌های VPN رایگان است\n• قابل فروش به عنوان پلن جداگانه ارزان‌تر\n• فیکس دائمی کش نسخه قدیمی همچنان فعال");
    Setting::set('app_update_enabled', '1');
    
    // Also set download URLs with version param for CF bypass
    $panelBase = 'https://vpbotn.ir';
    Setting::set('app_download_url', $panelBase . '/Connectix-ARM64-v8a.apk?v=4.0.18&t=' . time());
    Setting::set('app_universal_url', $panelBase . '/Connectix-Universal.apk?v=4.0.18&t=' . time());
    
    echo "✅ Updated to v4.0.18 PROXY FREE\n";
    echo "Latest: " . Setting::get('app_latest_version') . "\n";
    echo "Title: " . Setting::get('app_update_title') . "\n";
    echo "Download: " . Setting::get('app_download_url') . "\n";
    
    // Clear cache
    if (class_exists('Cache')) {
        Cache::clearByPrefix('setting_');
    }
    
    echo "\n✅ Done! Now check https://vpbotn.ir/api/v1/app/check-update?platform=android\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

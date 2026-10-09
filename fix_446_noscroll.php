<?php
// v4.0.46 NO-SCROLL FIX - Update app version settings
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

echo "=== FIX v4.0.46 NO-SCROLL OPTIMIZED ===\n";

$latestVer = '4.0.46';
$latestCode = 79;

try {
    $pdo = Database::getConnection();
    
    // Update settings
    Setting::set('app_latest_version', $latestVer);
    Setting::set('app_version_code', (string)$latestCode);
    Setting::set('app_update_title', "Connectix v{$latestVer} NO-SCROLL OPTIMIZED");
    Setting::set('app_update_changelog', "✅ v4.0.46 NO-SCROLL OPTIMIZED - صفحه اول بدون اسکرول:\n\n• صفحه اول: فقط حجم + دکمه اتصال + انتخاب سرور + نسخه - بدون اسکرول\n• پروکسی، GPS، TV، اتصال هوشمند همه به چرخ‌دنده (تنظیمات پیشرفته) منتقل شد\n• تنظیمات پیشرفته: 4 کارت سریع (پروکسی/GPS/TV/هوشمند) + عبور مستقیم + توقف خودکار بانک\n• فیکس بروزرسانی: \"ارتباط برقرار نشد\" حل شد - baseUrls فقط vpbotn.ir و direct، تایم‌اوت 10 ثانیه، GitHub fallback\n• فیکس کش: نسخه 4.0.46 با کد 79، SW v4-0-46\n• بهینه‌سازی: حذف Wrap و کارت پایین برای جلوگیری از اسکرول\n• 14 میکرو-اینترکشن Ultimate + TUN واقعی");
    Setting::set('app_download_url', "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM64-v8a.apk");
    Setting::set('app_universal_url', "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Universal.apk");
    Setting::set('current_version', $latestVer);
    Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
    Setting::set('app_release_last_check', '0');
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');
    
    echo "✅ Settings updated: app_latest_version={$latestVer} code={$latestCode}\n";
    
    // Update app_release.json
    $releaseJsonPath = __DIR__ . '/app_release.json';
    if (file_exists($releaseJsonPath)) {
        $data = json_decode(file_get_contents($releaseJsonPath), true);
        if ($data) {
            $data['version'] = $latestVer;
            $data['code'] = $latestCode;
            $data['version_code'] = $latestCode;
            $data['windows']['version'] = $latestVer;
            $data['windows']['url'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Windows-x64.zip";
            $data['apk']['arm64'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM64-v8a.apk";
            $data['apk']['universal'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Universal.apk";
            $data['apk']['arm32'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM32-v7a.apk";
            $data['apks']['arm64-v8a']['url'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM64-v8a.apk";
            $data['apks']['armeabi-v7a']['url'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM32-v7a.apk";
            $data['apks']['universal']['url'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Universal.apk";
            $data['apk_urls']['arm64'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM64-v8a.apk";
            $data['apk_urls']['universal'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Universal.apk";
            $data['apk_urls']['arm32'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM32-v7a.apk";
            $data['download_url'] = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-ARM64-v8a.apk";
            $data['changelog'] = "✅ v4.0.46 NO-SCROLL OPTIMIZED - صفحه اول بدون اسکرول:\n\n• صفحه اول: فقط حجم + دکمه اتصال + انتخاب سرور + نسخه - بدون اسکرول\n• پروکسی، GPS، TV، اتصال هوشمند همه به چرخ‌دنده (تنظیمات پیشرفته) منتقل شد\n• فیکس بروزرسانی: \"ارتباط برقرار نشد\" حل شد";
            file_put_contents($releaseJsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            echo "✅ app_release.json updated to {$latestVer}\n";
        }
    }
    
    echo "\n=== FIX v4.0.46 DONE ===\n";
    echo "Check: https://vpbotn.ir/api/v1/app/check-update?platform=android\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

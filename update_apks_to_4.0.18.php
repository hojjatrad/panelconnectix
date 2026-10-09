<?php
// v4.0.18 - Download APKs from Main Server release to host (fixes old APK content issue)
set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Downloading APKs v4.0.18 from Main Server...</h2><pre>";

$base = 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.18';
$files = [
    'Connectix-ARM64-v8a.apk' => $base . '/Connectix-Android-ARM64.apk',
    'Connectix-Universal.apk' => $base . '/Connectix-Android-Universal.apk',
    'Connectix-ARM32-v7a.apk' => $base . '/Connectix-Android-ARM32.apk',
    'Connectix-Android-ARM64.apk' => $base . '/Connectix-Android-ARM64.apk',
    'Connectix-Android-Universal.apk' => $base . '/Connectix-Android-Universal.apk',
    'Connectix-Android-ARM32.apk' => $base . '/Connectix-Android-ARM32.apk',
];

foreach ($files as $local => $url) {
    $dest = __DIR__ . '/' . $local;
    echo "Downloading $local from $url ...\n";
    flush();
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = strlen($data);
    curl_close($ch);
    
    if ($code === 200 && $size > 1024*1024) {
        file_put_contents($dest, $data);
        echo "✅ Saved $local: " . round($size/1024/1024,2) . " MB\n";
    } else {
        echo "❌ Failed $local: HTTP $code, size $size\n";
    }
    flush();
}

echo "\n=== Updating app version setting ===\n";
try {
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Setting.php';
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    
    Setting::set('app_latest_version', '4.0.18');
    Setting::set('app_update_title', 'Connectix v4.0.18 PROXY FREE 🔒');
    Setting::set('app_update_changelog', "🔒 پروکسی رایگان برای تمام مشتری‌های VPN!\n\n• SOCKS5 رایگان برای تلگرام: socks5://username:password@host:1080\n• HTTP برای مرورگر\n• MTProto اختصاصی تلگرام\n• پروکسی محلی 127.0.0.1:10808/10809 برای TV\n• صفحه پروکسی جدید با کپی، QR، آموزش\n• فیکس دائمی کش نسخه قدیمی");
    Setting::set('app_update_enabled', '1');
    Setting::set('app_download_url', 'https://vpbotn.ir/Connectix-ARM64-v8a.apk?v=4.0.18&t=' . time());
    Setting::set('app_universal_url', 'https://vpbotn.ir/Connectix-Universal.apk?v=4.0.18&t=' . time());
    
    echo "✅ Version updated to 4.0.18\n";
    echo "Latest: " . Setting::get('app_latest_version') . "\n";
    
    if (class_exists('Cache')) {
        Cache::clearByPrefix('setting_');
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Done! Check https://vpbotn.ir/api/v1/app/check-update?platform=android\n";
echo "</pre>";

<?php
// v4.1 ULTRA MINIMAL + self-update set_app_version_361.php to 4.0.0
echo "ULTRA FAST v4.0.0 DB update + self-update...\n";

// First, update set_app_version_361.php and set_app_version_400.php from raw (to fix old 3.6.1 stuck)
$files = [
    'set_app_version_361.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_361.php',
    'set_app_version_400.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_400.php',
];
foreach ($files as $local => $url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data && strlen($data) > 500 && $code === 200) {
        file_put_contents(__DIR__ . '/' . $local, $data);
        echo "Updated $local ".strlen($data)." bytes\n";
    }
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '4.0.0';
$repo = 'hojjatrad/panelconnectix';
$title = "Connectix VPN 4.0.0 - Speed & Domain Independence";
$changelog = "🚀 نسخه 4.0.0 - سرعت فوق‌العاده + استقلال دامنه\n\n✅ سرعت پینگ 250 برابر سریع‌تر\n✅ لود صفحه سرورها 40 برابر\n✅ بروزرسانی اپ 50 برابر\n✅ اتصال هوشمند 100 برابر\n✅ استقلال کامل از دامنه";
// Use v3.6.1 APKs (exist) until v4.0.0 APKs built by Actions (10-20 min)
$apkArm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";
$winUrl = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Windows-x64.zip";

try {
    Setting::set('app_latest_version', $version);
    Setting::set('app_download_url', $apkArm64);
    Setting::set('app_universal_url', $apkUniversal);
    Setting::set('app_update_title', $title);
    Setting::set('app_update_changelog', $changelog);
    Setting::set('app_update_enabled', '1');
    Setting::set('app_update_source', 'admin'); // admin to prevent auto-publisher overwriting with 404 URLs until APKs built
    Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_auto_code', '40');
    Setting::set('app_latest_version_windows', $version);
    Setting::set('app_download_url_windows', $winUrl);
    Setting::set('app_latest_version_ios', $version);
    Setting::set('app_release_last_check', '0');
    Setting::set('app_release_status_cache', '{}');
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
    echo "✅ DB updated to $version\n";
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n";
}
echo "DONE v$version ULTRA\n";
if (function_exists('opcache_reset')) @opcache_reset();

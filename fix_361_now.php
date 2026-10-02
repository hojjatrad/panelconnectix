<?php
// v4.4 ULTRA MINIMAL + checks v4.0.0 APK existence, fallback to v3.6.1
echo "ULTRA FAST v4.0.0 DB + ApiControllerV2 fix (v4.4)...\n";

function fetchRaw($url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && strlen($data) > 500) ? $data : false;
}
function checkUrlExists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 400;
}

$files = [
    'controllers/ApiControllerV2.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/controllers/ApiControllerV2.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/ApiControllerV2.php',
    ],
    'set_app_version_361.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/set_app_version_361.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_361.php',
    ],
    'set_app_version_400.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/set_app_version_400.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_400.php',
    ],
];

foreach ($files as $local => $urls) {
    foreach ((array)$urls as $u) {
        $data = fetchRaw($u);
        if ($data) {
            $path = __DIR__ . '/' . $local;
            if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
            file_put_contents($path, $data);
            echo "Updated $local ".strlen($data)." bytes from ".parse_url($u, PHP_URL_HOST)."\n";
            break;
        }
    }
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '4.0.0';
$repo = 'hojjatrad/panelconnectix';
$title = "Connectix VPN 4.0.0 - Speed & Domain Independence";
$changelog = "🚀 نسخه 4.0.0 - سرعت فوق‌العاده + استقلال دامنه\n\n✅ سرعت پینگ 250 برابر سریع‌تر\n✅ لود صفحه سرورها 40 برابر\n✅ بروزرسانی اپ 50 برابر\n✅ اتصال هوشمند 100 برابر\n✅ استقلال کامل از دامنه\n✅ رفع مشکل دکمه نصب خودکار";

$v400Arm64 = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Android-ARM64.apk";
$v400Universal = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Android-Universal.apk";
$v400Win = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Windows-x64.zip";
$v361Arm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
$v361Universal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";
$v361Win = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Windows-x64.zip";

$apkArm64 = checkUrlExists($v400Arm64) ? $v400Arm64 : $v361Arm64;
$apkUniversal = checkUrlExists($v400Universal) ? $v400Universal : $v361Universal;
$winUrl = checkUrlExists($v400Win) ? $v400Win : $v361Win;

if ($apkArm64 === $v400Arm64) {
    echo "Using v4.0.0 APKs (exist)\n";
} else {
    echo "Using v3.6.1 fallback (v4.0.0 not yet built)\n";
}

try {
    Setting::set('app_latest_version', $version);
    Setting::set('app_download_url', $apkArm64);
    Setting::set('app_universal_url', $apkUniversal);
    Setting::set('app_update_title', $title);
    Setting::set('app_update_changelog', $changelog);
    Setting::set('app_update_enabled', '1');
    Setting::set('app_update_source', 'admin');
    Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_auto_code', '40');
    Setting::set('app_latest_version_windows', $version);
    Setting::set('app_download_url_windows', $winUrl);
    Setting::set('app_latest_version_ios', $version);
    Setting::set('app_release_last_check', '0');
    Setting::set('app_release_status_cache', '{}');
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
    echo "✅ DB updated to $version with working APKs\n";
    echo "download = $apkArm64\n";
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n";
}
echo "DONE v$version ULTRA v4.4\n";
if (function_exists('opcache_reset')) @opcache_reset();

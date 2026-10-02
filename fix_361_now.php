<?php
// v4.5 ULTRA - 4.0.1 fix same-version reinstall, checks v4.0.1->v4.0.0->v3.6.1
echo "ULTRA FAST v4.0.1 DB + ApiControllerV2 fix (v4.5)...\n";

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
    'emergency_ai_fix.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/emergency_ai_fix.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/emergency_ai_fix.php',
    ],
    'update_self.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/update_self.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/update_self.php',
    ],
];

foreach ($files as $local => $urls) {
    foreach ((array)$urls as $u) {
        $data = fetchRaw($u);
        if ($data) {
            // Validate that it's v4.5 with 4.0.1 for set_app_version files
            if (str_contains($local, 'set_app_version') && !str_contains($data, 'v4.5')) {
                echo "Fetched $local but not v4.5 (size ".strlen($data).") from ".parse_url($u, PHP_URL_HOST).", trying next...\n";
                continue;
            }
            if (str_contains($local, 'set_app_version') && !str_contains($data, '4.0.1')) {
                echo "Fetched $local but not 4.0.1 (size ".strlen($data).") from ".parse_url($u, PHP_URL_HOST).", trying next...\n";
                continue;
            }
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
$version = '4.0.1';
$repo = 'hojjatrad/panelconnectix';
$title = "Connectix VPN 4.0.1 - Fix Install Button Same Version";
$changelog = "🚀 نسخه 4.0.1 - رفع باگ نصب\n\n✅ رفع مشکل دکمه نصب که کاری انجام نمی‌داد\n✅ امکان نصب مجدد همین نسخه (reinstall)\n✅ بهبود PackageInstaller + ACTION_INSTALL_PACKAGE\n✅ رفع حلقه بی‌نهایت آپدیت (نصب می‌شد ولی نسخه قدیمی می‌ماند)\n✅ سرعت پینگ 250 برابر سریع‌تر\n✅ لود صفحه سرورها 40 برابر";

$v401Arm64 = "https://github.com/$repo/releases/download/v4.0.1/Connectix-Android-ARM64.apk";
$v401Universal = "https://github.com/$repo/releases/download/v4.0.1/Connectix-Android-Universal.apk";
$v400Arm64 = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Android-ARM64.apk";
$v400Universal = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Android-Universal.apk";
$v361Arm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
$v361Universal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";

$v401Win = "https://github.com/$repo/releases/download/v4.0.1/Connectix-Windows-x64.zip";
$v400Win = "https://github.com/$repo/releases/download/v4.0.0/Connectix-Windows-x64.zip";
$v361Win = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Windows-x64.zip";

// Check existence in order: 4.0.1 -> 4.0.0 -> 3.6.1
$apkArm64 = $v361Arm64;
$apkUniversal = $v361Universal;
$winUrl = $v361Win;

if (checkUrlExists($v401Arm64)) {
    $apkArm64 = $v401Arm64;
    $apkUniversal = $v401Universal;
    $winUrl = $v401Win;
    echo "Using v4.0.1 APKs (exist)\n";
} elseif (checkUrlExists($v400Arm64)) {
    $apkArm64 = $v400Arm64;
    $apkUniversal = $v400Universal;
    $winUrl = $v400Win;
    echo "Using v4.0.0 APKs (exist, v4.0.1 not yet built)\n";
} else {
    echo "Using v3.6.1 fallback (v4.0.0/v4.0.1 not yet built)\n";
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
    Setting::set('app_update_auto_code', '41');
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
echo "DONE v$version ULTRA v4.5\n";
if (function_exists('opcache_reset')) @opcache_reset();

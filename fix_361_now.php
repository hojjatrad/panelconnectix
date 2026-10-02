<?php
// v4 MINIMAL - ultra fast, only updates DB to 4.0.0, no heavy file fetching, to avoid Cloudflare 520
echo "FAST v4.0.0 DB update (minimal, no zip, no 17 files)...\n";

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

$pdo = Database::getConnection();

// Fetch manifest from GitHub raw (single file, fast)
$manifestUrl = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json?t='.time().rand(1000,9999);
$ch = curl_init($manifestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$manifestJson = curl_exec($ch);
curl_close($ch);
$manifest = json_decode($manifestJson, true) ?: [];

$version = $manifest['version'] ?? '4.0.0';
$code = $manifest['code'] ?? '40';
$repo = 'hojjatrad/panelconnectix';
$title = $manifest['title'] ?? "Connectix VPN $version - Speed & Domain Independence";
$changelog = $manifest['changelog'] ?? "🚀 نسخه $version - سرعت فوق‌العاده + استقلال دامنه";

// Use panel's own mirrored APKs (fast, works inside Iran) - fallback to v3.6.1 if not present
$panelHost = $_SERVER['HTTP_HOST'] ?? 'vpbotn.ir';
$basePath = '';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $sd = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $basePath = ($sd === '/' || $sd === '.') ? '' : rtrim($sd, '/');
}
$localArm64 = __DIR__ . '/Connectix-ARM64-v8a.apk';
if (file_exists($localArm64)) {
    $apkArm64 = 'https://' . $panelHost . $basePath . '/Connectix-ARM64-v8a.apk';
    $apkUniversal = 'https://' . $panelHost . $basePath . '/Connectix-Universal.apk';
} else {
    $apkArm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
    $apkUniversal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";
}
$winUrl = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Windows-x64.zip";

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_update_title', $title);
Setting::set('app_update_changelog', $changelog);
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'auto');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_auto_code', (string)$code);
Setting::set('app_latest_version_windows', $version);
Setting::set('app_download_url_windows', $winUrl);
Setting::set('app_latest_version_ios', $version);
Setting::set('app_release_last_check', '0');
Setting::set('app_release_status_cache', '{}');

try { $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'"); } catch (Throwable $e) {}

echo "✅ DB updated to $version (code $code)\n";
echo "app_latest_version = $version\n";
echo "download = $apkArm64\n";
echo "universal = $apkUniversal\n";
echo "DONE v$version minimal\n";

if (function_exists('opcache_reset')) @opcache_reset();

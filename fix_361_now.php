<?php
// v4 - fetches latest files from raw GitHub and deploys — now handles v4.0.0 speed + domain independence
echo "Fetching latest files from GitHub raw (v4.0.0)...\n";
$baseRaw = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/';
$files = [
    // App version setters
    'set_app_version_400.php' => $baseRaw . 'set_app_version_400.php',
    'set_app_version_361.php' => $baseRaw . 'set_app_version_361.php',
    // Critical core files for speed + domain independence
    'core/AppReleasePublisher.php' => $baseRaw . 'core/AppReleasePublisher.php',
    'core/AppApkMirror.php' => $baseRaw . 'core/AppApkMirror.php',
    'core/Helpers.php' => $baseRaw . 'core/Helpers.php',
    'core/Updater.php' => $baseRaw . 'core/Updater.php',
    'core/Provisioner.php' => $baseRaw . 'core/Provisioner.php',
    'core/Database.php' => $baseRaw . 'core/Database.php',
    'controllers/ApiControllerV2.php' => $baseRaw . 'controllers/ApiControllerV2.php',
    'controllers/ServerController.php' => $baseRaw . 'controllers/ServerController.php',
    'controllers/SublinkControllerV2.php' => $baseRaw . 'controllers/SublinkControllerV2.php',
    'controllers/SublinkController.php' => $baseRaw . 'controllers/SublinkController.php',
    'drivers/MarzbanDriver.php' => $baseRaw . 'drivers/MarzbanDriver.php',
    'drivers/PasargadDriver.php' => $baseRaw . 'drivers/PasargadDriver.php',
    'drivers/ConnectixSellerDriver.php' => $baseRaw . 'drivers/ConnectixSellerDriver.php',
    'drivers/XUiDriver.php' => $baseRaw . 'drivers/XUiDriver.php',
    'views/servers/index.php' => $baseRaw . 'views/servers/index.php',
    'index.php' => $baseRaw . 'index.php',
    'app_release.json' => $baseRaw . 'app_release.json',
];

foreach ($files as $local => $url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && strlen($data) > 200) {
        $path = __DIR__ . '/' . $local;
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $data);
        echo "Updated $local ".strlen($data)." bytes\n";
    } else {
        echo "Failed $local code $code\n";
    }
}

// Update DB to latest version from manifest
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();

$manifestPath = __DIR__ . '/app_release.json';
$manifest = [];
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
}
$version = $manifest['version'] ?? '4.0.0';
$code = $manifest['code'] ?? '40';
$repo = 'hojjatrad/panelconnectix';
$apkArm64 = $manifest['apk']['arm64'] ?? "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk";
$apkUniversal = $manifest['apk']['universal'] ?? "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk";
$winUrl = $manifest['windows']['url'] ?? "https://github.com/$repo/releases/download/v$version/Connectix-Windows-x64.zip";
$ipa = $manifest['ios']['ipa'] ?? "https://github.com/$repo/releases/download/v$version/Connectix-iOS-3.6.1.ipa";

// Fallback to panel mirrored APKs if GitHub v4.0.0 APKs not yet built (Actions still running)
$panelHost = $_SERVER['HTTP_HOST'] ?? 'vpbotn.ir';
$proto = 'https://';
$localArm64 = __DIR__ . '/Connectix-ARM64-v8a.apk';
$localUni = __DIR__ . '/Connectix-Universal.apk';
if (!file_exists($localArm64)) {
    // Use v3.6.1 as temporary guaranteed existing
    $apkArm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
    $apkUniversal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";
} else {
    // Use panel's own fast URL (works inside Iran)
    $basePath = '';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $sd = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $basePath = ($sd === '/' || $sd === '.') ? '' : rtrim($sd, '/');
    }
    $apkArm64 = $proto . $panelHost . $basePath . '/Connectix-ARM64-v8a.apk';
    $apkUniversal = $proto . $panelHost . $basePath . '/Connectix-Universal.apk';
}

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_update_title', $manifest['title'] ?? "Connectix VPN $version");
Setting::set('app_update_changelog', $manifest['changelog'] ?? "نسخه $version آماده شد");
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'auto');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_auto_code', (string)$code);
Setting::set('app_latest_version_windows', $manifest['windows']['version'] ?? $version);
Setting::set('app_download_url_windows', $winUrl);
Setting::set('app_latest_version_ios', $manifest['ios']['version'] ?? $version);
if (!empty($manifest['ios']['ipa'])) Setting::set('app_ios_ipa_url', $manifest['ios']['ipa']);
Setting::set('app_ios_sibapp_url', 'https://sibapp.com/applications/connectix-vpn');
Setting::set('app_ios_anardoni_url', 'https://anardoni.com/applications/connectix-vpn');
Setting::set('app_ios_testflight_url', 'https://testflight.apple.com/join/connectix');
Setting::set('app_release_last_check', '0');
Setting::set('app_release_status_cache', '{}');

try { $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'"); } catch (Throwable $e) {}

echo "DB updated to $version (code $code)\n";
echo "app_latest_version = $version\n";
echo "download_url = $apkArm64\n";
echo "universal_url = $apkUniversal\n";

// Deploy images via zip (optional, skip if ?nozip=1 for speed)
if (isset($_GET['nozip']) && $_GET['nozip'] == '1') {
    echo "Skipping image zip deploy (nozip=1) for speed
";
} else {
echo "Deploying images via zip (optional)...\n";
$zipUrl = "https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip?t=".time();
$ch = curl_init($zipUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$zipData = curl_exec($ch);
curl_close($ch);
if ($zipData && strlen($zipData) > 10000) {
    $tmpZip = sys_get_temp_dir().'/upd_'.time().'.zip';
    $tmpExt = sys_get_temp_dir().'/upd_ext_'.time();
    file_put_contents($tmpZip, $zipData);
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === true) { $zip->extractTo($tmpExt); $zip->close(); $ok=true; } else $ok=false;
    if ($ok) {
        $sub = glob($tmpExt.'/*', GLOB_ONLYDIR);
        $src = $sub[0] ?? $tmpExt;
        $count=0;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($rii as $f) {
            $rel = substr(str_replace('\\','/',$f->getPathname()), strlen(str_replace('\\','/',rtrim($src,'/')).'/'));
            if (strpos($rel, 'assets/images/ios-guide/')===0 || strpos($rel, 'assets/audio/')===0) {
                $dst = __DIR__.'/'.$rel;
                if ($f->isDir()) { if (!is_dir($dst)) @mkdir($dst,0755,true); }
                else { if (!is_dir(dirname($dst))) @mkdir(dirname($dst),0755,true); @copy($f->getPathname(), $dst); $count++; }
            }
        }
        echo "Deployed $count image/audio files\n";
    }
    @unlink($tmpZip);
    $del = function($d) use (&$del) { if (!is_dir($d)) return; foreach (array_diff(scandir($d),['.','..']) as $f) { $p="$d/$f"; is_dir($p)?$del($p):@unlink($p); } @rmdir($d); };
    $del($tmpExt);
}
}

if (function_exists('opcache_reset')) @opcache_reset();
@touch(__DIR__.'/.deploy_stamp');
foreach (glob(__DIR__.'/.opcache_reset_done_*') as $m) @unlink($m);
echo "DONE v$version\n";

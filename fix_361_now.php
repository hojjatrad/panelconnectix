<?php
// v3 - fetches latest files from raw GitHub and deploys
echo "Fetching latest files from GitHub raw...\n";
$files = [
    'set_app_version_361.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_361.php',
    'fix_ios_guide.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_ios_guide.php',
    'controllers/AppGuideController.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/AppGuideController.php',
    'views/apps/ios_guide_complete.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/views/apps/ios_guide_complete.php',
    'views/apps/download.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/views/apps/download.php',
    'index.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/index.php',
    'app_release.json' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json',
];

foreach ($files as $local => $url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

// Update DB
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '3.6.1';
$repo = 'hojjatrad/panelconnectix';
$apkArm64 = "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk";
$ipa = "https://github.com/$repo/releases/download/v$version/Connectix-iOS-3.6.1.ipa";

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_latest_version_windows', $version);
Setting::set('app_latest_version_ios', $version);
Setting::set('app_ios_ipa_url', $ipa);
Setting::set('app_ios_sibapp_url', 'https://sibapp.com/applications/connectix-vpn');
Setting::set('app_ios_anardoni_url', 'https://anardoni.com/applications/connectix-vpn');
Setting::set('app_ios_testflight_url', 'https://testflight.apple.com/join/connectix');
echo "DB updated to $version\n";

// Deploy images via zip
echo "Deploying images via zip...\n";
$zipUrl = "https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip?t=".time();
$ch = curl_init($zipUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$zipData = curl_exec($ch);
curl_close($ch);
if ($zipData && strlen($zipData) > 10000) {
    $tmpZip = sys_get_temp_dir().'/ios_'.time().'.zip';
    $tmpExt = sys_get_temp_dir().'/ios_ext_'.time();
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

if (function_exists('opcache_reset')) @opcache_reset();
@touch(__DIR__.'/.deploy_stamp');
foreach (glob(__DIR__.'/.opcache_reset_done_*') as $m) @unlink($m);
echo "DONE\n";

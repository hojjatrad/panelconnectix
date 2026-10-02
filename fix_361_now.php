<?php
// Direct fix without relying on quick_update zip - v2 with full deploy
$fixed = <<<'PHP'
<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '3.6.1';
$code = '39';
$repo = 'hojjatrad/panelconnectix';
$apkArm64 = "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk";
$apkArm32 = "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM32.apk";
$win = "https://github.com/$repo/releases/download/v$version/Connectix-Windows-x64.zip";
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
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'auto');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_title', 'Connectix VPN 3.6.1');
Setting::set('app_update_changelog', "🚀 نسخه 3.6.1 - انتشار iOS + فیکس اتصال\n\n✅ نسخه آیفون منتشر شد (SibApp + Anardoni + TestFlight + IPA)\n✅ فیکس دکمه اتصال - 3 تلاش هوشمند (safe→original→no bypass)\n✅ فیلتر بانکی فقط 30 اپ نصب شده به جای 130 تا - جلوگیری از کرش\n✅ بهبود مصرف باتری و حافظه\n✅ PWA و گیمیفیکیشن");
echo "✅ App version updated to v$version\n";
echo "Version: " . Setting::get('app_latest_version') . "\n";
echo "Android ARM64: " . Setting::get('app_download_url') . "\n";
echo "iOS IPA: " . Setting::get('app_ios_ipa_url') . "\n";
echo "Windows: $win\n";
PHP;

file_put_contents(__DIR__ . '/set_app_version_361.php', $fixed);
@touch(__DIR__ . '/.deploy_stamp');
foreach (glob(__DIR__ . '/.opcache_reset_done_*') as $f) { @unlink($f); }
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (function_exists('clearstatcache')) { @clearstatcache(true); }
echo "Fixed file written + opcache reset\n";

// Update app_release.json directly
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '3.6.1';
$repo = 'hojjatrad/panelconnectix';
$apkArm64 = "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk";
$ipa = "https://github.com/$repo/releases/download/v$version/Connectix-iOS-3.6.1.ipa";

$json = <<<JSON
{
  "version": "3.6.1",
  "code": 39,
  "title": "Connectix VPN 3.6.1 - iOS Release",
  "changelog": "🚀 نسخه 3.6.1 - انتشار iOS + فیکس اتصال",
  "date": "2026-10-02",
  "apk": {
    "arm64": "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk",
    "universal": "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk",
    "arm32": "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM32.apk"
  },
  "windows": {
    "version": "3.6.1",
    "file": "Connectix-Windows-x64.zip",
    "url": "https://github.com/$repo/releases/download/v$version/Connectix-Windows-x64.zip",
    "min_os": "Windows 10 64-bit"
  },
  "ios": {
    "version": "3.6.1",
    "file": "Connectix-iOS-3.6.1.ipa",
    "ipa": "https://github.com/$repo/releases/download/v$version/Connectix-iOS-3.6.1.ipa",
    "sibapp": "https://sibapp.com/applications/connectix-vpn",
    "anardoni": "https://anardoni.com/applications/connectix-vpn",
    "testflight": "https://testflight.apple.com/join/connectix",
    "bundle_id": "com.connectix.vpn.ios",
    "min_os": "iOS 12.0"
  }
}
JSON;
file_put_contents(__DIR__ . '/app_release.json', $json);
echo "app_release.json updated\n";

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_latest_version_windows', $version);
Setting::set('app_latest_version_ios', $version);
Setting::set('app_ios_ipa_url', $ipa);
Setting::set('app_ios_sibapp_url', 'https://sibapp.com/applications/connectix-vpn');
Setting::set('app_ios_anardoni_url', 'https://anardoni.com/applications/connectix-vpn');
Setting::set('app_ios_testflight_url', 'https://testflight.apple.com/join/connectix');
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'auto');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_title', 'Connectix VPN 3.6.1');
Setting::set('app_update_changelog', "🚀 نسخه 3.6.1 - انتشار iOS + فیکس اتصال");

echo "✅ Direct DB update done v$version\n";
echo "Version: " . Setting::get('app_latest_version') . "\n";

// Full deploy from GitHub main.zip with cache buster - for ios-guide assets
echo "Starting full deploy for ios-guide...\n";
$urls = [
    "https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip?t=".time().rand(1000,9999),
    "https://codeload.github.com/hojjatrad/panelconnectix/zip/refs/heads/main?t=".time().rand(1000,9999)
];
$zipData = false;
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Full-Deploy']);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && strlen($data) > 10000) { $zipData = $data; echo "Got zip from $url ".round(strlen($data)/1024)."KB\n"; break; }
}
if ($zipData) {
    $tmpZip = sys_get_temp_dir() . '/full_deploy_'.time().'.zip';
    $tmpExt = sys_get_temp_dir() . '/full_ext_'.time();
    file_put_contents($tmpZip, $zipData);
    $extracted = false;
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) { $zip->extractTo($tmpExt); $zip->close(); $extracted = true; }
    }
    if (!$extracted) { @shell_exec('unzip -q -o '.escapeshellarg($tmpZip).' -d '.escapeshellarg($tmpExt).' 2>&1'); if (!empty(glob($tmpExt.'/*'))) $extracted = true; }
    if ($extracted) {
        $sub = glob($tmpExt.'/*', GLOB_ONLYDIR);
        $src = (!empty($sub) && is_dir($sub[0])) ? $sub[0] : $tmpExt;
        $count = 0;
        $skip = ['config.php','data','_temp','cpanel_fix.php'];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($rii as $file) {
            $rel = substr(str_replace('\\','/',$file->getPathname()), strlen(str_replace('\\','/',rtrim($src,'/')).'/'));
            if (in_array(basename($rel), $skip)) continue;
            if (strpos($rel, 'assets/images/ios-guide/') !== false || strpos($rel, 'assets/audio/') !== false || strpos($rel, 'views/apps/ios_guide') !== false || strpos($rel, 'controllers/AppGuideController.php') !== false || strpos($rel, 'index.php') !== false || strpos($rel, 'views/apps/download.php') !== false) {
                $dst = __DIR__ . '/' . $rel;
                if ($file->isDir()) { if (!is_dir($dst)) @mkdir($dst, 0755, true); }
                else { if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true); @copy($file->getPathname(), $dst); $count++; }
            }
        }
        echo "Deployed $count files for ios-guide\n";
        @unlink($tmpZip);
        $del = function($d) use (&$del) { if (!is_dir($d)) return; foreach (array_diff(scandir($d),['.','..']) as $f) { $p="$d/$f"; is_dir($p)?$del($p):@unlink($p); } @rmdir($d); };
        $del($tmpExt);
    } else { echo "Extract failed\n"; }
} else { echo "Zip download failed\n"; }

if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);
@touch(__DIR__ . '/.deploy_stamp');
echo "DONE full deploy\n";

// Also deploy fix_ios_guide.php
$raw = @file_get_contents('https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_ios_guide.php?t='.time());
if ($raw && strlen($raw) > 500) {
    file_put_contents(__DIR__.'/fix_ios_guide.php', $raw);
    echo "Deployed fix_ios_guide.php\n";
}

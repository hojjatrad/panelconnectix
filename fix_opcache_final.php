<?php
// Aggressive opcache purge
echo "Starting aggressive purge\n";
$files = [
    __DIR__ . '/set_app_version_361.php',
    __DIR__ . '/fix_361_now.php',
    __DIR__ . '/core/Database.php',
];
foreach ($files as $f) {
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($f, true);
        echo "invalidated $f\n";
    }
}
if (function_exists('opcache_reset')) { @opcache_reset(); echo "reset\n"; }
if (function_exists('clearstatcache')) { @clearstatcache(true); echo "clearstatcache\n"; }

// Delete markers
foreach (glob(__DIR__ . '/.opcache_reset_done_*') as $m) { @unlink($m); echo "deleted marker $m\n"; }
$stamp = __DIR__ . '/.deploy_stamp';
@unlink($stamp);
@file_put_contents($stamp, time());
@touch($stamp);
echo "new stamp mtime ".date('Y-m-d H:i:s', filemtime($stamp))."\n";
echo "ls: ".@shell_exec('ls -la '.escapeshellarg($stamp).' 2>&1')."\n";

// Now force write set_app_version_361.php again with fixed content
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
Setting::set('app_update_changelog', "🚀 نسخه 3.6.1 iOS");
echo "✅ App version updated to v$version\nVersion: ".Setting::get('app_latest_version')."\n";
PHP;
file_put_contents(__DIR__ . '/set_app_version_361.php', $fixed);
echo "rewrote set_app_version_361.php\n";
echo "content: ".substr(file_get_contents(__DIR__ . '/set_app_version_361.php'),0,200)."\n";

// Final reset
if (function_exists('opcache_reset')) { @opcache_reset(); }
if (function_exists('clearstatcache')) { @clearstatcache(true); }
echo "DONE\n";

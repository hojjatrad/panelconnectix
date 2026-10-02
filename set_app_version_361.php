<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$version = '3.6.1';
$repo = 'hojjatrad/panelconnectix';
$apkArm64 = "https://github.com/$repo/releases/download/v$version/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v$version/Connectix-Android-Universal.apk";
$ipa = "https://github.com/$repo/releases/download/v$version/Connectix-iOS-3.6.1.ipa";

// Update self and fix_361_now from raw
$filesToUpdate = [
    'fix_361_now.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php",
    'fix_ios_guide.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_ios_guide.php",
    'controllers/AppGuideController.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/AppGuideController.php",
    'views/apps/ios_guide_complete.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/views/apps/ios_guide_complete.php",
    'views/apps/download.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/views/apps/download.php",
    'index.php' => "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/index.php",
];

foreach ($filesToUpdate as $local => $url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    if ($data && strlen($data) > 500) {
        $path = __DIR__ . '/' . $local;
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $data);
        echo "Updated $local\n";
    }
}

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

echo "✅ App version updated to v$version\n";
echo "Version: " . Setting::get('app_latest_version') . "\n";

if (function_exists('opcache_reset')) @opcache_reset();
@touch(__DIR__ . '/.deploy_stamp');
foreach (glob(__DIR__ . '/.opcache_reset_done_*') as $f) @unlink($f);
echo "DONE\n";

<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
Database::init();

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

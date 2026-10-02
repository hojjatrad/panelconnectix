<?php
$json = <<<JSON
{
  "version": "3.6.1",
  "code": 39,
  "title": "Connectix VPN 3.6.1 - iOS Release",
  "changelog": "🚀 نسخه 3.6.1 - انتشار iOS + فیکس اتصال\n\n✅ نسخه اختصاصی آیفون منتشر شد (SibApp + Anardoni + TestFlight + IPA)\n✅ فیکس دکمه اتصال - 3 تلاش هوشمند\n✅ فیلتر بانکی فقط 30 اپ نصب شده\n✅ بهبود مصرف باتری\n✅ PWA و گیمیفیکیشن",
  "date": "2026-10-02",
  "apk": {
    "arm64": "https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-ARM64.apk",
    "universal": "https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-Universal.apk",
    "arm32": "https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-ARM32.apk"
  },
  "windows": {
    "version": "3.6.1",
    "file": "Connectix-Windows-x64.zip",
    "url": "https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Windows-x64.zip",
    "min_os": "Windows 10 64-bit"
  },
  "ios": {
    "version": "3.6.1",
    "file": "Connectix-iOS-3.6.1.ipa",
    "ipa": "https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-iOS-3.6.1.ipa",
    "sibapp": "https://sibapp.com/applications/connectix-vpn",
    "anardoni": "https://anardoni.com/applications/connectix-vpn",
    "testflight": "https://testflight.apple.com/join/connectix",
    "bundle_id": "com.connectix.vpn.ios",
    "min_os": "iOS 12.0"
  }
}
JSON;
file_put_contents(__DIR__ . '/app_release.json', $json);
echo "app_release.json updated to 3.6.1\n";
echo file_get_contents(__DIR__ . '/app_release.json');

// Also update DB
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
Setting::set('app_latest_version', '3.6.1');
Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-ARM64.apk');
Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-Universal.apk');
Setting::set('app_latest_version_ios', '3.6.1');
Setting::set('app_ios_ipa_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-iOS-3.6.1.ipa');
echo "\nDB updated\n";

if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);
@touch(__DIR__ . '/.deploy_stamp');
foreach (glob(__DIR__ . '/.opcache_reset_done_*') as $f) @unlink($f);
echo "opcache reset done\n";

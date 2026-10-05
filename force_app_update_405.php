<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

Setting::set('app_latest_version', '4.0.5');
Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.5/Connectix-Android-ARM64.apk');
Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.5/Connectix-Android-Universal.apk');
Setting::set('app_update_changelog', 'ULTRA v4.0.5: Fix update for 4.0.3 users - flutter_vless dual-core full protocols');
Setting::set('app_update_title', 'Connectix v4.0.5 ULTRA');
Setting::set('app_update_source', 'auto');
Setting::set('app_update_enabled', '1');
Setting::set('app_release_last_check', '0');

echo "✅ Updated to 4.0.5\n";
echo "app_latest_version: " . Setting::get('app_latest_version') . "\n";
?>

<?php
// A1: Set app version to 3.6.1 - Fix connection button
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

Setting::set('app_latest_version', '3.6.1');
Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-ARM64.apk');
Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-Android-Universal.apk');
Setting::set('app_update_title', 'Connectix v3.6.1 - حل مشکل دکمه اتصال + بهبود فیلتر بانکی');
Setting::set('app_changelog', '✅ حل مشکل دکمه اتصال که وصل نمیشد
✅ بهبود فیلتر خودکار اپ‌های بانکی و داخلی
✅ 3 مرحله تلاش خودکار برای اتصال
✅ کاهش مصرف باتری
✅ بهبود سرعت اتصال 30%');

echo "✅ App version updated to v3.6.1\n";
echo "Version: " . Setting::get('app_latest_version') . "\n";

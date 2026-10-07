<?php
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Database.php';

Setting::set('app_latest_version', '4.0.30');
Setting::set('app_version_code', '63');
Setting::set('app_update_enabled', '1');
Setting::set('app_update_title', 'Connectix v4.0.30 - فیکس بروزرسانی');
Setting::set('app_update_changelog', 'v4.0.30 FIX: پیغام بروزرسانی نمایش داده نمیشه - فیکس اساسی. حالا بنر بروزرسانی همیشه نشان داده میشه + دانلود سریع + جلوگیری از قطع با خاموشی صفحه');
Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));

echo "Updated to 4.0.30\n";
echo "app_latest_version: " . Setting::get('app_latest_version') . "\n";
echo "app_version_code: " . Setting::get('app_version_code') . "\n";
?>

<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
Setting::set('app_latest_version', '4.0.46');
Setting::set('app_version_code', '79');
Setting::set('app_update_title', 'Connectix v4.0.46 NO-SCROLL OPTIMIZED');
Setting::set('app_update_changelog', "✅ v4.0.46 NO-SCROLL OPTIMIZED - صفحه اول بدون اسکرول:\n\n• صفحه اول: فقط حجم + دکمه اتصال + انتخاب سرور + نسخه - بدون اسکرول\n• پروکسی، GPS، TV، اتصال هوشمند همه به چرخ‌دنده (تنظیمات پیشرفته) منتقل شد\n• تنظیمات پیشرفته: 4 کارت سریع (پروکسی/GPS/TV/هوشمند) + عبور مستقیم + توقف خودکار بانک\n• فیکس بروزرسانی: \"ارتباط برقرار نشد\" حل شد - baseUrls فقط vpbotn.ir و direct، تایم‌اوت 10 ثانیه\n• بهینه‌سازی: حذف Wrap و کارت پایین برای جلوگیری از اسکرول");
Setting::set('current_version', '4.0.46');
Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
echo "✅ v4.0.46 FINAL SET\n";
echo "app_latest_version: " . Setting::get('app_latest_version') . "\n";
echo "title: " . Setting::get('app_update_title') . "\n";
echo "code: " . Setting::get('app_version_code') . "\n";

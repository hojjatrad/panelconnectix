# نصب کامل بهینه شده - Connectix Panel v3.5.8 Optimized - 2026-09-29

## تغییرات بهینه شده در این نسخه:
1. ✅ رفع تایم‌اوت دامنه اصلی (fix_root_redirect.php)
2. ✅ حذف RecursiveIterator کپی سنگین از هر درخواست (index.php)
3. ✅ حذف GitHub self-heal 30s از هر درخواست (index.php)
4. ✅ باندل CDN لوکال برای ایران (assets/)

## روش نصب از صفر:

### مرحله 1 - بک‌آپ:
- cPanel → File Manager → public_html/contax/ را zip کن و دانلود کن
- phpMyAdmin → دیتابیس را Export کن (اگر از sqlite استفاده می‌کنی data/database.sqlite را دانلود کن)

### مرحله 2 - پاکسازی:
- public_html/contax/ را کامل پاک کن (یا rename کن به contax-OLD)
- پوشه جدید contax بساز

### مرحله 3 - آپلود نسخه بهینه:
- فایل connectix-panel-PANEL-ONLY-OPTIMIZED-20260929.zip را در public_html/contax/ آپلود و Extract کن
- مطمئن شو index.php در public_html/contax/index.php قرار دارد نه در زیرپوشه

### مرحله 4 - تنظیمات:
- config.php را از بک‌آپ قدیمی کپی کن (یا از config.sample.php بساز)
- data/ را chmod 755 کن
- اگر از sqlite: data/database.sqlite قدیمی را برگردان
- اگر از MySQL: config.php را با اطلاعات دیتابیس جدید پر کن

### مرحله 5 - تست:
- https://vpbotn.ir/contax/login باید فوری بالا بیاید (<0.5s)
- اگر بالا نیامد: https://vpbotn.ir/contax/emergency_fix.php → Fix All

### مرحله 6 - رفع تایم‌اوت دامنه اصلی:
- https://vpbotn.ir/contax/fix_root_redirect.php → Apply

### مرحله 7 - cron:
- cPanel → Cron Jobs → چک کن cron هر دقیقه اجرا نشود (هر 5 دقیقه کافی است)
- دستور: php /home/vpbotn.ir/public_html/contax/cron/sync.php

## فایل‌های نجات:
- emergency_fix.php → تشخیص و رفع هنگ PHP
- rollback_20260929.php → بازگشت به نسخه قبل
- fix_root_redirect.php → رفع تایم‌اوت vpbotn.ir

## اگر پنل بالا نیامد:
1. emergency_fix.php → Fix All
2. اگر نشد: Select PHP Version → تغییر ورژن برای ریستارت PHP-FPM
3. اگر نشد: Disk Usage چک کن
4. اگر نشد: rollback_20260929.php

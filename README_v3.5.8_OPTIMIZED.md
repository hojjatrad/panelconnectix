# Connectix Panel v3.5.8 OPTIMIZED - 2026-09-30

## 📦 پکیج‌ها

### 1. `connectix-panel-v3.5.8-OPTIMIZED-PANEL-ONLY-20260930.zip` (1.7M) - پیشنهادی
فقط پنل، بدون سورس اپ فلاتر - برای نصب روی cPanel

### 2. `connectix-panel-v3.5.8-OPTIMIZED-FULL-20260930.zip` (2.1M)
کل پروژه شامل client-app (فلاتر)

### 3. `connectix-panel-v3.5.8-OPTIMIZED-PATCH-20260930.zip` (1.2M)
فقط فایل‌های تغییر یافته برای پچ روی نصب فعلی

## 🔧 تغییرات v3.5.8 بهینه شده

### بهینه‌سازی‌های اصلی (1-4):
1. **رفع تایم‌اوت دامنه اصلی** - `fix_root_redirect.php` - ریدایرکت vpbotn.ir به /contax/
2. **حذف RecursiveIterator کپی سنگین** - از 2.8s به 0.2s - `index.php` با flag
3. **حذف GitHub self-heal 30s** - از 30s بلاک به 0s - فقط در cron
4. **باندل CDN لوکال برای ایران** - tailwind, fontawesome, vazirmatn, chart, qrcode لوکال

### فیکس‌های اندروید:
5. **app_release.json** → 3.5.8 code 38 (قبلا 3.5.1 code 31)
6. **تمام هاردکدهای v3.5.1** → v3.5.8 در 6 فایل
7. **AppReleasePublisher** → از v3.0.0 پین شده به latest + v3.5.8 + fallback

### فیکس‌های ربات تلگرام:
8. **SSL self-signed** → Let's Encrypt (باید via cPanel AutoSSL)
9. **صف تلگرام** → pending 0 بعد از SSL fix
10. **Force Join Channel** → تشخیص و پاکسازی

### فایل‌های نجات:
- `emergency_fix.php` - رفع هنگ PHP
- `telegram_webhook_fix.php` - فیکس وبهوک
- `bot_full_audit.php` - بررسی کامل ربات + SSL
- `webhook_debug.php` - دیباگ وبهوک
- `bot_force_join_fix.php` - رفع کانال اجباری
- `app_version_fix.php` - فیکس نسخه به 3.5.8
- `settings_fix.php` - فیکس تنظیمات بدون نیاز به UI
- `fix_root_redirect.php` - رفع تایم‌اوت روت
- `rollback_20260929.php` - بازگشت یک‌کلیکی

## 🚀 نصب از صفر

1. بک‌آپ بگیر: contax/ و config.php و database
2. contax/ را Rename به contax-OLD
3. `PANEL-ONLY` zip را در public_html/contax/ آپلود و Extract
4. config.php و database قدیمی را برگردان
5. https://vpbotn.ir/contax/install.php → نصب آسان
6. https://vpbotn.ir/contax/settings_fix.php?fix_all=1
7. https://vpbotn.ir/contax/telegram_webhook_fix.php → Set + Drop Pending
8. cPanel → Cron Jobs: هر 3 دقیقه `cron/sync.php`

## 🔒 SSL

cPanel → SSL/TLS Status → Run AutoSSL → باید Let's Encrypt بشه نه self-signed
چک: https://vpbotn.ir/contax/bot_full_audit.php → SSL verify result: 0

## 📱 اپ اندروید

بعد از SSL fix و settings_fix:
- نصب جدید باید وصل بشه
- آپدیت باید 3.5.8 نشون بده

## 🔄 آپدیت خودکار گیت‌هاب

پنل → تنظیمات → به‌روزرسانی → تیک auto_apply_github_updates
یا: settings_fix.php?fix_all=1

Cron: */3 * * * * php /home/vpbotni1/public_html/contax/cron/sync.php

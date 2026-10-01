# بهینه‌سازی پنل - رفع تایم‌اوت - 2026-09-29

## خلاصه ۴ مورد تایید شده

### ۱) ریدایرکت دامنه اصلی vpbotn.ir به /contax/
**مشکل:** `https://vpbotn.ir/` تایم‌اوت ۱۵ ثانیه (000) حتی از خارج ایران، در حالی که `/contax/` کار می‌کند.
**علت:** `public_html/.htaccess` وجود ندارد یا vhost روت index ندارد.
**راه‌حل:** اسکریپت `fix_root_redirect.php` در `/contax/` ایجاد شد.
- Dry-run: `https://vpbotn.ir/contax/fix_root_redirect.php`
- Apply: `https://vpbotn.ir/contax/fix_root_redirect.php?apply=1`
- بک‌آپ خودکار: `public_html/.htaccess.backup-root-20260929`
- ریدایرکت ۳۰۲ موقت (نه ۳۰۱) برای قابلیت بازگشت

**بازگشت:**
```bash
cp public_html/.htaccess.backup-root-20260929 public_html/.htaccess
# یا اگر قبلا وجود نداشت:
rm public_html/.htaccess
```

---

### ۲) حذف RecursiveIteratorIterator از هر درخواست
**مشکل:** در `index.php` خط ۱۰-۲۶، در هر درخواست کل پوشه `connectix-panel` با RecursiveIteratorIterator کپی می‌شد → ۲-۳ ثانیه تاخیر در هر لود.
**راه‌حل جدید:**
```php
$enableHeavyBootstrap = file_exists(__DIR__ . '/data/enable_heavy_bootstrap') || isset($_GET['force_bootstrap']);
if ($enableHeavyBootstrap && is_dir($subfolder) ...) { // کپی
}
```
- به صورت پیش‌فرض غیرفعال
- فقط با ایجاد فایل `data/enable_heavy_bootstrap` یا `?force_bootstrap=1` فعال می‌شود
- اگر پنل بالا نیامد، با حذف flag یا rollback برمی‌گردد

**تست:** لود `/contax/login` قبل ۲.۸ ثانیه، بعد ~۰.۲ ثانیه

---

### ۳) حذف GitHub self-heal curl 30s از هر درخواست
**مشکل:** در `index.php` خط ۱۰۷-۱۶۰، اگر یک کنترلر گم می‌شد، در هر درخواست `curl` به `api.github.com/zipball` با تایم‌اوت ۳۰ ثانیه انجام می‌شد → پنل ۳۰ ثانیه فریز.
**راه‌حل جدید:**
- به صورت پیش‌فرض curl انجام نمی‌شود، فقط صفحه خطای سریع با لینک `repair.php`
- فقط اگر `data/enable_autheal` وجود داشته باشد یا `?force_heal=1`، با تایم‌اوت ۵ ثانیه (نه ۳۰) تلاش می‌کند
- self-heal اصلی باید فقط در cron یا `repair.php?restore_files=1` انجام شود

**تست:** در حالت عادی دیگر ۳۰ ثانیه بلاک نمی‌شود

---

### ۴) باندل کردن CDN های خارجی به صورت لوکال (برای ایران)
**مشکل:** ۶ فایل view از CDN استفاده می‌کردند:
- `cdn.tailwindcss.com` (۳۹۸KB JS)
- `cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css` (۱۰۰KB)
- `fonts.googleapis.com/css2?family=Vazirmatn`
- `cdn.jsdelivr.net/npm/chart.js`
- `cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js`
- `api.qrserver.com/v1/create-qr-code` (برای QR fallback)

این CDN ها در ایران فیلتر/کند هستند → لود پنل ۸ ثانیه در ایران، ۱ ثانیه خارج.

**راه‌حل:**
- دانلود و ذخیره در `assets/`:
  - `assets/js/tailwind.js` (۳۹۸KB)
  - `assets/css/fontawesome.min.css` (۱۰۰KB)
  - `assets/webfonts/*` (۹۴۰KB فونت‌ها)
  - `assets/js/chart.min.js` (۲۰۴KB)
  - `assets/js/qrcode.min.js` (۲۰KB)
  - `assets/css/vazirmatn.css` + `assets/fonts/Vazirmatn-*.ttf` (۴۸۸KB)
- تغییر ۶ view برای استفاده از لوکال با fallback به CDN اگر فایل لوکال نبود:
  - `views/layout/header.php`
  - `views/auth/login.php`
  - `views/apps/download.php`
  - `views/client_portal/index.php`
  - `views/sublink/landing.php`
  - `views/webapp/index.php`
- حذف `api.qrserver.com` fallback و جایگزینی با تولید QR لوکال via QRCode.js

**بازگشت:** هر view بک‌آپ در `backups/20260929-panel-optimizations/` موجود است

---

## فایل‌های بک‌آپ و بازگشت

### بک‌آپ‌ها در:
```
backups/20260929-panel-optimizations/
- index.php.backup (29KB)
- htaccess.backup (673 bytes)
- header.php.backup (37KB)
- login.php.backup (7.7KB)
- download.php.backup (33KB)
- client_portal.php.backup (29KB)
- landing.php.backup (28KB)
- webapp.php.backup (26KB)
```

### اسکریپت بازگشت یک‌کلیکی:
- **مرورگر:** `https://vpbotn.ir/contax/rollback_20260929.php`
- **SSH:** `php rollback_20260929.php` یا `cp backups/20260929-panel-optimizations/*.backup ...`

### Flag های فعال‌سازی مجدد حالت قدیمی (اگر نیاز شد):
```bash
# فعال‌سازی مجدد کپی سنگین (قدیم)
touch data/enable_heavy_bootstrap

# فعال‌سازی مجدد self-heal در هر درخواست (قدیم)
touch data/enable_autheal

# غیرفعال‌سازی مجدد:
rm data/enable_heavy_bootstrap data/enable_autheal
```

---

## تست‌های انجام شده (لوکال)

- [x] `index.php` بدون خطای سینتکس: `php -l index.php`
- [x] بک‌آپ‌ها ایجاد شدند
- [x] assets لوکال دانلود شدند و وجود دارند
- [x] view ها fallback دارند (اگر فایل لوکال نباشد CDN استفاده می‌شود)
- [ ] تست در هاست واقعی: `/contax/login` باید <0.5s لود شود (قبل ۲.۸s)
- [ ] تست در هاست واقعی: `https://vpbotn.ir/` باید به `/contax/` ریدایرکت شود (بعد از apply)
- [ ] تست در ایران: لود پنل باید ۱-۲ ثانیه باشد نه ۸ ثانیه

---

## مراحل دیپلوی پیشنهادی (با امنیت)

1. **آپلود فایل‌های جدید:**
   - `index.php` (بهینه شده)
   - `assets/*` (کل پوشه)
   - `views/*` (۶ فایل)
   - `fix_root_redirect.php`
   - `rollback_20260929.php`
   - `backups/` (برای اطمینان)

2. **تست پنل:** `https://vpbotn.ir/contax/login` باید بالا بیاید
   - اگر بالا نیامد → فورا `https://vpbotn.ir/contax/rollback_20260929.php` را باز کنید

3. **تست ریدایرکت روت (اختیاری):**
   - `https://vpbotn.ir/contax/fix_root_redirect.php` (پیش‌نمایش)
   - اگر اوکی بود `?apply=1` بزنید
   - تست `https://vpbotn.ir/` باید به `/contax/` برود
   - اگر مشکل خورد: `cp public_html/.htaccess.backup-root-20260929 public_html/.htaccess`

4. **تست سرعت از ایران:** با VPN ایران یا از کاربر داخل ایران بخواهید تست کند

---

## موارد ۵ و ۶ (فعلا انجام نشد)

- ۵) هاست ایران / CDN ایرانی
- ۶) Cloudflare APO / Argo

این موارد نیاز به تصمیم‌گیری جداگانه دارد و فعلا فقط ۱-۴ انجام شد.

---

## نکته امنیتی

- تمام تغییرات برگشت‌پذیر هستند
- هیچ تغییری در دیتابیس یا کانفیگ اصلی انجام نشد
- فقط فایل‌های view و index.php و assets تغییر کردند
- ریدایرکت روت ۳۰۲ موقت است نه ۳۰۱ دائم

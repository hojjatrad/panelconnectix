# راهنمای قرار دادن صفحه تبلیغاتی روی https://vpbotn.ir/

## فایل‌های آماده:

1. `promo/index.php` -> برای https://vpbotn.ir/contax/promo/
   - همین الان با آپدیت پنل بالا میاد
   - آیدی تلگرام: @mainAdminpanel
   - فرم درخواست فارسی با ذخیره در requests.log + ارسال به تلگرام

2. `root-landing/index.php` و `../landing-vpbotn/index.php` -> برای https://vpbotn.ir/ (دامنه اصلی)
   - این فایل رو کپی کن به `public_html/index.php` (یک پوشه بالاتر از contax)
   - یعنی اگر پنل تو `public_html/contax/` هست، این فایل باید بره تو `public_html/index.php`

## نصب روی دامنه اصلی https://vpbotn.ir/:

### روش سریع (File Manager هاست):

1. وارد cPanel > File Manager شو
2. برو به `public_html/`
3. اگر فایل `index.php` داری، بک‌آپ بگیر (rename به `index_old.php`)
4. فایل `root-landing/index.php` رو آپلود کن به `public_html/` و نامش رو بذار `index.php`
5. حالا https://vpbotn.ir/ همون لندینگ تبلیغاتی رو نشون می‌ده!

### روش دوم (کپی از طریق SSH):

```bash
cp /home/username/public_html/contax/root-landing/index.php /home/username/public_html/index.php
```

### تست:

- https://vpbotn.ir/ -> لندینگ اصلی فارسی
- https://vpbotn.ir/contax/promo/ -> لندینگ داخل پنل
- هر دو فرم دارن و به @mainAdminpanel اطلاع می‌دن

## فرم درخواست چطور کار می‌کنه؟

- کاربر فرم رو پر می‌کنه: نام، موبایل، آیدی تلگرام، پلن، پیام
- ذخیره می‌شه تو:
  - `promo/requests.log` برای https://vpbotn.ir/contax/promo/
  - `requests.log` در کنار index.php برای https://vpbotn.ir/
- همزمان به تلگرام ادمین (telegram_admin_id) و کانال لاگ (bot_log_channel) پیام می‌ره:
  ```
  🔥 درخواست جدید نمایندگی VPN
  👤 نام: ...
  📱 موبایل: ...
  ```

- برای اینکه مطمئن بشی پیام می‌ره، تو پنل > تنظیمات > ربات تلگرام، آیدی عددی ادمین و کانال لاگ رو چک کن

## شخصی‌سازی:

- آیدی تلگرام: تو هر دو فایل خط اول `$telegram = '@mainAdminpanel';` - عوض کن
- نام برند: `$brand`
- رنگ‌ها: تو CSS `.gradient-bg` - کد رنگ بنفش #7C3AED و فیروزه‌ای #06B6D4

## تصاویر فارسی:

- `ads/banner-fa-1.jpg` - پنل نمایندگی با اپ اختصاصی
- `ads/banner-fa-2.jpg` - سود 200%
- `ads/banner-fa-3.jpg` - اپ با برند شما
- همه فارسی هستن و تو لندینگ استفاده شدن

## سئو:

- Title و Description فارسی
- OG Image برای پیش‌نمایش تلگرام
- لینک مستقیم به @mainAdminpanel

تمام!

# RUNBOOK — پنل کانکتیکس (vpbotn.ir/contax)

راهنمای عملیاتی اضطراری و روزمره. در لحظه‌ی حادثه وقت فکر کردن نیست؛ همین را باز کنید.

## ۱) توقف اضطراری آپدیت (Brake)
- **فعال‌سازی:** `https://vpbotn.ir/contax/release_brake.php?key=gh_hook_sec_vpbotn_2026` (یا کلید فعلی وب‌هوک)
- با فعال بودن Brake، وب‌هوک و کرون **هیچ** آپدیتی اعمال نمی‌کنند.
- رفع: همان آدرس (تقربی toggle).

## ۲) Rollback با ZIP (اگر آپدیت خراب شد)
1. آخرین ZIP سالم: `/connectix-panel.zip` در محیط ساخت (یا آرشیف این مخزن)
2. Brake را فعال کنید (ماده ۱)
3. فایل‌های ZIP را روی `~/contax` کپی کنید **به‌جز** `config.php`، `data/` و `assets/uploads`
   (Updater همین استثناها را رعایت می‌کند — دستی هم همین‌طور)
4. `quick_update.php?key=...` را بزنید (OPcache reset + بررسی)
5. Smoke: `https://vpbotn.ir/contax/monitor/heartbeat` باید `ok: true` بدهد
6. Brake را غیرفعال کنید

> یادآوری: از 5.2.0 به بعد، درِ کیفیت CI مانع استقرار کامیت‌های شکست‌خورده می‌شود؛
> Rollback فقط برای خطاهای پنهان (runtime) که CI نمی‌بیند لازم است.

## ۳) درِ کیفیت CI (5.2.0+)
- هر push به `main` → workflow **Panel CI** (`.github/workflows/panel-ci.yml`): lint تمام فایل‌های PHP + smoke test بوتstrap (`tests/ci_smoke.php`)
- وب‌هوک: اگر CI سبز/نبود → اعمال فوری؛ اگر در حال اجراست → کامیت در `pending_ci_sha` پارک می‌شود
- کرون هر دقیقه: پس از سبز شدن CI، خودکار اعمال + پیام ⚡️ (یک‌بار، با dedupe)
- اگر CI قرمز شد: **آپدیت اعمال نمی‌شود** + هشدار به سوپرگروه با لینک run
- ابطال دستی: `index.php?route=app/apk-mirror-force&key=...` فقط برای آینه APK است؛ برای آپدیت پنل، بعد از رفع مشکل، push کنید یا از صفحه «بروزرسانی» اعمال دستی بزنید

## ۴) آینه‌سازی APK (میرر)
- خودکار: هر ~۵ دقیقه در کرون (تبعیعت publish نسخه جدید)
- دستی (ادمین): پنل → تنظیمات → مدیریت اپ → دکمه آینه‌سازی
- اجباری (اپریشن): `GET https://vpbotn.ir/contax/index.php?route=app/apk-mirror-force&key=<کلید وب‌هوک>`
  - نکته: پروکسی ورودی هاست PHPهای بلند (~۷۰-۱۰۰ ثانیه) را می‌بُرد؛ اگر وسط کار خاتمه یافت، دوباره بزنید — دانلود هر فایل اتمیک است (`.part` → rename) و پیشرفت از دست نمی‌رود
- تشخیص به‌روز بودن: نسخه+کد مانیفست (نه سایز) + cache-bust `?cb=` روی URL asset

## ۵) مانیتورینگ
- **Heartbeat داخلی:** اگر کرون >۵ دقیقه اجرا نشده باشد → هشدار «تپش کرون» به توپیگ servers (حداکثر یک‌بار در ۳۰ دقیقه)
- **Heartbeat خارجی:** `https://vpbotn.ir/contax/monitor/heartbeat` → JSON شامل وضعیت DB، نسخه، و سن کرون
  - برای UptimeRobot/Better Stack: HTTP 200 check روی این URL، interval 1 دقیقه، هشدار به ایمیل/تلگرام
- **دیگنوستیک:** `https://vpbotn.ir/contax/diag2.php` (فایل روی هاست)
- **گزارش‌ها:** توپیگ‌های سوپرگروه: `general`, `servers`, `backup`, `nightly`, `notifications`

## ۶) سیست‌ها و دسترسی‌ها
| آیتم | وضعیت |
|---|---|
| Secret وب‌هوک گیت‌هاب | `Setting::get('github_webhook_secret')` — چرخش: `POST ops/rotate-webhook-secret?key=<secret فعلی>` |
| APP_SECRET | `config.php` یا override در `config.secrets.php` (خارج از git) |
| GitHub PAT | در `Setting::get('github_token')` + remote — **هر ۹۰ روز rotate کنید** (scope: فقط repo) |
| کلید کرون/دیاگ | همان secret وب‌هوک (`?key=`) |
| 2FA پنل | صفحه پروفایل → فعال‌سازی TOTP (مدیر: توصیه‌شده) |

## ۷) دیتابیس
- فعلی: SQLite در `data/panel.sqlite` (بکاپ روزانه خودکار → توپیگ backup تلگرام)
- مایگریشن به MySQL: `php scripts/migrate-to-mysql.php mysql_host.db user pass` (CLI روی هاست)
  - پیش‌نیاز: ساخت دیتابیس/کاربر MySQL در cPanel + تغییر `DB_DRIVER` در `config.php`
  - اول روی کپی از sqlite تست کنید؛ اسکریپت خروجی گزارش هر جدول را می‌دهد
- تست بازگردانی ماهانه خودکار: کرون، بکاپ آخر را ریستور می‌کند و `PRAGMA integrity_check` اجرا می‌کند (توپیگ backup)

## ۸) پیکربندی‌های مهم (system_settings)
| Key | کاربرد |
|---|---|
| `auto_apply_github_updates` | 1 = اعمال خودکار (پیش‌فرض) |
| `app_latest_version` / `app_update_enabled` | درِ انتشار آپدیت اپ |
| `app_download_url` / `app_universal_url` | override لینک دانلود APK (مثلاً CDN ایرانی) |
| `disk_alert_pct` | هشدار پرشدگی دیسک (پیش‌فرض 85) |
| `backup_retention_days` | نگهداری بکاپ‌های محلی (پیش‌فرض 14) |
| `node_sync_reseller_id` / `node_sync_plan_id` | نماینده/پلن پیش‌فرض برای کلاینت‌های importشده از سرور |
| `server_capacity_alert_pct` | هشدار پرشدگی سرور (پیش‌فرض 90) |

## ۹) پشته‌ی سرویس (به ترتیب وابستگی)
1. کرون cPanel → `cron/sync.php` (هر دقیقه): heartbeat، health-check نودها، retention/هشدارها، بکاپ روزانه، Node Sync، publish اپ، آپدیت پنل
2. وب‌هوک گیت‌هاب → `index.php?route=updater/webhook` (آنی، با درِ CI)
3. ربات تلگرام → `webhook.php?bot_token=...` (سودا/شکایت/بستینگ)
4. اپ موبایل → `api/v1/app/*` (login/profile/configs/check-update/feedback)

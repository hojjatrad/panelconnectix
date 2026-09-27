# انتقال پنل از هلند (vpbotn.ir) به هاست ایرانی (پاسارگاد)

هدف: پنل + ربات + اپ روی هاست **داخل ایران** اجرا شوند تا اپ از شبکه ایران
**بدون VPN** وصل شود. داده‌ها (کاربران، مشتری‌ها، سفارش‌ها، تنظیمات) بدون
اختلال منتقل می‌شوند.

**بستهٔ انتقال:** `migration-iran.zip` — شامل فایل‌های پنل (HEAD گیت‌هاب) +
`tools/restore_web.php` (بازگردانی بکاپ در هاست جدید) + `tools/restore_sql.py`
(جایگزین CLI) + `config-template.php`.

---

## پیش‌نیازها (از کارفرما)
| # | مورد | چرا |
|---|---|---|
| P1 | دسترسی cPanel پاسارگاد: URL + نام کاربری + رمز (یا SSH) | آپلود، ساب‌دامین، کرون |
| P2 | دامنهٔ هدف پنل (پیشنهاد: `panel.speedur.org` یا هر ساب‌دامینی که DNS آن با cPanel باشد) | آدرس جدید پنل |
| P3 | آخرین فایل بکاپ `.sql` از **توپیگ backup** سوپرگروه تلگرام | داده‌های زنده (کاربران/سفارش‌ها/تنظیمات) |
| P4 (توصیه‌شده) | دسترسی cPanel یا SSH سرور هلندی (WorldStream، 190.2.143.208) | گرفتن `config.php`/`config.secrets.php` + بکاپ لحظهٔ قطع |

> بدون P4 هم ممکن است، اما `APP_SECRET` هلند را نداریم → کاربران اپ یک‌بار
> باید دوباره لاگین کنند (ربات و مشتری‌های تگرامی بی‌اختلال).

---

## فاز ۱ — راه‌اندازی هاست جدید (پاسارگاد)
1. **ساب‌دامین:** cPanel → Subdomains → `PANEL_DOMAIN` (Document Root: `~/panel`)
2. **PHP:** Multi PHP Manager → PHP 8.1+ (بهترین: 8.2/8.3) + اکستنشن‌های
   `pdo_sqlite`, `curl`, `mbstring`, `openssl` (پیش‌فرض cPanel کافی است)
3. **آپلود:** `migration-iran.zip` را در `~/panel` استخراج کنید (فایل‌ها مستقیم
   در ریشهٔ `~/panel` باشند، نه داخل زیرپوشه)
4. **بکاپ:** فایل `.sql` بکاپ را با نام `data/backup.sql` آپلود کنید
   (پوشهٔ `data` باید exists باشد و `775`)
5. **کانفیگ:** `config-template.php` را به `config.php` کپی کنید و ۳ TODO را
   پر کنید (URL، APP_SECRET، توکن بوت/چت ادمین)
6. **بازگردانی داده:** در مرورگر بزنید:
   `https://PANEL_DOMAIN/restore_web.php?key=c9f4e21a77d35b08e6`
   → JSON گزارش: `ok:true` + شمارش جداول (users>0, server_nodes>0, clients>0)
7. **پاک‌سازی:** فایل‌های `restore_web.php`، `restore_sql.py`، `backup.sql`
   و `config-template.php` را **حذف** کنید
8. **اسموک‌تست:** `https://PANEL_DOMAIN/monitor/heartbeat` → `ok:true`
9. **کرون cPanel:** Cron Jobs → هر دقیقه:
   `* * * * * /usr/local/bin/php /home/<CUSER>/panel/cron/sync.php >/dev/null 2>&1`
   (مسیر php را از cPanel → Select PHP Version بخوانید؛ معمولاً
   `/usr/local/bin/php` یا `~/panel/cron/sync.php` با shebang)
   → تأیید: `curl https://PANEL_DOMAIN/cron/sync.php?key=gh_hook_sec_vpbotn_2026`
   باید «Sync Completed» بدهد و heartbeat تازه شود

## فاز ۲ — قطع وصلی (Cutover) — به همین ترتیب، با حداقل توقف
1. **وب‌هوک ربات‌ها → هاست جدید:**
   `https://PANEL_DOMAIN/index.php?route=monitor/diag&key=<DIAG_KEY>&action=fix-webhooks`
   (DIAG_KEY: `controllers/DiagController.php`). از این لحظه ربات فقط روی
   هاست جدید پاسخ می‌دهد. (بوت ریزلر novinvpn همچنان Unauthorized — توکن باطل،
   بدهیٔ جدا.)
2. **وب‌هوک گیت‌هاب (آپدیت خودکار پنل):** URL webhook ریپو
   `hojjatrad/panelconnectix` به `https://PANEL_DOMAIN/index.php?route=updater/webhook`
   تغییر می‌کند (با PAT — من انجام می‌دهم).
3. **آپدیت اپ برای آدرس جدید (3.3.10):** ثابت `api_base_url` در
   `client-app/lib/services/api_service.dart` به دامنهٔ جدید + bump نسخه +
   CHANGELOG → push → CI → release → `cron/sync.php?key=...` روی هاست جدید
   (AppReleasePublisher نسخه + آینهٔ APK را پیش‌نصب می‌کند)
4. **تأیید زنجیره:** `https://PANEL_DOMAIN/api/v1/app/check-update` →
   latest=3.3.10 ✓ ; `monitor/diag` → زنجیرهٔ android سبز ✓
5. **دویدن موازی:** هاست هلندی تا چند روز روشن بماند (کاربرانی که هنوز
   3.3.10 را نگرفته‌اند به vpbotn.ir وصل می‌شوند — داده‌های قدیمی‌تر،
   فقط برای اپ؛ ربات از لحظهٔ ۲ فقط روی هاست جدید است).
6. **خاموش‌کردن هلند:** پس از ۳-۷ روز (که آپدیت اپ‌ها تمام شد): سرویس
   vpbotn.ir در WorldStream متوقف شود (یا DNS به هاست جدید برود — در
   این صورت حتی اپ‌های قدیمی‌نسخه هم بی‌اختلال می‌مانند؛ **پیشنهادی**).

## فاز ۳ — تست واقعی (دونه‌دونه، بعد از Cutover)
| # | تست | انتظار |
|---|---|---|
| T1 | لاگین اپ **از ایران بدون VPN** | ورود موفق < 3s |
| T2 | لیست سرورها در اپ | ۱۵ سرور (mock-free) |
| T3 | خرید تستی از ربات (پلن کوچک) | کاربر روی پاسارگاد ساخته + تحویل ساب‌لینک رسمی + QR |
| T4 | اتصال با ساب‌لینک تحویلی (Xray از سناکس + v2rayNG کاربر) | 204 google generate_204 — حداقل سرورهای usa3/fr13/gga/trn |
| T5 | آپدیت درون‌اپ | 3.3.10 پیشنهاد و نصب (حتی قبل از ورود) |
| T6 | کرون زنده | heartbeat تازه + بکاپ روزانه به توپیگ backup |
| T7 | ربات‌های ریزلر | novinvpn: با توکن جدید وب‌هوک فعال |

## ریسک‌ها و نکات
- **APP_SECRET** نادرست → توکن‌های ذخیره‌شدهٔ اپ باطل → یک‌بار re-login (فقط اپ)
- **بکاپ قدیمی** → بین لحظهٔ بکاپ تا Cutover سفارش‌های جدید روی هلند می‌مانند؛
  راه‌حل: بکاپ **لحظهٔ قطع** (از cPanel هلند یا با تریگر دستی) — با P4 انجام‌پذیر است
- **PHP < 8.1** → خطاهای `str_starts_with`/`match`؛ حتماً 8.1+
- **پوشهٔ data حق‌بهم‌حق‌ها**: `775` (وگرین SQLite نمی‌تواند بنویسد)
- **آینهٔ APK**: بعد از Cutover خودکار روی هاست جدید ساخته می‌شود (AppApkMirror)
- **Marzban/نود**: `sub.speedur.org:2096` احتمالاً روی همین سرور است → اتصال
  پنل به نود از «هلند→ایران» به «محل‌به‌محل» تبدیل می‌شود (سریع‌تر و پایدارتر)

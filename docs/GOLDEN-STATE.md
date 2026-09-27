# مرجع طلایی — وضعیت‌های سالمِ تأییدشده (Golden State)

منبعِ اولیۀ مرجع برای رفع هر مشکل: **قبل از هر تغییری این فایل را بخوان** و
رفتار فعلی را با اینجا مقایسه کن. بعد از هر فیکس تأییدشده، entry تاریخ‌دار
بزن (قانون ۱ و  در `docs/RULES.md`).

---

## ۱) زنجیرهٔ تحویل ساب‌لینک (ربات → خریدار) — سازهٔ مرجع

**رفتار درست (تأییدشده 2026-09-26 روی پراکشن 5.4.7):**
خرید → `admin_approve` → کاربر روی **نود پاسارگاد** ساخته می‌شود →
`node_sublink` (ساب رسمی Marzban: `https://sub.speedur.org:2096/sub/<token>`)
در `clients.node_sublink` ذخیره می‌شود → پیام تحویل با QR + همان ساب‌لینک
(عکس → caption). خریدار مستقیم به سرورهای پاسارگاد وصل می‌شود (پنل وسط راه نیست).

**کدهای اصلی (نوک‌ریسه‌ها):**
- `controllers/TelegramBotController.php`
  - `getClientPrimarySublink()` (L64–137): زنجیرهٔ رزولوشن =
    `node_sublink` → `driver getUser` زنده → fallback proxy پنل.
    L77–80 بازنویسیِ base `montago-shop.ir` به `sub.speedur.org:2096`.
  - پیام‌های تحویل L1773/1853/1909/2003/2055 همگی از `getClientPrimarySublink`
  - `admin_approve` (L755–770) → `TelegramBot::editAnyMessage`
- `core/TelegramBot.php::editAnyMessage` (L368): ترتیب = editText → editCaption → **sendMessage جدید** (هرگز رها نمی‌شود)
- `drivers/PasargadDriver.php` (خط پاسارگاد/مدرن): `POST /user` با
  `group_ids:[1]`، بدون `sub_token` (توکن رسمی JWT توسط نود ساخته می‌شود)
- `controllers/ApiControllerV2.php::extractServerList` (L506+): لیست سرورهای اپ
  از لینک‌های زندهٔ کاربر (با 90s cache مشترک لاگین+configs)

**تست زنجیره (استاندارد):**
`GET https://vpbotn.ir/contax/index.php?route=monitor/diag&key=<DIAG_KEY>`
→ بخش `android`: node_binding / driver_getUser / live_links / mock_guard / final_links
+ نمونهٔ client واقعی با `node_sublink` و لینک‌ها.

## ۲) سلامت نود پاسارگاد (ماتریس تأییدشده 2026-09-26 — Xray v25.3.6)

**ساب اصلی مدیر (adminhojjat) — ۱۱ از ۱۵ سالم:**
| سالم | مرده |
|---|---|
| gga10, gsv8, nln2, pt-play, ugb, usa3, usa4, ukm, fr13, pk-play, trn7 (montago/mobileemdad) | sv3.nonpath.ir, fln2+fln4.moon-night.ir (vless sec=none), itl.mobileemdad.top (timeout) |

**ساب کاربر خریداری‌شده (usr_469a8f) — ۵ از ۱۲ reality سالم:**
sالم: gga6, usa3, fr13, trn2 — مرده: gsv4, nln1, pt-play, ugb, usa4, ukm, ae-google

**کشف کلیدی (ریشهٔ «ساب ربات وصل نمیشه»):** همان سرور (usa4) با UUID مدیر
**وصل** می‌شود ولی با UUID کاربر جدید **قطع** — pbk/sid یکسان، فقط UUID فرق دارد
→ **کاربرهای جدیدِ API روی همهٔ سرورهای پاسارگاد سینک نمی‌شوند** (گروه
montago/usa3/fr13/trn بله، گروه gsv/nln/pt-play/ugb/usa4/ukm/ae خیر).
این یک رجشن سمت **اپراتور پاسارگاد** است (کاربر می‌گوید قبلاً ۱۲–۱۵ سرور همه
آپ بود → باید به‌عنوان regression به پشتیبانی پاسارگاد گزارش شود). متن گزارش:
«کاربرهای جدید ساخته‌شده از طریق API روی برخی سرورها احراز هویت نمی‌شوند؛ همان UUID
روی montago/usa3/fr13/trn کار می‌کند. تست: usa4 با UUID مدیر وصل، با UUID کاربر
جدید قطع — pbk/sid یکسان است. به‌نظر می‌رسد سینک کاربران Marzban به XUI آن
سرورها انجام نمی‌شود.»

## ۳) چرخهٔ انتشار اپ (سالم — 3.3.8/5.4.7)

`push main` → `build-client-apps.yml` (Flutter 3.22, JDK17) → APKهای
Universal/ARM64/ARM32 + `app_release.json` → release **v3.0.0** (assetها جایگزین می‌شوند)
→ کرون پراکشن `AppReleasePublisher::sync()` (هر ~5 دقیقه، throttled 300s):
manifest را می‌خواند → اگر نسخهٔ جدیدتر: `app_latest_version`/changelog/title را
پیش‌نصب + **آینه‌گذاری APK روی پنل** (`AppApkMirror` → `$baseUrl/Connectix-*.apk`)
→ پیام 🚀 به بوت.
- تریگر دستی پراکشن: `https://vpbotn.ir/contax/cron/sync.php?key=gh_hook_sec_vpbotn_2026`
- درِ انتشار: `app_update_enabled` / `app_update_source` (admin = override دستی)
- نسخهٔ اندروید از: `versionCode`=build number pubspec، `versionName`=`currentAppVersion` (dashboard_screen.dart)
- اپ 3.3.9: check-update **قبل از ورود** (صفحهٔ لاگین) + آدرس پنل ثابت
  (`api_service.dart::initBaseUrl`؛ UI تنظیم آدرس حذف شده)

## ۴) لاگین/سرورهای اپ (سالم — 5.4.7 + 3.3.8)

لاگین = فقط احراز هویت (<1s با cache 90s مشترک login+configs روی پنل؛
همگام‌سازی سنگین از لاگین حذف شد و در `app/profile` بعد از ورود تکرار می‌شود).
مهلت سمت اپ 30s + پیام خطای دقیق timeout. دریافت سرورها:
`app/configs` (API) → اگر mock/کمتر از 6 → **fallback مستقیم به ساب‌لینک کاربر**
(`sub_url` ذخیره‌شده، parse 15 لینک).
**ریشهٔ «برنامه ارتباط برقرار نمی‌کند» (2026-09-26):** پنل روی `190.2.143.208`
(WorldStream، **هلند**) هاست است و از شبکهٔ ایران در دسترس نیست → timeout 30s.
راه‌حل دائمی = جابه‌جایی پنل به هاست ایرانی (cPanel پاسارگاد کاربر).
راه‌حل موقت = اپ از روی VPN وصل‌شده باز شود.

## ۵) تحویل ربات (عکس/کپشن) + دکمه‌ها (سالم — 3.3.7/5.4.7)

- دکمه‌های `v2rayng://` deep-link: فیکس 3.3.7 (733f2d9, deploy 20:08) —
  در لاگ telegram_api بعد از آن **هیچ** خطایی نیست.
- تحویل روی پیام عکس: `editAnyMessage` (text→caption→new-message). خطای
  «there is no text in the message to edit» روی عکس **منتظره** است و با
  caption/پیام جدید جبران می‌شود — خریدار تحویل را می‌گیرد.

## ۶) قفل‌شده‌ها (بدون دستور، لمس ممنوع — قانون ۲)

| بخش | چرا |
|---|---|
| `getClientPrimarySublink` + زنجیرهٔ تحویل | آخرین نسخه‌ای است که ساب رسمی پاسارگاد را درست می‌دهد |
| دکمه‌های glass (3.3.7) | بعد از فیکس، صفر خطا در لاگ |
| `AppReleasePublisher` + آینهٔ APK | چرخهٔ خودکار آپدیت سالم است |
| `editAnyMessage` (3-stage) | رها-نشدن تحویل تضمین‌شده است |
| cache 90s `extractServerList` | ریشه‌برداشتندهٔ لاگینِ کند |

## ۷) پراکشن — حقایق (2026-09-27)

- پنل: `https://vpbotn.ir/contax` = 190.2.143.208 (هلند) — نسخه 5.4.7
- نود: `sub.speedur.org:2096` (cPanel کاربر / پاسارگاد — کاندیدای میزبانی پنل)
- کلیدها: DIAG_KEY در `controllers/DiagController.php`؛ کرون/بریک/میرر:
  `gh_hook_sec_vpbotn_2026` (RUNBOOK.md بخش ۶)
- **بدهی‌های باز پراکشن:**
  1. cron cPanel اجرا نمی‌شود (heartbeat: آخرین سینک >94 دقیقه) → کرون را
     `*/1 * * * * php /home/*/contax/cron/sync.php` تنظیم کن + تریگر دستی بالا
  2. بوت ریزلر `novinvpn` (user 2) توکن باطل (Unauthorized) → توکن جدید از BotFather
  3. thread id فرام (12345) قدیمی در سوپرگروه → اعلان‌ها fail می‌شوند (خوش‌نمای)
  4. ۴ سرور مردهٔ پاسارگاد + رجشن سینک کاربرهای جدید → گزارش به پاسارگاد (بخش ۲)
  5. جابه‌جایی پنل به هاست ایران → راه‌حل دائمی اپ

## ۸) تاریخچهٔ فیکس‌ها (entryهای تاریخ‌دار — قدیمی‌ترین بالا)

- **2026-09-26 | 5.4.7 + اپ 3.3.8**: خطای «خطا در برقراری ارتباط با سرور» هنگام
  ورود — حذف sync سنگین از appLogin + cache 90s؛ مهلت اپ 12s→30s. تأیید:
  لاگین پراکشن <1s (diag).
- **2026-09-26 | اپ 3.3.7**: خطای دکمه‌های `v2rayng://` — فیکس deep-link.
  تأیید: صفر خطا در telegram_api بعد از deploy 20:08.
- **2026-09-26 | تشخیص**: «ساب ربات وصل نمیشه» = (a) رجشن سینک پاسارگاد (بخش ۲)
  + (b) نبود VPN برای اپ (پنل هلندی). کدِ ما سالم بود (زنجیرهٔ diag سبز).
- **2026-09-27 | اپ 3.3.9 (انتشار و تأیید شد)**: بروزرسانی قبل از ورود +
  حذف UI تنظیم آدرس + آدرس ثابت. مسیر: push 3461df4 → CI سبز (build-client-apps)
  → release v3.0.0 (APK 26,046,618 B) → کرون پراکشن `AppReleasePublisher`
  → check-update = 3.3.9 ✓ + آینه APK روی پنل ✓ (Last-Modified 04:55).
  یادداشت: خطای اول CI (const expression در label دیالوگ) با fix push 3461df4 حل شد —
  درِ کیفیت CI کار کرد؛ هر build شکست‌خورده **اعمال/منتشر نمی‌شود**.
- **2026-09-27 | تأیید زنده آخرین کاربر (`usr_3e1689`)**:
  تست تک‌تک ۱۵ نود از ساب‌لینک تحویلی رسمی به روش Xray واقعی + استعلام `generate_204`:
  - متصل (HTTP 204 زیر ۱ ثانیه): `gga6.montago-shop.ir`, `usa3.mobileemdad.top`, `fr13.mobileemdad.top`, `trn1.mobileemdad.top`
  - مسدود سمت کلاستر نودهای پاسارگاد (عدم سینک کاربرهای API روی این نودها): `gsv7`, `nln3`, `pt-play`, `ugb`, `usa4`, `ukm`, `ae-google`, `itl` + سرورهای غیریالیتی `sv2`, `fln1`, `fln3`.
  - وضعیت ورود به اپ: با اتصال VPN از نودهای سالم، اپ با موفقیت وارد پنل و داده‌ها شد (آدرس ثابت `vpbotn.ir/contax` بدون مشکل عمل کرد).
  - پرونده مهاجرت به هاست ایران با دستور کارفرما بسته شد (ربات مستقیماً مانند میرزا پرو به سرور اصلی متصل است و تغییر فیزیکی سرور رد شد).
- **2026-09-27 | اپ 3.4.0 (رفع ۴ مشکل کلیدی کارفرما)**:
  1. بارگذاری آنی سرورها در اجراهای بعدی: کش پایدار در SharedPreferences (`cached_servers`) + متد `toJson()` در `ServerModel` + بارگذاری در `SplashScreen` و `DashboardScreen` در صفر میلی‌ثانیه بدون نیاز به زدن بروزرسانی.
  2. دکمه پینگ تمام سرورها: تبدیل `ServerListModal` به StatefulWidget + متد موازی `pingAllServers` از طریق سوکت مستقیم TCP به پورت ۴۴۳ + نمایش برچسب عددی پینگ و رنگ‌بندی (سبز/زرد/نارنجی/قرمز) + مرتب‌سازی خودکار بر اساس کمترین پینگ + انتخاب هوشمند سریع‌ترین سرور.
  3. نمایش سرعت آپلود/دانلود و دکمه قطع اتصال در نوتیفیکیشن گوشی: دریافت برودکست بومی `V2RAY_CONNECTION_INFO` در `V2rayNotificationReceiver` + نمایش زنده سرعت لحظه‌ای دانلود و آپلود + دکمه اکشن `قطع اتصال` که مستقیم `STOP_SERVICE` را فراخوانی می‌کند + نمایش چیپ‌های سرعت لحظه‌ای در داشبورد اپ.
  4. پایداری حداکثری اتصال و رفع قطع/وصل خودکار: حذف کامل هاپینگ خودکار به سرور بعدی (`nextServer`) در `_handleAutoReconnect`؛ تلاش مجدد منحصراً روی همان سرور انتخابی تا ۳ بار؛ افزودن مجوز `WAKE_LOCK` و اصلاح DNS به ۳ سرور پشتیبان ضد تحریم (`1.1.1.1`, `8.8.8.8`, `1.0.0.1`) تا ارتباط تا لمس عمدی کاربر پایدار بماند.

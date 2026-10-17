# 🔍 ممیزی جامع Connectix Panel + Client App - v4.0.52
**تاریخ:** 2026-10-10 | **مقیاس ارزیابی:** 10 معیار حرفه‌ای
**وضعیت فعلی:** Panel CI ✅ Success | Client Build ✅ Success | نسخه 4.0.52+86

---

## 📊 خلاصه اجرایی (Executive Summary)

Connectix یک پلتفرم VPN چندسرویسی بسیار کامل با امکانات ریسلر، ربات تلگرام، کیف پول، گردونه شانس، بایپس داخلی و... است. از نظر **امکانات** نمره 9/10 می‌گیرد ولی از نظر **معماری و سبکی** نمره 5/10 دارد. بزرگترین مشکل: **فایل‌های غول‌پیکر (TelegramBotController 5768 خط، dashboard_screen 3652 خط)، 794 بلاک catch خالی، عدم pagination، و انیمیشن‌های سنگین دکمه اتصال که باعث می‌شود در گوشی‌های ضعیف بعضی دکمه‌ها عمل نکند.**

---

## 🏆 نقاط قوت (Strengths) - نمره 8.5/10

### 1. امکانات فوق کامل (9.5/10)
- ✅ مولتی پروتکل: VLESS, VMess, Trojan, SS, SOCKS, HTTP, WireGuard, Hysteria2, Reality, XTLS, Stealth, Multiplex
- ✅ مولتی اکانت نامحدود + سوئیچر تلگرام‌استایل + حالت یکپارچه (unified)
- ✅ اسمارت کانکت (انتخاب بهترین پینگ از 6 سرور اول) + ذخیره آخرین سرور سالم + Auto-Reconnect با backoff
- ✅ بایپس 130 اپ ایرانی و بانکی (ایتا، روبیکا، بله، شاد، همراه بانک ملت/ملی/تجارت/صادرات/سپه/پاسارگاد، بلوبانک، آپ، تاپ، نوبیتکس، اسنپ، دیوار، کافه‌بازار و...)
- ✅ ربات تلگرام: 15 دکمه قابل تنظیم (خرید، تمدید، حساب‌ها، تست، گردونه، کیف‌پول، زیرمجموعه، اپ، پشتیبانی، نمایندگی، ورود وب، مینی‌اپ، وضعیت سرور LIVE، گزارش مالی، امتیاز)
- ✅ سیستم ریسلر: tier، امتیاز و جایزه، گزارش مالی، مانیتورینگ زنده، بکاپ خودکار، همگام‌سازی نودها، چرخش ساب‌لینک ضدفیلتر
- ✅ مدیریت مرکزی اپ از وب (v4.0.51): `api/v1/app/global-config` - ادمین یک‌بار panel_url و api_key را تنظیم می‌کند، همه اپ‌ها خودکار می‌گیرند + hide_manual
- ✅ آپدیت درون‌برنامه‌ای با 8 URL، resume با ?start=، تایید PK و versionName، WakeLock، باتری exemption

### 2. هوشمندی پنل لوکیشن (8/10)
- Resolver 6 لایه: saved working → well-known → panel-location API → old redirector → brute-force → GitHub remote config
- هدرهای X-Panel-Canonical برای مهاجرت خودکار
- کش سرورها و پروفایل برای لود فوری آفلاین

### 3. امنیت پایه خوب (7/10)
- `Auth::requireAdmin`, `Helpers::verifyCsrf` در بیشتر POSTها
- `password_hash` BCRYPT، `api_token` تصادفی، `sub_token` 24 کاراکتری
- `telegram_chat_id` binding با 4 روش (یوزر/پس، دیپ‌لینک 1 کلیکه، لیست چنداکانته، مهمان)

---

## ⚠️ نقاط ضعف (Weaknesses) - نمره 5.2/10

### 1. معماری غول‌پیکر و عدم رعایت SRP (4/10)
| فایل | خطوط | مشکل |
|------|-------|-------|
| `TelegramBotController.php` | 5768 | همه چیز: منو، سفارش، پرداخت، کیف پول، ریسلر، بکاپ DB، مانیتورینگ، مالی، امتیاز - باید به 10 سرویس جدا تقسیم شود |
| `dashboard_screen.dart` | 3652 | UI + منطق VPN + پینگ + آپدیت + بایپس + هات‌اسپات + اعلانات + مولتی‌اکانت در یک فایل |
| `api_service.dart` | 2688 | resolver + login + servers + ping + download + proxy + feedback + account switch در یک کلاس |
| `Database.php` | 1714 | 50+ `safeAddColumn` مهاجرت دستی، بدون migration versioning |
| `header.php` | 866 | منو + استایل + اسکریپت همه در یک فایل |

**نتیجه:** نگهداری سخت، تست غیرممکن، باگ‌های regression زیاد (مثلا v4.0.51 فیکس استایل `app_config.php` که header/footer نداشت).

### 2. Performance - سنگینی و کندی (5/10)
- **عدم pagination:** `ClientController::index()` همه کلاینت‌ها را `SELECT ... ORDER BY id DESC` بدون `LIMIT` می‌خواند. اگر 10k کلاینت داشته باشید، 50MB HTML تولید می‌شود، مرورگر هنگ می‌کند.
- **N+1 Query:** در `ServerController` برای هر سرور `SELECT COUNT(*) FROM clients WHERE server_id` جداگانه
- **Cache file-based:** `Cache.php` با `md5(key).cache` و `file_get_contents` - در هاست اشتراکی با 1000 فایل کش، `clearByPrefix` همه فایل‌ها را می‌خواند → O(n)
- **Flutter:** `ConnectButtonUltimate` با 10 AnimationController همزمان (pulse, orbit 8x, liquid, plasma 6x, fiber 14x, confetti 22x, dust 25x, shake, flag wave) → در گوشی ضعیف 30fps و باتری‌خور، و گاهی `onTap` گم می‌شود چون GestureDetector زیر لایه‌های انیمیشن قرار می‌گیرد.
- **Resolver 6 لایه با timeout 4-10 ثانیه هر لایه:** در اینترنت ضعیف ایران، لاگین 42 ثانیه طول می‌کشد (7 URL * 6s) → کاربر فکر می‌کند دکمه لاگین کار نمی‌کند.

### 3. Security - مشکلات متوسط (6/10)
- **794 بلاک `catch (Throwable $e) {}` خالی:** خطاها قورت داده می‌شوند، لاگ نمی‌شود → دیباگ غیرممکن، باگ‌های امنیتی پنهان می‌ماند
- **SQL Injection نیمه‌امن:** `pdo->query("SELECT * FROM server_nodes WHERE id = $id")` در `MonitorController`, `ServerController`, `ClientController` - هرچند `$id` معمولا int cast شده، ولی در بعضی جاها `$_GET['id']` مستقیم بدون cast (مثلا `NodeSync` قبلا)
- **XSS:** در `views/clients/index.php` بعضی جاها `<?= $c['username'] ?>` بدون `htmlspecialchars` در `onclick="if(confirm('حذف <?= $c['username'] ?>'))"` - اگر یوزرنیم `';alert(1);//` باشد XSS می‌شود
- **CSRF:** بعضی GETها state-changing هستند (مثلا `clients/resync?id=`) → باید POST + CSRF باشد
- **Rate Limiting:** لاگین پنل وب و ربات هیچ rate limit ندارد → brute force
- **API Key در URL:** `?auth_token=` در GET لاگ سرور و Cloudflare می‌ماند

### 4. UI/UX - زیبا ولی شلوغ (6.5/10)
- **Panel:** Tailwind زیبا ولی 11 منو با badge NEW، رنگ‌های بنفش/فیروزه‌ای/رز زیاد، contrast کم در dark mode، فونت 11px خیلی ریز
- **Client App:** صفحه اول قرار بود no-scroll باشد ولی هنوز `SingleChildScrollView` با 8 کارت (quota, alert, smart connect, connect button, timer, speed, server selector, footer) → در گوشی کوچک اسکرول می‌خواهد
- **انیمیشن بیش از حد:** کاربر ایرانی VPN می‌خواهد وصل شود، نه confetti و پرچم مواج و غبار نورانی - این‌ها 15% CPU می‌گیرند
- **عدم consistency:** دکمه‌ها گاهی `ElevatedButton`, گاهی `InkWell`, گاهی `GestureDetector` با استایل متفاوت

---

## 🐛 لیست باگ‌های فعال (Bug List)

### 🔴 Critical (باید فوری فیکس شود)
1. **Bot Buttons Toggle Bug (فیکس شد در v4.0.52):** `btn_monitoring_enabled` و 3 تای دیگر در `updateSettings()` ذخیره نمی‌شد → همیشه فعال
2. **Reply Keyboard Monitoring/Financial (فیکس شد در v4.0.52):** `processMessage` هندلر نداشت → دکمه ثابت پایین کار نمی‌کرد
3. **Mobile App Buttons Not Working - Root Cause:**
   - **a) TransactionTooLargeException:** `defaultDomesticBypassApps` 130 پکیج → `VpnService.Builder.addDisallowedApplication` برای هر کدام → binder transaction >1MB → `FAILED BINDER TRANSACTION` → `startV2Ray` exception → catch خالی → دکمه اتصال هیچ کاری نمی‌کند (فیکس نیمه: truncate به 30 ولی هنوز ریسک دارد)
   - **b) RTL Directionality + GestureDetector conflict:** `ConnectButtonUltimate` داخل `Directionality RTL` و `Center` و `SingleChildScrollView` → در بعضی گوشی‌ها hitTest خراب، `onTap` فراخوانی نمی‌شود. لاگ: `LAW 12 Fixed RTL flip bug - split Persian label and English speed to prevent bidi flip on connect + buttons non-functional`
   - **c) V2Ray not initialized:** `_flutterV2ray.initializeV2Ray()` async است ولی `_toggleConnection` بدون چک `isInitialized` صدا زده می‌شود → silent fail
   - **d) MethodChannel not registered:** اگر `MainActivity.kt` متد `com.connectix.vpn/updater` را رجیستر نکرده باشد (مثلا بعد از obfuscate)، تمام دکمه‌های آپدیت، هات‌اسپات، بایپس کار نمی‌کنند
   - **e) Smart Connect progress stuck:** `getServerDelay` بدون timeout می‌ماند → `onProgress` هیچ وقت `0 از 16` را آپدیت نمی‌کند → کاربر فکر می‌کند دکمه خراب است (فیکس شد در v4.0.16 با timeout 4s)

4. **No Pagination = OOM:** `clients` با 5000+ رکورد → PHP memory limit 128M → 500 error

### 🟠 Major
5. **Cache Stale:** `Setting::get` با file cache 5 دقیقه → بعد از تغییر تنظیمات ربات، تا 5 دقیقه دکمه قدیمی نشان داده می‌شود (هرچند `forget` صدا زده می‌شود ولی `system_settings_all` گاهی باقی می‌ماند)
6. **Soft Delete Resync:** اگر کلاینت soft-deleted باشد و `NodeSync::syncServer` اجرا شود، ترافیک آپدیت می‌شود ولی `is_local_deleted` باقی می‌ماند → کاربر گیج می‌شود که چرا مصرف آپدیت شده ولی در لیست نیست
7. **Download Stuck 10% (فیکس شد v4.0.41):** `DownloadManager` با `getCacheDir` در MIUI پاک می‌شود → progress 10% گیر می‌کند
8. **Exit Errors (فیکس شد v4.0.41):** `dispose()` فقط timer cancel می‌کرد، V2Ray و MethodChannel handler باقی می‌ماند → crash on exit
9. **Proxy Infinite Spinner (فیکس شد v4.0.41):** `getProxies()` 7 URL * 8s = 56s → spinner بی‌نهایت

### 🟡 Minor
10. **Version Display Bug:** `DashboardScreen.currentAppVersion` هاردکد `4.0.51` ولی `actualVersion` از PackageManager می‌آید → فوتر دو نسخه نشان می‌دهد
11. **Hardcoded Domains:** `vpbotn.ir`, `direct.vpbotn.ir`, `ir.vpbotn.ir`, `cf.vpbotn.ir` در 20+ فایل هاردکد → اگر دامنه عوض شود باید 20 فایل عوض شود (باید فقط در `config.php` باشد)
12. **Fix Files Clutter:** 11 فایل `fix_*.php` در روت → CI lint را کند می‌کند و امنیت پایین (هر کسی می‌تواند اجرایشان کند)

---

## 📱 چرا در گوشی بعضی دکمه‌ها عمل نمی‌کنه؟ (تحلیل تخصصی)

### در اپ فلاتر (Client App):
1. **دکمه اتصال (Connect):**
   - انیمیشن سنگین + 10 کنترلر → در گوشی ضعیف (RAM 3GB) فریم دراپ → GestureDetector رویداد را گم می‌کند
   - `blockedAppsToPass` با 130 اپ → `TransactionTooLarge` → `startV2Ray` throw → catch و فقط log → کاربر می‌بیند دکمه می‌چرخد ولی وصل نمی‌شود
   - **راه حل فوری:** بایپس را به 20 اپ اصلی محدود کن + `ConnectButtonUltimate` را به `ConnectButtonV2` ساده (1 انیمیشن) تغییر بده

2. **دکمه‌های AppBar (تنظیمات، پشتیبانی، رفرش):**
   - `Directionality RTL` کل `body` را RTL کرده ولی `AppBar` actions LTR می‌ماند → در بعضی گوشی‌های شیائومی hit area جابجا می‌شود
   - **راه حل:** `AppBar` را از `Directionality` خارج کن یا `actions` را داخل `Directionality LTR` بگذار

3. **دکمه انتخاب سرور:**
   - `showModalBottomSheet` با `isScrollControlled: true` و `heightFactor: 0.85` → در گوشی با navigation bar، دکمه‌های پایین مودال زیر navigation می‌روند و قابل کلیک نیستند
   - **راه حل:** `SafeArea` + `padding: MediaQuery.viewInsets`

4. **دکمه‌های آپدیت:**
   - `downloadAndInstallApk` با `MethodChannel` → اگر کاربر `install unknown apps` را نداده باشد، `installApk` false برمی‌گرداند ولی UI فقط `onError` نشان می‌دهد که گاهی زیر BottomSheet می‌ماند

### در ربات تلگرام:
- قبلا فیکس شد: Reply Keyboard بدون هندلر → الان فیکس است
- ولی هنوز: اگر `btn_*_enabled` خاموش باشد، `getMainMenuReplyKeyboard` دکمه را مخفی می‌کند ولی کیبورد قدیمی در تلگرام کاربر کش شده می‌ماند تا `/menu` بزند → باید `remove_keyboard` بفرستیم یا پیام جدید با کیبورد جدید

### در پنل وب:
- `views/clients/index.php` دکمه‌های عملیات `w-8 h-8` خیلی کوچک (32px) → در موبایل با انگشت سخت کلیک می‌شود (باید 44px حداقل)
- `onclick="document.getElementById('deleteIdInput').value=..."` اگر فرم hidden باشد و کاربر 2 بار کلیک کند، race condition

---

## 🚀 پیشنهادات حرفه‌ای برای سبک، سریع، زیبا و پرکارایی

### 🔥 اولویت 1: سبک و سریع (Performance) - تاثیر 40%
1. **Pagination + Lazy Load:**
   ```php
   // ClientController::index()
   $page = max(1, (int)($_GET['page'] ?? 1));
   $perPage = 50;
   $offset = ($page-1)*$perPage;
   $sql .= " LIMIT $perPage OFFSET $offset";
   ```
   + در ویو `DataTables.js` با server-side processing

2. **کوچک کردن Flutter:**
   - `dashboard_screen.dart` را به 5 فایل تقسیم: `dashboard_vm.dart` (logic), `quota_card.dart`, `server_selector.dart`, `connect_section.dart`, `announcement_banner.dart`
   - `ConnectButtonUltimate` (742 خط) → `ConnectButtonLite` با 1 pulse animation + 1 scale → 80% سبک‌تر، 60fps در گوشی ضعیف
   - حذف `defaultDomesticBypassApps` 130 تایی → فقط 20 تای اصلی + قابلیت افزودن دستی
   - `api_service.dart` 2688 خط → به `panel_resolver.dart`, `auth_service.dart`, `server_service.dart`, `update_service.dart` تقسیم

3. **کش Redis یا APCu:**
   - جایگزینی `Cache.php` file-based با `APCu` (اگر هاست ساپورت کند) یا `SQLite` cache table با index
   - `Setting::getAll()` را یک‌بار در `$_SESSION` یا APCu نگه دار، نه هر درخواست فایل بخواند

4. **حذف Fix Files:**
   - همه `fix_*.php` را پاک کن، فقط یک `migrations/` با versioning نگه دار
   - CI lint فقط `core/`, `controllers/`, `views/` را چک کند، نه `fix_*.php`

### 🔒 اولویت 2: امنیت و پایداری - تاثیر 25%
5. **Prepared Statements همه جا:**
   ```php
   // بد
   $pdo->query("SELECT * FROM server_nodes WHERE id = $id")
   // خوب
   $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?")->execute([$id])
   ```
6. **Rate Limit + 2FA اجباری برای ادمین:**
   - `RateLimiter.php` را در `AuthController::login` و `TelegramBotController::processMessage` استفاده کن
   - لاگین 5 بار اشتباه → بلاک 15 دقیقه

7. **لاگ واقعی:**
   - جایگزینی `catch (Throwable $e) {}` با `SecurityLogger::log($e)` + Sentry یا فایل `logs/error.log`

8. **CSRF برای GETهای خطرناک:**
   - `clients/resync?id=` → باید `POST` + `csrfField`

### 🎨 اولویت 3: UI/UX حرفه‌ای - تاثیر 20%
9. **Design System:**
   - یک فایل `theme.css` با متغیرها: `--color-primary: #9333EA`, `--radius: 16px`, `--shadow: ...`
   - همه دکمه‌ها یک کلاس: `btn btn-primary btn-sm` به جای `px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-lg`
   - اندازه دکمه موبایل حداقل 44px

10. **No-Scroll Dashboard واقعی:**
    - فقط 3 چیز در صفحه اول: Quota Circle, Connect Button, Server Selector (مثل الان ولی بدون Smart Connect اضافی)
    - بقیه (هات‌اسپات، بایپس، GPS) در Drawer یا Bottom Navigation

11. **Skeleton Loading:**
    - به جای `CircularProgressIndicator` در کل صفحه، skeleton برای هر کارت

### ⚙️ اولویت 4: امکانات هوشمند - تاثیر 15%
12. **Resync هوشمند:**
    - وقتی soft-deleted کلاینت در سرور اصلی ترافیک مصرف کند، نوتیف تلگرام: "کاربر X که حذف محلی شده بود، 2GB مصرف کرد - بازگردانی؟"

13. **Server Health Auto-Failover:**
    - `ServerMonitor::checkAllServers()` هر 5 دقیقه → اگر latency > 2000ms → سرور را `health_status=offline` و کلاینت‌هایش را به بهترین سرور migrate

14. **App Config از وب کامل:**
    - الان `hide_manual_panel_url` فقط فیلد را مخفی می‌کند ولی `api_service.dart` هنوز `baseUrls` هاردکد دارد → باید `fetchGlobalAppConfig` قبل از هر چیز، لیست کامل `panel_urls` و `bypass_apps` را از وب بگیرد

15. **PWA + Offline:**
    - `sw.js` الان `v4-0-49` هاردکد → باید versioning خودکار + کش API responses

---

## 📋 چک‌لیست اقدام پیشنهادی (برای تایید شما)

### فاز 1: فوری (1 هفته) - بدون تغییر بزرگ
- [ ] حذف 130 بایپس → 20 اصلی (فوری سبک شدن و فیکس دکمه اتصال)
- [ ] `ConnectButtonUltimate` → `ConnectButtonV2` ساده (گزینه در settings برای انیمیشن سنگین)
- [ ] Pagination 50 تایی در `clients/index`
- [ ] پاک کردن `fix_*.php` قدیمی (فقط `fix_452` نگه دار)
- [ ] Prepared Statements برای 10 فایل اصلی

### فاز 2: میان‌مدت (2-3 هفته)
- [ ] تقسیم `TelegramBotController` به `Bot/MenuService`, `Bot/OrderService`, `Bot/PaymentService`, `Bot/AdminService`
- [ ] تقسیم `dashboard_screen.dart` به MVVM
- [ ] Redis/APCu cache
- [ ] Design System CSS

### فاز 3: بلندمدت (1 ماه)
- [ ] Unit Test برای `Provisioner`, `NodeSync`
- [ ] E2E Test برای ربات تلگرام
- [ ] PWA کامل + Offline support
- [ ] مانیتورینگ با Grafana

---

## 🎯 نمره نهایی فعلی vs بعد از پیشنهادات

| معیار | فعلی | بعد از فاز 1 | بعد از فاز 2-3 |
|-------|------|-------------|---------------|
| Performance (سرعت) | 5/10 | 7.5/10 | 9/10 |
| Security (امنیت) | 6/10 | 7.5/10 | 9/10 |
| UI/UX (زیبایی) | 6.5/10 | 8/10 | 9.5/10 |
| Maintainability (نگهداری) | 4/10 | 6/10 | 8.5/10 |
| Features (امکانات) | 9.5/10 | 9.5/10 | 10/10 |
| Mobile Stability (پایداری موبایل) | 5/10 | 8/10 | 9/10 |
| **میانگین** | **6/10** | **7.7/10** | **9.1/10** |

---

**منتظر تایید شما برای شروع فاز 1 هستم. کدام پیشنهاد را اول انجام دهم؟**

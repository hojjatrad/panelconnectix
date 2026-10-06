# v4.0.19 FOREVER LAW - قانون دائمی حل مشکل نصب میپره و نسخه قدیمی میمونه

## مشکل گزارش شده توسط کاربر
> بازم برنامه آپ هنگام نصب میپره و نصب نمیشه و نسخه جدیدی نمیاد

این مشکل از v4.0.13 تا v4.0.18 چند بار تکرار شده و هر بار فیکس موقتی بود. الان باید برای همیشه حل شود و قانون شود.

## ریشه‌یابی عمیق (Deep Root Cause Analysis)

### لایه 1: پنل (Panel Side)
1. **فایل APK قدیمی روی هاست**: وقتی `quick_update.php` فقط فایل‌های PHP را آپدیت میکند، فایل‌های `Connectix-ARM64-v8a.apk` روی هاست قدیمی میمانند. حتی با `?v=4.0.18`، محتوای فایل قدیمی است و کلودفلر BYPASS هم کمکی نمیکند چون origin قدیمی است.
2. **تنظیمات نسخه آپدیت نمیشود**: `app_latest_version` در `system_settings` دستی باید آپدیت شود. اگر نشود، `check-update` نسخه قدیمی برمیگرداند.
3. **کش کلودفلر**: حتی با `?v=`، اگر فایل روی هاست قدیمی باشد، نسخه قدیمی نصب میشود.

### لایه 2: اپ (App Side)
4. **دانلود APK قدیمی**: اپ از پنل `https://vpbotn.ir/Connectix-ARM64-v8a.apk?v=4.0.18` دانلود میکند ولی محتوای آن 4.0.17 است. نصب میشود ولی نسخه همان قدیمی میماند.
5. **عدم تایید نسخه**: اپ بعد از دانلود چک نمیکند که `versionName` داخل APK با نسخه مورد انتظار یکی است یا نه.
6. **نصب میپره**: 
   - اگر APK خراب باشد (HTML به جای APK)، `PackageInstaller` یا `Intent` میپره بدون خطا
   - اگر نسخه APK همان نسخه نصب شده باشد و `allowSameVersion=false` باشد، نصب میپره
   - در MIUI/Samsung Android 14+، `PackageInstaller` API بلاک است و پنجره نمیاد
   - `FileProvider` اگر فایل در مسیر اشتباه باشد، URI grant نمیشود و نصب میپره

### لایه 3: کش (Cache)
7. **کش مرورگر و کلودفلر**: حتی با `?v=`، بعضی CDN ها `?v=` را نادیده میگیرند اگر `Cache-Control` درست نباشد.
8. **کش اپ**: فایل `Connectix-Update.apk` قدیمی در کش میماند و دوباره استفاده میشود.

## قوانین دائمی (10 LAW) - باید همیشه در تمام نسخه‌های بعدی رعایت شود

### LAW 1: پنل هرگز APK قدیمی سرو نمیکند
**کجا**: `core/Database.php` + `controllers/ApiController.php` + `ApiControllerV2.php`
**چطور**:
- در `Database::ensureExtendedTablesExist()`: اگر `app_latest_version` قدیمی‌تر از `4.0.19` باشد، خودکار آپدیت به جدید + حذف فایل‌های APK قدیمی (اگر mtime > 5 دقیقه)
- در `ApiController::checkAppUpdate()`: اگر `app_version_updated_at` جدیدتر از mtime فایل APK باشد، فایل را حذف کن و fallback به گیت‌هاب
- در `quick_update.php`: بعد از sync، اگر نسخه تغییر کرد، خودکار APK تازه از گیت‌هاب دانلود کن

### LAW 2: اپ نسخه APK را بعد از دانلود چک میکند
**کجا**: `client-app/android/app/src/main/kotlin/com/connectix/vpn/MainActivity.kt` + `api_service.dart`
**چطور**:
- متد جدید `getApkVersionName` در MainActivity: از `PackageManager.getPackageArchiveInfo` نسخه را میگیرد
- در `downloadAndInstallApk`: بعد از دانلود، `getApkVersionName` را صدا بزن، اگر نسخه قدیمی‌تر از انتظار بود، فایل را حذف و URL بعدی را امتحان کن
- لاگ کامل: `v4.0.19 APK version check: expected 4.0.19, got 4.0.17 from https://...`

### LAW 3: اگر نسخه APK با انتظار فرق داشت، خودکار لینک بعدی
**کجا**: `api_service.dart`
**چطور**:
- در `attemptDownloadWithHttp` و `attemptDownloadWithHttpClient`: بعد از دانلود، version check، اگر قدیمی بود `return false` تا URL بعدی امتحان شود
- کاربر نیازی به زدن دستی دکمه ندارد

### LAW 4: قبل از دانلود، فایل قدیمی پاک میشود
**کجا**: `api_service.dart`
**چطور**:
- در هر دو متد download: `if (file.exists()) await file.delete()` قبل از دانلود
- همچنین در `getCacheDir`: از `externalFilesDir` استفاده کن (بهترین برای MIUI)

### LAW 5: ?v=version&t=time&s=random برای دور زدن تمام کش‌ها
**کجا**: `ApiController.php`, `ApiControllerV2.php`, `api_service.dart`, `.htaccess`
**چطور**:
- همیشه `?v=4.0.19&t=1710000000&s=1234` اضافه کن (v + t + s)
- در `.htaccess`: `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, no-transform, private` + `cf-cache-status: BYPASS` + `CDN-Cache-Control: no-store` + `Expires: 0` + `X-Accel-Buffering: no` برای `.apk` و `app_release.json` و `quick_update.php`

### LAW 6: همیشه گیت‌هاب به عنوان fallback حتی اگر فایل پنل موجود باشد
**کجا**: `api_service.dart` `generateAllUrls()`
**چطور**:
- لیست URL ها: اول پنل با ورژن، بعد پنل بدون ورژن ولی با cache buster، بعد گیت‌هاب مستقیم
- حتی اگر پنل فایل دارد، گیت‌هاب هم در لیست باشد تا اگر پنل قدیمی بود، گیت‌هاب امتحان شود
- `addUrl("https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-ARM64.apk")` همیشه آخر لیست

### LAW 7: quick_update.php خودکار APK تازه از گیت‌هاب دانلود میکند
**کجا**: `quick_update.php`
**چطور**:
- بعد از آپدیت نسخه، اگر `needsApkDownload=true`، با curl از گیت‌هاب APK ها را دانلود کن و روی هاست بذار
- اگر دانلود موفق نبود، فایل قدیمی را حذف کن تا fallback به گیت‌هاب شود
- لاگ: `✅ APK Connectix-ARM64-v8a.apk آپدیت شد: 38.3 MB`

### LAW 8: Database.php اگر نسخه قدیمی باشد فایل‌های قدیمی را حذف میکند
**کجا**: `core/Database.php`
**چطور**:
- وقتی نسخه از `4.0.18` به `4.0.19` میرود، تمام فایل‌های APK قدیمی که mtime > 5 دقیقه دارند را حذف کن
- این باعث میشود `check-update` به جای فایل قدیمی پنل، URL گیت‌هاب را برگرداند که همیشه تازه است

### LAW 9: Pure Intent نصب - بدون PackageInstaller API
**کجا**: `MainActivity.kt` `installApk`
**چطور**:
- فقط از `ACTION_INSTALL_PACKAGE` + `ACTION_VIEW` + `Chooser` استفاده کن
- **هرگز** از `PackageInstaller` API استفاده نکن (در MIUI/Samsung Android 14+ بلاک است و میپره)
- Grant URI permission به 9 پکیج نصاب معروف: `com.android.packageinstaller`, `com.google.android.packageinstaller`, `com.miui.packageinstaller`, `com.miui.global.packageinstaller`, `com.miui.securitycenter`, `com.samsung.android.packageinstaller`, `com.sec.android.preloadinstaller`, `com.android.managedprovisioning`, `com.google.android.permissioncontroller`
- فایل را در `externalFilesDir` بذار (بهترین سازگاری FileProvider)
- `allowSameVersion=true` همیشه

### LAW 10: لاگ کامل برای دیباگ + خطای دقیق با راه‌حل
**کجا**: همه جا
**چطور**:
- در هر مرحله لاگ با `v4.0.19` prefix
- اگر نصب شکست خورد، پیام دقیق با حجم فایل، مسیر، نسخه، و لینک مرورگر
- دکمه‌های fallback: "نصاب خالص v4.0.13"، "پوشه"، "مرورگر"
- در پنل: `error_log("v4.0.19 LAW: Deleted stale APK...")`

## تست‌های عمیق (Deep Tests)

### تست 1: پنل APK قدیمی دارد، نسخه جدید 4.0.19
- [x] `Database.php` باید فایل قدیمی را حذف کند
- [x] `ApiController` باید mtime چک کند و اگر قدیمی بود حذف و گیت‌هاب برگرداند
- [x] `check-update` باید `?v=4.0.19&t=...&s=...` برگرداند
- [x] `quick_update.php` باید خودکار APK جدید از گیت‌هاب دانلود کند

### تست 2: اپ APK قدیمی دانلود میکند
- [x] `getApkVersionName` باید نسخه را تشخیص دهد
- [x] اگر `4.0.17` بود ولی انتظار `4.0.19`، باید حذف و URL بعدی (گیت‌هاب) امتحان شود
- [x] لاگ باید `STALE APK DETECTED` نشان دهد

### تست 3: نصب میپره
- [x] اگر فایل < 500KB باشد، `FILE_TOO_SMALL` خطا
- [x] اگر `canRequestPackageInstalls=false` باشد، تنظیمات باز شود
- [x] Pure Intent باید پنجره سیستم را باز کند حتی در MIUI/Samsung
- [x] اگر باز نشد، دکمه "نصاب خالص" و "مرورگر" موجود باشد

### تست 4: کش کلودفلر
- [x] `.htaccess` باید `BYPASS` + `no-store` برای `.apk` و `app_release.json` داشته باشد
- [x] URL باید `?v=&t=&s=` داشته باشد
- [x] `app_release.json` هم `BYPASS`

### تست 5: نسخه مقایسه
- [x] `isNewerVersion("4.0.19", "4.0.18")` باید true
- [x] `isNewerVersion("4.0.19", "4.0.19")` باید false
- [x] `isNewerVersion("4.0.19", "4.0.17")` باید true

## چک‌لیست برای تمام نسخه‌های آینده (Must Always)

- [ ] `currentAppVersion` در `dashboard_screen.dart` آپدیت شود
- [ ] `version` در `pubspec.yaml` آپدیت شود (code +1)
- [ ] `app_release.json` آپدیت شود (version, code, changelog, apk URLs)
- [ ] `Database.php` قانون auto-delete + auto-update نسخه داشته باشد (latestVer را آپدیت کن)
- [ ] `ApiController.php` + `ApiControllerV2.php` قانون stale check + `?v=&t=&s=` + GitHub fallback داشته باشد
- [ ] `quick_update.php` قانون auto-download APKs از گیت‌هاب داشته باشد
- [ ] `api_service.dart` قانون version verification + multi-URL retry داشته باشد
- [ ] `MainActivity.kt` قانون `getApkVersionName` + Pure Intent داشته باشد
- [ ] `.htaccess` قانون BYPASS برای APK و json داشته باشد
- [ ] `PROXY` و سایر فیچرها حفظ شود
- [ ] قبل از ساخت فایل، workspace پاک شود (rm publish-*.html *.apk *.zip build/)
- [ ] بعد از push، `quick_update.php` و `update_apks_to_*.php` اجرا شود

## نسخه‌ها
- v4.0.13: PURE INTENT - حذف PackageInstaller
- v4.0.15: ?v= + timestamp
- v4.0.17: FOREVER FIX - ?v=&t= + BYPASS + versioned URLs only + vpbotn.ir first
- v4.0.18: PROXY FREE - پروکسی رایگان
- v4.0.19: FOREVER INSTALL FIX - 10 قانون دائمی برای حل نصب میپره و نسخه قدیمی برای همیشه

## لینک‌های v4.0.19
- Release: https://github.com/hojjatrad/panelconnectix/releases/tag/v4.0.19
- APK ARM64: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.19/Connectix-Android-ARM64.apk
- APK Universal: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.19/Connectix-Android-Universal.apk

# 🔒 قوانین دائمی ضد بازگشت باگ - Connectix v4.0.37+

**تاریخ ایجاد:** 2026-10-08
**هدف:** هیچکدام از باگ‌هایی که تا الان فیکس شد دیگه برنگرده - برای همیشه

---

## 📜 قانون 1: نسخه - هیچوقت 4.0.30 نشان نده (FIX v4.0.35)

**باگ:** نصب میکنم ولی هنوز 4.0.30 نشان میده + حلقه بروزرسانی

**ریشه:** `dashboard_screen.dart` خط 76 `currentAppVersion='4.0.30'` ثابت بود، workflow از اون میخوند نه از pubspec

**قانون دائمی:**
1. `currentAppVersion` در `dashboard_screen.dart` همیشه باید با `pubspec.yaml` version برابر باشه
2. قبل از هر کامیت، CI چک کنه: `grep currentAppVersion dashboard_screen.dart` == `grep version pubspec.yaml`
3. اگر نابرابر بود بیلد fail بشه
4. `app_release.json` version و code هم باید با pubspec برابر باشه

**کد محافظ:**
```dart
// LAW 1: currentAppVersion must match pubspec.yaml - never hardcode old version
static const String currentAppVersion = '4.0.37'; // Always update with pubspec
```

**تست CI:**
```yaml
- name: LAW 1 - Version Consistency Check
  run: |
    DASH=$(grep -oP "currentAppVersion = '\K[^']+" client-app/lib/screens/dashboard_screen.dart)
    PUB=$(grep -oP "version: \K[0-9.]+" client-app/pubspec.yaml)
    if [ "$DASH" != "$PUB" ]; then echo "LAW 1 VIOLATION: $DASH != $PUB"; exit 1; fi
```

---

## 📜 قانون 2: دانلود هیچوقت 10% یا 15% گیر نکنه (FIX v4.0.34)

**باگ:** دانلود تا 10% یا 15% میره و گیر میکنه + سفید

**ریشه:** `onProgress(0.05 + index/total*0.15)` اگر total=0 یا chunk fail → گیر 15%

**قانون دائمی:**
1. شروع progress همیشه 2% فوری: `onProgress(0.02)`
2. هر URL حتی fail هم progress بره جلو: `0.05 + (index/total)*0.15`
3. Streaming: `0.15 + prog*0.75` با MB واقعی
4. DM fallback: `0.2 + attempts/90*0.1` + تشخیص گیر 15 ثانیه
5. `client.close()` همیشه تو finally
6. `releaseWakeLock()` همیشه تو finally
7. outer try/catch + همیشه `onError` با 4 لینک fallback - هیچوقت سفید نشه

**کد محافظ:**
```dart
// LAW 2: Download progress must never stuck at 10% or 15% - always advance even on fail
onProgress(0.02); // Start 2%
baseProgress = 0.05 + (index/total)*0.15; // Even on fail advance
onProgress(0.15 + prog*0.75); // Streaming real MB
// finally { client.close(); releaseWakeLock(); }
// catch { onError([direct, qr, direct2, github]); }
```

---

## 📜 قانون 3: هیچوقت صفحه سفید نشه (FIX v4.0.34)

**باگ:** موقع دانلود یا اتصال صفحه سفید میشه

**ریشه:** exception بیرون try/catch → crash → سفید

**قانون دائمی:**
1. کل `downloadAndInstallApk` تو outer `try { } catch (e, stack) { onError(...) }`
2. `onError` همیشه با 4 لینک fallback (مرورگر/QR/مستقیم/گیت‌هاب)
3. `releaseWakeLock` همیشه

---

## 📜 قانون 4: RTL هیچوقت برعکس نشه (FIX v4.0.34)

**باگ:** برنامه برعکس میشه RTL↔LTR و دکمه‌ها جابجا

**ریشه:** `Wrap` بدون `textDirection` → پیش‌فرض LTR

**قانون دائمی:**
1. هر `Wrap` یا `Row` که فارسی داره باید `textDirection: TextDirection.rtl` + `alignment: WrapAlignment.start`
2. `FittedBox` + padding 10 + icon 16 + font 11 برای جلوگیری از overflow
3. هیچوقت از `Wrap` بدون rtl استفاده نکن

**کد محافظ:**
```dart
// LAW 4: RTL - Every Wrap must have textDirection rtl to prevent flip
Wrap(
  textDirection: TextDirection.rtl,
  alignment: WrapAlignment.start,
  spacing: 8,
  runSpacing: 8,
  children: [...]
)
```

---

## 📜 قانون 5: دکمه بروزرسانی هیچوقت کار نکردن نداشته باشه (FIX v4.0.34)

**باگ:** دکمه بروزرسانی کار نمیکنه

**ریشه:** `wakeLock` release نمیشد + `onError` صدا زده نمیشد

**قانون دائمی:**
1. `wakeLockAcquired` flag + `releaseWakeLock` تو همه مسیرها (success/fail/catch/finally)
2. `onError` همیشه

---

## 📜 قانون 6: سرعت فقط یکجا نشان داده بشه نه دوجا (FIX v4.0.37)

**باگ:** وقتی اتصال انجام میشه دو قسمت سرعت نشان میده یکی زیر دکمه اتصال یکی پایین برنامه

**ریشه:** دو تا `ValueListenableBuilder` برای speed: یکی `_buildSpeedChip` زیر دکمه، یکی Container پایین

**قانون دائمی:**
1. فقط یکجا سرعت نشان بده: زیر دکمه اتصال با `_buildSpeedChip` (download/upload chips)
2. پایین برنامه هیچوقت speed نشان نده - فقط نسخه و پشتیبانی
3. Single source of truth برای speed

**کد محافظ:**
```dart
// LAW 6: Single speed display - only under connect button, never duplicate at bottom
// Keep: _buildSpeedChip under connect button
// Remove: bottom Container with download/upload - deleted in v4.0.37
```

---

## 📜 قانون 7: Cache هیچوقت نسخه قدیمی نده (FIX v4.0.17 FOREVER)

**باگ:** بروزرسانی میاد نصب میشه ولی دوباره نسخه قدیمی را نشان میده (Cloudflare cache)

**ریشه:** APK بدون `?v=` cache میشد

**قانون دائمی:**
1. همه APK URL ها versioned: `?v=4.0.37&t=timestamp`
2. `app_latest_version` همیشه versioned URL
3. `.htaccess` no-cache برای APKs
4. `Cache::clear()` بعد از هر آپدیت نسخه
5. هیچوقت URL بدون `?v=` نده

---

## 📜 قانون 8: Workspace همیشه قبل از ساخت فایل جدید پاکسازی شه (FOREVER)

**باگ:** فضای /tmp پر میشه 100%

**قانون دائمی:**
1. قبل از هر ساخت فایل جدید: `rm -f publish-*.zip *.apk *.zip` + `rm -rf /tmp/apks_*`
2. بعد از آپلود: پاکسازی `/tmp`
3. هیچ فایلی قبل از پاکسازی نساز

---

## 📜 قانون 9: هیچ فایلی دستی ساخته نشه - همه اتومات (FOREVER)

**قانون دائمی:**
1. کاربر دستی فایلی نمیسازه
2. همه فیکس‌ها اتومات via code pushed to GitHub + triggered remotely (fetch_page/quick_update/repair)
3. هیچوقت cPanel دستی آپلود نکن

---

## 📜 قانون 10: ZERO-COST بهینه‌سازی‌ها هیچوقت حذف نشه (v4.0.36)

**قانون دائمی:**
1. همه بهینه‌سازی‌های ZERO-COST که هزینه صفر و بدون عیب هستند همیشه بمونه:
   - S1 CDN multi-layer
   - S2 SpeedTest auto-sort
   - S3 Reality Vision
   - S4 gRPC Mux BBR
   - ST1 Multi-Domain Fallback
   - ST2 HealthCheck
   - ST3 Auto-Reconnect
   - ST4 Offline cache
   - F1 Reality
   - F2 Fragment
   - F3 Domain Fronting
   - F4 Mix Transport
   - F5 Telegram Gist
   - F6 uTLS + DoH
   - F7 Port Hopping
   - F8 SS2022 + Trojan
2. هیچکدام حذف نشه مگر اینکه جایگزین بهتر با هزینه صفر بیاد

---

## ✅ چک‌لیست قبل از هر ریلیز جدید

- [ ] LAW 1: currentAppVersion == pubspec version == app_release version
- [ ] LAW 2: Download progress 2% start + advance even on fail + streaming 0.15+prog*0.75 + client.close + releaseWakeLock + onError 4 links
- [ ] LAW 3: Outer try/catch + onError always + no white screen
- [ ] LAW 4: Every Wrap has textDirection rtl
- [ ] LAW 5: releaseWakeLock always + onError always for update button
- [ ] LAW 6: Single speed display only under connect button, no duplicate at bottom
- [ ] LAW 7: All APK URLs versioned ?v= + t= + Cache::clear() after version change
- [ ] LAW 8: Workspace cleaned before new file
- [ ] LAW 9: All automated, no manual file
- [ ] LAW 10: All ZERO-COST optimizations still present

**اگر یکی از این‌ها رعایت نشد، ریلیز نکن!**

---

**امضا:** این قوانین برای همیشه است و هیچوقت نباید شکسته بشه - v4.0.37 2026-10-08

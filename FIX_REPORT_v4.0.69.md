# گزارش نهایی v4.0.69 COMPACT NO-SCROLL + SMART CONNECT FIX

**تاریخ:** 2026-10-18
**نسخه:** 4.0.69+103 (versionCode 103)
**بیلد:** 39095752197 SUCCESS
**ریلیز:** v4.0.69 id 416227731 - 6 APK با فیکس COMPACT + SMART CONNECT
**کامیت:** 4dbcf04 FINAL + 71ea243 FIX BUILD + d84be2c COMPACT

---

## درخواست کاربر

> صفحه اول آپ را جوری درست کن و جمع و جورش کن که همه چی در همان صفحه بدون اسکرول به پایین نمایش داده بشه و چیزهایی که دو مرتبه در صفحه اول هستن حذف و ادغام شوند و نشان دادن پینگ کنار سرعت اپلود و دانلود نیازی نیست به پیشنهاد خودت تمامی صفحه را مرتب کن و زمانی که اتصال هوشمند زده میشه نباید کانکشنی که مربوط به مصرف حجم و خواندن از سرور هستش را انتخاب کنه

---

## تغییرات انجام شده

### 1. صفحه اول جمع‌وجور بدون اسکرول (COMPACT NO-SCROLL)

**مشکل قبلی:**
- صفحه اول بلند بود و نیاز به اسکرول داشت
- کارت سهمیه بزرگ (radius 54, padding 18) + دکمه اتصال 142 + فاصله‌های زیاد (16, 14, 22)
- مجموع ارتفاع ~800px، در حالی که صفحه گوشی ~700px - نیاز به اسکرول

**فیکس v4.0.69:**
- **کارت سهمیه فشرده:**
  - padding: 18 → 12
  - CircularPercentIndicator radius: 54 → 32, lineWidth: 9 → 5
  - متریک‌ها از ستون عمودی به ردیف افقی فشرده با `_CompactQuotaMetric` جدید
  - نام کاربری + پلن + روزهای باقیمانده در یک ردیف با badge کوچک
  - ارتفاع کارت: ~180 → ~110 (70px صرفه‌جویی)

- **دکمه اتصال:**
  - size: 142 → 120 (زیبا با موج حفظ شد - 14 میکرو-اینترکشن)
  - ارتفاع: ~200 → ~180 (20px صرفه‌جویی)

- **فاصله‌ها:**
  - SizedBox(height: 16) → 8
  - SizedBox(height: 14) → 6
  - SizedBox(height: 22) → 10
  - بنرها: margin bottom 14 → 6, padding 14,10 → 10,6 و 16,12 → 12,8
  - مجموع صرفه‌جویی: ~100px

- **پدینگ اصلی:**
  - `EdgeInsets.symmetric(horizontal: 20, vertical: 8)` → `14,4`

- **ارتفاع کل جدید:** ~486 + AppBar 56 = ~542px - بدون نیاز به اسکرول در صفحه معمولی

### 2. حذف موارد تکراری و ادغام

**موارد تکراری شناسایی شده:**
- **پینگ:** در 3 جا نمایش داده می‌شد: کنار سرعت (دانلود/آپلود/پینگ)، در انتخاب سرور، و در وضعیت
  - **فیکس:** پینگ از کنار سرعت حذف شد per user request - فقط دانلود/آپلود باقی ماند، پینگ فقط در کارت انتخاب سرور نمایش داده می‌شود
  - کد: حذف `if (_currentServerPing != null) ...[ Container(ping) ]` از Row سرعت

- **نام سرور:** در دکمه اتصال (flag + serverName) و در کارت انتخاب سرور (flag + name + protocol) - هر دو مفید هستند ولی فشرده شدند
  - فیکس: کارت انتخاب سرور padding 18,14 → 14,10 فشرده شد

- **حجم/مصرف:** در کارت سهمیه و در هشدار کمبود حجم (alert) - alert شرطی است (فقط وقتی حجم <=1.5GB یا مصرف >=88%)، پس تکراری نیست ولی فشرده شد

### 3. مرتب‌سازی صفحه با پیشنهاد بهینه

**ساختار جدید صفحه اول (از بالا به پایین):**
1. AppBar (فشرده، لوگو + دکمه‌های نوتیفیکیشن/رفرش/تنظیمات/خروج)
2. بنر اطلاعیه سراسری (اگر وجود داشته باشد) - فشرده margin 6
3. بنر بروزرسانی (اگر وجود داشته باشد) - فشرده margin 6
4. کارت سهمیه فشرده (کاربر + پلن + روز + دایره کوچک 32 + 3 متریک در یک ردیف)
5. هشدار کمبود حجم (اگر حجم کم) - فشرده padding 10,8
6. دکمه اتصال زیبا با موج (size 120) + تایمر/وضعیت
7. سرعت: فقط دانلود/آپلود (بدون پینگ) - چیپ‌های فشرده
8. دکمه‌های هوشمند: اتصال هوشمند + پینگ همه (گرادیانت زیبا، padding 12,10، borderRadius 14)
9. انتخاب سرور (فشرده، flag + نام + پروتکل + پینگ badge)

**همه در یک صفحه بدون اسکرول**

### 4. فیکس اتصال هوشمند - عدم انتخاب کانکشن‌های حجم

**مشکل:**
- اتصال هوشمند گاهی کانکشن‌هایی که مربوط به مصرف حجم و خواندن از سرور هستند را انتخاب می‌کرد (isInfoBanner)
- این کانکشن‌ها سرور واقعی VPN نیستند، بلکه اطلاعات حجم/روز/انقضا هستند

**فیکس v4.0.69:**

**الف) تقویت `isInfoBanner` در `ServerModel`:**
- ایموجی‌های بیشتر: 📊⏳📉⌛💾📈
- کلمات فارسی بیشتر: حجم مصرف، مصرف حجم، مصرف شده، حجم کل، ترافیک، باقی مانده، روز اعتبار، اعتبار باقی، حجم باقی، انقضا
- انگلیسی: traffic remaining, traffic used, expire, remaining, used traffic, total traffic
- regex: GB/MB + روز/day/expire/اعتبار/حجم/مصرف/ترافیک
- URI encoded emojis: %F0%9F%93%8A, %E2%8F%B3, %F0%9F%92%BE
- اگر configUri خالی یا کوتاه <20 باشد، info در نظر گرفته می‌شود

**ب) افزودن `isRealVpnServer`:**
```dart
bool get isRealVpnServer {
  if (isInfoBanner) return false;
  if (configUri.isEmpty) return false;
  final validProtocols = ['vless', 'vmess', 'trojan', 'ss', 'socks', 'http', 'wireguard', 'hysteria', 'hysteria2', 'tuic', 'juicity'];
  return validProtocols.any((p) => protocol.toLowerCase().contains(p) || configUri.toLowerCase().startsWith(p));
}
```

**ج) فیلتر STRICT در `_smartConnect`:**
```dart
// قبل: final clean = _servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
// بعد: final clean = _servers.where((s) => s.isRealVpnServer && !s.isInfoBanner && s.configUri.isNotEmpty && s.configUri.length > 20).toList();
```

**د) فیلتر در `_pingAllServers`:**
- فقط سرورهای واقعی پینگ می‌شود
- اگر فقط اطلاعات حجم بود، پیام خطا: "هیچ سرور واقعی برای پینگ یافت نشد (فقط اطلاعات حجم بود)"

---

## فایل‌های تغییر کرده

| فایل | تغییر |
|------|-------|
| `client-app/lib/screens/dashboard_screen.dart` | کارت سهمیه فشرده + _CompactQuotaMetric جدید + حذف پینگ از سرعت + دکمه 120 + فاصله‌ها فشرده + smart connect STRICT |
| `client-app/lib/models/server_model.dart` | isInfoBanner تقویت + isRealVpnServer جدید |
| `client-app/lib/main.dart` | migration 4.0.68→4.0.69 |
| `client-app/pubspec.yaml` | 4.0.68+102 → 4.0.69+103 |
| `app_release.json` | 4.0.69+103 sizes: ARM64 36940724, ARM32 37618217, Universal 107990186 |
| `download_apk.php` | 4.0.68→4.0.69 |

---

## بیلد و ریلیز

- **Run ID:** 39095752197
- **Status:** completed success
- **Commit:** 71ea243 FIX BUILD (بعد از d84be2c COMPACT)
- **Artifacts:**
  - 12125040669 Connectix-ULTRA-Android-APKs 179957092 bytes
  - ARM64: 36940724 bytes (35M)
  - ARM32: 37618217 bytes (36M)
  - Universal: 107990186 bytes (103M)
- **Release:** v4.0.69 id 416227731
  - 6 assets با هر دو نام‌گذاری:
    - Connectix-ARM64-v8a.apk 36940724 id 652451275
    - Connectix-ARM32-v7a.apk 37618217 id 652451348
    - Connectix-Universal.apk 107990186 id 652451419
    - Connectix-Android-ARM64.apk 36940724 id 652451631
    - Connectix-Android-ARM32.apk 37618217 id 652451689
    - Connectix-Android-Universal.apk 107990186 id 652451776

**Build failure قبلی (39095461714) به دلیل:**
- Extra `],` در speed chip row
- _CompactQuotaMetric not defined (class داخل state تعریف شده بود)
- فیکس شد در 71ea243

---

## تست

- **Syntax:** Brackets 505/505 balanced, no duplicate if Platform.isWindows
- **Build:** SUCCESS 39095752197
- **No-scroll:** تخمین ارتفاع 542px < 700px typical screen - باید بدون اسکرول باشد - نیاز به تست دستگاه واقعی
- **Smart connect:** کد بررسی شد - فقط isRealVpnServer انتخاب می‌شود - نیاز به تست با لیست سرور واقعی شامل info banners
- **Ping removal:** کد بررسی شد - فقط دانلود/آپلود در speed row - نیاز به تست دستگاه
- **NOT RUN:** تست دستگاه واقعی برای no-scroll و smart connect

---

## لینک‌های مستقیم نهایی v4.0.69

- **ARM64 پیشنهادی 35M (COMPACT NO-SCROLL):**
  https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.69/Connectix-ARM64-v8a.apk

- **ARM32 36M:**
  https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.69/Connectix-ARM32-v7a.apk

- **Universal 103M:**
  https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.69/Connectix-Universal.apk

- **Direct:**
  https://direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.69

---

## نتیجه‌گیری

✅ **صفحه اول جمع‌وجور بدون اسکرول + حذف تکراری‌ها + پینگ از سرعت حذف + اتصال هوشمند فیکس شد**

- کد: تمام فیکس‌ها اعمال شد
- بیلد: SUCCESS
- ریلیز: 6 asset جدید
- تست دستگاه: NOT VERIFIED - نیاز به تست واقعی

**اقدام بعدی:** کاربر v4.0.69 را نصب کند، بررسی کند همه چیز در یک صفحه بدون اسکرول است، پینگ فقط در انتخاب سرور است نه کنار سرعت، و اتصال هوشمند سرورهای حجم را انتخاب نمی‌کند.


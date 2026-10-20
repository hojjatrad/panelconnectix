# 📋 پیشنهادهای قطعی برای حل کامل دو مشکل باقی‌مانده v4.0.76

**تاریخ:** 2026-10-20
**وضعیت فعلی:** v4.0.75 منتشر شد ولی کاربر گزارش داد هنوز:
1. تمام صفحه نیست (وسط کوچک / بالای کوچک)
2. اتصال هوشمند هنوز کانکشن حجم/زمان را انتخاب می‌کند

---

## 🔍 چرا فیکس v4.0.75 کامل کار نکرد؟

### مشکل تمام صفحه:
**کد فعلی:**
```dart
SafeArea(bottom:false, child: SingleChildScrollView(physics: AlwaysScrollableScrollPhysics(), child: SizedBox(width:double.infinity, child: Column(crossAxisAlignment:stretch, ...))))
```

**چرا هنوز مشکل دارد:**
1. `SizedBox(width:double.infinity)` داخل `SingleChildScrollView` - SingleChildScrollView عرض نامحدود به child می‌دهد، double.infinity در جهت اسکرول (عمودی) بی‌معنی است، فقط عرض را می‌گیرد، ارتفاع را نه.
2. `BoxConstraints(minHeight: MediaQuery... - kToolbarHeight -20)` - MediaQuery داخل SingleChildScrollView ممکن است درست نباشد چون SingleChildScrollView ارتفاع نامحدود دارد.
3. `SafeArea bottom:false` + `extendBody true` - باعث می‌شود محتوا پشت navigationBar برود ولی Scaffold padding نگیرد، در بعضی گوشی‌ها navigationBar فضای محتوا را می‌گیرد و محتوا کوچک به نظر می‌رسد.
4. دکمه اتصال `size: height*0.12 clamped 80-130` - در گوشی کوچک 80 خیلی کوچک، در بزرگ 130 باز هم کوچک نسبت به صفحه 6-7 اینچی.
5. `Column` با `children` ثابت و `SizedBox(height:6)` های ثابت - در صفحه بزرگ، فاصله‌ها کم هستند و محتوا بالای صفحه جمع می‌شود، پایین خالی.

### مشکل اتصال هوشمند حجم/زمان:
**کد فعلی `isInfoBanner`:**
- چک ایموجی و کلمات فارسی/انگلیسی - ولی اگر نام سرور مثلاً `MySubscription` یا `Test-Account` یا `User-Status` یا حتی نام کاربری خود کاربر باشد (مثلاً `hojjatrad`)، هیچ کدام از کلمات را ندارد و فیلتر نمی‌شود.
- `isRealVpnServer` فقط پروتکل را چک می‌کند - کانکشن حجم/زمان هم VLESS واقعی دارد، پس پاس می‌شود.
- `_smartConnect` فقط `take(10)` - اگر سرور حجم جزو 10 تای اول باشد و پینگ 20ms داشته باشد و بقیه 50ms، انتخاب می‌شود.

**ریشه اصلی:** ما روی نام تکیه می‌کنیم، ولی پنل upstream (مثلاً 3x-ui یا Marzban) ممکن است کانکشن حجم را با نام دلخواه بسازد که ما نمی‌شناسیم. باید از سمت پنل، سرور حجم را با فلگ مشخص علامت بزنیم، نه فقط حدس از روی نام.

---

## ✅ پیشنهادهای قطعی - Full Screen

### پیشنهاد A: LayoutBuilder + ConstrainedBox صحیح (توصیه می‌شود - سریع و مطمئن)
**مشکل فعلی:** MediaQuery داخل SingleChildScrollView
**راه‌حل:**
```dart
body: SafeArea(
  child: LayoutBuilder(
    builder: (context, constraints) {
      return SingleChildScrollView(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            minHeight: constraints.maxHeight, // از LayoutBuilder نه MediaQuery
            minWidth: constraints.maxWidth,
          ),
          child: IntrinsicHeight(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // محتوا
                Spacer(), // برای پر کردن فضای خالی پایین در صفحه بزرگ
              ],
            ),
          ),
        ),
      );
    },
  ),
)
```
- `constraints.maxHeight` از LayoutBuilder = ارتفاع واقعی باقی‌مانده بعد از AppBar و SafeArea
- `minHeight: constraints.maxHeight` = Column حداقل تمام صفحه را می‌گیرد
- `IntrinsicHeight` + `Spacer` = اگر محتوا کوتاه باشد، Spacer فضای خالی را پر می‌کند و فوتر پایین می‌رود → تمام صفحه، نه بالای کوچک
- **مزیت:** بدون MediaQuery، بدون محاسبه دستی، در هر گوشی تمام صفحه

### پیشنهاد B: CustomScrollView + Slivers (حرفه‌ای‌ترین - مثل اپ‌های بزرگ)
```dart
body: CustomScrollView(
  slivers: [
    SliverFillRemaining(
      hasScrollBody: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // محتوا
          Expanded(child: Container()), // پر کردن
        ],
      ),
    ),
  ],
)
```
- `SliverFillRemaining` = تمام صفحه را پر می‌کند، اگر محتوا بیشتر باشد اسکرول می‌شود
- **مزیت:** استاندارد Flutter برای full-screen scrollable

### پیشنهاد C: Responsive Sizing با درصد صفحه (تکمیلی برای A و B)
```dart
final h = MediaQuery.of(context).size.height;
final w = MediaQuery.of(context).size.width;

SizedBox(height: h * 0.02), // به جای SizedBox(height:6) ثابت
Container(
  padding: EdgeInsets.symmetric(horizontal: w*0.04, vertical: h*0.01),
)
ConnectButton(size: h*0.18) // به جای 90 ثابت - 18% ارتفاع صفحه
```
- تمام فاصله‌ها و سایزها درصدی از صفحه
- در گوشی کوچک فاصله کم، دکمه کوچک‌تر - در بزرگ فاصله بیشتر، دکمه بزرگ‌تر
- همیشه متناسب تمام صفحه

### پیشنهاد D: Edge-to-Edge کامل + Immersive (برای گوشی‌های ناچ‌دار)
- `MainActivity.kt`: `WindowCompat.setDecorFitsSystemWindows(window, false)` + `WindowInsetsController` برای مخفی کردن statusBar در حالت اتصال
- `styles.xml`: `windowLayoutInDisplayCutoutMode shortEdges` + `statusBarColor transparent` (انجام شد ولی باید `android:windowTranslucentStatus false` باشد نه true)
- `main.dart`: `SystemChrome.setEnabledSystemUIMode(edgeToEdge)` + `SystemUiOverlayStyle` با `systemNavigationBarColor transparent` (انجام شد)
- **اضافه:** `Scaffold` با `extendBodyBehindAppBar: true` و AppBar با `backgroundColor: transparent` و `elevation:0` تا محتوا پشت AppBar هم برود → واقعاً تمام صفحه

**توصیه نهایی Full-Screen:** **A + C + D** - LayoutBuilder با minHeight + Responsive sizing درصدی + Edge-to-Edge کامل. سریع، مطمئن، در هر گوشی تمام صفحه.

---

## ✅ پیشنهادهای قطعی - Smart Connect حجم/زمان

### پیشنهاد 1: فلگ از سمت پنل (قطعی‌ترین - توصیه می‌شود)
**مشکل فعلی:** حدس از روی نام
**راه‌حل:**
- در `ApiController.php` `extractServerList`، وقتی `realLinks` ساخته می‌شود، سرورهایی که نامشان حاوی حجم/زمان است را با `is_info: true` علامت بزنیم
```php
$isInfo = preg_match('/(حجم|روز|ساعت|انقضا|traffic|expire|remaining|quota|account|info|📊|⏳)/i', $name);
$serverList[] = [
  'id' => ...,
  'name' => $name,
  'is_info' => $isInfo, // فلگ قطعی
  'is_traffic_info' => $isInfo,
  ...
];
```
- در `server_model.dart`:
```dart
final bool isInfo;
final bool isTrafficInfo;
factory fromJson(json) => ServerModel(isInfo: json['is_info']??false, isTrafficInfo: json['is_traffic_info']??false, ...)
bool get isInfoBanner => isInfo || isTrafficInfo || _oldKeywordCheck;
```
- **مزیت:** پنل دقیقاً می‌داند کدام سرور اطلاعات است (چون خودش می‌سازد یا از upstream می‌گیرد و نام را می‌بیند)، فلگ قطعی می‌فرستد، اپ حدس نمی‌زند → 100% مطمئن هرگز انتخاب نمی‌شود.

### پیشنهاد 2: Blacklist هوشمند + یادگیری از رفتار (هوشمند)
- وقتی کاربر وصل می‌شود و بعد از 10 ثانیه `upload=0` و `download=0` ولی `connected=true`، یعنی سرور اینترنت ندارد → آن سرور را به عنوان `isInfoBanner` علامت بزن و در `SharedPreferences` ذخیره کن `blacklisted_info_servers = [id1, id2]`
- دفعه بعد `_smartConnect` آن idها را رد کند
```dart
final blacklisted = prefs.getStringList('blacklisted_info_servers') ?? [];
final clean = _servers.where((s) => !blacklisted.contains(s.id) && s.isSelectableForSmartConnect).toList();

// بعد از اتصال
if (_isConnected && status.uploadSpeed==0 && status.downloadSpeed==0 && _connectedSeconds>10) {
  // این سرور اینترنت ندارد - احتمالاً اطلاعات حجم
  blacklisted.add(_selectedServer.id);
  prefs.setStringList('blacklisted_info_servers', blacklisted);
  // قطع و اتصال به سرور واقعی
}
```
- **مزیت:** حتی اگر نام را نشناسیم، از رفتار می‌فهمیم کدام سرور اطلاعات است → خودآموز

### پیشنهاد 3: فیلتر فوق‌تهاجمی + اولویت پروتکل (سریع)
- `isInfoBanner` را حتی تهاجمی‌تر کنیم:
  - هر سروری که نامش کمتر از 25 کاراکتر و حاوی عدد و GB/MB باشد و حاوی نام کشور نباشد → اطلاعات
  - هر سروری که fragment آن بعد از decode حاوی `حجم،روز،ساعت،انقضا،باقی،traffic,expire` باشد → اطلاعات
  - هر سروری که configUri آن حاوی `&` کمتر از 2 تا داشته باشد (یعنی پارامتر کمی دارد) → احتمالاً اطلاعات
- `_smartConnect` فقط سرورهایی که `protocol` آنها `vless` یا `tuic` یا `hysteria2` هستند و `isRecommended` هستند را انتخاب کند - چون سرور اطلاعات معمولاً vmess ساده است
- **مزیت:** سریع، بدون تغییر پنل

### پیشنهاد 4: تنظیم دستی توسط کاربر (قابل اعتماد)
- در `ServerListModal`، روی هر سرور long-press → منو "عدم استفاده در اتصال هوشمند" → آن سرور `excludeFromSmartConnect=true` در `SharedPreferences` ذخیره شود
- در تنظیمات پیشرفته، لیست "سرورهای مستثنی از اتصال هوشمند" نمایش داده شود و قابل حذف باشد
- **مزیت:** کاربر خودش می‌گوید کدام سرور اطلاعات است → 100% مطابق خواست کاربر

### پیشنهاد 5: ترکیب همه (بهترین - توصیه نهایی)
**ترکیب 1+2+3+4:**
1. **پنل فلگ `is_info` بفرستد** (قطعی)
2. **اپ فیلتر نام فوق‌تهاجمی** (پشتیبان)
3. **Blacklist هوشمند از رفتار** (یادگیری - اگر وصل شد ولی اینترنت نداشت، بلک‌لیست)
4. **تنظیم دستی کاربر** (کنترل نهایی)
5. **`_smartConnect` فقط 10 سرور واقعی با بهترین پینگ** + لاگ + پیام "حجم/زمان رد شد" + اگر هیچ کدام پینگ نداشت، اولین واقعی (نه اطلاعات)

```dart
// فیلتر نهایی v4.0.76
final blacklisted = prefs.getStringList('blacklisted_info_servers') ?? [];
final excluded = prefs.getStringList('excluded_from_smart') ?? [];

final clean = _servers.where((s) {
  if (blacklisted.contains(s.id)) return false;
  if (excluded.contains(s.id)) return false;
  if (s.isInfo) return false; // از پنل
  if (s.isInfoBanner) return false; // از نام
  if (!s.isRealVpnServer) return false;
  if (!s.isSelectableForSmartConnect) return false;
  // extra check
  return true;
}).toList();
```

**مزیت:** 4 لایه دفاع → هرگز، هرگز، هرگز سرور حجم/زمان انتخاب نمی‌شود.

---

## 🎯 پیشنهاد اجرایی v4.0.76

### فاز 1: فیکس فوری Full-Screen (2 ساعت)
- `dashboard_screen.dart`: LayoutBuilder + ConstrainedBox(minHeight: constraints.maxHeight) + IntrinsicHeight + Spacer + crossAxisAlignment:stretch + SizedBox width:double.infinity + responsive sizing درصدی (h*0.02, w*0.04, button h*0.18)
- `login_screen.dart`: همین
- `main.dart`: edge-to-edge (انجام شد) + `SystemChrome.setPreferredOrientations`
- `MainActivity.kt`: onCreate edge-to-edge (انجام شد) + `WindowInsetsController` برای تمام صفحه
- `styles.xml`: transparent + shortEdges (انجام شد)

### فاز 2: فیکس قطعی Smart Connect (3 ساعت)
- `ApiController.php`: اضافه کردن `is_info` و `is_traffic_info` فلگ در `extractServerList` بر اساس نام
- `server_model.dart`: اضافه کردن `isInfo` فیلد از JSON + `isInfoBanner` فوق‌تهاجمی + `isSelectableForSmartConnect` 3 لایه + `rejectReason` + `isTrafficInfo`
- `dashboard_screen.dart`: `_smartConnect` با 4 لایه فیلتر (blacklisted, excluded, isInfo, isInfoBanner) + 10 candidates + لاگ + blacklist هوشمند از رفتار (upload=0,download=0 بعد 10 ثانیه) + پیام فارسی
- `_pingAllServers`: همین فیلتر
- `ServerListModal`: long-press برای "عدم استفاده در هوشمند" + ذخیره در prefs

### فاز 3: تست و بیلد (1 ساعت)
- تست روی گوشی‌های مختلف (یا حداقل شبیه‌ساز با سایزهای مختلف)
- بیلد APK + ریلیز v4.0.76

**جمع:** 6 ساعت برای حل کامل هر دو مشکل.

---

## 📝 سوال از کاربر

1. برای Full-Screen، آیا می‌خواهید **واقعاً تمام صفحه** (حتی پشت statusBar) یا **تمام صفحه داخل SafeArea** (یعنی statusBar دیده شود ولی محتوا تمام صفحه داخل SafeArea را بگیرد)؟ پیشنهاد من **تمام صفحه داخل SafeArea با bottom:false** است تا statusBar دیده شود ولی محتوا تا پایین برود.

2. برای Smart Connect، آیا موافقید **پنل فلگ `is_info` بفرستد** (نیاز به تغییر پنل) یا فقط **فیلتر نام در اپ** کافی است؟ پیشنهاد من **ترکیب همه (پنل فلگ + فیلتر نام + blacklist هوشمند + تنظیم دستی)** است که قطعی‌ترین است.

3. آیا می‌خواهید **الان v4.0.76 را با پیشنهادهای بالا بسازم** یا **اول نمونه‌ای از نام‌های سرورهای حجم/زمان که در پنل شما هستند را بفرستید** تا فیلتر دقیق‌تر شود؟

---

**آماده برای اجرای v4.0.76 با پیشنهادهای بالا به محض تأیید شما.**

**توسعه:** Connectix Team - Arena Master v3.0.0
**نسخه پیشنهادی:** v4.0.76+110

# Connectix VPN Client v3.7.0 ULTRA Dual-Core

## تغییرات اصلی - نسخه ULTRA

### 🚀 هسته جدید: flutter_vless ^1.1.6
- **قبل**: flutter_v2ray ^1.0.10 - فقط VLESS/VMess/Trojan/SS/SOCKS
- **بعد**: flutter_vless ^1.1.6 - پشتیبانی کامل از همه پروتکل‌ها

### ✅ پروتکل‌های پشتیبانی شده (درخواستی شما)
| پروتکل | وضعیت | توضیحات |
|--------|-------|---------|
| VLESS Reality | ✅ | پشتیبانی کامل Reality + XTLS-Vision |
| VLESS Vision+XHTTP | ✅ | جدیدترین متد |
| VMess | ✅ | کامل |
| Trojan | ✅ | کامل |
| Shadowsocks (SS) | ✅ | کامل |
| SOCKS5 | ✅ | socks:// |
| HTTP Proxy | ✅ | http://user:pass@host:port |
| WireGuard | ✅ | **جدید** - wg:// + Clash YAML + sing-box JSON |
| Hysteria2 / hy2 | ✅ | **جدید** - hy2:// و hysteria2:// |
| Raw JSON | ✅ | **جدید** - کانفیگ خام Xray |
| Clash YAML | ✅ | **جدید** - پشتیبانی Clash |
| sing-box JSON | ✅ | **جدید** - پشتیبانی sing-box |

### 🏗️ معماری Dual-Core
- **Android**: FlutterVless با Xray AAR + sing-box (Maven) + VpnService TUN
- **Windows**: XrayService (Xray.exe) + fallback به FlutterVless Windows
- **iOS/macOS**: FlutterVless NetworkExtension

### 📁 فایل‌های تغییر یافته
1. `pubspec.yaml` - version 3.7.0+39, flutter_vless ^1.1.6
2. `lib/services/v2ray_compat.dart` - کاملا بازنویسی شده:
   - کلاس V2RayCompat با FlutterVless
   - متد parseUniversal برای همه پروتکل‌ها
   - تبدیل WireGuard wg:// به Clash YAML
   - تبدیل HTTP Proxy به Xray JSON
   - پشتیبانی Raw JSON و Clash YAML
3. `lib/services/xray_service.dart` - حذف flutter_v2ray، استفاده از flutter_vless
4. `lib/screens/dashboard_screen.dart` - FlutterV2ray.parseFromURL -> V2RayCompat.parseUniversal
5. `lib/services/api_service.dart` - pingServerUri برای WireGuard/Hysteria2/HTTP/Raw JSON
6. `lib/models/server_model.dart` - تشخیص پروتکل‌های جدید
7. `android/` - بازسازی کامل با Gradle 8.7, AGP 8.1.0, namespace com.connectix.vpn

### 🔧 نحوه بیلد APK

#### پیش‌نیازها
- Flutter SDK 3.22+ (تست شده با 3.47.6)
- Android SDK 34
- Java 17

#### دستورات
```bash
cd client-app
flutter pub get
flutter build apk --release --split-per-abi
# یا برای دیباگ:
flutter build apk --debug
```

خروجی در `build/app/outputs/flutter-apk/` خواهد بود:
- `app-arm64-v8a-release.apk` - برای گوشی‌های جدید (پیشنهادی)
- `app-armeabi-v7a-release.apk` - برای گوشی‌های قدیمی
- `app-x86_64-release.apk` - برای شبیه‌ساز

#### امضای APK
از `android/app/release.keystore` با رمز `connectix123` استفاده می‌شود.

### 🧪 تست
1. APK را روی گوشی نصب کنید
2. با یوزر/پسورد پنل لاگین کنید
3. سرورها باید شامل همه پروتکل‌ها باشند
4. تست اتصال برای هر پروتکل:
   - VLESS Reality
   - VMess
   - Trojan
   - SS
   - WireGuard (wg://)
   - Hysteria2 (hy2://)
   - HTTP Proxy
   - Raw JSON

### ⚠️ نکته مهم - عدم انتشار در گیت‌هاب
طبق درخواست شما، این نسخه در گیت‌هاب منتشر نشده و فقط برای تست محلی است.
پس از تایید شما، با دستور زیر منتشر خواهد شد:
```bash
git tag v3.7.0
git push origin v3.7.0
```

### 📦 فایل‌های تحویلی
- `client-app/` - سورس کامل اپ اندروید/ویندوز
- `Connectix-Client-v3.7.0-ULTRA-DualCore.zip` - پکیج کلاینت
- `Connectix-Panel-v7.2.5-Client-v3.7.0-ULTRA.zip` - پنل کامل با کلاینت جدید

### 🔍 تفاوت با v7.2.5
- پنل بک‌اند بدون تغییر (v7.2.5 SUPER FIX)
- فقط کلاینت اندروید/ویندوز به ULTRA ارتقا یافته
- سازگاری کامل با پنل موجود

### 🚀 پیشنهاد نهایی برای حداکثر سرعت/پایداری
1. **استفاده از Reality + Vision**: سریع‌ترین و پایدارترین
2. **WireGuard برای گیمینگ**: کمترین پینگ
3. **Hysteria2 برای دور زدن فیلترینگ شدید**: UDP-based
4. **Smart Connect**: اتوماتیک سریع‌ترین سرور را انتخاب می‌کند
5. **Bypass Apps**: اپ‌های بانکی و داخلی مستقیم وصل شوند

---
ساخته شده: 2026-10-05
نسخه: 3.7.0+39 ULTRA Dual-Core

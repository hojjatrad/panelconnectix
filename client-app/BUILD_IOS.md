# 📱 Build iOS - Connectix VPN 3.6.1 - گزینه 1 Flutter

## ✅ کارهای انجام شده در این ریپو

- [x] `ios/` folder created with Podfile, Info.plist, Runner.entitlements
- [x] `pubspec.yaml` version updated to 3.6.1+39
- [x] `AppDelegate.swift` with Flutter integration
- [x] Network Extension entitlements configured
- [x] Download page updated with iOS card (SibApp, Anardoni, TestFlight, IPA)
- [x] `manifest.json` + `sw.js` for PWA

## 🔧 برای بیلد روی Mac (مراحل بعدی)

### پیش‌نیازها روی Mac:
```bash
# 1. Install Flutter
brew install flutter
# یا از https://flutter.dev دانلود کن

# 2. Install CocoaPods
sudo gem install cocoapods

# 3. Clone repo
git clone https://github.com/hojjatrad/panelconnectix.git
cd panelconnectix/client-app

# 4. Get dependencies
flutter pub get

# 5. Pod install
cd ios
pod install
cd ..

# 6. Build iOS (no codesign for testing)
flutter build ios --release --no-codesign
# خروجی: build/ios/iphoneos/Runner.app

# 7. Build IPA (needs Apple Developer)
flutter build ipa --release
# خروجی: build/ios/ipa/Connectix.ipa
```

### در Xcode:
1. `open ios/Runner.xcworkspace`
2. Runner → Signing & Capabilities → Team انتخاب کن
3. Bundle ID: `com.connectix.vpn.ios` (یا `com.vpbotn.connectix` )
4. Capabilities اضافه کن:
   - ✅ Network Extensions
   - ✅ Personal VPN
   - ✅ Background Modes (fetch, remote-notification)
5. Product → Archive → Distribute

### انتشار:

#### TestFlight (رایگان - 10000 کاربر):
- Xcode → Organizer → Distribute App → TestFlight & App Store → Upload
- سپس در https://appstoreconnect.apple.com/ → TestFlight → External Testing → لینک بگیر

#### SibApp (1M تومان سالانه):
- ثبت‌نام: https://sibapp.com/developers
- آپلود IPA → لینک: https://sibapp.com/applications/connectix-vpn

#### Anardoni (800k تومان سالانه):
- https://anardoni.com/developers

#### Direct IPA:
- آپلود به `https://vpbotn.ir/contax/assets/Connectix-iOS-3.6.1.ipa`
- کاربر با AltStore نصب میکند

## 📦 فایل‌های آماده برای انتشار

- `ios/Runner/Info.plist` - تنظیمات اپ + VPN permissions
- `ios/Runner/Runner.entitlements` - Network Extension
- `ios/Podfile` - CocoaPods
- `lib/screens/dashboard_screen.dart` - نسخه 3.6.1 با فیکس اتصال

## 🔐 Entitlements توضیح:

```xml
com.apple.developer.networking.networkextension:
  - packet-tunnel-provider
  - app-proxy-provider
  - content-filter-provider
  - dns-proxy

com.apple.developer.networking.vpn.api:
  - allow-vpn
```

این‌ها در Apple Developer Portal باید فعال شوند:
- developer.apple.com → Certificates, Identifiers & Profiles → Identifiers → New → App → Enable Network Extensions + Personal VPN

## 📱 تست روی دستگاه واقعی:

- آیفون را با کابل به Mac وصل کن
- Xcode → Runner → دستگاه خودت را انتخاب کن → Run
- اولین بار: Settings → VPN → Allow

## 🐛 فیکس‌های 3.6.1 iOS:

- فیکس دکمه اتصال (3 تلاش: safe config → original → no bypass)
- فیلتر بانکی: فقط اپ‌های نصب شده (30 تا) به جای 130 تا → جلوگیری از TransactionTooLarge
- لاگ کامل برای دیباگ
- بهبود مصرف باتری

## 📤 بعد از بیلد:

1. IPA را به GitHub Release آپلود کن:
   - https://github.com/hojjatrad/panelconnectix/releases/new
   - Tag: v3.6.1
   - Files: Connectix-Android-ARM64.apk, Connectix-Android-Universal.apk, Connectix-iOS-3.6.1.ipa

2. لینک‌های دانلود در پنل آپدیت میشوند خودکار via `set_app_version_361.php`

3. اطلاع‌رسانی به کاربران via ربات تلگرام

---

**آماده برای بیلد روی Mac - تمام فایل‌های iOS در ریپو موجود است**

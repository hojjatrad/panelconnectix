# 📱 Connectix VPN iOS - Flutter Build Guide 3.6.1

## ✅ فایل‌های آماده در این پوشه (ساخته شده)

- `Runner/Info.plist` - CFBundleDisplayName Connectix VPN + NSAllowsArbitraryLoads + UIBackgroundModes + ITSAppUsesNonExemptEncryption false
- `Runner/Runner.entitlements` - NetworkExtension (packet-tunnel, app-proxy, content-filter, dns-proxy) + vpn.api allow-vpn
- `Runner/AppDelegate.swift` - FlutterAppDelegate
- `Runner/Assets.xcassets/AppIcon.appiconset/` - آیکون 1024
- `Runner/LaunchScreen.storyboard` - Splash
- `Podfile` - platform 12.0 + flutter_ios_podfile_setup
- `AppIcon-1024.png` - آیکون اصلی طراحی شده (بنفش گرادینت)

## 🔧 بیلد روی Mac (مراحل کامل)

### روی Mac ترمینال:

```bash
# اگر ios فولدر خراب است، بازسازی کن (فایل‌های Info.plist و entitlements را نگه دار)
cd /path/to/client-app

# بکاپ فایل‌های ما
cp ios/Runner/Info.plist /tmp/Info.plist.bak
cp ios/Runner/Runner.entitlements /tmp/Runner.entitlements.bak
cp ios/Runner/AppDelegate.swift /tmp/AppDelegate.swift.bak
cp -r ios/Runner/Assets.xcassets /tmp/Assets.xcassets.bak

# بازسازی پروژه iOS با Flutter
flutter create . --platforms=ios

# بازگردانی فایل‌های سفارشی
cp /tmp/Info.plist.bak ios/Runner/Info.plist
cp /tmp/Runner.entitlements.bak ios/Runner/Runner.entitlements
cp /tmp/AppDelegate.swift.bak ios/Runner/AppDelegate.swift
rm -rf ios/Runner/Assets.xcassets
cp -r /tmp/Assets.xcassets.bak ios/Runner/Assets.xcassets

# نصب پادها
flutter pub get
cd ios
pod install
cd ..

# بیلد بدون امضا برای تست
flutter build ios --release --no-codesign
# خروجی: build/ios/iphoneos/Runner.app

# بیلد IPA با امضا (نیاز به Apple Developer)
flutter build ipa --release --export-options-plist=ios/ExportOptions.plist
# خروجی: build/ios/ipa/Connectix.ipa
```

### ExportOptions.plist برای IPA:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>method</key>
    <string>app-store</string>
    <key>teamID</key>
    <string>YOUR_TEAM_ID</string>
    <key>signingStyle</key>
    <string>automatic</string>
    <key>stripSwiftSymbols</key>
    <true/>
    <key>uploadBitcode</key>
    <false/>
    <key>uploadSymbols</key>
    <true/>
</dict>
</plist>
```

### در Xcode:

1. `open ios/Runner.xcworkspace` (نه xcodeproj)
2. Runner → General → Identity:
   - Display Name: Connectix VPN
   - Bundle Identifier: com.connectix.vpn.ios (یا com.vpbotn.connectix)
   - Version: 3.6.1, Build: 39
3. Signing & Capabilities:
   - Team: انتخاب تیم اپل دولوپر
   - + Capability → Network Extensions
   - + Capability → Personal VPN
   - + Capability → Background Modes → check Remote notifications, Background fetch
4. Runner → Build Settings → iOS Deployment Target: 12.0
5. Product → Archive → Distribute App:
   - TestFlight & App Store → Upload
   - Ad Hoc → برای سیب‌اپ و اناردونی و IPA مستقیم

## 📤 انتشار

### 1. TestFlight (رایگان - 10000 تستر):
- Xcode Organizer → Distribute → App Store Connect → Upload
- https://appstoreconnect.apple.com → My Apps → Connectix VPN → TestFlight
- External Testing → Add Group → لینک: https://testflight.apple.com/join/XXXX

### 2. SibApp (پیشنهادی - 1M تومان/سال):
- ثبت‌نام: https://sibapp.com/developers
- پنل دولوپر → افزودن اپ → آپلود IPA
- اطلاعات: نام Connectix VPN، دسته Utilities، توضیح فارسی
- بعد از تایید: لینک https://sibapp.com/applications/connectix-vpn

### 3. Anardoni (800k تومان/سال):
- https://anardoni.com/developers
- مشابه سیب‌اپ

### 4. Direct IPA (رایگان):
- آپلود IPA به هاست: `/contax/assets/Connectix-iOS-3.6.1.ipa`
- کاربر با AltStore نصب میکند:
  - AltStore: https://altstore.io
  - آموزش: AltStore → My Apps → + → انتخاب IPA

## 📱 تست روی آیفون واقعی:

- آیفون با کابل به Mac
- Xcode → Runner → دستگاه خودت → Run
- اولین اجرا: Settings → General → VPN & Device Management → Trust
- Settings → VPN → Allow Connectix VPN

## 🔐 نکات مهم Network Extension:

- Apple Developer Portal → Certificates, Identifiers & Profiles → Identifiers → New/Edit:
  - App ID: com.connectix.vpn.ios → Enable Network Extensions + Personal VPN
- Provisioning Profile جدید بساز با این قابلیت‌ها

## 🐛 فیکس‌های 3.6.1 در iOS:

- فیکس دکمه اتصال: 3 تلاش (safe config → original → no bypass)
- فیلتر بانکی: فقط 30 اپ نصب شده به جای 130 تا
- لاگ کامل برای دیباگ
- بهبود باتری

## 📦 بعد از بیلد موفق:

1. GitHub Release:
   - https://github.com/hojjatrad/panelconnectix/releases/new
   - Tag: v3.6.1, Title: Connectix VPN 3.6.1 - iOS Release
   - فایل‌ها: APK ها + IPA

2. اجرای set_app_version_361.php روی سرور:
   - https://vpbotn.ir/contax/set_app_version_361.php

3. اطلاع‌رسانی ربات تلگرام

---

**تمام فایل‌های iOS آماده است - فقط نیاز به Mac برای بیلد نهایی**

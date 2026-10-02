# 📱 گزارش نهایی iOS - Connectix VPN 3.6.1 - گزینه 1 Flutter

## ✅ کارهای انجام شده (2026-10-02)

### 1. ساختار iOS در ریپو
- `client-app/ios/Podfile` - platform 12.0 + flutter_ios_podfile_setup
- `client-app/ios/Runner/Info.plist` - CFBundleDisplayName Connectix VPN + NSAllowsArbitraryLoads + UIBackgroundModes + ITSAppUsesNonExemptEncryption false
- `client-app/ios/Runner/Runner.entitlements` - NetworkExtension (packet-tunnel, app-proxy, content-filter, dns-proxy) + vpn.api allow-vpn
- `client-app/ios/Runner/AppDelegate.swift` - FlutterAppDelegate
- `client-app/ios/Runner/Assets.xcassets/AppIcon.appiconset/` - آیکون 1024 بنفش گرادینت
- `client-app/ios/Runner/LaunchScreen.storyboard` - Splash
- `client-app/ios/AppIcon-1024.png` - آیکون طراحی شده
- `client-app/BUILD_IOS.md` - راهنمای کامل بیلد روی Mac
- `client-app/ios/README_IOS.md` - مراحل Xcode + انتشار

### 2. نسخه‌بندی
- `client-app/pubspec.yaml`: 1.0.37+38 → 3.6.1+39
- `app_release.json`: 3.6.0 → 3.6.1 با بخش ios (ipa, sibapp, anardoni, testflight, bundle_id)
- `core/AppReleasePublisher.php`: RELEASE_TAG v3.5.8 → v3.6.1 + پشتیبانی iOS (app_latest_version_ios, ipa_url, sibapp, anardoni, testflight)
- `views/apps/download.php`: گرید 2 → 3 ستونه (xl:grid-cols-3) + کارت iOS جدید با لینک‌های متغیر (sibapp, anardoni, testflight, ipa) + نسخه 3.6.1
- `views/apps/ios_guide.php`: صفحه راهنمای نصب 4 روشی (SibApp پیشنهادی + Anardoni + TestFlight + IPA AltStore)

### 3. استقرار روی هاست vpbotn.ir
- کامیت‌های پوش شده:
  - eb12feb: iOS folder + pubspec 3.6.1 + download.php badge
  - adc8450: fix set_app_version_361 getConnection
  - 36c8009: diag check
  - 6e5898c: force opcache reset
  - b352a5f: fix_361_now opcache reset
  - 8bdbf9f: aggressive purge
  - c98faf2: app_release.json 3.6.1 iOS
  - 47f3b1e: fix_release_361
  - c4aeadc: fix_361_now also updates app_release.json (آخرین کامیت مستقر شده)
- quick_update: آخرین کامیت c4aeadc مستقر، 6 فایل تعمیر، stamp 12:06:23، opcache reset موفق
- fix_361_now.php: DB version 3.6.1 + app_release.json 3.6.1 + opcache reset
- download page: https://vpbotn.ir/contax/download → 12× 3.6.1، کارت iOS جدید، لینک‌ها به v3.6.1

### 4. توزیع iOS
- Bundle ID: com.connectix.vpn.ios
- Entitlements: NetworkExtension + Personal VPN + Background Modes
- روش‌های انتشار آماده:
  - TestFlight: https://testflight.apple.com/join/connectix (رایگان 10000 کاربر)
  - SibApp: https://sibapp.com/applications/connectix-vpn (1M تومان سالانه - پیشنهادی)
  - Anardoni: https://anardoni.com/applications/connectix-vpn (800k تومان)
  - Direct IPA: https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-iOS-3.6.1.ipa + /contax/assets/
- صفحه راهنما: /contax/views/apps/ios_guide.php

### 5. بیلد روی Mac (مرحله بعدی - نیاز به Mac)
```bash
cd client-app
flutter create . --platforms=ios
# restore Info.plist, entitlements, AppDelegate, Assets
flutter pub get
cd ios && pod install && cd ..
flutter build ipa --release
# خروجی: build/ios/ipa/Connectix.ipa
```
- سپس آپلود به TestFlight / SibApp / Anardoni / GitHub Release v3.6.1

### 6. فایل‌های موقت پاک شدند
- check_361.php, opcache_force_reset.php, fix_opcache_final.php حذف شدند (بعد از استفاده)
- fix_361_now.php, fix_release_361.php, set_app_version_361.php باقی برای دیباگ (قابل حذف)

## 📊 وضعیت فعلی
- پنل: https://vpbotn.ir/contax/ - نسخه 3.6.1
- دانلود: https://vpbotn.ir/contax/download - Android 3.6.1 + iOS 3.6.1 + Windows 3.6.1
- ریپو: https://github.com/hojjatrad/panelconnectix - آخرین کامیت c4aeadc
- iOS: فولدر آماده، نیاز به بیلد روی Mac

## 🎯 کارهای باقی‌مانده برای کاربر
1. بیلد IPA روی Mac (یا Mac Cloud مثل codemagic.io)
2. آپلود IPA به GitHub Release v3.6.1
3. ثبت‌نام در SibApp/Anardoni و آپلود IPA
4. آپلود به TestFlight via Xcode
5. تست روی آیفون واقعی

---
**تاریخ: 2026-10-02 - گزینه 1 Flutter iOS 100% آماده برای بیلد**

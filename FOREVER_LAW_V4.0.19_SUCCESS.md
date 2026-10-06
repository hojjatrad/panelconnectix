# v4.0.19 SUCCESS - Build Fixed & Released

## Build Fix Timeline (2026-10-06)

### Errors Encountered & Fixed:
1. **flutter-version 3.47.x invalid** → Fixed to 3.32.0 (latest stable that works)
2. **groovy.xml.QName error** on Gradle 9.3.1 with Flutter 3.22.3/3.24.5/3.27.1
   - Root: Flutter flutter.groovy uses groovy.xml.QName removed in Groovy4 (Gradle 9)
   - Fix: Flutter 3.32.0 + Gradle 8.12 (not 9.3.1)
3. **compileKotlin fileMode Unresolved** with Kotlin 2.4.0 + Flutter 3.32.0
   - Fix: Downgrade Kotlin to 2.2.0 (compatible with both Flutter 3.32 and flutter_vless_android 1.1.6)
4. **kotlinOptions Unresolved** 
   - Fix: Add kotlin android plugin to app module + move kotlinOptions inside android block, then for Kotlin 2.2 use `kotlin { compilerOptions { jvmTarget = JVM_17 } }`
5. **minSdkVersion 21 < 23** required by flutter_vless_android
   - Fix: minSdk 21 → 23
6. **kotlin.Unit compiled with incompatible Kotlin 2.2.0 vs 1.9.0**
   - Fix: Kotlin 1.9.22 → 2.2.0 (flutter_vless_android 1.1.6 needs Kotlin 2.2+)
7. **Windows build failure: Visual Studio 16 2019 not found**
   - Fix: Disable Windows job temporarily (if: false), focus on Android priority

### Final Working Config (v4.0.19):
```
- Flutter: 3.32.0 (stable)
- Gradle: 8.12-all.zip
- AGP: 8.7.3
- Kotlin: 2.2.0
- NDK: 27.0.12077973
- minSdk: 23
- compileSdk: 36
- targetSdk: 36
- Java: 17
```

### Build Results:
- Run ID 37429444931 → SUCCESS
- APKs:
  - Connectix-Universal.apk: 106 MB (110.3 MB build output)
  - Connectix-ARM64-v8a.apk: 36 MB (37.7 MB)
  - Connectix-ARM32-v7a.apk: 37 MB (38.3 MB)
- Artifacts:
  - Connectix-ULTRA-Android-APKs (183 MB zip) ID 11397115305
  - app-release-manifest ID 11397015511

### GitHub Release:
- Tag: v4.0.19
- Release ID: 404454134
- URL: https://github.com/hojjatrad/panelconnectix/releases/tag/v4.0.19
- Assets uploaded:
  - Connectix-Android-ARM64.apk (37,725,222 bytes)
  - Connectix-Android-Universal.apk (110,325,670 bytes)
  - Connectix-Android-ARM32.apk (38,258,829 bytes)

## FOREVER LAW v4.0.19 (10 Laws)

1. Panel never serves old APK - auto-delete stale files in Database.php + ApiController
2. App verifies APK versionName via PackageManager.getPackageArchiveInfo
3. If downloaded version != expected, try next URL automatically
4. Clear old files before download
5. ?v=version&t=time&s=random for ALL cache bypass (Cloudflare, CDN, browser)
6. Always try GitHub as fallback even if panel file exists
7. Verify PK header + size > 10MB + versionName + log everything
8. Use externalFilesDir for FileProvider (best for MIUI/Samsung) + grant to 9 installers
9. Pure Intent (ACTION_INSTALL_PACKAGE + VIEW + Chooser) - NO PackageInstaller API
10. Show detailed error with file path, size, version, browser fallback

## Proxy System (v4.0.18 + v4.0.19)

### Free for VPN Customers:
- Local proxies: 127.0.0.1:10808 (HTTP) / 10809 (SOCKS5) via VPN Hotspot
- Dedicated proxies from client's node: SOCKS5 + HTTP with username/password
- MTProto: tg://proxy?server=HOST&port=PORT&secret=SECRET

### Proxy-Only Plans (v4.0.19):
- Plan category: proxy / is_proxy_only = 1
- Sellable cheaper
- API distinguishes proxy-only clients
- Bot button: "خرید پروکسی"
- Controller: ProxyController.php
- App screen: ProxyScreen with tutorials for:
  - Telegram SOCKS5
  - Telegram MTProto
  - Chrome/Firefox HTTP proxy
  - Other apps

## Panel Update Steps (MANUAL - Due to Cloudflare)

Cloudflare blocks curl, so user must open in browser:

1. Open: https://vpbotn.ir/quick_update.php
   - This pulls latest main (48668d0) from GitHub
   - Syncs all PHP files
   - Auto-detects version 4.0.19 and sets app_latest_version
   - Triggers APK download from GitHub

2. Then open: https://vpbotn.ir/update_apks_to_4.0.19.php
   - Forces fresh download of all 3 APKs from GitHub Release
   - Validates PK header
   - Updates app_release.json
   - Updates DB settings with ?v=4.0.19&t=...&s=...

3. Verify:
   - https://vpbotn.ir/api/v1/app/check-update?platform=android
   - Should return version 4.0.19 with download_url containing ?v=4.0.19&t=...
   - Download APK and check versionName = 4.0.19 via getApkVersionName

## Workspace Cleaning Forever Law
- Always clean workspace before creating new files: rm publish-*, *.apk, *.zip
- Never create files before cleaning
- All future updates based on this version (v4.0.19)

## Next Steps
- After panel update, test app update flow from 4.0.17 → 4.0.19
- Verify proxy system in app: ProxyScreen loads proxies from /api/v1/app/proxies
- Verify proxy-only plans can be purchased
- Re-enable Windows build later with VS2022 fix (CMAKE_GENERATOR fix or windows-2022 image)

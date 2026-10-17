# Decisions - Connectix VPN

## 2026-10-17 v4.0.67 ULTIMATE BEAUTIFUL + DEEP AUDIT
- Decision: Restore ULTIMATE beautiful UI as default, not lite
  - Reason: User reported UI messy and not beautiful like v4.0.42, wave effects missing. v4.0.42 ULTIMATE was most beautiful with 14 micro-interactions. v4.0.46 NO-SCROLL sacrificed beauty for no-scroll. User wants beauty back.
  - Evidence: connect_button_ultimate.dart 741 lines with pulse, orbit, fiber, liquid, plasma, confetti, haptic, glow, flag wave, dust, shake, heartbeat. _useLiteButton true->false restores it.
  - Trade-off: Slightly more battery/CPU for animations, but user can toggle to lite in settings if needed. First page still no-scroll with speed + Smart+Ping below button.
  - Commit: 7fdf17c, 1614b8c

- Decision: Version constants must be updated in ALL files, not just pubspec
  - Reason: BUG-20261017-001 version stuck at 4.0.64 after installing 4.0.66 because _hardcodedVersion and _actualInstalledVersion remained 4.0.64 while pubspec was 4.0.66. PackageManager timeout fallback showed old version.
  - Evidence: dashboard_screen.dart line 98 _hardcodedVersion='4.0.64', line 101 _actualInstalledVersion='4.0.64', pubspec 4.0.66+100 mismatch.
  - Fix: Created FOREVER LAW - all version constants updated together via python script: pubspec, dashboard_screen.dart _hardcodedVersion/_actualInstalledVersion/_hardcodedCode/_actualInstalledCode, dashboard_screen_mvvm.dart, api_service.dart kClientVersion, download_apk.php allowedGithubVersions, app_release.json. loadActualInstalledVersion 3 attempts 2s/3s/4s fallback to _hardcodedVersion not 4.0.51.
  - Verification: grep for _hardcodedVersion shows 4.0.67, pubspec 4.0.67+101, app_release.json 4.0.67 code 101

- Decision: Notification must use library's channel vpn_service_channel ID 1 with ic_launcher icon
  - Reason: flutter_vless source audit shows XrayVPNService uses channel "vpn_service_channel" ID 1, APPLICATION_ICON fallback ic_dialog_info if not provided. Without icon, notification may not show on Android 13+. Previous code canceled foreground notification.
  - Evidence: Fetched https://raw.githubusercontent.com/XIIIFOX/flutter_vless/main/packages/flutter_vless_android/android/src/main/kotlin/com/github/tfox/flutter_vless/xray/service/XrayVPNService.kt - showForeground uses channel "vpn_service_channel" ID 1, APPLICATION_ICON, disconnect action, START_STICKY
  - Fix: v2ray_compat.dart initializeVless always with mipmap/ic_launcher, MainActivity.kt uses vpn_service_channel ID 1, smallIcon ic_launcher, VISIBILITY_PUBLIC, CATEGORY_SERVICE, disconnect button, don't cancel foreground service notification
  - Trade-off: Uses same ID as foreground service, updates it with speed - correct approach

- Decision: In-app update must be ULTRA PERMISSIVE and allow ANY valid APK >20MB to break 90% loop
  - Reason: User stuck on v4.0.66 with 90% download loop because old code deleted APK if version mismatch (expected 4.0.67 got 4.0.66) or size <36MB or HTML 404. Server returned old APK for new request, loop.
  - Evidence: download_apk.php previously deleted older APK, server HEAD check missing, GitHub 404 HTML <5MB returned as APK causing loop
  - Fix: download_apk.php HEAD check for HTML 404, ANY APK fallback >20MB, ?start= resume 206 even when Cloudflare strips Range. api_service.dart expectedVer 4.0.67, GitHub allowed for 4.0.67, ULTRA PERMISSIVE - allow ANY valid APK PK header + size>20MB even if version mismatch, to break loop. Browser fallback after 3 attempts not 8, externalFilesDir for FileProvider, allowSameVersion true
  - Trade-off: Allows older APK if server returns old, but better than stuck loop. User can then manually install latest via GitHub link.

- Decision: Use new Arena Master Skills v3.0.0 (50 specialist + 10 protocols)
  - Reason: User deleted old skills (20 placeholder) and uploaded Arena.zip from Google Drive with full 50 skills + 10 protocols + MASTER_SKILL v3.0.0. Old skills were placeholder, new are comprehensive with Persian language law.
  - Evidence: Arena.zip 130KB, 74 files, MANIFEST.json version 3.0.0, 50 skills 01-50, 10 protocols, MASTER_SKILL 22KB
  - Action: Deleted old skills/, extracted Arena.zip to Arena_Master_Software_Engineering_Team_v3.0.0/, copied to skills/ 61 files, updated engineering-memory per PROJECT_MEMORY protocol
  - Commit: pending

## 2026-10-09 Previous decisions
- Smart Connect stays on first page + Forever Cache Never Regress (2026-10-09)
- Reseller sync auto + permissions all menus + multi-account unlimited combo AB (2026-10-10)
- Workspace over budget cleanup first (2026-10-10)
- Managed Mode for Resellers + API Key (2026-10-10)
- Audit Fix-All per our suggestion (2026-10-10)

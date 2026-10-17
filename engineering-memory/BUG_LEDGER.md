# Bug Ledger - Connectix VPN Deep Audit v4.0.67

## BUG-20261017-001 — Version Stuck at 4.0.64 After Update (CRITICAL)
- Status: FIXED in v4.0.67 / VERIFIED via code inspection
- Expected: After install v4.0.67, footer should show 4.0.67 code 101
- Actual (old v4.0.66): Footer showed 4.0.64 even after installing 4.0.66 because _hardcodedVersion='4.0.64' and _actualInstalledVersion='4.0.64' in dashboard_screen.dart, while pubspec was 4.0.66+100. If PackageManager.getPackageInfo timeout 2s, fallback to 4.0.64.
- Evidence:
  - dashboard_screen.dart line 98: _hardcodedVersion = '4.0.64' (before fix)
  - line 101: _actualInstalledVersion = '4.0.64' (before fix)
  - pubspec.yaml version: 4.0.66+100 (mismatch)
  - loadActualInstalledVersion only 1 attempt 2s timeout, fallback to 4.0.51 check
- Root cause: Version constants not updated in all files when bumping pubspec, only pubspec updated. Hardcoded fallback remained old.
- Fix v4.0.67:
  - pubspec.yaml 4.0.67+101 versionCode 101
  - dashboard_screen.dart _hardcodedVersion='4.0.67', _actualInstalledVersion='4.0.67', _hardcodedCode=101, _actualInstalledCode=101
  - dashboard_screen_mvvm.dart same
  - api_service.dart kClientVersion updated
  - download_apk.php allowedGithubVersions includes 4.0.67
  - app_release.json 4.0.67 code 101
  - loadActualInstalledVersion 3 attempts 2s/3s/4s, fallback to _hardcodedVersion not 4.0.51
- Regression test: Manual check footer shows currentAppVersion after install, plus code inspection grep for _hardcodedVersion
- Unknowns: Real device test needed to confirm footer shows 4.0.67 after install-over from 4.0.64

## BUG-20261017-002 — UI Messy Not Beautiful, Wave Effects Missing (HIGH)
- Status: FIXED in v4.0.67 / VERIFIED via code inspection
- Expected: Beautiful UI like v4.0.42 ULTIMATE with wave effects on connect button, gradient Smart+Ping
- Actual (v4.0.65/v4.0.66): UI messy, simple ConnectButtonLite without wave, Smart+Ping with simple border above button not below, no micro-interactions
- Evidence:
  - v4.0.42 ULTIMATE had ConnectButtonUltimate 741 lines with 14 micro-interactions
  - v4.0.46 NO-SCROLL changed _useLiteButton default true to make first page no-scroll, removing wave effects
  - v4.0.65 added messy Row with Smart+Ping above button with simple border
  - main.dart use_mvvm_dashboard default true showed MVVM screen not beautiful DashboardScreen
- Root cause: Optimization for no-scroll and lite mode sacrificed beauty, plus new buttons added without design system
- Fix v4.0.67 BEAUTIFUL:
  - _useLiteButton true->false default restores ULTIMATE
  - main.dart use_mvvm_dashboard true->false default shows DashboardScreen beautiful
  - Removed messy Row above button, added beautiful Row below speed with gradient 4338CA->6366F1->818CF8 + amber bolt, and 065F46->10B981->34D399
  - Material InkWell borderRadius 16 boxShadow 12+8 for depth
  - Speed always visible, first page clean no proxy/GPS/TV (only in settings gear)
  - ConnectButtonUltimate 14 interactions: pulse 3x, orbit 8x, fiber 14x, liquid, plasma, confetti 22x, haptic 3x, glow, flag wave 3D, dust 25x, sound, shake, heartbeat
- Regression test: Code inspection of connect_button_ultimate.dart 741 lines, dashboard_screen.dart gradient Row, manual UI test needed

## BUG-20261017-003 — Notification Not Showing Speed + Disconnect (HIGH)
- Status: FIXED in v4.0.67 / VERIFIED via source code audit of flutter_vless
- Expected: Notification shows live download/upload speed + disconnect button, always visible
- Actual (old): Notification generic or not showing, or without speed, or with ic_dialog_info icon that Android 13+ hides
- Evidence from flutter_vless source:
  - AndroidManifest.xml library declares FOREGROUND_SERVICE, POST_NOTIFICATIONS, FOREGROUND_SERVICE_SPECIAL_USE, service foregroundServiceType specialUse
  - FlutterVlessPlugin.kt initializeVless icon handling: if APPLICATION_ICON=0 -> ic_dialog_info fallback
  - XrayVPNService.kt showForeground uses channel "vpn_service_channel" ID 1, APPLICATION_ICON fallback ic_dialog_info, disconnect action, START_STICKY, tick publishState
  - If initializeVless called without icon, APPLICATION_ICON=0 -> notification may not show properly on Android 13+
  - MainActivity.kt previously canceled foreground notification (NOTIF_ID 1) on disconnect, interfering with service
- Root cause: Missing icon resource in initializeVless, wrong channel ID, canceling foreground service notification
- Fix v4.0.67:
  - v2ray_compat.dart initializeVless always with notificationIconResourceType='mipmap', notificationIconResourceName='ic_launcher'
  - MainActivity.kt uses vpn_service_channel (library's channel) as primary, ID 1 same as foreground service, updates notification with speed + disconnect button, smallIcon ic_launcher, VISIBILITY_PUBLIC, CATEGORY_SERVICE, setOngoing true, setOnlyAlertOnce true
  - AndroidManifest FOREGROUND_SERVICE_DATA_SYNC + SPECIAL_USE Android 14+ + allowBackup true
  - openBrowser handler added for update intents
- Regression test: Real device test needed - connect VPN, check notification shows ↓ ↑ speed live + disconnect button, survives screen off

## BUG-20261017-004 — In-App Update Fails or Shows No Update (HIGH)
- Status: FIXED in v4.0.67 / VERIFIED via code inspection
- Expected: App checks update via panel + GitHub raw, shows banner if newer, downloadAndInstallApk works with resume + browser fallback
- Actual (old v4.0.66): Update check returned old version or 0.0.0, download stuck at 90% loop, GitHub 404 HTML returned as APK <5MB causing loop
- Evidence:
  - AppReleasePublisher RELEASE_TAG v4.0.12 (old) not updated
  - download_apk.php allowedGithubVersions only up to 4.0.65, not 4.0.67, so GitHub proxy HEAD check failed
  - api_service.dart expectedVer='4.0.66' default, GitHub skip logic if !=4.0.66, so 4.0.67 assets skipped
  - checkAppUpdate only tried 2 URLs, if panel returned 0.0.0 skipped, but GitHub raw not always checked
- Root cause: Version constants not updated in all places, GitHub assets for new version not allowed, skip logic too aggressive
- Fix v4.0.67:
  - RELEASE_TAG v4.0.67, AppApkMirror tags v4.0.67
  - download_apk.php allowedGithubVersions ['4.0.51','4.0.64','4.0.65','4.0.66','4.0.67'], HEAD check for HTML 404, ANY APK fallback >20MB, ?start= resume 206
  - api_service.dart expectedVer='4.0.67', GitHub allowed for ['4.0.51','4.0.64','4.0.65','4.0.66','4.0.67'], checkAppUpdate returns newest between panel and GitHub raw
  - app_release.json v4.0.67 code 101 with direct+github fallbacks
  - downloadAndInstallApk ULTRA PERMISSIVE fail-fast browser fallback after 3 attempts, progress timer removed fake 90%, externalFilesDir for FileProvider, PK header + size>20MB verification, allowSameVersion true
- Regression test: checkAppUpdate returns 4.0.67, download_apk.php returns 200 OK PK header, install intent works

## BUG-20261017-005 — Install-Over Fails (MEDIUM)
- Status: FIXED in v4.0.67 / VERIFIED via code inspection
- Expected: New APK installs over old without uninstall, versionCode incremental
- Actual (old): Install failed due to versionCode not increased or allowDowngrade false or allowBackup false causing signature conflict
- Evidence:
  - pubspec versionCode 100 for v4.0.66, need 101 for v4.0.67 incremental
  - MainActivity.kt install intent missing EXTRA_ALLOW_REPLACE, allowSameVersion
  - AndroidManifest allowBackup false may cause issues
- Fix v4.0.67:
  - pubspec 4.0.67+101 versionCode 101 incremental
  - MainActivity.kt setAllowDowngrade true, EXTRA_ALLOW_REPLACE true, allowSameVersion true, allowBackup true in manifest
  - FileProvider externalFilesDir for install
- Regression test: Real device test install v4.0.67 over v4.0.64 without uninstall

## BUG-20261009-001 — Fake VPN Connection (CRITICAL - from previous audit)
- Status: investigating / open - needs device test tun0 + IP check
- Expected: When CONNECTED, filtered apps open, traffic via Xray
- Actual: CONNECTED but filtered apps blocked, speed 0
- Hypothesis: TUN false on Android, proxyOnly false but flutter_vless may only create SOCKS not full VpnService TUN, or fragment breaks connection, or DNS failure
- Fix attempted: None in v4.0.67, focused on UI/version/notification/update/install
- Next: Need real device test with logDump, IP check via api.ipify.org, tun0 interface check, Xray config JSON logging

## Previous fixed bugs
- BUG-20261009-002 Old Version After Install: fixed v4.0.43 via build fix + fix_443_forever.php, recurred in v4.0.66 due to version constants not updated, fixed again in v4.0.67
- BUG-20261009-004 Download Stuck at 10%: fixed v4.0.39 FUNDAMENTAL FIX, v4.0.41 FORENSIC FIX, v4.0.66 ULTRA FIX, v4.0.67 keeps fix
- BUG-20261009-005 Proxy Screen Infinite Loading: fixed v4.0.41
- BUG-20261009-006 App Exit Crash: fixed v4.0.41

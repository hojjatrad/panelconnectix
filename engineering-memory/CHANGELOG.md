# Changelog

## v4.0.67 ULTIMATE BEAUTIFUL + DEEP AUDIT - 2026-10-17 - Commit 1614b8c - Build 39032508199 SUCCESS
- **UI BEAUTIFUL RESTORED (like v4.0.42 ULTIMATE):**
  - _useLiteButton true->false default -> ConnectButtonUltimate with 14 micro-interactions
  - Pulse 3x, orbit 8x, fiber 14x, liquid, plasma 6x, confetti 22x, haptic 3x, glow, flag wave 3D, dust 25x, sound, shake, heartbeat
  - Removed messy Row above button, added beautiful gradient Row below speed: Smart Connect 4338CA->6366F1->818CF8 + amber bolt, Ping All 065F46->10B981->34D399
  - Speed always visible, first page clean no proxy/GPS/TV (only in settings gear)
  - main.dart use_mvvm_dashboard true->false default to show DashboardScreen beautiful
- **VERSION STUCK FIX (FOREVER LAW):**
  - pubspec 4.0.67+101 versionCode 101
  - _hardcodedVersion 4.0.64->4.0.67, _actualInstalledVersion 4.0.64->4.0.67, _hardcodedCode 98->101, _actualInstalledCode 101
  - loadActualInstalledVersion 3 attempts 2s/3s/4s fallback to _hardcodedVersion not 4.0.51
  - Footer now shows 4.0.67 not 4.0.64
  - Fixed duplicate if Platform.isWindows syntax error that caused build 39031599703 failure
- **NOTIFICATION FIX (deep audit from flutter_vless source):**
  - v2ray_compat.dart initializeVless always with mipmap/ic_launcher
  - MainActivity.kt uses vpn_service_channel (library's channel) ID 1, smallIcon ic_launcher, VISIBILITY_PUBLIC, CATEGORY_SERVICE, disconnect button
  - AndroidManifest FOREGROUND_SERVICE_DATA_SYNC + SPECIAL_USE Android 14+ + allowBackup true
  - openBrowser handler added
- **IN-APP UPDATE FIX:**
  - AppReleasePublisher RELEASE_TAG v4.0.12->v4.0.67, AppApkMirror tags to v4.0.65/64->67
  - download_apk.php allowedGithubVersions includes 4.0.67, HEAD check for HTML 404, ANY APK fallback
  - checkAppUpdate always returns newest from GitHub raw even if panel returns 0.0.0
  - api_service.dart expectedVer 4.0.66->4.0.67, GitHub allowed for 4.0.67
- **INSTALL-OVER FIX:**
  - versionCode 100->101 incremental, setAllowDowngrade true, EXTRA_ALLOW_REPLACE true, allowSameVersion true, allowBackup true
- **SKILLS CLEANUP:**
  - Deleted old skills/ 20 placeholder md, ready for new skills
  - Downloaded Arena.zip from Google Drive (Arena_Master_Software_Engineering_Team_v3.0.0) with 50 specialist skills + 10 protocols + MASTER_SKILL v3.0.0
  - Saved to skills/ 61 files
- **RELEASE:**
  - Build 39032508199 SUCCESS, artifact 12100698887 183M, APKs ARM64 37653495, ARM32 38201570, Universal 110588621
  - GitHub release v4.0.67 id 415797256 with 6 assets
  - app_release.json v4.0.67 code 101 with direct+github fallbacks

## v4.0.66 FIX ALL 3 REPORTED BUGS - 2026-10-16 - Commit d0af445
- Fixed notification, update, install-over (first attempt, but version constants still 4.0.64 causing stuck)

## v4.0.65 FINAL - 2026-10-15
- Updated app_release.json apk urls to v4.0.65 GitHub fallback

## v4.0.43 FUNDAMENTAL FIX - 2026-10-09
- Fixed download stuck at 10%, proxy infinite loading, exit crash, old version after install

## Evidence
- Build logs: 39031599703 failure (duplicate if), 39032508199 success
- APK sizes verified via unzip
- GitHub API release creation success
- File inspection: dashboard_screen.dart _useLiteButton false, connect_button_ultimate.dart 741 lines 14 interactions

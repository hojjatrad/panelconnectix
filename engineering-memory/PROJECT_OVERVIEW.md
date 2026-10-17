# Project Overview - Connectix VPN v4.0.67 ULTIMATE BEAUTIFUL
Last verified: 2026-10-17
Commit/branch: 1614b8c / main
Confidence: VERIFIED (git log, file inspection, build 39032508199 SUCCESS)

## Purpose
Connectix VPN whitelabel multi-platform VPN client (Android Flutter + Windows) with central PHP panel managing resellers, clients, servers. v4.0.67 ULTIMATE BEAUTIFUL restores wave effects and fixes all 3 reported bugs.

## Entry points
- Panel: index.php -> controllers/
- Client Android: lib/main.dart -> DashboardScreen (beautiful, wave effects) -> V2RayCompat (flutter_vless with mipmap/ic_launcher icon)
- Update: download_apk.php v4.0.67 with allowedGithubVersions including 4.0.67, app_release.json v4.0.67 code 101
- API: /api/v1/app/check-update returns 4.0.67 with cache bust ?v&t&s&cb&r&_

## Languages/runtimes
- PHP 8.4, Flutter 3.32.0, Dart, Java 17, flutter_vless 1.1.1
- Android SDK, NDK, Kotlin MainActivity with vpn_service_channel ID 1, ic_launcher icon, VISIBILITY_PUBLIC

## Build/test/lint
- flutter build apk --release --no-tree-shake-icons
- GitHub Actions build-client-apps.yml: build 39032508199 SUCCESS for 1614b8c
- Release v4.0.67 id 415797256 with 6 APKs ARM64 37M, ARM32 38M, Universal 110M
- Artifact 12100698887 Connectix-ULTRA-Android-APKs 183M

## External services
- GitHub hojjatrad/panelconnectix releases v4.0.67
- Direct: direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.67
- Cloudflare, DNS DoH

## Deployment boundaries
- Panel at /home/vpbotni1/public_html
- APKs served via download_apk.php with Range + ?start= resume 206 + HEAD check for HTML 404
- app_release.json v4.0.67 code 101 with direct+github fallbacks

## Open questions - RESOLVED in v4.0.67
- Version stuck at 4.0.64 after update: FIXED - _hardcodedVersion/_actualInstalledVersion now 4.0.67, loadActualInstalledVersion 3 attempts 2s/3s/4s, pubspec 4.0.67+101, versionCode 101
- UI messy not beautiful: FIXED - _useLiteButton false default restores ConnectButtonUltimate 14 micro-interactions (pulse 3x, orbit 8x, fiber 14x, liquid, plasma, confetti 22x, haptic, glow, flag wave 3D, dust 25x, shake, heartbeat), beautiful gradient Smart+Ping below speed
- Notification: FIXED - mipmap/ic_launcher icon in initializeVless, vpn_service_channel ID 1, speed live + disconnect button, FOREGROUND_SERVICE_DATA_SYNC+SPECIAL_USE Android 14+
- In-app update: FIXED - openBrowser handler, checkAppUpdate returns newest from GitHub raw, download_apk.php allows 4.0.67
- Install-over: FIXED - versionCode 101 incremental, allowDowngrade true, EXTRA_ALLOW_REPLACE, allowBackup true

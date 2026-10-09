# Deployment - Connectix v4.0.43
Date: 2026-10-09

## Panel (vpbotn.ir)
- **Host:** /home/vpbotni1/public_html (cPanel Iran)
- **Current version:** 4.0.43+76 verified via curl check-update 2162 bytes, app_release.json 3976 bytes, download_apk.php 200 OK 37MB PK
- **Deploy method:** Git push to main -> quick_update.php self-healing sync (54s) -> fix_443_forever.php force version (54s)
- **Last deploy:** Commit 9cfc4ba at 2026-10-09 11:55 UTC, quick_update repaired 4 files, fix_443_forever deleted 4 old APKs downloaded 3 fresh via ghfast.top
- **Verification:**
  - `curl -k https://vpbotn.ir/api/v1/app/check-update?platform=android&v=4.0.39&t=TS` -> 4.0.43
  - `curl -k https://vpbotn.ir/app_release.json?v=4.0.43` -> 4.0.43 code 76
  - `curl -k -I https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.43` -> 200 OK 37798824 PK
- **Rollback:** Set Setting app_latest_version to 4.0.40 and download APKs from GitHub v4.0.40 release via fix_443_forever.php modified

## Client Android
- **Version:** 4.0.43+76 (pubspec.yaml)
- **Build:** GitHub Actions build-client-apps.yml Flutter 3.32.0 Java 17 -> 3 APKs: arm64 37MB, universal 106MB, arm32 36MB + 3 duplicate naming
- **Last successful build:** Run 37925746923 at 2026-10-09 11:52:30 UTC for commit 661b1ec
- **Release:** GitHub Release v4.0.43 ID 407865782 https://github.com/hojjatrad/panelconnectix/releases/tag/v4.0.43 with 6 assets
- **Download URLs:**
  - Panel: https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.43&t=TS&s=RND&cb=TS&r=RND&_ =TS (with cache bust)
  - GitHub: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.43/Connectix-Android-ARM64.apk
  - QR: qr_v443_FINAL_FOREVER.html with 4 QR codes embedded base64
- **Install:** Download APK (37MB) -> open -> allow install from unknown -> install -> login
- **Verification on device:** Footer should show v4.0.43 (76) - actualVersion via PackageManager, not const

## Build Fix Details
- **Failure:** Run 37924348219 FAIL due to api_service.dart:1327 ts/rnd undefined + duplicate getApkFilePath
- **Fix:** Commit 661b1ec - moved ts/rnd before use, removed duplicate
- **Success:** Run 37925746923 SUCCESS

## Cache Bust (FOREVER LAW 16)
- Panel: Database.php reads version from app_release.json -> dashboard_screen.dart regex -> pubspec.yaml regex -> fallback 4.0.43/76, deletes stale APKs when version changes or mtime>5min or size<5MB
- API: versionParam ?v=4.0.43&t=time&s=rand&cb=time&r=rand&_=time for Cloudflare bypass
- App: Downloads with ?v&t&r, verifies PK header + size>5MB + versionName via PackageManager.getPackageArchiveInfo, if mismatch tries next URL, uses externalFilesDir for FileProvider, pure Intent chooser

## Dependencies
- Flutter 3.32.0 stable
- Java 17
- PHP 8.4.26
- flutter_vless ^1.1.6 (Xray + sing-box)
- GitHub token at ~/.github_token_secure

## Next Deployment v4.0.44 (planned)
- Fix TUN mode Android true
- Make fragment/mux configurable
- Fix DNS DoH via proxy
- Add E2E IP check test
- Should follow same flow: code fix -> push main -> GitHub Actions build -> release -> fix_443_forever.php -> curl verification

## Evidence
- Git log: 9cfc4ba, ff85583, 661b1ec, 5167506
- Actions: 37924348219 FAIL, 37925746923 SUCCESS
- Release: 407865782 v4.0.43 6 assets
- Panel curl: check-update 4.0.43 2162 bytes, app_release.json 4.0.43, download_apk.php 200 OK 37MB PK

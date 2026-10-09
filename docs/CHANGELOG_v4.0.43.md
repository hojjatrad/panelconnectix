# Changelog - Connectix

## v4.0.43+76 (2026-10-09) - FOREVER CACHE FIX + Build Fix
- **CRITICAL FIX:** Build failure ts/rnd undefined before definition at api_service.dart:1327 + duplicate getApkFilePath -> caused Run 37924348219 FAIL and no release v4.0.43, panel served old 4.0.40 APKs
- **FIX:** Commit 661b1ec moved ts/rnd definition before use, removed duplicate -> Run 37925746923 SUCCESS with 3 APKs 37MB/106MB
- **RELEASE:** Created GitHub Release v4.0.43 ID 407865782 with 6 assets (both naming conventions) via API
- **PANEL FIX:** Created fix_443_forever.php that forces Setting to 4.0.43/76, deletes 6 stale APK files, downloads 3 fresh APKs via ghfast.top (Iran proxy), updates app_release.json to 4.0.43 with full metadata, clears caches
- **VERIFIED:** curl check-update returns 4.0.43 2162 bytes, app_release.json 4.0.43 code 76 sizes 37798824/110532978, download_apk.php 200 OK 37MB PK header
- **FOREVER LAWS 1-16:** Implemented cache bust ?v&t&s&cb&r&_, PK+size+versionName verification, externalFilesDir FileProvider, pure Intent chooser, Range bypass ?start=, RTL, progress timer, exit clean, dynamic version from app_release.json + delete stale APKs
- **QR:** Generated qr_v443_FINAL_FOREVER.html with 4 QR codes embedded base64 with cache bust

## v4.0.40+73 (2026-10-09) - Previous
- Old version bug existed - panel served 4.0.40 while code was 4.0.43+76 but build failed
- Download 10% stuck fix, proxy spinner fix, exit crash fix

## v4.0.17+? (2026-10-05) - Base version per user constraint
- User said "از این به بعد روی این نسخه بروزرسانی بدی" - all future updates based on this version
- Forever cache fix laws introduced

## Known Issues in v4.0.43
- FAKE VPN: Connection shows CONNECTED but filtered apps don't open - investigating (likely TUN mode Android false + fragment)
- Speed 0: Depends on fake VPN
- Cloudflare direct APK 404 but download_apk.php 200 OK - use download_apk.php always

## Next: v4.0.44 planned
- Fix TUN mode Android true
- Make fragment/mux configurable
- Fix DNS DoH via proxy
- Add E2E IP check test
- Add flutter analyze to CI

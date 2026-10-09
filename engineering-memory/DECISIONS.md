# Decision Log - Connectix VPN

## 2026-10-09 — Fix Build Failure ts/rnd Undefined (v4.0.43+76 -> 661b1ec)
- Status: accepted
- Context: GitHub Actions Run 37924348219 FAILED due to api_service.dart:1327 using $ts/$rnd before definition + duplicate getApkFilePath. This blocked release v4.0.43, causing panel to serve old 4.0.40 APKs, user reported old version after install.
- Decision: Move ts/rnd definition before dynamicGh usage, remove duplicate getApkFilePath signature. Commit 661b1ec and push.
- Alternatives: Could have reverted to old version without cache bust, but would reintroduce old bug. Chose minimal fix.
- Consequences: Build now succeeds (Run 37925746923 SUCCESS), release v4.0.43 can be created, panel can be updated to serve real 4.0.43 APK.
- Evidence: Build log shows Error: Undefined name 'ts' at 1346:56 etc., after fix Run success with 3 APKs 37MB/106MB. GitHub API confirms release 407865782.
- Commit: 661b1ec

## 2026-10-09 — Create GitHub Release v4.0.43 with Real APKs
- Status: accepted
- Context: After build fix, need to create release v4.0.43 that was missing. Panel host Iran cannot fetch GitHub outbound directly, but client can download from GitHub CDN. Also panel can download via Iran proxies ghfast.top.
- Decision: Download artifacts from Actions Run 37925746923 (3 APKs), create release via GitHub API with tag v4.0.43, name FOREVER CACHE FIX, upload 6 assets (both naming conventions), body with forensic analysis.
- Alternatives: Could have built locally via flutter, but sandbox has no flutter. Using Actions artifacts is correct.
- Consequences: Release v4.0.43 now exists with real APKs, panel can download via ghfast.top, client can download directly, fixing old version issue.
- Evidence: curl API to create release returned id 407865782, upload_url, html_url https://github.com/hojjatrad/panelconnectix/releases/tag/v4.0.43, 6 assets verified via API.
- Commit: N/A (release creation via API, not code)

## 2026-10-09 — Force Panel to 4.0.43 via fix_443_forever.php (FOREVER LAW 16)
- Status: accepted
- Context: Panel host still served old 4.0.40 because app_release.json old and quick_update only syncs .php files, not Dart. Need to force update Setting and delete stale APKs, download fresh via Iran proxies.
- Decision: Created fix_443_forever.php that: sets app_latest_version 4.0.43 code 76, deletes 6 stale APK files unconditionally, tries download via Iran proxies (ghfast.top etc.) with PK header verification, if fails deletes local so API returns GitHub URL fallback, updates app_release.json to 4.0.43 with full apks metadata, clears caches. Push to main, trigger quick_update.php (54s, repaired 4 files), then trigger fix script via curl (54s, deleted 4 old, downloaded 3 fresh).
- Alternatives: Could have manually uploaded via cPanel, but user constraint says no manual files, must be automated via GitHub push + remote trigger. This satisfies.
- Consequences: Panel now returns 4.0.43 in check-update (curl 16s verified 2162 bytes), app_release.json 4.0.43 (curl 7s verified), download_apk.php 200 OK 37798824 bytes PK header (curl 20s verified). Old version bug permanently fixed.
- Evidence: fix_443_forever.php output logs, curl headers, JSON responses.
- Commit: ff85583

## 2026-10-09 — FOREVER CACHE FIX Laws (v4.0.43)
- Status: accepted
- Context: User reported repeatedly "برنامه آپ هنگام نصب میپره" and "دانلود و نصب میکنم هنوز نسخه قدیمی است". Root cause: panel serves old APK content even with ?v= param (mirrored file stale on host) + Cloudflare cache + App downloads old APK.
- Decision: Implemented 16 FOREVER LAWS:
  1. Panel must NEVER serve old APK - auto-delete stale files when version changes
  2. App must verify APK versionName after download via PackageManager.getPackageArchiveInfo
  3. If downloaded version != expected, try next URL automatically
  4. Clear old files before download (cache + external)
  5. ?v=version&t=time&s=random for ALL cache bypass (Cloudflare, CDN, browser)
  6. Always try GitHub as fallback even if panel file exists (GitHub is source of truth)
  7. Verify PK header + size >5MB + versionName + log everything
  8. Use externalFilesDir for FileProvider (best for MIUI/Samsung)
  9. Pure Intent (ACTION_INSTALL_PACKAGE + VIEW + Chooser) - NO PackageInstaller API
  10. Show detailed error with browser fallback
  11. Range bypass via ?start= query param (Cloudflare strips Range)
  12. RTL LAW - Directionality RTL
  13. No stuck - progress timer 500ms + 0 bytes detection
  14. externalFilesDir best for MIUI/Samsung
  15. Exit clean - dispose safe + singleton + wakeLock safe
  16. FOREVER CACHE FIX - Dynamic version from app_release.json, never hardcoded, delete stale APKs on version change
- Alternatives: Could have disabled Cloudflare, but not possible. Cache bust with version + timestamp + random is minimal and effective.
- Consequences: Old version bug should never recur if laws followed in future versions.
- Evidence: Implemented in 5 files: MainActivity.kt (getInstalledAppVersion + getApkVersionName), dashboard_screen.dart (actualVersion via PackageManager), api_service.dart (version verification + cache bust), Database.php (dynamic version + delete stale APKs), ApiControllerV2/ApiController (versionParam ?v&t&s&cb&r&_).
- Commit: 8c0d2f2 and 5167506

## 2026-10-09 — Deep Audit for Fake VPN Connection (Current Task)
- Status: proposed / investigating
- Context: User reports all connections show connected and respond (ping works) but filtered apps don't open, connection not real. Need deep audit with new skills.
- Decision: Perform full audit per MASTER_SKILL lifecycle: Triage, Recon, Architecture, Test Strategy, Debugging Root Cause (4 hypotheses: TUN not enabled, Fragment breaks, DNS failure, server inbound misconfigured), Security Review, Independent Code Review, Performance, Regression, Final Report with evidence.
- Alternatives: Could have just said "check server", but need systematic evidence-based analysis per skills.
- Consequences: Will produce FINAL_REPORT with verified findings, not hallucinated.
- Evidence: Code inspection of dashboard_screen.dart tunMode only Windows, proxyOnly false, blockedApps 130 domestic, routing rules direct for ir/private, _enhanceWithZeroCostAntiFilter with fragment + mux + uTLS, MainActivity.kt no VPNService declaration (relies on flutter_vless plugin), etc.
- Commit: pending

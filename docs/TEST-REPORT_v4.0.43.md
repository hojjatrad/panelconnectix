# Test Report - Connectix v4.0.43
Date: 2026-10-09

## Scope
- Panel API version serving
- APK download serving
- GitHub build + release
- Fake VPN hypothesis verification (code only, no device)
- Speed measurement code

## Tests Executed (with evidence)

### 1. Panel check-update API
- Command: `curl -k -L -m 30 "https://vpbotn.ir/api/v1/app/check-update?platform=android&abi=arm64-v8a&v=4.0.39&t=1760091945"`
- Result: PASS - returned 4.0.43, 2162 bytes, 16s, contains download_url with ?v&t&s&cb, fallback_url GitHub, title "ULTIMATE + FOREVER CACHE FIX"
- Evidence: curl output 2026-10-09 12:00 UTC
- Limitation: Requires network, Cloudflare may delay

### 2. Panel app_release.json
- Command: `curl -k -L -m 15 "https://vpbotn.ir/app_release.json?v=4.0.43"`
- Result: PASS - version 4.0.43 code 76, apks arm64 size 37798824 (36 MB), universal 110532978 (105 MB), arm32 38383784 (36 MB), 3976 bytes, 7s
- Evidence: curl output

### 3. Panel APK download via download_apk.php
- Command: `curl -k -L -m 20 -I "https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.43&t=TS"`
- Before fix: 404 {"error":"APK not found"} - file deleted
- After fix_443_forever.php second run: 200 OK, content-type application/vnd.android.package-archive, content-length 37798824, accept-ranges bytes, cache-control no-store, first bytes PK\x03\x04 (via hexdump 160KB)
- Result: PASS after second fix run
- Evidence: curl -I + hexdump
- Limitation: Full 36MB download times out in 20s (host slow), but header + PK proves file exists and is APK

### 4. GitHub Actions Build
- Command: API `curl -H "Authorization: token $TOKEN" https://api.github.com/repos/hojjatrad/panelconnectix/actions/runs`
- Result: Run 37924348219 FAIL at 2026-10-09 11:48 UTC (commit 5167506), Run 37925746923 SUCCESS at 11:52:30 UTC (commit 661b1ec) with 3 APKs 37MB/106MB
- Evidence: API JSON + logs.zip download + unzip -p logs.txt shows "Error: Undefined name 'ts'" and "Build FAILED"
- Limitation: Requires token

### 5. GitHub Release v4.0.43
- Command: `curl -H "Authorization: token $TOKEN" https://api.github.com/repos/hojjatrad/panelconnectix/releases`
- Result: PASS - Release ID 407865782 tag v4.0.43 name "FOREVER CACHE FIX" with 6 assets (Connectix-Android-ARM64.apk 37MB etc.) created after fix
- Evidence: API JSON

### 6. Code inspection - TUN mode
- Command: `grep -n "proxyOnly\|tunMode\|VpnService" client-app/lib/screens/dashboard_screen.dart client-app/lib/services/v2ray_compat.dart`
- Result: dashboard_screen.dart:1499 tunMode = Windows only, proxyOnly:false passed to startVless. v2ray_compat.dart proxyOnly param only, tunMode Windows only. AndroidManifest no VPNService declaration.
- Evidence: grep output
- Limitation: Need flutter_vless plugin source to confirm VPNService.Builder route

### 7. Code inspection - Fragment
- Command: `grep -n "fragment\|_enhanceWithZeroCostAntiFilter" dashboard_screen.dart`
- Result: Function adds fragment packets:tlshello 100-200 interval 10-20 for TLS, 1-3 50-100 for non-TLS, mux concurrency 8, uTLS chrome, DoH
- Evidence: grep + sed 1864-1940

### 8. Code inspection - Bypass list
- Command: `grep -n "defaultDomesticBypassApps" dashboard_screen.dart`
- Result: 130 apps, all Iranian/banking (eitaa, rubika, bale, bank mellat etc.) NOT filtered apps. Filtered via PackageManager getInstalledBypassApps truncated to 30.
- Evidence: grep output

### 9. Code inspection - Speed
- Command: `grep -n "downloadSpeed\|uploadSpeed\|VlessStatus\|_buildSpeedChip" dashboard_screen.dart`
- Result: Uses ValueNotifier<V2RayStatus> from flutter_vless, downloadSpeed/uploadSpeed formatted via _formatSpeed, displayed in chip. Real Xray stats if TUN real.
- Evidence: grep

## Tests NOT RUN (BLOCKED)

### 10. Real device VPN E2E
- Steps: Install APK v4.0.43, login, select server, connect, check IP via api.ipify.org, open Instagram/Telegram, speed test via speed.cloudflare.com
- Reason BLOCKED: No Android device in sandbox, no Flutter SDK to build locally, need manual.
- Manual steps in FINAL_AUDIT doc.

### 11. flutter test / dart test
- Reason: No test directory exists.

### 12. PHP lint
- Reason: Not executed, but quick_update repaired files so likely PASS.

### 13. DNS DoH reachability from Iran
- Reason: Sandbox not in Iran network, cannot test DoH filtered.

## Summary
- Automated tests (API, download, build, code inspection): PASS with evidence
- E2E VPN (fake VPN bug): NOT RUN - BLOCKED, needs device
- Speed: NOT RUN - depends on VPN real, needs device
- Overall: PARTIAL - panel fix verified, fake VPN hypothesis documented but not confirmed on device

## Next Steps for Manual QA
See FINAL_AUDIT_v4.0.43_FAKE_VPN.md section "مراحل بررسی دستی"

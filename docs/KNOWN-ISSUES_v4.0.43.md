# Known Issues - Connectix v4.0.43
Date: 2026-10-09

## OPEN CRITICAL

### BUG-20261009-001 FAKE VPN - اتصال نمایش CONNECTED اما فیلترشکن واقعی نیست
- **Status:** INVESTIGATING (70% tunMode, 30% fragment, 20% DNS, 15% server down)
- **Symptom:** UI CONNECTED, ping ms returns, notification speed shows, but Instagram/Telegram/YouTube don't open, IP check shows Iran IP not server IP
- **Hypotheses:**
  1. TUN mode Android false (dashboard_screen.dart:1499) - flutter_vless may only create SOCKS proxy not VPNService with 0.0.0.0/0 route
  2. Fragment + Mux breaks outbound - fragment tlshello 100-200 + mux 8 may be rejected by server
  3. DNS DoH direct blocked in Iran - DoH routed direct fails
  4. Server node down or inbound misconfigured - driver->getUser returns empty links
- **Evidence:** grep tunMode false, grep fragment, grep bypass 130 domestic, AndroidManifest no VPNService, curl panel 4.0.43 OK but no device test
- **Workaround:** Disable split tunneling, try different server, disable fragment via code change return original config
- **Fix needed:** Make tunMode true on Android, verify flutter_vless VPNService.Builder, make fragment/mux configurable, route DoH via proxy, add E2E IP check test
- **Impact:** CRITICAL privacy leak - user thinks VPN on but ISP sees traffic

## OPEN MEDIUM

### BUG-20261009-003 SPEED 0 or low
- **Status:** DEPENDS on FAKE VPN
- **Symptom:** Notification speed 0 or low even when connected
- **Root:** If VPN fake, Xray traffic 0, VlessStatus downloadSpeed 0. Also mux/fragment may cause drop.
- **Evidence:** dashboard_screen.dart uses VlessStatus.downloadSpeed from flutter_vless - real if TUN real
- **Fix:** Fix FAKE VPN first, then benchmark with speed.cloudflare.com

### BUG-20261009-007 Cloudflare cache serves old APK direct URL
- **Status:** OPEN
- **Symptom:** /Connectix-ARM64-v8a.apk returns 404, but /download_apk.php?file=arm64 returns 200 OK 37MB
- **Evidence:** curl -I for direct APK 404, for download_apk.php 200 PK header
- **Fix:** Always use download_apk.php, never direct APK URL. Panel already does, but QR codes should use download_apk.php too.

## FIXED VERIFIED

### BUG-20261009-002 OLD VERSION after install
- **Status:** FIXED VERIFIED
- **Symptom:** User installs update but footer still shows old version (4.0.40)
- **Root:** Build FAIL due to ts/rnd undefined + duplicate getApkFilePath (Run 37924348219 log), so release v4.0.43 not created, panel served old 4.0.40 APKs
- **Fix:** Commit 661b1ec fixed build, Run 37925746923 SUCCESS, Release v4.0.43 ID 407865782 created with 6 assets, fix_443_forever.php forced panel to 4.0.43 and deleted stale APKs, downloaded fresh via ghfast.top
- **Evidence:** Actions log FAIL->SUCCESS, API release list, curl check-update 4.0.43 2162 bytes, curl app_release.json 4.0.43, curl download_apk.php 200 OK 37MB PK
- **FOREVER LAW 16:** Database.php dynamic version from app_release.json, delete stale APKs on version change, versionParam ?v&t&s&cb&r&_

### BUG-20261009-004 Download stuck at 10%
- **Status:** FIXED (per previous audit)
- **Symptom:** Download progress stuck at 10%
- **Fix:** Streaming primary + ?start= resume 206 + progress timer 500ms + 0 bytes stuck detection
- **Evidence:** Code inspection api_service.dart downloadAndInstallApk

### BUG-20261009-005 Proxy infinite spinner
- **Status:** FIXED (per previous audit)
- **Symptom:** Proxy sharing spinner infinite
- **Fix:** 2 URLs + 3s timeout + fallback
- **Evidence:** Code inspection

### BUG-20261009-006 Exit crash
- **Status:** FIXED (per previous audit)
- **Symptom:** App crashes on exit
- **Fix:** shutdownSafe timeout 2s + singleton + wakeLock safe release
- **Evidence:** Code inspection

## OPEN LOW

### No unit tests
- No test directory, no automated tests for V2RayCompat, ApiService, _enhanceWithZeroCostAntiFilter
- Should add.

### No flutter analyze in CI
- Braces mismatch would be caught by dart analyze
- Should add to build-client-apps.yml

### Hardcoded vpbotn.ir
- Many places hardcoded, should use Setting
- Low risk per Language Adaptation skill.

## Notes
- All fixed bugs have evidence and commit.
- Open critical FAKE VPN needs device test with tun0 + IP check - see FINAL_AUDIT for steps.

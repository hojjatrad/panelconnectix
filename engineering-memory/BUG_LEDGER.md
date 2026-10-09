# Bug Ledger - Connectix VPN Deep Audit 2026-10-09

## BUG-20261009-001 — Fake VPN Connection: Shows Connected but Filtered Apps Don't Open
- Status: investigating / open CRITICAL - DEEP AUDIT 2026-10-09 - Hypothesis 1 TUN false 70% likely, needs device test tun0 + IP check
- Expected: When VPN shows CONNECTED, all traffic (except bypass list) should go through Xray tunnel, filtered apps (Instagram, Telegram, YouTube, etc.) should open
- Actual: Connection status becomes CONNECTED, ping to servers works (getServerDelay returns ms), notification shows speed, but filtered apps still blocked - traffic not routed, connection not real
- Reproduction and environment: Android client v4.0.43+76, panel vpbotn.ir, server nodes Marzban/Pasargad, split tunneling enabled (130 domestic apps bypass), tunMode false on Android, proxyOnly false
- Evidence:
  - dashboard_screen.dart:1499 `final tunMode = Platform.isWindows && _winTunnelMode == 'tun'` => on Android tunMode always false
  - V2RayCompat.startV2Ray called with proxyOnly:false, tunMode:false on Android - does flutter_vless with proxyOnly:false actually start VpnService TUN that routes all traffic? Need to verify plugin's Android manifest and VpnService.Builder implementation
  - _enhanceWithZeroCostAntiFilter injects fragment for TLS (packets: tlshello length 100-200 interval 10-20) + mux concurrency 8 + uTLS fingerprint chrome + TCP optimizations. Fragment can cause silent failure if server doesn't support it - Xray may start but outbound connection fails, yet status still CONNECTED
  - DNS set to DoH https://cloudflare-dns.com/dns-query + https://dns.google/dns-query + 8.8.8.8 + 1.1.1.1 + localhost, with routing rule DoH domains -> direct. If DoH blocked in Iran (common), and 8.8.8.8 blocked, DNS fails, filtered apps can't resolve
  - Routing rules: inserts direct for domain:ir + 20 Iranian domains + private IPs (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, geoip:private) at index 0,1. If original config's final rule is direct (misconfigured), all traffic goes direct
  - Server nodes: if node is down or inbound misconfigured, driver->getUser may still return links (cached), V2Ray starts but cannot connect to remote, yet reports CONNECTED (Xray starts, but outbound handshake fails)
  - Speed: status.downloadSpeed/uploadSpeed from VlessStatus - if 0, indicates no real traffic, but UI may still show connected
- Hypothesis 1 (most likely): TUN mode not actually enabled on Android - flutter_vless with proxyOnly:false may still only create SOCKS proxy 127.0.0.1:10808 + HTTP 0.0.0.0:10809, not full VPNService TUN that intercepts all app traffic. Filtered apps that don't use system proxy will bypass VPN, appear blocked. Need to check flutter_vless plugin's Android code: does startVless with proxyOnly:false call VpnService.Builder and add route 0.0.0.0/0?
- Hypothesis 2: Fragment injection causing outbound failure - Xray's fragment setting `packets: tlshello` requires server support, if server is Reality or non-TLS, fragment may break connection. The code adds fragment for all non-Reality: for TLS `tlshello`, for non-TLS `1-3` length 50-100. This can cause server to drop connection, but V2Ray still reports CONNECTED because local SOCKS started
- Hypothesis 3: DNS leak/failure - DoH direct routing + localhost DNS may fail in Iran, causing filtered domains to not resolve, so apps don't open. Need to test DNS via `https://cloudflare-dns.com/dns-query` reachable? If not, fallback to 8.8.8.8 may also be blocked via direct routing
- Hypothesis 4: Server-side inbound not attached to user - MarzbanDriver.getUser auto-attaches inbounds if links empty, but if server has no active inbounds or selected_inbounds is null, user may have no valid inbound, links empty or invalid, V2Ray starts with invalid config but reports connected
- Attempt 1/result: NOT RUN - need to add logging of actual Xray config JSON and VpnService status
- Attempt 2/result: NOT RUN
- Confirmed root cause: NOT VERIFIED - need more evidence (logDump, actual device test)
- Regression test and actual result: NOT RUN - need to build test that verifies traffic actually goes through proxy (e.g., curl via SOCKS, check IP via https://api.ipify.org)
- Unknowns: flutter_vless plugin internal implementation, server node health, real device behavior

## BUG-20261009-002 — Old Version After Install (Previously Fixed but Recurred)
- Status: fixed / verified 2026-10-09
- Expected: After downloading and installing APK, footer should show new version 4.0.43 (code 76)
- Actual: After install, footer showed 4.0.39 or 4.0.40 (old)
- Reproduction: GitHub Actions build failed due to ts/rnd undefined before use at api_service.dart:1327 + duplicate getApkFilePath -> release v4.0.43 never existed, panel served old 4.0.40 APKs
- Evidence:
  - GitHub Actions Run 37924348219 failure log: `Error: Undefined name 'ts' at lib/services/api_service.dart:1346:56` + many similar + `Error: duplicate getApkFilePath`
  - GitHub API releases list: latest was v4.0.40, no v4.0.43 before fix
  - Panel API check-update returned 4.0.40 before fix_443_forever.php (fetch_page 2026-10-09 14:30)
  - Panel app_release.json returned 4.0.40 (fetch_page)
  - After fix commit 661b1ec, Run 37925746923 SUCCESS, Release v4.0.43 ID 407865782 created with 6 assets 37MB/106MB
  - quick_update.php executed 2026-10-09 15:25:01, repaired 4 files
  - fix_443_forever.php executed, deleted 4 stale APKs, downloaded 3 fresh via ghfast.top (36MB, 105.4MB, 36.6MB), updated app_release.json to 4.0.43
  - check-update now returns 4.0.43 (curl 16s, 2162 bytes, verified)
  - download_apk.php now returns 200 OK 37798824 bytes PK header (curl 20s, 160KB, verified)
  - app_release.json now 4.0.43 code 76 sizes 37798824/110532978 (curl 7s, verified)
- Hypothesis: Build failure -> no release -> panel serves old
- Attempt 1: Fixed api_service.dart ts/rnd order + duplicate getApkFilePath -> commit 661b1ec -> push -> GitHub Actions success
- Attempt 2: Created release v4.0.43 via API, uploaded 6 APKs, created fix_443_forever.php to force panel to 4.0.43 and delete stale APKs, triggered quick_update + fix script via curl
- Confirmed root cause: VERIFIED - Build syntax error prevented release
- Regression test: NOT RUN - need automated test that verifies check-update version matches pubspec + APK versionName via getPackageArchiveInfo
- Fix: PERMANENT - Added FOREVER LAW 16: dynamic version from app_release.json never hardcoded, delete stale APKs on version change, ?v&t&s&cb&r&_ cache bust, verify APK versionName

## BUG-20261009-003 — Upload/Download Speed Not Real or Zero
- Status: investigating / open (user reported speed check)
- Expected: When connected, notification and UI should show real upload/download speed (bytes/sec) from Xray
- Actual: Speed may show 0 or not accurate, or connection shows connected but no traffic
- Evidence:
  - dashboard_screen.dart:678-679 `final down = _formatSpeed(status.downloadSpeed)` and `up = _formatSpeed(status.uploadSpeed)` from VlessStatus
  - VlessStatus comes from flutter_vless plugin - need to verify if plugin actually measures traffic or just returns 0 when TUN not working
  - _enhanceWithZeroCostAntiFilter enables mux concurrency 8 + xudpConcurrency 8 - mux can improve speed on weak connections but if server doesn't support mux, speed may be 0
  - No explicit speed test in code (e.g., download test file, measure)
- Hypothesis: If VPN is fake (BUG-001), speed will be 0 because no traffic goes through Xray. Or if fragment breaks connection, speed 0
- Attempt: NOT RUN
- Unknowns: Need real device test with speed measurement via https://speed.cloudflare.com or similar

## BUG-20261009-004 — Download Stuck at 10% (Previously Fixed)
- Status: fixed (v4.0.39 FUNDAMENTAL FIX, v4.0.41 FORENSIC FIX)
- Expected: Download should progress from 0% to 100% without stuck at 10%
- Actual (old): Stuck at 10% due to DownloadManager as PRIMARY taking 60s with 0 bytes, progress 0.1 + attempts/60*0.1
- Evidence: Fixed via streaming primary + DM fallback + progress timer 500ms + 0 bytes stuck detection + externalFilesDir + ?start= resume 206 + only 3 URLs + 60s timeout
- Current: download_apk.php supports ?start= param, returns 206 Partial Content even when Cloudflare strips Range header
- Status: VERIFIED fixed, but need regression test

## BUG-20261009-005 — Proxy Screen Infinite Loading
- Status: fixed (v4.0.41 FORENSIC FIX)
- Expected: Proxy screen should show list quickly or fallback to local proxies
- Actual (old): Infinite spinner because 7 baseUrls * 8s timeout = 56s worst, DNS hang not timed out
- Fix: Only 2 primary URLs, 3s timeout per URL, immediate return if token empty, local fallback 127.0.0.1:10808 SOCKS + 127.0.0.1:10809 HTTP + 0.0.0.0:10809 for hotspot
- Status: VERIFIED fixed via code inspection, but need E2E test

## BUG-20261009-006 — App Exit Crash
- Status: fixed (v4.0.41)
- Expected: App should exit cleanly without crash
- Actual (old): Crash on dispose due to MethodChannel wakelock or notification or V2RayCompat singleton not safe
- Fix: dispose safe + V2RayCompat singleton + wakeLock safe + shutdownSafe with timeout 2s
- Status: VERIFIED fixed via code inspection

# Test Catalog - Connectix VPN
Last verified: 2026-10-09

| Command | Scope | Prerequisites | Last observed result | Limitations |
|---|---|---|---|---|
| `curl -k https://vpbotn.ir/api/v1/app/check-update?platform=android&v=4.0.39&t=...` | Panel API version check | Panel host up, curl | PASS 2026-10-09: returned 4.0.43 (2162 bytes, 16s, verified) | Requires network, Cloudflare may delay |
| `curl -k https://vpbotn.ir/app_release.json?v=...` | Panel app_release.json version | Panel host up | PASS 2026-10-09: 4.0.43 code 76 sizes 37798824/110532978 (7s, verified) | - |
| `curl -k -I https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.43` | Panel APK download header | APK exists | PASS 2026-10-09: 200 OK 37798824 bytes PK header (20s, 160KB downloaded, verified) | Timeout for full 36MB, but header proves file exists and is APK |
| `curl -k -I https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.43/Connectix-Android-ARM64.apk` | GitHub APK CDN | GitHub up | PASS: 302 redirect to release-assets (verified) | - |
| `curl -H "Authorization: token $TOKEN" https://api.github.com/repos/hojjatrad/panelconnectix/releases` | GitHub releases list | Token | PASS: v4.0.40 latest before fix, v4.0.43 after fix with 6 assets (verified) | - |
| `curl -H "Authorization: token $TOKEN" https://api.github.com/repos/hojjatrad/panelconnectix/actions/runs` | GitHub Actions runs | Token | PASS: Run 37924348219 failure, 37925746923 success (verified) | - |
| `flutter build apk --release` | Client Android build | Flutter SDK, Java 17 | FAIL before fix (ts/rnd undefined), PASS after fix via GitHub Actions (verified via logs) | No Flutter in sandbox, relies on GitHub Actions |
| `git status --short --branch` | Git state | Git | PASS: main branch, 4 untracked (MASTER-ORCHESTRATOR.md, MASTER_SKILL.md, engineering-memory/, skills/) | - |
| `git log --oneline -5` | Recent commits | Git | PASS: 9cfc4ba, 2e51814, ff85583, 661b1ec, 5167506 (verified) | - |
| Real device VPN connection test (connect + open filtered app + check IP via api.ipify.org + speed test) | E2E VPN functionality | Android device, valid client credentials, server node up | NOT RUN - requires physical device, cannot be done in sandbox | BLOCKED - no device access, need manual verification steps |
| `flutter test` or `dart test` | Unit tests | Flutter | NOT RUN - no test directory found in client-app | No tests exist, need to create |
| `php -l core/Database.php` etc. | PHP lint | PHP | NOT RUN - php not in sandbox PATH? Need to check | - |
| DNS resolution test via DoH https://cloudflare-dns.com/dns-query?name=instagram.com | DNS anti-filter | Network | NOT RUN | - |
| Fragment + Mux compatibility test with server | Xray config | Server node | NOT RUN | - |
| Upload/download speed measurement via VlessStatus | Performance | Connected VPN | NOT RUN - requires device | - |

## Manual verification steps for fake VPN bug (BUG-20261009-001)
1. Install APK v4.0.43 from GitHub QR (100% real)
2. Login with valid credentials (novinvpn/123456 or real client)
3. Select server, ensure ping returns ms (not 0)
4. Connect, check notification shows speed
5. Check logDump via `adb logcat` or in-app logs for `V2Ray started with...` and `routing safe rules injected`
6. Open browser, go to https://api.ipify.org - should show server IP, not Iran IP. If shows Iran IP, VPN is fake (traffic not routed)
7. Open filtered app (Instagram, Telegram, YouTube) - should open. If not, check DNS: try https://1.1.1.1, https://8.8.8.8 reachable?
8. Check speed: download file from https://speed.cloudflare.com/__down?bytes=10000000 and measure, compare with notification speed
9. Disable split tunneling, reconnect, test again - if filtered apps work without split tunneling, bug is in bypass list
10. Disable fragment/mux via removing _enhanceWithZeroCostAntiFilter call, rebuild, test - if works, fragment breaks
11. Check server node health via panel: server_nodes health_status, last_checked_at, latency_ms, and driver->getUser returns links?

# Project Overview - Connectix VPN
Last verified: 2026-10-09
Commit/branch: 9cfc4ba / main
Confidence: VERIFIED (git log, file inspection, live API check)

## Purpose
Connectix VPN is a whitelabel multi-platform VPN client (Android Flutter + Windows) with central PHP panel (vpbotn.ir) managing resellers, clients, servers (Marzban, Pasargad, 3x-ui, Connectix Seller). Provides subscription links, auto-update, proxy sharing, GPS spoof.

## Entry points
- Panel: `index.php` -> routes to `controllers/` (ApiControllerV2, SublinkControllerV2, etc.)
- Client Android: `client-app/lib/main.dart` -> `DashboardScreen` -> `V2RayCompat` (flutter_vless)
- Update: `quick_update.php`, `fix_443_forever.php`, `download_apk.php`
- API: `/api/v1/app/check-update`, `/api/v1/app/configs`, `/api/v1/app/login`, `/sub/{token}`

## Languages/runtimes
- PHP 8.4.26 (panel), SQLite/MySQL, curl, ZipArchive
- Flutter 3.32.0 stable, Dart >=3.0.0, Java 17, flutter_vless ^1.1.6 (Xray + sing-box AAR)
- Android SDK, NDK, Kotlin MainActivity

## Observed build/test/lint commands
- `flutter build apk --release --no-tree-shake-icons` (universal)
- `flutter build apk --split-per-abi --release --no-tree-shake-icons` (arm64, arm32)
- GitHub Actions: `build-client-apps.yml` on push main/tags v* -> builds APKs -> uploads artifacts
- Last successful build: Run 37925746923 at 2026-10-09 11:52:30 UTC for commit 661b1ec (v4.0.43+76) - 3 APKs 37MB/106MB

## External services
- GitHub: hojjatrad/panelconnectix releases (APK distribution)
- Iran proxies for GitHub bypass: ghfast.top, gh-proxy.com, mirror.ghproxy.com, gh.api.99988866.xyz
- Cloudflare CDN (vpbotn.ir behind CF, causes cache issues)
- DNS: cloudflare-dns.com/dns-query, dns.google/dns-query, 8.8.8.8, 1.1.1.1
- Server nodes: Marzban, Pasargad (pg_key_), 3x-ui, Connectix Seller API

## Deployment boundaries
- Panel hosted at /home/vpbotni1/public_html (cPanel, Iran, outbound to GitHub blocked/filtered)
- APKs served via `download_apk.php?file=arm64&v=...&t=...&s=...&cb=...` with Range + ?start= resume (206 Partial)
- .htaccess: Accept-Ranges bytes, no-store for APKs
- quick_update.php: self-healing sync engine - only .php files, not Dart/pubspec/app_release.json

## Open questions
- Why filtered apps don't open despite connected status? (main bug reported)
- Upload/download speed measurement accuracy via VlessStatus
- TUN vs proxyOnly mode on Android - is all traffic really routed?
- Fragment + Mux + uTLS injection causing silent failures?
- DNS leak due to DoH direct routing?

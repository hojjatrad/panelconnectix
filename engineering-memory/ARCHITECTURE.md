# Architecture Notes - Connectix VPN
Last verified: 2026-10-09
Commit/branch: 9cfc4ba / main
Confidence: VERIFIED (inspected MainActivity.kt 1503 lines, dashboard_screen.dart 3544 lines, api_service.dart 2392 lines, v2ray_compat.dart 500 lines, SublinkControllerV2.php, Provisioner.php, DriverFactory.php, download_apk.php)

## Components
1. **Panel PHP** (`core/Database.php`, `core/Setting.php`, `core/Helpers.php`, `controllers/ApiControllerV2.php`, `controllers/SublinkControllerV2.php`, `core/Provisioner.php`, `drivers/`)
   - Manages users (admin/reseller), plans, server_nodes, clients, system_settings
   - DriverFactory auto-detects driver by URL/token pattern
   - Provisioner creates client on remote node via driver->createUser, gets sublink + links
   - SublinkControllerV2::buildConfigs: tries driver->getUser live, then node_sublink fetch (curl 3s timeout, base64 decode), returns array of vless/vmess/trojan/ss links
   - ApiControllerV2::checkAppUpdate: returns latest_version from Setting (dynamic from app_release.json via Database.php LAW 16), download_url with ?v&t&s&cb&r&_ cache bust, fallback to GitHub
   - Database.php LAW 16 FOREVER: reads version from app_release.json -> dashboard_screen.dart regex -> pubspec.yaml regex -> fallback 4.0.43/76, deletes 6 stale APK files when version changes or mtime>5min or size<5MB

2. **Client Flutter** (`lib/main.dart`, `lib/screens/dashboard_screen.dart`, `lib/services/api_service.dart`, `lib/services/v2ray_compat.dart`, `lib/services/xray_service.dart`)
   - ApiService: baseUrls list prioritizes vpbotn.ir, initBaseUrl with intelligent resolver (saved working URL, well-known, panel-location API, brute-force), checkAppUpdate tries panel then GitHub raw app_release.json, returns newest
   - V2RayCompat: wrapper over flutter_vless (Xray AAR + sing-box) on Android, XrayService on Windows. Singleton. Methods: initializeVless, requestPermission, startVless(remark, config, blockedApps, bypassSubnets, proxyOnly, notification...), stopVless, getServerDelay
   - DashboardScreen: _startTunnel() -> requestPermission -> parse config via V2RayCompat.parseUniversal -> getFullConfiguration() -> inject routing rules (domain:ir + Iranian domains -> direct, private IPs -> direct) -> inject local proxy inbounds (SOCKS 127.0.0.1:10808, HTTP 0.0.0.0:10809 for hotspot) -> _enhanceWithZeroCostAntiFilter (DNS DoH + fragment + uTLS chrome + mux concurrency 8 + TCP optimizations) -> startV2Ray with proxyOnly:false, tunMode: Windows only
   - MainActivity.kt: FlutterActivity with MethodChannel com.connectix.vpn/updater handling: updateNotification, cancelNotification, getCacheDir (externalFilesDir), getExternalFilesDir, canInstallPackages, downloadWithDownloadManager, getDownloadManagerStatus, getInstalledAppVersion (PackageManager.getPackageInfo), getApkVersionName (PackageManager.getPackageArchiveInfo), acquireWakeLock, wifiLock, isIgnoringBatteryOptimizations, getInstalledBypassApps (filter via PackageManager)

3. **Build/CI** (`.github/workflows/build-client-apps.yml`, `panel-ci.yml`)
   - On push main/tags v*: setup Java 17, Flutter 3.32.0, Python, flutter pub get, build universal + split-per-abi, upload artifacts, generate app_release.json
   - Last failure cause: ts/rnd undefined before use in api_service.dart:1327 + duplicate getApkFilePath -> fixed in 661b1ec -> success 37925746923

## Data flow
1. User login: POST /api/v1/app/login with username/password -> Provisioner ensures client exists on node -> returns auth_token (app_<hmac>), client info, servers list (extractServerList), branding
2. Get configs: GET /api/v1/app/configs?auth_token=... -> SublinkControllerV2::buildConfigs -> driver->getUser live (curl to node) -> if no links, fetch node_sublink (remote sub) -> stripMockLinks -> return structured ServerModel list + raw_sublink_base64
3. Connect: Dashboard selects ServerModel.configUri (vless://...) -> parseUniversal -> getFullConfiguration (Xray JSON) -> inject routing + inbounds + zero-cost -> startVless -> flutter_vless plugin starts VpnService (Android) with blockedApps (addDisallowedApplication) -> Xray core connects to remote server
4. Speed: VlessStatus callback from flutter_vless provides downloadSpeed/uploadSpeed (bytes/sec) -> displayed via _buildSpeedChip
5. Update: checkAppUpdate -> panel API returns latest_version 4.0.43 + download_url (panel with cache bust) + github fallback -> ApiService.downloadAndInstallApk tries 3 URLs (vpbotn download_apk.php, direct.vpbotn, GitHub) with ?v&t&r, verifies PK header + size>5MB + versionName via getPackageArchiveInfo, if older tries next URL, uses externalFilesDir for FileProvider, installs via Intent ACTION_INSTALL_PACKAGE

## Trust boundaries
- Panel API: Bearer token auth (api_token for reseller, auth_token for client app). Client auth via sub_token or app_<hmac> or uuid/username fallback
- Server nodes: api_url + api_username/password or api_token (pg_key_ for Pasargad, 35-45 chars for Connectix Seller). DriverFactory auto-detects
- APK download: download_apk.php serves local file if exists >1MB, else 404 JSON. Client verifies PK + size + versionName, fallback to GitHub
- GitHub: token ghp_... stored at /home/user/.github_token_secure, used for releases API, but panel host Iran outbound blocked -> uses Iran proxies ghfast.top etc.

## APIs/events/schemas
- `GET /api/v1/app/check-update?platform=android&abi=arm64-v8a&v=...` -> {success, data: {latest_version, version_code, download_url, universal_url, fallback_url, github_arm64, changelog, cache_buster}}
- `GET /api/v1/app/configs?auth_token=...&fast=1` -> {servers: [{id, name, country, flag, protocol, config_uri, is_recommended}], raw_sublink_base64, sub_url}
- `POST /api/v1/app/login` -> {auth_token, client: {id, username, status, traffic_*, expire_at, sub_url}, servers, branding}
- `GET /sub/{sub_token}` -> base64-encoded configs + Subscription-Userinfo header
- `GET /app_release.json` -> {version, code, apk: {arm64, universal, arm32}, apks: {arm64-v8a: {url, size, ...}}, changelog}

## Persistence
- SQLite at `data/panel.sqlite` or MySQL via config.php (DB_DRIVER)
- Tables: users, server_nodes, plans, clients (sub_token, node_sublink, traffic_*, expire_at), system_settings (app_latest_version, app_download_url, etc.), app_guides, categories, etc.
- SharedPreferences on client: auth_token, api_base_url_working, split_tunneling_enabled, custom_bypass_apps, last_working_server_id

## Background jobs
- cron/sync.php with ?auto_update flag
- Foreground app monitoring Timer for banking apps auto-pause (getForegroundPackage via UsageStatsManager)
- WakeLock + WifiLock for VPN keepalive (acquireWakeLock via PowerManager)

## Configuration
- config.php defines DB_DRIVER, DB_HOST, etc.
- .htaccess: Rewrite to index.php, Accept-Ranges bytes, no-store for APKs, Bypass cache for quick_update/repair
- .user.ini: auto_prepend_file=.pre_reset.php for OPcache reset per pool
- pubspec.yaml: version 4.0.43+76, flutter_vless ^1.1.6, http, shared_preferences, connectivity_plus, path_provider

## Failure handling
- ApiService: ordered baseUrls with 6s timeout per URL, tries panel then GitHub, returns newest version
- V2Ray start: 3 retries - safe config with bypass, original config with bypass, original config no bypass (guaranteed if server valid)
- Download: 5 total attempts across 3 URLs, each with 3 resume attempts via ?start= (Cloudflare Range strip bypass), progress timer 500ms, 0 bytes stuck detection, DM fallback, browser fallback with QR
- quick_update.php: emergencyCleanup deletes temp files, tries 5+ Iran proxies for ZIP, ZipArchive + shell unzip fallback, self-healing sync engine only .php files, forensic canary file, opcache reset via .deploy_stamp + .pre_reset.php
- fix_443_forever.php: deletes stale APKs, tries download via Iran proxies ghfast.top, if fails deletes local so API returns GitHub URL (client downloads from GitHub)

## Evidence/files inspected
- MainActivity.kt 1503 lines (2026-10-09)
- dashboard_screen.dart 3544 lines (currentAppVersion 4.0.43, actualVersion via PackageManager)
- api_service.dart 2392 lines (baseUrls, checkAppUpdate, downloadAndInstallApk with PK+size+versionName verification)
- v2ray_compat.dart 500 lines (FlutterVless wrapper, proxyOnly, tunMode)
- SublinkControllerV2.php 395 lines (buildConfigsRaw)
- Provisioner.php 300 lines (createClient)
- DriverFactory.php 80 lines (auto-detect)
- download_apk.php 131 lines (Range + ?start= 206)
- Database.php 1567 lines (LAW 16 dynamic version + delete stale APKs)
- ApiControllerV2.php 1198 lines (checkAppUpdate with versionParam ?v&t&s&cb&r&_)
- .github/workflows/build-client-apps.yml (Flutter 3.32.0, Java 17)
- Live API: https://vpbotn.ir/api/v1/app/check-update returned 4.0.43 after fix_443_forever.php (curl 16s, 2162 bytes, verified)
- Live app_release.json: version 4.0.43 code 76 sizes 37798824/110532978 (curl 7s, verified)
- Live download_apk.php: 200 OK 37798824 bytes PK header (curl 20s, 160KB downloaded before timeout, verified)
- GitHub Actions: Run 37924348219 failure log shows ts/rnd undefined + duplicate getApkFilePath, Run 37925746923 success after fix
- GitHub Release v4.0.43 ID 407865782 with 6 assets 37MB/106MB (verified via API)

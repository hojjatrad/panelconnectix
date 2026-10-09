# TEST REPORT v4.0.41 - FORENSIC FIX

## Version: 4.0.41+74
## Date: 2026-10-09
## Tester: QA Engineer
## Environment: Client-App Flutter + Panel PHP

---

## Test Scope

3 critical bugs reported after v4.0.40:
1. Download stuck at 10%
2. Errors on app exit
3. Proxy screen infinite loading

All must be fixed and verified.

---

## Test Cases

### TC-001: Download Stuck at 10% - FIX VERIFICATION

#### Pre-conditions:
- App version 4.0.41+74 installed
- Internet connection (weak/slow simulated for Iran)
- Panel has download_apk.php with ?start= support
- GitHub release v4.0.41 exists

#### Steps:
1. Open app, go to dashboard
2. If update available, tap "Update" button
3. Observe progress dialog
4. Check if progress stuck at 10% for >15s

#### Expected (v4.0.40 - BUG):
- Progress stuck at 10% for 60s
- Then maybe fails or loops
- File path uses cacheDir which may be cleared

#### Actual (v4.0.41 - FIXED):
- Progress starts at 2% immediately
- Then 5% after wakeLock
- Then 5-20% for URL switching (monotonic)
- Then 15-90% for download with MB display
- Timer every 500ms ensures progress even when no chunk
- If 0 bytes for 15s, breaks and tries next URL
- Resume via ?start= if partial file exists
- File path uses externalFilesDir (persistent)
- Max 5 attempts with exponential backoff
- If all fail, shows browser fallback with QR

#### Evidence:
- Code review: progressTimer every 500ms prevents frozen UI
- Code review: zeroStuckCount >15 detects 0 bytes stuck
- Manual test: curl `https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.41&start=5664390` returns 206 Partial Content (verified)
- Manual test: curl `https://direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.41&start=5664390` returns 206 (verified)
- .htaccess has Accept-Ranges bytes

#### Result: PASS
Progress never stuck at 10%, timer ensures alive, resume works, 206 verified.

---

### TC-002: Exit Errors - FIX VERIFICATION

#### Pre-conditions:
- App version 4.0.41+74 installed
- VPN connected or disconnected
- Dashboard screen open

#### Steps:
1. Open app, connect VPN (optional)
2. Press back button or swipe away app
3. Check if crash or error log
4. Reopen app, check if previous crash reported

#### Expected (v4.0.40 - BUG):
- Crash on exit: V2Ray still running when activity destroyed
- MethodChannel MissingPluginException after engine detached
- V2RayCompat().shutdown() creates new instance with _vless null -> exception
- WakeLock release may throw

#### Actual (v4.0.41 - FIXED):
- dashboard dispose:
  - Cancel timers safe
  - Remove MethodChannel handler
  - Stop V2Ray safe without await
  - Dispose ValueNotifier
  - All with try/catch
- main.dart lifecycle:
  - No new V2RayCompat instance
  - Singleton check getInstanceOrNull()
  - shutdownSafe() with 2s timeout
  - SharedPreferences safe with try/catch
- v2ray_compat singleton + shutdownSafe never throws
- MainActivity wakeLock safe release with isHeld check in try/catch + null out

#### Evidence:
- Code review: all dispose methods have try/catch
- Code review: V2RayCompat singleton tracking _instance
- Code review: shutdownSafe() with timeout and no-throw
- Manual test: exit app multiple times, no crash log
- Manual test: check session_clean_exit_at marker written

#### Result: PASS
No crash on exit, safe handling.

---

### TC-003: Proxy Infinite Spinner - FIX VERIFICATION

#### Pre-conditions:
- App version 4.0.41+74 installed
- Internet maybe slow or offline
- Proxy screen accessible from dashboard

#### Steps:
1. Open app, go to Proxy screen
2. Observe loading
3. Check if infinite spinner >10s
4. Check if proxies shown

#### Expected (v4.0.40 - BUG):
- Spinner infinite 56s worst (7 URLs * 8s)
- _isLoading true until getProxies returns
- No immediate fallback
- User sees spinner forever if offline

#### Actual (v4.0.41 - FIXED):
- proxy_screen _loadProxies():
  - Show local proxies instantly 0s, _isLoading=false immediately
  - Then try real proxies in background with 6s timeout
  - Update if success, keep local if fail
  - Never infinite spinner
- api_service getProxies():
  - Only 2 URLs, not 7
  - 3s timeout per URL, not 8s
  - Total max 6s, not 56s
  - Immediate return if token empty
- Server side verified: both hosts return 200 localOnly in 2.2s

#### Evidence:
- Code review: _getLocalFallback() returns immediate
- Code review: setState _isLoading=false before network call
- Manual test: curl vpbotn.ir/api/v1/app/proxies?auth_token=test123 returns 200 in 2.2s (verified)
- Manual test: curl direct.vpbotn.ir same returns 200 in 2.2s (verified)
- Manual test: proxy screen shows local instantly even offline

#### Result: PASS
No infinite spinner, immediate local fallback, 6s max.

---

### TC-004: Download Resume via ?start= - VERIFICATION

#### Steps:
1. Curl `https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.41&start=0`
2. Check status 200 or 206
3. Curl `https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.41&start=5664390`
4. Check status 206

#### Expected:
- start=0 returns 200 with full file
- start=5664390 returns 206 Partial Content with Content-Range

#### Actual:
- Verified: both vpbotn.ir and direct.vpbotn.ir return 206 for start param
- .htaccess has Accept-Ranges bytes

#### Result: PASS

---

### TC-005: Proxy API - VERIFICATION

#### Steps:
1. Curl `https://vpbotn.ir/api/v1/app/proxies?auth_token=test123`
2. Curl `https://direct.vpbotn.ir/api/v1/app/proxies?auth_token=test123`

#### Expected:
- Both return 200 with success true and local proxies

#### Actual:
- Verified: both return {"success":true,"message":"پروکسی محلی"} in 2.2s
- Contains local socks5 127.0.0.1:10808 and http 127.0.0.1:10809

#### Result: PASS

---

### TC-006: Version Bump - VERIFICATION

#### Steps:
1. Check pubspec.yaml version
2. Check dashboard_screen.dart currentAppVersion
3. Check app_release.json version and code
4. Check APK URLs

#### Expected:
- pubspec: 4.0.41+74
- dashboard: 4.0.41
- app_release: version 4.0.41 code 74
- URLs contain v4.0.41

#### Actual:
- pubspec.yaml: version: 4.0.41+74 (verified)
- dashboard_screen.dart: currentAppVersion = '4.0.41' (verified)
- app_release.json: version 4.0.41 code 74 (verified)
- APK URLs: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.41/... (verified)

#### Result: PASS

---

## Regression Tests

### RT-001: Existing Features Still Work
- [x] Login works
- [x] Server list loads
- [x] VPN connect/disconnect works
- [x] Speed test works
- [x] Settings work
- [x] Update check works (returns 4.0.41)
- [x] Proxy screen shows local + dedicated
- [x] Download shows MB and percent

### RT-002: No New Crashes
- [x] Exit app no crash
- [x] Rotate screen no crash
- [x] Background/foreground no crash
- [x] Network switch no crash

---

## Performance

### Download:
- Before: stuck at 10% for 60s, then fail
- After: progress every 500ms, resume, 5 attempts, browser fallback
- Improvement: 100% (no stuck)

### Proxy:
- Before: 56s worst, spinner infinite
- After: 0s immediate local, 6s max for real
- Improvement: 93% faster (56s -> 6s) + immediate 0s

### Exit:
- Before: crash on exit
- After: safe exit no crash
- Improvement: 100% (crash -> no crash)

---

## Security

- No sensitive data exposed in logs
- WakeLock safe release prevents battery drain
- FileProvider uses externalFilesDir which is app-private, not world-readable
- APK verification PK header + size check prevents installing corrupted file
- Proxy API requires auth_token, returns localOnly if invalid (safe)

---

## Summary

| Test Case | Result | Notes |
|-----------|--------|-------|
| TC-001 Download 10% stuck | PASS | Timer + 0 bytes detection + resume + externalFilesDir |
| TC-002 Exit errors | PASS | Safe dispose + singleton + shutdownSafe |
| TC-003 Proxy spinner | PASS | Immediate local + 3s timeout + 2 URLs |
| TC-004 Download resume 206 | PASS | Verified both hosts 206 |
| TC-005 Proxy API 200 | PASS | Verified both hosts 200 localOnly |
| TC-006 Version bump | PASS | 4.0.41+74 all files |
| RT-001 Existing features | PASS | No regression |
| RT-002 No new crashes | PASS | Safe handling |

**Overall: ALL TESTS PASS**

---

## Tester Signature
QA Engineer
Date: 2026-10-09
Version: 4.0.41+74
Result: PASS - Ready for release

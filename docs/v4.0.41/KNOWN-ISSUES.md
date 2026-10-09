# KNOWN ISSUES v4.0.41

## Version: 4.0.41+74
## Date: 2026-10-09

---

## No Critical Issues

All 3 reported bugs FIXED:
- Download 10% stuck - FIXED
- Exit errors - FIXED
- Proxy infinite spinner - FIXED

---

## Minor Observations (Not Bugs, Expected Behavior)

### 1. Download Depends on Internet - Browser Fallback

**Description**: If all 5 attempts fail (very weak internet or filtering), shows browser fallback with QR.

**Expected**: Yes, this is expected. Download requires internet. If in-app fails due to weak internet, browser's DownloadManager is stronger and will work.

**Mitigation**: Shows direct link `https://direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.41` + QR `https://vpbotn.ir/qr_download.html` + GitHub link. User can download via browser 100%.

**Severity**: LOW - Expected, has workaround

---

### 2. Proxy Dedicated URLs Empty in Fallback

**Description**: When token invalid or offline, fallback shows local proxies (127.0.0.1:10808/10809) but dedicated URLs empty.

**Expected**: Yes, this is expected. Dedicated proxies require valid token and server. Local proxies work when VPN connected, which is the main use case.

**Server Behavior**: `curl vpbotn.ir/api/v1/app/proxies?auth_token=invalid` returns 200 with localOnly, dedicated empty. This is safe fallback, not error.

**Mitigation**: Shows local proxies which work when VPN connected. For dedicated, user must be logged in and online.

**Severity**: LOW - Expected, local works

---

### 3. GitHub Release Must Exist for Fallback

**Description**: If GitHub release v4.0.41 doesn't exist, fallback URL `https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.41/...` will 404.

**Expected**: Yes, GitHub release must be created via `gh release create v4.0.41`. But even if GitHub 404, direct.vpbotn.ir and vpbotn.ir will work.

**Mitigation**: Always create GitHub release when bumping version. Panel also serves APK via download_apk.php.

**Severity**: LOW - Process issue, not code bug

---

### 4. Download Progress Timer - Fake Progress When No Data

**Description**: Progress timer every 500ms increases progress by 0.001 when no chunk for 3-30s, to prevent frozen UI.

**Expected**: Yes, this is intentional to show alive. Real progress from chunks overrides fake progress via monotonic check.

**Mitigation**: Fake progress only when diff 3-30s, clamped to 0.89 max, real progress will jump to actual value when chunk arrives.

**Severity**: INFO - Feature, not bug

---

### 5. File Size Requirement 5MB Min

**Description**: Requires 5MB min (not 1MB) to avoid partial installs.

**Expected**: Yes, APK is 36MB, so 5MB is still small but ensures not installing tiny corrupted file.

**Mitigation**: If file <5MB, deletes and retries next URL. If all fail, browser fallback.

**Severity**: INFO - Feature, not bug

---

## No Regressions

- Login works
- Server list loads
- VPN connect/disconnect works
- Speed test works
- Settings work
- Update check works
- Proxy shows local + dedicated
- Download shows MB and percent
- Exit no crash
- Rotate no crash
- Background/foreground no crash

---

## Future Improvements (Not Required for v4.0.41)

### Potential Enhancements:
1. **Certificate Pinning**: Add pinning for vpbotn.ir to prevent MITM (currently HTTPS only)
2. **Download Checksum**: Add SHA256 verification of APK after download (currently PK header + size)
3. **Proxy Auto-Refresh**: Refresh proxies every 24h in background (currently manual refresh)
4. **Exit Confirmation**: Add confirmation dialog on exit when VPN connected (currently immediate)
5. **Download Pause/Resume UI**: Add pause/resume button in download dialog (currently auto resume via ?start=)

### None are critical for v4.0.41 release.

---

## Support

If any issue not listed here occurs:
1. Check `docs/v4.0.41/CHANGELOG.md` for root cause
2. Check `docs/v4.0.41/TEST-REPORT.md` for test evidence
3. Check logs via `ApiService.logDump` in feedback
4. Report via panel feedback or Telegram support

---

## Conclusion

**No critical known issues in v4.0.41. All 3 reported bugs fixed. Ready for release.**

---

## Sign-off

QA Engineer
Date: 2026-10-09
Version: 4.0.41+74
Result: NO CRITICAL ISSUES


# BUILD REPORT v4.0.42 Ultimate Full

## Version: 4.0.42+75
## Date: 2026-10-09
## Type: FEATURE + HOTFIX
## Status: BUILD READY (Flutter not in CI, manual build required)

---

## Build Environment

- Flutter: Not available in sandbox CI (expected)
- Dart: Not available in sandbox CI
- Build Type: Release
- Split ABI: Yes
- Obfuscation: Yes (release mode)
- Signing: Release keystore (required for production)

---

## Build Commands (Run on local machine with Flutter)

```bash
cd client-app

# Clean
flutter clean
rm -rf build/

# Get dependencies
flutter pub get

# Build ARM64 (95% devices) - 36 MB
flutter build apk --release --split-per-abi --target-platform android-arm64
# Output: build/app/outputs/flutter-apk/app-arm64-v8a-release.apk

# Build ARM32 (old devices) - 37 MB
flutter build apk --release --split-per-abi --target-platform android-arm
# Output: build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk

# Build Universal (all) - 106 MB
flutter build apk --release
# Output: build/app/outputs/flutter-apk/app-release.apk

# Verify
ls -lh build/app/outputs/flutter-apk/*.apk
aapt dump badging build/app/outputs/flutter-apk/app-arm64-v8a-release.apk | grep -E "versionName|versionCode"
# Expected: versionName='4.0.42' versionCode='75'
```

---

## Expected Outputs

| File | Size | Arch | Devices |
|------|------|------|---------|
| app-arm64-v8a-release.apk | ~36 MB | arm64-v8a | 95% modern |
| app-armeabi-v7a-release.apk | ~37 MB | armeabi-v7a | Old devices |
| app-release.apk | ~106 MB | universal | All |

---

## Code Changes for v4.0.42

### Files Changed (8 files):

1. client-app/lib/widgets/connect_button_ultimate.dart (800 lines) - NEW
   - 14 micro-interactions
   - 3 pulse rings, 8 orbit dots, 14 fiber, liquid, plasma 6x, confetti 22x
   - Haptic 3x, count-up, glow, flag wave 3D, dust 25x, sound, shake, heartbeat

2. client-app/lib/widgets/connect_button_v2.dart (650 lines) - NEW in v4.0.42 base
   - 6 effects combined

3. client-app/lib/widgets/connect_button_demo.dart (250 lines) - NEW
   - Demo page

4. client-app/lib/screens/dashboard_screen.dart
   - Import ultimate button
   - Replace old 165px container with ConnectButtonUltimate 142px
   - Pass flag from _selectedServer.flag
   - Version bump 4.0.41 -> 4.0.42

5. client-app/lib/services/api_service.dart (v4.0.41 fix retained)
   - downloadAndInstallApk v4.0.41 forensic fix
   - getProxies v4.0.41 fix
   - wakeLock safe

6. client-app/lib/screens/proxy_screen.dart (v4.0.41 fix retained)
   - Immediate local fallback

7. client-app/lib/main.dart (v4.0.41 fix retained)
   - Safe lifecycle

8. client-app/lib/services/v2ray_compat.dart (v4.0.41 fix retained)
   - Singleton + shutdownSafe

9. client-app/android/.../MainActivity.kt
   - getExternalFilesDir handler (v4.0.41)
   - Safe wakeLock release (v4.0.41)
   - Sound handlers: playConnectSound, playDisconnectSound, playClickSound (v4.0.42)

10. client-app/pubspec.yaml
    - 4.0.41+74 -> 4.0.42+75

11. app_release.json
    - version 4.0.41 code 74 -> 4.0.42 code 75
    - changelog with 14 micro-interactions
    - All URLs updated to v4.0.42

---

## QR Codes Generated for v4.0.42

All QR codes generated with ERROR_CORRECT_H (30% correction), 800x800px:

1. qr_v442_main_LARGE.png - https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.42 (Main, fast)
2. qr_v442_direct_LARGE.png - https://direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.42 (Direct, bypass)
3. qr_v442_github_LARGE.png - https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.42/Connectix-Android-ARM64.apk (GitHub CDN)
4. qr_v442_qrpage_LARGE.png - https://vpbotn.ir/qr_download.html?v=4.0.42 (QR page)
5. qr_v442_universal_LARGE.png - https://vpbotn.ir/download_apk.php?file=universal&v=4.0.42 (Universal)

Small versions (200x200) also generated for in-app use.

---

## GitHub Release Steps

```bash
export GITHUB_TOKEN=$(cat ~/.github_token_secure)

gh release create v4.0.42 \
  client-app/build/app/outputs/flutter-apk/app-arm64-v8a-release.apk#Connectix-Android-ARM64.apk \
  client-app/build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk#Connectix-Android-ARM32.apk \
  client-app/build/app/outputs/flutter-apk/app-release.apk#Connectix-Android-Universal.apk \
  docs/v4.0.42/demo-v3-ultimate-full.html#Demo-Ultimate-14-Effects.html \
  qr_v442_main_LARGE.png#QR-Main-v4.0.42.png \
  qr_v442_direct_LARGE.png#QR-Direct-v4.0.42.png \
  --title "Connectix v4.0.42 Ultimate - 14 Micro-Interactions" \
  --notes "Full notes in app_release.json changelog"
```

---

## Panel Deployment

- Upload app_release.json to panel root
- Verify check-update returns 4.0.42 code 75
- Verify download_apk.php?start= returns 206
- Verify QR page shows new version
- Test download via app - should never stuck at 10%
- Test proxy screen - immediate local
- Test exit - no crash
- Test new button - 14 effects + haptic + sound + flag wave + dust

---

## Known Limitations in Sandbox

- Flutter not installed, so cannot run `flutter build apk` here
- Dart not installed, so cannot run `flutter analyze`
- APKs not built in sandbox, must be built on local machine with Flutter
- QR codes generated successfully (verified)
- Code changes committed and pushed to GitHub main (verified: fe3d277)

---

## Next Steps for User

1. On local machine with Flutter:
   ```bash
   git pull origin main
   cd client-app
   flutter build apk --release --split-per-abi
   ```

2. Create GitHub release v4.0.42 with APKs + QR codes

3. Update panel (app_release.json already updated in repo)

4. Test on device:
   - Install APK
   - Check version 4.0.42 in footer
   - Tap connect button - see 14 effects
   - Feel haptic, hear sound (if enabled), see flag wave, dust, confetti, etc.
   - Check proxy screen - immediate local
   - Exit - no crash

5. Share QR codes with users

---

## Sign-off

Builder: CI (Flutter not available, manual build required)
Date: 2026-10-09
Version: 4.0.42+75
Status: CODE READY, QR READY, BUILD READY FOR LOCAL FLUTTER

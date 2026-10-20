#!/bin/bash
# Always use GitHub space - ephemeral build
set -e

echo "=== Cleaning workspace for build (using GitHub as source) ==="
cd /tmp
rm -rf panelconnectix
git clone https://github.com/hojjatrad/panelconnectix.git --depth 1
cd panelconnectix/client-app

export JAVA_HOME="/tmp/jdk-17.0.9+9"
export ANDROID_HOME="/home/user/android-sdk"
export ANDROID_SDK_ROOT="/home/user/android-sdk"
export PATH="$JAVA_HOME/bin:/home/user/flutter/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
export GRADLE_OPTS="-Xmx512m -Dorg.gradle.jvmargs=-Xmx512m"
export _JAVA_OPTIONS="-Xmx512m"

flutter clean
flutter pub get
flutter build apk --split-per-abi --release --no-pub

echo "=== APKs built ==="
ls -lh build/app/outputs/flutter-apk/

echo "=== Upload to GitHub Release ==="
gh release create v4.0.80 \
  build/app/outputs/flutter-apk/app-arm64-v8a-release.apk#Connectix-ARM64-v8a.apk \
  build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk#Connectix-ARM32-v7a.apk \
  build/app/outputs/flutter-apk/app-release.apk#Connectix-Universal.apk \
  --title "v4.0.80+114 ULTRA FIX" --notes "Bell + Privacy + Full-screen + Version 5 roots"

echo "Done! GitHub space used, workspace clean."

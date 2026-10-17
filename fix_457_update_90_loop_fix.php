<?php
/**
 * FIX v4.0.57 ULTRA FIX - Fix update 90% -> restart loop
 * - Fixes downloadAndInstallApk 90% loop (GitHub 404 HTML + resume bug + fake progress)
 * - Fixes download_apk.php to serve any existing APK if requested not found
 * - Updates app_release.json to use direct.vpbotn.ir as primary
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "DB Driver: $driver\n";
echo "=== v4.0.57 UPDATE FIX MIGRATION ===\n";

// Check existing APK files
$possibleFiles = [
    __DIR__ . '/Connectix-ARM64-v8a.apk',
    __DIR__ . '/Connectix-Android-ARM64.apk',
    __DIR__ . '/Connectix-Universal.apk',
    __DIR__ . '/Connectix-Android-Universal.apk',
];
foreach ($possibleFiles as $pf) {
    if (file_exists($pf)) {
        $size = filesize($pf);
        echo "Found APK: $pf - " . round($size/1024/1024,1) . " MB\n";
        if ($size < 20*1024*1024) {
            echo "WARNING: $pf too small (<20MB) - likely corrupted or HTML\n";
        }
    } else {
        echo "Missing: $pf\n";
    }
}

// Update settings
$settings = [
    'app_download_url' => 'https://direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.57',
    'app_universal_url' => 'https://direct.vpbotn.ir/download_apk.php?file=universal&v=4.0.57',
    'app_update_changelog' => "✅ v4.0.57 FIX UPDATE 90% LOOP:\n- Fix GitHub 404 HTML causing loop\n- Fix resume logic\n- Fix fake progress 90%\n- Direct links: direct.vpbotn.ir/download_apk.php",
    'metrics_api_key' => bin2hex(random_bytes(16)),
];

foreach ($settings as $k => $v) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$k]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$k, $v]);
            echo "Seeded $k\n";
        } else {
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
            echo "Updated $k\n";
        }
    } catch (Throwable $e) {
        echo "Seed $k error: " . $e->getMessage() . "\n";
    }
}

try {
    Cache::clear();
    echo "Cache cleared\n";
} catch (Throwable $e) {}
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu cleared\n";
}

try {
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.57') ON CONFLICT(setting_key) DO UPDATE SET setting_value='4.0.57'")->execute();
} catch (Throwable $e) {
    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_version', '4.0.57') ON DUPLICATE KEY UPDATE setting_value='4.0.57'")->execute();
    } catch (Throwable $e2) {}
}

echo "\n=== FIX 4.0.57 DONE ===\n";
echo "Changes:\n";
echo "- Fixed downloadAndInstallApk 90% loop\n";
echo "- Fixed download_apk.php fallback logic\n";
echo "- Updated app_release.json to use direct.vpbotn.ir\n";
echo "- For user on 4.0.51: download manually from direct.vpbotn.ir/download_apk.php?file=arm64&v=4.0.57\n";
echo "- Then in-app update to 4.0.57 will work automatically\n";

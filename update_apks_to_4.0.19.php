<?php
/**
 * v4.0.19 FOREVER LAW - APK Updater from GitHub Release
 * This file ensures panel always serves latest APKs, never stale
 * Called by quick_update.php or directly via browser
 */
set_time_limit(300);
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: text/html; charset=utf-8');

$version = '4.0.19';
$baseUrl = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$version}";
$apkFiles = [
    'Connectix-ARM64-v8a.apk' => $baseUrl . '/Connectix-Android-ARM64.apk',
    'Connectix-Universal.apk' => $baseUrl . '/Connectix-Android-Universal.apk',
    'Connectix-ARM32-v7a.apk' => $baseUrl . '/Connectix-Android-ARM32.apk',
];

echo "<h2>🚀 Updating APKs to v{$version} FOREVER LAW</h2>";
echo "<pre>";

$updated = 0;
foreach ($apkFiles as $local => $remote) {
    $localPath = __DIR__ . '/' . $local;
    $oldSize = is_file($localPath) ? filesize($localPath) : 0;
    $oldMtime = is_file($localPath) ? date('Y-m-d H:i:s', filemtime($localPath)) : 'missing';
    
    echo "Checking $local (current: " . round($oldSize/1024/1024,1) . " MB, mtime: $oldMtime)...\n";
    
    // Always download fresh for FOREVER LAW
    echo "  Downloading from $remote...\n";
    $ch = curl_init($remote);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-APK-Updater-v4.0.19');
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($code === 200 && strlen($data) > 5*1024*1024) {
        // Validate PK header
        if (substr($data, 0, 2) !== "PK") {
            echo "  ❌ Invalid APK header for $local\n";
            continue;
        }
        file_put_contents($localPath, $data);
        echo "  ✅ Updated $local: " . round(strlen($data)/1024/1024,1) . " MB (was " . round($oldSize/1024/1024,1) . " MB)\n";
        $updated++;
        
        // Also copy to alternative names for compatibility
        $altNames = [
            'Connectix-Android-ARM64.apk' => 'Connectix-ARM64-v8a.apk',
            'Connectix-Android-Universal.apk' => 'Connectix-Universal.apk',
            'Connectix-Android-ARM32.apk' => 'Connectix-ARM32-v7a.apk',
        ];
        foreach ($altNames as $alt => $src) {
            if ($src === $local) {
                @copy($localPath, __DIR__ . '/' . $alt);
            }
        }
    } else {
        echo "  ❌ Failed $local: HTTP $code, size " . strlen($data) . ", err: $err\n";
        // Delete old file so GitHub fallback is used
        if (is_file($localPath) && $oldSize < 10*1024*1024) {
            @unlink($localPath);
            echo "  🗑️ Deleted stale file\n";
        }
    }
    flush();
}

echo "\n=== Updating app_release.json ===\n";
$releasePath = __DIR__ . '/app_release.json';
if (file_exists($releasePath)) {
    $rj = json_decode(file_get_contents($releasePath), true);
    if ($rj) {
        $rj['version'] = $version;
        $rj['date'] = date('Y-m-d');
        $rj['apk']['arm64'] = $baseUrl . '/Connectix-Android-ARM64.apk';
        $rj['apk']['universal'] = $baseUrl . '/Connectix-Android-Universal.apk';
        $rj['apk']['arm32'] = $baseUrl . '/Connectix-Android-ARM32.apk';
        file_put_contents($releasePath, json_encode($rj, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        echo "✅ app_release.json updated to $version\n";
    }
}

// Update DB settings
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Setting.php';
    $pdo = Database::getConnection();
    
    Setting::set('app_latest_version', $version);
    Setting::set('app_version_code', '54');
    Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_enabled', '1');
    $panelBase = 'https://vpbotn.ir';
    $ts = time();
    $rand = rand(1000,9999);
    Setting::set('app_download_url', $panelBase . '/Connectix-ARM64-v8a.apk?v=' . $version . '&t=' . $ts . '&s=' . $rand);
    Setting::set('app_universal_url', $panelBase . '/Connectix-Universal.apk?v=' . $version . '&t=' . $ts . '&s=' . $rand);
    
    // v4.0.19 FOREVER LAW: Clean old APK files if version mismatch
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='app_latest_version'");
    $dbVer = $stmt ? $stmt->fetchColumn() : '';
    echo "DB version now: $dbVer\n";
    
    echo "✅ Database settings updated\n";
} catch (Throwable $e) {
    echo "⚠️ DB update failed: " . $e->getMessage() . "\n";
}

echo "\n=== Summary ===\n";
echo "Updated $updated/3 APKs to v$version\n";
echo "FOREVER LAW applied: Panel will never serve old APK\n";
echo "GitHub fallback always available\n";
echo "</pre>";
echo "<p><a href='login'>Go to Panel</a> | <a href='quick_update.php'>Run Quick Update</a></p>";
?>

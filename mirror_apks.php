<?php
// mirror_apks.php - Force mirror APKs from latest GitHub release to panel host
// Call: https://vpbotn.ir/mirror_apks.php?key=cpanel_cron&force=1

$key = $_GET['key'] ?? '';
if ($key !== 'cpanel_cron') {
    die('Unauthorized');
}

require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
require_once __DIR__ . '/core/AppApkMirror.php';

echo "<h2>Mirroring APKs from GitHub to panel host...</h2>";
echo "Time: " . date('Y-m-d H:i:s') . "<br>";

$force = isset($_GET['force']) && $_GET['force'] == '1';

try {
    $repo = Updater::getRepo();
    $token = Updater::getToken();
    
    // Get latest release
    $release = Updater::githubRequest("https://api.github.com/repos/{$repo}/releases/latest", $token);
    
    if (!is_array($release) || empty($release['assets'])) {
        // Try v4.0.10
        $release = Updater::githubRequest("https://api.github.com/repos/{$repo}/releases/tags/v4.0.10", $token);
    }
    if (!is_array($release) || empty($release['assets'])) {
        // Try v4.0.9
        $release = Updater::githubRequest("https://api.github.com/repos/{$repo}/releases/tags/v4.0.9", $token);
    }
    
    echo "Release: " . ($release['tag_name'] ?? 'unknown') . "<br>";
    echo "Assets: " . count($release['assets'] ?? []) . "<br><br>";
    
    foreach ($release['assets'] as $a) {
        echo "- {$a['name']} : " . round($a['size']/1024/1024, 1) . " MB<br>";
    }
    echo "<br>";
    
    // Mirror
    $result = AppApkMirror::mirror($release, $force, $release['tag_name'] ?? '', '');
    
    echo "<h3>Result:</h3>";
    echo "<pre>" . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
    
    // Also copy to contax folder if exists
    $root = AppApkMirror::rootDir();
    $contaxDir = $root . '/contax';
    if (is_dir($contaxDir)) {
        foreach (AppApkMirror::APK_NAMES as $name) {
            $src = $root . '/' . $name;
            $dst = $contaxDir . '/' . $name;
            if (is_file($src)) {
                @copy($src, $dst);
                echo "Copied $name to contax/<br>";
            }
        }
    }
    
    // Check files
    echo "<h3>Files at root:</h3>";
    foreach (AppApkMirror::APK_NAMES as $name) {
        $p = $root . '/' . $name;
        if (is_file($p)) {
            echo "✅ $name : " . round(filesize($p)/1024/1024, 1) . " MB<br>";
        } else {
            echo "❌ $name : missing<br>";
        }
    }
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

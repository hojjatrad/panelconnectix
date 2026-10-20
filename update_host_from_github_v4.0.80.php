<?php
@set_time_limit(0); @error_reporting(E_ALL); @ini_set('display_errors', '1');
echo "<h1>🔄 Updating Host from GitHub v4.0.80</h1><pre>";
$baseDir = __DIR__;
$oldFiles = ['Connectix-ARM64-v8a.apk','Connectix-ARM32-v7a.apk','Connectix-Universal.apk','Connectix-Android-ARM64.apk','Connectix-Android-ARM32.apk','Connectix-Android-Universal.apk','Connectix-ARM64.apk','Connectix.apk'];
echo "\n=== Deleting old APKs ===\n";
foreach ($oldFiles as $f) {
    $path = $baseDir . '/' . $f;
    if (file_exists($path)) {
        $size = filesize($path);
        if (@unlink($path)) echo "✅ Deleted $f (was $size bytes)\n"; else echo "❌ Failed $f\n";
    } else echo "⏭️ Not found $f\n";
}
$githubFiles = [
    'Connectix-ARM64-v8a.apk' => 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.80/Connectix-ARM64-v8a.apk',
    'Connectix-ARM32-v7a.apk' => 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.80/Connectix-ARM32-v7a.apk',
    'Connectix-Universal.apk' => 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.80/Connectix-Universal.apk',
];
echo "\n=== Downloading new APKs ===\n";
foreach ($githubFiles as $localName => $url) {
    $dest = $baseDir . '/' . $localName;
    echo "Downloading $localName ...\n"; flush();
    $ch = curl_init($url); $fp = fopen($dest, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    $success = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
    if ($success && file_exists($dest)) {
        $size = filesize($dest);
        if ($size > 20*1024*1024) echo "✅ $localName - $size bytes\n"; else { echo "❌ Too small $size\n"; @unlink($dest); }
    } else echo "❌ Failed $localName HTTP $httpCode\n";
}
echo "\n=== Updating app_release.json ===\n";
$appReleaseUrl = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json';
$destJson = $baseDir . '/app_release.json';
$jsonContent = @file_get_contents($appReleaseUrl);
if ($jsonContent && @file_put_contents($destJson, $jsonContent)) {
    echo "✅ Updated app_release.json\n";
    $data = json_decode($jsonContent, true);
    echo "Version: " . ($data['version'] ?? 'unknown') . " Code: " . ($data['code'] ?? 'unknown') . "\n";
} else echo "❌ Failed app_release.json\n";
echo "\n=== Done ===\nCheck /api/v1/app/check-update?platform=android should show 4.0.80\n</pre><h2>✅ Update Complete</h2>";
?>

<?php
// mirror_simple.php - Simple APK mirror without GitHub API
// Call: https://vpbotn.ir/mirror_simple.php?key=cpanel_cron

$key = $_GET['key'] ?? '';
if ($key !== 'cpanel_cron') die('Unauthorized');

$root = __DIR__;
$files = [
    'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.12/Connectix-Android-ARM64.apk' => 'Connectix-ARM64-v8a.apk',
    'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.12/Connectix-Android-Universal.apk' => 'Connectix-Universal.apk',
];

echo "<h2>Simple Mirror v4.0.12</h2>";
echo date('Y-m-d H:i:s') . "<br><br>";

foreach ($files as $url => $localName) {
    $localPath = $root . '/' . $localName;
    echo "Downloading $localName from $url ...<br>";
    flush();
    
    $ch = curl_init($url);
    $fp = fopen($localPath . '.part', 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android) ConnectixMirror',
        CURLOPT_HTTPHEADER => ['Accept: application/octet-stream'],
    ]);
    $ok = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    
    $size = is_file($localPath . '.part') ? filesize($localPath . '.part') : 0;
    echo "HTTP: $http, Size: " . round($size/1024/1024,1) . " MB, Error: $err<br>";
    
    if ($http == 200 && $size > 1024*1024) {
        rename($localPath . '.part', $localPath);
        chmod($localPath, 0644);
        echo "✅ Saved $localName<br><br>";
        
        // Also copy to contax if exists
        $contaxPath = $root . '/contax/' . $localName;
        if (is_dir($root . '/contax')) {
            @copy($localPath, $contaxPath);
            echo "Copied to contax/$localName<br><br>";
        }
    } else {
        @unlink($localPath . '.part');
        echo "❌ Failed<br><br>";
    }
}

echo "Done. Files at root:<br>";
foreach (['Connectix-ARM64-v8a.apk', 'Connectix-Universal.apk'] as $n) {
    $p = $root . '/' . $n;
    echo "$n : " . (is_file($p) ? round(filesize($p)/1024/1024,1) . " MB" : "missing") . "<br>";
}

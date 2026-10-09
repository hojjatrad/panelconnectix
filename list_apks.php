<?php
$files = glob(__DIR__ . '/Connectix*.apk');
echo "Found " . count($files) . " APK files:\n";
foreach ($files as $f) {
    echo basename($f) . " - " . filesize($f) . " bytes - " . date('Y-m-d H:i:s', filemtime($f)) . "\n";
}
echo "\nChecking download_apk.php map:\n";
$map = [
    'arm64' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'universal' => __DIR__ . '/Connectix-Universal.apk',
];
foreach ($map as $k=>$p) {
    echo "$k => $p exists: " . (file_exists($p) ? 'YES '.filesize($p) : 'NO') . "\n";
}
echo "\nApp release json:\n";
echo file_get_contents(__DIR__ . '/app_release.json');

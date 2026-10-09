<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== LIST ROOT ===\n";
$dir = __DIR__;
$files = scandir($dir);
foreach ($files as $f) {
    if (strpos($f, 'fix_44') !== false) {
        echo "$f - " . filesize($dir.'/'.$f) . " bytes - " . date('Y-m-d H:i:s', filemtime($dir.'/'.$f)) . "\n";
    }
}
echo "\n=== ALL FIX_*.PHP ===\n";
foreach (glob($dir.'/fix_*.php') as $f) {
    echo basename($f) . " - " . filesize($f) . "\n";
}
echo "\n=== CHECK fix_440_simple.php EXISTS ===\n";
echo file_exists($dir.'/fix_440_simple.php') ? "YES exists\n" : "NO not exists\n";
if (file_exists($dir.'/fix_440_simple.php')) {
    echo "Content first 200 chars:\n";
    echo substr(file_get_contents($dir.'/fix_440_simple.php'),0,500) . "\n";
}

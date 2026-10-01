<?php
header('Content-Type: text/plain; charset=utf-8');
$file = __DIR__.'/core/Performance.php';
echo "Before invalidate MD5: ".md5_file($file)."\n";
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($file, true);
    echo "Invalidated Performance.php\n";
}
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Reset OPcache\n";
}

require_once __DIR__.'/core/Database.php';
require_once __DIR__.'/core/Setting.php';
require_once __DIR__.'/core/Cache.php';
require_once __DIR__.'/core/Performance.php';

echo "Performance.php loaded\n";
$ref = new ReflectionClass('Performance');
echo "File: ".$ref->getFileName()."\n";
$source = file_get_contents($ref->getFileName());
echo "Contains via main PDO in loaded file: ".(strpos($source,'via main PDO')!==false?'YES':'NO')."\n";

echo "\n--- Running warmupCache ---\n";
$result = Performance::warmupCache();
foreach ($result['messages'] as $msg) {
    echo $msg."\n";
}

echo "\n--- Running getStats ---\n";
$stats = Performance::getStats();
print_r($stats);

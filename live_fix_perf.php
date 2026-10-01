<?php
// Live fix - directly overwrite Performance.php with latest from GitHub raw
header('Content-Type: text/plain; charset=utf-8');
$rawUrl = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/core/Performance.php?cb='.time().rand(1000,9999);
$ch = curl_init($rawUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => ['Cache-Control: no-cache']
]);
$code = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http\n";
echo "Length: ".strlen($code)."\n";
if (strpos($code, 'via main PDO') === false) {
    echo "ERROR: code doesn't contain new marker\n";
    echo substr($code,0,500);
    exit;
}

$target = __DIR__ . '/core/Performance.php';
$backup = $target . '.bak.' . time();
@copy($target, $backup);
file_put_contents($target, $code);
echo "Written to $target, backup $backup\n";

// Verify
$verify = file_get_contents($target);
echo "Verify contains via main PDO: ".(strpos($verify,'via main PDO')!==false?'YES':'NO')."\n";

// Also fix Database.php to ensure buffered queries
$dbFile = __DIR__ . '/core/Database.php';
$dbCode = file_get_contents($dbFile);
if (strpos($dbCode, 'MYSQL_ATTR_USE_BUFFERED_QUERY') === false) {
    echo "Fixing Database.php to add buffered query...\n";
    $dbCode = str_replace(
        "PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,",
        "PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,",
        $dbCode
    );
    file_put_contents($dbFile, $dbCode);
    echo "Database.php fixed\n";
}

// Clear cache
if (is_dir(__DIR__ . '/cache')) {
    $files = glob(__DIR__ . '/cache/*');
    foreach ($files as $f) {
        if (is_file($f)) @unlink($f);
    }
    echo "Cache cleared\n";
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "OPcache reset\n";
}

echo "DONE - now run optimize_performance.php\n";

<?php
header('Content-Type: text/plain; charset=utf-8');
$files = [
    'core/Performance.php',
    'optimize_performance.php',
    'core/Database.php',
    'core/Cache.php',
    'core/Updater.php'
];
foreach ($files as $rel) {
    $rawUrl = "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/{$rel}?cb=".time().rand(1000,9999);
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
    if ($http !== 200 || strlen($code) < 100) {
        echo "FAIL $rel HTTP $http len ".strlen($code)."\n";
        continue;
    }
    $target = __DIR__ . '/' . $rel;
    if (is_file($target)) {
        @copy($target, $target.'.bak.'.time());
    }
    file_put_contents($target, $code);
    echo "OK $rel len ".strlen($code)." MD5 ".md5($code)."\n";
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }
}
if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "OPcache reset\n";
}
echo "DONE\n";

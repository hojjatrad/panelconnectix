<?php
// v4 - self updater for fix_361_now.php and fix_400_now.php
$files = [
    'fix_361_now.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php',
    'set_app_version_400.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_400.php',
    'fix_400_now.php' => 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_400_now.php',
];

foreach ($files as $local => $url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data && strlen($data) > 500) {
        file_put_contents(__DIR__ . '/' . $local, $data);
        echo "Updated $local ".strlen($data)." bytes\n";
    } else {
        echo "Failed $local code $code\n";
    }
}
if (function_exists('opcache_reset')) @opcache_reset();
echo "DONE - now run fix_361_now.php or set_app_version_400.php\n";

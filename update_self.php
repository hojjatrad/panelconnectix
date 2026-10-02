<?php
// v4.3 - self updater with jsDelivr bypass for CDN cache
function fetchRaw($url) {
    $ch = curl_init($url.'?t='.time().rand(1000,9999).'&cb='.rand(100000,999999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache', 'Pragma: no-cache', 'User-Agent: Connectix-Updater-Bypass']);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && strlen($data) > 500) ? $data : false;
}

$files = [
    'fix_361_now.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/fix_361_now.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php',
    ],
    'set_app_version_361.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/set_app_version_361.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_361.php',
    ],
    'set_app_version_400.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/set_app_version_400.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_400.php',
    ],
    'controllers/ApiControllerV2.php' => [
        'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/controllers/ApiControllerV2.php',
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/ApiControllerV2.php',
    ],
];

foreach ($files as $local => $urls) {
    foreach ((array)$urls as $u) {
        $data = fetchRaw($u);
        if ($data) {
            $path = __DIR__ . '/' . $local;
            if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
            file_put_contents($path, $data);
            echo "Updated $local ".strlen($data)." bytes from ".parse_url($u, PHP_URL_HOST)."\n";
            break;
        } else {
            echo "Failed $local from $u\n";
        }
    }
}
if (function_exists('opcache_reset')) @opcache_reset();
echo "DONE - now run fix_361_now.php\n";

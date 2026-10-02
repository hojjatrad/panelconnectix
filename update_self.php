<?php
// v4.1 - self updater with multi-URL fallback to bypass CDN cache
$files = [
    'fix_361_now.php' => [
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php',
        'https://github.com/hojjatrad/panelconnectix/raw/main/fix_361_now.php',
        'https://codeload.github.com/hojjatrad/panelconnectix/zip/main',
    ],
    'set_app_version_400.php' => [
        'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_400.php',
    ],
];

foreach ($files as $local => $urls) {
    $success = false;
    foreach ((array)$urls as $baseUrl) {
        if (str_ends_with($baseUrl, '.zip')) continue; // skip zip for now
        $url = $baseUrl . '?t=' . time() . rand(1000,9999) . '&cb=' . rand(100000,999999);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache', 'Pragma: no-cache', 'User-Agent: Connectix-Updater-Bypass']);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data && strlen($data) > 500 && $code === 200) {
            // Check if it's the ultra minimal version (contains ULTRA FAST)
            if ($local === 'fix_361_now.php' && !str_contains($data, 'ULTRA')) {
                echo "Fetched $local but not ULTRA version (size ".strlen($data)."), trying next URL...\n";
                continue;
            }
            file_put_contents(__DIR__ . '/' . $local, $data);
            echo "Updated $local ".strlen($data)." bytes from $baseUrl\n";
            $success = true;
            break;
        } else {
            echo "Failed $local from $baseUrl code $code\n";
        }
    }
    if (!$success) echo "All URLs failed for $local\n";
}
if (function_exists('opcache_reset')) @opcache_reset();
echo "DONE - now run fix_361_now.php\n";

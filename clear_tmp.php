<?php
header('Content-Type: text/plain; charset=utf-8');
$dirs = [__DIR__.'/data/tmp', __DIR__.'/data/sessions', sys_get_temp_dir()];
foreach ($dirs as $d) {
    echo "Checking $d\n";
    if (!is_dir($d)) { echo "  Not dir\n"; continue; }
    $files = glob($d.'/*');
    $count = 0;
    $freed = 0;
    foreach ($files as $f) {
        if (is_file($f)) {
            $size = filesize($f);
            // Delete files older than 1 hour or matching browser_update pattern
            if (strpos(basename($f), 'browser_update') !== false || strpos(basename($f), 'connectix_update') !== false || filemtime($f) < time()-3600) {
                $freed += $size;
                @unlink($f);
                $count++;
            }
        }
    }
    echo "  Deleted $count files, freed " . round($freed/1024/1024,2) . " MB\n";
    echo "  Free space: " . round(disk_free_space($d)/1024/1024,2) . " MB\n";
}
echo "\n=== Fix 440 file ===\n";
echo file_exists(__DIR__.'/fix_440_simple.php') ? "exists ".filesize(__DIR__.'/fix_440_simple.php')." bytes\n" : "not exists\n";
// Try to fetch new fix_440 from Main Server raw and overwrite
$url = 'https://raw.githubusercontentusercontent.com/hojjatrad/panelconnectix/main/fix_440_simple.php';
echo "Fetching new fix_440 from $url\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$data = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code len ".strlen($data)."\n";
if ($data && strlen($data)>1000) {
    file_put_contents(__DIR__.'/fix_440_simple.php', $data);
    echo "Overwritten fix_440_simple.php with new version len ".strlen($data)."\n";
}
echo "Done\n";

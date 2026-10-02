<?php
$url = "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php?t=".time();
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$data = curl_exec($ch);
curl_close($ch);
if ($data && strlen($data) > 1000) {
    file_put_contents(__DIR__ . '/fix_361_now.php', $data);
    echo "Updated fix_361_now.php ".strlen($data)." bytes\n";
    echo substr($data,0,200)."\n";
} else {
    echo "Failed to fetch\n";
}
if (function_exists('opcache_reset')) @opcache_reset();
@touch(__DIR__ . '/.deploy_stamp');

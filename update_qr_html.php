<?php
header('Content-Type: text/plain; charset=utf-8');
$url = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/qr_download.html';
echo "Fetching $url\n";
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
    file_put_contents(__DIR__.'/qr_download.html', $data);
    echo "Written qr_download.html ".strlen($data)." bytes\n";
} else {
    echo "Failed\n";
}

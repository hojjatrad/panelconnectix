<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();
$srv = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' ORDER BY id DESC LIMIT 1")->fetch();
$token = $srv['api_token'];
$baseUrl = 'https://api.connectix.vip';

function callApi($baseUrl, $endpoint, $method, $data, $token) {
    $ch = curl_init($baseUrl.$endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
        'X-Api-Token: '.$token,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code, 'raw'=>$res, 'json'=>json_decode($res,true)];
}

echo "<h2>🧹 پاکسازی یوزرهای تستی VIP</h2>";

// List all clients
$r = callApi($baseUrl, '/v1/seller/clients?page=1&recordPerPage=100', 'GET', null, $token);
$clients = $r['json']['clients']['data'] ?? [];

$toDelete = [];
foreach ($clients as $c) {
    $name = $c['name'] ?? '';
    $username = $c['username'] ?? '';
    // Test users have name containing Test or username starting with t or 8cl55hfm etc
    if (stripos($name,'Test')!==false || stripos($name,'تست')!==false || 
        in_array($c['id'], ['7f7abee6-dee4-489a-9b62-e944348c37ec','c0404323-05a8-4f68-8823-92c68ab3dd89']) ||
        $username === '8cl55hfm' || $username === '8clukanm') {
        $toDelete[] = $c;
    }
}

echo "<p>یوزرهای تستی یافت شده: ".count($toDelete)."</p>";
foreach ($toDelete as $c) {
    echo "<p>{$c['id']} - {$c['username']} - {$c['name']} - {$c['plan_name']}</p>";
    // Try delete
    $id = $c['id'];
    $endpoints = [
        "/v1/seller/clients/$id" => 'DELETE',
        "/v1/seller/clients/$id/delete" => 'DELETE',
        "/v1/seller/clients/delete/$id" => 'DELETE',
    ];
    foreach ($endpoints as $ep => $method) {
        $dr = callApi($baseUrl, $ep, $method, null, $token);
        echo "<p>  $method $ep => HTTP {$dr['code']} - ".htmlspecialchars(substr($dr['raw'],0,500))."</p>";
        if ($dr['code']>=200 && $dr['code']<300) {
            echo "<p style='color:green'>  ✅ حذف شد</p>";
            break;
        }
    }
}

echo "<hr><p>تمام</p>";

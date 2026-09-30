<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h2>🔄 تست تمدید و ویرایش VIP</h2>";

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

$meta = callApi($baseUrl, '/v1/seller/clients/meta-data', 'GET', null, $token);
$planId = $meta['json']['seller_plans'][0]['id'] ?? '';
$groupId = $meta['json']['groups'][0]['id'] ?? '';

// Create test user
echo "<h3>ساخت یوزر تستی</h3>";
$r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', [
    'name' => 'Renew Test',
    'username' => 'ren'.time().rand(10,99),
    'password' => 'Ab1234',
    'plan_id' => $planId,
    'group_id' => $groupId,
], $token);
echo "<p>HTTP {$r['code']} - ".htmlspecialchars($r['raw'])."</p>";
$clientId = $r['json']['client_id'] ?? '';
$text = $r['json']['text_to_copy'] ?? '';
preg_match('/username:\s*`([^`]+)`/', $text, $m);
$actualUsername = $m[1] ?? '';
echo "<p>ID=$clientId Username=$actualUsername</p>";

if (!$clientId) die("ساخت ناموفق");

echo "<h3>تست endpointهای تمدید/ویرایش</h3>";

$tests = [
    // Renew / extend
    ["POST", "/v1/seller/clients/renew", ['id'=>$clientId, 'plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/extend", ['id'=>$clientId, 'plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/renewal", ['id'=>$clientId, 'plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/charge", ['id'=>$clientId, 'plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/renew", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/extend", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/renewal", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/charge", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/renew", []],
    // Update
    ["POST", "/v1/seller/clients/update", ['id'=>$clientId, 'name'=>'Updated Name']],
    ["POST", "/v1/seller/clients/$clientId/update", ['name'=>'Updated Name']],
    ["PUT", "/v1/seller/clients/$clientId", ['name'=>'Updated Name']],
    ["PATCH", "/v1/seller/clients/$clientId", ['name'=>'Updated Name']],
    // Toggle active
    ["POST", "/v1/seller/clients/$clientId/toggle", ['is_active'=>0]],
    ["POST", "/v1/seller/clients/toggle", ['id'=>$clientId, 'is_active'=>0]],
    ["POST", "/v1/seller/clients/status", ['id'=>$clientId, 'is_active'=>0]],
    // Try to get edit form
    ["GET", "/v1/seller/clients/$clientId/edit"],
    ["GET", "/v1/seller/clients/$clientId"],
];

foreach ($tests as $t) {
    [$method, $ep, $data] = $t;
    echo "<h4>$method $ep ".($data ? json_encode($data, JSON_UNESCAPED_UNICODE) : "")."</h4>";
    $res = callApi($baseUrl, $ep, $method, $data, $token);
    $color = ($res['code']>=200 && $res['code']<300) ? 'green' : 'red';
    echo "<p style='color:$color'>HTTP {$res['code']}</p>";
    echo "<pre style='background:#111;color:#0ff;padding:8px;max-height:300px;overflow:auto;direction:ltr'>".htmlspecialchars(substr($res['raw'],0,2000))."</pre>";
    if ($res['code']>=200 && $res['code']<300) {
        echo "<p style='background:green;color:white;padding:8px'>✅ موفق!</p>";
    }
    usleep(400000);
}

echo "<h3>حذف یوزر تستی</h3>";
$del = callApi($baseUrl, '/v1/seller/clients/delete', 'POST', ['id'=>$clientId], $token);
echo "<p>HTTP {$del['code']} - ".htmlspecialchars($del['raw'])."</p>";

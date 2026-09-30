<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h2>🗑️ تست endpoint حذف یوزر VIP</h2>";

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

// First create a user to delete
echo "<h3>1. ساخت یوزر تستی برای حذف</h3>";
$meta = callApi($baseUrl, '/v1/seller/clients/meta-data', 'GET', null, $token);
$planId = $meta['json']['seller_plans'][0]['id'] ?? '';
$groupId = $meta['json']['groups'][0]['id'] ?? '';

$createPayload = [
    'name' => 'Delete Test',
    'username' => 'del'.time().rand(10,99),
    'password' => 'Ab1234',
    'plan_id' => $planId,
    'group_id' => $groupId,
];

$r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', $createPayload, $token);
echo "<p>HTTP {$r['code']} - ".htmlspecialchars($r['raw'])."</p>";

if ($r['code']<200 || $r['code']>=300) {
    die("ساخت ناموفق");
}

$clientId = $r['json']['client_id'] ?? '';
$text = $r['json']['text_to_copy'] ?? '';
preg_match('/username:\s*`([^`]+)`/', $text, $m);
$actualUsername = $m[1] ?? '';

echo "<p>ساخته شد: ID=$clientId Username=$actualUsername</p>";

echo "<h3>2. تست endpointهای حذف</h3>";

$endpointsToTest = [
    // DELETE methods
    ["DELETE", "/v1/seller/clients/$clientId"],
    ["DELETE", "/v1/seller/clients/$clientId/delete"],
    ["DELETE", "/v1/seller/clients/delete/$clientId"],
    ["DELETE", "/v1/seller/clients/$clientId/destroy"],
    ["DELETE", "/v1/seller/clients/destroy/$clientId"],
    ["DELETE", "/v1/seller/clients/$clientId/remove"],
    ["DELETE", "/v1/seller/clients/remove/$clientId"],
    ["DELETE", "/v1/seller/client/$clientId"],
    ["DELETE", "/v1/seller/client/$clientId/delete"],
    // POST methods
    ["POST", "/v1/seller/clients/$clientId/delete"],
    ["POST", "/v1/seller/clients/$clientId/destroy"],
    ["POST", "/v1/seller/clients/$clientId/remove"],
    ["POST", "/v1/seller/clients/delete"],
    ["POST", "/v1/seller/clients/destroy"],
    ["POST", "/v1/seller/clients/remove"],
    ["POST", "/v1/seller/clients/$clientId"],
    // With body containing id
    ["POST", "/v1/seller/clients/delete", ['id'=>$clientId]],
    ["POST", "/v1/seller/clients/destroy", ['id'=>$clientId]],
    ["POST", "/v1/seller/clients/remove", ['id'=>$clientId]],
    ["POST", "/v1/seller/clients/delete", ['client_id'=>$clientId]],
    ["POST", "/v1/seller/clients/destroy", ['client_id'=>$clientId]],
    ["POST", "/v1/seller/clients/delete", ['client_ids'=>[$clientId]]],
    ["DELETE", "/v1/seller/clients", ['id'=>$clientId]],
    // Try with username instead of ID
    ["DELETE", "/v1/seller/clients/$actualUsername"],
    ["POST", "/v1/seller/clients/$actualUsername/delete"],
    // Try bulk delete
    ["POST", "/v1/seller/clients/bulk-delete", ['ids'=>[$clientId]]],
    ["POST", "/v1/seller/clients/bulk_delete", ['ids'=>[$clientId]]],
    // Try update is_active = 0 as alternative to delete
    ["POST", "/v1/seller/clients/$clientId/update", ['is_active'=>0]],
    ["PUT", "/v1/seller/clients/$clientId", ['is_active'=>0]],
    // Try renew/extend endpoints to understand pattern
    ["GET", "/v1/seller/clients/$clientId"],
    ["GET", "/v1/seller/clients/$clientId/edit"],
];

foreach ($endpointsToTest as $test) {
    [$method, $ep, $data] = array_pad($test, 3, null);
    $label = "$method $ep" . ($data ? " + ".json_encode($data, JSON_UNESCAPED_UNICODE) : "");
    echo "<h4>$label</h4>";
    $res = callApi($baseUrl, $ep, $method, $data, $token);
    $color = ($res['code']>=200 && $res['code']<300) ? 'green' : 'red';
    echo "<p style='color:$color'>HTTP {$res['code']}</p>";
    echo "<pre style='background:#111;color:#0ff;padding:8px;max-height:200px;overflow:auto;direction:ltr'>".htmlspecialchars(substr($res['raw'],0,2000))."</pre>";
    if ($res['code']>=200 && $res['code']<300) {
        echo "<p style='background:green;color:white;padding:10px'>✅ موفق! Endpoint حذف: $method $ep</p>";
        // Verify deletion
        $check = callApi($baseUrl, "/v1/seller/clients?page=1&recordPerPage=100", 'GET', null, $token);
        $found = false;
        $clients = $check['json']['clients']['data'] ?? [];
        foreach ($clients as $c) {
            if ($c['id'] === $clientId) { $found = true; break; }
        }
        echo "<p>هنوز در لیست هست؟ ".($found ? "بله - حذف نشد" : "نه - حذف شد ✅")."</p>";
        break;
    }
    usleep(400000);
}

echo "<hr><h3>3. تست endpointهای تمدید و ویرایش (برای آینده)</h3>";
$renewTests = [
    ["POST", "/v1/seller/clients/$clientId/renew", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/extend", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/renewal", ['plan_id'=>$planId]],
    ["POST", "/v1/seller/clients/$clientId/charge", ['plan_id'=>$planId]],
];

foreach ($renewTests as $test) {
    [$method, $ep, $data] = $test;
    echo "<h4>$method $ep</h4>";
    $res = callApi($baseUrl, $ep, $method, $data, $token);
    echo "<p>HTTP {$res['code']} - ".htmlspecialchars(substr($res['raw'],0,1000))."</p>";
    usleep(400000);
}

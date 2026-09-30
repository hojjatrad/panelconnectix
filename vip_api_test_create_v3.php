<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h2>🚀 تست v3 - پسورد max 8 کاراکتر</h2>";

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
$plans = $meta['json']['seller_plans'] ?? [];
$groups = $meta['json']['groups'] ?? [];
$planId = $plans[0]['id'] ?? '';
$groupId = $groups[0]['id'] ?? '';

echo "Plan: $planId Group: $groupId<br>";

$tests = [
    [
        'label' => 'Test 1 - password 5 chars (مثل نمونه‌های موجود qy8ff)',
        'payload' => [
            'name' => 'Test API',
            'username' => 't'.time().rand(10,99),
            'password' => 'a1b2c',
            'plan_id' => $planId,
            'group_id' => $groupId,
        ]
    ],
    [
        'label' => 'Test 2 - password 6 chars',
        'payload' => [
            'name' => 'Test API 2',
            'username' => 't2'.time().rand(10,99),
            'password' => 'Ab1234',
            'plan_id' => $planId,
            'group_id' => $groupId,
        ]
    ],
    [
        'label' => 'Test 3 - password 8 chars max',
        'payload' => [
            'name' => 'Test API 3',
            'username' => 't3'.time().rand(10,99),
            'password' => 'Ab123456',
            'plan_id' => $planId,
            'group_id' => $groupId,
        ]
    ],
    [
        'label' => 'Test 4 - بدون group_id (شاید optional)',
        'payload' => [
            'name' => 'Test API 4',
            'username' => 't4'.time().rand(10,99),
            'password' => 'Ab1234',
            'plan_id' => $planId,
        ]
    ],
    [
        'label' => 'Test 5 - با Economic group (affb6513-cd8d-4dad-b04e-02007f8c2a51)',
        'payload' => [
            'name' => 'Test Economic',
            'username' => 't5'.time().rand(10,99),
            'password' => 'Ab1234',
            'plan_id' => $planId,
            'group_id' => 'affb6513-cd8d-4dad-b04e-02007f8c2a51',
        ]
    ],
    [
        'label' => 'Test 6 - پلن (4x) 5GB-1M + Economic با گروه Economic',
        'payload' => [
            'name' => 'Test 5GB',
            'username' => 't6'.time().rand(10,99),
            'password' => 'Ab12Cd',
            'plan_id' => '8a84ecb0-1641-4642-9e37-aa08bf126a17',
            'group_id' => 'affb6513-cd8d-4dad-b04e-02007f8c2a51',
        ]
    ],
];

$created = [];
foreach ($tests as $t) {
    echo "<hr><h3>{$t['label']}</h3>";
    echo "<pre style='background:#333;color:#ff0;padding:8px;direction:ltr'>".htmlspecialchars(json_encode($t['payload'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";
    $r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', $t['payload'], $token);
    $color = ($r['code']>=200 && $r['code']<300) ? 'green' : 'red';
    echo "<p style='color:$color'>HTTP {$r['code']}</p>";
    echo "<pre style='background:#111;color:#0ff;padding:10px;max-height:500px;overflow:auto;direction:ltr'>".htmlspecialchars($r['raw'])."</pre>";
    if ($r['code']>=200 && $r['code']<300) {
        $id = $r['json']['id'] ?? $r['json']['data']['id'] ?? $r['json']['client']['id'] ?? null;
        echo "<p style='background:green;color:white;padding:10px'>✅ ساخته شد! ID: $id</p>";
        $created[] = $id;
        break;
    }
    usleep(600000);
}

if (!empty($created)) {
    echo "<h2 style='color:green'>🎉 موفق! حالا driver رو آپدیت می‌کنم</h2>";
} else {
    echo "<h3 style='color:red'>هنوز نشد - بذار همه خطاهای validation رو ببینیم</h3>";
    // Try with empty name etc
    $r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', ['name'=>'A','username'=>'u'.time(),'password'=>'Ab123','plan_id'=>$planId,'group_id'=>$groupId], $token);
    echo "<pre>".htmlspecialchars($r['raw'])."</pre>";
}

// Also try to check if there's a GET for single client to understand structure
echo "<hr><h3>بررسی ساختار کلاینت برای فهم فیلدها</h3>";
$list = callApi($baseUrl, '/v1/seller/clients?page=1&recordPerPage=1', 'GET', null, $token);
echo "<pre style='background:#222;color:#fff;padding:10px;direction:ltr'>".htmlspecialchars(substr($list['raw'],0,3000))."</pre>";

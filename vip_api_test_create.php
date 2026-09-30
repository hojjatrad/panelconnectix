<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h2>🚀 تست ساخت یوزر VIP - Endpoint درست پیدا شد: POST /v1/seller/clients/store</h2>";
echo "<p>قبلاً 405 بود، الان 422 میگه password لازمه = دسترسی باز شده!</p>";

$pdo = Database::getConnection();
$srv = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' ORDER BY id DESC LIMIT 1")->fetch();
if (!$srv) die("No server");

$token = $srv['api_token'];
$baseUrl = 'https://api.connectix.vip';

function callApi($baseUrl, $endpoint, $method, $data, $token) {
    $url = $baseUrl . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
        'X-Api-Token: '.$token,
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code, 'raw'=>$res, 'json'=>json_decode($res,true)];
}

// Get meta-data again
$metaRes = callApi($baseUrl, '/v1/seller/clients/meta-data', 'GET', null, $token);
$plans = $metaRes['json']['seller_plans'] ?? [];
$groups = $metaRes['json']['groups'] ?? [];

$planId = $plans[0]['id'] ?? null;
$groupId = $groups[0]['id'] ?? null;

echo "<p>Plan ID: $planId - Group ID: $groupId</p>";

$tests = [];

// Test 1: minimal with password
$tests[] = [
    'label' => 'Test 1 - name + username + password + plan_id + group_id',
    'payload' => [
        'name' => 'Test API User',
        'username' => 'test_'.time().rand(10,99),
        'password' => 'Test1234!',
        'plan_id' => $planId,
        'group_id' => $groupId,
    ]
];

// Test 2: with email
$tests[] = [
    'label' => 'Test 2 - add email',
    'payload' => [
        'name' => 'Test API User 2',
        'username' => 'test2_'.time().rand(10,99),
        'password' => 'Test1234!',
        'plan_id' => $planId,
        'group_id' => $groupId,
        'email' => 'test'.time().'@test.com',
    ]
];

// Test 3: try seller_plan_id instead of plan_id
$tests[] = [
    'label' => 'Test 3 - seller_plan_id',
    'payload' => [
        'name' => 'Test API User 3',
        'username' => 'test3_'.time().rand(10,99),
        'password' => 'Test1234!',
        'seller_plan_id' => $planId,
        'group_id' => $groupId,
    ]
];

// Test 4: try plan as string title?
$tests[] = [
    'label' => 'Test 4 - plan (not plan_id)',
    'payload' => [
        'name' => 'Test API User 4',
        'username' => 'test4_'.time().rand(10,99),
        'password' => 'Test1234!',
        'plan' => $planId,
        'group_id' => $groupId,
    ]
];

// Test 5: try without username (maybe auto generates)
$tests[] = [
    'label' => 'Test 5 - without username, with password',
    'payload' => [
        'name' => 'Test API User 5',
        'password' => 'Test1234!',
        'plan_id' => $planId,
        'group_id' => $groupId,
    ]
];

// Test 6: try with is_active
$tests[] = [
    'label' => 'Test 6 - full with is_active + traffic',
    'payload' => [
        'name' => 'Test API User 6',
        'username' => 'test6_'.time().rand(10,99),
        'password' => 'Test1234!',
        'plan_id' => $planId,
        'group_id' => $groupId,
        'is_active' => 1,
        'is_child_protection_enabled' => false,
    ]
];

// Test 7: try with group_name instead of group_id
$tests[] = [
    'label' => 'Test 7 - group_name',
    'payload' => [
        'name' => 'Test API User 7',
        'username' => 'test7_'.time().rand(10,99),
        'password' => 'Test1234!',
        'plan_id' => $planId,
        'group_name' => $groups[0]['name'] ?? 'default',
    ]
];

// Test 8: try minimal Laravel style - maybe need confirmation?
$tests[] = [
    'label' => 'Test 8 - password_confirmation',
    'payload' => [
        'name' => 'Test API User 8',
        'username' => 'test8_'.time().rand(10,99),
        'password' => 'Test1234!',
        'password_confirmation' => 'Test1234!',
        'plan_id' => $planId,
        'group_id' => $groupId,
    ]
];

$createdUsers = [];

foreach ($tests as $t) {
    echo "<hr><h3>{$t['label']}</h3>";
    echo "<pre style='background:#333;color:#ff0;padding:8px;direction:ltr'>".htmlspecialchars(json_encode($t['payload'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";
    $r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', $t['payload'], $token);
    $color = ($r['code']>=200 && $r['code']<300) ? 'green' : ($r['code']==422 ? 'orange' : 'red');
    echo "<p style='color:$color'>HTTP {$r['code']}</p>";
    echo "<pre style='background:#111;color:#0ff;padding:10px;max-height:400px;overflow:auto;direction:ltr'>".htmlspecialchars(substr($r['raw'],0,4000))."</pre>";
    
    if ($r['code']>=200 && $r['code']<300) {
        $id = $r['json']['id'] ?? $r['json']['data']['id'] ?? $r['json']['client']['id'] ?? null;
        $uname = $r['json']['username'] ?? $r['json']['data']['username'] ?? $t['payload']['username'] ?? 'unknown';
        echo "<p style='background:green;color:white;padding:10px'>✅ ساخته شد! ID: $id Username: $uname</p>";
        $createdUsers[] = ['id'=>$id, 'username'=>$uname, 'raw'=>$r['json']];
        // Don't delete yet, keep for inspection, but list them
        break; // stop on first success to avoid spamming
    }
    usleep(700000);
}

if (!empty($createdUsers)) {
    echo "<hr><h2 style='color:green'>🎉 موفق! یوزر ساخته شد - حالا باید حذف کنیم تا تمیز بمونه</h2>";
    foreach ($createdUsers as $cu) {
        echo "<p>یوزر: {$cu['username']} ID: {$cu['id']}</p>";
        // Try delete
        if (!empty($cu['id'])) {
            $delEndpoints = [
                "/v1/seller/clients/{$cu['id']}",
                "/v1/seller/clients/{$cu['id']}/delete",
                "/v1/seller/clients/delete/{$cu['id']}",
            ];
            foreach ($delEndpoints as $delEp) {
                $dr = callApi($baseUrl, $delEp, 'DELETE', null, $token);
                echo "<p>DELETE $delEp => HTTP {$dr['code']} - ".htmlspecialchars(substr($dr['raw'],0,1000))."</p>";
                if ($dr['code']>=200 && $dr['code']<300) {
                    echo "<p style='color:green'>✅ حذف شد</p>";
                    break;
                }
            }
            // Also try POST delete
            $dr = callApi($baseUrl, "/v1/seller/clients/{$cu['id']}/destroy", 'POST', null, $token);
            echo "<p>POST destroy => HTTP {$dr['code']} - ".htmlspecialchars(substr($dr['raw'],0,500))."</p>";
        }
    }
} else {
    echo "<hr><h3 style='color:red'>هنوز ساخته نشد - باید فیلدهای لازم رو پیدا کنیم</h3>";
    echo "<p>پیشنهاد: لاگ validation errorها رو ببین، معمولاً Laravel همه فیلدهای missing رو میگه</p>";
}

// Also try to discover other endpoints via trying to GET /v1/seller/clients/store (should say GET not supported and list allowed?)
echo "<hr><h3>🔍 بررسی endpointهای دیگر مرتبط با ساخت</h3>";
$otherEndpoints = [
    '/v1/seller/clients/create',
    '/v1/seller/clients/store',
    '/v1/seller/clients',
    '/v1/seller/client/store',
    '/v1/seller/client/create',
];

foreach ($otherEndpoints as $ep) {
    $r = callApi($baseUrl, $ep, 'GET', null, $token);
    echo "<p>GET $ep => HTTP {$r['code']} - ".htmlspecialchars(substr($r['raw'],0,500))."</p>";
}

// Try to get API docs via trying POST to /v1/seller/clients/store with empty payload to see all required fields
echo "<hr><h3>📋 همه فیلدهای required با payload خالی</h3>";
$r = callApi($baseUrl, '/v1/seller/clients/store', 'POST', [], $token);
echo "<pre style='background:#111;color:#f80;padding:10px;direction:ltr'>".htmlspecialchars($r['raw'])."</pre>";

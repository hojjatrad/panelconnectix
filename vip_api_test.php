<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/drivers/ConnectixSellerDriver.php';

echo "<h2>🔍 بررسی کلید API سرور VIP - آیا میتونه یوزر بسازه؟</h2>";
echo "<p>زمان: ".date('Y-m-d H:i:s')." - ".(function_exists('jdate') ? jdate('Y-m-d H:i:s') : '')."</p>";

$pdo = Database::getConnection();
$servers = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' OR api_url LIKE '%connectix.vip%' ORDER BY id DESC")->fetchAll();

if (empty($servers)) {
    echo "<p style='color:red'>❌ هیچ سرور Connectix Seller یافت نشد! برو سرورها > افزودن > Connectix Seller</p>";
    exit;
}

foreach ($servers as $srv) {
    echo "<hr><h3>سرور #{$srv['id']} - {$srv['name']} - {$srv['api_url']}</h3>";
    $token = $srv['api_token'] ?? '';
    $user = $srv['api_username'] ?? '';
    echo "Token len: ".strlen($token)." - ".htmlspecialchars(substr($token,0,15))."...".htmlspecialchars(substr($token,-5))."<br>";
    echo "User: ".htmlspecialchars($user)."<br>";
    echo "Active: {$srv['is_active']}<br>";

    $baseUrl = 'https://api.connectix.vip';
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer '.$token,
        'X-Api-Token: '.$token,
    ];

    function callApi($baseUrl, $endpoint, $method='GET', $data=null, $token) {
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
        $err = curl_error($ch);
        curl_close($ch);
        $json = json_decode($res, true);
        return ['code'=>$code, 'raw'=>$res, 'json'=>$json, 'error'=>$err, 'url'=>$url];
    }

    $endpoints = [
        'GET /v1/seller/seller-data' => ['/v1/seller/seller-data', 'GET'],
        'GET /v1/seller/clients?page=1&recordPerPage=2' => ['/v1/seller/clients?page=1&recordPerPage=2', 'GET'],
        'GET /v1/seller/clients/meta-data (پلن‌ها و گروه‌ها)' => ['/v1/seller/clients/meta-data', 'GET'],
        'GET /v1/seller/groups' => ['/v1/seller/groups', 'GET'],
        'GET /v1/seller/plans' => ['/v1/seller/plans', 'GET'],
        'GET /v1/seller/servers' => ['/v1/seller/servers', 'GET'],
        'GET /v1/seller/stats' => ['/v1/seller/stats', 'GET'],
        'GET /v1/seller/dashboard' => ['/v1/seller/dashboard', 'GET'],
    ];

    $metaData = null;
    foreach ($endpoints as $label => $info) {
        [$ep, $method] = $info;
        $r = callApi($baseUrl, $ep, $method, null, $token);
        $ok = ($r['code'] >=200 && $r['code'] <300) ? "✅" : "❌";
        echo "<h4>$ok $label - HTTP {$r['code']}</h4>";
        if ($r['error']) echo "<p style='color:red'>cURL: {$r['error']}</p>";
        $preview = substr($r['raw'],0,3000);
        echo "<pre style='background:#111;color:#0f0;padding:10px;max-height:400px;overflow:auto;direction:ltr;text-align:left'>".htmlspecialchars($preview)."</pre>";
        if (strpos($label,'meta-data')!==false && $r['json']) {
            $metaData = $r['json'];
        }
    }

    // Parse meta-data for plans
    if ($metaData) {
        echo "<h3>📦 پلن‌های قابل استفاده برای ساخت یوزر:</h3>";
        $plans = $metaData['seller_plans'] ?? $metaData['plans'] ?? $metaData['data']['seller_plans'] ?? [];
        $groups = $metaData['groups'] ?? $metaData['data']['groups'] ?? [];
        echo "<p>تعداد پلن: ".count($plans)." - تعداد گروه: ".count($groups)."</p>";
        echo "<pre style='background:#222;color:#fff;padding:10px;max-height:500px;overflow:auto;direction:ltr'>".htmlspecialchars(json_encode($plans, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))."</pre>";
        echo "<pre style='background:#222;color:#aaf;padding:10px;max-height:300px;overflow:auto;direction:ltr'>Groups: ".htmlspecialchars(json_encode($groups, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))."</pre>";

        // Try to create user - test permissions
        echo "<hr><h3>🧪 تست ساخت یوزر - بررسی دسترسی جدید</h3>";
        if (empty($plans)) {
            echo "<p style='color:orange'>⚠️ پلنی یافت نشد، نمی‌تونم تست ساخت کنم</p>";
        } else {
            $firstPlan = $plans[0] ?? null;
            $planId = $firstPlan['id'] ?? $firstPlan['plan_id'] ?? null;
            $firstGroup = $groups[0] ?? null;
            $groupId = $firstGroup['id'] ?? $firstGroup['group_id'] ?? null;

            echo "<p>پلن انتخابی برای تست: ".htmlspecialchars(json_encode($firstPlan, JSON_UNESCAPED_UNICODE))."</p>";
            echo "<p>گروه انتخابی: ".htmlspecialchars(json_encode($firstGroup, JSON_UNESCAPED_UNICODE))."</p>";

            // Different payload variants to try (common seller API patterns)
            $testUsername = 'test_api_'.time().rand(100,999);
            $payloads = [
                'Variant 1 - Simple' => [
                    'name' => 'تست API',
                    'username' => $testUsername,
                    'plan_id' => $planId,
                    'group_id' => $groupId,
                ],
                'Variant 2 - With group_name' => [
                    'name' => 'تست API',
                    'username' => $testUsername.'_2',
                    'plan_id' => $planId,
                    'group_id' => $groupId,
                    'group_name' => $firstGroup['name'] ?? null,
                ],
                'Variant 3 - Full' => [
                    'name' => 'تست API Connectix Panel',
                    'username' => $testUsername.'_3',
                    'plan_id' => $planId,
                    'group_id' => $groupId,
                    'is_active' => 1,
                ],
                'Variant 4 - seller_plans format' => [
                    'name' => 'Test User',
                    'username' => $testUsername.'_4',
                    'plan_id' => $planId,
                    'group_id' => $groupId,
                    'traffic' => 10,
                    'expire_days' => 30,
                ],
            ];

            // Also try alternative endpoints
            $createEndpoints = [
                '/v1/seller/clients',
                '/v1/seller/client',
                '/v1/seller/clients/create',
                '/v1/seller/clients/store',
                '/v1/seller/users',
                '/v1/seller/clients/add',
            ];

            $created = false;
            $createdId = null;
            $createdUsername = null;

            foreach ($createEndpoints as $createEp) {
                if ($created) break;
                foreach ($payloads as $vName => $payload) {
                    if ($created) break;
                    // Clean null values
                    $payload = array_filter($payload, fn($v)=> $v!==null);
                    echo "<h4>🔄 تلاش: POST $createEp - $vName</h4>";
                    echo "<pre style='background:#333;color:#ff0;padding:8px'>Payload: ".htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE))."</pre>";
                    $r = callApi($baseUrl, $createEp, 'POST', $payload, $token);
                    $ok = ($r['code'] >=200 && $r['code'] <300) ? "✅ موفق" : "❌ ناموفق";
                    echo "<p>$ok - HTTP {$r['code']}</p>";
                    echo "<pre style='background:#111;color:#0ff;padding:10px;max-height:300px;overflow:auto;direction:ltr'>".htmlspecialchars(substr($r['raw'],0,3000))."</pre>";

                    if ($r['code'] >=200 && $r['code'] <300) {
                        $created = true;
                        $createdId = $r['json']['id'] ?? $r['json']['data']['id'] ?? $r['json']['client']['id'] ?? null;
                        $createdUsername = $payload['username'];
                        echo "<p style='background:green;color:white;padding:10px'>🎉 یوزر ساخته شد! ID: $createdId Username: $createdUsername</p>";
                        // Try to delete it immediately to keep clean
                        if ($createdId) {
                            echo "<p>🧹 حذف یوزر تستی...</p>";
                            $delEndpoints = [
                                "/v1/seller/clients/$createdId",
                                "/v1/seller/clients/delete/$createdId",
                                "/v1/seller/clients/$createdId/delete",
                            ];
                            foreach ($delEndpoints as $delEp) {
                                $dr = callApi($baseUrl, $delEp, 'DELETE', null, $token);
                                echo "<p>DELETE $delEp - HTTP {$dr['code']} - ".htmlspecialchars(substr($dr['raw'],0,500))."</p>";
                                if ($dr['code']>=200 && $dr['code']<300) break;
                            }
                        }
                        break;
                    }
                    // If 422 validation error, show details
                    if ($r['code']==422 && $r['json']) {
                        echo "<p style='color:orange'>Validation error - باید فیلدها رو اصلاح کنیم</p>";
                    }
                    usleep(500000); // 0.5s delay to avoid rate limit
                }
            }

            if (!$created) {
                echo "<h4 style='color:red'>❌ هیچ‌کدام از endpointهای ساخت یوزر کار نکرد - دسترسی ساخت هنوز فعال نشده یا payload متفاوت می‌خواد</h4>";
                echo "<p>پیشنهاد: مستندات جدید API رو از پشتیبانی Connectix بگیرید یا توکن با دسترسی write بگیرید</p>";
            } else {
                echo "<h3 style='color:green'>✅ تبریک! API الان دسترسی ساخت یوزر داره! می‌تونیم driver رو آپدیت کنیم تا از پنل مستقیم یوزر بسازه</h3>";
            }
        }
    }

    // Also test driver current implementation
    echo "<hr><h3>🔧 تست Driver فعلی پنل</h3>";
    $driver = new ConnectixSellerDriver($srv['api_url'], $srv['api_username'] ?? null, $srv['api_password'] ?? null, $token);
    $auth = $driver->authenticate();
    echo "<p>authenticate(): ".($auth?'✅ موفق':'❌ ناموفق - '.$driver->getLastError())."</p>";
    $stats = $driver->getNodeStats();
    echo "<p>Stats: ".htmlspecialchars(json_encode($stats, JSON_UNESCAPED_UNICODE))."</p>";
    $users = $driver->listUsers();
    echo "<p>listUsers count: ".count($users)."</p>";
    if (!empty($users)) {
        echo "<pre style='background:#222;color:#fff;padding:10px;max-height:400px;overflow:auto;direction:ltr'>First user: ".htmlspecialchars(json_encode($users[0], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))."</pre>";
    }
    $createTest = $driver->createUser(['username'=>'test','traffic'=>10]);
    echo "<p>createUser() فعلی: ".htmlspecialchars(json_encode($createTest, JSON_UNESCAPED_UNICODE))."</p>";
}

echo "<hr><p><b>نتیجه‌گیری:</b> اگر تست ساخت یوزر موفق بود، بهم بگو تا driver رو کامل کنم و از این به بعد از پنل مستقیم یوزر بسازی بدون نیاز به seller.connectix.vip</p>";
echo "<p><a href='../'>بازگشت</a></p>";

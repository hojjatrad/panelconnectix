<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
header('Content-Type: text/html; charset=utf-8');
$id = (int)($_GET['id'] ?? 2);
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
$stmt->execute([$id]);
$server = $stmt->fetch();
if (!$server) die("Server not found");

$base = rtrim($server['api_url'], '/');
$token = $server['api_token'] ?? '';
$user = $server['api_username'] ?? '';
$pass = $server['api_password'] ?? '';

echo "<h2>Seller API V2 Debug - {$server['name']} - $base</h2>";
echo "Token: ".htmlspecialchars(substr($token,0,20))." ... len=".strlen($token)."<br>";
echo "User: $user<br>";

function testUrl($url, $token, $method='GET', $data=null) {
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
        'X-API-KEY: '.$token,
        'Api-Token: '.$token,
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code, $ctype, $res];
}

$tests = [
    // Direct base
    $base,
    $base.'/',
    // Without v1
    'https://seller-api.connectix.vip/externel',
    'https://seller-api.connectix.vip/externel/',
    'https://seller-api.connectix.vip',
    'https://seller-api.connectix.vip/',
    // Common seller API
    $base.'/clients',
    $base.'/client/list',
    $base.'/users',
    $base.'/user/list',
    $base.'/list',
    $base.'/all',
    'https://seller-api.connectix.vip/externel/v1/clients',
    'https://seller-api.connectix.vip/externel/v1/client/list',
    'https://seller-api.connectix.vip/externel/v1/users',
    'https://seller-api.connectix.vip/externel/v1/user/list',
    // api.connectix.vip style
    'https://api.connectix.vip/v1/seller/clients',
    'https://api.connectix.vip/v1/seller/clients/meta-data',
    'https://seller-api.connectix.vip/v1/seller/clients',
    // docs
    $base.'/docs',
    $base.'/swagger',
    $base.'/api/documentation',
    'https://seller-api.connectix.vip/docs',
    'https://seller-api.connectix.vip/api/documentation',
];

foreach ($tests as $url) {
    echo "<h4 style='margin:10px 0 2px'>GET $url</h4>";
    [$code, $ctype, $res] = testUrl($url, $token);
    echo "HTTP $code | CT: $ctype | Len: ".strlen($res)."<br>";
    if ($code != 404 || strlen($res) > 30) {
        $preview = substr($res,0,2000);
        echo "<pre style='background:#f0f0f0; padding:8px; max-height:300px; overflow:auto'>".htmlspecialchars($preview)."</pre>";
        $j = json_decode($res,true);
        if ($j) {
            echo "JSON keys: ".implode(', ', array_keys($j))."<br>";
            if (isset($j['data']) && is_array($j['data'])) {
                echo "data count: ".count($j['data'])."<br>";
                if (count($j['data'])>0) {
                    echo "First item keys: ".implode(', ', array_keys($j['data'][0]))."<br>";
                    echo "<div style='background:green;color:white;padding:5px'>FOUND DATA</div>";
                }
            }
        }
    }
    echo "<hr>";
}

// Try POST login to get token if current token wrong
echo "<h3>Try login with username/pass to seller-api</h3>";
$loginUrls = [
    'https://seller-api.connectix.vip/externel/v1/login',
    'https://seller-api.connectix.vip/externel/v1/auth/login',
    'https://seller-api.connectix.vip/externel/v1/api/login',
    'https://seller-api.connectix.vip/api/login',
    'https://seller-api.connectix.vip/login',
    'https://api.connectix.vip/v1/login',
    'https://api.connectix.vip/v1/seller/login',
];

foreach ($loginUrls as $url) {
    echo "<h4>POST $url</h4>";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username'=>$user, 'password'=>$pass, 'email'=>$user]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Accept: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP $code | ".htmlspecialchars(substr($res,0,1000))."<br><hr>";
}

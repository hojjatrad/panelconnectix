<?php
/**
 * SELLER API DEBUG - Test seller-api.connectix.vip endpoints
 * Upload to contax/seller_api_debug.php?id=2
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/html; charset=utf-8');

$id = (int)($_GET['id'] ?? 2);
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
$stmt->execute([$id]);
$server = $stmt->fetch();
if (!$server) die("Server not found");

echo "<h2>Seller API Debug - Server {$server['name']} ID $id</h2>";
echo "<p>URL: {$server['api_url']} | Driver: {$server['driver']} | Token: " . substr($server['api_token'] ?? '', 0, 10) . "...</p>";

$baseUrl = rtrim($server['api_url'], '/');
// Clean like PasargadDriver does
$clean = preg_replace('#/(dashboard|admin|api|v1)+/?$#i', '', $baseUrl);
$clean = rtrim($clean, '/');
echo "Cleaned baseUrl: $clean<br>";

$token = $server['api_token'] ?? $server['api_password'] ?? '';
$username = $server['api_username'] ?? '';
$password = $server['api_password'] ?? '';

$candidates = [
    // Seller API specific
    '/externel/v1/client/list',
    '/externel/v1/users',
    '/externel/v1/clients',
    '/external/v1/client/list',
    '/external/v1/users',
    '/api/v1/client/list',
    '/api/client/list',
    '/api/users',
    '/api/v1/users',
    '/v1/client/list',
    '/client/list',
    '/users',
    '/api/system',
    '/api/v1/system',
    '/api/status',
    '/api/server/stats',
    '/api/inbounds',
    // Try with baseUrl as is
    '',
    '/list',
    '/clients',
];

foreach ($candidates as $ep) {
    $url = $clean . $ep;
    // Also try original baseUrl + endpoint
    $url2 = $baseUrl . $ep;
    
    foreach ([$url, $url2] as $testUrl) {
        if (strpos($testUrl, '//api') !== false || strpos($testUrl, 'externel/v1/api') !== false) continue; // skip double api
        
        echo "<h4>Trying: $testUrl</h4>";
        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'X-API-KEY: ' . $token,
            'Content-Type: application/json'
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        echo "HTTP $code | Error: $err | Size: " . strlen($res) . "<br>";
        if ($code === 200 && strlen($res) > 0) {
            $json = json_decode($res, true);
            if ($json) {
                echo "JSON keys: " . implode(', ', array_keys($json)) . "<br>";
                echo "<pre>" . substr(json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 0, 2000) . "</pre>";
                // If we found users, stop
                if (isset($json['users']) || isset($json['data']) || isset($json[0])) {
                    echo "<div style='background:green; color:white; padding:10px;'>✅ Found data!</div>";
                }
            } else {
                echo "Raw: " . substr($res, 0, 500) . "<br>";
            }
        }
        echo "<hr>";
        // Only try first URL that is not duplicate
        break;
    }
    // Limit to first 10 to avoid too many requests
    if (count($candidates) > 15) break;
}

echo "<h3>Try with username/password as form login</h3>";
foreach (['/api/admin/token', '/api/v1/admin/token', '/externel/v1/admin/token', '/api/admin/login'] as $ep) {
    $url = $clean . $ep;
    echo "<h4>POST $url with username=$username</h4>";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username'=>$username, 'password'=>$password]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP $code | Size: " . strlen($res) . "<br>";
    echo "<pre>" . substr($res, 0, 1000) . "</pre><hr>";
}

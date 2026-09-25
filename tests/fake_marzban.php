<?php
// Fake Marzban API for local smoke test
$uri = $_SERVER['REQUEST_URI'] ?? '/';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uri === '/api/admin/token') {
    echo json_encode(['access_token' => 'fake-token-123', 'token_type' => 'bearer']);
    exit;
}
if ($uri === '/api/client/list') {
    $now = time();
    echo json_encode(['users' => [
        [
            'username' => 'ali',
            'status' => 'active',
            'online_at' => $now - 120,
            'used_traffic' => 12 * 1073741824,
            'data_limit' => 20 * 1073741824,
            'expire' => $now + 30 * 86400,
            'subscription_url' => '/api/client/ali/sub',
            'links' => [
                'vless://a1b2c3d4-1111-4111-8111-000000000001@1.2.3.4:443?encryption=none&security=tls&type=ws&host=panel.ir&path=%2Fws&serviceName=ws#ali-v1',
                'vmess://eyJoIjoiMS4yLjMuNCJ9#ali-v2',
            ],
        ],
        [
            'username' => 'sara',
            'status' => 'disabled',
            'online_at' => 0,
            'used_traffic' => 0,
            'data_limit' => 0,
            'expire' => 0,
            'subscription_url' => '/api/client/sara/sub',
            'links' => [],
        ],
        [
            'username' => 'direct1',
            'status' => 'active',
            'online_at' => $now - 999999,
            'used_traffic' => (int)(99.5 * 1073741824),
            'data_limit' => 100 * 1073741824,
            'expire' => $now - 86400,
            'subscription_url' => 'https://marzban.example.ir/api/client/direct1/sub',
            'links' => [
                'trojan://user:pass@1.2.3.4:443?sni=t.example.ir&type=ws&path=%2Ft#direct1-t1',
            ],
        ],
    ]]);
    exit;
}
if (preg_match('#^/api/user/[^/]+$#', $uri) && in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'])) {
    echo json_encode(['username' => 'ok', 'status' => 'changed']);
    exit;
}
http_response_code(404);
echo json_encode(['detail' => 'not found']);

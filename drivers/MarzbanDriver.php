<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class MarzbanDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $token;
    private int $timeout = 10;

    public function __construct(string $baseUrl, ?string $username, ?string $password, ?string $token = null) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->token = $token;
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null, bool $isForm = false): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json'
        ];

        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($isForm) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? []));
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data ?? []));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data ?? []));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'code' => $httpCode, 'error' => "cURL Error: $err", 'data' => null];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'code' => $httpCode,
            'data' => $decoded,
            'raw' => $response
        ];
    }

    public function authenticate(): bool {
        if (!empty($this->token)) {
            // Test existing token with /api/v1/system
            $res = $this->request('/api/v1/system');
            if ($res['success']) return true;
        }

        if (empty($this->username) || empty($this->password)) {
            return false;
        }

        $res = $this->request('/api/v1/admin/token', 'POST', [
            'username' => $this->username,
            'password' => $this->password
        ], true);

        if ($res['success'] && !empty($res['data']['access_token'])) {
            $this->token = $res['data']['access_token'];
            return true;
        }

        return false;
    }

    public function createUser(array $payload): array {
        if (!$this->authenticate()) {
            return ['success' => false, 'error' => 'عدم موفقیت در احراز هویت با سرور مرزبان', 'uuid' => '', 'sublink' => ''];
        }

        $body = [
            'username' => $payload['username'],
            'proxies' => [
                'vless' => ['id' => $payload['uuid']],
                'vmess' => ['id' => $payload['uuid']]
            ],
            'inbounds' => [],
            'expire' => $payload['expire_timestamp'] ?? null,
            'data_limit' => $payload['traffic_limit_bytes'] ?? 0,
            'data_limit_reset_strategy' => 'no_reset',
            'status' => 'active',
            'note' => 'Provisioned via Connectix Panel'
        ];

        $res = $this->request('/api/v1/user', 'POST', $body);
        if ($res['success']) {
            $subUrl = $res['data']['subscription_url'] ?? '';
            if (empty($subUrl) && !empty($res['data']['links'])) {
                $subUrl = $res['data']['links'][0] ?? '';
            }
            return [
                'success' => true,
                'uuid' => $payload['uuid'],
                'sublink' => $subUrl,
                'error' => null
            ];
        }

        $errMsg = $res['data']['detail'] ?? $res['error'] ?? 'خطا در ایجاد کاربر مرزبان';
        return ['success' => false, 'error' => is_array($errMsg) ? json_encode($errMsg) : $errMsg, 'uuid' => '', 'sublink' => ''];
    }

    public function getUser(string $username): ?array {
        if (!$this->authenticate()) return null;
        $res = $this->request('/api/v1/user/' . urlencode($username));
        if ($res['success'] && !empty($res['data'])) {
            $u = $res['data'];
            return [
                'traffic_used_bytes' => $u['used_traffic'] ?? 0,
                'traffic_limit_bytes' => $u['data_limit'] ?? 0,
                'expire_at' => !empty($u['expire']) ? date('Y-m-d H:i:s', $u['expire']) : null,
                'status' => $u['status'] ?? 'active',
                'online' => ($u['online_at'] ?? 0) > (time() - 300)
            ];
        }
        return null;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        if (!$this->authenticate()) return false;
        $current = $this->getUser($username);
        if (!$current) return false;

        $newLimit = $current['traffic_limit_bytes'] + $addTrafficBytes;
        $currentExpire = !empty($current['expire_at']) ? strtotime($current['expire_at']) : time();
        $newExpire = max($currentExpire, time()) + $addSeconds;

        $res = $this->request('/api/v1/user/' . urlencode($username), 'PUT', [
            'data_limit' => $newLimit,
            'expire' => $newExpire,
            'status' => 'active'
        ]);

        return $res['success'];
    }

    public function deleteUser(string $username): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request('/api/v1/user/' . urlencode($username), 'DELETE');
        return $res['success'];
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request('/api/v1/user/' . urlencode($username), 'PUT', [
            'status' => $active ? 'active' : 'disabled'
        ]);
        return $res['success'];
    }

    public function getNodeStats(): array {
        if (!$this->authenticate()) {
            return ['status' => 'offline', 'users' => 0, 'cpu' => '0%', 'ram' => '0%'];
        }
        $res = $this->request('/api/v1/system');
        if ($res['success'] && !empty($res['data'])) {
            return [
                'status' => 'online',
                'version' => $res['data']['version'] ?? 'Marzban Core',
                'users' => $res['data']['total_user'] ?? 0,
                'cpu' => ($res['data']['cpu_usage'] ?? 0) . '%',
                'ram' => round(($res['data']['mem_used'] ?? 0) / 1024 / 1024 / 1024, 1) . ' GB'
            ];
        }
        return ['status' => 'online', 'users' => 0];
    }
}

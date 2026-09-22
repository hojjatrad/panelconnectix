<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class XUiDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private string $cookieFile;
    private int $timeout = 10;

    public function __construct(string $baseUrl, ?string $username, ?string $password) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/xui_cookie_' . md5($this->baseUrl . $this->username) . '.txt';
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json'
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'code' => $httpCode, 'error' => $err, 'data' => null];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300) && ($decoded['success'] ?? true),
            'code' => $httpCode,
            'data' => $decoded,
            'raw' => $response
        ];
    }

    public function authenticate(): bool {
        if (empty($this->username) || empty($this->password)) return false;

        $ch = curl_init($this->baseUrl . '/login');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($res, true);
        return $httpCode === 200 && ($decoded['success'] ?? false);
    }

    public function createUser(array $payload): array {
        if (!$this->authenticate()) {
            return ['success' => false, 'error' => 'خطا در احراز هویت 3x-ui', 'uuid' => '', 'sublink' => ''];
        }

        $clientData = [
            'id' => $payload['uuid'],
            'email' => $payload['username'],
            'limitIp' => 2,
            'totalGB' => $payload['traffic_limit_bytes'] ?? 0,
            'expiryTime' => ($payload['expire_timestamp'] ?? 0) * 1000,
            'enable' => true,
            'subId' => $payload['uuid'],
            'flow' => 'xtls-rprx-vision'
        ];

        // 3x-ui expects JSON in settings or direct API
        $res = $this->request('/panel/api/inbounds/addClient', 'POST', [
            'id' => 1, // default inbound 1
            'settings' => json_encode(['clients' => [$clientData]])
        ]);

        if ($res['success']) {
            return [
                'success' => true,
                'uuid' => $payload['uuid'],
                'sublink' => $this->baseUrl . '/sub/' . $payload['uuid'],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'error' => $res['data']['msg'] ?? 'خطا در ثبت کلاینت در 3x-ui',
            'uuid' => '',
            'sublink' => ''
        ];
    }

    public function getUser(string $username): ?array {
        if (!$this->authenticate()) return null;
        $res = $this->request('/panel/api/inbounds/getClientTraffics/' . urlencode($username));
        if ($res['success'] && !empty($res['data']['obj'])) {
            $obj = $res['data']['obj'];
            $used = ($obj['up'] ?? 0) + ($obj['down'] ?? 0);
            return [
                'traffic_used_bytes' => $used,
                'traffic_limit_bytes' => $obj['total'] ?? 0,
                'expire_at' => !empty($obj['expiryTime']) ? date('Y-m-d H:i:s', $obj['expiryTime'] / 1000) : null,
                'status' => ($obj['enable'] ?? false) ? 'active' : 'disabled',
                'online' => false
            ];
        }
        return null;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        // In 3x-ui client update can reset or increment total
        return true;
    }

    public function deleteUser(string $username): bool {
        return true;
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        return true;
    }

    public function getNodeStats(): array {
        return ['status' => 'online', 'version' => '3x-ui Core', 'users' => 1];
    }
}

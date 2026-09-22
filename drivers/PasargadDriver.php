<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class PasargadDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $apiToken;
    private int $timeout = 10;

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $apiToken = null) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiToken = $apiToken ?: $password; // token can be in token or password field
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        if (!empty($this->apiToken)) {
            $headers[] = 'Authorization: ' . $this->apiToken;
            $headers[] = 'X-API-KEY: ' . $this->apiToken;
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data ?? []));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
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
        if (empty($this->apiToken)) return false;
        $res = $this->request('/api/status');
        return $res['success'] || $res['code'] === 200;
    }

    public function createUser(array $payload): array {
        $body = [
            'username' => $payload['username'],
            'uuid' => $payload['uuid'],
            'traffic_limit' => $payload['traffic_limit_bytes'] ?? 0,
            'expire_time' => $payload['expire_timestamp'] ?? 0,
            'status' => 'active'
        ];

        $res = $this->request('/api/users/add', 'POST', $body);
        if ($res['success']) {
            $sublink = $res['data']['sub_link'] ?? ($this->baseUrl . '/sub/' . $payload['uuid']);
            return [
                'success' => true,
                'uuid' => $payload['uuid'],
                'sublink' => $sublink,
                'error' => null
            ];
        }

        return [
            'success' => false,
            'error' => $res['data']['message'] ?? $res['error'] ?? 'خطا در ارتباط با پنل پاسارگاد',
            'uuid' => '',
            'sublink' => ''
        ];
    }

    public function getUser(string $username): ?array {
        $res = $this->request('/api/users/' . urlencode($username));
        if ($res['success'] && !empty($res['data'])) {
            $u = $res['data'];
            return [
                'traffic_used_bytes' => $u['used_traffic'] ?? 0,
                'traffic_limit_bytes' => $u['total_traffic'] ?? 0,
                'expire_at' => !empty($u['expire_time']) ? date('Y-m-d H:i:s', $u['expire_time']) : null,
                'status' => $u['status'] ?? 'active',
                'online' => !empty($u['is_online'])
            ];
        }
        return null;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        $res = $this->request('/api/users/' . urlencode($username) . '/extend', 'POST', [
            'add_traffic' => $addTrafficBytes,
            'add_seconds' => $addSeconds
        ]);
        return $res['success'];
    }

    public function deleteUser(string $username): bool {
        $res = $this->request('/api/users/' . urlencode($username), 'DELETE');
        return $res['success'];
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        $res = $this->request('/api/users/' . urlencode($username) . '/status', 'PUT', [
            'active' => $active
        ]);
        return $res['success'];
    }

    public function getNodeStats(): array {
        $res = $this->request('/api/server/stats');
        if ($res['success'] && !empty($res['data'])) {
            return [
                'status' => 'online',
                'version' => 'Pasargad Panel',
                'users' => $res['data']['active_users'] ?? 0,
                'cpu' => ($res['data']['cpu'] ?? 0) . '%',
                'ram' => ($res['data']['ram'] ?? 0) . '%'
            ];
        }
        return ['status' => 'online', 'users' => 0];
    }
}

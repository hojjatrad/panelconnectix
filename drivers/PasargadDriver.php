<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class PasargadDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $token;
    private string $apiPrefix = '/api';
    private bool $isPasarGuard = true; // Modern PasarGuard (FastAPI) vs legacy Pasargad
    private ?string $lastError = null;
    private int $timeout = 12;

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $token = null) {
        $clean = rtrim(trim($baseUrl), '/');
        $clean = preg_replace('#/(dashboard|admin|api|v1)+/?$#i', '', $clean);
        $this->baseUrl = rtrim($clean, '/');

        $this->username = $username ? trim($username) : null;
        $this->password = $password ? trim($password) : null;
        $this->token = $token ? trim($token) : ($this->username ? null : $this->password);
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null, bool $isForm = false): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];

        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'X-API-KEY: ' . $this->token;
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->lastError = "خطای اتصال به سرور پاسارگاد (cURL): {$err}";
            return ['success' => false, 'code' => $httpCode, 'error' => $err, 'data' => null];
        }

        $decoded = json_decode((string)$response, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        if (!$isSuccess) {
            $detail = $decoded['detail'] ?? $decoded['message'] ?? null;
            if ($detail) {
                if (is_array($detail)) {
                    $detail = json_encode($detail, JSON_UNESCAPED_UNICODE);
                }
                if (stripos($detail, 'Incorrect') !== false || stripos($detail, 'password') !== false) {
                    $this->lastError = "نام کاربری یا رمز عبور ادمین پاسارگاد اشتباه است.";
                } else {
                    $this->lastError = "پاسخ سرور پاسارگاد: {$detail} (کد {$httpCode})";
                }
            } elseif ($httpCode === 403) {
                $this->lastError = "دسترسی توسط فایروال یا کلودفلر سرور پاسارگاد مسدود شد (خطای ۴۰۳ Cloudflare/WAF).";
            } elseif ($httpCode === 404) {
                $this->lastError = "مسیر وب‌سرویس پاسارگاد در این آدرس یافت نشد (کد ۴۰۴).";
            } else {
                $this->lastError = "خطای سرور پاسارگاد با کد HTTP {$httpCode}";
            }
        }

        return [
            'success' => $isSuccess,
            'code' => $httpCode,
            'data' => $decoded,
            'raw' => $response
        ];
    }

    public function authenticate(): bool {
        // Strategy A: If token is already present, test with system status
        if (!empty($this->token)) {
            $res = $this->request('/api/system');
            if ($res['success']) {
                $this->isPasarGuard = true;
                $this->apiPrefix = '/api';
                $this->lastError = null;
                return true;
            }
            $resLegacy = $this->request('/api/status');
            if ($resLegacy['success'] || $resLegacy['code'] === 200) {
                $this->isPasarGuard = false;
                $this->lastError = null;
                return true;
            }
        }

        // Strategy B: Modern PasarGuard with username & password (OAuth2 Form /api/admin/token)
        if (!empty($this->username) && !empty($this->password)) {
            foreach (['/api/admin/token', '/api/v1/admin/token'] as $endpoint) {
                $res = $this->request($endpoint, 'POST', [
                    'username' => $this->username,
                    'password' => $this->password
                ], true);

                if ($res['success'] && !empty($res['data']['access_token'])) {
                    $this->token = $res['data']['access_token'];
                    $this->isPasarGuard = true;
                    $this->apiPrefix = str_starts_with($endpoint, '/api/v1') ? '/api/v1' : '/api';
                    $this->lastError = null;
                    return true;
                }

                if ($res['code'] === 400 || $res['code'] === 401) {
                    $detail = $res['data']['detail'] ?? '';
                    if (stripos($detail, 'Incorrect') !== false || stripos($detail, 'password') !== false) {
                        $this->lastError = "نام کاربری یا رمز عبور ادمین پاسارگاد اشتباه است.";
                    } else {
                        $this->lastError = "خطای احراز هویت پاسارگاد: {$detail}";
                    }
                    return false;
                }
            }
        }

        // Strategy C: Legacy Pasargad token authentication via /api/status
        $candidateToken = $this->token ?: $this->password;
        if (!empty($candidateToken)) {
            $this->token = $candidateToken;
            $res = $this->request('/api/status');
            if ($res['success'] || $res['code'] === 200) {
                $this->isPasarGuard = false;
                $this->lastError = null;
                return true;
            }
        }

        if (empty($this->username) && empty($this->token)) {
            $this->lastError = "نام کاربری و کلمه عبور یا توکن API وارد نشده است.";
        }

        return false;
    }

    public function createUser(array $payload): array {
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'error' => $this->lastError ?: 'عدم موفقیت در احراز هویت با پنل پاسارگاد',
                'uuid' => '',
                'sublink' => ''
            ];
        }

        if ($this->isPasarGuard) {
            // Modern PasarGuard
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

            $res = $this->request($this->apiPrefix . '/user', 'POST', $body);
            if ($res['success']) {
                $subUrl = $res['data']['subscription_url'] ?? '';
                if (empty($subUrl) && !empty($res['data']['links'])) {
                    $subUrl = $res['data']['links'][0] ?? '';
                }
                return [
                    'success' => true,
                    'uuid' => $payload['uuid'],
                    'sublink' => $subUrl ?: ($this->baseUrl . '/sub/' . $payload['uuid']),
                    'error' => null
                ];
            }

            $errMsg = $res['data']['detail'] ?? $this->lastError ?? 'خطا در ایجاد کاربر پاسارگاد';
            return [
                'success' => false,
                'error' => is_array($errMsg) ? json_encode($errMsg, JSON_UNESCAPED_UNICODE) : $errMsg,
                'uuid' => '',
                'sublink' => ''
            ];
        } else {
            // Legacy Pasargad
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
                'error' => $res['data']['message'] ?? $res['error'] ?? 'خطا در ثبت کلاینت در پنل پاسارگاد',
                'uuid' => '',
                'sublink' => ''
            ];
        }
    }

    public function getUser(string $username): ?array {
        if (!$this->authenticate()) return null;

        if ($this->isPasarGuard) {
            $res = $this->request($this->apiPrefix . '/user/' . urlencode($username));
            if ($res['success'] && !empty($res['data'])) {
                $u = $res['data'];
                return [
                    'traffic_used_bytes' => $u['used_traffic'] ?? 0,
                    'traffic_limit_bytes' => $u['data_limit'] ?? 0,
                    'expire_at' => !empty($u['expire']) ? date('Y-m-d H:i:s', $u['expire']) : null,
                    'status' => $u['status'] ?? 'active',
                    'online' => ($u['online_at'] ?? 0) > (time() - 300),
                    'links' => $u['links'] ?? [],
                    'subscription_url' => $u['subscription_url'] ?? ''
                ];
            }
        } else {
            $res = $this->request('/api/users/' . urlencode($username));
            if ($res['success'] && !empty($res['data'])) {
                $u = $res['data'];
                return [
                    'traffic_used_bytes' => $u['used_traffic'] ?? 0,
                    'traffic_limit_bytes' => $u['total_traffic'] ?? 0,
                    'expire_at' => !empty($u['expire_time']) ? date('Y-m-d H:i:s', $u['expire_time']) : null,
                    'status' => $u['status'] ?? 'active',
                    'online' => !empty($u['is_online']),
                    'links' => $u['links'] ?? [],
                    'subscription_url' => $u['sub_link'] ?? ''
                ];
            }
        }
        return null;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        if (!$this->authenticate()) return false;

        if ($this->isPasarGuard) {
            $current = $this->getUser($username);
            if (!$current) return false;
            $newLimit = $current['traffic_limit_bytes'] + $addTrafficBytes;
            $currentExpire = !empty($current['expire_at']) ? strtotime($current['expire_at']) : time();
            $newExpire = max($currentExpire, time()) + $addSeconds;

            $res = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'PUT', [
                'data_limit' => $newLimit,
                'expire' => $newExpire,
                'status' => 'active'
            ]);
            return $res['success'];
        } else {
            $res = $this->request('/api/users/' . urlencode($username) . '/extend', 'POST', [
                'add_traffic' => $addTrafficBytes,
                'add_seconds' => $addSeconds
            ]);
            return $res['success'];
        }
    }

    public function deleteUser(string $username): bool {
        if (!$this->authenticate()) return false;
        $endpoint = $this->isPasarGuard ? ($this->apiPrefix . '/user/' . urlencode($username)) : ('/api/users/' . urlencode($username));
        $res = $this->request($endpoint, 'DELETE');
        return $res['success'];
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        if (!$this->authenticate()) return false;
        if ($this->isPasarGuard) {
            $res = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'PUT', [
                'status' => $active ? 'active' : 'disabled'
            ]);
            return $res['success'];
        } else {
            $res = $this->request('/api/users/' . urlencode($username) . '/status', 'PUT', [
                'active' => $active
            ]);
            return $res['success'];
        }
    }

    public function getNodeStats(): array {
        if (!$this->authenticate()) {
            return ['status' => 'offline', 'version' => 'Pasargad (عدم اتصال)', 'users' => 0];
        }

        if ($this->isPasarGuard) {
            $res = $this->request($this->apiPrefix . '/system');
            if ($res['success'] && !empty($res['data'])) {
                return [
                    'status' => 'online',
                    'version' => $res['data']['version'] ?? 'PasarGuard Core',
                    'users' => $res['data']['total_user'] ?? 0,
                    'cpu' => ($res['data']['cpu_usage'] ?? 0) . '%',
                    'ram' => round(($res['data']['mem_used'] ?? 0) / 1024 / 1024 / 1024, 1) . ' GB'
                ];
            }
        } else {
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
        }

        return ['status' => 'online', 'version' => 'Pasargad Core', 'users' => 0];
    }
}

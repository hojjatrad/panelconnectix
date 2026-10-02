<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class XUiDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $subDomain;
    private ?string $username;
    private ?string $password;
    private string $cookieFile;
    private ?string $token;
    private ?string $lastError = null;
    private int $timeout = 5;

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $token = null, ?string $subDomain = null) {
        $clean = rtrim(trim($baseUrl), '/');
        if (!empty($clean) && !preg_match('#^https?://#i', $clean)) {
            $clean = 'https://' . $clean;
        }
        $this->baseUrl = $clean;
        $this->username = $username ? trim($username) : null;
        $this->password = $password ? trim($password) : null;
        $this->token = $token ? trim($token) : ($this->username ? null : $this->password);
        $this->subDomain = $subDomain ? trim($subDomain) : null;
        $this->cookieFile = sys_get_temp_dir() . '/xui_cookie_' . md5($this->baseUrl . ($this->username ?: $this->token ?: 'default')) . '.txt';
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json'
        ];
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'X-API-KEY: ' . $this->token;
            $headers[] = 'Cookie: session=' . $this->token;
        }

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
        $cacheKey = md5($this->baseUrl . ($this->username ?? ''));
        $cacheFile = sys_get_temp_dir() . '/xui_prefix_' . $cacheKey . '.json';

        // If token is present, try probe with inbounds list
        if (!empty($this->token)) {
            $probe = $this->request('/panel/api/inbounds/list');
            if ($probe['success']) {
                $this->lastError = null;
                return true;
            }
        }

        if (empty($this->username) || empty($this->password)) {
            $this->lastError = "نام کاربری یا رمز عبور یا کلید API وارد نشده است.";
            return false;
        }

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
            'limitIp' => (int)($payload['ip_limit'] ?? 0),
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

    public function updateUser(string $username, array $params): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request('/panel/api/inbounds/list');
        if (!$res['success'] || empty($res['data']['obj'])) return false;

        foreach ($res['data']['obj'] as $inb) {
            $settings = json_decode((string)($inb['settings'] ?? '{}'), true) ?: [];
            $clients = $settings['clients'] ?? [];
            if (!is_array($clients)) continue;

            foreach ($clients as $c) {
                if (!is_array($c)) continue;
                $cEmail = (string)($c['email'] ?? '');
                $cUuid = (string)($c['id'] ?? '');
                if ($cEmail === $username || $cUuid === $username) {
                    $inboundId = (int)$inb['id'];
                    $updatedClient = $c;
                    if (isset($params['traffic_limit_bytes'])) {
                        $updatedClient['totalGB'] = round((float)$params['traffic_limit_bytes'] / 1073741824, 2);
                    }
                    if (isset($params['expire_timestamp'])) {
                        $ts = (int)$params['expire_timestamp'];
                        $updatedClient['expiryTime'] = ($ts > 0) ? ($ts * 1000) : 0;
                    }
                    if (isset($params['status'])) {
                        $updatedClient['enable'] = ($params['status'] === 'active');
                    }
                    $upRes = $this->request('/panel/api/inbounds/updateClient/' . $cUuid, 'POST', [
                        'id' => $inboundId,
                        'settings' => json_encode(['clients' => [$updatedClient]])
                    ]);
                    return $upRes['success'] ?? false;
                }
            }
        }
        return false;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        if (!$this->authenticate()) return false;
        $current = $this->getUser($username);
        $curLimit = $current ? (int)($current['traffic_limit_bytes'] ?? 0) : 0;
        $curExpire = ($current && !empty($current['expire_at'])) ? strtotime($current['expire_at']) : time();
        $newLimit = $curLimit + $addTrafficBytes;
        $newExpire = max($curExpire, time()) + $addSeconds;
        return $this->updateUser($username, [
            'traffic_limit_bytes' => $newLimit,
            'expire_timestamp' => $newExpire,
            'status' => 'active'
        ]);
    }

    public function deleteUser(string $username): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request('/panel/api/inbounds/list');
        if (!$res['success'] || empty($res['data']['obj'])) return false;

        foreach ($res['data']['obj'] as $inb) {
            $settings = json_decode((string)($inb['settings'] ?? '{}'), true) ?: [];
            $clients = $settings['clients'] ?? [];
            if (!is_array($clients)) continue;

            foreach ($clients as $c) {
                if (!is_array($c)) continue;
                if (($c['email'] ?? '') === $username || ($c['id'] ?? '') === $username) {
                    $inbId = (int)$inb['id'];
                    $uuid = (string)$c['id'];
                    $delRes = $this->request("/panel/api/inbounds/{$inbId}/delClient/{$uuid}", 'POST');
                    return $delRes['success'] ?? false;
                }
            }
        }
        return false;
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        return $this->updateUser($username, ['status' => $active ? 'active' : 'disabled']);
    }

    public function getNodeStats(): array {
        return ['status' => 'online', 'version' => '3x-ui Core', 'users' => 1];
    }

    /**
     * List ALL clients across all inbounds of this 3x-ui node.
     * Best-effort: builds vless/vmess/trojan/ss links from inbound settings.
     */
    public function listUsers(): array {
        if (!$this->authenticate()) return [];
        $res = $this->request('/panel/api/inbounds/list');
        if (!$res['success'] || empty($res['data']['obj'])) return [];

        $host = '';
        if (!empty($this->subDomain)) {
            $host = str_replace(['http://', 'https://'], '', rtrim($this->subDomain, '/'));
        } elseif (!empty($this->baseUrl)) {
            $host = str_replace(['http://', 'https://'], '', rtrim($this->baseUrl, '/'));
        }

        $out = [];
        foreach ($res['data']['obj'] as $inb) {
            $protocol = strtolower((string)($inb['protocol'] ?? ''));
            if (!in_array($protocol, ['vless', 'vmess', 'trojan', 'shadowsocks'])) continue;
            $settings = json_decode((string)($inb['settings'] ?? '{}'), true) ?: [];
            $stream = json_decode((string)($inb['streamSettings'] ?? '{}'), true) ?: [];
            $port = (int)($inb['port'] ?? 0);
            $clients = $settings['clients'] ?? [];
            if (!is_array($clients)) continue;

            // Map clientStats by email and id
            $clientStats = [];
            if (!empty($inb['clientStats']) && is_array($inb['clientStats'])) {
                foreach ($inb['clientStats'] as $cs) {
                    if (!is_array($cs)) continue;
                    $emailKey = strtolower(trim((string)($cs['email'] ?? '')));
                    if ($emailKey !== '') $clientStats[$emailKey] = $cs;
                }
            }

            foreach ($clients as $c) {
                if (!is_array($c)) continue;
                $uuid = (string)($c['id'] ?? '');
                $email = (string)($c['email'] ?? '');
                $trojanPass = (string)($c['password'] ?? '');
                $ssMethod = (string)($c['method'] ?? 'chacha20-ietf-poly1305');

                if ($protocol === 'vless' && $uuid !== '') $username = $email !== '' ? $email : $uuid;
                elseif ($protocol === 'vmess' && $uuid !== '') $username = $email !== '' ? $email : $uuid;
                elseif ($protocol === 'trojan' && $trojanPass !== '') $username = $trojanPass;
                elseif ($protocol === 'shadowsocks' && $trojanPass !== '') $username = $trojanPass;
                else continue;

                $cs = $clientStats[strtolower($username)] ?? $clientStats[strtolower($email)] ?? null;
                $usedBytes = 0;
                $limitBytes = 0;
                $expireAt = null;

                if ($cs) {
                    $up = (int)($cs['up'] ?? 0);
                    $down = (int)($cs['down'] ?? 0);
                    $usedBytes = $up + $down;
                    $limitBytes = (int)($cs['total'] ?? 0);
                    if (!empty($cs['expiryTime']) && (int)$cs['expiryTime'] > 0) {
                        $expMs = (int)$cs['expiryTime'];
                        $expSec = ($expMs > 20000000000) ? (int)round($expMs / 1000) : $expMs;
                        $expireAt = date('Y-m-d H:i:s', $expSec);
                    }
                }

                if ($limitBytes <= 0) {
                    $totalGB = (float)($c['totalGB'] ?? 0);
                    if ($totalGB > 0) {
                        $limitBytes = (int)round($totalGB * 1073741824);
                    } elseif (!empty($c['total'])) {
                        $t = (int)$c['total'];
                        $limitBytes = ($t > 0 && $t < 10000) ? (int)round($t * 1073741824) : $t;
                    }
                }

                if (!$expireAt && !empty($c['expiryTime']) && (int)$c['expiryTime'] > 0) {
                    $expMs = (int)$c['expiryTime'];
                    $expSec = ($expMs > 20000000000) ? (int)round($expMs / 1000) : $expMs;
                    $expireAt = date('Y-m-d H:i:s', $expSec);
                }

                $link = '';
                $security = (string)($stream['security'] ?? 'none');
                $network = (string)($stream['network'] ?? 'tcp');
                $sni = (string)($stream['sni'] ?? $stream['serverName'] ?? '');
                $pbk = (string)($stream['realitySettings']['publicKey'] ?? '');
                $sp = (string)($stream['tcpSettings'] ?? ['header']['request']['path'] ?? '');
                $wspath = (string)($stream['wsSettings']['path'] ?? '');
                $path = $wspath !== '' ? $wspath : $sp;

                if ($protocol === 'vless' && $host !== '' && $port > 0) {
                    $q = http_build_query(array_filter([
                        'encryption' => 'none',
                        'security' => $security,
                        'type' => $network,
                        'path' => $path,
                        'sni' => $sni,
                        'fp' => 'chrome',
                        'pbk' => $pbk,
                        'flow' => (string)($c['flow'] ?? ''),
                    ]));
                    $link = "vless://{$uuid}@{$host}:{$port}?{$q}#" . rawurlencode($username);
                } elseif ($protocol === 'vmess' && $host !== '' && $port > 0) {
                    $cfg = [
                        'v' => '2', 'ps' => $username, 'add' => $host, 'port' => (string)$port,
                        'id' => $uuid, 'aid' => (string)($c['alterId'] ?? 0),
                        'scy' => 'auto', 'net' => $network, 'type' => 'none',
                        'host' => $sni, 'path' => $path, 'tls' => ($security === 'tls' || $security === 'reality') ? 'tls' : '',
                    ];
                    $link = 'vmess://' . base64_encode(json_encode(array_filter($cfg)));
                } elseif ($protocol === 'trojan' && $host !== '' && $port > 0) {
                    $q = http_build_query(array_filter(['security' => $security, 'type' => $network, 'path' => $path, 'sni' => $sni]));
                    $link = "trojan://{$trojanPass}@{$host}:{$port}?{$q}#" . rawurlencode($username);
                } elseif ($protocol === 'shadowsocks' && $host !== '' && $port > 0) {
                    $link = 'ss://' . base64_encode("{$ssMethod}:{$trojanPass}@{$host}:{$port}") . '#' . rawurlencode($username);
                }

                $subId = (string)($c['subId'] ?? '');
                $subUrl = ($host !== '' && $subId !== '') ? "https://{$host}/sub/{$subId}" : '';

                $out[] = [
                    'username' => $username,
                    'status' => (($c['enable'] ?? true) === true) ? 'active' : 'disabled',
                    'online' => false,
                    'traffic_used_bytes' => $usedBytes,
                    'traffic_limit_bytes' => $limitBytes,
                    'expire_at' => $expireAt,
                    'subscription_url' => $subUrl,
                    'links' => $link !== '' ? [$link] : [],
                    'usage_unknown' => false,
                ];
            }
        }
        return $out;
    }
}

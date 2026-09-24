<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class MarzbanDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $token;
    private string $apiPrefix = '/api';
    private ?string $lastError = null;
    private ?string $subDomain = null;
    private int $timeout = 12;

    public function __construct(string $baseUrl, ?string $username, ?string $password, ?string $token = null, ?string $subDomain = null) {
        // Clean URL: remove trailing slashes, /dashboard, /admin, /api
        $clean = rtrim(trim($baseUrl), '/');
        $clean = preg_replace('#/(dashboard|admin|api|v1)+/?$#i', '', $clean);
        $this->baseUrl = rtrim($clean, '/');

        $this->username = $username ? trim($username) : null;
        $this->password = $password ? trim($password) : null;
        $this->token = $token ? trim($token) : null;
        $this->subDomain = $subDomain ? trim($subDomain) : null;
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function request(string $endpoint, string $method = 'GET', ?array $data = null, bool $isForm = false): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
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
            $this->lastError = "خطای اتصال به سرور (cURL): {$err}";
            return ['success' => false, 'code' => $httpCode, 'error' => $err, 'data' => null];
        }

        $decoded = json_decode((string)$response, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        if (!$isSuccess) {
            $detail = $decoded['detail'] ?? null;
            if ($detail) {
                if (is_array($detail)) {
                    $detail = json_encode($detail, JSON_UNESCAPED_UNICODE);
                }
                if (stripos($detail, 'Incorrect username or password') !== false) {
                    $this->lastError = "نام کاربری یا رمز عبور ادمین مرزبان اشتباه است.";
                } else {
                    $this->lastError = "پاسخ سرور: {$detail} (کد {$httpCode})";
                }
            } elseif ($httpCode === 403) {
                $this->lastError = "دسترسی توسط فایروال یا کلودفلر سرور مسدود شد (خطای ۴۰۳ Cloudflare/WAF).";
            } elseif ($httpCode === 404) {
                $this->lastError = "مسیر وب‌سرویس مرزبان در این پورت/آدرس یافت نشد (کد ۴۰۴).";
            } else {
                $this->lastError = "خطای سرور مرزبان با کد HTTP {$httpCode}";
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
        // 1. Try testing existing token if present
        if (!empty($this->token)) {
            foreach (['/api/system', '/api/v1/system'] as $testEndpoint) {
                $res = $this->request($testEndpoint);
                if ($res['success']) {
                    $this->apiPrefix = str_starts_with($testEndpoint, '/api/v1') ? '/api/v1' : '/api';
                    return true;
                }
            }
        }

        if (empty($this->username) || empty($this->password)) {
            $this->lastError = "نام کاربری یا رمز عبور ادمین وارد نشده است.";
            return false;
        }

        // 2. Try auth with multiple standard Marzban routes:
        // Priority A: /api/admin/token (Standard Marzban)
        // Priority B: /api/v1/admin/token (Alternative/Subversion)
        $authEndpoints = [
            '/api/admin/token' => '/api',
            '/api/v1/admin/token' => '/api/v1'
        ];

        foreach ($authEndpoints as $endpoint => $prefix) {
            $res = $this->request($endpoint, 'POST', [
                'username' => $this->username,
                'password' => $this->password
            ], true);

            if ($res['success'] && !empty($res['data']['access_token'])) {
                $this->token = $res['data']['access_token'];
                $this->apiPrefix = $prefix;
                $this->lastError = null;
                return true;
            }

            // If it returned 400 (Bad credentials), credentials are wrong; stop here
            if ($res['code'] === 400 || $res['code'] === 401) {
                $detail = $res['data']['detail'] ?? '';
                if (stripos($detail, 'Incorrect') !== false || stripos($detail, 'password') !== false) {
                    $this->lastError = "نام کاربری یا رمز عبور ادمین مرزبان اشتباه است. (Incorrect username or password)";
                } else {
                    $this->lastError = "خطای احراز هویت: {$detail}";
                }
                return false;
            }
        }

        return false;
    }

    public function applySubDomain(string $url): string {
        if (empty($url)) return $url;
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return $url;

        // Target domain to rewrite to
        $targetDomain = !empty($this->subDomain) ? trim($this->subDomain) : '';

        // If targetDomain is not set, but the server returned a known broken domain (like gga1.montago-shop.ir)
        // or a domain that differs from api_url host, fallback to baseUrl
        if (empty($targetDomain)) {
            if ($parts['host'] === 'gga1.montago-shop.ir' || str_contains($parts['host'], 'montago-shop.ir')) {
                $targetDomain = $this->baseUrl;
            } else {
                return $url;
            }
        }

        $scheme = str_starts_with($targetDomain, 'http://') ? 'http' : 'https';
        $targetHost = preg_replace('#^https?://#i', '', rtrim($targetDomain, '/'));

        $path = $parts['path'] ?? '';
        $query = !empty($parts['query']) ? ('?' . $parts['query']) : '';
        $fragment = !empty($parts['fragment']) ? ('#' . $parts['fragment']) : '';

        return "{$scheme}://{$targetHost}{$path}{$query}{$fragment}";
    }

    public function getDetailedInbounds(): array {
        if (!$this->authenticate()) return [];

        $endpoints = [$this->apiPrefix . '/inbounds', '/api/inbounds', '/api/v1/inbounds', '/api/system'];
        foreach ($endpoints as $ep) {
            $res = $this->request($ep);
            if (!$res['success'] || empty($res['data'])) continue;

            $raw = $res['data'];
            if (isset($raw['inbounds']) && is_array($raw['inbounds'])) {
                $raw = $raw['inbounds'];
            } elseif (isset($raw['data']) && is_array($raw['data'])) {
                $raw = $raw['data'];
            }

            $list = [];
            if (isset($raw[0]) && is_array($raw[0])) {
                foreach ($raw as $item) {
                    if (is_array($item)) {
                        $list[] = [
                            'tag' => $item['tag'] ?? 'Inbound',
                            'protocol' => strtolower($item['protocol'] ?? 'vless'),
                            'network' => $item['network'] ?? 'tcp',
                            'tls' => $item['tls'] ?? 'none',
                            'port' => $item['port'] ?? 443
                        ];
                    }
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $proto => $items) {
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (is_array($item)) {
                                $list[] = [
                                    'tag' => $item['tag'] ?? 'Inbound',
                                    'protocol' => strtolower((string)$proto),
                                    'network' => $item['network'] ?? 'tcp',
                                    'tls' => $item['tls'] ?? 'none',
                                    'port' => $item['port'] ?? 443
                                ];
                            } elseif (is_string($item)) {
                                $list[] = [
                                    'tag' => $item,
                                    'protocol' => strtolower((string)$proto),
                                    'network' => 'tcp',
                                    'tls' => 'tls',
                                    'port' => 443
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($list)) return $list;
        }

        return [];
    }

    public function getInbounds(): array {
        if (!$this->authenticate()) return [];

        $endpoints = [$this->apiPrefix . '/inbounds', '/api/inbounds', '/api/v1/inbounds', '/api/system'];
        foreach ($endpoints as $ep) {
            $res = $this->request($ep);
            if (!$res['success'] || empty($res['data'])) continue;

            $raw = $res['data'];
            if (isset($raw['inbounds']) && is_array($raw['inbounds'])) {
                $raw = $raw['inbounds'];
            } elseif (isset($raw['data']) && is_array($raw['data'])) {
                $raw = $raw['data'];
            }

            $inbounds = [];
            if (isset($raw[0]) && is_array($raw[0])) {
                foreach ($raw as $item) {
                    if (is_array($item)) {
                        $proto = strtolower($item['protocol'] ?? 'vless');
                        $tag = $item['tag'] ?? null;
                        if ($tag) {
                            $inbounds[$proto][] = $tag;
                        }
                    }
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $proto => $items) {
                    $protoLower = strtolower((string)$proto);
                    if (is_array($items)) {
                        $inbounds[$protoLower] = [];
                        foreach ($items as $item) {
                            if (is_array($item) && !empty($item['tag'])) {
                                $inbounds[$protoLower][] = $item['tag'];
                            } elseif (is_string($item)) {
                                $inbounds[$protoLower][] = $item;
                            }
                        }
                    }
                }
            }

            if (!empty($inbounds)) return $inbounds;
        }

        return [];
    }

    public function createUser(array $payload): array {
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'error' => $this->lastError ?: 'عدم موفقیت در احراز هویت با سرور مرزبان',
                'uuid' => '',
                'sublink' => ''
            ];
        }

        // Fetch dynamic active inbounds from server (Reality, VMess, Trojan, etc.)
        $allInbounds = $this->getInbounds();
        $inbounds = $allInbounds;

        // If specific selected inbounds were passed in payload, filter to only those tags
        if (!empty($payload['selected_inbounds'])) {
            $selectedTags = is_array($payload['selected_inbounds']) 
                ? $payload['selected_inbounds'] 
                : json_decode($payload['selected_inbounds'], true);

            if (!empty($selectedTags) && is_array($selectedTags)) {
                $filtered = [];
                foreach ($allInbounds as $proto => $tags) {
                    foreach ($tags as $t) {
                        if (in_array($t, $selectedTags)) {
                            $filtered[$proto][] = $t;
                        }
                    }
                }
                if (!empty($filtered)) {
                    $inbounds = $filtered;
                }
            }
        }

        // Detect if any active VLESS inbound uses reality
        $hasReality = false;
        $detailed = $this->getDetailedInbounds();
        foreach ($detailed as $d) {
            if ($d['protocol'] === 'vless' && ($d['tls'] === 'reality' || str_contains(strtolower($d['tag']), 'reality'))) {
                $hasReality = true;
                break;
            }
        }

        $proxies = [
            'vless' => ['id' => $payload['uuid'], 'flow' => $hasReality ? 'xtls-rprx-vision' : ''],
            'vmess' => ['id' => $payload['uuid']],
            'trojan' => ['password' => $payload['password'] ?? $payload['uuid']],
            'shadowsocks' => ['password' => $payload['password'] ?? $payload['uuid'], 'method' => 'chacha20-ietf-poly1305']
        ];

        // Filter proxies to only protocols present in active inbounds if inbounds were returned
        if (!empty($inbounds)) {
            $filteredProxies = [];
            foreach ($inbounds as $proto => $tags) {
                $protoLower = strtolower($proto);
                if (isset($proxies[$protoLower])) {
                    $filteredProxies[$protoLower] = $proxies[$protoLower];
                }
            }
            if (!empty($filteredProxies)) {
                $proxies = $filteredProxies;
            }
        }

        $body = [
            'username' => $payload['username'],
            'proxies' => $proxies,
            'expire' => $payload['expire_timestamp'] ?? null,
            'data_limit' => $payload['traffic_limit_bytes'] ?? 0,
            'data_limit_reset_strategy' => 'no_reset',
            'status' => 'active',
            'note' => 'Provisioned automatically via Connectix Panel'
        ];

        // Only attach inbounds map if we fetched non-empty inbounds from Marzban API
        if (!empty($inbounds)) {
            $body['inbounds'] = $inbounds;
        }

        $res = $this->request($this->apiPrefix . '/user', 'POST', $body);
        if ($res['success']) {
            $data = $res['data'] ?? [];

            // Fetch live user to get links resolved by core or decoded from sublink
            $live = $this->getUser($payload['username']);
            if ($live && !empty($live['links'])) {
                $links = $live['links'];
            } else {
                $links = $data['links'] ?? [];
            }

            $subUrl = (!empty($live['subscription_url'])) ? $live['subscription_url'] : ($data['subscription_url'] ?? '');

            if (empty($subUrl) && !empty($links)) {
                $subUrl = $links[0];
            }

            $domainBase = !empty($this->subDomain) ? (str_starts_with($this->subDomain, 'http') ? rtrim($this->subDomain, '/') : ('https://' . rtrim($this->subDomain, '/'))) : $this->baseUrl;
            if (!empty($subUrl) && str_starts_with($subUrl, '/')) {
                $subUrl = $domainBase . $subUrl;
            } else {
                $subUrl = $this->applySubDomain($subUrl);
            }

            return [
                'success' => true,
                'uuid' => $payload['uuid'],
                'sublink' => $subUrl ?: ($domainBase . '/sub/' . ($payload['sub_token'] ?? $payload['uuid'])),
                'links' => $links,
                'vless_link' => $links[0] ?? '',
                'error' => null
            ];
        }

        $errMsg = $res['data']['detail'] ?? $this->lastError ?? 'خطا در ایجاد کاربر مرزبان';
        return [
            'success' => false,
            'error' => is_array($errMsg) ? json_encode($errMsg, JSON_UNESCAPED_UNICODE) : $errMsg,
            'uuid' => '',
            'sublink' => ''
        ];
    }

    public function getUser(string $username): ?array {
        if (!$this->authenticate()) return null;
        $res = $this->request($this->apiPrefix . '/user/' . urlencode($username));
        if ($res['success'] && !empty($res['data'])) {
            $u = $res['data'];
            $expRaw = $u['expire'] ?? null;
            $expAt = null;
            if (!empty($expRaw)) {
                if (is_numeric($expRaw)) {
                    $expAt = ((int)$expRaw > 0) ? date('Y-m-d H:i:s', (int)$expRaw) : null;
                } else {
                    $ts = strtotime((string)$expRaw);
                    $expAt = ($ts !== false && $ts > 0) ? date('Y-m-d H:i:s', $ts) : (string)$expRaw;
                }
            }

            $links = $u['links'] ?? [];

            // If user has no links, auto-attach all active server inbounds and proxies to user so Marzban generates all configs
            if (empty($links)) {
                $allInbounds = $this->getInbounds();
                if (!empty($allInbounds)) {
                    $updPayload = ['inbounds' => $allInbounds];
                    $proxies = $u['proxies'] ?? [];
                    if (empty($proxies)) {
                        $uuid = (!empty($u['proxies']['vless']['id'])) ? $u['proxies']['vless']['id'] : Helpers::generateUUID();
                        $updPayload['proxies'] = [
                            'vless' => ['id' => $uuid],
                            'vmess' => ['id' => $uuid],
                            'trojan' => ['password' => $uuid],
                            'shadowsocks' => ['password' => $uuid, 'method' => 'chacha20-ietf-poly1305']
                        ];
                    }
                    $updRes = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'PUT', $updPayload);
                    if ($updRes['success'] && !empty($updRes['data']['links'])) {
                        $links = $updRes['data']['links'];
                    }
                }
            }

            $subUrl = $u['subscription_url'] ?? '';
            $domainBase = !empty($this->subDomain) ? (str_starts_with($this->subDomain, 'http') ? rtrim($this->subDomain, '/') : ('https://' . rtrim($this->subDomain, '/'))) : $this->baseUrl;
            if (!empty($subUrl) && str_starts_with($subUrl, '/')) {
                $subUrl = $domainBase . $subUrl;
            } else {
                $subUrl = $this->applySubDomain($subUrl);
            }

            // If user links are still empty in Marzban/PasarGuard API response, fetch configs from the subscription URL directly!
            if (empty($links) && !empty($subUrl) && !Helpers::isPanelSubUrl($subUrl)) {
                $ch = curl_init($subUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'v2rayNG/1.8.5');
                $subContent = curl_exec($ch);
                $subHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($subHttpCode === 200 && !empty($subContent)) {
                    $decoded = base64_decode($subContent, true);
                    $configText = ($decoded !== false && strlen($decoded) > 10) ? $decoded : $subContent;
                    $lines = preg_split('/[\r\n]+/', trim($configText));
                    $extracted = [];
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (preg_match('/^(vless|vmess|trojan|ss|hy2):\/\//i', $line)) {
                            $extracted[] = $line;
                        }
                    }
                    if (!empty($extracted)) {
                        $links = $extracted;
                    }
                }
            }

            return [
                'traffic_used_bytes' => $u['used_traffic'] ?? 0,
                'traffic_limit_bytes' => $u['data_limit'] ?? 0,
                'expire_at' => $expAt,
                'status' => $u['status'] ?? 'active',
                'online' => ($u['online_at'] ?? 0) > (time() - 300),
                'links' => $links,
                'subscription_url' => $subUrl
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

        $res = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'PUT', [
            'data_limit' => $newLimit,
            'expire' => $newExpire,
            'status' => 'active'
        ]);

        return $res['success'];
    }

    public function deleteUser(string $username): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'DELETE');
        return $res['success'];
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        if (!$this->authenticate()) return false;
        $res = $this->request($this->apiPrefix . '/user/' . urlencode($username), 'PUT', [
            'status' => $active ? 'active' : 'disabled'
        ]);
        return $res['success'];
    }

    public function getNodeStats(): array {
        if (!$this->authenticate()) {
            return ['status' => 'offline', 'users' => 0, 'cpu' => '0%', 'ram' => '0%'];
        }
        $res = $this->request($this->apiPrefix . '/system');
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

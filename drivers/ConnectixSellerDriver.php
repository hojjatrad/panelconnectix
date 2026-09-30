<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class ConnectixSellerDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $token;
    private ?string $username;
    private ?string $password;
    private ?string $lastError = null;
    private int $timeout = 15;

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $token = null, ?string $subDomain = null) {
        // Normalize baseUrl - Connectix seller API always lives at api.connectix.vip
        $clean = trim($baseUrl);
        if (stripos($clean, 'api.connectix.vip') !== false) {
            $this->baseUrl = 'https://api.connectix.vip';
        } elseif (stripos($clean, 'seller-api.connectix.vip') !== false) {
            $this->baseUrl = 'https://api.connectix.vip';
        } else {
            // fallback: try to extract domain, but force to api.connectix.vip if token looks like connectix seller token (40 chars alphanumeric)
            $this->baseUrl = 'https://api.connectix.vip';
        }
        $this->token = $token ? trim($token) : null;
        $this->username = $username ? trim($username) : null;
        $this->password = $password ? trim($password) : null;
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: ConnectixPanel/1.0'
        ];
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'X-Api-Token: ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null && in_array($method, ['POST','PUT','PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->lastError = "خطای اتصال Connectix Seller (cURL): $err";
            return ['success'=>false, 'code'=>$httpCode, 'error'=>$err, 'data'=>null, 'raw'=>$response];
        }

        $decoded = json_decode((string)$response, true);
        $isSuccess = ($httpCode >=200 && $httpCode <300);

        if (!$isSuccess) {
            $msg = $decoded['message'] ?? $decoded['detail'] ?? "HTTP $httpCode";
            if (is_array($msg)) $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            $this->lastError = "Connectix Seller API: $msg (کد $httpCode)";
        }

        return [
            'success'=>$isSuccess,
            'code'=>$httpCode,
            'data'=>$decoded,
            'raw'=>$response
        ];
    }

    public function authenticate(): bool {
        if (empty($this->token)) {
            $this->lastError = "توکن API فروشنده Connectix وارد نشده است.";
            return false;
        }
        // Test with seller-data endpoint - lightweight
        $res = $this->request('/v1/seller/seller-data');
        if ($res['success']) {
            $this->lastError = null;
            return true;
        }
        // Fallback: try clients endpoint
        $res2 = $this->request('/v1/seller/clients?page=1&recordPerPage=1');
        if ($res2['success']) {
            $this->lastError = null;
            return true;
        }
        // If both fail, keep last error from request
        return false;
    }

    public function getNodeStats(): array {
        if (!$this->authenticate()) {
            return ['status'=>'offline', 'users'=>0, 'version'=>'Connectix Seller (عدم اتصال)'];
        }
        // Get total_clients from clients endpoint
        $res = $this->request('/v1/seller/clients?page=1&recordPerPage=1');
        if ($res['success'] && !empty($res['data'])) {
            $total = $res['data']['total_clients'] ?? $res['data']['total'] ?? 0;
            // Also try dashboard stats
            return [
                'status'=>'online',
                'version'=>'Connectix Seller API',
                'users'=>(int)$total,
                'cpu'=>'0%',
                'ram'=>'0 GB'
            ];
        }
        return ['status'=>'online', 'users'=>0, 'version'=>'Connectix Seller API'];
    }

    private function parseTraffic(string $trafficStr): array {
        // Format "0.9/10" or "0/0.1" - GB
        $parts = explode('/', $trafficStr);
        $usedGb = 0;
        $limitGb = 0;
        if (count($parts) >= 2) {
            $usedGb = floatval(trim($parts[0]));
            $limitGb = floatval(trim($parts[1]));
        } elseif (count($parts) == 1) {
            $usedGb = floatval(trim($parts[0]));
        }
        $usedBytes = (int)round($usedGb * 1073741824);
        $limitBytes = (int)round($limitGb * 1073741824);
        return [$usedBytes, $limitBytes];
    }

    private function parseExpire(?string $expireDate, ?string $remainsDays): ?string {
        if (!empty($remainsDays) && is_numeric($remainsDays)) {
            $days = floatval($remainsDays);
            if ($days > 0) {
                return date('Y-m-d H:i:s', time() + (int)round($days * 86400));
            } else {
                // expired
                return date('Y-m-d H:i:s', time() - 86400);
            }
        }
        // expire_date is Jalali like "1405-8-9" or "N/A"
        if (empty($expireDate) || $expireDate === 'N/A' || $expireDate === 'N/A ') {
            return null;
        }
        // If it's Jalali, we can't easily convert, but try to parse as Gregorian fallback
        // For now, if contains 1400-1410, treat as far future and use remains_days already handled
        // If it's Gregorian YYYY-MM-DD, parse
        $ts = strtotime($expireDate);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d H:i:s', $ts);
        }
        return null;
    }

    public function listUsers(): array {
        if (!$this->authenticate()) return [];

        $allClients = [];
        $page = 1;
        $perPage = 100;
        $totalPages = 1;

        do {
            $res = $this->request("/v1/seller/clients?page=$page&recordPerPage=$perPage");
            if (!$res['success'] || empty($res['data'])) break;

            $data = $res['data'];
            $clients = $data['clients']['data'] ?? $data['data'] ?? $data['clients'] ?? [];
            if (!is_array($clients)) break;

            foreach ($clients as $c) {
                $allClients[] = $c;
            }

            // Pagination info
            $currentPage = $data['clients']['current_page'] ?? $page;
            $lastPage = $data['clients']['last_page'] ?? null;
            $total = $data['total_clients'] ?? 0;

            if ($lastPage) {
                $totalPages = $lastPage;
            } elseif ($total > 0) {
                $totalPages = (int)ceil($total / $perPage);
            } else {
                $totalPages = 1;
            }

            $page++;
            if ($page > $totalPages) break;
            if ($page > 20) break; // safety max 2000 clients
        } while (true);

        $out = [];
        foreach ($allClients as $c) {
            if (!is_array($c) || empty($c['username'])) continue;

            $username = (string)$c['username'];
            $isActive = (int)($c['is_active'] ?? 1);
            $isExpired = (bool)($c['is_expired'] ?? false);
            $connStatus = $c['connection_status'] ?? 'black'; // black, warning, success?

            $status = 'active';
            if ($isExpired) $status = 'expired';
            elseif ($isActive === 0) $status = 'disabled';
            elseif ($connStatus === 'black') $status = 'expired'; // guess
            // Check traffic limit
            [$usedBytes, $limitBytes] = $this->parseTraffic($c['used_traffic'] ?? '0/0');
            if ($limitBytes > 0 && $usedBytes >= $limitBytes) {
                $status = 'limited';
            }

            $online = false;
            // If used_devices not empty or last_active_date recent
            if (!empty($c['used_devices']) && is_array($c['used_devices']) && count($c['used_devices']) > 0) {
                $online = true;
            }
            // If connection_status warning/success might indicate online
            if (in_array($connStatus, ['success','warning','green'])) {
                // warning might be low traffic, but still consider maybe online if last_active recent
                // Use last_active_date if within 5 minutes? But date is Jalali, hard to parse.
                // For now, treat success as online
                if ($connStatus === 'success') $online = true;
            }

            $expireAt = $this->parseExpire($c['expire_date'] ?? null, $c['remains_days'] ?? null);

            $subUrl = $c['subscription_link'] ?? '';
            $links = [];
            if (!empty($c['outline_link'])) $links[] = $c['outline_link'];
            // subscription_link is not direct config, but we keep it as sub url
            // If outline_link is ss://, it's a valid link

            $out[] = [
                'username' => $username,
                'status' => $status,
                'online' => $online,
                'traffic_used_bytes' => $usedBytes,
                'traffic_limit_bytes' => $limitBytes,
                'expire_at' => $expireAt,
                'subscription_url' => (string)$subUrl,
                'links' => array_values(array_filter($links)),
                // Extra fields for UI
                'name' => $c['name'] ?? '',
                'plan_name' => $c['plan_name'] ?? '',
                'group_name' => $c['group_name'] ?? '',
                'used_traffic_str' => $c['used_traffic'] ?? '',
                'remains_days' => $c['remains_days'] ?? '',
                'id' => $c['id'] ?? '',
            ];
        }

        return $out;
    }

    // The following methods are not fully supported for Connectix Seller API yet
    // We return false or try best effort

    public function createUser(array $payload): array {
        // TODO: Implement via POST /v1/seller/clients - needs plan_id, name, etc.
        // For now, we don't have plan mapping from traffic limit to plan_id
        // So return error instructing to create manually in seller panel
        return [
            'success'=>false,
            'error'=>'ایجاد کاربر مستقیم از طریق API فروشنده Connectix هنوز پیاده‌سازی نشده است. لطفاً از پنل seller.connectix.vip کلاینت بسازید و سپس در این پنل Sync کنید.',
            'uuid'=>'',
            'sublink'=>''
        ];
    }

    public function getUser(string $username): ?array {
        $users = $this->listUsers();
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                return [
                    'traffic_used_bytes'=>$u['traffic_used_bytes'],
                    'traffic_limit_bytes'=>$u['traffic_limit_bytes'],
                    'expire_at'=>$u['expire_at'],
                    'status'=>$u['status'],
                    'online'=>$u['online'],
                    'links'=>$u['links'],
                    'subscription_url'=>$u['subscription_url']
                ];
            }
        }
        return null;
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        // Not implemented
        $this->lastError = "تمدید از طریق API فروشنده Connectix پشتیبانی نمی‌شود.";
        return false;
    }

    public function updateUser(string $username, array $params): bool {
        $this->lastError = "ویرایش از طریق API فروشنده Connectix پشتیبانی نمی‌شود.";
        return false;
    }

    public function deleteUser(string $username): bool {
        // Try to find id and delete via API
        // Need to discover delete endpoint: try DELETE /v1/seller/clients/{id}
        $users = $this->listUsers();
        $targetId = null;
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $targetId = $u['id'] ?? null;
                break;
            }
        }
        if (!$targetId) {
            $this->lastError = "کاربر $username در لیست Connectix یافت نشد.";
            return false;
        }
        $res = $this->request("/v1/seller/clients/$targetId", 'DELETE');
        return $res['success'];
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        $this->lastError = "تغییر وضعیت از طریق API فروشنده Connectix پشتیبانی نمی‌شود.";
        return false;
    }
}

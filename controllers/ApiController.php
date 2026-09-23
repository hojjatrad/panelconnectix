<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Provisioner.php';

class ApiController {
    /**
     * Authenticate API Token from Bearer header or ?token= param
     */
    private static function authenticate(): ?array {
        $token = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (!empty($_GET['api_token'])) {
            $token = trim($_GET['api_token']);
        } elseif (!empty($_POST['api_token'])) {
            $token = trim($_POST['api_token']);
        }

        if (empty($token)) {
            self::jsonError('کلید دسترسی API (Bearer Token) ارائه نشده است.', 401);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ? AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            self::jsonError('کلید API نامعتبر است یا حساب کاربری غیرفعال می‌باشد.', 403);
        }

        return $user;
    }

    private static function jsonSuccess(array $data = [], string $message = 'عملیات با موفقیت انجام شد'): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private static function jsonError(string $message, int $statusCode = 400): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * GET /api/v1/wallet
     */
    public function getWallet(): void {
        $user = self::authenticate();
        self::jsonSuccess([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'wallet_balance' => (int)$user['wallet_balance'],
            'discount_percent' => (int)$user['discount_percent'],
            'currency' => 'Tomans'
        ]);
    }

    /**
     * GET /api/v1/plans
     */
    public function getPlans(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();

        $tierInfo = Provisioner::getResellerTier((int)$user['id']);
        $discount = (int)$tierInfo['discount'];
        $result = [];
        foreach ($plans as $p) {
            $price = (int)$p['reseller_price'];
            if ($discount > 0) {
                $price = (int)($price - ($price * ($discount / 100)));
            }
            $result[] = [
                'id' => (int)$p['id'],
                'title' => $p['title'],
                'traffic_gb' => (int)$p['traffic_gb'],
                'duration_days' => (int)$p['duration_days'],
                'price' => $price,
                'server_group' => $p['server_group'],
                'is_free' => (bool)$p['is_free']
            ];
        }

        self::jsonSuccess($result);
    }

    /**
     * POST /api/v1/client/create
     */
    public function createClient(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();

        $raw = file_get_contents('php://input');
        $body = !empty($raw) ? json_decode($raw, true) : $_POST;

        $planId = (int)($body['plan_id'] ?? 0);
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');
        $serverId = !empty($body['server_id']) ? (int)$body['server_id'] : null;
        $note = trim($body['note'] ?? 'Created via API');

        if ($planId <= 0) {
            self::jsonError('شناسه پلن (plan_id) الزامی است.');
        }

        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();
        if (!$plan) {
            self::jsonError('پلن یافت نشد یا غیرفعال است.');
        }

        // Price Calculation with Tiered Discount
        $tierInfo = Provisioner::getResellerTier((int)$user['id']);
        $discount = (int)$tierInfo['discount'];
        $cost = (int)$plan['reseller_price'];
        if ($discount > 0) {
            $cost = (int)($cost - ($cost * ($discount / 100)));
        }

        if ($user['role'] !== 'admin' && $plan['is_free'] == 0 && $user['wallet_balance'] < $cost) {
            self::jsonError("موجودی کیف پول ناکافی است. موجودی فعلی: {$user['wallet_balance']} | هزینه پلن: {$cost}");
        }

        // Provision
        $prov = Provisioner::createClient($planId, $serverId, $username ?: null, $password ?: null, (int)$user['id'], $note);
        if (!$prov['success']) {
            self::jsonError($prov['error']);
        }

        // Deduct wallet if not admin
        if ($user['role'] !== 'admin' && $cost > 0) {
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$cost, $user['id']]);
            $newBal = $user['wallet_balance'] - $cost;
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, status) VALUES (?, ?, ?, 'plan_purchase', ?, 'completed')")
                ->execute([$user['id'], -$cost, $newBal, "خرید از طریق API برای {$prov['username']}"]);
        }

        self::jsonSuccess($prov, 'کلاینت با موفقیت ایجاد و فعال شد.');
    }

    /**
     * GET /api/v1/client/info
     */
    public function getClientInfo(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();

        $query = trim($_GET['username'] ?? $_GET['token'] ?? '');
        if (empty($query)) {
            self::jsonError('نام کاربری یا توکن الزامی است.');
        }

        $whereUser = ($user['role'] === 'admin') ? "1=1" : "c.reseller_id = " . intval($user['id']);

        $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name 
                               FROM clients c 
                               LEFT JOIN plans p ON c.plan_id = p.id 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               WHERE (c.username = ? OR c.sub_token = ?) AND $whereUser");
        $stmt->execute([$query, $query]);
        $client = $stmt->fetch();

        if (!$client) {
            self::jsonError('کلاینت یافت نشد یا دسترسی غیرمجاز است.', 404);
        }

        $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

        self::jsonSuccess([
            'id' => (int)$client['id'],
            'username' => $client['username'],
            'status' => $client['status'],
            'plan_title' => $client['plan_title'],
            'server_name' => $client['server_name'],
            'traffic_limit_bytes' => (int)$client['traffic_limit_bytes'],
            'traffic_used_bytes' => (int)$client['traffic_used_bytes'],
            'traffic_limit_gb' => round($client['traffic_limit_bytes'] / (1024*1024*1024), 2),
            'traffic_used_gb' => round($client['traffic_used_bytes'] / (1024*1024*1024), 2),
            'expire_at' => $client['expire_at'],
            'sub_url' => $subUrl
        ]);
    }

    /**
     * Authenticate Client App by Bearer Token or Query Param
     */
    private static function authenticateClientApp(): array {
        $token = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (!empty($_GET['auth_token'])) {
            $token = trim($_GET['auth_token']);
        } elseif (!empty($_GET['token'])) {
            $token = trim($_GET['token']);
        } elseif (!empty($_POST['auth_token'])) {
            $token = trim($_POST['auth_token']);
        }

        if (empty($token)) {
            self::jsonError('توکن احراز هویت اپلیکیشن (Bearer Auth Token) ارائه نشده است.', 401);
        }

        $pdo = Database::getConnection();

        // 1. Direct sub_token lookup
        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name, s.sub_domain, s.api_url,
                                      COALESCE(u.brand_name, b.brand_name, 'Connectix VPN') as brand_name,
                                      COALESCE(u.logo_url, b.logo_url) as logo_url,
                                      COALESCE(u.theme_color, b.theme_color, 'violet') as theme_color,
                                      COALESCE(u.support_username, b.telegram_support, '@Support') as telegram_support,
                                      b.whatsapp_support, b.renewal_url, p.title as plan_title
                               FROM clients c 
                               LEFT JOIN users u ON u.id = c.reseller_id
                               LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id
                               LEFT JOIN server_nodes s ON c.server_id = s.id
                               LEFT JOIN plans p ON c.plan_id = p.id
                               WHERE c.sub_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Hash token format 'app_<hash>'
        if (!$client && str_starts_with($token, 'app_')) {
            $hashPart = substr($token, 4);
            $allClients = $pdo->query("SELECT c.*, s.name as server_name, s.sub_domain, s.api_url,
                                              COALESCE(u.brand_name, b.brand_name, 'Connectix VPN') as brand_name,
                                              COALESCE(u.logo_url, b.logo_url) as logo_url,
                                              COALESCE(u.theme_color, b.theme_color, 'violet') as theme_color,
                                              COALESCE(u.support_username, b.telegram_support, '@Support') as telegram_support,
                                              b.whatsapp_support, b.renewal_url, p.title as plan_title
                                       FROM clients c 
                                       LEFT JOIN users u ON u.id = c.reseller_id
                                       LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id
                                       LEFT JOIN server_nodes s ON c.server_id = s.id
                                       LEFT JOIN plans p ON c.plan_id = p.id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allClients as $ac) {
                $calc = hash_hmac('sha256', $ac['sub_token'] . '_' . $ac['id'], 'connectix_app_key_2026');
                if (hash_equals($calc, $hashPart)) {
                    $client = $ac;
                    break;
                }
            }
        }

        if (!$client) {
            self::jsonError('توکن معتبر نمی‌باشد یا حساب یافت نشد.', 401);
        }

        return $client;
    }

    /**
     * POST /api/v1/app/login
     * Authenticate client with Username & Password
     */
    public function appLogin(): void {
        $raw = file_get_contents('php://input');
        $body = !empty($raw) ? json_decode($raw, true) : $_POST;

        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if (empty($username) || empty($password)) {
            self::jsonError('لطفاً نام کاربری و رمز عبور را وارد فرمایید.', 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT c.*, 
                                      s.name as server_name, s.sub_domain, s.api_url,
                                      COALESCE(u.brand_name, b.brand_name, 'Connectix VPN') as brand_name,
                                      COALESCE(u.logo_url, b.logo_url) as logo_url,
                                      COALESCE(u.theme_color, b.theme_color, 'violet') as theme_color,
                                      COALESCE(u.support_username, b.telegram_support, '@Support') as telegram_support,
                                      b.whatsapp_support,
                                      b.renewal_url,
                                      p.title as plan_title
                               FROM clients c 
                               LEFT JOIN users u ON u.id = c.reseller_id
                               LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id
                               LEFT JOIN server_nodes s ON c.server_id = s.id
                               LEFT JOIN plans p ON c.plan_id = p.id
                               WHERE c.username = ? LIMIT 1");
        $stmt->execute([$username]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            self::jsonError('نام کاربری یا کلمه عبور اشتباه است.', 401);
        }

        // Verify password
        $clientPwd = (string)($client['password'] ?? '');
        $pwdValid = ($clientPwd === $password) 
                 || (empty($clientPwd) && $password === '123456')
                 || (password_verify($password, $clientPwd));

        if (!$pwdValid) {
            self::jsonError('نام کاربری یا کلمه عبور اشتباه است.', 401);
        }

        // Account status check
        $now = time();
        $isExpired = (!empty($client['expire_at']) && strtotime($client['expire_at']) < $now);
        $isTrafficExhausted = ($client['traffic_limit_bytes'] > 0 && $client['traffic_used_bytes'] >= $client['traffic_limit_bytes']);
        $isDisabled = ($client['status'] === 'disabled');

        $computedStatus = 'active';
        if ($isDisabled) {
            $computedStatus = 'disabled';
        } elseif ($isExpired) {
            $computedStatus = 'expired';
        } elseif ($isTrafficExhausted) {
            $computedStatus = 'traffic_ended';
        }

        // Synchronize live stats from real node (Marzban / Pasargad / 3x-ui)
        if (!empty($client['server_id'])) {
            try {
                $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $stmtNode->execute([(int)$client['server_id']]);
                $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                if ($node && $node['driver'] !== 'mock') {
                    $driver = DriverFactory::create($node);
                    $liveData = $driver->getUser($client['username']);
                    if ($liveData) {
                        $liveUsed = (int)($liveData['traffic_used_bytes'] ?? $client['traffic_used_bytes']);
                        $liveLimit = (int)($liveData['traffic_limit_bytes'] ?? $client['traffic_limit_bytes']);
                        $liveExpire = !empty($liveData['expire_at']) ? $liveData['expire_at'] : $client['expire_at'];
                        
                        $client['traffic_used_bytes'] = $liveUsed;
                        $client['traffic_limit_bytes'] = $liveLimit;
                        $client['expire_at'] = $liveExpire;

                        $pdo->prepare("UPDATE clients SET traffic_used_bytes = ?, traffic_limit_bytes = ?, expire_at = ? WHERE id = ?")
                            ->execute([$liveUsed, $liveLimit, $liveExpire, $client['id']]);
                    }
                }
            } catch (Throwable $e) {}
        }

        // Generate App Token
        $appToken = 'app_' . hash_hmac('sha256', $client['sub_token'] . '_' . $client['id'], 'connectix_app_key_2026');

        $usedBytes = (int)$client['traffic_used_bytes'];
        $limitBytes = (int)$client['traffic_limit_bytes'];
        $remainBytes = max(0, $limitBytes - $usedBytes);
        $usagePercent = ($limitBytes > 0) ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0;
        $daysRemaining = Helpers::daysRemaining($client['expire_at']);

        // Update last connected
        $pdo->prepare("UPDATE clients SET last_connected_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$client['id']]);

        self::jsonSuccess([
            'auth_token' => $appToken,
            'client' => [
                'id' => (int)$client['id'],
                'username' => $client['username'],
                'status' => $computedStatus,
                'plan_title' => $client['plan_title'] ?? 'اشتراک اختصاصی',
                'server_name' => $client['server_name'] ?? 'سرور ابری پرسرعت',
                'traffic_total_gb' => round($limitBytes / (1024 * 1024 * 1024), 2),
                'traffic_used_gb' => round($usedBytes / (1024 * 1024 * 1024), 2),
                'traffic_remaining_gb' => round($remainBytes / (1024 * 1024 * 1024), 2),
                'traffic_used_bytes' => $usedBytes,
                'traffic_limit_bytes' => $limitBytes,
                'usage_percent' => $usagePercent,
                'expire_at' => $client['expire_at'],
                'days_remaining' => $daysRemaining,
                'ip_limit' => (int)($client['ip_limit'] ?? 2),
                'sub_url' => Helpers::subUrl($client['sub_token'])
            ],
            'branding' => [
                'app_name' => $client['brand_name'] ?? 'Connectix VPN',
                'logo_url' => $client['logo_url'] ?? '',
                'theme_color' => $client['theme_color'] ?? 'violet',
                'telegram_support' => $client['telegram_support'] ?? '@Support',
                'whatsapp_support' => $client['whatsapp_support'] ?? '',
                'renewal_url' => $client['renewal_url'] ?? Helpers::subUrl($client['sub_token'])
            ]
        ], 'ورود به اپلیکیشن با موفقیت انجام شد.');
    }

    /**
     * GET /api/v1/app/profile
     * Fetch Live Real-Time Status & Quota
     */
    public function appProfile(): void {
        $client = self::authenticateClientApp();
        $pdo = Database::getConnection();

        // Synchronize live stats from real node
        if (!empty($client['server_id'])) {
            try {
                $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $stmtNode->execute([(int)$client['server_id']]);
                $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                if ($node && $node['driver'] !== 'mock') {
                    $driver = DriverFactory::create($node);
                    $liveData = $driver->getUser($client['username']);
                    if ($liveData) {
                        $liveUsed = (int)($liveData['traffic_used_bytes'] ?? $client['traffic_used_bytes']);
                        $liveLimit = (int)($liveData['traffic_limit_bytes'] ?? $client['traffic_limit_bytes']);
                        $liveExpire = !empty($liveData['expire_at']) ? $liveData['expire_at'] : $client['expire_at'];
                        
                        $client['traffic_used_bytes'] = $liveUsed;
                        $client['traffic_limit_bytes'] = $liveLimit;
                        $client['expire_at'] = $liveExpire;

                        $pdo->prepare("UPDATE clients SET traffic_used_bytes = ?, traffic_limit_bytes = ?, expire_at = ? WHERE id = ?")
                            ->execute([$liveUsed, $liveLimit, $liveExpire, $client['id']]);
                    }
                }
            } catch (Throwable $e) {}
        }

        $usedBytes = (int)$client['traffic_used_bytes'];
        $limitBytes = (int)$client['traffic_limit_bytes'];
        $remainBytes = max(0, $limitBytes - $usedBytes);
        $usagePercent = ($limitBytes > 0) ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0;
        $daysRemaining = Helpers::daysRemaining($client['expire_at']);

        $now = time();
        $isExpired = (!empty($client['expire_at']) && strtotime($client['expire_at']) < $now);
        $isTrafficExhausted = ($limitBytes > 0 && $usedBytes >= $limitBytes);
        $isDisabled = ($client['status'] === 'disabled');

        $computedStatus = 'active';
        if ($isDisabled) $computedStatus = 'disabled';
        elseif ($isExpired) $computedStatus = 'expired';
        elseif ($isTrafficExhausted) $computedStatus = 'traffic_ended';

        self::jsonSuccess([
            'id' => (int)$client['id'],
            'username' => $client['username'],
            'status' => $computedStatus,
            'traffic_total_gb' => round($limitBytes / (1024 * 1024 * 1024), 2),
            'traffic_used_gb' => round($usedBytes / (1024 * 1024 * 1024), 2),
            'traffic_remaining_gb' => round($remainBytes / (1024 * 1024 * 1024), 2),
            'traffic_used_bytes' => $usedBytes,
            'traffic_limit_bytes' => $limitBytes,
            'usage_percent' => $usagePercent,
            'expire_at' => $client['expire_at'],
            'days_remaining' => $daysRemaining,
            'sub_url' => Helpers::subUrl($client['sub_token'])
        ]);
    }

    /**
     * GET /api/v1/app/configs
     * Returns Structured Connection Nodes + Raw Base64 Sublink
     */
    public function appConfigs(): void {
        $client = self::authenticateClientApp();
        $pdo = Database::getConnection();
        require_once __DIR__ . '/SublinkController.php';

        $realLinks = [];

        // 1. First priority: Check stored node_sublink directly
        if (!empty($client['node_sublink'])) {
            $ch = curl_init($client['node_sublink']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'v2rayNG/1.8.5');
            $subContent = curl_exec($ch);
            curl_close($ch);
            if (!empty($subContent)) {
                $decoded = base64_decode(trim($subContent), true) ?: $subContent;
                $lines = array_filter(array_map('trim', explode("\n", $decoded)));
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'vless://') || str_starts_with($line, 'vmess://') || str_starts_with($line, 'trojan://') || str_starts_with($line, 'ss://')) {
                        $realLinks[] = $line;
                    }
                }
            }
        }

        // 2. Second priority: Query driver directly
        if (empty($realLinks) && !empty($client['server_id'])) {
            try {
                $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $stmtNode->execute([(int)$client['server_id']]);
                $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                if ($node && $node['driver'] !== 'mock') {
                    $driver = DriverFactory::create($node);
                    $liveData = $driver->getUser($client['username']);
                    if (!empty($liveData['links']) && is_array($liveData['links'])) {
                        $realLinks = $liveData['links'];
                    } elseif (!empty($liveData['subscription_url'])) {
                        // Fetch sublink contents
                        $ch = curl_init($liveData['subscription_url']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        $subContent = curl_exec($ch);
                        curl_close($ch);
                        if (!empty($subContent)) {
                            $decoded = base64_decode(trim($subContent), true) ?: $subContent;
                            $lines = array_filter(array_map('trim', explode("\n", $decoded)));
                            foreach ($lines as $line) {
                                if (str_starts_with($line, 'vless://') || str_starts_with($line, 'vmess://') || str_starts_with($line, 'trojan://')) {
                                    $realLinks[] = $line;
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        $serverList = [];
        if (!empty($realLinks)) {
            $idx = 1;
            foreach ($realLinks as $link) {
                $parsed = parse_url($link);
                $proto = $parsed['scheme'] ?? 'vless';
                $fragment = !empty($parsed['fragment']) ? urldecode($parsed['fragment']) : "سرور پرسرعت #$idx";
                
                $serverList[] = [
                    'id' => 'node_' . $idx,
                    'name' => $fragment,
                    'country_name' => 'اروپا',
                    'country_code' => 'DE',
                    'flag' => '🌐',
                    'protocol' => $proto,
                    'operator_tag' => 'all',
                    'operator_name' => 'تمام اپراتورها',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $link,
                    'is_recommended' => ($idx === 1)
                ];
                $idx++;
            }
        }

        if (empty($serverList)) {
            $rawConfigs = SublinkController::buildConfigs($client);
            $rawSublink = base64_encode(implode("\n", array_values($rawConfigs)));

            $serverList = [
                [
                    'id' => 'mci_reality_de',
                    'name' => '🇩🇪 آلمان - همراه اول (Reality VIP)',
                    'country_name' => 'آلمان',
                    'country_code' => 'DE',
                    'flag' => '🇩🇪',
                    'protocol' => 'vless',
                    'operator_tag' => 'mci',
                    'operator_name' => 'همراه اول',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $rawConfigs['mci_reality'] ?? '',
                    'is_recommended' => true
                ],
                [
                    'id' => 'irancell_cdn_de',
                    'name' => '🇩🇪 آلمان - ایرانسل (Reality VIP)',
                    'country_name' => 'آلمان',
                    'country_code' => 'DE',
                    'flag' => '🇩🇪',
                    'protocol' => 'vless',
                    'operator_tag' => 'irancell',
                    'operator_name' => 'ایرانسل',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $rawConfigs['irancell_cdn'] ?? '',
                    'is_recommended' => true
                ],
                [
                    'id' => 'rightel_trojan_de',
                    'name' => '🇳🇱 هلند - رایتل و شاتل (Reality VIP)',
                    'country_name' => 'هلند',
                    'country_code' => 'NL',
                    'flag' => '🇳🇱',
                    'protocol' => 'vless',
                    'operator_tag' => 'rightel',
                    'operator_name' => 'رایتل و شاتل',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $rawConfigs['rightel_trojan'] ?? '',
                    'is_recommended' => false
                ],
                [
                    'id' => 'wifi_vmess_de',
                    'name' => '🇫🇮 فنلاند - اینترنت خانگی و مخابرات (Reality)',
                    'country_name' => 'فنلاند',
                    'country_code' => 'FI',
                    'flag' => '🇫🇮',
                    'protocol' => 'vless',
                    'operator_tag' => 'wifi',
                    'operator_name' => 'مخابرات و وای‌فای',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $rawConfigs['wifi_vmess'] ?? '',
                    'is_recommended' => false
                ],
                [
                    'id' => 'gaming_fast_de',
                    'name' => '🇹🇷 ترکیه - پینگ پایین گیمینگ و استریم (Reality)',
                    'country_name' => 'ترکیه',
                    'country_code' => 'TR',
                    'flag' => '🇹🇷',
                    'protocol' => 'vless',
                    'operator_tag' => 'all',
                    'operator_name' => 'گیمینگ پینگ پایین',
                    'ping_url' => 'https://www.google.com/generate_204',
                    'config_uri' => $rawConfigs['gaming_fast'] ?? '',
                    'is_recommended' => false
                ]
            ];
        } else {
            $rawSublink = base64_encode(implode("\n", array_column($serverList, 'config_uri')));
        }

        self::jsonSuccess([
            'servers' => $serverList,
            'raw_sublink_base64' => $rawSublink,
            'sub_url' => Helpers::subUrl($client['sub_token']),
            'total_servers' => count($serverList)
        ]);
    }

    /**
     * GET /api/v1/app/announcements
     */
    public function appAnnouncements(): void {
        $client = self::authenticateClientApp();
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT * FROM notifications WHERE is_active = 1 ORDER BY id DESC LIMIT 5");
        $notes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $list = [];
        foreach ($notes as $n) {
            $list[] = [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'message' => $n['message'],
                'type' => $n['type'] ?? 'info',
                'created_at' => $n['created_at']
            ];
        }

        self::jsonSuccess([
            'announcements' => $list
        ]);
    }

    /**
     * POST /api/v1/app/feedback
     */
    public function appFeedback(): void {
        $client = self::authenticateClientApp();
        $raw = file_get_contents('php://input');
        $body = !empty($raw) ? json_decode($raw, true) : $_POST;

        $subject = trim($body['subject'] ?? 'گزارش از اپلیکیشن');
        $message = trim($body['message'] ?? '');
        $deviceLog = trim($body['device_log'] ?? '');

        if (empty($message)) {
            self::jsonError('متن پیام یا گزارش خطا نمی‌تواند خالی باشد.');
        }

        $fullMsg = $message;
        if (!empty($deviceLog)) {
            $fullMsg .= "\n\n--- لاگ دستگاه ---\n" . $deviceLog;
        }

        $pdo = Database::getConnection();
        $pdo->prepare("INSERT INTO tickets (user_id, subject, priority, status, department) VALUES (?, ?, 'medium', 'open', 'support')")
            ->execute([$client['reseller_id'] ?: 1, "{$subject} (کلاینت {$client['username']})"]);
        $ticketId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'client', ?, ?)")
            ->execute([$ticketId, $client['id'], $fullMsg]);

        self::jsonSuccess([
            'ticket_id' => $ticketId
        ], 'گزارش شما با موفقیت ثبت شد و توسط تیم پشتیبانی بررسی خواهد شد.');
    }

    /**
     * Admin View: App API Documentation & Interactive Simulator
     */
    public function showAppApiDoc(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $sampleClient = $pdo->query("SELECT * FROM clients WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $branding = $pdo->query("SELECT * FROM branding_metadata WHERE user_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        require __DIR__ . '/../views/settings/app_api.php';
    }
}

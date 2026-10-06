<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

/**
 * Proxy Controller - v4.0.18
 * Provides SOCKS5, HTTP, MTProto proxies for Telegram and other apps
 * - Free for VPN customers
 * - Sellable as proxy-only plans
 * - Uses existing Xray/Marzban nodes
 */
class ProxyController {
    
    /**
     * Authenticate client app (same as ApiController)
     */
    private static function authenticateClientApp(): array {
        require_once __DIR__ . '/../core/Database.php';
        $pdo = Database::getConnection();
        
        $token = $_GET['auth_token'] ?? $_POST['auth_token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = trim(str_replace('Bearer ', '', $token));
        
        if ($token === '') {
            self::jsonError('توکن احراز هویت یافت نشد', 401);
        }
        
        // Try to find client by auth_token (which is sub_token or uuid)
        $stmt = $pdo->prepare("SELECT c.*, u.telegram_support, u.brand_name, u.logo_url, u.theme_color, u.whatsapp_support, u.renewal_url 
                               FROM clients c 
                               LEFT JOIN users u ON c.reseller_id = u.id 
                               WHERE c.sub_token = ? OR c.uuid = ? LIMIT 1");
        $stmt->execute([$token, $token]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            // Try by username/password via login
            self::jsonError('کلاینت یافت نشد یا توکن نامعتبر است', 401);
        }
        
        if ($client['status'] !== 'active') {
            self::jsonError('اکانت شما فعال نیست: ' . $client['status'], 403);
        }
        
        return $client;
    }
    
    private static function jsonSuccess($data, $message = ''): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private static function jsonError($message, $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * GET /api/v1/app/proxies
     * Returns all proxy configs for authenticated client
     * - Free for VPN customers
     * - Works for proxy-only plans too
     */
    public function appProxies(): void {
        try {
            $client = self::authenticateClientApp();
            $pdo = Database::getConnection();
            
            // Get client's server node
            $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? LIMIT 1");
            $stmtNode->execute([(int)$client['server_id']]);
            $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
            
            if (!$node) {
                // Fallback to first active node
                $node = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 AND driver != 'mock' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$node) {
                self::jsonError('هیچ سرور فعالی یافت نشد', 404);
            }
            
            // Extract host from api_url or sub_domain or host field
            $host = $node['host'] ?? $node['sub_domain'] ?? '';
            if ($host === '' && !empty($node['api_url'])) {
                $parsed = parse_url($node['api_url']);
                $host = $parsed['host'] ?? '';
            }
            if ($host === '') {
                $host = $node['name'] ?? 'proxy.example.com';
            }
            // Clean host (remove port if any)
            $host = trim(str_replace(['http://','https://'], '', $host));
            $host = explode(':', $host)[0];
            $host = explode('/', $host)[0];
            
            $username = $client['username'];
            // Use client's password or generate from username
            $password = $client['password'] ?? $client['uuid'] ?? '';
            if (empty($password) || strlen($password) < 4) {
                $password = substr(md5($client['username'] . $client['uuid']), 0, 12);
            }
            
            // Use server's configured proxy ports or defaults
            $socksPort = (int)($node['socks_port'] ?? 1080);
            $httpPort = (int)($node['http_port'] ?? 8080);
            $mtprotoPort = (int)($node['mtproto_port'] ?? 443);
            if ($socksPort <= 0) $socksPort = 1080;
            if ($httpPort <= 0) $httpPort = 8080;
            if ($mtprotoPort <= 0) $mtprotoPort = 443;
            
            // Generate MTProto secret - use ee + 32 hex chars (fake TLS)
            // Format: ee + 32 hex + 00000000000000000000000000000000 (for fake TLS) or just 32 hex
            $mtprotoSecretFull = $node['mtproto_secret'] ?? '';
            if (empty($mtprotoSecretFull)) {
                $mtprotoSecretFull = 'ee' . substr(md5($client['uuid'] . $host), 0, 32) . '00000000000000000000000000000000';
            }
            $mtprotoSecret = $mtprotoSecretFull;
            $mtprotoSecretShort = substr(md5($client['uuid']), 0, 32);
            if (str_starts_with($mtprotoSecret, 'ee')) {
                $mtprotoSecretShort = substr($mtprotoSecret, 2, 32);
            }
            
            // Check if client is proxy-only plan
            $isProxyOnly = false;
            $planTitle = '';
            if (!empty($client['plan_id'])) {
                $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
                $stmtPlan->execute([(int)$client['plan_id']]);
                $plan = $stmtPlan->fetch(PDO::FETCH_ASSOC);
                if ($plan) {
                    $planTitle = $plan['title'] ?? '';
                    // v4.0.18: Check is_proxy_only column first
                    if (isset($plan['is_proxy_only']) && (int)$plan['is_proxy_only'] === 1) {
                        $isProxyOnly = true;
                    } else {
                        // Fallback: Check if plan is proxy-only by category or title
                        $cat = strtolower($plan['category'] ?? '');
                        $title = strtolower($plan['title'] ?? '');
                        if (str_contains($cat, 'proxy') || str_contains($cat, 'پروکسی') || 
                            str_contains($title, 'proxy') || str_contains($title, 'پروکسی')) {
                            $isProxyOnly = true;
                        }
                    }
                }
            }
            // Also check client-level flag
            if (isset($client['is_proxy_only']) && (int)$client['is_proxy_only'] === 1) {
                $isProxyOnly = true;
            }
            
            // Build proxy list
            $proxies = [
                'client' => [
                    'username' => $client['username'],
                    'is_proxy_only' => $isProxyOnly,
                    'plan_title' => $planTitle,
                    'server_name' => $node['name'],
                    'server_host' => $host,
                ],
                // Local proxies (when VPN connected) - FREE for all VPN customers
                'local' => [
                    'socks' => [
                        'host' => '127.0.0.1',
                        'port' => 10808,
                        'url' => 'socks5://127.0.0.1:10808',
                        'type' => 'socks5',
                        'note' => 'فقط وقتی VPN وصله - برای همین گوشی',
                        'is_free' => true,
                    ],
                    'http' => [
                        'host' => '127.0.0.1',
                        'port' => 10809,
                        'url' => 'http://127.0.0.1:10809',
                        'type' => 'http',
                        'note' => 'برای TV و کنسول از طریق هات‌اسپات: 192.168.43.1:10809',
                        'is_free' => true,
                    ],
                ],
                // Dedicated proxies (without VPN) - FREE for VPN customers, also for proxy-only plans
                'dedicated' => [
                    'socks' => [
                        'host' => $host,
                        'port' => $socksPort,
                        'username' => $username,
                        'password' => $password,
                        'url' => "socks5://{$username}:{$password}@{$host}:{$socksPort}",
                        'type' => 'socks5',
                        'note' => 'بدون نیاز به VPN - برای تلگرام و سایر برنامه‌ها',
                        'is_free' => !$isProxyOnly, // Free for VPN customers
                    ],
                    'http' => [
                        'host' => $host,
                        'port' => $httpPort,
                        'username' => $username,
                        'password' => $password,
                        'url' => "http://{$username}:{$password}@{$host}:{$httpPort}",
                        'type' => 'http',
                        'note' => 'پروکسی HTTP برای مرورگر و سایر برنامه‌ها',
                        'is_free' => !$isProxyOnly,
                    ],
                ],
                // MTProto for Telegram - FREE
                'mtproto' => [
                    'host' => $host,
                    'port' => $mtprotoPort,
                    'secret' => $mtprotoSecret,
                    'secret_short' => $mtprotoSecretShort,
                    'url' => "https://t.me/proxy?server={$host}&port={$mtprotoPort}&secret={$mtprotoSecret}",
                    'tg_url' => "tg://proxy?server={$host}&port={$mtprotoPort}&secret={$mtprotoSecret}",
                    'type' => 'mtproto',
                    'note' => 'مخصوص تلگرام - بدون نیاز به فیلترشکن',
                    'is_free' => true,
                ],
                // Tutorials
                'tutorials' => [
                    'telegram_socks' => [
                        'title' => 'تلگرام با SOCKS5',
                        'steps' => [
                            'تلگرام را باز کنید',
                            'Settings → Data and Storage → Proxy Settings',
                            'Add Proxy → SOCKS5',
                            "Server: {$host}",
                            "Port: {$socksPort}",
                            "Username: {$username}",
                            "Password: {$password}",
                            'Save و فعال کنید'
                        ]
                    ],
                    'telegram_mtproto' => [
                        'title' => 'تلگرام با MTProto',
                        'steps' => [
                            'روی لینک MTProto بزنید',
                            'تلگرام باز میشه و تایید کنید',
                            'یا دستی: Settings → Proxy → Add Proxy → MTProto',
                            "Server: {$host}",
                            "Port: {$mtprotoPort}",
                            "Secret: {$mtprotoSecretShort}"
                        ]
                    ],
                    'browser' => [
                        'title' => 'مرورگر کروم/فایرفاکس',
                        'steps' => [
                            'کروم: Settings → System → Open proxy settings',
                            'یا فایرفاکس: Settings → Network Settings → Manual proxy',
                            "HTTP Proxy: {$host} Port: {$httpPort}",
                            "Username: {$username} Password: {$password}"
                        ]
                    ],
                ],
                'info' => [
                    'free_for_vpn' => 'تمام پروکسی‌ها برای مشتری‌های VPN رایگان است',
                    'sellable' => 'میتوانید پلن پروکسی جدا بفروشید - قیمت ارزان‌تر از VPN',
                    'traffic' => 'ترافیک پروکسی با VPN مشترک حساب میشود (در آینده جدا میشود)',
                ]
            ];
            
            self::jsonSuccess($proxies, 'لیست پروکسی‌ها با موفقیت دریافت شد');
            
        } catch (Throwable $e) {
            error_log("ProxyController appProxies error: " . $e->getMessage());
            self::jsonError('خطا در دریافت پروکسی‌ها: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Admin endpoint to list all proxy configs
     */
    public function adminProxies(): void {
        require_once __DIR__ . '/../core/Auth.php';
        Auth::requireAdmin();
        
        $pdo = Database::getConnection();
        $proxies = $pdo->query("
            SELECT c.username, c.status, s.name as server_name, s.api_url, s.sub_domain, p.title as plan_title
            FROM clients c
            JOIN server_nodes s ON c.server_id = s.id
            LEFT JOIN plans p ON c.plan_id = p.id
            WHERE c.status = 'active'
            ORDER BY c.id DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // v4.0.19 FIX: Use direct require, Helpers::view() doesn't exist
        require __DIR__ . '/../views/proxies/index.php';
    }
}

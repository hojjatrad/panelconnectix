<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class SublinkController {
    public function show(string $token): void {
        $pdo = Database::getConnection();

        // 1. Fetch Client, Server, and Reseller Branding
        $stmt = $pdo->prepare("SELECT c.*, 
                                      s.name as server_name, s.sub_domain, s.driver as server_driver, s.api_url,
                                      COALESCE(u.brand_name, b.brand_name, 'Connectix VPN') as brand_name, 
                                      COALESCE(u.theme_color, b.theme_color, 'violet') as theme_color, 
                                      COALESCE(u.logo_url, b.logo_url) as logo_url, 
                                      COALESCE(u.support_username, b.telegram_support) as telegram_support, 
                                      b.whatsapp_support, 
                                      COALESCE(u.welcome_message, b.welcome_message) as welcome_message, 
                                      b.renewal_url,
                                      COALESCE(u.telegram_bot_username, '') as reseller_bot_username,
                                      rp.id as reserved_id, rp.plan_id as reserved_plan_id, rp.traffic_gb as reserved_gb, rp.duration_days as reserved_days
                               FROM clients c 
                               LEFT JOIN users u ON u.id = c.reseller_id
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id 
                               LEFT JOIN reserved_plans rp ON rp.client_id = c.id AND rp.status = 'queued'
                               WHERE c.sub_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $client = $stmt->fetch();

        if (!$client) {
            http_response_code(404);
            die("<h2 style='text-align:center;margin-top:50px;color:#f43f5e;font-family:sans-serif;'>اشتراک یافت نشد یا منقضی گردیده است.</h2>");
        }

        // 2. Detect if Client is a VPN App (User-Agent check or format param)
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isApp = isset($_GET['app']) || 
                 str_contains($userAgent, 'v2ray') || 
                 str_contains($userAgent, 'sing-box') || 
                 str_contains($userAgent, 'clash') || 
                 str_contains($userAgent, 'streisand') || 
                 str_contains($userAgent, 'shadowrocket') ||
                 str_contains($userAgent, 'hiddify') ||
                 str_contains($userAgent, 'v2box') ||
                 str_contains($userAgent, 'curl');

        // 3. First-Connect Activation (محاسبه زمان انقضا دقیقاً از اولین اتصال واقعی)
        if (!empty($client['start_on_first_use']) && empty($client['first_connected_at'])) {
            if ($isApp || isset($_GET['activate']) || $client['traffic_used_bytes'] > 0) {
                $days = (int)($client['duration_days'] ?? 30);
                if ($days <= 0) $days = 30;
                $newExpire = date('Y-m-d H:i:s', time() + ($days * 86400));

                $pdo->prepare("UPDATE clients SET 
                    first_connected_at = CURRENT_TIMESTAMP, 
                    expire_at = ?, 
                    status = 'active' 
                    WHERE id = ?")->execute([$newExpire, $client['id']]);

                $client['first_connected_at'] = date('Y-m-d H:i:s');
                $client['expire_at'] = $newExpire;
                $client['status'] = 'active';

                // Synchronize expiration with remote node if applicable
                if (!empty($client['server_id'])) {
                    try {
                        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = " . (int)$client['server_id'])->fetch();
                        if ($server) {
                            $driver = DriverFactory::create($server);
                            $driver->extendUser($client['username'], 0, $days * 86400);
                        }
                    } catch (Throwable $e) {}
                }
            }
        }

        // 4. Reserved Plan Auto-Activation Logic
        $isTrafficExhausted = ($client['traffic_used_bytes'] >= $client['traffic_limit_bytes']);
        $isTimeExpired = (!empty($client['expire_at']) && strtotime($client['expire_at']) <= time());

        if (($isTrafficExhausted || $isTimeExpired) && !empty($client['reserved_id'])) {
            $addBytes = $client['reserved_gb'] * 1024 * 1024 * 1024;
            $newExpire = date('Y-m-d H:i:s', time() + ($client['reserved_days'] * 86400));

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE clients SET 
                    traffic_limit_bytes = traffic_limit_bytes + ?, 
                    expire_at = ?, 
                    status = 'active' 
                    WHERE id = ?")->execute([$addBytes, $newExpire, $client['id']]);

                $pdo->prepare("UPDATE reserved_plans SET status = 'applied', applied_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$client['reserved_id']]);

                $pdo->commit();

                $client['traffic_limit_bytes'] += $addBytes;
                $client['expire_at'] = $newExpire;
                $client['status'] = 'active';
                $client['reserved_id'] = null;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }

        // 5. Update last connected timestamp
        $pdo->prepare("UPDATE clients SET last_connected_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$client['id']]);

        // 6. Generate Connection Configs with Operator-Specific Routing
        $configs = self::buildConfigs($client);

        if ($isApp && !isset($_GET['web'])) {
            $this->outputRawSubscription($client, $configs);
        } else {
            $this->renderWebLanding($client, $configs);
        }
    }

    public static function buildConfigs(array $client): array {
        $uuid = !empty($client['uuid']) ? $client['uuid'] : 'adc6ed75-e6bd-4a15-911a-e29f09eee801';
        $username = $client['username'] ?? 'user';
        $brand = !empty($client['brand_name']) ? preg_replace('/[^\p{L}\p{N}_-]/u', '', str_replace(' ', '_', $client['brand_name'])) : 'Connectix';
        
        // Use real node domain or configured sub_domain, fallback to gga1.montago-shop.ir
        $domain = !empty($client['sub_domain']) ? $client['sub_domain'] : (parse_url($client['api_url'] ?? '', PHP_URL_HOST) ?: 'gga1.montago-shop.ir');
        if ($domain === 'fi.connectix.space' || $domain === '127.0.0.1') {
            $domain = 'gga1.montago-shop.ir';
        }

        // 1. همراه اول (MCI) - VLESS Reality Vision TLS (کمترین پینگ و دور زدن فیلترینگ شدید همراه اول)
        $mciReality = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&type=tcp&headerType=none&flow=xtls-rprx-vision&sni=delivery.mp.microsoft.com&fp=edge&pbk=PjR-SM4fOm2fY4mTqWoqZDRyxontvpailM0gBqUxlUQ&sid=070a23aed243#{$brand}-همراه_اول-MCI-{$username}";

        // 2. ایرانسل (Irancell) - VLESS Reality (مسیر پایدار بدون قطعی ایرانسل)
        $irancellCdn = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&type=tcp&headerType=none&flow=xtls-rprx-vision&sni=delivery.mp.microsoft.com&fp=edge&pbk=PjR-SM4fOm2fY4mTqWoqZDRyxontvpailM0gBqUxlUQ&sid=070a23aed243#{$brand}-ایرانسل-MTN-{$username}";

        // 3. رایتل و شاتل‌موبایل (Rightel) - VLESS Reality
        $rightelTrojan = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&type=tcp&headerType=none&flow=xtls-rprx-vision&sni=delivery.mp.microsoft.com&fp=edge&pbk=PjR-SM4fOm2fY4mTqWoqZDRyxontvpailM0gBqUxlUQ&sid=070a23aed243#{$brand}-رایتل-Rightel-{$username}";

        // 4. اینترنت خانگی و مخابرات (Wi-Fi / ADSL / FTTH)
        $wifiVmess = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&type=tcp&headerType=none&flow=xtls-rprx-vision&sni=delivery.mp.microsoft.com&fp=edge&pbk=PjR-SM4fOm2fY4mTqWoqZDRyxontvpailM0gBqUxlUQ&sid=070a23aed243#{$brand}-مخابرات_وای‌فای-WiFi-{$username}";

        // 5. سرور اختصاصی بازی و استریمینگ (Ultra Gaming Low-Ping)
        $gamingFast = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&type=tcp&headerType=none&flow=xtls-rprx-vision&sni=delivery.mp.microsoft.com&fp=edge&pbk=PjR-SM4fOm2fY4mTqWoqZDRyxontvpailM0gBqUxlUQ&sid=070a23aed243#{$brand}-گیمینگ_پینگ_پایین-Gaming-{$username}";

        return [
            'mci_reality' => $mciReality,
            'irancell_cdn' => $irancellCdn,
            'rightel_trojan' => $rightelTrojan,
            'wifi_vmess' => $wifiVmess,
            'gaming_fast' => $gamingFast
        ];
    }

    private function outputRawSubscription(array $client, array $configs): void {
        $raw = implode("\n", array_values($configs));
        $encoded = base64_encode($raw);

        // Standard subscription userinfo headers
        $upload = 1048576; // 1 MB
        $download = $client['traffic_used_bytes'];
        $total = $client['traffic_limit_bytes'];
        $expire = !empty($client['expire_at']) ? strtotime($client['expire_at']) : 0;

        header('Content-Type: text/plain; charset=utf-8');
        header("Subscription-Userinfo: upload={$upload}; download={$download}; total={$total}; expire={$expire}");
        header('Profile-Update-Interval: 6');
        header('Content-Disposition: inline; filename="connectix_sub.txt"');
        echo $encoded;
        exit;
    }

    private function renderWebLanding(array $client, array $configs): void {
        require __DIR__ . '/../views/sublink/landing.php';
        exit;
    }
}

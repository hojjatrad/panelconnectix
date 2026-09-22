<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class SublinkController {
    public function show(string $token): void {
        $pdo = Database::getConnection();

        // 1. Fetch Client, Server, and Reseller Branding
        $stmt = $pdo->prepare("SELECT c.*, 
                                      s.name as server_name, s.sub_domain, s.driver as server_driver,
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

        // 2. Reserved Plan Auto-Activation Logic
        $isTrafficExhausted = ($client['traffic_used_bytes'] >= $client['traffic_limit_bytes']);
        $isTimeExpired = (!empty($client['expire_at']) && strtotime($client['expire_at']) <= time());

        if (($isTrafficExhausted || $isTimeExpired) && !empty($client['reserved_id'])) {
            // Apply reserved plan automatically!
            $addBytes = $client['reserved_gb'] * 1024 * 1024 * 1024;
            $newExpire = date('Y-m-d H:i:s', time() + ($client['reserved_days'] * 86400));

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE clients SET 
                    traffic_limit_bytes = traffic_limit_bytes + ?, 
                    expire_at = ?, 
                    status = 'active' 
                    WHERE id = ?")->execute([$addBytes, $newExpire, $client['id']]);

                $pdo->prepare("UPDATE reserved_plans SET status = 'applied', applied_at = datetime('now') WHERE id = ?")
                    ->execute([$client['reserved_id']]);

                $pdo->commit();

                // Reload fresh client data
                $client['traffic_limit_bytes'] += $addBytes;
                $client['expire_at'] = $newExpire;
                $client['status'] = 'active';
                $client['reserved_id'] = null;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }

        // 3. Update last connected timestamp
        $pdo->prepare("UPDATE clients SET last_connected_at = datetime('now') WHERE id = ?")->execute([$client['id']]);

        // 4. Generate Connection Configs
        $configs = $this->buildConfigs($client);

        // 5. Detect if Client is a VPN App (User-Agent check or format param)
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isApp = isset($_GET['app']) || 
                 str_contains($userAgent, 'v2ray') || 
                 str_contains($userAgent, 'sing-box') || 
                 str_contains($userAgent, 'clash') || 
                 str_contains($userAgent, 'streisand') || 
                 str_contains($userAgent, 'shadowrocket') ||
                 str_contains($userAgent, 'curl');

        if ($isApp && !isset($_GET['web'])) {
            $this->outputRawSubscription($client, $configs);
        } else {
            $this->renderWebLanding($client, $configs);
        }
    }

    private function buildConfigs(array $client): array {
        $uuid = $client['uuid'];
        $username = $client['username'];
        $brand = !empty($client['brand_name']) ? $client['brand_name'] : 'Connectix';
        $domain = !empty($client['sub_domain']) ? $client['sub_domain'] : 'fi.connectix.space';

        // 1. VLESS Reality (High Speed & Anti-Filtering)
        $vlessReality = "vless://{$uuid}@{$domain}:443?encryption=none&security=reality&sni={$domain}&fp=chrome&pbk=mock_pbk_connectix_anti_filter&sid=123456&type=tcp&headerType=none#{$brand}-Reality-{$username}";

        // 2. VLESS WebSocket CDN
        $vlessWs = "vless://{$uuid}@{$domain}:80?encryption=none&security=none&type=ws&host={$domain}&path=%2Fvless-ws#{$brand}-CDN-{$username}";

        // 3. VMess TCP
        $vmessObj = [
            'v' => '2',
            'ps' => "{$brand}-VMess-{$username}",
            'add' => $domain,
            'port' => '443',
            'id' => $uuid,
            'aid' => '0',
            'net' => 'ws',
            'type' => 'none',
            'host' => $domain,
            'path' => '/vmess',
            'tls' => 'tls'
        ];
        $vmess = "vmess://" . base64_encode(json_encode($vmessObj));

        // 4. Trojan TLS
        $trojan = "trojan://{$uuid}@{$domain}:443?security=tls&sni={$domain}&type=tcp#{$brand}-Trojan-{$username}";

        return [
            'vless_reality' => $vlessReality,
            'vless_ws' => $vlessWs,
            'vmess' => $vmess,
            'trojan' => $trojan
        ];
    }

    private function outputRawSubscription(array $client, array $configs): void {
        $raw = implode("\n", $configs);
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

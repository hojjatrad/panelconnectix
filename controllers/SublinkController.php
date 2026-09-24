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

        // 5.5 Auto-upgrade / resolve node_sublink if empty
        if (empty($client['node_sublink'])) {
            $realServer = null;
            if (!empty($client['server_id'])) {
                $stmtSrv = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1 AND driver != 'mock'");
                $stmtSrv->execute([(int)$client['server_id']]);
                $realServer = $stmtSrv->fetch();
            }
            if (!$realServer) {
                $realServer = Provisioner::findBestServer('default', $pdo);
            }
            if ($realServer && $realServer['driver'] !== 'mock') {
                try {
                    $driver = DriverFactory::create($realServer);
                    $live = $driver->getUser($client['username']);
                    if ($live && !empty($live['subscription_url'])) {
                        $client['node_sublink'] = $live['subscription_url'];
                        $pdo->prepare("UPDATE clients SET node_sublink = ? WHERE id = ?")
                            ->execute([$live['subscription_url'], $client['id']]);
                    } else {
                        $driverPayload = [
                            'username' => $client['username'],
                            'password' => $client['password'],
                            'uuid' => $client['uuid'],
                            'sub_token' => $client['sub_token'],
                            'traffic_limit_bytes' => (int)$client['traffic_limit_bytes'],
                            'expire_timestamp' => !empty($client['expire_at']) ? strtotime($client['expire_at']) : (time() + 30 * 86400)
                        ];
                        $resDriver = $driver->createUser($driverPayload);
                        if ($resDriver['success'] && !empty($resDriver['sublink'])) {
                            $client['node_sublink'] = $resDriver['sublink'];
                            $client['server_id'] = $realServer['id'];
                            $client['driver'] = $realServer['driver'];
                            $pdo->prepare("UPDATE clients SET server_id = ?, node_sublink = ? WHERE id = ?")
                                ->execute([$realServer['id'], $resDriver['sublink'], $client['id']]);
                        }
                    }
                } catch (Throwable $e) {}
            }
        }

        // 6. Direct Proxy from Real Node Sublink if present
        if (!empty($client['node_sublink'])) {
            $ch = curl_init($client['node_sublink']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'v2rayNG/1.8.5');
            $sub = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 400 && !empty($sub)) {
                if ($isApp && !isset($_GET['web'])) {
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Profile-Update-Interval: 6');
                    header('Content-Disposition: inline; filename="connectix_sub.txt"');
                    echo $sub;
                    exit;
                }
                $decoded = base64_decode(trim($sub), true) ?: $sub;
                $lines = array_filter(array_map('trim', explode("\n", $decoded)));
                $out = [];
                foreach ($lines as $i => $l) {
                    if (str_starts_with($l, 'vless://') || str_starts_with($l, 'vmess://') || str_starts_with($l, 'trojan://') || str_starts_with($l, 'ss://')) {
                        $out['sub_link_' . ($i + 1)] = $l;
                    }
                }
                if (!empty($out)) {
                    $this->renderWebLanding($client, $out);
                    exit;
                }
            }
        }

        // 7. Generate Connection Configs with Operator-Specific Routing
        $configs = self::buildConfigs($client);

        if ($isApp && !isset($_GET['web'])) {
            $this->outputRawSubscription($client, $configs);
        } else {
            $this->renderWebLanding($client, $configs);
        }
    }

    public static function buildConfigs(array $client): array {
        $pdo = Database::getConnection();
        $uuid = !empty($client['uuid']) ? $client['uuid'] : 'adc6ed75-e6bd-4a15-911a-e29f09eee801';
        $username = $client['username'] ?? 'user';
        $brand = !empty($client['brand_name']) ? preg_replace('/[^\p{L}\p{N}_-]/u', '', str_replace(' ', '_', $client['brand_name'])) : 'Connectix';

        // 1. Check if server node has custom config_template
        if (!empty($client['server_id'])) {
            try {
                $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $stmtNode->execute([(int)$client['server_id']]);
                $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                if ($node && !empty($node['config_template'])) {
                    $tmpl = trim($node['config_template']);
                    $lines = array_filter(array_map('trim', explode("\n", $tmpl)));
                    $out = [];
                    foreach ($lines as $i => $l) {
                        $parsed = str_replace(
                            ['{uuid}', '{username}', '{remark}'],
                            [$uuid, $username, "{$brand}-{$username}"],
                            $l
                        );
                        $out['tpl_' . ($i + 1)] = $parsed;
                    }
                    if (!empty($out)) return $out;
                }
            } catch (Throwable $e) {}
        }

        // 2. If client is on a real node (Marzban / Pasargad / 3x-ui), fetch real links directly from the remote node
        if (!empty($client['server_id'])) {
            try {
                $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $stmtNode->execute([(int)$client['server_id']]);
                $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                if ($node && $node['driver'] !== 'mock') {
                    $driver = DriverFactory::create($node);
                    $liveUser = $driver->getUser($client['username']);
                    if (!empty($liveUser['links']) && is_array($liveUser['links'])) {
                        $out = [];
                        foreach ($liveUser['links'] as $i => $l) {
                            $out['node_link_' . ($i + 1)] = $l;
                        }
                        if (!empty($out)) return $out;
                    }
                    if (!empty($liveUser['subscription_url'])) {
                        $ch = curl_init($liveUser['subscription_url']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $sub = curl_exec($ch);
                        curl_close($ch);
                        if (!empty($sub)) {
                            $decoded = base64_decode(trim($sub), true) ?: $sub;
                            $lines = array_filter(array_map('trim', explode("\n", $decoded)));
                            $out = [];
                            foreach ($lines as $i => $l) {
                                if (str_starts_with($l, 'vless://') || str_starts_with($l, 'vmess://') || str_starts_with($l, 'trojan://') || str_starts_with($l, 'ss://')) {
                                    $out['sub_link_' . ($i + 1)] = $l;
                                }
                            }
                            if (!empty($out)) return $out;
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        // 3. Fallback: If client has node_sublink, fetch configs from it
        if (!empty($client['node_sublink'])) {
            try {
                $ch = curl_init($client['node_sublink']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $sub = curl_exec($ch);
                curl_close($ch);
                if (!empty($sub)) {
                    $decoded = base64_decode(trim($sub), true) ?: $sub;
                    $lines = array_filter(array_map('trim', explode("\n", $decoded)));
                    $out = [];
                    foreach ($lines as $i => $l) {
                        if (str_starts_with($l, 'vless://') || str_starts_with($l, 'vmess://') || str_starts_with($l, 'trojan://') || str_starts_with($l, 'ss://')) {
                            $out['sub_link_' . ($i + 1)] = $l;
                        }
                    }
                    if (!empty($out)) return $out;
                }
            } catch (Throwable $e) {}
        }

        // Return empty if no real configs from node (no fake fallbacks)
        return [];
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

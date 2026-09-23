<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class Provisioner {
    /**
     * Create and provision a new client
     */
    public static function createClient(
        int $planId, 
        ?int $serverId = null, 
        ?string $username = null, 
        ?string $password = null, 
        int $resellerId = 1, 
        string $customNote = '',
        ?string $telegramChatId = null
    ): array {
        $pdo = Database::getConnection();

        // Fetch Plan
        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();
        if (!$plan) {
            return ['success' => false, 'error' => 'پلن انتخاب‌شده یافت نشد یا غیرفعال است.'];
        }

        // Fetch Server (Specific passed ID -> Plan-bound Server -> Cluster Best Server)
        if ($serverId !== null && $serverId > 0) {
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
            $stmtServer->execute([$serverId]);
            $server = $stmtServer->fetch();
        } elseif (!empty($plan['server_id'])) {
            // Plan is directly bound to a specific server node!
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
            $stmtServer->execute([(int)$plan['server_id']]);
            $server = $stmtServer->fetch();
            if (!$server) {
                // Failover fallback to cluster group if bound node is inactive
                $server = self::findBestServer($plan['server_group'] ?? 'default', $pdo);
            }
        } else {
            $server = self::findBestServer($plan['server_group'] ?? 'default', $pdo);
        }

        if (!$server) {
            return ['success' => false, 'error' => 'هیچ سرور فعالی برای این پلن در سیستم یافت نشد.'];
        }

        // Generate username if not provided
        if (empty($username)) {
            $username = 'usr_' . substr(bin2hex(random_bytes(3)), 0, 6);
            // Ensure unique
            $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE username = ?");
            $stmtCheck->execute([$username]);
            while ($stmtCheck->fetch()) {
                $username = 'usr_' . substr(bin2hex(random_bytes(4)), 0, 7);
                $stmtCheck->execute([$username]);
            }
        }

        if (empty($password)) {
            $password = substr(bin2hex(random_bytes(4)), 0, 8);
        }

        $uuid = Helpers::generateUUID();
        $subToken = Helpers::generateToken(24);
        $trafficBytes = (int)$plan['traffic_gb'] * 1024 * 1024 * 1024;
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        $expireTimestamp = strtotime($expireAt);

        // 1. Provision on remote node
        try {
            $driver = DriverFactory::create($server);
            $driverPayload = [
                'username' => $username,
                'password' => $password,
                'uuid' => $uuid,
                'sub_token' => $subToken,
                'traffic_limit_bytes' => $trafficBytes,
                'expire_timestamp' => $expireTimestamp
            ];
            $driverResult = $driver->createUser($driverPayload);
            if (!$driverResult['success']) {
                return ['success' => false, 'error' => 'خطا در ثبت کاربر روی سرور نود: ' . ($driverResult['error'] ?? 'خطای نامشخص')];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'استثنا در برقراری ارتباط با سرور نود: ' . $e->getMessage()];
        }

        // 2. Insert into database
        try {
            $stmt = $pdo->prepare("INSERT INTO clients 
                (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, status, custom_note, telegram_chat_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'active', ?, ?)");
            $stmt->execute([
                $resellerId,
                $server['id'],
                $plan['id'],
                $username,
                $password,
                $uuid,
                $subToken,
                $trafficBytes,
                $expireAt,
                $customNote,
                $telegramChatId
            ]);
            $clientId = (int)$pdo->lastInsertId();

            $subUrl = Helpers::fullUrl("sub/{$subToken}");

            return [
                'success' => true,
                'client_id' => $clientId,
                'username' => $username,
                'password' => $password,
                'uuid' => $uuid,
                'sub_token' => $subToken,
                'sub_url' => $subUrl,
                'expire_at' => $expireAt,
                'traffic_gb' => $plan['traffic_gb'],
                'server_id' => (int)$server['id'],
                'server_name' => $server['name']
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'خطا در ذخیره‌سازی کلاینت در پایگاه داده: ' . $e->getMessage()];
        }
    }

    /**
     * Extend / Renew an existing client or queue reserved plan
     */
    public static function renewClient(int $clientId, int $planId, bool $queueAsReserved = false): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name, s.driver, s.api_url, s.api_username, s.api_password, s.api_token 
                               FROM clients c 
                               JOIN server_nodes s ON c.server_id = s.id 
                               WHERE c.id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        if (!$client) {
            return ['success' => false, 'error' => 'کلاینت مورد نظر یافت نشد.'];
        }

        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();
        if (!$plan) {
            return ['success' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست.'];
        }

        if ($queueAsReserved) {
            // Queue into reserved_plans table
            $stmtRes = $pdo->prepare("INSERT INTO reserved_plans (client_id, plan_id, traffic_gb, duration_days, status) VALUES (?, ?, ?, ?, 'queued')");
            $stmtRes->execute([$clientId, $planId, $plan['traffic_gb'], $plan['duration_days']]);
            return [
                'success' => true,
                'type' => 'reserved',
                'message' => 'پلن با موفقیت به صف رزرو خودکار اضافه شد و پس از اتمام دوره فعلی فعال خواهد شد.'
            ];
        }

        // Direct extension
        $addBytes = (int)$plan['traffic_gb'] * 1024 * 1024 * 1024;
        $currentExpire = strtotime($client['expire_at'] ?? 'now');
        $baseTime = ($currentExpire > time()) ? $currentExpire : time();
        $newExpire = date('Y-m-d H:i:s', $baseTime + ($plan['duration_days'] * 86400));

        try {
            // Remote node extension
            $driver = DriverFactory::create($client);
            $driver->extendUser($client['username'], $addBytes, $plan['duration_days'] * 86400);

            // DB update
            $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active' WHERE id = ?")
                ->execute([$addBytes, $newExpire, $clientId]);

            return [
                'success' => true,
                'type' => 'extended',
                'new_expire' => $newExpire,
                'added_gb' => $plan['traffic_gb']
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'خطا در تمدید روی سرور: ' . $e->getMessage()];
        }
    }

    /**
     * Find best healthy server with automatic failover and lowest latency
     */
    public static function findBestServer(string $clusterGroup = 'default', ?PDO $pdo = null): ?array {
        if (!$pdo) $pdo = Database::getConnection();

        // Condition for capacity: unlimited (max_clients <= 0 or null) OR current clients < max_clients
        $capacityCondition = "(s.max_clients IS NULL OR s.max_clients <= 0 OR (SELECT COUNT(*) FROM clients WHERE server_id = s.id) < s.max_clients)";

        // 1. Try healthy active servers for this group with lowest latency & available capacity
        $stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM clients WHERE server_id = s.id) as client_count 
                               FROM server_nodes s 
                               WHERE s.is_active = 1 
                                 AND (s.health_status = 'online' OR s.health_status IS NULL)
                                 AND (s.server_group = ? OR ? = 'default')
                                 AND {$capacityCondition}
                               ORDER BY client_count ASC, COALESCE(s.latency_ms, 999) ASC LIMIT 1");
        $stmt->execute([$clusterGroup, $clusterGroup]);
        $server = $stmt->fetch();

        // 2. Fallback to any online server with capacity
        if (!$server) {
            $server = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM clients WHERE server_id = s.id) as client_count 
                                  FROM server_nodes s 
                                  WHERE s.is_active = 1 
                                    AND (s.health_status != 'offline' OR s.health_status IS NULL)
                                    AND {$capacityCondition}
                                  ORDER BY client_count ASC, COALESCE(s.latency_ms, 999) ASC LIMIT 1")->fetch();
        }

        // 3. Last resort fallback: any active server with capacity
        if (!$server) {
            $server = $pdo->query("SELECT s.* FROM server_nodes s 
                                  WHERE s.is_active = 1 
                                    AND {$capacityCondition} 
                                  ORDER BY s.id ASC LIMIT 1")->fetch();
        }

        // 4. Absolute fallback if all servers are technically full
        if (!$server) {
            $server = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        }

        return $server ?: null;
    }

    /**
     * Calculate Reseller Tier and effective discount
     */
    public static function getResellerTier(int $resellerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$resellerId]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['tier' => 'bronze', 'title' => 'برنزی', 'badge' => '🥉', 'discount' => 15, 'client_count' => 0];
        }

        $baseDiscount = (int)$user['discount_percent'];
        $autoTier = (int)($user['auto_tier_enabled'] ?? 1);

        // Count active clients
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ? AND status = 'active'");
        $stmtCount->execute([$resellerId]);
        $clientCount = (int)$stmtCount->fetchColumn();

        if ($clientCount >= 100) {
            $tier = 'diamond';
            $title = 'الماس';
            $badge = '💎';
            $tierDiscount = 45;
        } elseif ($clientCount >= 50) {
            $tier = 'gold';
            $title = 'طلایی';
            $badge = '🥇';
            $tierDiscount = 35;
        } elseif ($clientCount >= 20) {
            $tier = 'silver';
            $title = 'نقره‌ای';
            $badge = '🥈';
            $tierDiscount = 25;
        } else {
            $tier = 'bronze';
            $title = 'برنزی';
            $badge = '🥉';
            $tierDiscount = 15;
        }

        $effectiveDiscount = $autoTier ? max($baseDiscount, $tierDiscount) : $baseDiscount;

        return [
            'tier' => $tier,
            'title' => $title,
            'badge' => $badge,
            'discount' => $effectiveDiscount,
            'base_discount' => $baseDiscount,
            'client_count' => $clientCount,
            'auto_tier' => $autoTier
        ];
    }

    /**
     * Create Free Trial Account (1 GB, 24 Hours)
     */
    public static function createTrialAccount(?string $telegramId = null, ?int $resellerId = 1, ?string $ip = null): array {
        $pdo = Database::getConnection();
        $trialEnabled = (bool)Setting::get('trial_enabled', 1);
        if (!$trialEnabled) {
            return ['success' => false, 'error' => 'سامانه تست رایگان موقتاً غیرفعال است.'];
        }

        $hours = (int)Setting::get('trial_duration_hours', 24);
        if ($hours <= 0) $hours = 24;

        // Support MB or GB (If trial_traffic_mb is set, use it; otherwise fallback to trial_traffic_gb * 1024)
        $trafficMb = (int)Setting::get('trial_traffic_mb', 0);
        if ($trafficMb <= 0) {
            $trafficGb = (int)Setting::get('trial_traffic_gb', 1);
            $trafficMb = $trafficGb > 0 ? $trafficGb * 1024 : 500;
        }

        // Check if user already got a trial today
        $today = date('Y-m-d 00:00:00');
        if (!empty($telegramId)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM trial_logs WHERE telegram_id = ? AND created_at >= ?");
            $stmtCheck->execute([$telegramId, $today]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'error' => 'شما امروز سهمیه اکانت تست رایگان خود را دریافت کرده‌اید. برای اتصال دائمی، اشتراک تهیه فرمایید.'];
            }
        }

        // Pick best healthy server
        $server = self::findBestServer('default', $pdo);
        if (!$server) {
            return ['success' => false, 'error' => 'سروری برای ارائه اکانت تست در دسترس نیست.'];
        }

        $username = 'test_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $password = substr(bin2hex(random_bytes(4)), 0, 8);
        $uuid = Helpers::generateUUID();
        $subToken = Helpers::generateToken(24);
        $trafficBytes = (int)$trafficMb * 1024 * 1024;
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));

        // Provision on remote node
        try {
            $driver = DriverFactory::create($server);
            $driverPayload = [
                'username' => $username,
                'password' => $password,
                'uuid' => $uuid,
                'sub_token' => $subToken,
                'traffic_limit_bytes' => $trafficBytes,
                'expire_timestamp' => strtotime($expireAt)
            ];
            $driverResult = $driver->createUser($driverPayload);
            if (!$driverResult['success']) {
                return ['success' => false, 'error' => 'خطا در نود سرور: ' . ($driverResult['error'] ?? 'خطای نامشخص')];
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'خطا در ارتباط با سرور: ' . $e->getMessage()];
        }

        // Save client
        $stmtClient = $pdo->prepare("INSERT INTO clients 
            (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, status, custom_note, telegram_chat_id) 
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 0, ?, 'active', 'اکانت تست رایگان', ?)");
        $stmtClient->execute([
            $resellerId ?: 1,
            $server['id'],
            $username,
            $password,
            $uuid,
            $subToken,
            $trafficBytes,
            $expireAt,
            $telegramId
        ]);
        $clientId = (int)$pdo->lastInsertId();

        // Log trial
        $stmtLog = $pdo->prepare("INSERT INTO trial_logs (user_id, reseller_id, telegram_id, ip_address, client_id) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$resellerId, $resellerId, $telegramId, $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), $clientId]);

        $subUrl = Helpers::fullUrl("sub/{$subToken}");

        return [
            'success' => true,
            'client_id' => $clientId,
            'username' => $username,
            'password' => $password,
            'uuid' => $uuid,
            'sub_url' => $subUrl,
            'traffic_gb' => $trafficGb,
            'hours' => $hours,
            'expire_at' => $expireAt,
            'server_name' => $server['name']
        ];
    }
}

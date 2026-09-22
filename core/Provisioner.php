<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
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

        // Fetch Server
        if ($serverId !== null && $serverId > 0) {
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
            $stmtServer->execute([$serverId]);
            $server = $stmtServer->fetch();
        } else {
            // Pick default or first active server for the plan's group
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE is_active = 1 AND (server_group = ? OR server_group = 'default') ORDER BY id ASC LIMIT 1");
            $stmtServer->execute([$plan['server_group']]);
            $server = $stmtServer->fetch();
            if (!$server) {
                $server = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 LIMIT 1")->fetch();
            }
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
}

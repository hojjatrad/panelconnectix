<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Provisioner.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class ClientController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        $search = trim($_GET['search'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        $serverGroup = trim($_GET['group'] ?? '');
        $planFilter = (int)($_GET['plan_id'] ?? 0);
        $expireFilter = trim($_GET['expire_filter'] ?? '');
        $usageFilter = trim($_GET['usage_filter'] ?? '');

        $where = $isAdmin ? ["1=1"] : ["c.reseller_id = " . intval($userId)];
        $params = [];

        if (!empty($search)) {
            $where[] = "(c.username LIKE ? OR c.uuid LIKE ? OR c.custom_note LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($statusFilter)) {
            $where[] = "c.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($serverGroup)) {
            $where[] = "s.server_group = ?";
            $params[] = $serverGroup;
        }

        if ($planFilter > 0) {
            $where[] = "c.plan_id = ?";
            $params[] = $planFilter;
        }

        if ($expireFilter === 'expiring_soon') {
            $threeDaysLater = date('Y-m-d H:i:s', strtotime('+3 days'));
            $now = date('Y-m-d H:i:s');
            $where[] = "(c.expire_at IS NOT NULL AND c.expire_at > ? AND c.expire_at <= ?)";
            $params[] = $now;
            $params[] = $threeDaysLater;
        } elseif ($expireFilter === 'expired') {
            $now = date('Y-m-d H:i:s');
            $where[] = "(c.expire_at IS NOT NULL AND c.expire_at <= ?)";
            $params[] = $now;
        } elseif ($expireFilter === 'unlimited') {
            $where[] = "c.expire_at IS NULL";
        }

        if ($usageFilter === 'critical') {
            $where[] = "(c.traffic_limit_bytes > 0 AND (c.traffic_used_bytes * 1.0 / c.traffic_limit_bytes) >= 0.9)";
        } elseif ($usageFilter === 'heavy') {
            $where[] = "(c.traffic_limit_bytes > 0 AND (c.traffic_used_bytes * 1.0 / c.traffic_limit_bytes) >= 0.5 AND (c.traffic_used_bytes * 1.0 / c.traffic_limit_bytes) < 0.9)";
        } elseif ($usageFilter === 'low') {
            $where[] = "(c.traffic_limit_bytes > 0 AND (c.traffic_used_bytes * 1.0 / c.traffic_limit_bytes) < 0.2)";
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, p.traffic_gb, p.duration_days, 
                                      s.name as server_name, s.server_group, s.sub_domain, 
                                      u.username as reseller_username,
                                      rp.id as reserved_id, rp.status as reserved_status, rp.traffic_gb as reserved_gb
                               FROM clients c 
                               LEFT JOIN plans p ON c.plan_id = p.id 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               LEFT JOIN users u ON c.reseller_id = u.id 
                               LEFT JOIN reserved_plans rp ON rp.client_id = c.id AND rp.status = 'queued'
                               WHERE $whereSql 
                               ORDER BY c.id DESC");
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        // Get plans & servers for filters and modals
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        require __DIR__ . '/../views/clients/index.php';
    }

    public function create(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();

        // Fetch active servers
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll();
        // Fetch active plans
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY is_free DESC, base_price ASC")->fetchAll();

        $generatedUsername = 'user_' . substr(bin2hex(random_bytes(4)), 0, 7);
        $generatedPassword = substr(bin2hex(random_bytes(4)), 0, 6);

        require __DIR__ . '/../views/clients/create.php';
    }

    public function store(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients/create');
        }

        $pdo = Database::getConnection();
        $user = Auth::user();
        $userId = Auth::id();

        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $planId = (int)($_POST['plan_id'] ?? 0);
        $customNote = trim($_POST['custom_note'] ?? '');

        if (empty($username) || empty($planId)) {
            Helpers::flash('error', 'تمامی فیلدهای ستاره‌دار الزامی هستند.');
            Helpers::redirect('clients/create');
        }

        // Check if username already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) {
            Helpers::flash('error', 'این نام کاربری قبلاً در سامانه ثبت شده است.');
            Helpers::redirect('clients/create');
        }

        // Fetch Plan
        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();

        if (!$plan) {
            Helpers::flash('error', 'پلن انتخاب‌شده معتبر نیست.');
            Helpers::redirect('clients/create');
        }

        // Server Selection: Direct or Auto Load Balancing
        $autoSelect = (($_POST['server_id'] ?? '') === 'auto' || empty($_POST['server_id']));
        $capacityCondition = "(s.max_clients IS NULL OR s.max_clients <= 0 OR (SELECT COUNT(*) FROM clients WHERE server_id = s.id) < s.max_clients)";

        if ($autoSelect) {
            $group = $plan['server_group'] ?? 'default';
            $stmtAuto = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM clients WHERE server_id = s.id) as client_count 
                                       FROM server_nodes s 
                                       WHERE s.is_active = 1 
                                         AND (s.server_group = ? OR ? = 'default') 
                                         AND {$capacityCondition}
                                       ORDER BY client_count ASC, COALESCE(s.latency_ms, 999) ASC LIMIT 1");
            $stmtAuto->execute([$group, $group]);
            $server = $stmtAuto->fetch();
            if (!$server) {
                // Fallback to any active server with capacity
                $server = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM clients WHERE server_id = s.id) as client_count 
                                       FROM server_nodes s 
                                       WHERE s.is_active = 1 AND {$capacityCondition}
                                       ORDER BY client_count ASC LIMIT 1")->fetch();
            }
            if (!$server) {
                // Last resort fallback
                $server = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
            }
            if ($server) {
                $serverId = (int)$server['id'];
            } else {
                $serverId = 0;
            }
        } else {
            $serverId = (int)($_POST['server_id'] ?? 0);
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
            $stmtServer->execute([$serverId]);
            $server = $stmtServer->fetch();

            if ($server && !empty($server['max_clients']) && (int)$server['max_clients'] > 0) {
                $currentClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE server_id = " . (int)$server['id'])->fetchColumn();
                if ($currentClients >= (int)$server['max_clients']) {
                    Helpers::flash('error', "ظرفیت سرور انتخاب شده ({$server['name']}) تکمیل شده است ({$currentClients}/{$server['max_clients']} کلاینت). لطفاً سرور دیگری انتخاب نمایید یا در بخش سرورها سقف ظرفیت آن را روی 0 (نامحدود) تنظیم فرمایید.");
                    Helpers::redirect('clients/create');
                }
            }
        }

        if (!$server) {
            Helpers::flash('error', 'هیچ سرور فعالی برای ایجاد اشتراک یافت نشد.');
            Helpers::redirect('clients/create');
        }

        // Price Calculation with Tiered Reseller Discount
        $tierInfo = Provisioner::getResellerTier($user['id']);
        $discount = (int)$tierInfo['discount'];
        $cost = $plan['reseller_price'];
        if ($discount > 0) {
            $cost = $cost - ($cost * ($discount / 100));
        }
        $cost = (int)$cost;

        // Check wallet balance if not free and not admin
        if (!Auth::isAdmin() && $plan['is_free'] == 0 && $user['wallet_balance'] < $cost) {
            Helpers::flash('error', "اعتبار کیف پول شما کافی نیست. موجودی: " . Helpers::formatMoney($user['wallet_balance']) . " | هزینه پلن: " . Helpers::formatMoney($cost));
            Helpers::redirect('billing');
        }

        $uuid = Helpers::generateUUID();
        $subToken = Helpers::generateToken(24);
        $trafficBytes = $plan['traffic_gb'] * 1024 * 1024 * 1024;
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        $expireTimestamp = strtotime($expireAt);
        $ipLimit = isset($_POST['ip_limit']) ? max(0, (int)$_POST['ip_limit']) : (int)($plan['ip_limit'] ?? 2);

        // 1. Provision on Remote Server Node via Driver
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
            Helpers::flash('error', 'خطا در ثبت کاربر روی سرور: ' . ($driverResult['error'] ?? 'خطای نامشخص'));
            Helpers::redirect('clients/create');
        }

        // First-Connect Calculation & Status
        $startOnFirstUse = !empty($plan['start_on_first_use']) || !empty($_POST['start_on_first_use']);
        $durationDays = (int)($plan['duration_days'] ?? 30);
        $maxDevices = max(1, (int)($plan['max_devices'] ?? $ipLimit ?? 2));

        if ($startOnFirstUse) {
            $expireAt = null;
            $clientStatus = 'waiting_connect';
        } else {
            $expireAt = date('Y-m-d H:i:s', time() + ($durationDays * 86400));
            $clientStatus = 'active';
        }

        // 2. Atomic Database Transaction
        try {
            $pdo->beginTransaction();

            // Deduct wallet if not admin and cost > 0
            $newBalance = $user['wallet_balance'];
            if (!Auth::isAdmin() && $cost > 0) {
                $newBalance -= $cost;
                $stmtUpdateWallet = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                $stmtUpdateWallet->execute([$newBalance, $userId]);

                // Record Transaction
                $stmtTrans = $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'plan_purchase', ?, ?, 'completed')");
                $desc = "خرید پلن {$plan['title']} برای کاربر $username";
                $refId = "TX-" . rand(100000, 999999);
                $stmtTrans->execute([$userId, -$cost, $newBalance, $desc, $refId]);
            }

            // Credit commission to Parent Reseller if this user is a Sub-Reseller
            if (!empty($user['parent_reseller_id']) && $cost > 0) {
                $parentId = (int)$user['parent_reseller_id'];
                $commPercent = (int)($user['commission_percent'] ?: 10);
                $commAmount = (int)round(($cost * $commPercent) / 100);
                if ($commAmount > 0) {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$commAmount, $parentId]);
                    $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, (SELECT wallet_balance FROM users WHERE id = ?), 'commission', ?, ?, 'completed')")
                        ->execute([$parentId, $commAmount, $parentId, "پورسانت {$commPercent}٪ از خرید ساب‌نماینده ({$user['username']}) بابت {$username}", "COMM-" . rand(100000, 999999)]);
                }
            }

            // Insert Client
            $nodeSublink = !empty($driverResult['sublink']) ? $driverResult['sublink'] : null;
            if ($nodeSublink && str_contains($nodeSublink, 'montago-shop.ir')) {
                $nodeSublink = preg_replace('#https?://[^/]+#i', 'https://sub.speedur.org:2096', $nodeSublink);
            }
            $stmtInsert = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, ip_limit, max_devices, start_on_first_use, duration_days, status, custom_note, node_sublink) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$userId, $serverId, $planId, $username, $password, $uuid, $subToken, $trafficBytes, $expireAt, $ipLimit, $maxDevices, $startOnFirstUse ? 1 : 0, $durationDays, $clientStatus, $customNote, $nodeSublink]);
            $newClientId = $pdo->lastInsertId();

            $pdo->commit();

            // Log activity
            Helpers::logActivity('client_create', "ایجاد کلاینت {$username} با پلن {$plan['title']} روی سرور {$server['name']}", 'client', $newClientId);

            // Send instant Telegram Notification to Admin
            require_once __DIR__ . '/../core/TelegramBot.php';
            $botText = "🚀 <b>سرویس جدید ایجاد شد</b>\n"
                     . "👤 کاربر: <code>{$username}</code>\n"
                     . "📦 پلن: <b>{$plan['title']}</b>\n"
                     . "⏳ مدت: {$plan['duration_days']} روز ({$plan['traffic_gb']}GB)\n"
                     . "🏷 نماینده: {$user['username']}\n"
                     . "💰 مبلغ: " . Helpers::formatMoney($cost);
            TelegramBot::sendMessage($botText);

            Helpers::flash('success', "کاربر {$username} با موفقیت ساخته شد و سرویس فعال گردید.");
            Helpers::redirect('clients');
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ثبت تراکنش دیتابیس: ' . $e->getMessage());
            Helpers::redirect('clients/create');
        }
    }

    /**
     * Update existing client service details (Modal Edit)
     */
    public function update(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients');
        }

        $id = (int)($_POST['client_id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name, s.driver, s.api_url, s.api_username, s.api_password, s.api_token 
                               FROM clients c 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               WHERE c.id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();

        if (!$client) {
            Helpers::flash('error', 'کلاینت یافت نشد.');
            Helpers::redirect('clients');
        }

        // Reseller access control
        if (Auth::isReseller() && (int)$client['reseller_id'] !== Auth::id()) {
            Helpers::flash('error', 'دسترسی غیرمجاز.');
            Helpers::redirect('clients');
        }

        $username = trim($_POST['username'] ?? $client['username']);
        $password = trim($_POST['password'] ?? $client['password']);
        $serverId = (int)($_POST['server_id'] ?? $client['server_id']);
        $planId = !empty($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;
        $status = in_array($_POST['status'] ?? '', ['active', 'disabled', 'expired', 'limited']) ? $_POST['status'] : $client['status'];
        
        $trafficLimitGb = isset($_POST['traffic_limit_gb']) ? (float)$_POST['traffic_limit_gb'] : ($client['traffic_limit_bytes'] / (1024*1024*1024));
        $trafficUsedGb = isset($_POST['traffic_used_gb']) ? (float)$_POST['traffic_used_gb'] : ($client['traffic_used_bytes'] / (1024*1024*1024));
        $trafficLimitBytes = (int)round($trafficLimitGb * 1024 * 1024 * 1024);
        $trafficUsedBytes = (int)round($trafficUsedGb * 1024 * 1024 * 1024);

        $expireAt = !empty($_POST['expire_at']) ? trim($_POST['expire_at']) : null;
        $telegramChatId = !empty($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : null;
        $customNote = trim($_POST['custom_note'] ?? ($client['custom_note'] ?? ''));
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? ($client['ip_limit'] ?? 2)));

        // If server changed, handle node migration
        $oldServerId = (int)$client['server_id'];
        if ($oldServerId !== $serverId) {
            try {
                // Delete from old node
                $oldServer = $pdo->query("SELECT * FROM server_nodes WHERE id = {$oldServerId}")->fetch();
                if ($oldServer) {
                    $oldDriver = DriverFactory::create($oldServer);
                    $oldDriver->deleteUser($client['username']);
                }

                // Provision on new node
                $newServer = $pdo->query("SELECT * FROM server_nodes WHERE id = {$serverId}")->fetch();
                if ($newServer) {
                    $newDriver = DriverFactory::create($newServer);
                    $expireSec = $expireAt ? (strtotime($expireAt) - time()) : 0;
                    $newDriver->createUser([
                        'username' => $username,
                        'uuid' => $client['uuid'],
                        'traffic_limit_bytes' => $trafficLimitBytes,
                        'expire_timestamp' => $expireAt ? strtotime($expireAt) : 0,
                        'proxies' => ['vless', 'vmess', 'trojan']
                    ]);
                }
            } catch (Throwable $e) {}
        } else {
            // Sync status with current remote node if changed
            if ($client['status'] !== $status) {
                try {
                    $driver = DriverFactory::create($client);
                    $driver->toggleUserStatus($client['username'], ($status === 'active'));
                } catch (Throwable $e) {}
            }
        }

        // Reset alert flags if traffic was reset or limit was increased
        $resetAlertsSql = "";
        if ($trafficUsedBytes < $client['traffic_used_bytes'] || $trafficLimitBytes > $client['traffic_limit_bytes']) {
            $resetAlertsSql = ", alert_80_sent = 0, alert_95_sent = 0";
        }

        $stmtUp = $pdo->prepare("UPDATE clients SET 
            username = ?, 
            password = ?, 
            server_id = ?, 
            plan_id = ?, 
            status = ?, 
            traffic_limit_bytes = ?, 
            traffic_used_bytes = ?, 
            expire_at = ?, 
            ip_limit = ?,
            telegram_chat_id = ?,
            custom_note = ?
            {$resetAlertsSql},
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?");
        $stmtUp->execute([
            $username,
            $password,
            $serverId,
            $planId,
            $status,
            $trafficLimitBytes,
            $trafficUsedBytes,
            $expireAt,
            $ipLimit,
            $telegramChatId,
            $customNote,
            $id
        ]);

        Helpers::flash('success', "مشخصات سرویس کاربر '{$username}' با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('clients');
    }

    public function renew(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('clients');
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $pdo = Database::getConnection();
        $user = Auth::user();
        $userId = Auth::id();

        $client = $pdo->query("SELECT * FROM clients WHERE id = $clientId")->fetch();
        $plan = $pdo->query("SELECT * FROM plans WHERE id = $planId")->fetch();

        if (!$client || !$plan) {
            Helpers::flash('error', 'کلاینت یا پلن یافت نشد.');
            Helpers::redirect('clients');
        }

        if (!Auth::isAdmin() && $client['reseller_id'] != $userId) {
            Helpers::flash('error', 'عدم دسترسی به این کلاینت.');
            Helpers::redirect('clients');
        }

        $cost = (int)$plan['reseller_price'];
        if (!Auth::isAdmin() && $user['wallet_balance'] < $cost) {
            Helpers::flash('error', 'اعتبار کیف پول برای تمدید آنی کافی نیست.');
            Helpers::redirect('clients');
        }

        $addBytes = $plan['traffic_gb'] * 1024 * 1024 * 1024;
        $currentExpire = !empty($client['expire_at']) ? strtotime($client['expire_at']) : time();
        $baseTime = max(time(), $currentExpire);
        $newExpireStr = date('Y-m-d H:i:s', $baseTime + ($plan['duration_days'] * 86400));

        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = " . intval($client['server_id']))->fetch();
        if ($server) {
            $driver = DriverFactory::create($server);
            $driver->extendUser($client['username'], $addBytes, $plan['duration_days'] * 86400);
        }

        $pdo->beginTransaction();
        try {
            if (!Auth::isAdmin() && $cost > 0) {
                $newBal = $user['wallet_balance'] - $cost;
                $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBal, $userId]);
                $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'plan_renewal', ?, ?, 'completed')")
                    ->execute([$userId, -$cost, $newBal, "تمدید سرویس {$client['username']}", "RNW-" . rand(10000, 99999)]);
            }

            $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active' WHERE id = ?")
                ->execute([$addBytes, $newExpireStr, $clientId]);

            $pdo->commit();
            Helpers::logActivity('client_renew', "تمدید آنی سرویس کلاینت {$client['username']} با پلن {$plan['title']}", 'client', $clientId);
            Helpers::flash('success', "سرویس کاربر {$client['username']} با موفقیت تمدید شد.");
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در تمدید: ' . $e->getMessage());
        }

        Helpers::redirect('clients');
    }

    public function reservePlan(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('clients');
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $planId = (int)($_POST['plan_id'] ?? 0);
        $pdo = Database::getConnection();
        $user = Auth::user();
        $userId = Auth::id();

        $client = $pdo->query("SELECT * FROM clients WHERE id = $clientId")->fetch();
        $plan = $pdo->query("SELECT * FROM plans WHERE id = $planId")->fetch();

        if (!$client || !$plan) {
            Helpers::flash('error', 'اطلاعات نامعتبر است.');
            Helpers::redirect('clients');
        }

        $cost = (int)$plan['reseller_price'];
        if (!Auth::isAdmin() && $user['wallet_balance'] < $cost) {
            Helpers::flash('error', 'موجودی کیف پول برای رزرو پلن کافی نیست.');
            Helpers::redirect('clients');
        }

        $pdo->beginTransaction();
        try {
            if (!Auth::isAdmin() && $cost > 0) {
                $newBal = $user['wallet_balance'] - $cost;
                $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBal, $userId]);
                $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'plan_renewal', ?, ?, 'completed')")
                    ->execute([$userId, -$cost, $newBal, "پیش‌خرید و رزرو پلن برای کاربر {$client['username']}", "RSV-" . rand(10000, 99999)]);
            }

            $pdo->prepare("INSERT INTO reserved_plans (client_id, plan_id, traffic_gb, duration_days, status) VALUES (?, ?, ?, ?, 'queued')")
                ->execute([$clientId, $planId, $plan['traffic_gb'], $plan['duration_days']]);

            $pdo->commit();
            Helpers::logActivity('client_reserve', "رزرو پلن در صف برای کلاینت {$client['username']} (پلن {$plan['title']})", 'client', $clientId);
            Helpers::flash('success', "پلن با موفقیت رزرو شد! پس از اتمام حجم یا تاریخ فعلی، خودکار فعال خواهد شد.");
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا: ' . $e->getMessage());
        }

        Helpers::redirect('clients');
    }

    public function delete(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients');
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $pdo = Database::getConnection();
        $userId = Auth::id();

        $client = $pdo->query("SELECT * FROM clients WHERE id = $clientId")->fetch();
        if (!$client) {
            Helpers::flash('error', 'کاربر یافت نشد.');
            Helpers::redirect('clients');
        }

        if (!Auth::isAdmin() && $client['reseller_id'] != $userId) {
            Helpers::flash('error', 'دسترسی غیرمجاز.');
            Helpers::redirect('clients');
        }

        // Try deleting from remote server
        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = " . intval($client['server_id']))->fetch();
        if ($server) {
            $driver = DriverFactory::create($server);
            $driver->deleteUser($client['username']);
        }

        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$clientId]);
        Helpers::logActivity('client_delete', "حذف قطعی کلاینت {$client['username']}", 'client', $clientId);
        Helpers::flash('info', "کاربر {$client['username']} با موفقیت حذف گردید.");
        Helpers::redirect('clients');
    }

    public function exportCsv(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        $search = trim($_GET['search'] ?? '');
        $statusFilter = trim($_GET['status'] ?? '');
        $serverGroup = trim($_GET['group'] ?? '');
        $planFilter = (int)($_GET['plan_id'] ?? 0);

        $where = $isAdmin ? ["1=1"] : ["c.reseller_id = " . intval($userId)];
        $params = [];

        if (!empty($search)) {
            $where[] = "(c.username LIKE ? OR c.uuid LIKE ? OR c.custom_note LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($statusFilter)) {
            $where[] = "c.status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($serverGroup)) {
            $where[] = "s.server_group = ?";
            $params[] = $serverGroup;
        }

        if ($planFilter > 0) {
            $where[] = "c.plan_id = ?";
            $params[] = $planFilter;
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name, u.username as reseller_username 
                               FROM clients c 
                               LEFT JOIN plans p ON c.plan_id = p.id 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               LEFT JOIN users u ON c.reseller_id = u.id 
                               WHERE $whereSql 
                               ORDER BY c.id DESC");
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        Helpers::logActivity('export_csv', "خروجی اکسل CSV از فهرست " . count($clients) . " کلاینت", 'client');

        $filename = 'clients_export_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // Add UTF-8 BOM so Excel opens Persian text correctly
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['شناسه', 'نام کاربری', 'وضعیت', 'پلن', 'سرور', 'مصرف (گیگابایت)', 'حجم کل (گیگابایت)', 'تاریخ انقضا', 'نماینده', 'لینک ساب‌لینک', 'یادداشت']);

        foreach ($clients as $c) {
            $usedGb = round($c['traffic_used_bytes'] / (1024*1024*1024), 2);
            $totalGb = round($c['traffic_limit_bytes'] / (1024*1024*1024), 2);
            $subUrl = Helpers::fullUrl('sub/' . $c['sub_token']);

            fputcsv($out, [
                $c['id'],
                $c['username'],
                $c['status'],
                $c['plan_title'] ?? '',
                $c['server_name'] ?? '',
                $usedGb,
                $totalGb,
                $c['expire_at'] ?? 'نامحدود',
                $c['reseller_username'] ?? '',
                $subUrl,
                $c['custom_note'] ?? ''
            ]);
        }
        fclose($out);
        exit;
    }

    public function bulkAction(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients');
        }

        $action = trim($_POST['bulk_action'] ?? '');
        $ids = $_POST['selected_ids'] ?? [];

        if (empty($action) || empty($ids) || !is_array($ids)) {
            Helpers::flash('error', 'هیچ کاربری یا عملیاتی انتخاب نشده است.');
            Helpers::redirect('clients');
        }

        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        $clientIds = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));

        $query = "SELECT c.*, s.driver, s.api_url, s.api_username, s.api_password, s.api_token 
                  FROM clients c 
                  JOIN server_nodes s ON c.server_id = s.id 
                  WHERE c.id IN ($placeholders)";
        if (!$isAdmin) {
            $query .= " AND c.reseller_id = " . intval($userId);
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($clientIds);
        $clients = $stmt->fetchAll();

        $count = 0;
        foreach ($clients as $c) {
            try {
                $driver = DriverFactory::create($c);

                if ($action === 'extend_30_days') {
                    $addSeconds = 30 * 86400;
                    $driver->extendUser($c['username'], 0, $addSeconds);
                    $curExpire = strtotime($c['expire_at'] ?? 'now');
                    $base = max($curExpire, time());
                    $newExp = date('Y-m-d H:i:s', $base + $addSeconds);
                    $pdo->prepare("UPDATE clients SET expire_at = ?, status = 'active' WHERE id = ?")->execute([$newExp, $c['id']]);
                    $count++;
                } elseif ($action === 'add_10_gb') {
                    $addBytes = 10 * 1024 * 1024 * 1024;
                    $driver->extendUser($c['username'], $addBytes, 0);
                    $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, status = 'active' WHERE id = ?")->execute([$addBytes, $c['id']]);
                    $count++;
                } elseif ($action === 'disable') {
                    $driver->toggleUserStatus($c['username'], false);
                    $pdo->prepare("UPDATE clients SET status = 'disabled' WHERE id = ?")->execute([$c['id']]);
                    $count++;
                } elseif ($action === 'enable') {
                    $driver->toggleUserStatus($c['username'], true);
                    $pdo->prepare("UPDATE clients SET status = 'active' WHERE id = ?")->execute([$c['id']]);
                    $count++;
                } elseif ($action === 'delete') {
                    $driver->deleteUser($c['username']);
                    $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$c['id']]);
                    $count++;
                }
            } catch (Throwable $e) {}
        }

        $actionNames = [
            'extend_30_days' => 'تمدید ۳۰ روزه',
            'add_10_gb' => 'افزایش ۱۰ گیگابایت حجم',
            'disable' => 'غیرفعال‌سازی',
            'enable' => 'فعال‌سازی مجدد',
            'delete' => 'حذف قطعی'
        ];
        $label = $actionNames[$action] ?? 'عملیات';

        Helpers::logActivity('client_bulk', "عملیات گروهی {$label} روی {$count} کلاینت", 'client');
        Helpers::flash('success', "{$label} با موفقیت روی {$count} کاربر اعمال شد.");
        Helpers::redirect('clients');
    }

    public function getConfigs(): void {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();
        $userId = Auth::id();
        $isAdmin = Auth::isAdmin();

        $where = $isAdmin ? "c.id = $id" : "c.id = $id AND c.reseller_id = $userId";
        $stmt = $pdo->query("SELECT c.*, s.name as server_name, s.sub_domain, b.brand_name 
                             FROM clients c 
                             LEFT JOIN server_nodes s ON c.server_id = s.id 
                             LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id 
                             WHERE $where LIMIT 1");
        $client = $stmt->fetch();
        if (!$client) {
            Helpers::jsonResponse(['success' => false, 'message' => 'کلاینت یافت نشد.']);
        }

        require_once __DIR__ . '/SublinkController.php';
        $subCtrl = new SublinkController();
        $ref = new ReflectionMethod('SublinkController', 'buildConfigs');
        $ref->setAccessible(true);
        $configs = $ref->invoke($subCtrl, $client);

        $botUser = Setting::get('telegram_bot_username', '');
        $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);
        Helpers::jsonResponse([
            'success' => true,
            'client' => [
                'id' => $client['id'],
                'username' => $client['username'],
                'password' => $client['password'] ?: '123456',
                'sub_token' => $client['sub_token'],
                'traffic_used' => Helpers::formatBytes($client['traffic_used_bytes']),
                'traffic_limit' => Helpers::formatBytes($client['traffic_limit_bytes']),
                'days_remaining' => Helpers::daysRemaining($client['expire_at']),
                'status' => $client['status'],
                'sub_url' => $subUrl,
                'bot_bind_url' => !empty($botUser) ? "https://t.me/" . ltrim($botUser, '@') . "?start=bind_" . $client['sub_token'] : ''
            ],
            'configs' => $configs
        ]);
    }

    public function createTestAccount(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients');
        }

        $pdo = Database::getConnection();
        $user = Auth::user();
        $userId = Auth::id();

        // Server selection (chosen or default active)
        $serverId = (int)($_POST['server_id'] ?? 0);
        if ($serverId > 0) {
            $server = $pdo->query("SELECT * FROM server_nodes WHERE id = {$serverId} AND is_active = 1")->fetch();
        } else {
            $server = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        }

        if (!$server) {
            Helpers::flash('error', 'هیچ سرور فعالی برای ایجاد اکانت تست در دسترس نیست.');
            Helpers::redirect('clients');
        }

        $trafficMb = (int)($_POST['traffic_mb'] ?? 200);
        if ($trafficMb <= 0) $trafficMb = 200;
        $hours = (int)($_POST['hours'] ?? 24);
        if ($hours <= 0) $hours = 24;

        // Generate credentials
        $username = 'test_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $password = substr(bin2hex(random_bytes(3)), 0, 6);
        $uuid = Helpers::generateUUID();
        $subToken = Helpers::generateToken(24);
        $trafficBytes = (int)$trafficMb * 1024 * 1024;
        $expireAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $trafficText = ($trafficMb >= 1024) ? round($trafficMb / 1024, 1) . ' گیگابایت' : $trafficMb . ' مگابایت';
        $customNote = "اکانت تست {$hours} ساعته ({$trafficText})";

        // Remote driver creation
        $driver = DriverFactory::create($server);
        $res = $driver->createUser([
            'username' => $username,
            'password' => $password,
            'uuid' => $uuid,
            'sub_token' => $subToken,
            'traffic_limit_bytes' => $trafficBytes,
            'expire_timestamp' => strtotime($expireAt)
        ]);

        if (!$res['success']) {
            Helpers::flash('error', 'خطا در ثبت کاربر روی سرور: ' . ($res['error'] ?? 'نامشخص'));
            Helpers::redirect('clients');
        }

        $stmt = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, status, custom_note) 
                               VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 0, ?, 'active', ?)");
        $stmt->execute([$userId, $server['id'], $username, $password, $uuid, $subToken, $trafficBytes, $expireAt, $customNote]);
        $newId = $pdo->lastInsertId();

        Helpers::logActivity('test_account_create', "ایجاد اکانت تست {$trafficText} ({$username}) روی سرور {$server['name']}", 'client', $newId);
        Helpers::flash('success', "اکانت تست {$hours} ساعته با حجم {$trafficText} و نام کاربری {$username} با موفقیت صادر گردید.");
        Helpers::redirect('clients');
    }

    public function bulk(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();

        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll();
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY is_free ASC, base_price ASC")->fetchAll();
        $resellers = Auth::isAdmin() ? $pdo->query("SELECT id, username FROM users WHERE role = 'reseller'")->fetchAll() : [];

        require __DIR__ . '/../views/clients/bulk.php';
    }

    public function bulkStore(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients/bulk');
        }

        $pdo = Database::getConnection();
        $userId = Auth::id();

        $count = (int)($_POST['count'] ?? 10);
        if ($count < 1) $count = 1;
        if ($count > 100) $count = 100;

        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['prefix'] ?? 'user_'));
        if (empty($prefix)) $prefix = 'user_';

        $planId = (int)($_POST['plan_id'] ?? 0);
        $serverId = (int)($_POST['server_id'] ?? 0);
        $targetResellerId = (Auth::isAdmin() && !empty($_POST['reseller_id'])) ? (int)$_POST['reseller_id'] : $userId;

        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();
        if (!$plan) {
            Helpers::flash('error', 'پلن انتخابی نامعتبر است.');
            Helpers::redirect('clients/bulk');
        }

        $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
        $stmtServer->execute([$serverId]);
        $server = $stmtServer->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور انتخابی نامعتبر است.');
            Helpers::redirect('clients/bulk');
        }

        $trafficBytes = (int)$plan['traffic_gb'] * 1024 * 1024 * 1024;
        $durationDays = (int)$plan['duration_days'];
        $expireAt = date('Y-m-d H:i:s', time() + ($durationDays * 86400));

        $createdAccounts = [];
        $driver = DriverFactory::create($server);

        for ($i = 1; $i <= $count; $i++) {
            $uniqueSuffix = substr(bin2hex(random_bytes(3)), 0, 5);
            $username = $prefix . $uniqueSuffix;
            $password = substr(bin2hex(random_bytes(3)), 0, 6);
            $uuid = Helpers::generateUUID();
            $subToken = Helpers::generateToken(24);

            // Remote node
            $driver->createUser([
                'username' => $username,
                'password' => $password,
                'uuid' => $uuid,
                'sub_token' => $subToken,
                'traffic_limit_bytes' => $trafficBytes,
                'expire_timestamp' => strtotime($expireAt)
            ]);

            // Database insert
            $stmtInsert = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, ip_limit, max_devices, start_on_first_use, duration_days, status, custom_note) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 2, 2, 1, ?, 'active', ?)");
            $stmtInsert->execute([$targetResellerId, $serverId, $planId, $username, $password, $uuid, $subToken, $trafficBytes, $expireAt, $durationDays, "ساخت گروهی دسته {$count} تایی"]);

            $subUrl = Helpers::subUrl($subToken);
            $createdAccounts[] = [
                'username' => $username,
                'password' => $password,
                'uuid' => $uuid,
                'sub_url' => $subUrl,
                'expire_at' => $expireAt,
                'traffic_gb' => $plan['traffic_gb'],
                'duration_days' => $durationDays
            ];
        }

        Helpers::logActivity('bulk_create', "ایجاد موفقیت‌آمیز {$count} اکانت گروهی با پیش‌وند {$prefix}", 'client');
        $_SESSION['bulk_created_accounts'] = $createdAccounts;
        Helpers::redirect('clients/bulk-result');
    }

    public function bulkResult(): void {
        Auth::requireLogin();
        $accounts = $_SESSION['bulk_created_accounts'] ?? [];
        if (empty($accounts)) {
            Helpers::redirect('clients');
        }
        require __DIR__ . '/../views/clients/bulk_result.php';
    }
}

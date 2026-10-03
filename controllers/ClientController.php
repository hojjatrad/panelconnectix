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
            $where[] = "(c.username LIKE ? OR c.uuid LIKE ? OR c.custom_note LIKE ? OR c.customer_name LIKE ?)";
            $params[] = "%$search%";
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

        // Optimization Quick Filters
        $quickFilter = trim($_GET['filter'] ?? $_GET['quick_filter'] ?? '');
        $now = date('Y-m-d H:i:s');
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $fourteenDaysAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        if ($quickFilter === 'unused') {
            $where[] = "(c.traffic_used_bytes = 0 OR c.traffic_used_bytes IS NULL)";
        } elseif ($quickFilter === 'expired_7d') {
            $where[] = "(c.expire_at IS NOT NULL AND c.expire_at <= '{$sevenDaysAgo}')";
        } elseif ($quickFilter === 'expired_14d') {
            $where[] = "(c.expire_at IS NOT NULL AND c.expire_at <= '{$fourteenDaysAgo}')";
        } elseif ($quickFilter === 'expired_30d') {
            $where[] = "(c.expire_at IS NOT NULL AND c.expire_at <= '{$thirtyDaysAgo}')";
        } elseif ($quickFilter === 'expired_all') {
            $where[] = "(c.status = 'expired' OR (c.expire_at IS NOT NULL AND c.expire_at <= '{$now}'))";
        } elseif ($quickFilter === 'trials') {
            $where[] = "(c.custom_note LIKE '%تست%' OR c.custom_note LIKE '%trial%' OR p.is_free = 1)";
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

        // Optimizer Live Stats
        $baseOwnerWhere = $isAdmin ? "1=1" : "reseller_id = " . intval($userId);
        $stmtOpt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN traffic_used_bytes = 0 OR traffic_used_bytes IS NULL THEN 1 ELSE 0 END) as count_unused,
            SUM(CASE WHEN expire_at IS NOT NULL AND expire_at <= '{$sevenDaysAgo}' THEN 1 ELSE 0 END) as count_expired_7d,
            SUM(CASE WHEN expire_at IS NOT NULL AND expire_at <= '{$fourteenDaysAgo}' THEN 1 ELSE 0 END) as count_expired_14d,
            SUM(CASE WHEN expire_at IS NOT NULL AND expire_at <= '{$thirtyDaysAgo}' THEN 1 ELSE 0 END) as count_expired_30d,
            SUM(CASE WHEN status = 'expired' OR (expire_at IS NOT NULL AND expire_at <= '{$now}') THEN 1 ELSE 0 END) as count_expired_all,
            SUM(CASE WHEN (custom_note LIKE '%تست%' OR custom_note LIKE '%trial%') AND (expire_at IS NOT NULL AND expire_at <= '{$now}') THEN 1 ELSE 0 END) as count_expired_trials
            FROM clients WHERE {$baseOwnerWhere}")->fetch(PDO::FETCH_ASSOC);

        $optimizerStats = [
            'unused' => (int)($stmtOpt['count_unused'] ?? 0),
            'expired_7d' => (int)($stmtOpt['count_expired_7d'] ?? 0),
            'expired_14d' => (int)($stmtOpt['count_expired_14d'] ?? 0),
            'expired_30d' => (int)($stmtOpt['count_expired_30d'] ?? 0),
            'expired_all' => (int)($stmtOpt['count_expired_all'] ?? 0),
            'expired_trials' => (int)($stmtOpt['count_expired_trials'] ?? 0),
        ];

        // Get plans & servers for filters and modals
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        require __DIR__ . '/../views/clients/index.php';
    }

    public function create(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();

        // Fetch active servers - include VIP Connectix even if marked inactive (auto-fixed)
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 OR driver = 'connectix_seller' OR api_url LIKE '%connectix.vip%' ORDER BY CASE WHEN driver='connectix_seller' THEN 0 ELSE 1 END, name ASC")->fetchAll();
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
        $customerName = trim($_POST['customer_name'] ?? '');
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
            $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND (is_active = 1 OR driver = 'connectix_seller' OR api_url LIKE '%connectix.vip%')");
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
        // Default IP limit 4 (VIP style) if not set or 0
        $rawIpLimit = $_POST['ip_limit'] ?? '';
        if ($rawIpLimit === '' || $rawIpLimit === null) {
            $ipLimit = (int)($plan['ip_limit'] ?? 4);
            if ($ipLimit <= 0) $ipLimit = 4;
        } else {
            $ipLimit = max(0, (int)$rawIpLimit);
            if ($ipLimit === 0) {
                // If user explicitly sets 0 (unlimited) keep 0, but default to 4 when empty
                // Check if POST was empty vs 0 - we already handled empty, so 0 is intentional unlimited
                // But for VIP server, enforce 4
                $ipLimit = 0;
            }
        }
        // For Connectix Seller VIP server, always enforce 4 as cap (user requested)
        if (!empty($server['driver']) && strtolower($server['driver']) === 'connectix_seller' && $ipLimit === 0) {
            $ipLimit = 4;
        }
        // If still 0 and no plan limit, default to 4 (professional)
        if ($ipLimit === 0 && (int)($plan['ip_limit'] ?? 0) === 0) {
            $ipLimit = 4;
        }

        // 1. Provision on Remote Server Node via Driver
        $driver = DriverFactory::create($server);
        $driverPayload = [
            'username' => $username,
            'password' => $password,
            'uuid' => $uuid,
            'sub_token' => $subToken,
            'traffic_limit_bytes' => $trafficBytes,
            'expire_timestamp' => $expireTimestamp,
            'ip_limit' => $ipLimit
        ];

        $driverResult = $driver->createUser($driverPayload);
        if (!$driverResult['success']) {
            Helpers::flash('error', 'خطا در ثبت کاربر روی سرور: ' . ($driverResult['error'] ?? 'خطای نامشخص'));
            Helpers::redirect('clients/create');
        }

        // First-Connect Calculation & Status - default checked (professional)
        $startOnFirstUse = true; // Always default to true as user requested
        if (isset($_POST['start_on_first_use'])) {
            $startOnFirstUse = !empty($_POST['start_on_first_use']);
        } else {
            // If plan has explicit setting, respect it, otherwise default true
            $startOnFirstUse = !empty($plan['start_on_first_use']) ? true : true;
        }
        $durationDays = (int)($plan['duration_days'] ?? 30);
        $maxDevices = max(0, (int)($plan['max_devices'] ?? $ipLimit ?? 0));

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
            if (!empty($nodeSublink)) {
                // Domain independent replacement
                $nodeSublink = Helpers::fixSublinkDomain($nodeSublink, $server['sub_domain'] ?? $client['sub_domain'] ?? null);
            }
            $stmtInsert = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, customer_name, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, ip_limit, max_devices, start_on_first_use, duration_days, status, custom_note, node_sublink) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$userId, $serverId, $planId, $username, $password, $customerName ?: null, $uuid, $subToken, $trafficBytes, $expireAt, $ipLimit, $maxDevices, $startOnFirstUse ? 1 : 0, $durationDays, $clientStatus, $customNote, $nodeSublink]);
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
        $customerName = trim($_POST['customer_name'] ?? ($client['customer_name'] ?? ''));
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
        $ipLimit = isset($_POST['ip_limit']) && $_POST['ip_limit'] !== '' ? max(0, (int)$_POST['ip_limit']) : (int)($client['ip_limit'] ?? 0);
        $nodeSublink = !empty($_POST['node_sublink']) ? trim($_POST['node_sublink']) : ($client['node_sublink'] ?? null);
        if (!empty($nodeSublink)) {
            // Domain independent replacement
            $srvSub = $server['sub_domain'] ?? $client['sub_domain'] ?? null;
            $nodeSublink = Helpers::fixSublinkDomain($nodeSublink, $srvSub);
        }

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
            // Sync status and details with current remote node
            try {
                $driver = DriverFactory::create($client);
                if ($client['status'] !== $status) {
                    $driver->toggleUserStatus($client['username'], ($status === 'active'));
                }
                if (method_exists($driver, 'updateUser')) {
                    $driver->updateUser($client['username'], [
                        'traffic_limit_bytes' => $trafficLimitBytes,
                        'expire_timestamp' => $expireAt ? strtotime($expireAt) : 0,
                        'status' => $status
                    ]);
                }
            } catch (Throwable $e) {}
        }

        // Reset alert flags if traffic was reset or limit was increased
        $resetAlertsSql = "";
        if ($trafficUsedBytes < $client['traffic_used_bytes'] || $trafficLimitBytes > $client['traffic_limit_bytes']) {
            $resetAlertsSql = ", alert_80_sent = 0, alert_95_sent = 0";
        }

        try {
            $stmtUp = $pdo->prepare("UPDATE clients SET 
                username = ?, 
                password = ?, 
                customer_name = ?,
                server_id = ?, 
                plan_id = ?, 
                status = ?, 
                traffic_limit_bytes = ?, 
                traffic_used_bytes = ?, 
                expire_at = ?, 
                ip_limit = ?,
                telegram_chat_id = ?,
                custom_note = ?,
                node_sublink = ?
                {$resetAlertsSql},
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            $stmtUp->execute([
                $username,
                $password,
                $customerName ?: null,
                $serverId,
                $planId,
                $status,
                $trafficLimitBytes,
                $trafficUsedBytes,
                $expireAt,
                $ipLimit,
                $telegramChatId,
                $customNote,
                $nodeSublink,
                $id
            ]);

            Helpers::flash('success', "مشخصات سرویس کاربر '{$username}' با موفقیت به‌روزرسانی شد.");
        } catch (Throwable $e) {
            Helpers::flash('error', "خطا در به‌روزرسانی مشخصات کلاینت: " . $e->getMessage());
        }
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

        // v6.8.28 PRO: Auto backup before delete with warning
        try {
            require_once __DIR__ . '/../core/ServerBackupManager.php';
            ServerBackupManager::autoBackupBeforeDelete((int)$client['server_id'], 'حذف کلاینت '.$client['username']);
        } catch (Throwable $e) {}

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

        // v6.8.28 PRO: Auto backup before bulk delete
        if (str_starts_with($action, 'delete')) {
            try {
                require_once __DIR__ . '/../core/ServerBackupManager.php';
                $serverGroups = [];
                foreach ($clients as $cl) { $serverGroups[$cl['server_id']] = true; }
                foreach (array_keys($serverGroups) as $sid) {
                    ServerBackupManager::autoBackupBeforeDelete((int)$sid, 'حذف گروهی '.$action.' ('.count($clients).' کلاینت)');
                }
                if (empty($serverGroups)) {
                    ServerBackupManager::createBackup(0, 'clients', true, 'بکاپ قبل حذف گروهی '.$action);
                }
            } catch (Throwable $e) {}
        }

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
                } elseif ($action === 'delete_unused') {
                    if ((int)($c['traffic_used_bytes'] ?? 0) === 0) {
                        $driver->deleteUser($c['username']);
                        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$c['id']]);
                        $count++;
                    }
                } elseif ($action === 'delete_expired') {
                    if ($c['status'] === 'expired' || (!empty($c['expire_at']) && strtotime($c['expire_at']) <= time())) {
                        $driver->deleteUser($c['username']);
                        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$c['id']]);
                        $count++;
                    }
                } elseif ($action === 'delete_expired_7d') {
                    if (!empty($c['expire_at']) && strtotime($c['expire_at']) <= strtotime('-7 days')) {
                        $driver->deleteUser($c['username']);
                        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$c['id']]);
                        $count++;
                    }
                }
            } catch (Throwable $e) {}
        }

        $actionNames = [
            'extend_30_days' => 'تمدید ۳۰ روزه',
            'add_10_gb' => 'افزایش ۱۰ گیگابایت حجم',
            'disable' => 'غیرفعال‌سازی',
            'enable' => 'فعال‌سازی مجدد',
            'delete' => 'حذف قطعی',
            'delete_unused' => 'حذف سرویس‌های بدون مصرف',
            'delete_expired' => 'حذف سرویس‌های منقضی‌شده',
            'delete_expired_7d' => 'حذف سرویس‌های منقضی بیش از ۷ روز'
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
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 1, ?, 'active', ?)");
            $planIpLimit = max(0, (int)($plan['ip_limit'] ?? 0));
            $stmtInsert->execute([$targetResellerId, $serverId, $planId, $username, $password, $uuid, $subToken, $trafficBytes, $expireAt, $planIpLimit, $planIpLimit, $durationDays, "ساخت گروهی دسته {$count} تایی"]);

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

    /**
     * Dedicated Smart Optimizer: Purge unused / expired services from nodes and database
     */
    public function optimizePurge(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('clients');
        }

        $type = trim($_POST['purge_type'] ?? '');
        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        $ownerWhere = $isAdmin ? "1=1" : "c.reseller_id = " . intval($userId);

        $now = date('Y-m-d H:i:s');
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $fourteenDaysAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        $condition = match($type) {
            'unused' => "(c.traffic_used_bytes = 0 OR c.traffic_used_bytes IS NULL)",
            'expired_7d' => "(c.expire_at IS NOT NULL AND c.expire_at <= '{$sevenDaysAgo}')",
            'expired_14d' => "(c.expire_at IS NOT NULL AND c.expire_at <= '{$fourteenDaysAgo}')",
            'expired_30d' => "(c.expire_at IS NOT NULL AND c.expire_at <= '{$thirtyDaysAgo}')",
            'expired_all' => "(c.status = 'expired' OR (c.expire_at IS NOT NULL AND c.expire_at <= '{$now}'))",
            'trials_expired' => "((c.custom_note LIKE '%تست%' OR c.custom_note LIKE '%trial%') AND (c.expire_at IS NOT NULL AND c.expire_at <= '{$now}'))",
            default => null
        };

        if (!$condition) {
            Helpers::flash('error', 'نوع عملیات بهینه‌سازی مشخص نیست.');
            Helpers::redirect('clients');
        }

        // Fetch targets and server node credentials to remove them from remote servers
        $sql = "SELECT c.id, c.username, c.server_id, s.driver, s.api_url, s.api_username, s.api_password, s.api_token 
                FROM clients c 
                LEFT JOIN server_nodes s ON c.server_id = s.id 
                WHERE {$ownerWhere} AND {$condition}";

        $targets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        if (empty($targets)) {
            Helpers::flash('info', 'هیچ سرویسی منطبق با شرایط انتخابی جهت حذف یافت نشد.');
            Helpers::redirect('clients');
        }

        $count = 0;
        $serverNodes = [];
        foreach ($targets as $t) {
            try {
                // Delete user from remote server node
                if (!empty($t['server_id']) && !empty($t['driver'])) {
                    if (!isset($serverNodes[$t['server_id']])) {
                        $serverNodes[$t['server_id']] = DriverFactory::create($t);
                    }
                    $serverNodes[$t['server_id']]->deleteUser($t['username']);
                }
            } catch (Throwable $e) {}

            try {
                $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$t['id']]);
                $count++;
            } catch (Throwable $e) {}
        }

        $typeLabels = [
            'unused' => 'سرویس‌های بدون مصرف (حجم صفر)',
            'expired_7d' => 'سرویس‌های منقضی بیش از ۷ روز (۱ هفته)',
            'expired_14d' => 'سرویس‌های منقضی بیش از ۱۴ روز (۲ هفته)',
            'expired_30d' => 'سرویس‌های منقضی بیش از ۳۰ روز (۱ ماه)',
            'expired_all' => 'تمامی سرویس‌های منقضی‌شده',
            'trials_expired' => 'اکانت‌های تست رایگان منقضی'
        ];
        $label = $typeLabels[$type] ?? 'سرویس‌ها';

        Helpers::logActivity('client_optimize_purge', "بهینه‌سازی و پاکسازی: حذف {$count} مورد از {$label}", 'system');
        Helpers::flash('success', "بهینه‌سازی با موفقیت انجام شد: تعداد {$count} مورد از «{$label}» از روی پنل و سرورها پاکسازی گردید.");
        Helpers::redirect('clients');
    }

    public function restoreTrafficFromBackup(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();

        $targetZip = null;
        $candidateZips = glob('/tmp/connectix_backups/*.zip') ?: [];
        rsort($candidateZips);

        foreach ($candidateZips as $cz) {
            if (str_contains($cz, '2026-09-28_12-00') || str_contains($cz, '2026-09-28_11-5')) {
                $targetZip = $cz;
                break;
            }
        }
        if (!$targetZip && !empty($candidateZips)) {
            $targetZip = $candidateZips[0];
        }

        $restored = 0;
        if ($targetZip && file_exists($targetZip)) {
            $zip = new ZipArchive();
            if ($zip->open($targetZip) === true) {
                $sql = $zip->getFromIndex(0);
                $zip->close();

                preg_match('/INSERT INTO `clients` \((.*?)\) VALUES/s', $sql, $colsM);
                $cols = array_map(function($c) { return trim($c, " `\t\n\r"); }, explode(',', $colsM[1] ?? ''));
                $usedIdx = array_search('traffic_used_bytes', $cols);
                $userIdx = array_search('username', $cols);
                $statusIdx = array_search('status', $cols);
                $expireIdx = array_search('expire_at', $cols);

                preg_match_all("/INSERT INTO `clients`.*?VALUES\s*\((.*?)\);/s", $sql, $matches);
                $stUpd = $pdo->prepare("UPDATE clients SET traffic_used_bytes = ?, status = ?, expire_at = ? WHERE username = ?");

                $pdo->beginTransaction();
                foreach ($matches[1] as $valStr) {
                    $vals = str_getcsv($valStr, ',', "'");
                    $uName = trim((string)($vals[$userIdx] ?? ''));
                    $used = (int)($vals[$usedIdx] ?? 0);
                    $status = $vals[$statusIdx] ?? 'active';
                    $expire = !empty($vals[$expireIdx]) && $vals[$expireIdx] !== 'NULL' ? $vals[$expireIdx] : null;

                    if (!empty($uName) && $used > 0) {
                        $stUpd->execute([$used, $status, $expire, $uName]);
                        if ($stUpd->rowCount() > 0) {
                            $restored++;
                        }
                    }
                }
                $pdo->commit();
            }
        }

        Helpers::logActivity('clients_traffic_restored', "بازیابی ترافیک مصرفی {$restored} کلاینت از اسنپ‌شات پایدار", 'client');
        Helpers::flash('success', "میزان ترافیک مصرفی واقعی {$restored} کلاینت با موفقیت از بکاپ پایدار بازیابی شد.");
        Helpers::redirect('clients');
    }
}

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
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
        $serverId = (int)($_POST['server_id'] ?? 0);
        $customNote = trim($_POST['custom_note'] ?? '');

        if (empty($username) || empty($planId) || empty($serverId)) {
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

        // Fetch Plan & Server
        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();

        $stmtServer = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? AND is_active = 1");
        $stmtServer->execute([$serverId]);
        $server = $stmtServer->fetch();

        if (!$plan || !$server) {
            Helpers::flash('error', 'پلن یا سرور انتخاب‌شده معتبر نیست.');
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

            // Insert Client
            $stmtInsert = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, status, custom_note) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'active', ?)");
            $stmtInsert->execute([$userId, $serverId, $planId, $username, $password, $uuid, $subToken, $trafficBytes, $expireAt, $customNote]);
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

        // Find default active server
        $server = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
        if (!$server) {
            Helpers::flash('error', 'هیچ سرور فعالی برای ایجاد اکانت تست در دسترس نیست.');
            Helpers::redirect('clients');
        }

        // Generate credentials
        $username = 'test_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $password = substr(bin2hex(random_bytes(3)), 0, 6);
        $uuid = Helpers::generateUUID();
        $subToken = Helpers::generateToken(24);
        $trafficBytes = 1 * 1024 * 1024 * 1024; // 1 GB test
        $expireAt = date('Y-m-d H:i:s', strtotime("+1 day"));
        $customNote = 'اکانت تست ۲۴ ساعته (رایگان)';

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

        Helpers::logActivity('test_account_create', "ایجاد اکانت تست ۲۴ ساعته {$username} روی سرور {$server['name']}", 'client', $newId);
        Helpers::flash('success', "اکانت تست ۱ روزه با نام کاربری {$username} با موفقیت صادر گردید.");
        Helpers::redirect('clients');
    }
}

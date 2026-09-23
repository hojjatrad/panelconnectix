<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class ServerController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        $servers = $pdo->query("SELECT s.*, c.name as category_name, c.badge_color as category_color, c.icon as category_icon,
                                (SELECT COUNT(*) FROM clients WHERE server_id = s.id) as client_count 
                                FROM server_nodes s 
                                LEFT JOIN categories c ON (s.category_id = c.id OR (s.category_id IS NULL AND s.server_group = c.slug))
                                ORDER BY s.id ASC")->fetchAll();

        $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 AND type IN ('server', 'both') ORDER BY sort_order ASC, id ASC")->fetchAll();

        // Query live status for each server
        $serverStats = [];
        foreach ($servers as $s) {
            try {
                $driver = DriverFactory::create($s);
                $serverStats[$s['id']] = $driver->getNodeStats();
            } catch (Exception $e) {
                $serverStats[$s['id']] = ['status' => 'error', 'users' => 0];
            }
        }

        require __DIR__ . '/../views/servers/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $name = trim($_POST['name'] ?? '');
        $driver = trim($_POST['driver'] ?? 'marzban');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subDomain = trim($_POST['sub_domain'] ?? '');
        $maxClients = (int)($_POST['max_clients'] ?? 500);

        if (empty($name) || empty($apiUrl)) {
            Helpers::flash('error', 'نام سرور و آدرس API الزامی هستند.');
            Helpers::redirect('servers');
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . $categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }

        $stmt = $pdo->prepare("INSERT INTO server_nodes (name, driver, api_url, api_username, api_password, api_token, server_group, category_id, sub_domain, max_clients) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $driver, $apiUrl, $username, $password, $token, $serverGroup, $categoryId, $subDomain, $maxClients]);

        Helpers::flash('success', 'سرور جدید با موفقیت به سامانه افزوده شد.');
        Helpers::redirect('servers');
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $driver = trim($_POST['driver'] ?? 'marzban');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subDomain = trim($_POST['sub_domain'] ?? '');
        $maxClients = (int)($_POST['max_clients'] ?? 500);

        if ($id <= 0 || empty($name) || empty($apiUrl)) {
            Helpers::flash('error', 'اطلاعات ارسالی سرور ناقص است.');
            Helpers::redirect('servers');
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . $categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }

        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_password = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ? WHERE id = ?");
            $stmt->execute([$name, $driver, $apiUrl, $username, $password, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ? WHERE id = ?");
            $stmt->execute([$name, $driver, $apiUrl, $username, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $id]);
        }

        Helpers::flash('success', "تنظیمات سرور '{$name}' با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('servers');
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();

        // Check if clients exist on this server
        $count = $pdo->query("SELECT COUNT(*) FROM clients WHERE server_id = $id")->fetchColumn();
        if ($count > 0) {
            Helpers::flash('error', "این سرور دارای {$count} کلاینت فعال است و امکان حذف مستقیم آن وجود ندارد. ابتدا کلاینت‌ها را منتقل یا حذف کنید.");
            Helpers::redirect('servers');
        }

        $pdo->prepare("DELETE FROM server_nodes WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'سرور با موفقیت حذف شد.');
        Helpers::redirect('servers');
    }

    public function ping(): void {
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();
        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = $id")->fetch();

        if (!$server) {
            Helpers::jsonResponse(['success' => false, 'message' => 'سرور یافت نشد.']);
        }

        $parsed = parse_url($server['api_url']);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

        if ($server['driver'] === 'mock') {
            $latency = rand(18, 45);
            Helpers::jsonResponse([
                'success' => true,
                'status' => 'online',
                'latency' => $latency,
                'message' => "سرور شبیه‌ساز فعال است ({$latency}ms)"
            ]);
        }

        if (empty($host)) {
            Helpers::jsonResponse(['success' => false, 'status' => 'offline', 'message' => 'آدرس هاست نامعتبر است.']);
        }

        $startTime = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 2.5);

        if ($socket) {
            $latency = round((microtime(true) - $startTime) * 1000);
            fclose($socket);
            Helpers::jsonResponse([
                'success' => true,
                'status' => 'online',
                'latency' => $latency,
                'message' => "پاسخ دریافت شد ({$latency}ms)"
            ]);
        } else {
            Helpers::jsonResponse([
                'success' => false,
                'status' => 'offline',
                'latency' => null,
                'message' => "عدم پاسخگویی یا تایم‌اوت ({$errstr})"
            ]);
        }
    }

    public function testConnection(): void {
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();
        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = $id")->fetch();

        if (!$server) {
            Helpers::jsonResponse(['success' => false, 'message' => 'سرور یافت نشد.']);
        }

        try {
            $driver = DriverFactory::create($server);
            $auth = $driver->authenticate();
            $stats = $driver->getNodeStats();
            Helpers::jsonResponse([
                'success' => $auth,
                'message' => $auth ? 'اتصال با موفقیت برقرار شد!' : 'عدم توانایی در احراز هویت با سرور',
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Helpers::jsonResponse(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function syncNow(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $stmt = $pdo->query("SELECT c.*, s.name as server_name, s.driver as server_driver, s.api_url, s.api_username, s.api_password, s.api_token,
                                    rp.id as reserved_id, rp.traffic_gb as reserved_gb, rp.duration_days as reserved_days
                             FROM clients c 
                             JOIN server_nodes s ON c.server_id = s.id 
                             LEFT JOIN reserved_plans rp ON rp.client_id = c.id AND rp.status = 'queued'
                             WHERE c.status != 'disabled'");
        $clients = $stmt->fetchAll();

        $synced = 0;
        $reservedActivated = 0;

        foreach ($clients as $c) {
            try {
                $driver = DriverFactory::create($c);
                $remoteData = $driver->getUser($c['username']);
                if ($remoteData && isset($remoteData['traffic_used_bytes'])) {
                    $pdo->prepare("UPDATE clients SET traffic_used_bytes = ? WHERE id = ?")->execute([$remoteData['traffic_used_bytes'], $c['id']]);
                    $c['traffic_used_bytes'] = $remoteData['traffic_used_bytes'];
                }

                $isTrafficDone = ($c['traffic_used_bytes'] >= $c['traffic_limit_bytes']);
                $isTimeDone = (!empty($c['expire_at']) && strtotime($c['expire_at']) <= time());

                if (($isTrafficDone || $isTimeDone) && !empty($c['reserved_id'])) {
                    $addBytes = (int)$c['reserved_gb'] * 1024 * 1024 * 1024;
                    $newExpire = date('Y-m-d H:i:s', time() + ($c['reserved_days'] * 86400));
                    $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active' WHERE id = ?")
                        ->execute([$addBytes, $newExpire, $c['id']]);
                    $pdo->prepare("UPDATE reserved_plans SET status = 'applied', applied_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$c['reserved_id']]);
                    $driver->extendUser($c['username'], $addBytes, $c['reserved_days'] * 86400);
                    $reservedActivated++;
                }
                $synced++;
            } catch (Throwable $e) {}
        }

        Helpers::flash('success', "همگام‌سازی لحظه‌ای با موفقیت انجام شد: {$synced} کلاینت بررسی و {$reservedActivated} پلن رزرو فعال شدند.");
        Helpers::redirect('servers');
    }

    /**
     * Run full health check and ping on all active server nodes
     */
    public static function performHealthCheck(): array {
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll();
        $results = [];

        foreach ($servers as $server) {
            $parsed = parse_url($server['api_url']);
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? (($parsed['scheme'] ?? 'http') === 'https' ? 443 : 80);

            if ($server['driver'] === 'mock') {
                $latency = rand(15, 35);
                $status = 'online';
                $err = null;
            } elseif (empty($host)) {
                $status = 'offline';
                $latency = 9999;
                $err = 'آدرس نامعتبر';
            } else {
                $startTime = microtime(true);
                $errno = 0;
                $errstr = '';
                $socket = @fsockopen($host, $port, $errno, $errstr, 2.5);
                if ($socket) {
                    $latency = (int)round((microtime(true) - $startTime) * 1000);
                    fclose($socket);
                    $status = ($latency > 1500) ? 'degraded' : 'online';
                    $err = null;
                } else {
                    $status = 'offline';
                    $latency = 9999;
                    $err = $errstr ?: 'تایم‌اوت در اتصال';
                }
            }

            try {
                $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = ? WHERE id = ?")
                    ->execute([$status, $latency, date('Y-m-d H:i:s'), $err, $server['id']]);
            } catch (Throwable $e) {}

            $results[$server['id']] = [
                'name' => $server['name'],
                'status' => $status,
                'latency' => $latency,
                'error' => $err
            ];
        }

        return $results;
    }

    public function checkHealth(): void {
        Auth::requireAdmin();
        $results = self::performHealthCheck();
        Helpers::flash('success', 'پایش سلامت ' . count($results) . ' سرور با موفقیت انجام شد و وضعیت‌ها به‌روزرسانی گردید.');
        Helpers::redirect('servers');
    }

    /**
     * Bulk Migrate all clients from one server node to another (Failover & Maintenance)
     */
    public function migrateClients(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $fromServerId = (int)($_POST['from_server_id'] ?? 0);
        $toServerId = (int)($_POST['to_server_id'] ?? 0);

        if ($fromServerId <= 0 || $toServerId <= 0 || $fromServerId === $toServerId) {
            Helpers::flash('error', 'لطفاً سرور مبدا و مقصد را به درستی و متفاوت از هم انتخاب نمایید.');
            Helpers::redirect('servers');
        }

        $pdo = Database::getConnection();
        $fromServer = $pdo->query("SELECT * FROM server_nodes WHERE id = {$fromServerId}")->fetch();
        $toServer = $pdo->query("SELECT * FROM server_nodes WHERE id = {$toServerId}")->fetch();

        if (!$fromServer || !$toServer) {
            Helpers::flash('error', 'سرور مبدا یا مقصد یافت نشد.');
            Helpers::redirect('servers');
        }

        $clients = $pdo->query("SELECT * FROM clients WHERE server_id = {$fromServerId}")->fetchAll();
        if (empty($clients)) {
            Helpers::flash('info', 'هیچ کلاینتی بر روی سرور مبدا برای انتقال وجود ندارد.');
            Helpers::redirect('servers');
        }

        $toDriver = DriverFactory::create($toServer);
        $migratedCount = 0;
        $failedCount = 0;

        foreach ($clients as $c) {
            try {
                $toDriver->createUser([
                    'username' => $c['username'],
                    'uuid' => $c['uuid'],
                    'traffic_limit_bytes' => $c['traffic_limit_bytes'],
                    'expire_timestamp' => !empty($c['expire_at']) ? strtotime($c['expire_at']) : 0,
                    'proxies' => ['vless', 'vmess', 'trojan']
                ]);

                $pdo->prepare("UPDATE clients SET server_id = ? WHERE id = ?")->execute([$toServerId, $c['id']]);
                $migratedCount++;
            } catch (Throwable $e) {
                $failedCount++;
            }
        }

        Helpers::flash('success', "عملیات مهاجرت دسته‌جمعی به پایان رسید: {$migratedCount} کلاینت با موفقیت از '{$fromServer['name']}' به '{$toServer['name']}' منتقل شدند." . ($failedCount > 0 ? " ({$failedCount} خطا)" : ""));
        Helpers::redirect('servers');
    }
}

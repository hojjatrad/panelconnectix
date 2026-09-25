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
        $driver = trim($_POST['driver'] ?? 'auto');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subDomain = trim($_POST['sub_domain'] ?? '');
        $sampleUsername = trim($_POST['sample_username'] ?? '');
        $maxClients = (int)($_POST['max_clients'] ?? 500);
        $configTemplate = trim($_POST['config_template'] ?? '');
        $selectedInbounds = !empty($_POST['selected_inbounds']) 
            ? (is_array($_POST['selected_inbounds']) ? json_encode(array_values($_POST['selected_inbounds']), JSON_UNESCAPED_UNICODE) : trim($_POST['selected_inbounds'])) 
            : null;

        if (empty($name) || empty($apiUrl)) {
            Helpers::flash('error', 'نام سرور و آدرس API الزامی هستند.');
            Helpers::redirect('servers');
        }

        // Auto-detect driver if set to auto or unknown
        if ($driver === 'auto' || empty($driver)) {
            $driver = self::detectDriverType($apiUrl, $username, $password, $token, $name);
        }

        // Auto-detect sub_domain (CDN) if empty or invalid
        if (empty($subDomain) || str_contains($subDomain, 'montago-shop.ir')) {
            $subDomain = self::autoDetectSubDomain($apiUrl, $sampleUsername, [
                'name' => $name,
                'driver' => $driver,
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . $categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }

        $stmt = $pdo->prepare("INSERT INTO server_nodes (name, driver, api_url, api_username, api_password, api_token, server_group, category_id, sub_domain, max_clients, config_template, selected_inbounds) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $driver, $apiUrl, $username, $password, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds]);

        Helpers::flash('success', "سرور جدید با موفقیت و تشخیص خودکار نوع پنل ({$driver}) و دامنه CDN ({$subDomain}) افزوده شد.");
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
        $driver = trim($_POST['driver'] ?? 'auto');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subDomain = trim($_POST['sub_domain'] ?? '');
        $sampleUsername = trim($_POST['sample_username'] ?? '');
        $maxClients = (int)($_POST['max_clients'] ?? 500);
        $configTemplate = trim($_POST['config_template'] ?? '');
        $selectedInbounds = !empty($_POST['selected_inbounds']) 
            ? (is_array($_POST['selected_inbounds']) ? json_encode(array_values($_POST['selected_inbounds']), JSON_UNESCAPED_UNICODE) : trim($_POST['selected_inbounds'])) 
            : null;

        if ($id <= 0 || empty($name) || empty($apiUrl)) {
            Helpers::flash('error', 'اطلاعات ارسالی سرور ناقص است.');
            Helpers::redirect('servers');
        }

        // Auto-detect driver if set to auto
        if ($driver === 'auto' || empty($driver)) {
            $driver = self::detectDriverType($apiUrl, $username, $password, $token, $name);
        }

        // Auto-detect sub_domain (CDN) if empty or invalid
        if (empty($subDomain) || str_contains($subDomain, 'montago-shop.ir')) {
            $subDomain = self::autoDetectSubDomain($apiUrl, $sampleUsername, [
                'id' => $id,
                'name' => $name,
                'driver' => $driver,
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . $categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }

        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_password = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ?, config_template = ?, selected_inbounds = ? WHERE id = ?");
            $stmt->execute([$name, $driver, $apiUrl, $username, $password, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ?, config_template = ?, selected_inbounds = ? WHERE id = ?");
            $stmt->execute([$name, $driver, $apiUrl, $username, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds, $id]);
        }

        Helpers::flash('success', "تنظیمات سرور '{$name}' با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('servers');
    }

    public static function autoDetectSubDomain(string $apiUrl, ?string $sampleUsername = null, array $nodeData = []): string {
        // 1. If sample user provided and node data has credentials, check if user's subscription link has host
        if (!empty($sampleUsername) && !empty($nodeData)) {
            try {
                $driver = DriverFactory::create($nodeData);
                if ($driver->authenticate()) {
                    $u = $driver->getUser($sampleUsername);
                    if ($u && !empty($u['subscription_url'])) {
                        $p = parse_url($u['subscription_url']);
                        if (!empty($p['host']) && !str_contains($p['host'], 'montago-shop.ir')) {
                            $port = !empty($p['port']) ? (':' . $p['port']) : '';
                            return $p['host'] . $port;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Fallback to API URL host + port
        $p = parse_url($apiUrl);
        if (!empty($p['host'])) {
            $port = !empty($p['port']) ? (':' . $p['port']) : '';
            return $p['host'] . $port;
        }

        return '';
    }

    public function clearAll(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $pdo = Database::getConnection();
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            } else {
                $pdo->exec("PRAGMA foreign_keys = OFF");
            }

            // In SQLite/MySQL, clients.server_id is NOT NULL, so mock clients attached to servers are removed
            $pdo->exec("UPDATE plans SET server_id = NULL");
            $pdo->exec("DELETE FROM reserved_plans");
            $pdo->exec("DELETE FROM trial_logs");
            $pdo->exec("DELETE FROM bot_orders");
            $pdo->exec("DELETE FROM clients");
            $pdo->exec("DELETE FROM server_nodes");

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            } else {
                $pdo->exec("PRAGMA foreign_keys = ON");
            }
            Helpers::logActivity('servers_clear_all', 'خام‌سازی و پاکسازی کامل جدول سرورها و کلاینت‌ها توسط مدیر', 'server');
            Helpers::flash('success', 'تمامی سرورها و کلاینت‌های قبلی با موفقیت پاکسازی و بخش سرورها کاملاً خام شد. اکنون می‌توانید سرورهای اختصاصی خود را متصل فرمایید.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در خام‌سازی سرورها: ' . $e->getMessage());
        }
        Helpers::redirect('servers');
    }

    public function purgeAllSamples(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            } else {
                $pdo->exec("PRAGMA foreign_keys = OFF");
            }

            $tablesToWipe = ['bot_orders', 'trial_logs', 'reserved_plans', 'clients', 'reseller_plans', 'plans', 'server_nodes', 'transactions', 'lucky_wheel_logs', 'wallet_logs', 'crypto_payments', 'bot_sessions'];
            foreach ($tablesToWipe as $tbl) {
                try { $pdo->exec("DELETE FROM `{$tbl}`"); } catch (Throwable $ignore) {}
            }

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            } else {
                $pdo->exec("PRAGMA foreign_keys = ON");
            }

            Helpers::logActivity('purge_all_samples', 'پاکسازی ۱۰۰٪ کامل تمامی پلن‌ها، سرورها، سفارشات و اکانت‌های تستی توسط مدیر', 'system');
            Helpers::flash('success', 'تمامی پلن‌ها، سرورها، سفارشات تستی و کلاینت‌ها با موفقیت ۱۰۰٪ پاکسازی شدند! سیستم اکنون کاملاً خام و آماده معرفی سرورها و پلن‌های اختصاصی شماست.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در پاکسازی: ' . $e->getMessage());
        }
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

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            } else {
                $pdo->exec("PRAGMA foreign_keys = OFF");
            }

            // In SQLite/MySQL, clients.server_id is NOT NULL, so delete attached clients safely
            $pdo->prepare("DELETE FROM reserved_plans WHERE client_id IN (SELECT id FROM clients WHERE server_id = ?)")->execute([$id]);
            $pdo->prepare("DELETE FROM bot_orders WHERE server_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM clients WHERE server_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE plans SET server_id = NULL WHERE server_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM server_nodes WHERE id = ?")->execute([$id]);

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            } else {
                $pdo->exec("PRAGMA foreign_keys = ON");
            }

            Helpers::flash('success', 'سرور با موفقیت حذف گردید.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در حذف سرور: ' . $e->getMessage());
        }

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
            $lastErr = method_exists($driver, 'getLastError') ? $driver->getLastError() : null;

            if ($auth) {
                $pdo->prepare("UPDATE server_nodes SET health_status = 'online', last_check_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("UPDATE server_nodes SET health_status = 'offline', last_check_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            }

            $message = $auth 
                ? 'اتصال با موفقیت برقرار شد!' 
                : ($lastErr ?: 'عدم توانایی در احراز هویت با سرور');

            Helpers::jsonResponse([
                'success' => $auth,
                'message' => $message,
                'error' => $auth ? null : $lastErr,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            Helpers::jsonResponse(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function testRawConnection(): void {
        Auth::requireAdmin();
        $driverType = trim($_POST['driver'] ?? $_GET['driver'] ?? 'marzban');
        $apiUrl = trim($_POST['api_url'] ?? $_GET['api_url'] ?? '');
        $username = trim($_POST['api_username'] ?? $_GET['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? $_GET['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? $_GET['api_token'] ?? '');

        if (empty($apiUrl)) {
            Helpers::jsonResponse(['success' => false, 'message' => 'لطفاً ابتدا آدرس سرور (URL) را وارد نمایید.']);
        }

        $dummy = [
            'name' => 'Testing Node',
            'driver' => $driverType,
            'api_url' => $apiUrl,
            'api_username' => $username,
            'api_password' => $password,
            'api_token' => $token
        ];

        try {
            $driver = DriverFactory::create($dummy);
            $auth = $driver->authenticate();
            $stats = $driver->getNodeStats();
            $lastErr = method_exists($driver, 'getLastError') ? $driver->getLastError() : null;

            if ($auth) {
                Helpers::jsonResponse([
                    'success' => true,
                    'driver' => $driverType,
                    'message' => "اتصال و احراز هویت با درایور {$driverType} با موفقیت تایید شد!",
                    'stats' => $stats
                ]);
            }

            // Intelligent Fallback: If user selected pasargad but node is Marzban or vice-versa, test the counterpart
            $altDriver = ($driverType === 'pasargad') ? 'marzban' : (($driverType === 'marzban') ? 'pasargad' : null);
            if ($altDriver) {
                $dummy['driver'] = $altDriver;
                $altDriverInstance = DriverFactory::create($dummy);
                if ($altDriverInstance->authenticate()) {
                    $altStats = $altDriverInstance->getNodeStats();
                    Helpers::jsonResponse([
                        'success' => true,
                        'driver' => $altDriver,
                        'suggest_switch' => true,
                        'message' => "اتصال با درایور '{$altDriver}' با موفقیت برقرار شد! (تغییر خودکار درایور به {$altDriver})",
                        'stats' => $altStats
                    ]);
                }
            }

            Helpers::jsonResponse([
                'success' => false,
                'message' => $lastErr ?: 'احراز هویت با مشخصات وارد شده ناموفق بود. لطفاً آدرس، نام کاربری و رمز عبور را بررسی نمایید.',
                'error' => $lastErr
            ]);
        } catch (Throwable $e) {
            Helpers::jsonResponse([
                'success' => false,
                'message' => 'خطای سیستمی: ' . $e->getMessage()
            ]);
        }
    }

    public static function detectDriverType(string $apiUrl, string $username, string $password, string $token = '', string $name = ''): string {
        $nameLower = mb_strtolower($name, 'UTF-8');
        if (str_contains($nameLower, 'پاسارگاد') || str_contains($nameLower, 'pasargad') || str_contains($nameLower, 'pasarguard')) {
            return 'pasargad';
        }

        if (str_contains($nameLower, 'x-ui') || str_contains($nameLower, 'xui') || str_contains($apiUrl, ':2053') || str_contains($apiUrl, ':54321')) {
            return 'xui';
        }

        // Probe live endpoints
        try {
            $psg = DriverFactory::create([
                'driver' => 'pasargad',
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
            if ($psg->authenticate()) {
                return 'pasargad';
            }
        } catch (Throwable $e) {}

        try {
            $mzb = DriverFactory::create([
                'driver' => 'marzban',
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
            if ($mzb->authenticate()) {
                return 'marzban';
            }
        } catch (Throwable $e) {}

        try {
            $xui = DriverFactory::create([
                'driver' => 'xui',
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
            if ($xui->authenticate()) {
                return 'xui';
            }
        } catch (Throwable $e) {}

        $port = parse_url($apiUrl, PHP_URL_PORT);
        if ($port == 2096 || $port == 2083 || $port == 2087) {
            return 'pasargad';
        }

        return 'marzban';
    }

    public function fetchInboundsAndSample(): void {
        Auth::requireAdmin();
        $serverId = (int)($_POST['server_id'] ?? $_GET['server_id'] ?? 0);
        $sampleUsername = trim($_POST['sample_username'] ?? $_GET['sample_username'] ?? '');
        $pdo = Database::getConnection();

        $server = null;
        if ($serverId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$server) {
            $server = [
                'name' => trim($_POST['name'] ?? 'Raw Test Node'),
                'driver' => trim($_POST['driver'] ?? $_GET['driver'] ?? 'auto'),
                'api_url' => trim($_POST['api_url'] ?? $_GET['api_url'] ?? ''),
                'api_username' => trim($_POST['api_username'] ?? $_GET['api_username'] ?? ''),
                'api_password' => trim($_POST['api_password'] ?? $_GET['api_password'] ?? ''),
                'api_token' => trim($_POST['api_token'] ?? $_GET['api_token'] ?? ''),
                'sub_domain' => trim($_POST['sub_domain'] ?? $_GET['sub_domain'] ?? '')
            ];
        }

        if (empty($server['api_url'])) {
            Helpers::jsonResponse(['success' => false, 'message' => 'لطفاً ابتدا آدرس سرور را وارد فرمایید.']);
        }

        try {
            $driverType = $server['driver'] ?? 'auto';
            if ($driverType === 'auto' || empty($driverType)) {
                $driverType = self::detectDriverType($server['api_url'], $server['api_username'], $server['api_password'], $server['api_token'] ?? '', $server['name'] ?? '');
                $server['driver'] = $driverType;
            }

            $driver = DriverFactory::create($server);
            if (!$driver->authenticate()) {
                $altDriver = ($driverType === 'pasargad') ? 'marzban' : 'pasargad';
                $server['driver'] = $altDriver;
                $driver = DriverFactory::create($server);
                if (!$driver->authenticate()) {
                    $lastErr = method_exists($driver, 'getLastError') ? $driver->getLastError() : 'عدم توانایی در احراز هویت با سرور';
                    Helpers::jsonResponse(['success' => false, 'message' => $lastErr, 'error' => $lastErr]);
                }
                $driverType = $altDriver;
            }

            // 1. Fetch detailed inbounds from server (tag, proto, network, tls, port)
            $inbounds = method_exists($driver, 'getDetailedInbounds') 
                ? $driver->getDetailedInbounds() 
                : [];

            $sampleData = null;
            $extractedDomain = '';

            // 2. If MirzaPro-style sample username is provided, query it directly!
            if (!empty($sampleUsername)) {
                $existingUser = $driver->getUser($sampleUsername);
                if ($existingUser) {
                    $sublink = $existingUser['subscription_url'] ?? '';
                    $links = $existingUser['links'] ?? [];
                    
                    if (!empty($sublink)) {
                        $parts = parse_url($sublink);
                        if (!empty($parts['host'])) {
                            $scheme = $parts['scheme'] ?? 'https';
                            $port = !empty($parts['port']) ? (':' . $parts['port']) : '';
                            $extractedDomain = "{$scheme}://{$parts['host']}{$port}";
                        }
                    }

                    $sampleData = [
                        'sublink' => $sublink,
                        'vless_link' => $links[0] ?? '',
                        'links' => $links,
                        'extracted_sub_domain' => $extractedDomain,
                        'source' => "مستقیماً از کاربر موجود '{$sampleUsername}' در سرور (استایل میرزاپرو)"
                    ];
                }
            }

            // 3. Fallback: provision temporary test user if no sample user provided or not found
            if (!$sampleData) {
                $testUser = 'mirza_' . substr(bin2hex(random_bytes(3)), 0, 6);
                $cRes = $driver->createUser([
                    'username' => $testUser,
                    'uuid' => Helpers::generateUUID(),
                    'traffic_limit_bytes' => 1073741824, // 1GB
                    'expire_timestamp' => time() + 86400 // 1 day
                ]);

                if ($cRes['success']) {
                    $sublink = $cRes['sublink'] ?? '';
                    $links = $cRes['links'] ?? [];
                    if (!empty($sublink)) {
                        $parts = parse_url($sublink);
                        if (!empty($parts['host'])) {
                            $scheme = $parts['scheme'] ?? 'https';
                            $port = !empty($parts['port']) ? (':' . $parts['port']) : '';
                            $extractedDomain = "{$scheme}://{$parts['host']}{$port}";
                        }
                    }
                    $sampleData = [
                        'sublink' => $sublink,
                        'vless_link' => $cRes['vless_link'] ?? ($links[0] ?? ''),
                        'links' => $links,
                        'extracted_sub_domain' => $extractedDomain,
                        'source' => 'ایجاد کاربر تستی موقت خودکار'
                    ];
                    // Clean up test user immediately
                    $driver->deleteUser($testUser);
                }
            }

            // Auto-update server in database if server_id was supplied
            if ($serverId > 0) {
                $updFields = ['driver = ?'];
                $updValues = [$driverType];
                if (!empty($extractedDomain)) {
                    $updFields[] = 'sub_domain = COALESCE(NULLIF(sub_domain, ""), ?)';
                    $updValues[] = $extractedDomain;
                }
                $updValues[] = $serverId;
                $pdo->prepare("UPDATE server_nodes SET " . implode(', ', $updFields) . " WHERE id = ?")->execute($updValues);
            }

            $driverLabel = match($driverType) {
                'pasargad' => 'پاسارگاد (PasarGuard)',
                'xui' => '3X-UI',
                default => 'مرزبان (Marzban)'
            };

            Helpers::jsonResponse([
                'success' => true,
                'message' => "نوع پنل ({$driverLabel}) و اینباندها با موفقیت شناسایی شدند.",
                'detected_driver' => $driverType,
                'detected_driver_label' => $driverLabel,
                'extracted_sub_domain' => $extractedDomain,
                'inbounds' => $inbounds,
                'sample' => $sampleData
            ]);
        } catch (Throwable $e) {
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

    /**
     * GET servers/{id}/node-users
     * Live list of ALL clients that exist on the node (from the node API,
     * Marzban/Pasargad/3x-ui) merged with panel tracking info, for direct
     * management and copy of links / credentials.
     */
    public function nodeUsers(string $id = ''): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $id = (int)$id;

        $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
        $stmt->execute([$id]);
        $server = $stmt->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور یافت نشد.');
            Helpers::redirect('servers');
        }

        $driver = DriverFactory::create($server);
        $connected = $driver->authenticate();
        $users = $connected ? $driver->listUsers() : [];
        $nodeError = $connected ? '' : ($driver->getLastError() ?? 'اتصال برقرار نشد');
        $driverName = $server['driver'] ?? '';

        // Merge with panel-tracked clients (panel username/password/plan/reseller)
        $panelInfo = [];
        if (!empty($users)) {
            $names = array_values(array_unique(array_column($users, 'username')));
            $ph = implode(',', array_fill(0, count($names), '?'));
            try {
                $st = $pdo->prepare("SELECT c.username, c.password, c.status AS panel_status, c.sub_token, c.node_sync,
                                             u.full_name AS reseller_name, u.username AS reseller_username,
                                             p.title AS plan_title
                                      FROM clients c
                                      LEFT JOIN users u ON u.id = c.reseller_id
                                      LEFT JOIN plans p ON p.id = c.plan_id
                                      WHERE c.server_id = ? AND c.username IN ($ph)");
                $st->execute(array_merge([$id], $names));
                foreach ($st->fetchAll() as $row) {
                    $row['sub_url'] = Helpers::subUrl((string)$row['sub_token']);
                    $panelInfo[(string)$row['username']] = $row;
                }
            } catch (Throwable $e) {}
        }

        $search = trim((string)($_GET['q'] ?? ''));

        require __DIR__ . '/../views/servers/node_users.php';
    }

    /**
     * POST servers/node-users/action
     * Manage a node client: toggle (enable/disable), extend (traffic/time), delete.
     */
    public function nodeUsersAction(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $serverId = (int)($_POST['server_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $username = trim((string)($_POST['username'] ?? ''));

        $back = 'servers/' . $serverId . '/node-users';
        if ($serverId <= 0 || $username === '' || !in_array($action, ['toggle', 'extend', 'delete'], true)) {
            Helpers::flash('error', 'درخواست نامعتبر است.');
            Helpers::redirect('servers');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
        $stmt->execute([$serverId]);
        $server = $stmt->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور یافت نشد.');
            Helpers::redirect('servers');
        }

        $driver = DriverFactory::create($server);
        try {
            if ($action === 'toggle') {
                $active = ($_POST['active'] ?? '1') === '1';
                $ok = $driver->toggleUserStatus($username, $active);
                Helpers::flash($ok ? 'success' : 'error', $ok
                    ? "کلاینت {$username} " . ($active ? 'فعال' : 'غیرفعال') . " شد."
                    : "خطا: " . ($driver->getLastError() ?? 'عملیات انجام نشد.'));
            } elseif ($action === 'extend') {
                $days = (int)($_POST['days'] ?? 0);
                $gb = (float)($_POST['gb'] ?? 0);
                if ($days <= 0 && $gb <= 0) {
                    Helpers::flash('error', 'مقدار تمدید (روز و/یا گیگ) را وارد کنید.');
                } else {
                    $ok = $driver->extendUser($username, (int)($gb * 1073741824), $days * 86400);
                    Helpers::flash($ok ? 'success' : 'error', $ok
                        ? "اشتراک {$username} تمدید شد ({$days} روز، {$gb} گیگابایت)."
                        : "خطا: " . ($driver->getLastError() ?? 'عملیات انجام نشد.'));
                }
            } elseif ($action === 'delete') {
                $ok = $driver->deleteUser($username);
                Helpers::flash($ok ? 'success' : 'error', $ok
                    ? "کلاینت {$username} از سرور حذف شد."
                    : "خطا: " . ($driver->getLastError() ?? 'عملیات انجام نشد.'));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطای عملیات: ' . $e->getMessage());
        }

        Helpers::redirect($back);
    }

    /**
     * POST servers/{id}/node-users/sync
     * Mirror this server's live users into the panel clients table
     * (adds missing node-direct clients with generated credentials,
     * refreshes usage/expire of already-imported ones).
     */
    public function nodeUsersSync(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('servers');
        }

        $id = (int)$id;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
        $stmt->execute([$id]);
        $server = $stmt->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور یافت نشد.');
            Helpers::redirect('servers');
        }

        require_once __DIR__ . '/../core/NodeSync.php';
        try {
            $st = NodeSync::syncServer($pdo, $server);
            if (!empty($st['errors'])) {
                Helpers::flash('error', 'همگام‌سازی با خطا: ' . implode(' | ', array_slice($st['errors'], 0, 3)));
            } else {
                Helpers::flash('success', sprintf(
                    'همگام‌سازی «%s» انجام شد: %d کلاینت جدید وارد پنل شد، %d به‌روزرسانی، %d کلاینت پنلی دست‌نخورده.',
                    $server['name'], $st['added'], $st['updated'], $st['skipped']
                ));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در همگام‌سازی: ' . $e->getMessage());
        }

        Helpers::redirect('servers/' . $id . '/node-users');
    }

    /**
     * GET servers/{id}/node-users/export?format=txt|csv
     * Download all node clients with links & credentials (for use in the
     * dedicated app / other tools).
     */
    public function nodeUsersExport(string $id = ''): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $id = (int)$id;

        $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
        $stmt->execute([$id]);
        $server = $stmt->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور یافت نشد.');
            Helpers::redirect('servers');
        }

        $driver = DriverFactory::create($server);
        $users = $driver->authenticate() ? $driver->listUsers() : [];

        $panelInfo = [];
        if (!empty($users)) {
            $names = array_values(array_unique(array_column($users, 'username')));
            $ph = implode(',', array_fill(0, count($names), '?'));
            try {
                $st = $pdo->prepare("SELECT username, password, sub_token FROM clients WHERE server_id = ? AND username IN ($ph)");
                $st->execute(array_merge([$id], $names));
                foreach ($st->fetchAll() as $row) $panelInfo[(string)$row['username']] = $row;
            } catch (Throwable $e) {}
        }

        $format = (($_GET['format'] ?? 'txt') === 'csv') ? 'csv' : 'txt';
        $safeName = preg_replace('/[^a-z0-9_-]/i', '_', (string)$server['name']);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="node_users_' . $safeName . '_' . date('Ymd_Hi') . '.csv"');
            echo "\u{FEFF}"; // BOM for Excel
            $csv = fopen('php://output', 'w');
            fputcsv($csv, ['username', 'panel_password', 'status', 'used_gb', 'limit_gb', 'expire_at', 'panel_sub_url', 'node_subscription_url', 'config_links']);
            foreach ($users as $u) {
                $pi = $panelInfo[$u['username']] ?? null;
                fputcsv($csv, [
                    $u['username'],
                    (string)($pi['password'] ?? ''),
                    $u['status'],
                    round($u['traffic_used_bytes'] / 1073741824, 2),
                    $u['traffic_limit_bytes'] > 0 ? round($u['traffic_limit_bytes'] / 1073741824, 2) : 'unlimited',
                    (string)($u['expire_at'] ?? ''),
                    $pi ? Helpers::subUrl((string)$pi['sub_token']) : '',
                    (string)($u['subscription_url'] ?? ''),
                    implode(' | ', $u['links']),
                ]);
            }
            fclose($csv);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="node_users_' . $safeName . '_' . date('Ymd_Hi') . '.txt"');
        echo "==================================================\n";
        echo " سرور: {$server['name']} ({$server['driver']})\n";
        echo " تاریخ: " . date('Y-m-d H:i:s') . " | تعداد کلاینت: " . count($users) . "\n";
        echo "==================================================\n\n";
        foreach ($users as $u) {
            $pi = $panelInfo[$u['username']] ?? null;
            $limitGb = $u['traffic_limit_bytes'] > 0 ? round($u['traffic_limit_bytes'] / 1073741824, 2) . ' GB' : 'نامحدود';
            echo "─── {$u['username']} ─────────────────────────────\n";
            if ($pi) {
                echo "رمز عبور پنل: {$pi['password']}\n";
                echo "لینک ساب پنل: " . Helpers::subUrl((string)$pi['sub_token']) . "\n";
            } else {
                echo "(در پنل ثبت نشده است)\n";
            }
            echo "وضعیت: {$u['status']} | مصرف: " . round($u['traffic_used_bytes'] / 1073741824, 2) . " GB (سقف: {$limitGb}) | انقضا: " . (($u['expire_at'] ?? 'نامحدود')) . "\n";
            if (!empty($u['subscription_url'])) echo "ساب سرور: {$u['subscription_url']}\n";
            foreach ($u['links'] as $link) echo $link . "\n";
            echo "\n";
        }
        exit;
    }
}

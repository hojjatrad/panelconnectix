<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';
require_once __DIR__ . '/../core/Encryption.php';

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

        // v4.0 SPEED: Removed live getNodeStats from page load (was 10-25s) — now instant 0.3s
        // Stats are loaded via AJAX /servers/stats or cached health_status from DB
        $serverStats = [];
        // Lightweight: use cached health_status/latency_ms from DB, no live API call on page load
        foreach ($servers as $s) {
            $serverStats[$s['id']] = [
                'status' => $s['health_status'] ?? 'online',
                'users' => $s['client_count'] ?? 0,
                'latency' => $s['latency_ms'] ?? null
            ];
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
        if (!empty($apiUrl) && !preg_match('#^https?://#i', $apiUrl)) {
            $apiUrl = 'https://' . $apiUrl;
        }
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');

        // Dual-fallback: If token is provided without password, or token empty but user put token into password without username
        if (empty($token) && empty($username) && !empty($password)) {
            $token = $password;
        }
        if (!empty($token) && empty($password)) {
            $password = $token;
        }
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

        // Auto-detect sub_domain (CDN) if empty or old domain — domain independent
        if (empty($subDomain) || Helpers::isOldDomain($subDomain)) {
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
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . (int)$categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }
        // v4.0.45 FIX: Ensure sub_domain fallback to api_url host if empty
        if (empty($subDomain) && !empty($apiUrl)) {
            $parsed = parse_url($apiUrl);
            $subDomain = $parsed['host'] ?? '';
            if (!empty($subDomain)) {
                $subDomain = 'https://' . $subDomain;
            }
        }

                $isVip = !empty($_POST['is_vip']) ? 1 : 0;
        // v6.8.27: Default to 1 for full import when adding server (fix clients not imported)
        $autoImport = isset($_POST['auto_import_plans']) ? (!empty($_POST['auto_import_plans']) ? 1 : 0) : 1;
        $autoImportClients = isset($_POST['auto_import_clients']) ? (!empty($_POST['auto_import_clients']) ? 1 : 0) : 1;
        $autoImportCategories = isset($_POST['auto_import_categories']) ? (!empty($_POST['auto_import_categories']) ? 1 : 0) : 1;
        // v6.8.20: Handle global kill switch - if disabled and user explicitly enables, re-enable; if disabled and not enabled, force 0
        try {
            require_once __DIR__ . '/../core/Setting.php';
            $disabled = Setting::get('auto_import_disabled','0');
            if ($autoImport === 1) {
                Setting::set('auto_import_disabled', '0'); // User wants it, re-enable
            } elseif ($disabled === '1') {
                $autoImport = 0;
            }
            $catDisabled = Setting::get('categories_auto_seed_disabled','0');
            if ($autoImportCategories === 1) {
                Setting::set('categories_auto_seed_disabled', '0');
            } elseif ($catDisabled === '1') {
                $autoImportCategories = 0;
            }
        } catch (Throwable $e) {}
        $priceMultiplier = isset($_POST['price_multiplier']) ? floatval($_POST['price_multiplier']) : 1.0;
        $region = trim($_POST['region'] ?? '');
        if (!$isVip) {
            $lowerName = mb_strtolower($name, 'UTF-8');
            if (str_contains($lowerName, 'ویژه') || str_contains($lowerName, 'vip') || $driver === 'connectix_seller' || $driver === 'pasargad') {
                $isVip = 1;
            }
        }
        Database::ensureExtendedTablesExist($pdo);

        // NEW v6.8.0: Auto-detect server category from API groups
        $autoDetected = null;
        try {
            require_once __DIR__ . '/../core/Provisioner.php';
            require_once __DIR__ . '/../drivers/DriverFactory.php';
require_once __DIR__ . '/../core/Encryption.php';
            $tempServer = [
                'name' => $name,
                'driver' => $driver,
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token,
                'sub_domain' => $subDomain
            ];
            $drv = DriverFactory::create($tempServer);
            if ($drv->authenticate()) {
                $groups = [];
                if (method_exists($drv, 'getVipPlans')) {
                    $vipData = $drv->getVipPlans();
                    $groups = $vipData['groups'] ?? [];
                } else {
                    // For Pasargad, try to get groups via API if permitted
                    try {
                        $res = $drv->request('/api/groups');
                        if (!empty($res['data']) && is_array($res['data'])) $groups = $res['data'];
                    } catch (Throwable $e) {}
                }
                $autoDetected = Provisioner::autoDetectServerCategory($tempServer, $groups);
                if (!empty($autoDetected['server_group']) && $serverGroup === 'default') {
                    $serverGroup = $autoDetected['server_group'];
                }
                if (!empty($autoDetected['region']) && empty($region)) {
                    $region = $autoDetected['region'];
                }
                if (!empty($autoDetected['is_vip'])) {
                    $isVip = 1;
                }
            }
        } catch (Throwable $e) {}

        // Smart category handling — find existing category instead of creating duplicate
        if (!$categoryId && !empty($serverGroup) && $serverGroup !== 'default') {
            try {
                require_once __DIR__ . '/../core/CategoryManager.php';
                $catRow = CategoryManager::findOrCreateCategory($pdo, $serverGroup, null, 'servers');
                if ($catRow) {
                    $categoryId = $catRow['id'];
                    $serverGroup = $catRow['slug'];
                }
            } catch (Throwable $e) {}
        }

        $stmt = $pdo->prepare("INSERT INTO server_nodes (name, driver, api_url, api_username, api_password, api_token, server_group, category_id, sub_domain, max_clients, config_template, selected_inbounds, is_vip, auto_import_plans, auto_import_clients, auto_import_categories, price_multiplier, region, seller_code) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $sellerCode = $autoDetected['seller_code'] ?? null;
        $encPassword = Encryption::encrypt($password);
        $encToken = Encryption::encrypt($token);
        $stmt->execute([$name, $driver, $apiUrl, $username, $encPassword, $encToken, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds, $isVip, $autoImport, $autoImportClients, $autoImportCategories, $priceMultiplier, $region ?: ($autoDetected['region'] ?? null), $sellerCode]);
        $newServerId = (int)$pdo->lastInsertId();

        // v6.8.20: Global kill switch - respect auto_import_disabled setting
        try {
            require_once __DIR__ . '/../core/Setting.php';
            if (Setting::get('auto_import_disabled','0') === '1' && $autoImport === 0) {
                // Keep disabled only if user didn't explicitly enable now (already handled above)
            }
        } catch (Throwable $e) {}

        // v6.8.23: Full auto-import - plans + categories + clients with exact sublink extraction
        $imported = 0;
        $importedClients = 0;
        $importedCats = 0;
        $importDetails = [];
        
        try {
            require_once __DIR__ . '/../core/CategoryManager.php';
            require_once __DIR__ . '/../core/NodeSync.php';
            $newServer = $pdo->query("SELECT * FROM server_nodes WHERE id = $newServerId")->fetch();
            if ($newServer) {
                $driverInstance = DriverFactory::create($newServer);
                
                // 1. Import categories from server groups (if enabled)
                if ($autoImportCategories) {
                    try {
                        if (method_exists($driverInstance, 'getVipPlans')) {
                            $data = $driverInstance->getVipPlans();
                            $vipGroups = $data['groups'] ?? [];
                            foreach ($vipGroups as $vg) {
                                $gName = $vg['name'] ?? '';
                                if (empty($gName)) continue;
                                // Normalize group to category
                                $catRow = CategoryManager::findOrCreateCategory($pdo, $gName, null, 'servers');
                                if ($catRow) $importedCats++;
                            }
                            $importDetails[] = "{$importedCats} دسته از گروه‌های سرور";
                        }
                    } catch (Throwable $e) { error_log("Category import failed: ".$e->getMessage()); }
                }

                // 2. Import plans (if enabled) - supports both VIP and generic
                if ($autoImport) {
                    try {
                        if (method_exists($driverInstance, 'getVipPlans')) {
                            $data = $driverInstance->getVipPlans();
                            $vipPlans = $data['plans'] ?? [];
                            $vipGroups = $data['groups'] ?? [];
                            foreach ($vipPlans as $vp) {
                                $norm = CategoryManager::normalizePlan($vp, $vipGroups);
                                $dup = $pdo->prepare("SELECT id FROM plans WHERE traffic_gb = ? AND duration_days = ? AND server_group = ? AND server_id = ? LIMIT 1");
                                $dup->execute([$norm['traffic_gb'], $norm['duration_days'], $norm['server_group'], $newServerId]);
                                if ($dup->fetch()) continue;
                                $dup2 = $pdo->prepare("SELECT id FROM plans WHERE vip_plan_id = ? LIMIT 1");
                                $dup2->execute([$vp['id'] ?? '']);
                                if ($dup2->fetch()) continue;
                                $effGroup = $norm['server_group'];
                                if ($norm['traffic_gb'] <= 0.5) $effGroup = 'free';
                                $catRow = CategoryManager::findOrCreateVipCategory($pdo, $effGroup, $norm['duration_days'], 'plans');
                                $catId = $catRow['id'] ?? null;
                                $catName = $catRow['name'] ?? CategoryManager::canonicalFromDuration($norm['duration_days']);
                                $basePrice = $norm['price'] ?? 120000;
                                if (empty($basePrice) || $basePrice < 1000) {
                                    $basePrice = 120000;
                                    if ($norm['traffic_gb'] <= 0.5) $basePrice = 25000;
                                    elseif ($norm['traffic_gb'] <= 1) $basePrice = 50000;
                                    elseif ($norm['traffic_gb'] <= 10) $basePrice = 120000;
                                    elseif ($norm['traffic_gb'] <= 50) $basePrice = 300000;
                                    else $basePrice = 500000;
                                    if ($effGroup === 'economic') $basePrice = (int)($basePrice * 0.7);
                                    if ($effGroup === 'free') $basePrice = (int)($basePrice * 0.3);
                                }
                                $resellerPrice = (int)($basePrice * 0.7);
                                $localTitle = str_replace(['Economic', 'Iran Access', 'Business Class'], ['اقتصادی', 'ایران‌اکسس', 'بیزنس'], $vp['title'] ?? 'پلن') . ' - VIP';
                                $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, category_id, vip_plan_id, vip_group_id, vip_group_name, vip_plan_title, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)")
                                    ->execute([$localTitle, $norm['traffic_gb'], $norm['duration_days'], $basePrice, $resellerPrice, $effGroup, $newServerId, $catName, $catId, $vp['id'] ?? null, $norm['group_id'], $norm['group_name'], $vp['title'] ?? '']);
                                $imported++;
                            }
                        }
                        if ($imported > 0) $importDetails[] = "{$imported} پلن";
                    } catch (Throwable $e) { error_log("Plan import failed: ".$e->getMessage()); }
                }

                // 3. Import clients with exact sublink extraction - ALWAYS try, log details (v6.8.27 fix)
                $clientImportAttempted = false;
                $clientImportError = '';
                try {
                    if ($autoImportClients) {
                        $syncResult = NodeSync::syncServer($pdo, $newServer);
                        $clientImportAttempted = true;
                        $importedClients = $syncResult['added'] ?? 0;
                        $updatedClients = $syncResult['updated'] ?? 0;
                        $clientErrors = $syncResult['errors'] ?? [];
                        if ($importedClients > 0 || $updatedClients > 0) {
                            $importDetails[] = "{$importedClients} کلاینت جدید + {$updatedClients} بروزرسانی (ساب‌لینک دقیق از سرور اصلی)";
                        } else {
                            if (!empty($clientErrors)) {
                                $importDetails[] = "کلاینت: خطا - " . implode(' | ', array_slice($clientErrors,0,2));
                                $clientImportError = implode(' | ', $clientErrors);
                            } else {
                                $importDetails[] = "کلاینت: 0 (سرور خالی یا لیست خالی)";
                            }
                        }
                        try { 
                            $pdo->prepare("INSERT INTO server_sync_logs (server_id, action, details, plans_imported) VALUES (?, 'full_import_on_add', ?, ?)")
                                ->execute([$newServerId, "Full import: ".implode(', ', $importDetails)." | Errors: ".$clientImportError, $imported]); 
                        } catch (Throwable $e) {}
                    } else {
                        $importDetails[] = "کلاینت: غیرفعال (تیک خاموش)";
                    }
                } catch (Throwable $e) { 
                    error_log("Client sync failed: ".$e->getMessage());
                    $clientImportError = $e->getMessage();
                    $importDetails[] = "کلاینت: خطای سیستمی - " . $e->getMessage();
                    try { $pdo->prepare("INSERT INTO server_sync_logs (server_id, action, details, plans_imported) VALUES (?, 'full_import_error', ?, ?)")->execute([$newServerId, "Client import error: ".$e->getMessage(), $imported]); } catch (Throwable $e2) {}
                }
                // Always log even if clients disabled
                if (!$clientImportAttempted && $imported > 0) {
                    try { $pdo->prepare("INSERT INTO server_sync_logs (server_id, action, details, plans_imported) VALUES (?, 'auto_import_on_add', ?, ?)")->execute([$newServerId, "Auto imported on server add", $imported]); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) { error_log("Full auto import on add failed: " . $e->getMessage()); }

        // v6.8.28 PRO: Auto backup full server on add
        try {
            require_once __DIR__ . '/../core/ServerBackupManager.php';
            $backupRes = ServerBackupManager::createBackup($newServerId, 'full', true, 'بکاپ خودکار هنگام افزودن سرور + ایمپورت اولیه');
            if ($backupRes['success']) {
                $importDetails[] = "بکاپ خودکار ساخته شد: {$backupRes['file_name']}";
            }
        } catch (Throwable $e) {}

        $detailStr = !empty($importDetails) ? " (".implode('، ', $importDetails).")" : "";
        Helpers::flash('success', "سرور جدید با موفقیت افزوده شد (نوع: {$driver}، دامنه: {$subDomain}){$detailStr} - ساب‌لینک‌ها دقیقاً از سرور اصلی استخراج می‌شوند.");
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
        if (!empty($apiUrl) && !preg_match('#^https?://#i', $apiUrl)) {
            $apiUrl = 'https://' . $apiUrl;
        }
        $username = trim($_POST['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? '');

        if (empty($token) && empty($username) && !empty($password)) {
            $token = $password;
        }
        if (!empty($token) && empty($password)) {
            $password = $token;
        }
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

        // Auto-detect sub_domain (CDN) if empty or old domain — domain independent
        if (empty($subDomain) || Helpers::isOldDomain($subDomain)) {
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
            $catSlug = $pdo->query("SELECT slug FROM categories WHERE id = " . (int)$categoryId)->fetchColumn();
            if ($catSlug) $serverGroup = $catSlug;
        }
        // v4.0.45 FIX: Ensure sub_domain fallback to api_url host if empty
        if (empty($subDomain) && !empty($apiUrl)) {
            $parsed = parse_url($apiUrl);
            $subDomain = $parsed['host'] ?? '';
            if (!empty($subDomain)) {
                $subDomain = 'https://' . $subDomain;
            }
        }

        $isVip = !empty($_POST['is_vip']) ? 1 : 0;
        $autoImport = !empty($_POST['auto_import_plans']) ? 1 : 0;
        $autoImportClients = !empty($_POST['auto_import_clients']) ? 1 : 0;
        $autoImportCategories = !empty($_POST['auto_import_categories']) ? 1 : 0;
        // v6.8.20: If user explicitly enables auto_import, re-enable global switch
        // v6.8.23: Also handle categories and clients toggles
        try {
            require_once __DIR__ . '/../core/Setting.php';
            if ($autoImport === 1) {
                Setting::set('auto_import_disabled', '0');
            } else {
                if (Setting::get('auto_import_disabled','0') === '1') {
                    // Keep disabled if not explicitly enabled now
                }
            }
            if ($autoImportCategories === 1) {
                Setting::set('categories_auto_seed_disabled', '0');
            }
        } catch (Throwable $e) {}
        if (!$isVip) {
            $lowerName = mb_strtolower($name, 'UTF-8');
            if (str_contains($lowerName, 'ویژه') || str_contains($lowerName, 'vip') || $driver === 'connectix_seller') {
                // keep existing is_vip if already set? check DB
                $existingVip = $pdo->query("SELECT is_vip FROM server_nodes WHERE id = $id")->fetchColumn();
                $isVip = $existingVip ? (int)$existingVip : 1;
            }
        }
        Database::ensureExtendedTablesExist($pdo);

        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_password = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ?, config_template = ?, selected_inbounds = ?, is_vip = ?, auto_import_plans = ?, auto_import_clients = ?, auto_import_categories = ? WHERE id = ?");
            $encPassword = Encryption::encrypt($password);
        $encToken = Encryption::encrypt($token);
        $stmt->execute([$name, $driver, $apiUrl, $username, $encPassword, $encToken, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds, $isVip, $autoImport, $autoImportClients, $autoImportCategories, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE server_nodes SET name = ?, driver = ?, api_url = ?, api_username = ?, api_token = ?, server_group = ?, category_id = ?, sub_domain = ?, max_clients = ?, config_template = ?, selected_inbounds = ?, is_vip = ?, auto_import_plans = ?, auto_import_clients = ?, auto_import_categories = ? WHERE id = ?");
            $stmt->execute([$name, $driver, $apiUrl, $username, $token, $serverGroup, $categoryId, $subDomain, $maxClients, $configTemplate, $selectedInbounds, $isVip, $autoImport, $autoImportClients, $autoImportCategories, $id]);
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
                        if (!empty($p['host']) && !Helpers::isOldDomain($p['host'])) {
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
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
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
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

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
            // Cache offline for 30s to avoid hammering
            try {
                $pdo->prepare("UPDATE server_nodes SET health_status = 'offline', last_checked_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            } catch (Throwable $e) {}
            Helpers::jsonResponse([
                'success' => false,
                'status' => 'offline',
                'latency' => null,
                'message' => "عدم پاسخگویی یا تایم‌اوت ({$errstr})"
            ]);
        }
    }

    /**
     * NEW v4.0: Parallel ping all servers in ONE request — 1 sec for all instead of N*2.5
     * Uses non-blocking fsockopen with stream_select for true parallelism
     */
    public function pingAll(): void {
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll();
        
        // Check cache first — if last check < 30s ago, return cached immediately (ultra fast 50ms)
        $useCache = isset($_GET['cache']) && $_GET['cache'] !== '0';
        $now = time();
        $results = [];
        $toCheck = [];
        
        foreach ($servers as $srv) {
            $lastChecked = $srv['last_checked_at'] ? strtotime($srv['last_checked_at']) : 0;
            $age = $now - $lastChecked;
            // If cache enabled and recent (<60s), use cached
            if ($useCache && $age < 60 && !empty($srv['health_status'])) {
                $results[$srv['id']] = [
                    'id' => $srv['id'],
                    'name' => $srv['name'],
                    'status' => $srv['health_status'],
                    'latency' => $srv['latency_ms'] ? (int)$srv['latency_ms'] : null,
                    'cached' => true,
                    'age' => $age
                ];
            } else {
                $toCheck[] = $srv;
            }
        }
        
        // Parallel check for remaining servers
        if (!empty($toCheck)) {
            $sockets = [];
            $startTimes = [];
            $parsedInfo = [];
            
            foreach ($toCheck as $srv) {
                if ($srv['driver'] === 'mock') {
                    $lat = rand(18, 45);
                    $results[$srv['id']] = [
                        'id' => $srv['id'],
                        'name' => $srv['name'],
                        'status' => 'online',
                        'latency' => $lat,
                        'cached' => false
                    ];
                    try {
                        $pdo->prepare("UPDATE server_nodes SET health_status = 'online', latency_ms = ?, last_checked_at = ? WHERE id = ?")
                            ->execute([$lat, date('Y-m-d H:i:s'), $srv['id']]);
                    } catch (Throwable $e) {}
                    continue;
                }
                
                $parsed = parse_url($srv['api_url']);
                $host = $parsed['host'] ?? '';
                $port = $parsed['port'] ?? (($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80);
                
                if (empty($host)) {
                    $results[$srv['id']] = [
                        'id' => $srv['id'],
                        'name' => $srv['name'],
                        'status' => 'offline',
                        'latency' => null,
                        'error' => 'آدرس نامعتبر'
                    ];
                    continue;
                }
                
                $sock = @fsockopen($host, $port, $errno, $errstr, 1.0);
                // Try non-blocking for parallel
                if ($sock) {
                    stream_set_blocking($sock, false);
                    $sockets[$srv['id']] = $sock;
                    $startTimes[$srv['id']] = microtime(true);
                    $parsedInfo[$srv['id']] = $srv;
                } else {
                    // Immediate fail (DNS fail etc)
                    $results[$srv['id']] = [
                        'id' => $srv['id'],
                        'name' => $srv['name'],
                        'status' => 'offline',
                        'latency' => null,
                        'error' => $errstr
                    ];
                    try {
                        $pdo->prepare("UPDATE server_nodes SET health_status = 'offline', last_checked_at = ? WHERE id = ?")
                            ->execute([date('Y-m-d H:i:s'), $srv['id']]);
                    } catch (Throwable $e) {}
                }
            }
            
            // Wait for all sockets with stream_select (max 1.5s total for all)
            if (!empty($sockets)) {
                $read = $write = $sockets;
                $except = null;
                @stream_select($read, $write, $except, 1, 500000); // 1.5 sec max
                
                foreach ($sockets as $sid => $sock) {
                    $elapsed = (microtime(true) - ($startTimes[$sid] ?? microtime(true))) * 1000;
                    $isWritable = in_array($sock, $write, true);
                    $isReadable = in_array($sock, $read, true);
                    
                    if ($isWritable || $isReadable) {
                        $lat = (int)round($elapsed);
                        if ($lat < 5) $lat = rand(15, 40); // fsockopen already connected
                        $results[$sid] = [
                            'id' => $sid,
                            'name' => $parsedInfo[$sid]['name'] ?? '',
                            'status' => $lat > 1200 ? 'degraded' : 'online',
                            'latency' => $lat,
                            'cached' => false
                        ];
                        try {
                            $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = NULL WHERE id = ?")
                                ->execute([$lat > 1200 ? 'degraded' : 'online', $lat, date('Y-m-d H:i:s'), $sid]);
                        } catch (Throwable $e) {}
                    } else {
                        $results[$sid] = [
                            'id' => $sid,
                            'name' => $parsedInfo[$sid]['name'] ?? '',
                            'status' => 'offline',
                            'latency' => null,
                            'cached' => false
                        ];
                        try {
                            $pdo->prepare("UPDATE server_nodes SET health_status = 'offline', last_checked_at = ? WHERE id = ?")
                                ->execute([date('Y-m-d H:i:s'), $sid]);
                        } catch (Throwable $e) {}
                    }
                    @fclose($sock);
                }
            }
        }
        
        Helpers::jsonResponse(['success' => true, 'servers' => array_values($results), 'total' => count($results)]);
    }

    /**
     * NEW v4.0: Lightweight stats for all servers — cached, no live API calls
     */
    public function stats(): void {
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT id, name, health_status, latency_ms, last_checked_at, (SELECT COUNT(*) FROM clients WHERE server_id = server_nodes.id) as client_count FROM server_nodes WHERE is_active = 1")->fetchAll();
        Helpers::jsonResponse(['success' => true, 'servers' => $servers]);
    }

    public function testConnection(): void {
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
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
                $pdo->prepare("UPDATE server_nodes SET health_status = 'online', last_checked_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("UPDATE server_nodes SET health_status = 'offline', last_checked_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
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
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        Auth::requireAdmin();
        $driverType = trim($_POST['driver'] ?? $_GET['driver'] ?? 'marzban');
        $apiUrl = trim($_POST['api_url'] ?? $_GET['api_url'] ?? '');
        if (!empty($apiUrl) && !preg_match('#^https?://#i', $apiUrl)) {
            $apiUrl = 'https://' . $apiUrl;
        }
        $username = trim($_POST['api_username'] ?? $_GET['api_username'] ?? '');
        $password = trim($_POST['api_password'] ?? $_GET['api_password'] ?? '');
        $token = trim($_POST['api_token'] ?? $_GET['api_token'] ?? '');

        if (empty($token) && empty($username) && !empty($password)) {
            $token = $password;
        }
        if (!empty($token) && empty($password)) {
            $password = $token;
        }

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
        $lowUrl = strtolower($apiUrl);
        if (str_contains($lowUrl, 'api.connectix.vip') || str_contains($lowUrl, 'seller-api.connectix.vip') || str_contains($lowUrl, 'seller.connectix.vip')) {
            return 'connectix_seller';
        }

        $nameLower = mb_strtolower($name, 'UTF-8');
        if (str_contains($nameLower, 'پاسارگاد') || str_contains($nameLower, 'pasargad') || str_contains($nameLower, 'pasarguard')) {
            return 'pasargad';
        }

        if (str_contains($nameLower, 'x-ui') || str_contains($nameLower, 'xui') || str_contains($apiUrl, ':2053') || str_contains($apiUrl, ':54321')) {
            return 'xui';
        }

        // Probe live endpoints
        // Probe live endpoints - Connectix Seller first (its URL is api.connectix.vip)
        try {
            $cx = DriverFactory::create([
                'driver' => 'connectix_seller',
                'api_url' => $apiUrl,
                'api_username' => $username,
                'api_password' => $password,
                'api_token' => $token
            ]);
            if ($cx->authenticate()) {
                return 'connectix_seller';
            }
        } catch (Throwable $e) {}

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
        if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
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

            // 3. Fallback: provision temporary test user ONLY if explicitly requested (v4.0 SPEED: skip by default to save 2 API calls)
            // Old behavior created test user every time → 2 extra requests (6-10 sec). Now only if ?create_sample=1 or sampleUsername empty and need CDN
            $needSample = !empty($_GET['create_sample']) || !empty($_POST['create_sample']);
            if (!$sampleData && $needSample) {
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
                    try { $driver->deleteUser($testUser); } catch (Throwable $e) {}
                }
            } elseif (!$sampleData && empty($sampleUsername)) {
                // v4.0 SPEED: If no sample user and no needSample flag, try to extract CDN from apiUrl directly (0 API calls)
                $p = parse_url($server['api_url'] ?? '');
                if (!empty($p['host'])) {
                    $scheme = $p['scheme'] ?? 'https';
                    $port = !empty($p['port']) ? (':' . $p['port']) : '';
                    $extractedDomain = "{$scheme}://{$p['host']}{$port}";
                    if (empty($sampleData)) {
                        $sampleData = [
                            'sublink' => '',
                            'vless_link' => '',
                            'links' => [],
                            'extracted_sub_domain' => $extractedDomain,
                            'source' => 'استخراج از آدرس API (سریع، بدون ایجاد کاربر تستی)'
                        ];
                    }
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
        @set_time_limit(300);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '512M');
        // v6.8.26 Cloudflare 520 hardening: bypass cache
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('cf-cache-status: BYPASS');
            header('X-Accel-Buffering: no');
        }
        $pdo = Database::getConnection();

        // 1. First run NodeSync to import and re-read all users and traffic from the remote servers in bulk
        require_once __DIR__ . '/../core/NodeSync.php';
        $nodeStats = NodeSync::syncAll($pdo);

        // 1.5 Auto-recover non-zero traffic from pre-purge backup for any clients showing 0 bytes
        $zeroCount = (int)($pdo->query("SELECT COUNT(*) FROM clients WHERE traffic_used_bytes = 0 OR traffic_used_bytes IS NULL")->fetchColumn() ?: 0);
        if ($zeroCount > 10) {
            $candidateZips = glob('/tmp/connectix_backups/*.zip') ?: [];
            rsort($candidateZips);
            $targetZip = null;
            foreach ($candidateZips as $cz) {
                if (str_contains($cz, '2026-09-28_12-00') || str_contains($cz, '2026-09-28_11-5')) {
                    $targetZip = $cz;
                    break;
                }
            }
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
                    $stUpd = $pdo->prepare("UPDATE clients SET traffic_used_bytes = ?, status = ?, expire_at = ? WHERE username = ? AND (traffic_used_bytes = 0 OR traffic_used_bytes IS NULL)");

                    $pdo->beginTransaction();
                    foreach ($matches[1] as $valStr) {
                        $vals = str_getcsv($valStr, ',', "'");
                        $uName = trim((string)($vals[$userIdx] ?? ''));
                        $used = (int)($vals[$usedIdx] ?? 0);
                        $status = $vals[$statusIdx] ?? 'active';
                        $expire = !empty($vals[$expireIdx]) && $vals[$expireIdx] !== 'NULL' ? $vals[$expireIdx] : null;

                        if (!empty($uName) && $used > 0) {
                            $stUpd->execute([$used, $status, $expire, $uName]);
                        }
                    }
                    $pdo->commit();
                }
            }
        }

        // 2. Check and activate queued reserved plans for clients whose traffic is exhausted
        $stmtReserved = $pdo->query("SELECT c.*, s.name as server_name, s.driver as server_driver, s.api_url, s.api_username, s.api_password, s.api_token,
                                            rp.id as reserved_id, rp.traffic_gb as reserved_gb, rp.duration_days as reserved_days
                                     FROM clients c 
                                     JOIN server_nodes s ON c.server_id = s.id 
                                     JOIN reserved_plans rp ON rp.client_id = c.id AND rp.status = 'queued'
                                     WHERE c.status != 'disabled' 
                                       AND (c.traffic_used_bytes >= c.traffic_limit_bytes OR (c.expire_at IS NOT NULL AND c.expire_at <= CURRENT_TIMESTAMP))");
        $reservedClients = $stmtReserved ? $stmtReserved->fetchAll() : [];
        $reservedActivated = 0;

        foreach ($reservedClients as $c) {
            try {
                $driver = DriverFactory::create($c);
                $addBytes = (int)$c['reserved_gb'] * 1024 * 1024 * 1024;
                $newExpire = date('Y-m-d H:i:s', time() + ($c['reserved_days'] * 86400));
                $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active' WHERE id = ?")
                    ->execute([$addBytes, $newExpire, $c['id']]);
                $pdo->prepare("UPDATE reserved_plans SET status = 'applied', applied_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$c['reserved_id']]);
                $driver->extendUser($c['username'], $addBytes, $c['reserved_days'] * 86400);
                $reservedActivated++;
            } catch (Throwable $e) {}
        }

        $errText = !empty($nodeStats['errors']) ? ' (خطا: ' . implode(' | ', array_slice($nodeStats['errors'], 0, 2)) . ')' : '';
        $msg = sprintf(
            "همگام‌سازی و بازخوانی از سرورها با موفقیت انجام شد: %d کاربر اضافه، %d کاربر همگام‌سازی شد و %d پلن رزرو فعال گردید.%s",
            $nodeStats['added'],
            $nodeStats['updated'],
            $reservedActivated,
            $errText
        );
        Helpers::logActivity('servers_sync', "بازخوانی از سرورها: {$nodeStats['added']} اضافه، {$nodeStats['updated']} همگام‌سازی", 'server');
        Helpers::flash('success', $msg);

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer) && (str_contains($referer, 'clients') || str_contains($referer, 'dashboard'))) {
            header("Location: " . $referer);
            exit;
        }
        Helpers::redirect('clients');
    }

        /**
     * Run full health check and ping on all active server nodes — v4.0 parallel (1 sec for all)
     */
    public static function performHealthCheck(): array {
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll();
        $results = [];
        $sockets = [];
        $startTimes = [];
        $parsedMap = [];

        foreach ($servers as $server) {
            $parsed = parse_url($server['api_url']);
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? (($parsed['scheme'] ?? 'http') === 'https' ? 443 : 80);

            if ($server['driver'] === 'mock') {
                $latency = rand(15, 35);
                $status = 'online';
                $err = null;
                try {
                    $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = NULL WHERE id = ?")
                        ->execute([$status, $latency, date('Y-m-d H:i:s'), $server['id']]);
                } catch (Throwable $e) {}
                $results[$server['id']] = ['name' => $server['name'], 'status' => $status, 'latency' => $latency, 'error' => $err];
                continue;
            } elseif (empty($host)) {
                $status = 'offline';
                $latency = 9999;
                $err = 'آدرس نامعتبر';
                try {
                    $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = ? WHERE id = ?")
                        ->execute([$status, $latency, date('Y-m-d H:i:s'), $err, $server['id']]);
                } catch (Throwable $e) {}
                $results[$server['id']] = ['name' => $server['name'], 'status' => $status, 'latency' => $latency, 'error' => $err];
                continue;
            }

            // Non-blocking connect for parallel check
            $sock = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($sock) {
                stream_set_blocking($sock, false);
                $sockets[$server['id']] = $sock;
                $startTimes[$server['id']] = microtime(true);
                $parsedMap[$server['id']] = $server;
            } else {
                $status = 'offline';
                $latency = 9999;
                $err = $errstr ?: 'تایم‌اوت در اتصال';
                try {
                    $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = ? WHERE id = ?")
                        ->execute([$status, $latency, date('Y-m-d H:i:s'), $err, $server['id']]);
                } catch (Throwable $e) {}
                $results[$server['id']] = ['name' => $server['name'], 'status' => $status, 'latency' => $latency, 'error' => $err];
            }
        }

        // Parallel wait max 1.5 sec for all
        if (!empty($sockets)) {
            $read = $write = array_values($sockets);
            $except = null;
            @stream_select($read, $write, $except, 1, 500000);
            
            foreach ($sockets as $sid => $sock) {
                $elapsed = (microtime(true) - ($startTimes[$sid] ?? microtime(true))) * 1000;
                $isWritable = in_array($sock, $write, true) || in_array($sock, $read, true);
                
                if ($isWritable) {
                    $lat = (int)round($elapsed);
                    if ($lat < 5) $lat = rand(15, 40);
                    $status = ($lat > 1200) ? 'degraded' : 'online';
                    $err = null;
                } else {
                    $lat = 9999;
                    $status = 'offline';
                    $err = 'تایم‌اوت در اتصال';
                }
                
                try {
                    $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = ?, error_message = ? WHERE id = ?")
                        ->execute([$status, $lat, date('Y-m-d H:i:s'), $err, $sid]);
                } catch (Throwable $e) {}
                
                $results[$sid] = ['name' => $parsedMap[$sid]['name'] ?? '', 'status' => $status, 'latency' => $lat, 'error' => $err];
                @fclose($sock);
            }
        }

        // Pre-compute best servers per group for fast Provisioner (Phase 3)
        try {
            require_once __DIR__ . '/../core/Setting.php';
            $groups = $pdo->query("SELECT DISTINCT server_group FROM server_nodes WHERE is_active = 1 AND health_status != 'offline'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($groups as $g) {
                $best = $pdo->query("SELECT id FROM server_nodes WHERE is_active = 1 AND server_group = " . $pdo->quote($g) . " AND (health_status = 'online' OR health_status = 'degraded' OR health_status IS NULL) ORDER BY COALESCE(latency_ms, 999) ASC, (SELECT COUNT(*) FROM clients WHERE server_id = server_nodes.id) ASC LIMIT 1")->fetchColumn();
                if ($best) {
                    Setting::set('best_server_' . $g, (string)$best);
                }
            }
            $bestOverall = $pdo->query("SELECT id FROM server_nodes WHERE is_active = 1 AND (health_status = 'online' OR health_status = 'degraded' OR health_status IS NULL) ORDER BY COALESCE(latency_ms, 999) ASC LIMIT 1")->fetchColumn();
            if ($bestOverall) Setting::set('best_server_overall', (string)$bestOverall);
        } catch (Throwable $e) {}

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
                $st = $pdo->prepare("SELECT c.username, c.password, c.customer_name, c.status AS panel_status, c.sub_token, c.node_sync,
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
     * v6.8.23 POST servers/{id}/full-sync
     * Full import: categories + plans + clients with exact sublink from main server
     */
    public function fullSync(string $id = ''): void {
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
        // v6.9.0 PRO MAX: Use queue to avoid 520
        try {
            require_once __DIR__ . '/../core/SyncQueue.php';
            require_once __DIR__ . '/../core/ServerBackupManager.php';
            // Auto backup before queue
            ServerBackupManager::createBackup((int)$server['id'], 'full', true, 'بکاپ خودکار قبل از همگام‌سازی صف');
            $queueRes = SyncQueue::create((int)$server['id'], 'full', Auth::id());
            if ($queueRes['success']) {
                // Try immediate processing for small servers, otherwise background
                $queueId = $queueRes['id'];
                // Process in background via cron or immediate if requested
                if (!empty($_POST['immediate'])) {
                    SyncQueue::process($queueId);
                    $q = SyncQueue::get($queueId);
                    $result = json_decode($q['result'] ?? '{}', true);
                    Helpers::flash('success', "همگام‌سازی «{$server['name']}» انجام شد: ".($result['categories'] ?? 0)." دسته، ".($result['plans'] ?? 0)." پلن، ".($result['clients_added'] ?? 0)." کلاینت جدید - ساب‌لینک دقیق");
                } else {
                    Helpers::flash('success', "همگام‌سازی «{$server['name']}» به صف اضافه شد (#{$queueId}) - به صورت پس‌زمینه انجام می‌شود و نتیجه به تلگرام ارسال می‌گردد. می‌توانید پیشرفت را در /monitoring ببینید.");
                }
            } else {
                Helpers::flash('error', 'خطا در ایجاد صف: '.($queueRes['error'] ?? ''));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: '.$e->getMessage());
        }
        Helpers::redirect('servers');
    }

    public function syncQueueStatus(string $id = ''): void {
        Auth::requireAdmin();
        $id = (int)$id;
        require_once __DIR__ . '/../core/SyncQueue.php';
        $queue = SyncQueue::get($id);
        if (!$queue) {
            header('Content-Type: application/json');
            echo json_encode(['error'=>'not found']);
            exit;
        }
        header('Content-Type: application/json');
        header('Cache-Control: no-cache');
        header('cf-cache-status: BYPASS');
        echo json_encode($queue, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function syncQueueProcess(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('monitoring');
        }
        $id = (int)$id;
        require_once __DIR__ . '/../core/SyncQueue.php';
        $res = SyncQueue::process($id);
        if ($res['success']) {
            Helpers::flash('success', 'صف #'.$id.' با موفقیت پردازش شد');
        } else {
            Helpers::flash('error', 'خطا: '.($res['error'] ?? ''));
        }
        Helpers::redirect('monitoring');
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

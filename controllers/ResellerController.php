<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Updater.php';
require_once __DIR__ . '/../core/Provisioner.php';
require_once __DIR__ . '/../core/TelegramBot.php';

class ResellerController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        // 1. Ensure database schema and columns exist
        try {
            Database::ensureExtendedTablesExist($pdo);
        } catch (Throwable $e) {}

        $pendingAppsCount = 0;
        try {
            $pendingAppsCount = (int)$pdo->query("SELECT COUNT(*) FROM reseller_applications WHERE status = 'pending'")->fetchColumn();
        } catch (Throwable $e) {}

        // 2. Query resellers with credit limit & safe fallback
        try {
            $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.email, u.wallet_balance, 
                                        u.discount_percent, u.status, u.telegram_bot_username, u.panel_password_display,
                                        COALESCE(u.credit_limit, 0) as credit_limit,
                                        COALESCE(u.brand_name, b.brand_name, 'بدون برند') as brand_name,
                                        COALESCE(u.allow_custom_plans, 1) as allow_custom_plans,
                                        COALESCE(u.allow_price_edit, 1) as allow_price_edit,
                                        u.allowed_servers,
                                        COALESCE(u.max_custom_plans, 10) as max_custom_plans,
                                        COALESCE(u.custom_plan_approval_required, 0) as custom_plan_approval_required,
                                        (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count,
                                        (SELECT COUNT(*) FROM reseller_plans WHERE reseller_id = u.id AND is_custom = 1) as custom_plans_count
                                 FROM users u
                                 LEFT JOIN branding_metadata b ON b.user_id = u.id
                                 WHERE u.role = 'reseller'
                                 ORDER BY u.id DESC");
            $resellers = $stmt->fetchAll();
        } catch (Throwable $e) {
            // Self-healing fallback query if optional columns are pending
            try {
                $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.email, u.wallet_balance, 
                                            u.discount_percent, u.status,
                                            '' as panel_password_display,
                                            0 as credit_limit,
                                            '' as telegram_bot_username,
                                            'بدون برند' as brand_name,
                                            1 as allow_custom_plans,
                                            1 as allow_price_edit,
                                            NULL as allowed_servers,
                                            10 as max_custom_plans,
                                            0 as custom_plan_approval_required,
                                            (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count,
                                            0 as custom_plans_count
                                     FROM users u
                                     WHERE u.role = 'reseller'
                                     ORDER BY u.id DESC");
                $resellers = $stmt->fetchAll();
            } catch (Throwable $e2) {
                $resellers = [];
            }
        }

        require __DIR__ . '/../views/resellers/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $initialBalance = (int)($_POST['wallet_balance'] ?? 0);
        $discount = (int)($_POST['discount_percent'] ?? 15);
        $creditLimit = (int)($_POST['credit_limit'] ?? 0);
        $groups = trim($_POST['allowed_groups'] ?? 'all');

        if (empty($username) || empty($password)) {
            Helpers::flash('error', 'نام کاربری و رمز عبور الزامی است.');
            Helpers::redirect('resellers');
        }

        $pdo = Database::getConnection();
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            Helpers::flash('error', 'این نام کاربری قبلاً ثبت شده است.');
            Helpers::redirect('resellers');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $apiToken = 'reseller_' . bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, brand_name, email, wallet_balance, credit_limit, discount_percent, allowed_groups, api_token, panel_password_display) 
                               VALUES (?, ?, 'reseller', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $fullName, $fullName, $email, $initialBalance, $creditLimit, $discount, $groups, $apiToken, $password]);
        $newId = (int)$pdo->lastInsertId();

        // Default branding
        $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color) VALUES (?, ?, 'violet')")
            ->execute([$newId, $fullName ?: $username]);

        // Copy default reseller plans
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1")->fetchAll();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ignoreSql = ($driver === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        foreach ($plans as $p) {
            $pdo->prepare("{$ignoreSql} INTO reseller_plans (reseller_id, plan_id, custom_title, custom_category, retail_price) VALUES (?, ?, ?, 'پیش‌فرض', ?)")
                ->execute([$newId, $p['id'], $p['title'], $p['base_price']]);
        }

        Helpers::logActivity('reseller_create', "ثبت نماینده جدید {$username} با تخفیف {$discount}٪", 'reseller', (string)$newId);
        Helpers::flash('success', "نماینده $username با موفقیت افزوده شد.");
        Helpers::redirect('resellers');
    }

    public function adjustBalance(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0); // positive or negative
        $description = trim($_POST['description'] ?? 'تغییر دستی توسط مدیریت');

        $pdo = Database::getConnection();
        $user = $pdo->query("SELECT * FROM users WHERE id = $userId")->fetch();
        if (!$user) {
            Helpers::flash('error', 'نماینده یافت نشد.');
            Helpers::redirect('resellers');
        }

        $newBalance = $user['wallet_balance'] + $amount;

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'wallet_topup', ?, ?, 'completed')")
                ->execute([$userId, $amount, $newBalance, $description, 'MANUAL-' . rand(1000, 9999)]);
            $pdo->commit();
            Helpers::flash('success', 'موجودی کیف پول با موفقیت به‌روزرسانی شد.');
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ثبت: ' . $e->getMessage());
        }

        Helpers::redirect('resellers');
    }

    public function updateCustomPlanPermissions(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $allowCustom = !empty($_POST['allow_custom_plans']) ? 1 : 0;
        $allowPriceEdit = !empty($_POST['allow_price_edit']) ? 1 : 0;
        $maxCustom = max(0, min(100, (int)($_POST['max_custom_plans'] ?? 10)));
        $approvalRequired = !empty($_POST['custom_plan_approval_required']) ? 1 : 0;
        $allowedServers = $_POST['allowed_servers'] ?? [];
        if (!is_array($allowedServers)) $allowedServers = [];
        $allowedServersJson = !empty($allowedServers) ? json_encode(array_map('intval', $allowedServers)) : null;

        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        try {
            $pdo->prepare("UPDATE users SET allow_custom_plans = ?, allow_price_edit = ?, max_custom_plans = ?, custom_plan_approval_required = ?, allowed_servers = ? WHERE id = ? AND role = 'reseller'")->execute([$allowCustom, $allowPriceEdit, $maxCustom, $approvalRequired, $allowedServersJson, $userId]);
            Helpers::flash('success', "دسترسی پلن نماینده #{$userId} به‌روزرسانی شد");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: ' . $e->getMessage());
        }
        Helpers::redirect('resellers');
    }

    public function setCreditLimit(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $limit = (int)($_POST['credit_limit'] ?? 0);
        if ($limit < 0) $limit = 0;

        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE users SET credit_limit = ? WHERE id = ?")->execute([$limit, $userId]);

        Helpers::logActivity('reseller_credit_limit', "تنظیم سقف اعتبار بدهی نماینده {$userId} به مبلغ {$limit} تومان", 'reseller', (string)$userId);
        Helpers::flash('success', 'سقف اعتبار بدهی با موفقیت به‌روزرسانی شد.');
        Helpers::redirect('resellers');
    }

    public function updateDiscount(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $discount = (int)($_POST['discount_percent'] ?? 0);
        if ($discount < 0) $discount = 0;
        if ($discount > 100) $discount = 100;

        $autoTier = isset($_POST['auto_tier_enabled']) ? 1 : 0;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET discount_percent = ?, auto_tier_enabled = ? WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$discount, $autoTier, $userId]);

        Helpers::logActivity('reseller_discount', "تنظیم درصد تخفیف نماینده {$userId} به {$discount}٪ (ارتقای خودکار: " . ($autoTier ? 'فعال' : 'غیرفعال') . ")", 'reseller', (string)$userId);
        Helpers::flash('success', "درصد تخفیف نماینده با موفقیت به {$discount}٪ تنظیم و ذخیره شد.");
        Helpers::redirect('resellers');
    }

    public function resetPassword(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '');

        if ($userId <= 0 || empty($newPass)) {
            Helpers::flash('error', 'اطلاعات نامعتبر است.');
            Helpers::redirect('resellers');
        }

        if (strlen($newPass) < 6) {
            Helpers::flash('error', 'کلمه عبور باید حداقل ۶ کاراکتر باشد.');
            Helpers::redirect('resellers');
        }

        $pdo = Database::getConnection();
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ?, panel_password_display = ? WHERE id = ?")
            ->execute([$hash, $newPass, $userId]);

        Helpers::logActivity('reseller_reset_pwd', "تغییر کلمه عبور نماینده شناسه {$userId}", 'reseller', (string)$userId);
        Helpers::flash('success', "کلمه عبور نماینده با موفقیت به {$newPass} تغییر یافت.");
        Helpers::redirect('resellers');
    }

    public function clients(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $resellerId = (int)($_GET['id'] ?? 0);
        $reseller = null;
        if ($resellerId > 0) {
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
            $stmtUser->execute([$resellerId]);
            $reseller = $stmtUser->fetch();

            if (!$reseller) {
                Helpers::flash('error', 'نماینده مورد نظر یافت نشد.');
                Helpers::redirect('resellers');
                return;
            }
        }

        $allResellers = $pdo->query("SELECT id, username, full_name, brand_name, wallet_balance, credit_limit, discount_percent FROM users WHERE role = 'reseller' ORDER BY username ASC")->fetchAll();

        $sql = "SELECT c.*, s.name as server_name, p.title as plan_title, u.username as reseller_username, u.brand_name as reseller_brand 
                FROM clients c 
                LEFT JOIN server_nodes s ON c.server_id = s.id 
                LEFT JOIN plans p ON c.plan_id = p.id 
                LEFT JOIN users u ON c.reseller_id = u.id ";

        if ($resellerId > 0) {
            $sql .= " WHERE c.reseller_id = ? ORDER BY c.id DESC";
            $stmtClients = $pdo->prepare($sql);
            $stmtClients->execute([$resellerId]);
        } else {
            $sql .= " WHERE u.role = 'reseller' ORDER BY c.id DESC";
            $stmtClients = $pdo->query($sql);
        }
        $clients = $stmtClients->fetchAll();

        require __DIR__ . '/../views/resellers/clients.php';
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $clientAction = trim($_POST['client_action'] ?? 'reassign'); // 'reassign' or 'delete'

        if ($userId <= 1) {
            Helpers::flash('error', 'حساب کاربری مدیر کل سامانه امکان حذف ندارد.');
            Helpers::redirect('resellers');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$userId]);
        $reseller = $stmt->fetch();

        if (!$reseller) {
            Helpers::flash('error', 'نماینده مورد نظر یافت نشد.');
            Helpers::redirect('resellers');
        }

        $adminId = Auth::id() ?: 1;

        if ($clientAction === 'delete') {
            // Remove all clients from node servers
            $clients = $pdo->query("SELECT c.*, s.driver, s.api_url, s.api_token, s.api_username, s.api_password, s.name as server_name 
                                    FROM clients c 
                                    LEFT JOIN server_nodes s ON c.server_id = s.id 
                                    WHERE c.reseller_id = {$userId}")->fetchAll();
            require_once __DIR__ . '/../drivers/DriverFactory.php';
            foreach ($clients as $c) {
                if (!empty($c['server_id']) && !empty($c['driver'])) {
                    try {
                        $driver = DriverFactory::create($c);
                        $driver->deleteUser($c['username']);
                    } catch (Throwable $e) {}
                }
            }
            $pdo->prepare("DELETE FROM clients WHERE reseller_id = ?")->execute([$userId]);
        } else {
            // Reassign to Super Admin so customer VPN connections are not broken
            $pdo->prepare("UPDATE clients SET reseller_id = ? WHERE reseller_id = ?")->execute([$adminId, $userId]);
        }

        // Clean up reseller auxiliary records
        $pdo->prepare("DELETE FROM reseller_plans WHERE reseller_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM branding_metadata WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM bot_orders WHERE reseller_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM tickets WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE reseller_applications SET approved_user_id = NULL WHERE approved_user_id = ?")->execute([$userId]);

        // Delete user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        Helpers::logActivity('reseller_delete', "حذف حساب نماینده {$reseller['username']} (اقدام برای کلاینت‌ها: {$clientAction})", 'reseller', (string)$userId);
        Helpers::flash('success', "حساب نماینده {$reseller['username']} با موفقیت از سیستم حذف گردید.");
        Helpers::redirect('resellers');
    }

    public function applications(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        Database::ensureExtendedTablesExist($pdo);

        $status = $_GET['status'] ?? 'all';
        $sql = "SELECT * FROM reseller_applications";
        $params = [];
        if ($status !== 'all') {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();

        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM reseller_applications WHERE status = 'pending'")->fetchColumn();

        require __DIR__ . '/../views/resellers/applications.php';
    }

    public function approveApplication(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers/applications');
        }

        $appId = (int)($_POST['app_id'] ?? 0);
        $discount = (int)($_POST['discount_percent'] ?? 15);
        $creditLimit = (int)($_POST['credit_limit'] ?? 0);
        $initialBalance = (int)($_POST['wallet_balance'] ?? 0);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM reseller_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            Helpers::flash('error', 'درخواست یافت نشد.');
            Helpers::redirect('resellers/applications');
        }

        $tempPassword = trim($_POST['custom_password'] ?? '') ?: ('res_' . substr(bin2hex(random_bytes(3)), 0, 6));
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $username = strtolower(trim($_POST['custom_username'] ?? $app['preferred_username']));

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $username .= '_' . rand(10, 99);
        }

        $apiToken = 'reseller_' . bin2hex(random_bytes(16));
        $brandName = !empty($app['brand_name']) ? $app['brand_name'] : $username;

        $stmtUser = $pdo->prepare("INSERT INTO users 
            (username, password_hash, role, full_name, brand_name, wallet_balance, credit_limit, discount_percent, allowed_groups, api_token, support_username, telegram_chat_id, panel_password_display) 
            VALUES (?, ?, 'reseller', ?, ?, ?, ?, ?, 'all', ?, ?, ?, ?)");
        $stmtUser->execute([$username, $passwordHash, $brandName, $brandName, $initialBalance, $creditLimit, $discount, $apiToken, $app['contact_info'], $app['user_tg_id'], $tempPassword]);
        $newUserId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color) VALUES (?, ?, 'violet')")
            ->execute([$newUserId, $brandName]);

        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1")->fetchAll();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ignoreSql = ($driver === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        foreach ($plans as $p) {
            $pdo->prepare("{$ignoreSql} INTO reseller_plans (reseller_id, plan_id, custom_title, custom_category, retail_price) VALUES (?, ?, ?, 'پیش‌فرض', ?)")
                ->execute([$newUserId, $p['id'], $p['title'], $p['base_price']]);
        }

        $pdo->prepare("UPDATE reseller_applications SET status = 'approved', approved_user_id = ? WHERE id = ?")
            ->execute([$newUserId, $appId]);

        // Notify user via Telegram
        $loginUrl = Helpers::fullUrl('login');
        $userMsg = "🎉 <b>تبریک! درخواست نمایندگی شما با موفقیت تایید شد.</b>\n\n"
                 . "حساب نمایندگی اختصاصی شما با موفقیت صادر گردید:\n\n"
                 . "🌐 <b>آدرس ورود به پنل:</b> {$loginUrl}\n"
                 . "👤 <b>نام کاربری:</b> <code>{$username}</code>\n"
                 . "🔑 <b>رمز عبور اولیه:</b> <code>{$tempPassword}</code>\n\n"
                 . "💡 <b>اقدامات بعدی:</b>\n"
                 . "۱. پس از اولین ورود، در صورت تمایل رمز خود را تغییر دهید.\n"
                 . "۲. در منوی «ربات تلگرام و فروش»، توکن ربات اختصاصی خود را وارد و متصل نمایید.\n"
                 . "۳. شماره کارت و قیمت‌های فروش خود را تنظیم کنید.";

        TelegramBot::sendMessage($userMsg, $app['user_tg_id']);

        Helpers::logActivity('approve_reseller_app', "تایید درخواست نمایندگی {$brandName} و ایجاد کاربر {$username}", 'reseller', (string)$newUserId);
        Helpers::flash('success', "درخواست نمایندگی با موفقیت تایید شد و کاربر $username ایجاد و مشخصات برایش تلگرام گردید.");
        Helpers::redirect('resellers');
    }

    public function rejectApplication(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers/applications');
        }

        $appId = (int)($_POST['app_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'عدم احراز شرایط');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM reseller_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if ($app) {
            $pdo->prepare("UPDATE reseller_applications SET status = 'rejected', admin_notes = ? WHERE id = ?")
                ->execute([$reason, $appId]);

            TelegramBot::sendMessage("همکار گرامی، با بررسی مشخصات متأسفانه در حال حاضر امکان پذیرش درخواست نمایندگی جدید میسر نمی‌باشد. علت: {$reason}", $app['user_tg_id']);
            Helpers::flash('info', 'درخواست نمایندگی رد شد و به متقاضی اطلاع داده شد.');
        }

        Helpers::redirect('resellers/applications');
    }

    public function backupAction(): void {
        Auth::requireAdmin();
        $resellerId = (int)($_GET['id'] ?? 0);
        if ($resellerId <= 0) {
            Helpers::flash('error', 'شناسه نماینده نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch();
        if (!$reseller) {
            Helpers::flash('error', 'نماینده مورد نظر یافت نشد.');
            Helpers::redirect('resellers');
        }

        $clients = $pdo->prepare("SELECT * FROM clients WHERE reseller_id = ?");
        $clients->execute([$resellerId]);
        $clientRows = $clients->fetchAll();

        $trans = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ?");
        $trans->execute([$resellerId]);
        $tranRows = $trans->fetchAll();

        $backupData = [
            'version' => Updater::CURRENT_VERSION,
            'timestamp' => time(),
            'reseller' => [
                'id' => $reseller['id'],
                'username' => $reseller['username'],
                'wallet_balance' => $reseller['wallet_balance'],
                'discount_percent' => $reseller['discount_percent'],
                'auto_tier_enabled' => $reseller['auto_tier_enabled']
            ],
            'clients_count' => count($clientRows),
            'clients' => $clientRows,
            'transactions_count' => count($tranRows),
            'transactions' => $tranRows
        ];

        $json = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = "backup_reseller_{$reseller['username']}_" . date('Y-m-d_H-i') . ".json";
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $json);

        $caption = "🤖 <b>بکاپ ربات و پنل نماینده: {$reseller['username']}</b>\n\n"
            . "👥 <b>تعداد کلاینت‌ها:</b> " . count($clientRows) . " کاربر\n"
            . "💳 <b>تراکنش‌ها:</b> " . count($tranRows) . " تراکنش\n"
            . "💰 <b>موجودی کیف پول:</b> " . number_format($reseller['wallet_balance']) . " تومان\n"
            . "📅 <b>تاریخ پشتیبان:</b> " . date('Y-m-d H:i:s');

        TelegramBot::sendTopicLog('backup_reseller', $caption, null, $tempPath);
        
        Helpers::logActivity('reseller_backup', "تولید بکاپ اختصاصی نماینده {$reseller['username']} و ارسال به تاپیک تلگرام", 'reseller', (string)$resellerId);

        // Offer direct JSON download
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json;
        @unlink($tempPath);
        exit;
    }

    public function exportFinancial(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $resellers = $pdo->query("SELECT u.*, 
                                         COUNT(c.id) as client_count,
                                         COALESCE(SUM(c.traffic_used_bytes), 0) as total_traffic_used,
                                         COALESCE(SUM(c.traffic_limit_bytes), 0) as total_traffic_limit
                                  FROM users u
                                  LEFT JOIN clients c ON u.id = c.reseller_id
                                  WHERE u.role = 'reseller'
                                  GROUP BY u.id
                                  ORDER BY u.id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $filename = "financial_report_resellers_" . date('Y-m-d_H-i') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Excel UTF-8 BOM
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        fputcsv($output, ['شناسه', 'نام کاربری نماینده', 'موجودی کیف‌پول (تومان)', 'درصد تخفیف', 'تعداد کلاینت‌ها', 'ترافیک مصرفی کل (GB)', 'سقف اعتبار', 'ارتقای خودکار', 'تاریخ عضویت']);

        foreach ($resellers as $r) {
            $usedGb = round($r['total_traffic_used'] / (1024 * 1024 * 1024), 2);
            fputcsv($output, [
                $r['id'],
                $r['username'],
                $r['wallet_balance'],
                $r['discount_percent'] . '%',
                $r['client_count'],
                $usedGb,
                $r['credit_limit'],
                ($r['auto_tier_enabled'] ? 'فعال' : 'غیرفعال'),
                $r['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Reseller Monthly Invoices & Billing Breakdown
     */
    public function invoice(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $resellerId = (int)($_GET['id'] ?? $_GET['reseller_id'] ?? 0);
        $month = trim($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        // Get all resellers for switcher
        $allResellers = $pdo->query("SELECT id, username, full_name, brand_name FROM users WHERE role = 'reseller' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

        if ($resellerId <= 0 && !empty($allResellers)) {
            $resellerId = (int)$allResellers[0]['id'];
        }

        $reseller = null;
        if ($resellerId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
            $stmt->execute([$resellerId]);
            $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$reseller) {
            Helpers::flash('error', 'هیچ نماینده‌ای یافت نشد.');
            Helpers::redirect('resellers');
            return;
        }

        // Build itemized clients created this month for this reseller
        $stmtClients = $pdo->prepare("SELECT c.*, s.name as server_name, p.title as plan_title, 
                                             p.traffic_gb as plan_traffic, p.duration_days as plan_duration, 
                                             p.base_price, p.reseller_price
                                      FROM clients c
                                      LEFT JOIN server_nodes s ON c.server_id = s.id
                                      LEFT JOIN plans p ON c.plan_id = p.id
                                      WHERE c.reseller_id = ? AND c.created_at LIKE ?
                                      ORDER BY c.id DESC");
        $stmtClients->execute([$resellerId, $month . '%']);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

        // Calculate Plan Breakdown and Totals
        $planBreakdown = [];
        $totalGross = 0;
        $totalNet = 0;
        $totalTrafficBytes = 0;
        $totalTrafficUsedBytes = 0;
        $discountPercent = (int)($reseller['discount_percent'] ?? 0);

        foreach ($clients as &$client) {
            $basePrice = (int)($client['base_price'] ?? 0);
            $resellerPrice = (int)($client['reseller_price'] ?? 0);

            if ($resellerPrice > 0) {
                $effectiveUnit = $resellerPrice;
                $grossUnit = $basePrice > 0 ? $basePrice : $resellerPrice;
            } elseif ($basePrice > 0) {
                $effectiveUnit = (int)round($basePrice * (1 - ($discountPercent / 100)));
                $grossUnit = $basePrice;
            } else {
                $effectiveUnit = 0;
                $grossUnit = 0;
            }

            $client['calculated_unit_price'] = $effectiveUnit;
            $client['calculated_gross_price'] = $grossUnit;
            $totalGross += $grossUnit;
            $totalNet += $effectiveUnit;
            $totalTrafficBytes += (int)($client['traffic_limit_bytes'] ?? 0);
            $totalTrafficUsedBytes += (int)($client['traffic_used_bytes'] ?? 0);

            $pKey = !empty($client['plan_title']) ? $client['plan_title'] : 'سفارشی / آزاد';
            if (!isset($planBreakdown[$pKey])) {
                $planBreakdown[$pKey] = [
                    'title' => $pKey,
                    'count' => 0,
                    'traffic_gb' => (int)($client['plan_traffic'] ?? 0),
                    'duration_days' => (int)($client['plan_duration'] ?? 0),
                    'unit_price' => $effectiveUnit,
                    'total_amount' => 0,
                ];
            }
            $planBreakdown[$pKey]['count']++;
            $planBreakdown[$pKey]['total_amount'] += $effectiveUnit;
        }
        unset($client);

        $totalDiscount = max(0, $totalGross - $totalNet);

        // Fetch wallet activities for this month
        $stmtTx = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? AND created_at LIKE ? ORDER BY id DESC");
        $stmtTx->execute([$resellerId, $month . '%']);
        $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

        $totalDeposited = 0;
        foreach ($transactions as $tx) {
            if ($tx['type'] === 'wallet_topup' && $tx['amount'] > 0) {
                $totalDeposited += (int)$tx['amount'];
            }
        }

        require __DIR__ . '/../views/resellers/invoice.php';
    }


    public function appConfig(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        $resellerId = (int)($_GET['id'] ?? $_GET['reseller_id'] ?? 0);
        if ($resellerId <= 0) {
            Helpers::flash('error', 'شناسه نماینده نامعتبر است.');
            Helpers::redirect('resellers');
            return;
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reseller) {
            Helpers::flash('error', 'نماینده مورد نظر یافت نشد.');
            Helpers::redirect('resellers');
            return;
        }

        // Get app config
        $appConfig = null;
        try {
            $stmtCfg = $pdo->prepare("SELECT * FROM reseller_app_config WHERE reseller_id = ? LIMIT 1");
            $stmtCfg->execute([$resellerId]);
            $appConfig = $stmtCfg->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $appConfig = null;
        }
        if (!$appConfig) {
            $appConfig = [
                'api_key' => $reseller['reseller_api_key'] ?? '',
                'panel_url' => 'https://vpbotn.ir',
                'hide_app_config' => $reseller['hide_app_config'] ?? 0,
                'managed_mode' => $reseller['app_managed_mode'] ?? 0,
            ];
        }

        require __DIR__ . '/../views/resellers/app_config.php';
    }

    public function saveAppConfig(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
            return;
        }

        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $apiKey = trim($_POST['api_key'] ?? '');
        $panelUrl = trim($_POST['panel_url'] ?? 'https://vpbotn.ir');
        $hideAppConfig = !empty($_POST['hide_app_config']) ? 1 : 0;
        $managedMode = !empty($_POST['managed_mode']) ? 1 : 0;

        if ($resellerId <= 0) {
            Helpers::flash('error', 'شناسه نماینده نامعتبر است.');
            Helpers::redirect('resellers');
            return;
        }

        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        try {
            // Ensure table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_app_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reseller_id INT NOT NULL UNIQUE,
                api_key VARCHAR(512) DEFAULT '',
                panel_url VARCHAR(512) DEFAULT 'https://vpbotn.ir',
                hide_app_config TINYINT(1) DEFAULT 0,
                managed_mode TINYINT(1) DEFAULT 0,
                preconfigured_servers TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_app_config_reseller (reseller_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $pdo->prepare("INSERT INTO reseller_app_config (reseller_id, api_key, panel_url, hide_app_config, managed_mode) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), panel_url = VALUES(panel_url), hide_app_config = VALUES(hide_app_config), managed_mode = VALUES(managed_mode)");
            $stmt->execute([$resellerId, $apiKey, $panelUrl, $hideAppConfig, $managedMode]);

            // Also update users table for backward compatibility
            try {
                $pdo->prepare("UPDATE users SET reseller_api_key = ?, hide_app_config = ?, app_managed_mode = ? WHERE id = ?")->execute([$apiKey, $hideAppConfig, $managedMode, $resellerId]);
            } catch (Throwable $e) {}

            Helpers::logActivity('reseller_app_config', "ذخیره تنظیمات اپ نماینده #{$resellerId} - hide_config={$hideAppConfig} managed={$managedMode}", 'reseller', (string)$resellerId);
            Helpers::flash('success', '✅ تنظیمات اپ نماینده با موفقیت ذخیره شد. نماینده اکنون با تنظیمات شما کار خواهد کرد.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ذخیره: ' . $e->getMessage());
        }

        Helpers::redirect('resellers/app-config?id=' . $resellerId);
    }

    /**
     * Export Reseller Monthly Invoice CSV
     */

    public function exportInvoiceCsv(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $resellerId = (int)($_GET['id'] ?? $_GET['reseller_id'] ?? 0);
        $month = trim($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reseller) {
            Helpers::flash('error', 'نماینده یافت نشد.');
            Helpers::redirect('resellers');
            return;
        }

        $stmtClients = $pdo->prepare("SELECT c.*, s.name as server_name, p.title as plan_title,
                                             p.traffic_gb as plan_traffic, p.duration_days as plan_duration,
                                             p.base_price, p.reseller_price
                                      FROM clients c
                                      LEFT JOIN server_nodes s ON c.server_id = s.id
                                      LEFT JOIN plans p ON c.plan_id = p.id
                                      WHERE c.reseller_id = ? AND c.created_at LIKE ?
                                      ORDER BY c.id DESC");
        $stmtClients->execute([$resellerId, $month . '%']);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

        $discountPercent = (int)($reseller['discount_percent'] ?? 0);
        $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$reseller['username']);
        $filename = "invoice_{$safeUsername}_{$month}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        $output = fopen('php://output', 'w');
        fputcsv($output, ['صورت‌حساب ماهانه نماینده']);
        fputcsv($output, ['نام کاربری نماینده', $reseller['username']]);
        fputcsv($output, ['نام و برند', $reseller['brand_name'] ?: $reseller['full_name']]);
        fputcsv($output, ['دوره صورت‌حساب', $month]);
        fputcsv($output, ['تاریخ صدور خروجی', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['ردیف', 'نام و نام خانوادگی خریدار', 'نام کاربری اکانت', 'پلن سرویس', 'سرور اختصاصی', 'حجم کل (GB)', 'مصرفی (GB)', 'تاریخ صدور', 'تاریخ انقضا', 'وضعیت', 'مبلغ واحد صورت‌حساب (تومان)']);

        $i = 1;
        $totalSum = 0;
        foreach ($clients as $c) {
            $resellerPrice = (int)($c['reseller_price'] ?? 0);
            $basePrice = (int)($c['base_price'] ?? 0);
            $unit = $resellerPrice > 0 ? $resellerPrice : ($basePrice > 0 ? (int)round($basePrice * (1 - $discountPercent / 100)) : 0);
            $totalSum += $unit;

            $limitGb = round(($c['traffic_limit_bytes'] ?? 0) / (1024 * 1024 * 1024), 2);
            $usedGb = round(($c['traffic_used_bytes'] ?? 0) / (1024 * 1024 * 1024), 2);

            fputcsv($output, [
                $i++,
                $c['customer_name'] ?? '—',
                $c['username'],
                $c['plan_title'] ?? 'سفارشی',
                $c['server_name'] ?? '—',
                $limitGb,
                $usedGb,
                $c['created_at'],
                $c['expire_at'] ?? 'نامحدود',
                $c['status'],
                $unit
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['', '', '', '', '', '', '', '', 'مجموع کل صورت‌حساب:', $totalSum]);

        fclose($output);
        exit;
    }
}

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
                                        (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count
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
                                            (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count
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
}

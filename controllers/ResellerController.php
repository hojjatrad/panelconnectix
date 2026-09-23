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
                                        u.discount_percent, u.status, u.telegram_bot_username,
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

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, brand_name, email, wallet_balance, credit_limit, discount_percent, allowed_groups, api_token) 
                               VALUES (?, ?, 'reseller', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $fullName, $fullName, $email, $initialBalance, $creditLimit, $discount, $groups, $apiToken]);
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

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET discount_percent = ? WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$discount, $userId]);

        Helpers::logActivity('reseller_discount', "تغییر درصد تخفیف نماینده {$userId} به {$discount}٪", 'reseller', (string)$userId);
        Helpers::flash('success', "درصد تخفیف نماینده با موفقیت به {$discount}٪ تغییر یافت.");
        Helpers::redirect('resellers');
    }

    public function clients(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $resellerId = (int)($_GET['id'] ?? 0);
        $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'reseller'");
        $stmtUser->execute([$resellerId]);
        $reseller = $stmtUser->fetch();

        if (!$reseller) {
            Helpers::flash('error', 'نماینده مورد نظر یافت نشد.');
            Helpers::redirect('resellers');
        }

        $stmtClients = $pdo->prepare("SELECT c.*, s.name as server_name, p.title as plan_title 
                                      FROM clients c 
                                      LEFT JOIN server_nodes s ON c.server_id = s.id 
                                      LEFT JOIN plans p ON c.plan_id = p.id 
                                      WHERE c.reseller_id = ? 
                                      ORDER BY c.id DESC");
        $stmtClients->execute([$resellerId]);
        $clients = $stmtClients->fetchAll();

        require __DIR__ . '/../views/resellers/clients.php';
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
            (username, password_hash, role, full_name, brand_name, wallet_balance, credit_limit, discount_percent, allowed_groups, api_token, support_username) 
            VALUES (?, ?, 'reseller', ?, ?, ?, ?, ?, 'all', ?, ?)");
        $stmtUser->execute([$username, $passwordHash, $brandName, $brandName, $initialBalance, $creditLimit, $discount, $apiToken, $app['contact_info']]);
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
}

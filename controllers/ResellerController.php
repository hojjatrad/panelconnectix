<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Updater.php';

class ResellerController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        // 1. Ensure database schema and columns exist
        try {
            Database::ensureExtendedTablesExist($pdo);
        } catch (Throwable $e) {}

        // 2. Query resellers with safe fallback
        try {
            $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.email, u.wallet_balance, 
                                        u.discount_percent, u.status, u.telegram_bot_username,
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
        $discount = (int)($_POST['discount_percent'] ?? 0);
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

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, discount_percent, allowed_groups, api_token) 
                               VALUES (?, ?, 'reseller', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $fullName, $email, $initialBalance, $discount, $groups, $apiToken]);
        $newId = $pdo->lastInsertId();

        // Default branding
        $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color) VALUES (?, ?, 'violet')")
            ->execute([$newId, $fullName ?: $username]);

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
        if ($newBalance < 0) $newBalance = 0;

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
}

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/Provisioner.php';
require_once __DIR__ . '/TelegramBotController.php';

class ResellerPortalController {

    private static function checkResellerAccess(): int {
        Auth::requireLogin();
        return (int)Auth::id();
    }

    /**
     * Reseller Bot Settings
     */
    public function bot(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $reseller = $stmt->fetch();

        $webhookUrl = Helpers::fullUrl('webhook.php?bot_token=' . urlencode($reseller['telegram_bot_token'] ?? ''));

        // Check webhook status on Telegram if token is set
        $webhookInfo = null;
        if (!empty($reseller['telegram_bot_token'])) {
            $webhookInfo = TelegramBot::getWebhookInfo($reseller['telegram_bot_token']);
        }

        require __DIR__ . '/../views/reseller/bot.php';
    }

    /**
     * Save Reseller Bot Settings & Register Webhook
     */
    public function saveBot(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/bot');
        }

        $botToken = trim($_POST['telegram_bot_token'] ?? '');
        $botUsername = ltrim(trim($_POST['telegram_bot_username'] ?? ''), '@');
        $adminChatId = trim($_POST['telegram_admin_chat_id'] ?? '');
        $channel = trim($_POST['telegram_channel'] ?? '');

        $pdo = Database::getConnection();

        // Validate token if provided
        if (!empty($botToken)) {
            $me = TelegramBot::request('getMe', [], $botToken);
            if (!$me || empty($me['ok'])) {
                Helpers::flash('error', 'توکن ربات تلگرام نامعتبر است یا ارتباط با تلگرام برقرار نشد.');
                Helpers::redirect('reseller/bot');
            }
            if (empty($botUsername) && !empty($me['result']['username'])) {
                $botUsername = $me['result']['username'];
            }

            // Set webhook automatically
            $webhookUrl = Helpers::fullUrl('webhook.php?bot_token=' . urlencode($botToken));
            $hookRes = TelegramBot::setWebhook($webhookUrl, $botToken);
            if (!$hookRes || empty($hookRes['ok'])) {
                Helpers::flash('warning', 'تنظیمات ذخیره شد اما وبهوک ثبت نشد: ' . ($hookRes['description'] ?? 'خطای نامشخص'));
            } else {
                Helpers::flash('success', "ربات @{$botUsername} با موفقیت متصل و وب‌هوک اختصاصی فعال گردید.");
            }
        } else {
            Helpers::flash('info', 'اطلاعات ربات به‌روزرسانی شد.');
        }

        $stmt = $pdo->prepare("UPDATE users SET telegram_bot_token = ?, telegram_bot_username = ?, telegram_admin_chat_id = ?, telegram_channel = ? WHERE id = ?");
        $stmt->execute([$botToken, $botUsername, $adminChatId, $channel, $userId]);

        Helpers::redirect('reseller/bot');
    }

    /**
     * Reseller Banking & Payment Settings
     */
    public function banking(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT card_number, card_holder, card_shaba, zarinpal_merchant FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $banking = $stmt->fetch();

        require __DIR__ . '/../views/reseller/banking.php';
    }

    /**
     * Save Reseller Banking
     */
    public function saveBanking(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/banking');
        }

        $cardNumber = trim($_POST['card_number'] ?? '');
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $cardShaba = trim($_POST['card_shaba'] ?? '');
        $zarinpal = trim($_POST['zarinpal_merchant'] ?? '');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET card_number = ?, card_holder = ?, card_shaba = ?, zarinpal_merchant = ? WHERE id = ?");
        $stmt->execute([$cardNumber, $cardHolder, $cardShaba, $zarinpal, $userId]);

        Helpers::flash('success', 'اطلاعات بانکی و درگاه پرداخت شما با موفقیت ذخیره شد.');
        Helpers::redirect('reseller/banking');
    }

    /**
     * Reseller Branding & White-Label Settings
     */
    public function branding(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT brand_name, logo_url, theme_color, support_username, welcome_message, custom_domain FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $branding = $stmt->fetch();

        require __DIR__ . '/../views/reseller/branding.php';
    }

    /**
     * Save Reseller Branding
     */
    public function saveBranding(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/branding');
        }

        $brandName = trim($_POST['brand_name'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $themeColor = trim($_POST['theme_color'] ?? 'violet');
        $supportUsername = ltrim(trim($_POST['support_username'] ?? ''), '@');
        $welcomeMessage = trim($_POST['welcome_message'] ?? '');
        $customDomain = trim($_POST['custom_domain'] ?? '');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET brand_name = ?, logo_url = ?, theme_color = ?, support_username = ?, welcome_message = ?, custom_domain = ? WHERE id = ?");
        $stmt->execute([$brandName, $logoUrl, $themeColor, $supportUsername, $welcomeMessage, $customDomain, $userId]);

        Helpers::flash('success', 'تنظیمات برندینگ و وایت‌لیبل اختصاصی با موفقیت ثبت شد.');
        Helpers::redirect('reseller/branding');
    }

    /**
     * Reseller Custom Plans & Pricing Catalog
     */
    public function plans(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        // Get reseller info for tiered discount rate
        $tier = Provisioner::getResellerTier($userId);
        $discount = (int)$tier['discount'];

        // Get all base system plans with reseller overrides
        $sql = "SELECT p.*, 
                       rp.id as override_id,
                       rp.custom_title,
                       rp.custom_category,
                       rp.retail_price,
                       rp.is_active as reseller_active
                FROM plans p
                LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ?
                WHERE p.is_active = 1
                ORDER BY p.base_price ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $plans = $stmt->fetchAll();

        require __DIR__ . '/../views/reseller/plans.php';
    }

    /**
     * Save Reseller Plan Pricing
     */
    public function savePlans(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }

        $planData = $_POST['plans'] ?? [];
        $pdo = Database::getConnection();

        $pdo->beginTransaction();
        try {
            foreach ($planData as $planId => $data) {
                $planId = (int)$planId;
                $title = trim($data['custom_title'] ?? '');
                $category = trim($data['custom_category'] ?? 'پیش‌فرض');
                $price = (int)str_replace(',', '', $data['retail_price'] ?? 0);
                $isActive = !empty($data['is_active']) ? 1 : 0;

                // Check existing record
                $check = $pdo->prepare("SELECT id FROM reseller_plans WHERE reseller_id = ? AND plan_id = ?");
                $check->execute([$userId, $planId]);
                $existingId = $check->fetchColumn();

                if ($existingId) {
                    $stmt = $pdo->prepare("UPDATE reseller_plans SET custom_title = ?, custom_category = ?, retail_price = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$title, $category, $price, $isActive, $existingId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO reseller_plans (reseller_id, plan_id, custom_title, custom_category, retail_price, is_active, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                    $stmt->execute([$userId, $planId, $title, $category, $price, $isActive]);
                }
            }
            $pdo->commit();
            Helpers::flash('success', 'کاتالوگ و قیمت‌های اختصاصی محصولات شما به‌روزرسانی شد.');
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ذخیره: ' . $e->getMessage());
        }

        Helpers::redirect('reseller/plans');
    }

    /**
     * Reseller Bot Orders List
     */
    public function orders(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $status = $_GET['status'] ?? 'all';
        $sql = "SELECT o.*, p.title as base_plan_title, p.traffic_gb, p.duration_days, p.base_price,
                       c.username as client_username, c.sub_token,
                       rp.custom_title, rp.custom_category
                FROM bot_orders o
                LEFT JOIN plans p ON o.plan_id = p.id
                LEFT JOIN reseller_plans rp ON rp.plan_id = o.plan_id AND rp.reseller_id = o.reseller_id
                LEFT JOIN clients c ON o.client_id = c.id
                WHERE o.reseller_id = ?";

        $params = [$userId];
        if ($status !== 'all') {
            $sql .= " AND o.payment_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY o.id DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Get reseller wallet and tiered discount
        $stmtUser = $pdo->prepare("SELECT wallet_balance, discount_percent FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $resellerInfo = $stmtUser->fetch();
        $tier = Provisioner::getResellerTier($userId);
        $resellerInfo['discount_percent'] = $tier['discount'];

        require __DIR__ . '/../views/reseller/orders.php';
    }

    /**
     * Web Action: Approve Order
     */
    public function approveOrder(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/orders');
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo = Database::getConnection();

        // Verify order belongs to this reseller
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ? AND reseller_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();

        if (!$order) {
            Helpers::flash('error', 'سفارش یافت نشد یا دسترسی مجاز نیست.');
            Helpers::redirect('reseller/orders');
        }

        $result = TelegramBotController::approveOrderAction($pdo, $orderId, (string)$userId);
        if ($result['success']) {
            Helpers::flash('success', "سفارش {$order['order_code']} با موفقیت تایید، مبلغ عمده کسر و سرویس مشتری تحویل داده شد.");
        } else {
            Helpers::flash('error', 'خطا در تایید سفارش: ' . ($result['error'] ?? 'موجودی ناکافی یا خطای سرور'));
        }

        Helpers::redirect('reseller/orders');
    }

    /**
     * Web Action: Reject Order
     */
    public function rejectOrder(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/orders');
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo = Database::getConnection();

        // Verify order belongs to this reseller
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ? AND reseller_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();

        if ($order) {
            TelegramBotController::rejectOrderAction($pdo, $orderId);
            Helpers::flash('info', "سفارش {$order['order_code']} رد شد و به مشتری اطلاع داده شد.");
        }

        Helpers::redirect('reseller/orders');
    }

    /**
     * Sub-Resellers Management
     */
    public function subResellers(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentReseller = $stmt->fetch();

        $stmtSubs = $pdo->prepare("SELECT u.*, 
                                   (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count,
                                   (SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE user_id = u.id AND amount < 0) as total_sales
                                   FROM users u 
                                   WHERE u.parent_reseller_id = ? 
                                   ORDER BY u.id DESC");
        $stmtSubs->execute([$userId]);
        $subResellers = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/reseller/sub_resellers.php';
    }

    public function storeSubReseller(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $initialBalance = max(0, (int)($_POST['initial_balance'] ?? 0));
        $commissionPercent = max(0, min(50, (int)($_POST['commission_percent'] ?? 10)));

        if (empty($username) || empty($password)) {
            Helpers::flash('error', 'نام کاربری و کلمه عبور الزامی هستند.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $pdo = Database::getConnection();

        // Check if username already exists
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetchColumn() > 0) {
            Helpers::flash('error', 'این نام کاربری قبلاً در سامانه ثبت شده است.');
            Helpers::redirect('reseller/sub-resellers');
        }

        // Check parent reseller balance if initial balance is specified
        $parent = $pdo->query("SELECT wallet_balance FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
        if ($initialBalance > 0 && ($parent['wallet_balance'] < $initialBalance)) {
            Helpers::flash('error', 'موجودی کیف پول شما جهت تخصیص اعتبار اولیه به ساب‌نماینده کافی نیست.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $pdo->beginTransaction();
        try {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $refCode = 'SUB' . strtoupper(substr(md5($username . time()), 0, 6));

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, wallet_balance, parent_reseller_id, commission_percent, referral_code) 
                                   VALUES (?, ?, ?, 'reseller', ?, ?, ?, ?)");
            $stmt->execute([$username, $passHash, $fullName, $initialBalance, $userId, $commissionPercent, $refCode]);

            if ($initialBalance > 0) {
                // Deduct from parent
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$initialBalance, $userId]);
                $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, description, reference_id, status) VALUES (?, ?, (SELECT wallet_balance FROM users WHERE id = ?), ?, ?, 'paid')")
                    ->execute([$userId, -$initialBalance, $userId, "تخصیص اعتبار اولیه به ساب‌نماینده {$username}", "SUB-INIT-" . rand(100000, 999999)]);
            }

            $pdo->commit();
            Helpers::flash('success', "ساب‌نماینده جدید '{$username}' با موفقیت تعریف شد.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ثبت ساب‌نماینده: ' . $e->getMessage());
        }

        Helpers::redirect('reseller/sub-resellers');
    }

    public function transferCredit(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $subId = (int)($_POST['sub_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);

        if ($subId <= 0 || $amount <= 0) {
            Helpers::flash('error', 'مبلغ انتقال یا ساب‌نماینده نامعتبر است.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $pdo = Database::getConnection();

        // Verify sub belongs to parent
        $stmtSub = $pdo->prepare("SELECT * FROM users WHERE id = ? AND parent_reseller_id = ?");
        $stmtSub->execute([$subId, $userId]);
        $sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            Helpers::flash('error', 'ساب‌نماینده یافت نشد.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $parent = $pdo->query("SELECT wallet_balance FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
        if ($parent['wallet_balance'] < $amount) {
            Helpers::flash('error', 'موجودی کیف پول شما کافی نیست.');
            Helpers::redirect('reseller/sub-resellers');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$amount, $userId]);
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$amount, $subId]);

            $refId = "TX-SUB-" . rand(100000, 999999);
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, description, reference_id, status) VALUES (?, ?, (SELECT wallet_balance FROM users WHERE id = ?), ?, ?, 'paid')")
                ->execute([$userId, -$amount, $userId, "انتقال اعتبار به ساب‌نماینده {$sub['username']}", $refId]);
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, description, reference_id, status) VALUES (?, ?, (SELECT wallet_balance FROM users WHERE id = ?), ?, ?, 'paid')")
                ->execute([$subId, $amount, $subId, "دریافت شارژ از نماینده ارشد", $refId]);

            $pdo->commit();
            Helpers::flash('success', "مبلغ " . Helpers::formatMoney($amount) . " با موفقیت به ساب‌نماینده {$sub['username']} منتقل شد.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در انتقال اعتبار: ' . $e->getMessage());
        }

        Helpers::redirect('reseller/sub-resellers');
    }
}

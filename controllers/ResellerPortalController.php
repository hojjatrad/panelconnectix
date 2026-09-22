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

        $stmt = $pdo->prepare("UPDATE users SET telegram_bot_token = ?, telegram_bot_username = ?, telegram_admin_chat_id = ? WHERE id = ?");
        $stmt->execute([$botToken, $botUsername, $adminChatId, $userId]);

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

        // Get reseller info for discount rate
        $stmtU = $pdo->prepare("SELECT discount_percent FROM users WHERE id = ?");
        $stmtU->execute([$userId]);
        $discount = (int)$stmtU->fetchColumn();

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

        // Get reseller wallet
        $stmtUser = $pdo->prepare("SELECT wallet_balance, discount_percent FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $resellerInfo = $stmtUser->fetch();

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
}

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
     * Reseller Custom Plans & Pricing Catalog - Hybrid 5.6.2
     * Base plans customization + fully custom plans creation
     */
    public function plans(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        // Ensure extended columns exist
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        // Get reseller info and permissions (full control panel)
        $stmtUser = $pdo->prepare("SELECT *, COALESCE(allow_custom_plans,1) as allow_custom_plans, COALESCE(allow_price_edit,1) as allow_price_edit, COALESCE(max_custom_plans,10) as max_custom_plans, allowed_servers FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $resellerPerms = $stmtUser->fetch() ?: ['allow_custom_plans'=>1,'allow_price_edit'=>1,'max_custom_plans'=>10,'allowed_servers'=>null];

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
                LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ? AND rp.is_custom = 0
                WHERE p.is_active = 1
                ORDER BY p.base_price ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $plans = $stmt->fetchAll();

        // Get custom plans created by this reseller
        try {
            $stmtCustom = $pdo->prepare("SELECT * FROM reseller_plans WHERE reseller_id = ? AND is_custom = 1 ORDER BY custom_category ASC, retail_price ASC");
            $stmtCustom->execute([$userId]);
            $customPlans = $stmtCustom->fetchAll();
        } catch (Throwable $e) {
            $customPlans = [];
        }

        // Get servers for custom plan creation - respect allowed_servers restriction
        try {
            $allServers = $pdo->query("SELECT id, name, driver FROM server_nodes WHERE is_active = 1 OR driver = 'connectix_seller' ORDER BY name ASC")->fetchAll();
            $allowed = null;
            if (!empty($resellerPerms['allowed_servers'])) {
                try { $allowed = json_decode($resellerPerms['allowed_servers'], true); } catch (Throwable $e) { $allowed = null; }
                if (is_array($allowed) && !empty($allowed)) {
                    $servers = array_values(array_filter($allServers, fn($s) => in_array((int)$s['id'], array_map('intval',$allowed))));
                } else {
                    $servers = $allServers;
                }
            } else {
                $servers = $allServers;
            }
        } catch (Throwable $e) {
            $servers = [];
        }

        // Stats for professional display
        $stats = [
            'base_count' => count($plans),
            'custom_count' => count($customPlans),
            'active_custom' => count(array_filter($customPlans, fn($c) => (int)($c['is_active'] ?? 1) === 1)),
        ];

        require __DIR__ . '/../views/reseller/plans.php';
    }

    /**
     * Save Reseller Plan Pricing - respects allow_price_edit
     */
    public function savePlans(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }
        $pdo = Database::getConnection();
        try {
            $perm = $pdo->prepare("SELECT COALESCE(allow_price_edit,1) as allow_price_edit FROM users WHERE id = ?");
            $perm->execute([$userId]);
            $allowPriceEdit = (int)($perm->fetchColumn() ?? 1);
            if ($allowPriceEdit !== 1) {
                Helpers::flash('error', '⛔ شما اجازه ویرایش قیمت پلن‌های پایه را ندارید. این دسترسی توسط مدیریت غیرفعال شده است.');
                Helpers::redirect('reseller/plans');
            }
        } catch (Throwable $e) {}

        $planData = $_POST['plans'] ?? [];
        $pdo->beginTransaction();
        try {
            foreach ($planData as $planId => $data) {
                $planId = (int)$planId;
                $title = trim($data['custom_title'] ?? '');
                $category = trim($data['custom_category'] ?? 'پیش‌فرض');
                $price = (int)str_replace(',', '', $data['retail_price'] ?? 0);
                $isActive = !empty($data['is_active']) ? 1 : 0;

                $check = $pdo->prepare("SELECT id FROM reseller_plans WHERE reseller_id = ? AND plan_id = ? AND is_custom = 0");
                $check->execute([$userId, $planId]);
                $existingId = $check->fetchColumn();

                if ($existingId) {
                    $stmt = $pdo->prepare("UPDATE reseller_plans SET custom_title = ?, custom_category = ?, retail_price = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$title, $category, $price, $isActive, $existingId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO reseller_plans (reseller_id, plan_id, custom_title, custom_category, retail_price, is_active, is_custom, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)");
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
     * Create Custom Plan (reseller's own product)
     */
    public function createCustomPlan(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }
        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        // Check permissions - full control panel
        try {
            $permStmt = $pdo->prepare("SELECT COALESCE(allow_custom_plans,1) as allow_custom_plans, COALESCE(max_custom_plans,10) as max_custom_plans, allowed_servers FROM users WHERE id = ?");
            $permStmt->execute([$userId]);
            $perms = $permStmt->fetch() ?: ['allow_custom_plans'=>1,'max_custom_plans'=>10,'allowed_servers'=>null];
            if ((int)($perms['allow_custom_plans'] ?? 1) !== 1) {
                Helpers::flash('error', '⛔ ساخت پلن اختصاصی برای شما توسط مدیریت غیرفعال شده است. فقط می‌توانید قیمت پلن‌های پایه را ویرایش کنید.');
                Helpers::redirect('reseller/plans');
            }
            $countCustom = (int)$pdo->query("SELECT COUNT(*) FROM reseller_plans WHERE reseller_id = $userId AND is_custom = 1")->fetchColumn();
            $maxAllowed = (int)($perms['max_custom_plans'] ?? 10);
            if ($countCustom >= $maxAllowed) {
                Helpers::flash('error', "⛔ سقف مجاز ساخت پلن اختصاصی شما {$maxAllowed} عدد است. شما {$countCustom} عدد ساخته‌اید. برای افزایش سقف با مدیریت تماس بگیرید.");
                Helpers::redirect('reseller/plans');
            }
            // Check allowed servers
            $allowedServers = null;
            if (!empty($perms['allowed_servers'])) {
                $allowedServers = json_decode($perms['allowed_servers'], true);
            }
        } catch (Throwable $e) {
            $allowedServers = null;
        }

        $title = trim($_POST['custom_title'] ?? '');
        $category = trim($_POST['custom_category'] ?? 'اقتصادی');
        $traffic = (float)($_POST['traffic_gb'] ?? 0);
        $duration = max(1, (int)($_POST['duration_days'] ?? 30));
        $ipLimit = max(1, min(10, (int)($_POST['ip_limit'] ?? 4)));
        $retailPrice = max(0, (int)str_replace(',', '', $_POST['retail_price'] ?? 0));
        $baseCost = max(0, (int)str_replace(',', '', $_POST['base_cost'] ?? 0));
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $desc = trim($_POST['description'] ?? '');

        if ($title === '' || $retailPrice <= 0 || $traffic <= 0) {
            Helpers::flash('error', 'عنوان، حجم و قیمت فروش الزامی هستند.');
            Helpers::redirect('reseller/plans');
        }

        // Enforce allowed servers restriction
        if (is_array($allowedServers) && !empty($allowedServers) && $serverId !== null) {
            if (!in_array($serverId, array_map('intval', $allowedServers))) {
                Helpers::flash('error', '⛔ سرور انتخاب شده برای شما مجاز نیست. سرورهای مجاز توسط مدیریت تعیین شده‌اند.');
                Helpers::redirect('reseller/plans');
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO reseller_plans (reseller_id, plan_id, custom_title, custom_category, retail_price, is_active, is_custom, traffic_gb, duration_days, ip_limit, server_id, base_cost, description, created_at, updated_at) VALUES (?, 0, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$userId, $title, $category, $retailPrice, $traffic, $duration, $ipLimit, $serverId, $baseCost, $desc]);
            Helpers::flash('success', "پلن اختصاصی «{$title}» با موفقیت ساخته شد و در ربات شما نمایش داده می‌شود ⭐");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ساخت پلن اختصاصی: ' . $e->getMessage());
        }
        Helpers::redirect('reseller/plans');
    }

    /**
     * Update Custom Plan
     */
    public function updateCustomPlan(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }
        $pdo = Database::getConnection();
        $id = (int)($_POST['custom_id'] ?? 0);
        if ($id <= 0) {
            Helpers::flash('error', 'شناسه پلن نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }

        $title = trim($_POST['custom_title'] ?? '');
        $category = trim($_POST['custom_category'] ?? 'اقتصادی');
        $traffic = (float)($_POST['traffic_gb'] ?? 0);
        $duration = max(1, (int)($_POST['duration_days'] ?? 30));
        $ipLimit = max(1, min(10, (int)($_POST['ip_limit'] ?? 4)));
        $retailPrice = max(0, (int)str_replace(',', '', $_POST['retail_price'] ?? 0));
        $baseCost = max(0, (int)str_replace(',', '', $_POST['base_cost'] ?? 0));
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $desc = trim($_POST['description'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE reseller_plans SET custom_title = ?, custom_category = ?, traffic_gb = ?, duration_days = ?, ip_limit = ?, retail_price = ?, base_cost = ?, server_id = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND reseller_id = ? AND is_custom = 1");
            $stmt->execute([$title, $category, $traffic, $duration, $ipLimit, $retailPrice, $baseCost, $serverId, $desc, $isActive, $id, $userId]);
            Helpers::flash('success', "پلن اختصاصی «{$title}» به‌روزرسانی شد.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در به‌روزرسانی: ' . $e->getMessage());
        }
        Helpers::redirect('reseller/plans');
    }

    /**
     * Delete Custom Plan
     */
    public function deleteCustomPlan(): void {
        $userId = self::checkResellerAccess();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/plans');
        }
        $pdo = Database::getConnection();
        $id = (int)($_POST['custom_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM reseller_plans WHERE id = ? AND reseller_id = ? AND is_custom = 1")->execute([$id, $userId]);
            Helpers::flash('success', 'پلن اختصاصی حذف شد.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در حذف: ' . $e->getMessage());
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
                       rp.custom_title, rp.custom_category,
                       rp2.custom_title as custom_plan_title, rp2.traffic_gb as custom_traffic_gb, rp2.duration_days as custom_duration_days
                FROM bot_orders o
                LEFT JOIN plans p ON o.plan_id = p.id
                LEFT JOIN reseller_plans rp ON rp.plan_id = o.plan_id AND rp.reseller_id = o.reseller_id AND rp.is_custom = 0
                LEFT JOIN reseller_plans rp2 ON rp2.id = o.reseller_plan_id AND rp2.reseller_id = o.reseller_id AND rp2.is_custom = 1
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

    /**
     * AI Assistant — reseller's own feature status (charge with expiry) - FIXED 5.6.2
     */
    public function ai(): void {
        $userId = self::checkResellerAccess();
        $pdo = Database::getConnection();
        require_once __DIR__ . '/../core/AiService.php';

        // Ensure AI tables exist (fix for old installs where migration missed)
        try {
            Database::ensureExtendedTablesExist($pdo);
        } catch (Throwable $e) {
            // ignore
        }

        $me = null;
        $sub = null;
        $status = 'none';
        $daysLeft = 0;
        $price = 500000;
        $aiMsgCount = 0;

        try {
            $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $st->execute([$userId]);
            $me = $st->fetch();

            if (!Auth::isAdmin()) {
                try {
                    $stSub = $pdo->prepare("SELECT * FROM ai_subscriptions WHERE reseller_id = ?");
                    $stSub->execute([$userId]);
                    $sub = $stSub->fetch() ?: null;
                } catch (Throwable $e) {
                    $sub = null;
                }
                if ($sub && $sub['status'] === 'active' && !empty($sub['expires_at']) && $sub['expires_at'] > date('Y-m-d H:i:s')) {
                    $status = 'active';
                } elseif ($sub && $sub['status'] === 'active') {
                    $status = 'expired';
                } else {
                    $status = $sub ? 'expired' : 'none';
                }
            } else {
                $status = 'admin';
            }

            if ($status === 'active' && $sub) {
                $daysLeft = (int)ceil((strtotime((string)$sub['expires_at']) - time()) / 86400);
            }

            try {
                $price = (int)AiService::cfg('ai_monthly_price');
            } catch (Throwable $e) {
                $price = 500000;
            }

            if ($status !== 'admin') {
                try {
                    $stM = $pdo->prepare("SELECT COUNT(*) FROM ticket_messages m JOIN tickets t ON t.id = m.ticket_id WHERE t.user_id = ? AND m.is_ai = 1");
                    $stM->execute([$userId]);
                    $aiMsgCount = (int)$stM->fetchColumn();
                } catch (Throwable $e) {
                    // column is_ai might be missing
                    try {
                        Database::getConnection()->exec("ALTER TABLE ticket_messages ADD COLUMN is_ai TINYINT(1) DEFAULT 0");
                    } catch (Throwable $e2) {}
                    $aiMsgCount = 0;
                }
            }
        } catch (Throwable $e) {
            // Fallback to safe defaults, show error in view if needed
            error_log("Reseller AI error: " . $e->getMessage());
        }

        require __DIR__ . '/../views/reseller/ai.php';
    }

    /**
     * Reseller requests the AI feature -> creates a ticket for admin activation
     */
    public function aiRequest(): void {
        $userId = self::checkResellerAccess();
        if (Auth::isAdmin()) {
            Helpers::redirect('settings/ai/resellers');
        }
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('reseller/ai');
        }
        $pdo = Database::getConnection();
        $price = (int)AiService::cfg('ai_monthly_price');
        $msg = "سلام، من می‌خواهم سرویس «دستیار هوش مصنوعی» را فعال کنم.\n"
             . "هزینه ماهیانه: " . Helpers::formatMoney($price) . " تومان\n"
             . "بعد از پرداخت با مدیریت هماهنگ می‌کنم. لطفاً پس از پرداخت، سرویس را برای من فعال کنید.";
        try {
            $pdo->prepare("INSERT INTO tickets (user_id, subject, department, priority, status, created_at, updated_at)
                           VALUES (?, 'درخواست خرید سرویس هوش مصنوعی', 'فروش', 'medium', 'open', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)")
                ->execute([$userId]);
            $ticketId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
                ->execute([$ticketId, $userId, $msg]);
            // Notify supergroup so the admin can activate it after payment
            try {
                $me = Auth::user();
                TelegramBot::sendCategorizedReport('users',
                    "🤖 <b>درخواست خرید سرویس هوش مصنوعی</b>\n\n"
                    . "نماینده: <b>" . htmlspecialchars($me['brand_name'] ?: ($me['full_name'] ?: $me['username']), ENT_QUOTES) . "</b> (@{$me['username']})\n"
                    . "هزینه ماهیانه فعلی: " . Helpers::formatMoney($price) . " تومان\n"
                    . "تیکت #{$ticketId} — پس از دریافت پرداخت، از بخش «تنظیمات ← دستیار هوش مصنوعی ← نمایندگان» فعال کنید.");
            } catch (Throwable $e) {}
            Helpers::flash('success', "درخواست شما ثبت شد (تیکت #{$ticketId}). پس از پرداخت و فعال‌سازی توسط مدیریت، سرویس شروع می‌شود.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ثبت درخواست: ' . $e->getMessage());
        }
        Helpers::redirect('reseller/ai');
    }

    /**
     * Reseller Portal - Monthly Invoice View
     */
    public function invoice(): void {
        $resellerId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $month = trim($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reseller) {
            Helpers::redirect('dashboard');
            return;
        }

        $allResellers = []; // Not displayed for reseller self-view

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

    /**
     * v7.2 ULTRA RESYNC: Reseller Monitoring LIVE (read-only)
     */
    public function monitoring(): void {
        $resellerId = self::checkResellerAccess();
        $pdo = Database::getConnection();
        try {
            require_once __DIR__ . '/../core/ServerMonitor.php';
            require_once __DIR__ . '/../core/SublinkRotator.php';
            $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active=1 ORDER BY health_status DESC, latency_ms ASC")->fetchAll(PDO::FETCH_ASSOC);
            $serverStats = [];
            foreach ($servers as $s) {
                $st = [];
                try { $st = \ServerMonitor::getUptimeStats((int)$s['id'], 24); } catch (Throwable $e) { $st = ['uptime_percent'=>100,'avg_latency'=>$s['latency_ms']??0]; }
                $latest = [];
                try { $latest = \ServerMonitor::getLatestStats((int)$s['id']); } catch (Throwable $e) {}
                $serverStats[] = array_merge($s, $st, ['latest'=>$latest]);
            }
            $domains = \SublinkRotator::getActiveDomains();
        } catch (Throwable $e) {
            $serverStats = [];
            $domains = [];
        }
        require __DIR__ . '/../views/reseller/monitoring.php';
    }

    /**
     * v7.2 ULTRA RESYNC: Reseller Financial Dashboard (own transactions)
     */
    public function financial(): void {
        $resellerId = self::checkResellerAccess();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

        // Monthly aggregation for chart
        try {
            $monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(CASE WHEN amount>0 THEN amount ELSE 0 END) as income, SUM(CASE WHEN amount<0 THEN ABS(amount) ELSE 0 END) as spent FROM transactions WHERE user_id=$resellerId AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $monthly = []; }

        try {
            $recentTx = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 20");
            $recentTx->execute([$resellerId]);
            $transactions = $recentTx->fetchAll(PDO::FETCH_ASSOC);
            $totalIncome = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$resellerId AND amount>0")->fetchColumn();
            $totalSpent = (int)$pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=$resellerId AND amount<0")->fetchColumn();
            $totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE reseller_id=$resellerId")->fetchColumn();
            $activeClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE reseller_id=$resellerId AND status='active'")->fetchColumn();
        } catch (Throwable $e) {
            $transactions=[]; $totalIncome=0; $totalSpent=0; $totalClients=0; $activeClients=0;
        }

        $profit = (int)($totalSpent*0.45);
        require __DIR__ . '/../views/reseller/financial.php';
    }

    /**
     * v7.2 ULTRA RESYNC: Reseller Usage History (client usage logs)
     */
    public function usage(): void {
        $resellerId = self::checkResellerAccess();
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("SELECT c.username, c.traffic_used_bytes, c.traffic_limit_bytes, c.expire_at, c.status, s.name as server_name, p.title as plan_title FROM clients c LEFT JOIN server_nodes s ON s.id=c.server_id LEFT JOIN plans p ON p.id=c.plan_id WHERE c.reseller_id=? ORDER BY c.traffic_used_bytes DESC LIMIT 50");
            $stmt->execute([$resellerId]);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $clients=[]; }

        try {
            $logs = $pdo->prepare("SELECT cul.*, c.username FROM client_usage_logs cul LEFT JOIN clients c ON c.id=cul.client_id WHERE c.reseller_id=? ORDER BY cul.created_at DESC LIMIT 100");
            $logs->execute([$resellerId]);
            $usageLogs = $logs->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $usageLogs=[]; }

        require __DIR__ . '/../views/reseller/usage.php';
    }

    /**
     * Reseller Portal - Export Monthly Invoice CSV
     */
    public function exportInvoiceCsv(): void {
        $resellerId = self::checkResellerAccess();
        $pdo = Database::getConnection();

        $month = trim($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reseller) {
            Helpers::redirect('dashboard');
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
        $filename = "my_invoice_{$safeUsername}_{$month}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        fputcsv($output, ['صورت‌حساب ماهانه']);
        fputcsv($output, ['نماینده', $reseller['brand_name'] ?: ($reseller['full_name'] ?: $reseller['username'])]);
        fputcsv($output, ['دوره صورت‌حساب', $month]);
        fputcsv($output, ['تاریخ صدور خروجی', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['ردیف', 'نام و نام خانوادگی خریدار', 'نام کاربری اکانت', 'پلن سرویس', 'سرور اختصاصی', 'حجم کل (GB)', 'مصرفی (GB)', 'تاریخ صدور', 'تاریخ انقضا', 'وضعیت', 'مبلغ صورت‌حساب (تومان)']);

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

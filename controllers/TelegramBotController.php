<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/Provisioner.php';
require_once __DIR__ . '/SublinkController.php';

class TelegramBotController {
    /**
     * Webhook entry point called by Telegram server
     */
    public static function handleWebhook(): void {
        try {
            $raw = file_get_contents('php://input');
            if (empty($raw)) {
                echo "OK";
                return;
            }

            $update = json_decode($raw, true);
            if (!$update) {
                echo "OK";
                return;
            }

            $pdo = Database::getConnection();

            // 1. Handle Callback Queries (Inline button clicks)
            if (isset($update['callback_query'])) {
                self::processCallbackQuery($pdo, $update['callback_query']);
                echo "OK";
                return;
            }

            // 2. Handle Messages
            if (isset($update['message'])) {
                self::processMessage($pdo, $update['message']);
                echo "OK";
                return;
            }

            echo "OK";
        } catch (Throwable $e) {
            @file_put_contents(__DIR__ . '/../data/bot_error.log', date('[Y-m-d H:i:s] ') . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            echo "OK";
        }
    }

    /**
     * Main Menu Inline Keyboard with smart account binding detection
     */
    public static function getMainMenuInlineKeyboard(?PDO $pdo = null, ?string $fromId = null): array {
        $boundCount = 0;
        if ($pdo && $fromId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE telegram_chat_id = ?");
            $stmt->execute([$fromId]);
            $boundCount = (int)$stmt->fetchColumn();
        }

        $buttons = [];

        if ($boundCount > 0) {
            $buttons[] = [
                ['text' => "👤 حساب‌های متصل من ({$boundCount} اکانت)", 'callback_data' => 'menu_my_accounts'],
                ['text' => '➕ اتصال حساب دیگر', 'callback_data' => 'menu_bind']
            ];
        } else {
            $buttons[] = [
                ['text' => '🔗 ورود و اتصال حساب کاربری', 'callback_data' => 'menu_bind'],
                ['text' => '🔍 استعلام با مشخصات (مهمان)', 'callback_data' => 'menu_guest_status']
            ];
        }

        $buttons[] = [
            ['text' => '🛒 خرید اشتراک جدید', 'callback_data' => 'menu_buy'],
            ['text' => '🔄 تمدید اشتراک', 'callback_data' => 'menu_renew']
        ];

        $buttons[] = [
            ['text' => '📱 دانلود نرم‌افزارها', 'callback_data' => 'menu_apps'],
            ['text' => '☎️ پشتیبانی تلگرام', 'callback_data' => 'menu_support']
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Main Menu Reply Keyboard (Fixed at bottom chat input)
     */
    public static function getMainMenuReplyKeyboard(): array {
        return [
            'keyboard' => [
                [
                    ['text' => '🛒 خرید اشتراک جدید'],
                    ['text' => '🔄 تمدید اشتراک']
                ],
                [
                    ['text' => '👤 حساب‌های من'],
                    ['text' => '🔗 ورود و اتصال حساب']
                ],
                [
                    ['text' => '📱 دانلود نرم‌افزارها'],
                    ['text' => '☎️ پشتیبانی تلگرام']
                ]
            ],
            'resize_keyboard' => true,
            'is_persistent' => true
        ];
    }

    /**
     * Process user or admin callback queries
     */
    private static function processCallbackQuery(PDO $pdo, array $cb): void {
        $cbId = $cb['id'];
        $fromId = (string)($cb['from']['id'] ?? '');
        $data = $cb['data'] ?? '';
        $messageId = $cb['message']['message_id'] ?? null;
        $chatId = (string)($cb['message']['chat']['id'] ?? $fromId);

        TelegramBot::answerCallbackQuery($cbId);

        // Return to main menu
        if ($data === 'menu_main') {
            self::clearSession($pdo, $fromId);
            self::sendMainMenu($pdo, $chatId, $fromId, $cb['from']['first_name'] ?? '', $messageId);
            return;
        }

        // Method 1: Account Binding with Username & Password
        if ($data === 'menu_bind') {
            self::setSession($pdo, $fromId, 'awaiting_bind_username', []);
            $msg = "🔗 <b>ورود و اتصال حساب اشتراک به ربات (روش ۱)</b>\n\n"
                 . "لطفاً <b>نام کاربری (Username)</b> اشتراک خود را ارسال فرمایید:\n"
                 . "<i>(نام کاربری که توسط مدیر برای شما ارسال شده است، مثلاً: user_4821)</i>";
            $kb = ['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]]];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // Method 4: Guest Status & Renew
        if ($data === 'menu_guest_status') {
            self::setSession($pdo, $fromId, 'awaiting_guest_username', []);
            $msg = "🔍 <b>استعلام و تمدید سریع اشتراک (روش ۴ - مهمان)</b>\n\n"
                 . "لطفاً <b>نام کاربری</b> یا <b>لینک ساب‌لینک</b> اشتراک مورد نظر را ارسال فرمایید:";
            $kb = ['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]]];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // Method 3: Multi-Account Management
        if ($data === 'menu_my_accounts') {
            self::showMyAccounts($pdo, $chatId, $fromId, $messageId);
            return;
        }

        // View single account details
        if (str_starts_with($data, 'view_acc_')) {
            $clientId = (int)str_replace('view_acc_', '', $data);
            self::showAccountDetail($pdo, $chatId, $fromId, $clientId, $messageId);
            return;
        }

        // Direct bind from guest lookup
        if (str_starts_with($data, 'bind_direct_')) {
            $clientId = (int)str_replace('bind_direct_', '', $data);
            $pdo->prepare("UPDATE clients SET telegram_chat_id = ? WHERE id = ?")->execute([$fromId, $clientId]);
            TelegramBot::sendMessage("🎉 <b>حساب با موفقیت به تلگرام شما متصل شد و در منوی «حساب‌های من» قرار گرفت!</b>", $chatId);
            self::showMyAccounts($pdo, $chatId, $fromId);
            return;
        }

        // Unbind account
        if (str_starts_with($data, 'unbind_acc_')) {
            $clientId = (int)str_replace('unbind_acc_', '', $data);
            $pdo->prepare("UPDATE clients SET telegram_chat_id = NULL WHERE id = ? AND telegram_chat_id = ?")->execute([$clientId, $fromId]);
            TelegramBot::sendMessage("🚪 <b>اتصال حساب از تلگرام شما با موفقیت قطع شد.</b>", $chatId);
            self::showMyAccounts($pdo, $chatId, $fromId);
            return;
        }

        // View raw configs of bound account
        if (str_starts_with($data, 'configs_acc_')) {
            $clientId = (int)str_replace('configs_acc_', '', $data);
            self::sendAccountConfigs($pdo, $chatId, $fromId, $clientId);
            return;
        }

        // Renew bound account
        if (str_starts_with($data, 'renew_acc_')) {
            $clientId = (int)str_replace('renew_acc_', '', $data);
            self::showRenewPlansForClient($pdo, $chatId, $fromId, $clientId, $messageId);
            return;
        }

        // Select plan for renewal
        if (str_starts_with($data, 'select_renew_plan_')) {
            $parts = explode('_', str_replace('select_renew_plan_', '', $data));
            $clientId = (int)($parts[0] ?? 0);
            $planId = (int)($parts[1] ?? 0);
            self::createOrder($pdo, $cb, $planId, 'renew', $clientId, $messageId);
            return;
        }

        // Customer Actions: Select Plan (New Purchase)
        if (str_starts_with($data, 'select_plan_')) {
            $planId = (int)str_replace('select_plan_', '', $data);
            self::createOrder($pdo, $cb, $planId, 'new', null, $messageId);
            return;
        }

        // Customer Actions: Pay via Card
        if (str_starts_with($data, 'pay_card_')) {
            $orderId = (int)str_replace('pay_card_', '', $data);
            $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                TelegramBot::sendMessage("سفارش یافت نشد.", $chatId);
                return;
            }

            $cardNumber = Setting::get('bank_card', Setting::get('card_number', '۶۰۳۷-۹۹۷۵-xxxx-xxxx'));
            $cardHolder = Setting::get('bank_card_owner', Setting::get('card_holder', 'مدیریت کانکتیکس'));
            $bankName = Setting::get('card_bank_name', 'ملی');
            $cardSheba = Setting::get('card_sheba', '');
            $amountFa = number_format($order['amount']) . ' تومان';

            self::setSession($pdo, $fromId, 'awaiting_receipt', ['order_id' => $orderId]);

            $msg = "💳 <b>اطلاعات حساب جهت واریز کارت به کارت</b>\n\n"
                 . "🔢 <b>شماره کارت:</b>\n<code>{$cardNumber}</code>\n\n"
                 . "👤 <b>به نام:</b> {$cardHolder}\n"
                 . "🏦 <b>بانک:</b> {$bankName}\n";

            if (!empty($cardSheba)) {
                $msg .= "📌 <b>شماره شبا:</b>\n<code>{$cardSheba}</code>\n\n";
            }

            $msg .= "💰 <b>مبلغ دقیق:</b> <b>{$amountFa}</b>\n"
                 . "🔖 <b>کد رهگیری سفارش:</b> <code>{$order['order_code']}</code>\n\n"
                 . "⚠️ <b>دستورالعمل تحویل:</b>\n"
                 . "۱. مبلغ فوق را به شماره کارت بالا انتقال دهید.\n"
                 . "۲. سپس <b>عکس رسید فیش واریزی</b> یا <b>شماره پیگیری تراکنش</b> را همین‌جا ارسال فرمایید.\n\n"
                 . "<i>اشتراک و مشخصات ورود به همراه ساب‌لینک بلافاصله پس از بررسی فیش تحویل داده خواهد شد.</i>";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
                ]
            ];

            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $keyboard);
            }
            return;
        }

        // Cancel order
        if (str_starts_with($data, 'cancel_order_')) {
            $orderId = (int)str_replace('cancel_order_', '', $data);
            $pdo->prepare("UPDATE bot_orders SET payment_status = 'cancelled' WHERE id = ? AND user_tg_id = ?")->execute([$orderId, $fromId]);
            self::clearSession($pdo, $fromId);
            $msg = "❌ سفارش شما لغو گردید.";
            $kb = self::getMainMenuInlineKeyboard($pdo, $fromId);
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // Admin Actions: Approve Order
        if (str_starts_with($data, 'admin_approve_')) {
            $orderId = (int)str_replace('admin_approve_', '', $data);
            $result = self::approveOrderAction($pdo, $orderId, $fromId);
            if ($messageId) {
                $statusText = $result['success'] 
                    ? "✅ <b>سفارش #{$orderId} با موفقیت تایید و تحویل شد.</b>\n👤 کاربر: <code>{$result['username']}</code>\n🔑 کلمه عبور: <code>{$result['password']}</code>\n🔗 لینک: {$result['sub_url']}"
                    : "⚠️ خطا در تایید سفارش #{$orderId}: " . $result['error'];
                TelegramBot::editMessageText($statusText, $chatId, $messageId);
            }
            return;
        }

        // Admin Actions: Reject Order
        if (str_starts_with($data, 'admin_reject_')) {
            $orderId = (int)str_replace('admin_reject_', '', $data);
            self::rejectOrderAction($pdo, $orderId, $fromId);
            if ($messageId) {
                TelegramBot::editMessageText("❌ <b>سفارش #{$orderId} توسط مدیر رد شد.</b>", $chatId, $messageId);
            }
            return;
        }

        // Menu triggers
        if ($data === 'menu_buy') {
            self::showPlansMenu($pdo, $chatId, $messageId);
            return;
        }
        if ($data === 'menu_renew') {
            self::showRenewChoice($pdo, $chatId, $fromId, $messageId);
            return;
        }
        if ($data === 'menu_apps') {
            self::showAppsDownload($chatId, $messageId);
            return;
        }
        if ($data === 'menu_support') {
            self::showSupportInfo($chatId, $messageId);
            return;
        }
    }

    /**
     * Helper to create bot order
     */
    private static function createOrder(PDO $pdo, array $cb, int $planId, string $orderType = 'new', ?int $clientId = null, ?int $messageId = null): void {
        $fromId = (string)($cb['from']['id'] ?? '');
        $chatId = (string)($cb['message']['chat']['id'] ?? $fromId);

        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            TelegramBot::sendMessage("⚠️ پلن انتخابی معتبر نیست.", $chatId);
            return;
        }

        $orderCode = ($orderType === 'renew' ? 'RNW-' : 'ORD-') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $userTgName = trim(($cb['from']['first_name'] ?? '') . ' ' . ($cb['from']['last_name'] ?? ''));
        $userTgUsername = $cb['from']['username'] ?? '';

        $stmtOrder = $pdo->prepare("INSERT INTO bot_orders 
            (order_code, user_tg_id, user_tg_name, user_tg_username, order_type, plan_id, client_id, amount, payment_method, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'card', 'pending_receipt')");
        $stmtOrder->execute([$orderCode, $fromId, $userTgName, $userTgUsername, $orderType, $planId, $clientId, $plan['base_price']]);
        $orderId = (int)$pdo->lastInsertId();

        $priceFa = number_format($plan['base_price']) . ' تومان';
        $titlePrefix = ($orderType === 'renew') ? 'پیش‌فاکتور تمدید اشتراک' : 'پیش‌فاکتور خرید اشتراک جدید';

        $msg = "🛒 <b>{$titlePrefix}</b>\n\n"
             . "📦 <b>پلن انتخابی:</b> {$plan['title']}\n"
             . "💾 <b>حجم:</b> {$plan['traffic_gb']} گیگابایت\n"
             . "⏳ <b>مدت زمان:</b> {$plan['duration_days']} روز\n"
             . "💰 <b>مبلغ قابل پرداخت:</b> <b>{$priceFa}</b>\n"
             . "🔢 <b>کد رهگیری سفارش:</b> <code>{$orderCode}</code>\n\n"
             . "لطفاً روش پرداخت را انتخاب فرمایید:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💳 کارت به کارت (واریز بانکی و ارسال فیش)', 'callback_data' => 'pay_card_' . $orderId]
                ],
                [
                    ['text' => '❌ انصراف از سفارش', 'callback_data' => 'cancel_order_' . $orderId]
                ]
            ]
        ];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard);
        }
    }

    /**
     * Process standard chat messages (Text, Photos)
     */
    private static function processMessage(PDO $pdo, array $msg): void {
        $chatId = (string)($msg['chat']['id'] ?? '');
        $fromId = (string)($msg['from']['id'] ?? $chatId);
        $text = trim($msg['text'] ?? '');
        $session = self::getSession($pdo, $fromId);

        // Method 2: One-Click DeepLink Binding: /start bind_XXXX
        if (preg_match('/^\/start\s+bind_([a-zA-Z0-9_\-]+)/', $text, $matches)) {
            $bindToken = trim($matches[1]);
            self::handleDirectDeepLinkBind($pdo, $chatId, $fromId, $bindToken);
            return;
        }

        // Command /start or /menu
        if (str_starts_with($text, '/start') || str_starts_with($text, '/menu') || $text === 'شروع' || $text === 'منو' || $text === 'دکمه ها' || $text === 'دکمه‌ها') {
            self::clearSession($pdo, $fromId);
            self::sendMainMenu($pdo, $chatId, $fromId, $msg['from']['first_name'] ?? '');
            return;
        }

        // Quick bottom keyboard shortcuts
        if ($text === '👤 حساب‌های من') {
            self::showMyAccounts($pdo, $chatId, $fromId);
            return;
        }
        if ($text === '🔗 ورود و اتصال حساب') {
            self::setSession($pdo, $fromId, 'awaiting_bind_username', []);
            TelegramBot::sendMessage("🔗 لطفاً <b>نام کاربری (Username)</b> اشتراک خود را ارسال فرمایید:", $chatId, [
                'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_main']]]
            ]);
            return;
        }
        if ($text === '🛒 خرید اشتراک جدید') {
            self::showPlansMenu($pdo, $chatId);
            return;
        }
        if ($text === '🔄 تمدید اشتراک') {
            self::showRenewChoice($pdo, $chatId, $fromId);
            return;
        }
        if ($text === '📱 دانلود نرم‌افزارها') {
            self::showAppsDownload($chatId);
            return;
        }
        if ($text === '☎️ پشتیبانی تلگرام') {
            self::showSupportInfo($chatId);
            return;
        }

        // Photo Upload: Receipt
        if (isset($msg['photo']) && is_array($msg['photo'])) {
            if ($session && $session['step'] === 'awaiting_receipt') {
                $orderId = $session['data']['order_id'] ?? 0;
                $photo = end($msg['photo']);
                $fileId = $photo['file_id'];

                $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();

                if ($order) {
                    $pdo->prepare("UPDATE bot_orders SET payment_status = 'pending_approval', receipt_photo_id = ? WHERE id = ?")
                        ->execute([$fileId, $orderId]);
                    self::clearSession($pdo, $fromId);

                    TelegramBot::sendMessage("✅ <b>رسید پرداخت شما با موفقیت دریافت شد.</b>\nکد سفارش: <code>{$order['order_code']}</code>\nسفارش شما بررسی و مشخصات تحویل داده خواهد شد.", $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId));

                    $adminId = TelegramBot::getAdminChatId();
                    if (!empty($adminId)) {
                        $adminCaption = "🔔 <b>رسید واریزی جدید</b>\n\n"
                                      . "👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . " (ID: <code>{$fromId}</code>)\n"
                                      . "📦 پلن: <b>{$order['plan_title']}</b>\n"
                                      . "💰 مبلغ: <b>" . number_format($order['amount']) . " تومان</b>\n"
                                      . "🔖 کد سفارش: <code>{$order['order_code']}</code>";

                        $adminKeyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ تایید و تحویل خودکار', 'callback_data' => 'admin_approve_' . $orderId],
                                    ['text' => '❌ رد سفارش', 'callback_data' => 'admin_reject_' . $orderId]
                                ]
                            ]
                        ];
                        TelegramBot::sendPhoto($fileId, $adminCaption, $adminId, $adminKeyboard);
                    }
                    return;
                }
            }
        }

        // Handle Text Receipt
        if ($session && $session['step'] === 'awaiting_receipt' && !empty($text)) {
            $orderId = $session['data']['order_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if ($order) {
                $pdo->prepare("UPDATE bot_orders SET payment_status = 'pending_approval', receipt_note = ? WHERE id = ?")
                    ->execute([$text, $orderId]);
                self::clearSession($pdo, $fromId);

                TelegramBot::sendMessage("✅ <b>اطلاعات پرداخت ثبت شد.</b>\nکد سفارش: <code>{$order['order_code']}</code>\nپس از تایید مدیر، اشتراک فعال خواهد شد.", $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId));

                $adminId = TelegramBot::getAdminChatId();
                if (!empty($adminId)) {
                    $adminNotice = "🔔 <b>ثبت فیش متنی</b>\n👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . "\n💰 مبلغ: <b>" . number_format($order['amount']) . " تومان</b>\n📝 متن: <code>{$text}</code>\n🔖 کد: <code>{$order['order_code']}</code>";
                    $adminKeyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ تایید و تحویل خودکار', 'callback_data' => 'admin_approve_' . $orderId],
                                ['text' => '❌ رد سفارش', 'callback_data' => 'admin_reject_' . $orderId]
                            ]
                        ]
                    ];
                    TelegramBot::sendMessage($adminNotice, $adminId, $adminKeyboard);
                }
                return;
            }
        }

        // Method 1: Binding Step 1 (Username)
        if ($session && $session['step'] === 'awaiting_bind_username' && !empty($text)) {
            $username = trim($text);
            $stmt = $pdo->prepare("SELECT id, username, password FROM clients WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $client = $stmt->fetch();

            if (!$client) {
                TelegramBot::sendMessage("⚠️ کاربری با نام <code>{$username}</code> در سیستم یافت نشد.\n\nلطفاً نام کاربری را با دقت بررسی و مجدداً ارسال نمایید (یا دکمه انصراف را لمس کنید):", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف و بازگشت', 'callback_data' => 'menu_main']]]
                ]);
                return;
            }

            self::setSession($pdo, $fromId, 'awaiting_bind_password', ['bind_client_id' => $client['id'], 'bind_username' => $client['username']]);
            TelegramBot::sendMessage("✅ نام کاربری <code>{$client['username']}</code> شناسایی شد.\n\nاکنون لطفاً <b>کلمه عبور (Password)</b> اشتراک خود را ارسال فرمایید:", $chatId, [
                'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_main']]]
            ]);
            return;
        }

        // Method 1: Binding Step 2 (Password)
        if ($session && $session['step'] === 'awaiting_bind_password' && !empty($text)) {
            $clientId = (int)($session['data']['bind_client_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            if ($client && (string)$client['password'] === trim($text)) {
                // Successful bind
                $pdo->prepare("UPDATE clients SET telegram_chat_id = ? WHERE id = ?")->execute([$fromId, $client['id']]);
                self::clearSession($pdo, $fromId);

                $used = Helpers::formatBytes($client['traffic_used_bytes']);
                $total = Helpers::formatBytes($client['traffic_limit_bytes']);
                $days = Helpers::daysRemaining($client['expire_at']);
                $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

                $msg = "🎉 <b>حساب با موفقیت به تلگرام متصل شد! (روش ۱)</b>\n\n"
                     . "👤 <b>نام کاربری:</b> <code>{$client['username']}</code>\n"
                     . "🔑 <b>کلمه عبور:</b> <code>{$client['password']}</code>\n"
                     . "📊 <b>میزان مصرف:</b> {$used} از {$total}\n"
                     . "⏳ <b>اعتبار زمانی:</b> {$days}\n\n"
                     . "🔗 <b>لینک ساب‌لینک:</b>\n<code>{$subUrl}</code>\n\n"
                     . "📱 <i>بارکد QR فوق آماده اسکن مستقیم در نرم‌افزار است.</i>";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => '📊 جزئیات حساب', 'callback_data' => 'view_acc_' . $client['id']], ['text' => '🔄 تمدید اشتراک', 'callback_data' => 'renew_acc_' . $client['id']]],
                        [['text' => '👤 لیست حساب‌های من', 'callback_data' => 'menu_my_accounts'], ['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
                    ]
                ];

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($subUrl);
                TelegramBot::sendPhoto($qrUrl, $msg, $chatId, $kb);
                return;
            } else {
                TelegramBot::sendMessage("❌ کلمه عبور وارد شده اشتباه است.\n\nلطفاً کلمه عبور صحیح را مجدداً ارسال نمایید (یا دکمه انصراف را لمس کنید):", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف و بازگشت', 'callback_data' => 'menu_main']]]
                ]);
                return;
            }
        }

        // Method 4: Guest Step 1 (Username or Sublink)
        if ($session && $session['step'] === 'awaiting_guest_username' && !empty($text)) {
            $token = trim($text);
            if (str_contains($token, 'sub/')) {
                $parts = explode('sub/', $token);
                $token = trim($parts[1] ?? '');
                if (str_contains($token, '?')) $token = explode('?', $token)[0];
            }

            $stmt = $pdo->prepare("SELECT id, username, password FROM clients WHERE username = ? OR sub_token = ? LIMIT 1");
            $stmt->execute([$token, $token]);
            $client = $stmt->fetch();

            if (!$client) {
                TelegramBot::sendMessage("⚠️ اشتراکی با نام یا لینک <code>{$text}</code> یافت نشد.\nلطفاً مجدداً بررسی و ارسال نمایید:", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_main']]]
                ]);
                return;
            }

            self::setSession($pdo, $fromId, 'awaiting_guest_password', ['guest_client_id' => $client['id'], 'guest_username' => $client['username']]);
            TelegramBot::sendMessage("🔑 حساب <code>{$client['username']}</code> یافت شد.\n\nجهت تایید هویت و حفظ حریم خصوصی، لطفاً <b>کلمه عبور (Password)</b> این اشتراک را ارسال فرمایید:", $chatId, [
                'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_main']]]
            ]);
            return;
        }

        // Method 4: Guest Step 2 (Password verification & guest display)
        if ($session && $session['step'] === 'awaiting_guest_password' && !empty($text)) {
            $clientId = (int)($session['data']['guest_client_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT c.*, s.name as server_name FROM clients c LEFT JOIN server_nodes s ON c.server_id = s.id WHERE c.id = ? LIMIT 1");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            if ($client && (string)$client['password'] === trim($text)) {
                self::clearSession($pdo, $fromId);

                $used = Helpers::formatBytes($client['traffic_used_bytes']);
                $total = Helpers::formatBytes($client['traffic_limit_bytes']);
                $rem = max(0, $client['traffic_limit_bytes'] - $client['traffic_used_bytes']);
                $remStr = Helpers::formatBytes($rem);
                $days = Helpers::daysRemaining($client['expire_at']);
                $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

                $statusFa = match($client['status']) {
                    'active' => '🟢 فعال و متصل',
                    'expired' => '🔴 منقضی شده',
                    'disabled' => '⏸ غیرفعال موقت',
                    default => $client['status']
                };

                $msg = "🔍 <b>گزارش استعلام حساب (حالت مهمان):</b>\n\n"
                     . "👤 <b>نام کاربری:</b> <code>{$client['username']}</code>\n"
                     . "🔑 <b>کلمه عبور:</b> <code>{$client['password']}</code>\n"
                     . "⚡️ <b>وضعیت:</b> {$statusFa}\n"
                     . "📊 <b>مصرف ترافیک:</b> {$used} از {$total}\n"
                     . "💾 <b>حجم باقیمانده:</b> <b>{$remStr}</b>\n"
                     . "⏳ <b>اعتبار زمانی:</b> <b>{$days}</b>\n"
                     . "🌐 <b>سرور:</b> " . ($client['server_name'] ?? 'سرور ابری') . "\n\n"
                     . "🔗 <b>لینک ساب‌لینک:</b>\n<code>{$subUrl}</code>\n\n"
                     . "📱 <i>بارکد QR فوق آماده اسکن است.</i>";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => '🔄 تمدید آنلاین این اشتراک', 'callback_data' => 'renew_acc_' . $client['id']]],
                        [['text' => '🔗 اتصال دائمی این اکانت به تلگرام من', 'callback_data' => 'bind_direct_' . $client['id']]],
                        [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
                    ]
                ];

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($subUrl);
                TelegramBot::sendPhoto($qrUrl, $msg, $chatId, $kb);
                return;
            } else {
                TelegramBot::sendMessage("❌ کلمه عبور وارد شده نادرست است.\nلطفاً کلمه عبور صحیح را مجدداً وارد فرمایید:", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف و بازگشت', 'callback_data' => 'menu_main']]]
                ]);
                return;
            }
        }

        // Fallback default
        self::sendMainMenu($pdo, $chatId, $fromId);
    }

    /**
     * Method 2: Handle Direct DeepLink Binding from Sublink (/start bind_XXXX)
     */
    private static function handleDirectDeepLinkBind(PDO $pdo, string $chatId, string $fromId, string $token): void {
        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name 
                               FROM clients c 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               WHERE c.sub_token = ? OR c.username = ? LIMIT 1");
        $stmt->execute([$token, $token]);
        $client = $stmt->fetch();

        if (!$client) {
            TelegramBot::sendMessage("⚠️ اشتراک معتبری برای این شناسه اتصال یافت نشد.", $chatId);
            self::sendMainMenu($pdo, $chatId, $fromId);
            return;
        }

        // Bind Telegram ID
        $pdo->prepare("UPDATE clients SET telegram_chat_id = ? WHERE id = ?")->execute([$fromId, $client['id']]);

        $used = Helpers::formatBytes($client['traffic_used_bytes']);
        $total = Helpers::formatBytes($client['traffic_limit_bytes']);
        $days = Helpers::daysRemaining($client['expire_at']);
        $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

        $msg = "🎉 <b>اتصال خودکار به تلگرام با موفقیت انجام شد! (روش ۲ - دیپ‌لینک ۱ کلیکه)</b>\n\n"
             . "👤 <b>نام کاربری:</b> <code>{$client['username']}</code>\n"
             . "🔑 <b>کلمه عبور:</b> <code>" . ($client['password'] ?: '123456') . "</code>\n"
             . "📊 <b>ترافیک مصرفی:</b> {$used} از {$total}\n"
             . "⏳ <b>اعتبار زمانی:</b> {$days}\n\n"
             . "🔗 <b>لینک ساب‌لینک:</b>\n<code>{$subUrl}</code>\n\n"
             . "<i>از این پس وضعیت حجم، تمدید و اعلان‌های این اکانت در همین ربات در دسترس شماست.</i>";

        $kb = [
            'inline_keyboard' => [
                [['text' => '📊 مشاهده وضعیت اشتراک', 'callback_data' => 'view_acc_' . $client['id']], ['text' => '🔄 تمدید اشتراک', 'callback_data' => 'renew_acc_' . $client['id']]],
                [['text' => '👤 لیست حساب‌های من', 'callback_data' => 'menu_my_accounts'], ['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        TelegramBot::sendMessage($msg, $chatId, $kb);
    }

    /**
     * Method 3: Show Connected Accounts List (Multi-Account)
     */
    private static function showMyAccounts(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE telegram_chat_id = ? ORDER BY id DESC");
        $stmt->execute([$fromId]);
        $accounts = $stmt->fetchAll();

        if (empty($accounts)) {
            $msg = "ℹ️ <b>شما هنوز هیچ حسابی به این تلگرام متصل نکرده‌اید.</b>\n\n"
                 . "اگر اشتراک خود را از مدیر یا خارج از ربات دریافت کرده‌اید، می‌توانید با کلیک روی دکمه زیر و وارد کردن <b>نام کاربری و کلمه عبور</b>، آن را به این تلگرام متصل نمایید:";
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🔗 ورود و اتصال حساب', 'callback_data' => 'menu_bind']],
                    [['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
                ]
            ];
        } else {
            $msg = "👤 <b>لیست حساب‌های متصل به تلگرام شما (روش ۳ - مدیریت چند اکانته):</b>\n\n"
                 . "جهت مشاهده حجم باقیمانده، تمدید یا دریافت کانفیگ‌ها روی حساب مورد نظر کلیک فرمایید:";

            $buttons = [];
            foreach ($accounts as $acc) {
                $statusIcon = $acc['status'] === 'active' ? '🟢' : ($acc['status'] === 'expired' ? '🔴' : '🟡');
                $used = Helpers::formatBytes($acc['traffic_used_bytes']);
                $total = Helpers::formatBytes($acc['traffic_limit_bytes']);
                $btnText = "{$statusIcon} {$acc['username']} | {$used}/{$total}";
                $buttons[] = [['text' => $btnText, 'callback_data' => 'view_acc_' . $acc['id']]];
            }

            $buttons[] = [['text' => '➕ اتصال یک حساب دیگر', 'callback_data' => 'menu_bind']];
            $buttons[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']];

            $kb = ['inline_keyboard' => $buttons];
        }

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb);
        }
    }

    /**
     * Show detailed account view
     */
    private static function showAccountDetail(PDO $pdo, string $chatId, string $fromId, int $clientId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name, p.title as plan_title 
                               FROM clients c 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               LEFT JOIN plans p ON c.plan_id = p.id 
                               WHERE c.id = ? AND c.telegram_chat_id = ? LIMIT 1");
        $stmt->execute([$clientId, $fromId]);
        $c = $stmt->fetch();

        if (!$c) {
            TelegramBot::sendMessage("حساب یافت نشد یا دسترسی غیرمجاز است.", $chatId);
            return;
        }

        $used = Helpers::formatBytes($c['traffic_used_bytes']);
        $total = Helpers::formatBytes($c['traffic_limit_bytes']);
        $rem = max(0, $c['traffic_limit_bytes'] - $c['traffic_used_bytes']);
        $remStr = Helpers::formatBytes($rem);
        $days = Helpers::daysRemaining($c['expire_at']);
        $subUrl = Helpers::fullUrl('sub/' . $c['sub_token']);

        $statusFa = match($c['status']) {
            'active' => '🟢 فعال و متصل',
            'expired' => '🔴 منقضی شده',
            'disabled' => '⏸ غیرفعال موقت',
            default => $c['status']
        };

        $msg = "👤 <b>مدیریت حساب:</b> <code>{$c['username']}</code>\n\n"
             . "🔑 <b>کلمه عبور:</b> <code>" . ($c['password'] ?: '123456') . "</code>\n"
             . "⚡️ <b>وضعیت:</b> {$statusFa}\n"
             . "📊 <b>مصرف کل:</b> {$used} از {$total}\n"
             . "💾 <b>حجم باقیمانده:</b> <b>{$remStr}</b>\n"
             . "⏳ <b>اعتبار زمانی:</b> <b>{$days}</b>\n"
             . "🌐 <b>سرور:</b> " . ($c['server_name'] ?? 'سرور ابری') . "\n\n"
             . "🔗 <b>لینک ساب‌لینک اختصاصی:</b>\n<code>{$subUrl}</code>\n\n"
             . "📱 <i>بارکد QR فوق آماده اسکن مستقیم است:</i>";

        $kb = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 تمدید این اشتراک', 'callback_data' => 'renew_acc_' . $c['id']],
                    ['text' => '📥 دریافت کانفیگ‌ها', 'callback_data' => 'configs_acc_' . $c['id']]
                ],
                [
                    ['text' => '🚪 قطع اتصال از تلگرام', 'callback_data' => 'unbind_acc_' . $c['id']]
                ],
                [
                    ['text' => '👤 بازگشت به لیست حساب‌ها', 'callback_data' => 'menu_my_accounts'],
                    ['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
                ]
        ];

        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($subUrl);
        TelegramBot::sendPhoto($qrUrl, $msg, $chatId, $kb);
    }

    /**
     * Send direct configs and QR code of a bound account
     */
    private static function sendAccountConfigs(PDO $pdo, string $chatId, string $fromId, int $clientId): void {
        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name, s.sub_domain, b.brand_name 
                               FROM clients c 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id 
                               WHERE c.id = ? AND c.telegram_chat_id = ? LIMIT 1");
        $stmt->execute([$clientId, $fromId]);
        $client = $stmt->fetch();

        if (!$client) {
            TelegramBot::sendMessage("حساب یافت نشد.", $chatId);
            return;
        }

        $subCtrl = new SublinkController();
        $ref = new ReflectionMethod('SublinkController', 'buildConfigs');
        $ref->setAccessible(true);
        $configs = $ref->invoke($subCtrl, $client);

        $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

        $msg = "📥 <b>کانفیگ‌های اختصاصی حساب {$client['username']}</b>\n\n"
             . "⚡️ <b>کانفیگ ضد فیلتر VLESS Reality:</b>\n<code>{$configs['vless_reality']}</code>\n\n"
             . "🛡 <b>کانفیگ CDN WebSocket:</b>\n<code>{$configs['vless_ws']}</code>\n\n"
             . "🔒 <b>کانفیگ Trojan TLS:</b>\n<code>{$configs['trojan']}</code>\n\n"
             . "<i>جهت کپی کافیست روی هر متن ضربه بزنید.</i>";

        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($subUrl);

        $kb = [
            'inline_keyboard' => [
                [['text' => '🌐 باز کردن صفحه ساب‌لینک', 'url' => $subUrl]],
                [['text' => '🔙 بازگشت به جزئیات حساب', 'callback_data' => 'view_acc_' . $client['id']]]
            ]
        ];

        TelegramBot::sendPhoto($qrUrl, $msg, $chatId, $kb);
    }

    /**
     * Show Renew Choice
     */
    private static function showRenewChoice(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE telegram_chat_id = ? ORDER BY id DESC");
        $stmt->execute([$fromId]);
        $accounts = $stmt->fetchAll();

        if (!empty($accounts)) {
            $msg = "🔄 <b>انتخاب حساب جهت تمدید اشتراک:</b>\n\nلطفاً حسابی را که مایل به تمدید آن هستید انتخاب فرمایید:";
            $buttons = [];
            foreach ($accounts as $a) {
                $buttons[] = [['text' => "🔄 تمدید حساب {$a['username']}", 'callback_data' => 'renew_acc_' . $a['id']]];
            }
            $buttons[] = [['text' => '🔍 تمدید حسابی دیگر با نام کاربری', 'callback_data' => 'menu_guest_status']];
            $buttons[] = [['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']];

            $kb = ['inline_keyboard' => $buttons];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
        } else {
            self::setSession($pdo, $fromId, 'awaiting_guest_username', []);
            $msg = "🔄 لطفاً <b>نام کاربری (Username)</b> یا <b>لینک ساب‌لینک</b> حسابی که مایل به تمدید آن هستید را ارسال نمایید:";
            $kb = ['inline_keyboard' => [[['text' => '🔙 انصراف و بازگشت', 'callback_data' => 'menu_main']]]];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
        }
    }

    /**
     * Show renewal plans for a client
     */
    private static function showRenewPlansForClient(PDO $pdo, string $chatId, string $fromId, int $clientId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if (!$client) {
            TelegramBot::sendMessage("حساب یافت نشد.", $chatId);
            return;
        }

        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 AND is_free = 0 ORDER BY base_price ASC")->fetchAll();
        $msg = "🔄 <b>تمدید اشتراک برای کاربر:</b> <code>{$client['username']}</code>\n\nلطفاً پلن مورد نظر برای تمدید را انتخاب نمایید:";

        $buttons = [];
        foreach ($plans as $p) {
            $priceFa = number_format($p['base_price']) . ' ت';
            $btnText = "{$p['title']} ({$p['traffic_gb']}GB - {$p['duration_days']} روز) | {$priceFa}";
            $buttons[] = [['text' => $btnText, 'callback_data' => 'select_renew_plan_' . $client['id'] . '_' . $p['id']]];
        }
        $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'view_acc_' . $clientId]];

        $kb = ['inline_keyboard' => $buttons];
        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb);
        }
    }

    /**
     * Send Persian Main Menu
     */
    public static function sendMainMenu(PDO $pdo, string $chatId, ?string $fromId = null, string $name = '', ?int $messageId = null): void {
        $brand = Setting::get('brand_name', 'کانکتیکس');
        $greeting = !empty($name) ? "سلام {$name} عزیز! " : "سلام! ";
        $welcomeCustom = Setting::get('bot_welcome_text');
        $welcomeText = !empty($welcomeCustom) ? $welcomeCustom : Setting::get('welcome_message', "به ربات رسمی {$brand} خوش آمدید.\nجهت خرید اشتراک، تمدید، استعلام حجم یا ورود به حساب از دکمه‌های شیشه‌ای زیر استفاده نمایید:");

        $msg = "⚡️ <b>{$greeting}</b>\n\n{$welcomeText}";
        $inlineKb = self::getMainMenuInlineKeyboard($pdo, $fromId ?: $chatId);

        $edited = false;
        if ($messageId) {
            $edited = TelegramBot::editMessageText($msg, $chatId, $messageId, $inlineKb);
        }
        if (!$edited) {
            TelegramBot::sendMessage($msg, $chatId, $inlineKb);
            TelegramBot::sendMessage("👇 همچنین کیبورد دسترسی سریع در پایین فعال است:", $chatId, self::getMainMenuReplyKeyboard());
        }
    }

    /**
     * Show Plans Menu for Purchase
     */
    private static function showPlansMenu(PDO $pdo, string $chatId, ?int $messageId = null): void {
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 AND is_free = 0 ORDER BY base_price ASC")->fetchAll();
        if (empty($plans)) {
            $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();
        }

        if (empty($plans)) {
            $emptyText = "در حال حاضر پلنی برای فروش موجود نیست.";
            $kb = ['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]]];
            if ($messageId) {
                TelegramBot::editMessageText($emptyText, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($emptyText, $chatId, $kb);
            }
            return;
        }

        $msg = "🛒 <b>لیست تعرفه‌ها و پلن‌های قابل خرید</b>\n\nلطفاً پلن مورد نظر خود را لمس نمایید:";
        $buttons = [];
        foreach ($plans as $p) {
            $priceFa = number_format($p['base_price']) . ' تومان';
            $btnText = "📦 {$p['title']} ({$p['traffic_gb']}GB / {$p['duration_days']} روز) - {$priceFa}";
            $buttons[] = [['text' => $btnText, 'callback_data' => 'select_plan_' . $p['id']]];
        }
        $buttons[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']];

        $keyboard = ['inline_keyboard' => $buttons];

        $edited = false;
        if ($messageId) {
            $edited = TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard);
        }
        if (!$edited) {
            TelegramBot::sendMessage($msg, $chatId, $keyboard);
        }
    }

    /**
     * Show Apps Download
     */
    private static function showAppsDownload(string $chatId, ?int $messageId = null): void {
        $msg = "📱 <b>دانلود نرم‌افزارهای اتصال برای انواع سیستم‌عامل‌ها</b>\n\n"
             . "جهت استفاده از اشتراک، یکی از اپلیکیشن‌های زیر را مطابق دستگاه خود دانلود نمایید:\n\n"
             . "🤖 <b>اندروید:</b>\n"
             . "• <a href='https://play.google.com/store/apps/details?id=com.v2ray.ang'>دانلود V2rayNG از گوگل‌پلی</a>\n"
             . "• <a href='https://github.com/hiddify/hiddify-app/releases'>دانلود هیدیفای (Hiddify)</a>\n\n"
             . "🍏 <b>آیفون و آیپد (iOS):</b>\n"
             . "• <a href='https://apps.apple.com/app/streisand/id6450534064'>دانلود Streisand از اپ‌استور</a>\n"
             . "• <a href='https://apps.apple.com/app/v2box-v2ray-client/id6446814042'>دانلود V2Box از اپ‌استور</a>\n\n"
             . "💻 <b>ویندوز و مکینتاش:</b>\n"
             . "• <a href='https://github.com/hiddify/hiddify-app/releases'>دانلود نرم‌افزار Hiddify Next</a>\n"
             . "• <a href='https://github.com/2dust/v2rayN/releases'>دانلود v2rayN ویندوز</a>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        $edited = false;
        if ($messageId) {
            $edited = TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard);
        }
        if (!$edited) {
            TelegramBot::sendMessage($msg, $chatId, $keyboard);
        }
    }

    /**
     * Show Support Info
     */
    private static function showSupportInfo(string $chatId, ?int $messageId = null): void {
        $supportId = Setting::get('support_telegram', '@Connectix_Admin');
        $msg = "☎️ <b>پشتیبانی و ارتباط با مدیریت</b>\n\n"
             . "در صورت وجود هرگونه پرسش، مشکل در پرداخت یا نیاز به راهنمایی در اتصال، می‌توانید مستقیماً با آیدی پشتیبانی در تماس باشید:\n\n"
             . "👤 <b>آیدی پشتیبانی تلگرام:</b> {$supportId}\n"
             . "⏰ <b>ساعات پاسخگویی:</b> ۲۴ ساعته در هفت روز هفته";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'پیام به پشتیبان تلگرام', 'url' => 'https://t.me/' . ltrim($supportId, '@')]],
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        $edited = false;
        if ($messageId) {
            $edited = TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard);
        }
        if (!$edited) {
            TelegramBot::sendMessage($msg, $chatId, $keyboard);
        }
    }

    /**
     * Approve order and provision account
     */
    public static function approveOrderAction(PDO $pdo, int $orderId, ?string $adminId = null): array {
        $stmt = $pdo->prepare("SELECT o.*, p.traffic_gb, p.duration_days, p.server_group 
                               FROM bot_orders o 
                               LEFT JOIN plans p ON o.plan_id = p.id 
                               WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            return ['success' => false, 'error' => 'سفارش یافت نشد.'];
        }

        if ($order['payment_status'] === 'paid') {
            return ['success' => true, 'username' => 'قبلاً فعال شده', 'password' => '---', 'sub_url' => ''];
        }

        // Renewal order fulfillment
        // Delivery for renewal order
        if ($order['order_type'] === 'renew' && !empty($order['client_id'])) {
            $renewResult = Provisioner::renewClient((int)$order['client_id'], (int)$order['plan_id']);
            if (!$renewResult['success']) {
                return ['success' => false, 'error' => $renewResult['error']];
            }

            $pdo->prepare("UPDATE bot_orders SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$orderId]);

            $stmtCl = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmtCl->execute([$order['client_id']]);
            $client = $stmtCl->fetch();

            $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($subUrl);

            $customerMsg = "🎉 <b>تمدید اشتراک شما با موفقیت تایید و اعمال گردید!</b>\n\n"
                         . "👤 <b>نام کاربری:</b> <code>{$client['username']}</code>\n"
                         . "🔑 <b>کلمه عبور:</b> <code>" . ($client['password'] ?: '123456') . "</code>\n"
                         . "➕ <b>حجم افزوده شده:</b> {$order['traffic_gb']} گیگابایت\n"
                         . "⏳ <b>تاریخ انقضای جدید:</b> {$renewResult['new_expire']}\n\n"
                         . "🔗 <b>لینک ساب‌لینک اختصاصی:</b>\n<code>{$subUrl}</code>\n\n"
                         . "📱 <i>بارکد QR فوق به‌روزرسانی شده و آماده اسکن است.</i>";

            TelegramBot::sendPhoto($qrUrl, $customerMsg, $order['user_tg_id'], [
                'inline_keyboard' => [
                    [['text' => '📊 مشاهده وضعیت اشتراک', 'callback_data' => 'view_acc_' . $client['id']]],
                    [['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
                ]
            ]);

            return [
                'success' => true,
                'username' => $client['username'],
                'password' => $client['password'] ?: '123456',
                'sub_url' => $subUrl
            ];
        }

        // New Purchase Order fulfillment via Provisioner
        $customNote = "خریداری شده توسط تلگرام ID: " . $order['user_tg_id'];
        $prov = Provisioner::createClient(
            (int)$order['plan_id'], 
            $order['server_id'] ? (int)$order['server_id'] : null, 
            null, 
            null, 
            1, 
            $customNote,
            (string)$order['user_tg_id']
        );

        if (!$prov['success']) {
            return ['success' => false, 'error' => $prov['error']];
        }

        // Update Order
        $pdo->prepare("UPDATE bot_orders SET payment_status = 'paid', client_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$prov['client_id'], $orderId]);

        // Deliver to customer on Telegram as QR Photo + Full Caption
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($prov['sub_url']);
        $customerMsg = "🎉 <b>سفارش شما تایید و اشتراک فعال گردید!</b>\n\n"
                     . "👤 <b>نام کاربری:</b> <code>{$prov['username']}</code>\n"
                     . "🔑 <b>کلمه عبور:</b> <code>{$prov['password']}</code>\n"
                     . "📦 <b>حجم اشتراک:</b> {$prov['traffic_gb']} گیگابایت\n"
                     . "⏳ <b>مهلت استفاده:</b> {$prov['expire_at']}\n"
                     . "🌐 <b>سرور:</b> {$prov['server_name']}\n\n"
                     . "🔗 <b>لینک اتصال اختصاصی شما (Sublink):</b>\n"
                     . "<code>{$prov['sub_url']}</code>\n\n"
                     . "📱 <i>برای اتصال، بارکد فوق را اسکن نمایید یا روی دکمه‌های زیر ضربه بزنید:</i>";

        $customerKeyboard = [
            'inline_keyboard' => [
                [['text' => '🌐 باز کردن صفحه اشتراک و QR کد', 'url' => $prov['sub_url']]],
                [['text' => '📱 دانلود نرم‌افزارهای اتصال', 'callback_data' => 'menu_apps']],
                [['text' => '👤 حساب‌های من', 'callback_data' => 'menu_my_accounts']],
                [['text' => '🔙 منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        TelegramBot::sendPhoto($qrUrl, $customerMsg, $order['user_tg_id'], $customerKeyboard);

        return [
            'success' => true,
            'username' => $prov['username'],
            'password' => $prov['password'],
            'sub_url' => $prov['sub_url']
        ];
    }

    /**
     * Reject order action
     */
    public static function rejectOrderAction(PDO $pdo, int $orderId, ?string $adminId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $pdo->prepare("UPDATE bot_orders SET payment_status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$orderId]);
            $rejectMsg = "❌ <b>سفارش شماره {$order['order_code']} توسط مدیریت رد شد.</b>\nدر صورت کسر وجه یا نیاز به بررسی، لطفاً با پشتیبانی در ارتباط باشید.";
            TelegramBot::sendMessage($rejectMsg, $order['user_tg_id'], [
                'inline_keyboard' => [[['text' => '☎️ پشتیبانی تلگرام', 'callback_data' => 'menu_support']]]
            ]);
        }
    }

    // Session helpers
    private static function getSession(PDO $pdo, string $tgId): ?array {
        $stmt = $pdo->prepare("SELECT * FROM bot_sessions WHERE tg_id = ?");
        $stmt->execute([$tgId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['data'] = !empty($row['data']) ? json_decode($row['data'], true) : [];
            return $row;
        }
        return null;
    }

    private static function setSession(PDO $pdo, string $tgId, string $step, array $data = []): void {
        $jsonData = json_encode($data);
        if (DB_DRIVER === 'sqlite') {
            $stmt = $pdo->prepare("INSERT INTO bot_sessions (tg_id, step, data, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON CONFLICT(tg_id) DO UPDATE SET step = excluded.step, data = excluded.data, updated_at = CURRENT_TIMESTAMP");
        } else {
            $stmt = $pdo->prepare("INSERT INTO bot_sessions (tg_id, step, data, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE step = VALUES(step), data = VALUES(data), updated_at = CURRENT_TIMESTAMP");
        }
        $stmt->execute([$tgId, $step, $jsonData]);
    }

    private static function clearSession(PDO $pdo, string $tgId): void {
        $pdo->prepare("DELETE FROM bot_sessions WHERE tg_id = ?")->execute([$tgId]);
    }

    // ==========================================
    // Web Admin Panel Management Actions
    // ==========================================

    public function manage(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $settings = Setting::getAll();
        $webhookInfo = TelegramBot::getWebhookInfo();

        $orders = $pdo->query("SELECT o.*, p.title as plan_title, c.username as client_username 
                               FROM bot_orders o 
                               LEFT JOIN plans p ON o.plan_id = p.id 
                               LEFT JOIN clients c ON o.client_id = c.id 
                               ORDER BY o.id DESC LIMIT 50")->fetchAll();

        require __DIR__ . '/../views/settings/bot.php';
    }

    public function updateSettings(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/bot');
        }

        Setting::set('telegram_bot_token', trim($_POST['telegram_bot_token'] ?? ''));
        Setting::set('telegram_bot_username', trim($_POST['telegram_bot_username'] ?? ''));
        Setting::set('telegram_admin_id', trim($_POST['telegram_admin_id'] ?? ''));
        Setting::set('telegram_bot_active', isset($_POST['telegram_bot_active']) ? '1' : '0');

        Setting::set('card_number', trim($_POST['card_number'] ?? ''));
        Setting::set('card_holder', trim($_POST['card_holder'] ?? ''));
        Setting::set('card_bank_name', trim($_POST['card_bank_name'] ?? ''));
        Setting::set('card_sheba', trim($_POST['card_sheba'] ?? ''));
        Setting::set('support_telegram', trim($_POST['support_telegram'] ?? ''));
        Setting::set('bot_welcome_text', trim($_POST['bot_welcome_text'] ?? ''));

        Setting::set('payment_gateway', trim($_POST['payment_gateway'] ?? 'card'));
        Setting::set('zarinpal_merchant', trim($_POST['zarinpal_merchant'] ?? ''));
        Setting::set('nextpay_apikey', trim($_POST['nextpay_apikey'] ?? ''));
        Setting::set('nowpayments_apikey', trim($_POST['nowpayments_apikey'] ?? ''));

        Helpers::flash('success', 'تنظیمات ربات تلگرام و درگاه‌های پرداخت با موفقیت ذخیره شد.');
        Helpers::redirect('settings/bot');
    }

    public function setWebhookAction(): void {
        Auth::requireAdmin();
        $webhookUrl = Helpers::fullFileUrl('webhook.php');
        $res = TelegramBot::setWebhook($webhookUrl);

        if (isset($res['ok']) && $res['ok'] === true) {
            Helpers::flash('success', "وبهوک تلگرام با موفقیت تنظیم شد: {$webhookUrl}");
        } else {
            $desc = $res['description'] ?? 'خطای نامشخص در ارتباط با تلگرام';
            Helpers::flash('error', "خطا در تنظیم وبهوک: {$desc}");
        }
        Helpers::redirect('settings/bot');
    }

    public function testMessageAction(): void {
        Auth::requireAdmin();
        $adminId = TelegramBot::getAdminChatId();
        if (empty($adminId)) {
            Helpers::flash('error', 'شناسه عددی تلگرام مدیر (Admin Chat ID) تنظیم نشده است.');
            Helpers::redirect('settings/bot');
        }

        $pdo = Database::getConnection();
        $brand = Setting::get('brand_name', 'کانکتیکس');
        $msg = "🚀 <b>تست ارتباط ربات تلگرام با پنل {$brand}</b>\n\n"
             . "✅ توکن ربات و سیستم اتصال چنداکانتی کاملاً متصل و فعال هستند.";

        $sent = TelegramBot::sendMessage($msg, $adminId, self::getMainMenuInlineKeyboard($pdo, $adminId));

        if ($sent) {
            TelegramBot::sendMessage("👇 کیبورد دسترسی سریع:", $adminId, self::getMainMenuReplyKeyboard());
            Helpers::flash('success', "پیام تست به همراه کیبورد و منوی چندکاربره با موفقیت به تلگرام مدیر (ID: {$adminId}) ارسال گردید.");
        } else {
            Helpers::flash('error', "ارسال پیام تست ناموفق بود. توکن ربات و شناسه عددی مدیر را بررسی فرمایید.");
        }
        Helpers::redirect('settings/bot');
    }

    public function deleteWebhookAction(): void {
        Auth::requireAdmin();
        $res = TelegramBot::deleteWebhook();
        if (isset($res['ok']) && $res['ok'] === true) {
            Helpers::flash('success', 'وبهوک تلگرام حذف شد.');
        } else {
            Helpers::flash('error', 'خطا در حذف وبهوک.');
        }
        Helpers::redirect('settings/bot');
    }

    public function approveWeb(): void {
        Auth::requireAdmin();
        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo = Database::getConnection();

        $res = self::approveOrderAction($pdo, $orderId);
        if ($res['success']) {
            Helpers::flash('success', "سفارش #{$orderId} تایید شد. کلاینت {$res['username']} با رمز {$res['password']} صادر و به تلگرام مشتری تحویل گردید.");
        } else {
            Helpers::flash('error', "خطا در تایید سفارش: " . $res['error']);
        }
        Helpers::redirect('settings/bot');
    }

    public function rejectWeb(): void {
        Auth::requireAdmin();
        $orderId = (int)($_POST['order_id'] ?? 0);
        $pdo = Database::getConnection();

        self::rejectOrderAction($pdo, $orderId);
        Helpers::flash('info', "سفارش #{$orderId} رد شد و به مشتری اطلاع داده شد.");
        Helpers::redirect('settings/bot');
    }
}

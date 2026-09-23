<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/Provisioner.php';
require_once __DIR__ . '/SublinkController.php';

class TelegramBotController {
    public static ?array $currentContext = null;

    public static function resolveContext(PDO $pdo, ?string $token = null, ?int $resellerId = null): array {
        $token = $token ?: ($_GET['bot_token'] ?? $_GET['token'] ?? null);
        $resellerId = $resellerId ?: (isset($_GET['reseller_id']) ? (int)$_GET['reseller_id'] : null);

        $reseller = null;
        if (!empty($token)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_bot_token = ? LIMIT 1");
            $stmt->execute([$token]);
            $reseller = $stmt->fetch();
        } elseif (!empty($resellerId)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$resellerId]);
            $reseller = $stmt->fetch();
        }

        if (!$reseller) {
            $reseller = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetch();
            $token = $token ?: TelegramBot::getToken();
        }

        $brand = !empty($reseller['brand_name']) ? $reseller['brand_name'] : Setting::get('brand_name', 'کانکتیکس');
        $card = [
            'number' => !empty($reseller['card_number']) ? $reseller['card_number'] : Setting::get('bank_card', Setting::get('card_number', '۶۰۳۷-۹۹۷۵-xxxx-xxxx')),
            'holder' => !empty($reseller['card_holder']) ? $reseller['card_holder'] : Setting::get('bank_card_owner', Setting::get('card_holder', 'مدیریت')),
            'shaba' => !empty($reseller['card_shaba']) ? $reseller['card_shaba'] : Setting::get('card_sheba', ''),
        ];
        $adminChatId = !empty($reseller['telegram_admin_chat_id']) ? $reseller['telegram_admin_chat_id'] : TelegramBot::getAdminChatId();

        self::$currentContext = [
            'reseller_id' => (int)($reseller['id'] ?? 1),
            'reseller' => $reseller,
            'bot_token' => $token,
            'brand_name' => $brand,
            'card' => $card,
            'admin_chat_id' => $adminChatId,
            'support_username' => !empty($reseller['support_username']) ? $reseller['support_username'] : Setting::get('telegram_support', ''),
            'channel' => !empty($reseller['telegram_channel']) ? $reseller['telegram_channel'] : Setting::get('bot_force_join_channel', '')
        ];

        return self::$currentContext;
    }

    public static function getContext(?PDO $pdo = null): array {
        if (self::$currentContext !== null) {
            return self::$currentContext;
        }
        $pdo = $pdo ?: Database::getConnection();
        return self::resolveContext($pdo);
    }

    /**
     * Check if user has joined the mandatory channel (Force Join)
     */
    public static function checkForceJoin(PDO $pdo, string $chatId, string $fromId, ?string $botToken = null): bool {
        $ctx = self::getContext($pdo);
        $channel = trim(!empty($ctx['channel']) ? $ctx['channel'] : Setting::get('bot_force_join_channel', ''));
        if (empty($channel)) {
            return true;
        }

        $botToken = $botToken ?: $ctx['bot_token'];
        $res = TelegramBot::getChatMember($channel, (int)$fromId, $botToken);
        if ($res && isset($res['status'])) {
            $status = $res['status'];
            if (in_array($status, ['creator', 'administrator', 'member', 'restricted'])) {
                return true;
            }
        }

        $channelClean = ltrim($channel, '@');
        $channelUrl = str_starts_with($channel, '-') ? '#' : "https://t.me/{$channelClean}";

        $joinMsg = "⚠️ <b>عضویت در کانال الزامی است</b>\n\n"
                 . "کاربر گرامی، جهت استفاده از کلیه خدمات ربات، عضویت در کانال رسمی اطلاع‌رسانی الزامی است:\n\n"
                 . "📢 <b>کانال رسمی:</b> {$channel}\n\n"
                 . "لطفاً ابتدا در کانال عضو شده و سپس دکمه «تایید عضویت ✅» را لمس فرمایید.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📢 عضویت در کانال رسمی', 'url' => $channelUrl]
                ],
                [
                    ['text' => '✅ تایید عضویت', 'callback_data' => 'check_join']
                ]
            ]
        ];

        TelegramBot::sendMessage($joinMsg, $chatId, $keyboard, $botToken);
        return false;
    }

    public static function getPlansForReseller(PDO $pdo, int $resellerId, bool $includeFree = false): array {
        $sql = "SELECT p.*, 
                       COALESCE(rp.custom_title, p.title) as display_title,
                       COALESCE(rp.custom_category, p.category, '۱ ماهه') as display_category,
                       COALESCE(rp.retail_price, p.base_price) as display_price,
                       COALESCE(rp.is_active, 1) as display_active
                FROM plans p
                LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ?
                WHERE p.is_active = 1 AND COALESCE(rp.is_active, 1) = 1 AND COALESCE(p.show_in_bot, 1) = 1";
        if (!$includeFree) {
            $sql .= " AND p.is_free = 0";
        }
        $sql .= " ORDER BY display_category ASC, display_price ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$resellerId]);
        return $stmt->fetchAll();
    }

    /**
     * Webhook entry point called by Telegram server
     */
    public static function handleWebhook(?string $incomingToken = null, ?int $incomingResellerId = null): void {
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
            self::resolveContext($pdo, $incomingToken, $incomingResellerId);

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
     * Main Menu Inline Keyboard with clean 2-column layout & custom labels
     */
    public static function getMainMenuInlineKeyboard(?PDO $pdo = null, ?string $fromId = null): array {
        $boundCount = 0;
        if ($pdo && $fromId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE telegram_chat_id = ?");
            $stmt->execute([$fromId]);
            $boundCount = (int)$stmt->fetchColumn();
        }

        $buyText = Setting::get('btn_buy_text', '🛒 خرید اشتراک');
        $renewText = Setting::get('btn_renew_text', '🔄 تمدید اشتراک');
        $myAccText = Setting::get('btn_my_accounts_text', '👤 حساب‌های من');
        $trialText = Setting::get('btn_trial_text', '🎁 تست رایگان');
        $refText = Setting::get('btn_referral_text', '🤝 کسب درآمد');
        $appsText = Setting::get('btn_apps_text', '📱 دانلود و آموزش');
        $supportText = Setting::get('btn_support_text', '☎️ پشتیبانی');
        $resellerText = Setting::get('btn_reseller_text', '💼 اخذ نمایندگی');

        $buttons = [];

        if ($boundCount > 0) {
            $buttons[] = [
                ['text' => "{$myAccText} ({$boundCount})", 'callback_data' => 'menu_my_accounts'],
                ['text' => '➕ اتصال حساب دیگر', 'callback_data' => 'menu_bind']
            ];
        } else {
            $buttons[] = [
                ['text' => '🔗 ورود و اتصال حساب', 'callback_data' => 'menu_bind'],
                ['text' => '🔍 استعلام وضعیت', 'callback_data' => 'menu_guest_status']
            ];
        }

        $buttons[] = [
            ['text' => $buyText, 'callback_data' => 'menu_buy'],
            ['text' => $renewText, 'callback_data' => 'menu_renew']
        ];

        $buttons[] = [
            ['text' => $trialText, 'callback_data' => 'menu_trial'],
            ['text' => $refText, 'callback_data' => 'menu_referral']
        ];

        $buttons[] = [
            ['text' => $appsText, 'callback_data' => 'menu_apps'],
            ['text' => $supportText, 'callback_data' => 'menu_support']
        ];

        $buttons[] = [
            ['text' => $resellerText, 'callback_data' => 'menu_reseller_apply'],
            ['text' => '🔐 ورود به پنل وب', 'callback_data' => 'menu_panel_credentials']
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Main Menu Reply Keyboard (Fixed at bottom chat input - Clean 2-column layout)
     */
    public static function getMainMenuReplyKeyboard(): array {
        $buyText = Setting::get('btn_buy_text', '🛒 خرید اشتراک');
        $renewText = Setting::get('btn_renew_text', '🔄 تمدید اشتراک');
        $myAccText = Setting::get('btn_my_accounts_text', '👤 حساب‌های من');
        $trialText = Setting::get('btn_trial_text', '🎁 تست رایگان');
        $refText = Setting::get('btn_referral_text', '🤝 کسب درآمد');
        $appsText = Setting::get('btn_apps_text', '📱 دانلود و آموزش');
        $supportText = Setting::get('btn_support_text', '☎️ پشتیبانی');
        $resellerText = Setting::get('btn_reseller_text', '💼 اخذ نمایندگی');

        return [
            'keyboard' => [
                [
                    ['text' => $buyText],
                    ['text' => $renewText]
                ],
                [
                    ['text' => $myAccText],
                    ['text' => $trialText]
                ],
                [
                    ['text' => $refText],
                    ['text' => $appsText]
                ],
                [
                    ['text' => $supportText],
                    ['text' => $resellerText]
                ],
                [
                    ['text' => '🔐 ورود به پنل وب']
                ]
            ],
            'resize_keyboard' => true,
            'is_persistent' => true
        ];
    }

    public static function recordBotUser(PDO $pdo, array $from, int $resellerId = 1): void {
        $tgId = (string)($from['id'] ?? '');
        if (empty($tgId)) return;

        $firstName = $from['first_name'] ?? '';
        $lastName = $from['last_name'] ?? '';
        $username = $from['username'] ?? '';

        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $sql = "INSERT INTO bot_users (reseller_id, tg_id, first_name, last_name, username, last_active_at) 
                        VALUES (?, ?, ?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE 
                        first_name = VALUES(first_name), 
                        last_name = VALUES(last_name), 
                        username = VALUES(username), 
                        last_active_at = NOW()";
            } else {
                $sql = "INSERT INTO bot_users (reseller_id, tg_id, first_name, last_name, username, last_active_at) 
                        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP) 
                        ON CONFLICT(tg_id) DO UPDATE SET 
                        first_name = excluded.first_name, 
                        last_name = excluded.last_name, 
                        username = excluded.username, 
                        last_active_at = CURRENT_TIMESTAMP";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$resellerId, $tgId, $firstName, $lastName, $username]);
        } catch (Throwable $e) {}
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

        // Force Join Check handler
        if ($data === 'check_join') {
            $channel = trim(Setting::get('bot_force_join_channel', ''));
            $res = TelegramBot::getChatMember($channel, (int)$fromId);
            $isMember = false;
            if ($res && isset($res['status'])) {
                $status = $res['status'];
                if (in_array($status, ['creator', 'administrator', 'member', 'restricted'])) {
                    $isMember = true;
                }
            }

            if ($isMember) {
                TelegramBot::answerCallbackQuery($cbId, '✅ عضویت شما در کانال تایید شد. خوش آمدید!', false);
                self::sendMainMenu($pdo, $chatId, $fromId, $cb['from']['first_name'] ?? '', $messageId);
            } else {
                TelegramBot::answerCallbackQuery($cbId, '❌ شما هنوز در کانال عضو نشده‌اید! لطفاً ابتدا عضو شوید.', true);
            }
            return;
        }

        // Database Backup Restore Confirmation
        if ($data === 'confirm_db_restore') {
            $session = self::getSession($pdo, $fromId);
            $ctx = self::getContext($pdo);
            if ($session && $session['step'] === 'confirm_sql_restore') {
                $fileInfo = $session['data'] ?? [];
                $fileId = $fileInfo['file_id'] ?? '';
                $fileName = $fileInfo['file_name'] ?? 'backup.sql';

                TelegramBot::answerCallbackQuery($cbId, 'درحال دانلود و اجرای فایل دیتابیس...', false);
                if ($messageId) {
                    TelegramBot::editMessageText("⏳ <b>درحال پردازش و بازگردانی دیتابیس...</b>\nلطفاً چند ثانیه شکیبا باشید.", $chatId, $messageId, null, $ctx['bot_token']);
                }

                $tgFile = TelegramBot::getFile($fileId, $ctx['bot_token']);
                if ($tgFile && !empty($tgFile['file_path'])) {
                    $sqlContent = TelegramBot::downloadFile($tgFile['file_path'], $ctx['bot_token']);
                    if (!empty($sqlContent)) {
                        $restoreResult = Database::restoreFromSql($sqlContent);
                        self::clearSession($pdo, $fromId);

                        if ($restoreResult['success']) {
                            Helpers::logActivity('backup_restore_tg', "بازگردانی دیتابیس از تلگرام ({$fileName}) با موفقیت انجام شد ({$restoreResult['executed']} کوئری)", 'system');
                            $successMsg = "🎉 <b>بازگردانی پایگاه داده با موفقیت کامل انجام شد!</b>\n\n"
                                        . "📄 فایل: <code>{$fileName}</code>\n"
                                        . "⚡️ تعداد دستورات موفق: <b>{$restoreResult['executed']}</b> کوئری\n"
                                        . "🕒 تاریخ و ساعت: " . date('Y-m-d H:i:s');
                            if ($messageId) {
                                TelegramBot::editMessageText($successMsg, $chatId, $messageId, self::getMainMenuInlineKeyboard($pdo, $fromId), $ctx['bot_token']);
                            } else {
                                TelegramBot::sendMessage($successMsg, $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $ctx['bot_token']);
                            }
                        } else {
                            $errMsg = "❌ <b>خطا در بازگردانی دیتابیس:</b>\n" . htmlspecialchars($restoreResult['error'] ?? 'خطای ناشناخته');
                            if ($messageId) {
                                TelegramBot::editMessageText($errMsg, $chatId, $messageId, self::getMainMenuInlineKeyboard($pdo, $fromId), $ctx['bot_token']);
                            } else {
                                TelegramBot::sendMessage($errMsg, $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $ctx['bot_token']);
                            }
                        }
                    } else {
                        self::clearSession($pdo, $fromId);
                        TelegramBot::sendMessage("❌ خطا در دانلود فایل پشتیبان از سرور تلگرام.", $chatId, null, $ctx['bot_token']);
                    }
                } else {
                    self::clearSession($pdo, $fromId);
                    TelegramBot::sendMessage("❌ دسترسی به اطلاعات فایل تلگرام مقدور نبود.", $chatId, null, $ctx['bot_token']);
                }
            } else {
                TelegramBot::answerCallbackQuery($cbId, 'درخواست منقضی شده است.', true);
            }
            return;
        }

        if ($data === 'cancel_db_restore') {
            $ctx = self::getContext($pdo);
            self::clearSession($pdo, $fromId);
            TelegramBot::answerCallbackQuery($cbId, 'عملیات لغو شد.', false);
            if ($messageId) {
                TelegramBot::editMessageText("❌ عملیات بازگردانی دیتابیس لغو شد.", $chatId, $messageId, self::getMainMenuInlineKeyboard($pdo, $fromId), $ctx['bot_token']);
            }
            return;
        }

        // Before executing other actions, verify channel membership if enabled
        if (!self::checkForceJoin($pdo, $chatId, $fromId)) {
            TelegramBot::answerCallbackQuery($cbId, '⚠️ عضویت در کانال جهت استفاده از ربات الزامی است.', true);
            return;
        }

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

            $ctx = self::getContext($pdo);
            $botToken = $ctx['bot_token'];
            $cardNumber = $ctx['card']['number'];
            $cardHolder = $ctx['card']['holder'];
            $cardSheba = $ctx['card']['shaba'];
            $amountFa = number_format($order['amount']) . ' تومان';

            self::setSession($pdo, $fromId, 'awaiting_receipt', ['order_id' => $orderId]);

            $msg = "💳 <b>اطلاعات حساب جهت واریز کارت به کارت ({$ctx['brand_name']})</b>\n\n"
                 . "🔢 <b>شماره کارت:</b>\n<code>{$cardNumber}</code>\n\n"
                 . "👤 <b>به نام:</b> {$cardHolder}\n";

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
                TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
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

        // Category Selection for Plans
        if (str_starts_with($data, 'cat_buy_')) {
            $catHash = str_replace('cat_buy_', '', $data);
            self::showPlansMenu($pdo, $chatId, $messageId, $catHash);
            return;
        }

        // Platform Selection for Apps Download
        if (str_starts_with($data, 'apps_plat_')) {
            $plat = str_replace('apps_plat_', '', $data);
            self::showAppsDownload($pdo, $chatId, $messageId, $plat);
            return;
        }

        // Apply Coupon Code
        if (str_starts_with($data, 'apply_coupon_')) {
            $orderId = (int)str_replace('apply_coupon_', '', $data);
            self::setSession($pdo, $fromId, 'awaiting_coupon', ['order_id' => $orderId]);
            $msg = "🎟 <b>اعمال کد تخفیف</b>\n\nلطفاً کد تخفیف خود را به صورت متنی در چت ارسال فرمایید:";
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🔙 انصراف و بازگشت به فاکتور', 'callback_data' => 'view_order_' . $orderId]]
                ]
            ];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // View Order Invoice
        if (str_starts_with($data, 'view_order_')) {
            $orderId = (int)str_replace('view_order_', '', $data);
            self::renderOrderInvoice($pdo, $orderId, $chatId, $messageId);
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
            self::showAppsDownload($pdo, $chatId, $messageId, null);
            return;
        }
        if ($data === 'menu_support') {
            self::showSupportInfo($chatId, $messageId);
            return;
        }

        // Reseller Application Start
        if ($data === 'menu_reseller_apply') {
            self::startResellerApplication($pdo, $chatId, $fromId, $messageId);
            return;
        }

        // Web Panel Credentials & Magic Login Link
        if ($data === 'menu_panel_credentials') {
            self::showPanelCredentials($pdo, $chatId, $fromId, $messageId);
            return;
        }

        // Link panel account to telegram
        if ($data === 'link_panel_account') {
            self::setSession($pdo, $fromId, 'awaiting_panel_bind_username', []);
            $msg = "🔗 <b>اتصال حساب کاربری پنل وب به تلگرام</b>\n\n"
                 . "لطفاً <b>نام کاربری (Username)</b> ورود به پنل مدیریت یا نمایندگی خود را ارسال فرمایید:";
            $kb = ['inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // Change panel password from bot
        if ($data === 'change_panel_password') {
            self::setSession($pdo, $fromId, 'awaiting_new_panel_pwd', []);
            $msg = "🔄 <b>تغییر / تنظیم رمز عبور جدید پنل وب</b>\n\n"
                 . "لطفاً <b>کلمه عبور جدید</b> مورد نظر خود را ارسال فرمایید (حداقل ۶ کاراکتر):";
            $kb = ['inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb);
            }
            return;
        }

        // Free Trial Account Request
        if ($data === 'menu_trial') {
            self::handleFreeTrialRequest($pdo, $chatId, $fromId, $messageId);
            return;
        }

        // Referral & Affiliate Info
        if ($data === 'menu_referral') {
            self::showReferralInfo($pdo, $chatId, $fromId, $messageId);
            return;
        }

        // Crypto Payment Trigger
        if (str_starts_with($data, 'pay_crypto_')) {
            $orderId = (int)str_replace('pay_crypto_', '', $data);
            self::showCryptoPayment($pdo, $chatId, $fromId, $orderId, $messageId);
            return;
        }

        // TON Payment Trigger
        if (str_starts_with($data, 'pay_ton_')) {
            $orderId = (int)str_replace('pay_ton_', '', $data);
            self::showTonPayment($pdo, $chatId, $fromId, $orderId, $messageId);
            return;
        }

        // Admin Actions: Inline Crypto Payment Approval
        if (str_starts_with($data, 'admin_crypto_approve_')) {
            $cryptoId = (int)str_replace('admin_crypto_approve_', '', $data);
            self::approveCryptoPayment($pdo, $cryptoId, $chatId, $messageId);
            return;
        }

        // Admin Actions: Inline Crypto Payment Rejection
        if (str_starts_with($data, 'admin_crypto_reject_')) {
            $cryptoId = (int)str_replace('admin_crypto_reject_', '', $data);
            self::rejectCryptoPayment($pdo, $cryptoId, $chatId, $messageId);
            return;
        }

        // Admin Actions: Inline Approve Reseller
        if (str_starts_with($data, 'approve_reseller_')) {
            $appId = (int)str_replace('approve_reseller_', '', $data);
            self::approveResellerApplication($pdo, $appId, $chatId, $messageId);
            return;
        }

        // Admin Actions: Inline Reject Reseller
        if (str_starts_with($data, 'reject_reseller_')) {
            $appId = (int)str_replace('reject_reseller_', '', $data);
            self::rejectResellerApplication($pdo, $appId, $chatId, $messageId);
            return;
        }
    }

    /**
     * Render Order Invoice with Clean 2-Column Action Buttons
     */
    private static function renderOrderInvoice(PDO $pdo, int $orderId, string $chatId, ?int $messageId = null, ?string $botToken = null): void {
        $stmt = $pdo->prepare("SELECT o.*, p.title, p.traffic_gb, p.duration_days, 
                                      COALESCE(rp.custom_title, p.title) as display_title
                               FROM bot_orders o
                               LEFT JOIN plans p ON o.plan_id = p.id
                               LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = o.reseller_id
                               WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        $ctx = self::getContext($pdo);
        $botToken = $botToken ?: $ctx['bot_token'];

        $priceFa = number_format($order['amount']) . ' تومان';
        $titlePrefix = ($order['order_type'] === 'renew') ? 'پیش‌فاکتور تمدید اشتراک' : 'پیش‌فاکتور خرید اشتراک جدید';

        $trafficVal = (float)($order['traffic_gb'] ?? 0);
        if ($trafficVal > 0 && $trafficVal < 1) {
            $trafficText = round($trafficVal * 1024) . ' مگابایت';
        } elseif ($trafficVal >= 1) {
            $trafficText = ($trafficVal == (int)$trafficVal ? (int)$trafficVal : $trafficVal) . ' گیگابایت';
        } else {
            $trafficText = 'نامحدود';
        }

        $msg = "🛒 <b>{$titlePrefix}</b>\n\n"
             . "📦 <b>پلن انتخابی:</b> {$order['display_title']}\n"
             . "💾 <b>حجم ترافیک:</b> {$trafficText}\n"
             . "⏳ <b>مدت اعتبار:</b> {$order['duration_days']} روز\n";

        if (!empty($order['coupon_code'])) {
            $msg .= "🎟 <b>کد تخفیف:</b> <code>{$order['coupon_code']}</code>\n"
                  . "🔻 <b>میزان تخفیف:</b> " . number_format($order['discount_amount'] ?? 0) . " تومان\n";
        }

        $msg .= "💰 <b>مبلغ نهایی قابل پرداخت:</b> <b>{$priceFa}</b>\n"
             . "🔢 <b>کد رهگیری:</b> <code>{$order['order_code']}</code>\n\n"
             . "روش پرداخت یا ثبت کد تخفیف را انتخاب نمایید:";

        $buttons = [
            [
                ['text' => '💳 کارت به کارت', 'callback_data' => 'pay_card_' . $orderId],
                ['text' => '🪙 پرداخت تتر (USDT)', 'callback_data' => 'pay_crypto_' . $orderId]
            ],
            [
                ['text' => '💎 پرداخت با تون (TON)', 'callback_data' => 'pay_ton_' . $orderId]
            ]
        ];

        if (empty($order['coupon_code'])) {
            $buttons[1][] = ['text' => '🎟 کد تخفیف', 'callback_data' => 'apply_coupon_' . $orderId];
        }

        $buttons[] = [
            ['text' => '❌ انصراف از سفارش', 'callback_data' => 'cancel_order_' . $orderId]
        ];

        $keyboard = ['inline_keyboard' => $buttons];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
        }
    }

    /**
     * Helper to create bot order
     */
    private static function createOrder(PDO $pdo, array $cb, int $planId, string $orderType = 'new', ?int $clientId = null, ?int $messageId = null): void {
        $fromId = (string)($cb['from']['id'] ?? '');
        $chatId = (string)($cb['message']['chat']['id'] ?? $fromId);

        $ctx = self::getContext($pdo);
        $resellerId = $ctx['reseller_id'];
        $botToken = $ctx['bot_token'];

        $stmt = $pdo->prepare("SELECT p.*, 
                                      COALESCE(rp.custom_title, p.title) as display_title,
                                      COALESCE(rp.retail_price, p.base_price) as display_price
                               FROM plans p 
                               LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ?
                               WHERE p.id = ? AND p.is_active = 1");
        $stmt->execute([$resellerId, $planId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            TelegramBot::sendMessage("⚠️ پلن انتخابی معتبر نیست.", $chatId, null, $botToken);
            return;
        }

        $orderCode = ($orderType === 'renew' ? 'RNW-' : 'ORD-') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $userTgName = trim(($cb['from']['first_name'] ?? '') . ' ' . ($cb['from']['last_name'] ?? ''));
        $userTgUsername = $cb['from']['username'] ?? '';
        $finalPrice = (int)$plan['display_price'];
        $planServerId = !empty($plan['server_id']) ? (int)$plan['server_id'] : null;

        $stmtOrder = $pdo->prepare("INSERT INTO bot_orders 
            (order_code, reseller_id, bot_token, user_tg_id, user_tg_name, user_tg_username, order_type, plan_id, server_id, client_id, amount, payment_method, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'card', 'pending_receipt')");
        $stmtOrder->execute([$orderCode, $resellerId, $botToken, $fromId, $userTgName, $userTgUsername, $orderType, $planId, $planServerId, $clientId, $finalPrice]);
        $orderId = (int)$pdo->lastInsertId();

        self::renderOrderInvoice($pdo, $orderId, $chatId, $messageId, $botToken);
    }

    /**
     * Process standard chat messages (Text, Photos)
     */
    private static function processMessage(PDO $pdo, array $msg): void {
        $chatId = (string)($msg['chat']['id'] ?? '');
        $fromId = (string)($msg['from']['id'] ?? $chatId);
        $chatType = $msg['chat']['type'] ?? 'private';
        $text = trim($msg['text'] ?? '');
        $session = self::getSession($pdo, $fromId);

        // Check if message originated from a group or supergroup
        $isGroup = ($chatType === 'group' || $chatType === 'supergroup' || str_starts_with($chatId, '-'));

        // Handle Group/Supergroup Security & Keyboard Isolation
        if ($isGroup) {
            $ctx = self::getContext($pdo);
            $botToken = $ctx['bot_token'];
            $botUser = $ctx['bot_username'] ?: Setting::get('telegram_bot_username', 'ConnectixBot');

            // 1. Explicit command to remove stuck bottom keyboard from group
            if (in_array($text, ['/clean', '/cleankeyboard', '/remove_keyboard', '/clear', 'حذف کیبورد', 'پاکسازی کیبورد', 'پاکسازی'])) {
                TelegramBot::sendMessage("🧹 <b>کیبورد ربات با موفقیت از این گروه برداشته شد.</b>\nدیگر دکمه‌های ربات در پایین این گروه نمایش داده نخواهند شد.", $chatId, ['remove_keyboard' => true], $botToken);
                return;
            }

            // 2. Admin backup on-demand command
            if ($text === '/backup' && $fromId == Setting::get('telegram_admin_id')) {
                // allow admin backup if desired
            }

            // 3. User invoked bot or tapped an old persistent keyboard button inside group
            $buyText = Setting::get('btn_buy_text', '🛒 خرید اشتراک');
            $renewText = Setting::get('btn_renew_text', '🔄 تمدید اشتراک');
            $myAccText = Setting::get('btn_my_accounts_text', '👤 حساب‌های من');
            $trialText = Setting::get('btn_trial_text', '🎁 تست رایگان');
            $refText = Setting::get('btn_referral_text', '🤝 کسب درآمد');
            $appsText = Setting::get('btn_apps_text', '📱 دانلود و آموزش');
            $supportText = Setting::get('btn_support_text', '☎️ پشتیبانی');
            $resellerText = Setting::get('btn_reseller_text', '💼 اخذ نمایندگی');

            $knownBotButtons = [
                $buyText, $renewText, $myAccText, $trialText, $refText, $appsText, $supportText, $resellerText,
                '🛒 خرید اشتراک جدید', '🛒 خرید اشتراک', '🔄 تمدید اشتراک', '👤 حساب‌های من', '🎁 تست رایگان',
                '🎁 دریافت تست رایگان', '/test', 'تست رایگان', '🤝 زیرمجموعه‌گیری و درآمد', '🤝 کسب درآمد',
                '📱 دانلود نرم‌افزارها', '☎️ پشتیبانی تلگرام', '💼 اخذ نمایندگی', '🔐 ورود به پنل وب',
                '🔗 ورود و اتصال حساب', 'پنل', 'ورود به پنل', '/panel', '/login'
            ];

            if (str_starts_with($text, '/start') || str_starts_with($text, '/menu') || in_array($text, $knownBotButtons)) {
                $redirectKb = [
                    'inline_keyboard' => [
                        [['text' => '🚀 ورود به ربات در گفتگوی خصوصی (PV)', 'url' => "https://t.me/{$botUser}?start=group"]]
                    ]
                ];
                // Immediately remove keyboard from group and guide user to private chat
                TelegramBot::sendMessage(
                    "👋 <b>کاربر گرامی</b>\n\nجهت حفظ امنیت، دریافت مشخصات اتصال و خرید اشتراک، لطفاً به گفتگوی خصوصی (PV) ربات مراجعه فرمایید.\n\n<i>(کیبورد دکمه‌ها نیز از این گروه حذف گردید)</i>",
                    $chatId,
                    ['remove_keyboard' => true],
                    $botToken
                );
                TelegramBot::sendMessage("👇 برای ورود به چت خصوصی روی دکمه زیر کلیک کنید:", $chatId, $redirectKb, $botToken);
                return;
            }

            // For all general group chatter between members: completely ignore and stay silent!
            return;
        }

        // Record User in Database (Only for private chat users)
        self::recordBotUser($pdo, $msg['from'] ?? [], (int)(self::getContext($pdo)['reseller_id'] ?? 1));

        // Method 2: One-Click DeepLink Binding: /start bind_XXXX
        if (preg_match('/^\/start\s+bind_([a-zA-Z0-9_\-]+)/', $text, $matches)) {
            $bindToken = trim($matches[1]);
            self::handleDirectDeepLinkBind($pdo, $chatId, $fromId, $bindToken);
            return;
        }

        // Method 3: Referral DeepLink: /start ref_XXXX
        if (preg_match('/^\/start\s+ref_([0-9]+)/', $text, $matches)) {
            $inviterTgId = trim($matches[1]);
            if ($inviterTgId != $fromId) {
                self::handleReferralStart($pdo, $fromId, $inviterTgId, $msg['from'] ?? []);
            }
        }

        // Force Join Channel Verification (Before interactive actions)
        if (!self::checkForceJoin($pdo, $chatId, $fromId)) {
            return;
        }

        // Command /start or /menu
        if (str_starts_with($text, '/start') || str_starts_with($text, '/menu') || $text === 'شروع' || $text === 'منو' || $text === 'دکمه ها' || $text === 'دکمه‌ها') {
            self::clearSession($pdo, $fromId);
            self::sendMainMenu($pdo, $chatId, $fromId, $msg['from']['first_name'] ?? '');
            return;
        }

        $buyText = Setting::get('btn_buy_text', '🛒 خرید اشتراک');
        $renewText = Setting::get('btn_renew_text', '🔄 تمدید اشتراک');
        $myAccText = Setting::get('btn_my_accounts_text', '👤 حساب‌های من');
        $trialText = Setting::get('btn_trial_text', '🎁 تست رایگان');
        $refText = Setting::get('btn_referral_text', '🤝 کسب درآمد');
        $appsText = Setting::get('btn_apps_text', '📱 دانلود و آموزش');
        $supportText = Setting::get('btn_support_text', '☎️ پشتیبانی');
        $resellerText = Setting::get('btn_reseller_text', '💼 اخذ نمایندگی');

        // Quick bottom keyboard shortcuts
        if ($text === $myAccText || $text === '👤 حساب‌های من') {
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
        if ($text === $buyText || $text === '🛒 خرید اشتراک جدید' || $text === '🛒 خرید اشتراک') {
            self::showPlansMenu($pdo, $chatId);
            return;
        }
        if ($text === $renewText || $text === '🔄 تمدید اشتراک') {
            self::showRenewChoice($pdo, $chatId, $fromId);
            return;
        }
        if ($text === $trialText || $text === '🎁 دریافت تست رایگان' || $text === '/test' || $text === 'تست رایگان') {
            self::handleFreeTrialRequest($pdo, $chatId, $fromId);
            return;
        }
        if ($text === $refText || $text === '🤝 زیرمجموعه‌گیری و درآمد' || $text === '🤝 زیرمجموعه‌گیری و درآمدزایی' || $text === '/referral' || $text === 'زیرمجموعه‌گیری') {
            self::showReferralInfo($pdo, $chatId, $fromId);
            return;
        }
        if ($text === $appsText || $text === '📱 دانلود نرم‌افزارها') {
            self::showAppsDownload($pdo, $chatId);
            return;
        }
        if ($text === $supportText || $text === '☎️ پشتیبانی تلگرام') {
            self::showSupportInfo($chatId);
            return;
        }
        if ($text === $resellerText || $text === '🤝 درخواست نمایندگی' || $text === '🤝 درخواست پنل نمایندگی' || $text === '/reseller' || $text === '💼 اخذ نمایندگی') {
            self::startResellerApplication($pdo, $chatId, $fromId);
            return;
        }

        // Web Panel Credentials Button / Commands
        if ($text === '🔐 ورود به پنل وب' || $text === '/panel' || $text === '/login' || $text === 'پنل' || $text === 'ورود به پنل') {
            self::showPanelCredentials($pdo, $chatId, $fromId);
            return;
        }

        // Step 1: Link panel username
        if ($session && $session['step'] === 'awaiting_panel_bind_username' && !empty($text)) {
            $username = trim($text);
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if (!$u) {
                TelegramBot::sendMessage("❌ کاربری با نام <code>{$username}</code> در پنل یافت نشد. لطفاً مجدداً نام کاربری صحیح را ارسال فرمایید:", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]
                ]);
                return;
            }
            self::setSession($pdo, $fromId, 'awaiting_panel_bind_password', ['user_id' => $u['id'], 'username' => $u['username']]);
            TelegramBot::sendMessage("🔑 لطفاً <b>کلمه عبور</b> حساب <code>{$u['username']}</code> را وارد نمایید:", $chatId, [
                'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]
            ]);
            return;
        }

        // Step 2: Link panel password verification
        if ($session && $session['step'] === 'awaiting_panel_bind_password' && !empty($text)) {
            $userId = (int)($session['data']['user_id'] ?? 0);
            $inputPassword = trim($text);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if (!$u || !password_verify($inputPassword, $u['password_hash'])) {
                TelegramBot::sendMessage("❌ کلمه عبور وارد شده نادرست است. لطفاً مجدداً تلاش فرمایید:", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]
                ]);
                return;
            }
            // Successfully verified! Save telegram_chat_id & display password
            $pdo->prepare("UPDATE users SET telegram_chat_id = ?, panel_password_display = ? WHERE id = ?")
                ->execute([(string)$fromId, $inputPassword, $userId]);
            self::clearSession($pdo, $fromId);
            TelegramBot::sendMessage("✅ <b>اتصال حساب با موفقیت تایید شد!</b>\nحساب شما به این اکانت تلگرام متصل گردید.", $chatId);
            self::showPanelCredentials($pdo, $chatId, $fromId);
            return;
        }

        // Step 3: Change panel password from bot
        if ($session && $session['step'] === 'awaiting_new_panel_pwd' && !empty($text)) {
            $newPwd = trim($text);
            if (strlen($newPwd) < 6) {
                TelegramBot::sendMessage("❌ کلمه عبور باید حداقل ۶ کاراکتر باشد. لطفاً مجدداً ارسال نمایید:", $chatId, [
                    'inline_keyboard' => [[['text' => '🔙 انصراف', 'callback_data' => 'menu_panel_credentials']]]
                ]);
                return;
            }

            // Find user
            $adminTgId = (string)(self::getContext($pdo)['admin_chat_id'] ?? Setting::get('telegram_admin_id', ''));
            $user = null;
            if (!empty($adminTgId) && ((string)$fromId === $adminTgId || (string)$chatId === $adminTgId)) {
                $user = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
            if (!$user) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (telegram_chat_id = ? OR telegram_admin_chat_id = ?) LIMIT 1");
                $stmt->execute([(string)$fromId, (string)$fromId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$user) {
                self::clearSession($pdo, $fromId);
                TelegramBot::sendMessage("حساب یافت نشد.", $chatId);
                return;
            }

            $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ?, panel_password_display = ? WHERE id = ?")
                ->execute([$newHash, $newPwd, $user['id']]);
            self::clearSession($pdo, $fromId);

            $loginUrl = Helpers::fullUrl('login');
            TelegramBot::sendMessage("✅ <b>کلمه عبور پنل با موفقیت تغییر یافت.</b>\n\n👤 <b>نام کاربری:</b> <code>{$user['username']}</code>\n🔑 <b>کلمه عبور جدید:</b> <code>{$newPwd}</code>\n🌐 <b>آدرس ورود:</b> {$loginUrl}", $chatId);
            self::showPanelCredentials($pdo, $chatId, $fromId);
            return;
        }

        // Coupon Code Input Step
        if ($session && $session['step'] === 'awaiting_coupon' && !empty($text)) {
            $orderId = (int)($session['data']['order_id'] ?? 0);
            $couponCode = strtoupper(trim($text));

            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? LIMIT 1");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch();

            $now = date('Y-m-d');
            $isValid = false;
            $errorReason = 'کد تخفیف معتبر نمی‌باشد.';

            if ($coupon) {
                if ((int)$coupon['is_active'] !== 1) {
                    $errorReason = 'این کد تخفیف در حال حاضر غیرفعال است.';
                } elseif (!empty($coupon['expires_at']) && $coupon['expires_at'] < $now) {
                    $errorReason = 'مهلت استفاده از این کد تخفیف به پایان رسیده است.';
                } elseif ((int)$coupon['max_uses'] > 0 && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
                    $errorReason = 'ظرفیت استفاده از این کد تخفیف تکمیل شده است.';
                } else {
                    $isValid = true;
                }
            }

            if (!$isValid) {
                $kb = ['inline_keyboard' => [
                    [['text' => '🔙 بازگشت به فاکتور سفارش', 'callback_data' => 'view_order_' . $orderId]]
                ]];
                TelegramBot::sendMessage("❌ <b>خطا در اعمال تخفیف:</b>\n{$errorReason}\nلطفاً کد دیگری وارد کنید یا به فاکتور بازگردید.", $chatId, $kb);
                return;
            }

            // Apply discount
            $stmtOrder = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ?");
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch();

            if (!$order) {
                self::clearSession($pdo, $fromId);
                self::sendMainMenu($pdo, $chatId, $fromId, '', null);
                return;
            }

            $discountPercent = (int)$coupon['discount_percent'];
            $discountAmount = (int)round(($order['amount'] * $discountPercent) / 100);
            $newAmount = max(1000, $order['amount'] - $discountAmount);

            $pdo->prepare("UPDATE bot_orders SET coupon_code = ?, discount_amount = ?, amount = ? WHERE id = ?")
                ->execute([$coupon['code'], $discountAmount, $newAmount, $orderId]);

            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
                ->execute([$coupon['id']]);

            self::clearSession($pdo, $fromId);

            TelegramBot::sendMessage("🎉 <b>کد تخفیف {$coupon['code']} ({$discountPercent}٪) با موفقیت اعمال گردید!</b>", $chatId);
            self::renderOrderInvoice($pdo, $orderId, $chatId);
            return;
        }

        // Crypto TXID Submission Step
        if ($session && $session['step'] === 'awaiting_crypto_txid') {
            self::handleCryptoTxidSubmission($pdo, $chatId, $fromId, $text, $session['data'] ?? []);
            return;
        }

        // TON TXID Submission Step
        if ($session && $session['step'] === 'awaiting_ton_txid') {
            self::handleTonTxidSubmission($pdo, $chatId, $fromId, $text, $session['data'] ?? []);
            return;
        }

        // Reseller Application Steps
        if ($session && str_starts_with($session['step'], 'reseller_apply_')) {
            self::handleResellerApplicationStep($pdo, $chatId, $fromId, $text, $session, $msg['from'] ?? []);
            return;
        }

        // Document Upload (SQL Database Restore from Admin)
        if (isset($msg['document']) && is_array($msg['document'])) {
            $doc = $msg['document'];
            $fileName = $doc['file_name'] ?? '';
            $fileId = $doc['file_id'] ?? '';
            $ctx = self::getContext($pdo);
            $adminId = (string)($ctx['admin_chat_id'] ?? Setting::get('telegram_admin_id', ''));

            $isAdmin = (!empty($adminId) && ($fromId === $adminId || $chatId === $adminId));
            $isSql = str_ends_with(strtolower($fileName), '.sql');

            if ($isAdmin && $isSql) {
                self::setSession($pdo, $fromId, 'confirm_sql_restore', [
                    'file_id' => $fileId,
                    'file_name' => $fileName,
                    'file_size' => $doc['file_size'] ?? 0
                ]);

                $fileSizeKb = round(($doc['file_size'] ?? 0) / 1024, 1);
                $confirmMsg = "⚠️ <b>درخواست بازگردانی پایگاه داده (Database Restore)</b>\n\n"
                            . "📄 فایل: <code>{$fileName}</code> ({$fileSizeKb} KB)\n"
                            . "👤 هویت: مدیریت سیستم تایید گردید.\n\n"
                            . "آیا مایلید تمام داده‌ها و تنظیمات با این فایل پشتیبان هماهنگ و بازگردانی شوند؟\n"
                            . "<i>نکته: رکوردهای موجود با رکوردهای این فایل بازنویسی و ادغام خواهند شد.</i>";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و بازگردانی دیتابیس', 'callback_data' => 'confirm_db_restore'],
                            ['text' => '❌ لغو عملیات', 'callback_data' => 'cancel_db_restore']
                        ]
                    ]
                ];

                TelegramBot::sendMessage($confirmMsg, $chatId, $keyboard, $ctx['bot_token']);
                return;
            } elseif ($isSql && !$isAdmin) {
                TelegramBot::sendMessage("⛔️ شما دسترسی لازم جهت بازگردانی دیتابیس را ندارید.", $chatId, null, $ctx['bot_token']);
                return;
            }
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

                    $ctx = self::getContext($pdo);
                    $adminId = $ctx['admin_chat_id'];
                    $botToken = $ctx['bot_token'];

                    TelegramBot::sendMessage("✅ <b>رسید پرداخت شما با موفقیت دریافت شد.</b>\nکد سفارش: <code>{$order['order_code']}</code>\nسفارش شما بررسی و مشخصات تحویل داده خواهد شد.", $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);

                    $adminCaption = "🔔 <b>رسید واریزی جدید (ربات {$ctx['brand_name']})</b>\n\n"
                                  . "👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . " (ID: <code>{$fromId}</code>)\n"
                                  . "📦 پلن: <b>{$order['plan_title']}</b>\n"
                                  . "💰 مبلغ: <b>" . number_format($order['amount']) . " تومان</b>\n";
                    if (!empty($order['coupon_code'])) {
                        $adminCaption .= "🎟 تخفیف: <code>{$order['coupon_code']}</code> (-" . number_format($order['discount_amount'] ?? 0) . " ت)\n";
                    }
                    $adminCaption .= "🔖 کد سفارش: <code>{$order['order_code']}</code>";

                    $adminKeyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ تایید و تحویل خودکار', 'callback_data' => 'admin_approve_' . $orderId],
                                ['text' => '❌ رد سفارش', 'callback_data' => 'admin_reject_' . $orderId]
                            ]
                        ]
                    ];

                    if (!empty($adminId)) {
                        TelegramBot::sendPhoto($fileId, $adminCaption, $adminId, $adminKeyboard, $botToken);
                    }

                    // Forward to Sales Topic Thread
                    $logChat = trim(Setting::get('bot_log_channel', ''));
                    $topicSales = (int)Setting::get('bot_topic_sales', 0);
                    if (!empty($logChat)) {
                        TelegramBot::sendPhoto($fileId, $adminCaption, $logChat, $adminKeyboard, $botToken, $topicSales ?: null);
                    } else {
                        TelegramBot::sendCategorizedReport('sales', $adminCaption, $adminKeyboard, $botToken);
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

                $ctx = self::getContext($pdo);
                $adminId = $ctx['admin_chat_id'];
                $botToken = $ctx['bot_token'];

                TelegramBot::sendMessage("✅ <b>اطلاعات پرداخت ثبت شد.</b>\nکد سفارش: <code>{$order['order_code']}</code>\nپس از تایید مدیر، اشتراک فعال خواهد شد.", $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);

                $adminNotice = "🔔 <b>ثبت فیش متنی (ربات {$ctx['brand_name']})</b>\n👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . " (ID: <code>{$fromId}</code>)\n💰 مبلغ: <b>" . number_format($order['amount']) . " تومان</b>\n";
                if (!empty($order['coupon_code'])) {
                    $adminNotice .= "🎟 تخفیف: <code>{$order['coupon_code']}</code> (-" . number_format($order['discount_amount'] ?? 0) . " ت)\n";
                }
                $adminNotice .= "📝 متن: <code>{$text}</code>\n🔖 کد: <code>{$order['order_code']}</code>";

                $adminKeyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و تحویل خودکار', 'callback_data' => 'admin_approve_' . $orderId],
                            ['text' => '❌ رد سفارش', 'callback_data' => 'admin_reject_' . $orderId]
                        ]
                    ]
                ];
                if (!empty($adminId)) {
                    TelegramBot::sendMessage($adminNotice, $adminId, $adminKeyboard, $botToken);
                }
                TelegramBot::sendCategorizedReport('sales', $adminNotice, $adminKeyboard, $botToken);
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

        $ctx = self::getContext($pdo);
        $resellerId = $ctx['reseller_id'];
        $botToken = $ctx['bot_token'];

        $plans = self::getPlansForReseller($pdo, $resellerId);
        $msg = "🔄 <b>تمدید اشتراک برای کاربر:</b> <code>{$client['username']}</code>\n\nلطفاً پلن مورد نظر برای تمدید را انتخاب نمایید:";

        $buttons = [];
        foreach ($plans as $p) {
            $priceFa = !empty($p['is_free']) ? 'رایگان' : (number_format($p['display_price']) . ' ت');
            $trafficVal = (float)$p['traffic_gb'];
            if ($trafficVal > 0 && $trafficVal < 1) {
                $trafficText = round($trafficVal * 1024) . 'MB';
            } elseif ($trafficVal >= 1) {
                $trafficText = ($trafficVal == (int)$trafficVal ? (int)$trafficVal : $trafficVal) . 'GB';
            } else {
                $trafficText = 'نامحدود';
            }
            $daysText = $p['duration_days'] . 'D';
            $ipText = !empty($p['ip_limit']) ? " | {$p['ip_limit']}U" : "";
            $btnText = "🔄 {$trafficText} | {$daysText}{$ipText} | {$priceFa}";
            $buttons[] = [['text' => $btnText, 'callback_data' => 'select_renew_plan_' . $client['id'] . '_' . $p['id']]];
        }
        $buttons[] = [['text' => '🔙 بازگشت', 'callback_data' => 'view_acc_' . $clientId]];

        $kb = ['inline_keyboard' => $buttons];
        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
        }
    }

    /**
     * Send Persian Main Menu
     */
    public static function sendMainMenu(PDO $pdo, string $chatId, ?string $fromId = null, string $name = '', ?int $messageId = null): void {
        $ctx = self::getContext($pdo);
        $brand = $ctx['brand_name'];
        $botToken = $ctx['bot_token'];
        $greeting = !empty($name) ? "سلام {$name} عزیز! " : "سلام! ";
        $welcomeCustom = $ctx['reseller']['welcome_message'] ?? null;
        $welcomeText = !empty($welcomeCustom) ? $welcomeCustom : "به ربات رسمی {$brand} خوش آمدید.\nجهت خرید اشتراک، تمدید، استعلام حجم یا ورود به حساب از دکمه‌های شیشه‌ای زیر استفاده نمایید:";

        $msg = "⚡️ <b>{$greeting}</b>\n\n{$welcomeText}";
        $inlineKb = self::getMainMenuInlineKeyboard($pdo, $fromId ?: $chatId);

        $edited = false;
        if ($messageId) {
            $edited = TelegramBot::editMessageText($msg, $chatId, $messageId, $inlineKb, $botToken);
        }
        if (!$edited) {
            TelegramBot::sendMessage($msg, $chatId, $inlineKb, $botToken);
            // Strictly send bottom ReplyKeyboardMarkup in Private chats only (Never in Groups/Supergroups)
            if (!str_starts_with($chatId, '-')) {
                TelegramBot::sendMessage("👇 همچنین کیبورد دسترسی سریع در پایین فعال است:", $chatId, self::getMainMenuReplyKeyboard(), $botToken);
            }
        }
    }

    /**
     * Show Plans Menu for Purchase with Category Drill-down & Clean 2-Column Buttons
     */
    private static function showPlansMenu(PDO $pdo, string $chatId, ?int $messageId = null, ?string $selectedCategory = null): void {
        $ctx = self::getContext($pdo);
        $resellerId = $ctx['reseller_id'];
        $botToken = $ctx['bot_token'];

        $allPlans = self::getPlansForReseller($pdo, $resellerId);

        if (empty($allPlans)) {
            $emptyText = "در حال حاضر پلنی برای فروش در این ربات فعال نیست.";
            $kb = ['inline_keyboard' => [[['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]]];
            if ($messageId) {
                TelegramBot::editMessageText($emptyText, $chatId, $messageId, $kb, $botToken);
            } else {
                TelegramBot::sendMessage($emptyText, $chatId, $kb, $botToken);
            }
            return;
        }

        // Get unique categories
        $categories = array_values(array_unique(array_map(fn($p) => $p['display_category'] ?: '۱ ماهه', $allPlans)));

        // If no category selected and more than 1 category exists, show Categories Menu first
        if ($selectedCategory === null && count($categories) > 1) {
            $msg = "🛒 <b>دسته‌بندی بسته‌های اشتراک ({$ctx['brand_name']})</b>\n\n"
                 . "لطفاً دوره یا نوع پلن مورد نظر خود را انتخاب فرمایید:";

            $catButtons = [];
            $row = [];
            foreach ($categories as $cat) {
                $icon = match($cat) {
                    '۱ ماهه' => '📅',
                    '۲ ماهه' => '📅',
                    '۳ ماهه' => '📅',
                    '۶ ماهه' => '📅',
                    'اقتصادی' => '⚡️',
                    'VIP تجاری' => '🚀',
                    default => '📦'
                };
                $row[] = [
                    'text' => "{$icon} {$cat}",
                    'callback_data' => 'cat_buy_' . md5($cat)
                ];
                if (count($row) === 2) {
                    $catButtons[] = $row;
                    $row = [];
                }
            }
            if (!empty($row)) {
                $catButtons[] = $row;
            }
            $catButtons[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']];

            $kb = ['inline_keyboard' => $catButtons];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
            }
            return;
        }

        // Filter plans by selected category if provided
        $filteredPlans = $allPlans;
        $titleCat = 'کلیه بسته‌ها';
        if ($selectedCategory !== null) {
            foreach ($categories as $c) {
                if (md5($c) === $selectedCategory || $c === $selectedCategory) {
                    $titleCat = $c;
                    $filteredPlans = array_values(array_filter($allPlans, fn($p) => ($p['display_category'] ?: '۱ ماهه') === $c));
                    break;
                }
            }
        }

        $msg = "🛒 <b>بسته‌های اشتراک — دسته: {$titleCat}</b>\n\n"
             . "لطفاً پلن مورد نظر خود را لمس نمایید:";

        $buttons = [];
        $row = [];
        foreach ($filteredPlans as $p) {
            $priceFa = !empty($p['is_free']) ? 'رایگان' : (number_format($p['display_price']) . ' ت');
            $trafficVal = (float)$p['traffic_gb'];
            if ($trafficVal > 0 && $trafficVal < 1) {
                $trafficText = round($trafficVal * 1024) . 'MB';
            } elseif ($trafficVal >= 1) {
                $trafficText = ($trafficVal == (int)$trafficVal ? (int)$trafficVal : $trafficVal) . 'GB';
            } else {
                $trafficText = 'نامحدود';
            }
            $daysText = $p['duration_days'] . 'D';
            $ipText = !empty($p['ip_limit']) ? " | {$p['ip_limit']}U" : "";
            $btnText = "📦 {$trafficText} | {$daysText}{$ipText} | {$priceFa}";
            $row[] = ['text' => $btnText, 'callback_data' => 'select_plan_' . $p['id']];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }

        if (count($categories) > 1) {
            $buttons[] = [['text' => '🔙 بازگشت به دسته‌بندی‌ها', 'callback_data' => 'menu_buy']];
        } else {
            $buttons[] = [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']];
        }

        $kb = ['inline_keyboard' => $buttons];
        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
        }
    }

    /**
     * Show Apps Download & Video Guides by Platform
     */
    private static function showAppsDownload(PDO $pdo, string $chatId, ?int $messageId = null, ?string $selectedPlatform = null): void {
        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];

        $platforms = [
            'android' => ['title' => '🤖 اندروید (Android)', 'header' => '🤖 نرم‌افزارهای اندروید'],
            'ios' => ['title' => '🍏 آیفون و آیپد (iOS)', 'header' => '🍏 نرم‌افزارهای آیفون و آیپد'],
            'windows' => ['title' => '💻 ویندوز (Windows)', 'header' => '💻 نرم‌افزارهای ویندوز'],
            'macos' => ['title' => '🍎 مک‌بوک (macOS)', 'header' => '🍎 نرم‌افزارهای مک‌بوک']
        ];

        if ($selectedPlatform === null) {
            $msg = "📱 <b>مرکز دانلود نرم‌افزارها و راهنمای اتصال ({$ctx['brand_name']})</b>\n\n"
                 . "جهت مشاهده اپلیکیشن‌های سازگار و آموزش ویدیویی، سیستم‌عامل خود را انتخاب فرمایید:";

            $kb = [
                'inline_keyboard' => [
                    [
                        ['text' => '🤖 اندروید (Android)', 'callback_data' => 'apps_plat_android'],
                        ['text' => '🍏 آیفون / آیپد (iOS)', 'callback_data' => 'apps_plat_ios']
                    ],
                    [
                        ['text' => '💻 ویندوز (Windows)', 'callback_data' => 'apps_plat_windows'],
                        ['text' => '🍎 مک‌بوک (macOS)', 'callback_data' => 'apps_plat_macos']
                    ],
                    [
                        ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']
                    ]
                ]
            ];

            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
            }
            return;
        }

        // Fetch apps from app_guides table
        $stmt = $pdo->prepare("SELECT * FROM app_guides WHERE platform = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$selectedPlatform]);
        $apps = $stmt->fetchAll();

        $platTitle = $platforms[$selectedPlatform]['header'] ?? 'نرم‌افزارها';
        $msg = "📱 <b>{$platTitle}</b>\n\n"
             . "یکی از نرم‌افزارهای زیر را نصب نموده و لینک ساب‌لینک خود را در آن وارد کنید:\n\n";

        $buttons = [];
        if (empty($apps)) {
            $msg .= "<i>در حال حاضر نرم‌افزاری برای این بخش ثبت نشده است.</i>\n";
        } else {
            foreach ($apps as $idx => $app) {
                $msg .= ($idx + 1) . ". <b>" . htmlspecialchars($app['app_name']) . "</b>\n";
                if (!empty($app['description'])) {
                    $msg .= "<i>" . htmlspecialchars($app['description']) . "</i>\n";
                }
                $msg .= "\n";

                $row = [['text' => "📥 دانلود {$app['app_name']}", 'url' => $app['download_url']]];
                if (!empty($app['guide_url'])) {
                    $row[] = ['text' => "🎥 آموزش", 'url' => $app['guide_url']];
                }
                $buttons[] = $row;
            }
        }

        $buttons[] = [['text' => '🔙 بازگشت به سیستم‌عامل‌ها', 'callback_data' => 'menu_apps']];

        $kb = ['inline_keyboard' => $buttons];
        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
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
     * Show Web Panel Credentials & Magic 1-Click Login Link (For Admin & Resellers)
     */
    public static function showPanelCredentials(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];
        $adminTgId = (string)($ctx['admin_chat_id'] ?? Setting::get('telegram_admin_id', ''));

        // 1. Check if user is Super Admin
        $user = null;
        if (!empty($adminTgId) && ((string)$fromId === $adminTgId || (string)$chatId === $adminTgId)) {
            $user = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Check if user is a Reseller linked by telegram_chat_id or telegram_admin_chat_id
        if (!$user) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (telegram_chat_id = ? OR telegram_admin_chat_id = ?) LIMIT 1");
            $stmt->execute([(string)$fromId, (string)$fromId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Check approved reseller applications matching this Telegram ID
        if (!$user) {
            $stmtApp = $pdo->prepare("SELECT * FROM reseller_applications WHERE user_tg_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
            $stmtApp->execute([(string)$fromId]);
            $app = $stmtApp->fetch(PDO::FETCH_ASSOC);
            if ($app && !empty($app['approved_user_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$app['approved_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?")->execute([(string)$fromId, $user['id']]);
                }
            }
        }

        // If not found, allow them to link account
        if (!$user) {
            $msg = "🔐 <b>ورود به پنل مدیریت و نمایندگی</b>\n\n"
                 . "حساب تلگرام شما هنوز به هیچ اکانت مدیریت یا نمایندگی در پنل متصل نشده است.\n\n"
                 . "🔹 اگر <b>مدیر کل</b> یا <b>نماینده رسمی</b> هستید، لطفاً با لمس دکمه زیر نام کاربری و رمز پنل خود را یک‌بار وارد فرمایید تا تلگرام شما به پنل متصل گردد.\n\n"
                 . "🔹 اگر مایل به دریافت پنل نمایندگی اختصاصی هستید، از دکمه «اخذ نمایندگی» اقدام فرمایید:";
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🔗 اتصال حساب پنل وب به تلگرام', 'callback_data' => 'link_panel_account']],
                    [['text' => '💼 درخواست اخذ نمایندگی', 'callback_data' => 'menu_reseller_apply']],
                    [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
                ]
            ];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
            }
            return;
        }

        // Generate Magic One-Time Login Token (Valid for 15 minutes)
        $magicToken = bin2hex(random_bytes(16));
        $magicExpires = date('Y-m-d H:i:s', time() + 900);
        $pdo->prepare("UPDATE users SET magic_login_token = ?, magic_login_expires = ? WHERE id = ?")
            ->execute([$magicToken, $magicExpires, $user['id']]);

        $loginUrl = Helpers::fullUrl('login');
        $magicUrl = Helpers::fullUrl('login?magic_token=' . $magicToken);

        $roleFa = ($user['role'] === 'admin') ? '👑 مدیر کل سامانه (Super Admin)' : '💼 نماینده رسمی سامانه (Reseller)';
        $tierFa = !empty($user['tier_level']) ? strtoupper($user['tier_level']) : 'استاندارد';
        $walletFa = number_format($user['wallet_balance'] ?? 0) . ' تومان';

        $stmtClients = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ? AND status = 'active'");
        $stmtClients->execute([$user['id']]);
        $activeCount = (int)$stmtClients->fetchColumn();

        $pwdNote = !empty($user['panel_password_display']) 
            ? "<code>{$user['panel_password_display']}</code>" 
            : "<i>محفوظ در سیستم (جهت تغییر روی دکمه زیر بزنید)</i>";

        $msg = "🔐 <b>اطلاعات ورود به پنل تحت وب ({$ctx['brand_name']})</b>\n\n"
             . "👤 <b>نام کاربری:</b> <code>{$user['username']}</code>\n"
             . "🔑 <b>کلمه عبور:</b> {$pwdNote}\n"
             . "🌐 <b>آدرس صفحه ورود دستی:</b>\n<code>{$loginUrl}</code>\n\n"
             . "📊 <b>مشخصات حساب:</b>\n"
             . "• نقش: <b>{$roleFa}</b>\n"
             . "• سطح همکاری: <b>{$tierFa}</b> (تخفیف: {$user['discount_percent']}%)\n"
             . "• موجودی کیف پول: <b>{$walletFa}</b>\n"
             . "• کاربران فعال شما: <b>{$activeCount} کاربر</b>\n\n"
             . "🚀 <i>با زدن دکمه «ورود مستقیم» زیر، بدون نیاز به وارد کردن کلمه عبور، مستقیماً وارد داشبورد پنل وب خود خواهید شد (اعتبار لینک: ۱۵ دقیقه):</i>";

        $kb = [
            'inline_keyboard' => [
                [['text' => '🚀 ورود مستقیم به پنل وب (یک کلیک)', 'url' => $magicUrl]],
                [
                    ['text' => '🔄 تغییر / تنظیم رمز عبور', 'callback_data' => 'change_panel_password']
                ],
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
        }
    }

    /**
     * Approve order and provision account (Multi-tenant Wallet & Delivery Aware)
     */
    public static function approveOrderAction(PDO $pdo, int $orderId, ?string $adminId = null): array {
        $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title, p.traffic_gb, p.duration_days, p.base_price, p.server_group 
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

        $resellerId = (int)($order['reseller_id'] ?? 1);
        $botToken = !empty($order['bot_token']) ? $order['bot_token'] : null;

        // Wholesale Wallet Deduction for Resellers
        if ($resellerId > 1) {
            $stmtReseller = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtReseller->execute([$resellerId]);
            $reseller = $stmtReseller->fetch();

            if ($reseller) {
                $tierInfo = Provisioner::getResellerTier($resellerId);
                $discount = (int)$tierInfo['discount'];
                $wholesaleCost = (int)round($order['base_price'] * (1 - ($discount / 100)));

                if ($reseller['wallet_balance'] < $wholesaleCost) {
                    $errNotice = "⚠️ <b>خطا در تایید سفارش #{$order['order_code']}</b>\n\nموجودی کیف پول شما کافی نیست!\nموجودی: " . number_format($reseller['wallet_balance']) . " تومان\nهزینه عمده پلن: " . number_format($wholesaleCost) . " تومان\nلطفاً ابتدا کیف پول خود را شارژ فرمایید.";
                    if (!empty($reseller['telegram_admin_chat_id'])) {
                        TelegramBot::sendMessage($errNotice, (string)$reseller['telegram_admin_chat_id'], null, $botToken);
                    }
                    return ['success' => false, 'error' => 'موجودی کیف پول نماینده نزد مدیریت کافی نیست (نیاز به: ' . number_format($wholesaleCost) . ' تومان)'];
                }

                // Deduct wholesale cost
                $newBal = $reseller['wallet_balance'] - $wholesaleCost;
                $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBal, $resellerId]);
                $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'plan_purchase', ?, ?, 'completed')")
                    ->execute([$resellerId, -$wholesaleCost, $newBal, "خرید خودکار از ربات برای سفارش #{$order['order_code']}", $order['order_code']]);

                // Notify reseller of profit & remaining balance
                $profit = max(0, $order['amount'] - $wholesaleCost);
                $resellerReceipt = "💳 <b>گزارش کسر هزینه عمده و سود سفارش #{$order['order_code']}</b>\n\n"
                                 . "💰 دریافتی از مشتری: " . number_format($order['amount']) . " تومان\n"
                                 . "📉 کسر از کیف پول شما: " . number_format($wholesaleCost) . " تومان\n"
                                 . "💵 سود خالص شما: <b>+" . number_format($profit) . " تومان</b>\n"
                                 . "💼 باقیمانده کیف پول: " . number_format($newBal) . " تومان";
                if (!empty($reseller['telegram_admin_chat_id'])) {
                    TelegramBot::sendMessage($resellerReceipt, (string)$reseller['telegram_admin_chat_id'], null, $botToken);
                }
            }
        }

        // Renewal order fulfillment
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
            ], $botToken);

            return [
                'success' => true,
                'username' => $client['username'],
                'password' => $client['password'] ?: '123456',
                'sub_url' => $subUrl
            ];
        }

        // New Purchase Order fulfillment via Provisioner
        $customNote = "خریداری شده توسط ربات تلگرام ID: " . $order['user_tg_id'];
        $prov = Provisioner::createClient(
            (int)$order['plan_id'], 
            $order['server_id'] ? (int)$order['server_id'] : null, 
            null, 
            null, 
            $resellerId, 
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

        TelegramBot::sendPhoto($qrUrl, $customerMsg, $order['user_tg_id'], $customerKeyboard, $botToken);

        // Referral Commission Distribution
        try {
            $buyerTg = $order['user_tg_id'] ?? null;
            if (!empty($buyerTg)) {
                $stmtBuyer = $pdo->prepare("SELECT referred_by FROM bot_users WHERE tg_id = ?");
                $stmtBuyer->execute([$buyerTg]);
                $inviterTg = $stmtBuyer->fetchColumn();

                if (!empty($inviterTg) && $inviterTg != $buyerTg) {
                    $commissionPercent = (int)Setting::get('referral_commission_percent', 10);
                    $commission = (int)round($order['amount'] * ($commissionPercent / 100));

                    if ($commission > 0) {
                        $pdo->prepare("UPDATE bot_users SET referral_balance = referral_balance + ? WHERE tg_id = ?")
                            ->execute([$commission, $inviterTg]);

                        $inviterMsg = "💰 <b>پاداش معرفی جدید!</b>\n\n"
                                    . "یکی از زیرمجموعه‌های شما خریدی به مبلغ <b>" . number_format($order['amount']) . " تومان</b> انجام داد.\n"
                                    . "💵 مبلغ <b>" . number_format($commission) . " تومان</b> ({$commissionPercent}٪ پورسانت) به کیف‌پول پاداش شما افزوده شد!\n\n"
                                    . "💡 جهت مشاهده موجودی به بخش «🤝 زیرمجموعه‌گیری و درآمد» مراجعه فرمایید.";
                        TelegramBot::sendMessage($inviterMsg, (string)$inviterTg, null, $botToken);
                    }
                }
            }
        } catch (Throwable $e) {}

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
            ], $order['bot_token'] ?? null);
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

        // Feature: Force Join Channel
        Setting::set('bot_force_join_channel', trim($_POST['bot_force_join_channel'] ?? ''));

        // Feature: Forum Supergroup & 11 Specialized Topic Thread IDs
        Setting::set('bot_log_channel', trim($_POST['bot_log_channel'] ?? ''));
        $allTopicKeys = ['nightly', 'backup_reseller', 'backup_all', 'notifications', 'services', 'sales', 'finance', 'trials', 'general', 'commissions', 'errors'];
        foreach ($allTopicKeys as $tk) {
            Setting::set("bot_topic_{$tk}", trim($_POST["bot_topic_{$tk}"] ?? ''));
        }

        // Feature: Custom Button Labels
        Setting::set('btn_buy_text', trim($_POST['btn_buy_text'] ?? '🛒 خرید اشتراک'));
        Setting::set('btn_renew_text', trim($_POST['btn_renew_text'] ?? '🔄 تمدید اشتراک'));
        Setting::set('btn_my_accounts_text', trim($_POST['btn_my_accounts_text'] ?? '👤 حساب‌های من'));
        Setting::set('btn_trial_text', trim($_POST['btn_trial_text'] ?? '🎁 تست رایگان'));
        Setting::set('btn_referral_text', trim($_POST['btn_referral_text'] ?? '🤝 کسب درآمد'));
        Setting::set('btn_apps_text', trim($_POST['btn_apps_text'] ?? '📱 دانلود و آموزش'));
        Setting::set('btn_support_text', trim($_POST['btn_support_text'] ?? '☎️ پشتیبانی'));
        Setting::set('btn_reseller_text', trim($_POST['btn_reseller_text'] ?? '💼 اخذ نمایندگی'));

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

        // Feature 2: Free Trial Settings
        Setting::set('trial_enabled', isset($_POST['trial_enabled']) ? '1' : '0');
        Setting::set('trial_duration_hours', (string)(int)($_POST['trial_duration_hours'] ?? 24));
        Setting::set('trial_traffic_gb', (string)(int)($_POST['trial_traffic_gb'] ?? 1));
        Setting::set('trial_traffic_mb', (string)(int)($_POST['trial_traffic_mb'] ?? 0));

        // Feature 4: Referral / Affiliate Settings
        Setting::set('referral_enabled', isset($_POST['referral_enabled']) ? '1' : '0');
        Setting::set('referral_commission_percent', (string)(int)($_POST['referral_commission_percent'] ?? 10));

        // Feature 6: Cryptocurrency / USDT TRC20 & TON Settings
        Setting::set('crypto_usdt_trc20_address', trim($_POST['crypto_usdt_trc20_address'] ?? ''));
        Setting::set('crypto_usdt_rate', (string)(int)($_POST['crypto_usdt_rate'] ?? 98000));
        Setting::set('crypto_ton_wallet_address', trim($_POST['crypto_ton_wallet_address'] ?? ''));
        Setting::set('crypto_ton_rate', (string)(int)($_POST['crypto_ton_rate'] ?? 380000));

        Helpers::flash('success', 'تنظیمات ربات تلگرام، جوین اجباری، موضوعات انجمن و دکمه‌ها با موفقیت ذخیره شد.');
        Helpers::redirect('settings/bot');
    }

    /**
     * Auto Create Forum Topics in Supergroup
     */
    public function autoCreateTopicsAction(): void {
        header('Content-Type: application/json');
        Auth::requireLogin();

        $logChat = trim(Setting::get('bot_log_channel', Setting::get('telegram_admin_id', '')));
        if (empty($logChat)) {
            echo json_encode([
                'success' => false,
                'message' => 'شناسه سوپرگروه لاگ سیستم (مثلاً -1001234567890) در فیلد بالا تنظیم نشده است.'
            ]);
            return;
        }

        $topicsToCreate = [
            'nightly' => ['name' => '🌙 گزارش شبانه', 'color' => 7322096, 'desc' => 'آمار کارکرد روزانه، مصرف کل ترافیک و درآمد ۲۴ ساعته'],
            'backup_reseller' => ['name' => '🤖 بکاپ ربات نماینده', 'color' => 16766590, 'desc' => 'نسخه‌های پشتیبان و تنظیمات دیتابیس اختصاصی ربات‌های نمایندگان'],
            'backup_all' => ['name' => '💾 بکاپ تمام ربات', 'color' => 53380, 'desc' => 'فایل‌های بکاپ کامل دیتابیس، سرورها و کل پنل'],
            'notifications' => ['name' => '📢 گزارش اطلاع‌رسانی‌ها', 'color' => 13341393, 'desc' => 'پیام‌های ارسالی همگانی و اعلانات مهم به کاربران'],
            'services' => ['name' => '🛍 گزارش خرید خدمات', 'color' => 16747520, 'desc' => 'خرید بسته‌های سروری، ارتقای کلاسترها و تغییرات پلن‌ها'],
            'sales' => ['name' => '🛒 گزارش‌های خرید', 'color' => 5793266, 'desc' => 'سفارشات جدید و پیش‌فاکتورها'],
            'finance' => ['name' => '💳 گزارش‌های مالی', 'color' => 9367492, 'desc' => 'واریزی کارت‌به‌کارت، پرداخت‌های تتر، شارژ کیف پول'],
            'trials' => ['name' => '🎁 گزارشات اکانت تست', 'color' => 16775294, 'desc' => 'درخواست‌ها و صدور آنی اکانت‌های تست رایگان'],
            'general' => ['name' => '📊 سایر گزارشات', 'color' => 10066329, 'desc' => 'لاگ‌های متفرقه سیستم و رویدادهای عمومی'],
            'commissions' => ['name' => '🤝 گزارشات پورسانت', 'color' => 65438, 'desc' => 'پاداش بازاریابی و کمیسیون زیرنمایندگان'],
            'errors' => ['name' => '⚠️ گزارش خطاها', 'color' => 16711680, 'desc' => 'خطاهای ارتباط با نود سرورها، فیل‌اور و سیستم'],
        ];

        $results = [];
        $createdCount = 0;

        foreach ($topicsToCreate as $key => $conf) {
            $existingId = Setting::get("bot_topic_{$key}", '');
            if (!empty($existingId)) {
                $results[$key] = [
                    'name' => $conf['name'],
                    'thread_id' => (int)$existingId,
                    'status' => 'already_exists'
                ];
                continue;
            }

            $res = TelegramBot::createForumTopic($logChat, $conf['name'], $conf['color']);
            if ($res !== null) {
                $threadId = (int)$res;
                Setting::set("bot_topic_{$key}", (string)$threadId);
                $results[$key] = [
                    'name' => $conf['name'],
                    'thread_id' => $threadId,
                    'status' => 'created'
                ];
                $createdCount++;

                TelegramBot::sendMessage(
                    "📌 <b>موضوع اختصاصی فعال شد: {$conf['name']}</b>\nاین تاپیک جهت دریافت گزارشات: {$conf['desc']} فعال گردید.",
                    $logChat,
                    null,
                    null,
                    $threadId
                );
            } else {
                $results[$key] = [
                    'name' => $conf['name'],
                    'status' => 'failed',
                    'error' => 'سوپرگروه قابلیت Topics را فعال نکرده یا ربات دسترسی Manage Topics ندارد.'
                ];
            }
        }

        $hasFail = false;
        foreach ($results as $r) {
            if (($r['status'] ?? '') === 'failed') $hasFail = true;
        }

        echo json_encode([
            'success' => !$hasFail || $createdCount > 0,
            'message' => $hasFail ? 'برخی تاپیک‌ها ساخته نشدند (بررسی کنید ربات ادمین سوپرگروه با دسترسی Manage Topics باشد).' : 'تمام موضوعات با موفقیت در سوپرگروه ایجاد و تنظیم شدند.',
            'results' => $results
        ]);
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

    public static function startResellerApplication(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        self::setSession($pdo, $fromId, 'reseller_apply_brand', []);

        $msg = "🤝 <b>درخواست دریافت پنل نمایندگی فروش</b>\n\n"
             . "به جمع همکاران ما خوش آمدید! با دریافت پنل اختصاصی شما قادر خواهید بود:\n"
             . "• ایجاد اشتراک با برند، نام و لوگوی شخصی خودتان\n"
             . "• اتصال ربات تلگرام اختصاصی با توکن دلخواه خودتان\n"
             . "• دریافت تخفیف عمده‌فروشی روی تمامی پلن‌ها\n"
             . "• دریافت وجه مستقیم از مشتریان به شماره کارت شخصی خودتان\n\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "📝 <b>مرحله ۱ از ۴:</b>\n"
             . "لطفاً <b>نام کامل یا نام برند تجاری</b> خود را ارسال فرمایید:\n"
             . "<i>(مثال: نوین نت یا علیرضا حسینی)</i>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '❌ انصراف و بازگشت به منو', 'callback_data' => 'menu_main']]
            ]
        ];

        $botToken = self::getContext($pdo)['bot_token'];
        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
        }
    }

    private static function handleResellerApplicationStep(PDO $pdo, string $chatId, string $fromId, string $text, array $session, array $fromUser): void {
        $step = $session['step'];
        $data = $session['data'] ?? [];
        $botToken = self::getContext($pdo)['bot_token'];

        if ($text === '❌ انصراف' || $text === '/cancel') {
            self::clearSession($pdo, $fromId);
            TelegramBot::sendMessage("❌ فرآیند درخواست نمایندگی لغو گردید.", $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);
            return;
        }

        if ($step === 'reseller_apply_brand') {
            if (mb_strlen($text) < 2) {
                TelegramBot::sendMessage("⚠️ لطفاً نام یا برند معتبری وارد فرمایید (حداقل ۲ حرف):", $chatId, null, $botToken);
                return;
            }
            $data['brand_name'] = $text;
            self::setSession($pdo, $fromId, 'reseller_apply_contact', $data);

            $msg = "📱 <b>مرحله ۲ از ۴:</b>\n\n"
                 . "لطفاً <b>شماره تماس همراه</b> یا <b>آیدی پشتیبانی تلگرام</b> خود را جهت هماهنگی ارسال نمایید:\n"
                 . "<i>(مثال: 09121234567 یا @MySupport)</i>";
            TelegramBot::sendMessage($msg, $chatId, null, $botToken);
            return;
        }

        if ($step === 'reseller_apply_contact') {
            if (mb_strlen($text) < 4) {
                TelegramBot::sendMessage("⚠️ لطفاً اطلاعات تماس معتبری وارد نمایید:", $chatId, null, $botToken);
                return;
            }
            $data['contact_info'] = $text;
            self::setSession($pdo, $fromId, 'reseller_apply_username', $data);

            $msg = "👤 <b>مرحله ۳ از ۴:</b>\n\n"
                 . "لطفاً <b>نام کاربری انگلیسی دلخواه</b> جهت ورود به پنل نمایندگی را وارد نمایید:\n"
                 . "<i>(فقط حروف و اعداد انگلیسی، بدون فاصله، مثلاً: novin_net)</i>";
            TelegramBot::sendMessage($msg, $chatId, null, $botToken);
            return;
        }

        if ($step === 'reseller_apply_username') {
            $pref = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $text)));
            if (strlen($pref) < 3) {
                TelegramBot::sendMessage("⚠️ نام کاربری باید حداقل ۳ کاراکتر انگلیسی باشد. لطفاً مجدداً ارسال نمایید:", $chatId, null, $botToken);
                return;
            }

            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$pref]);
            if ($chk->fetch()) {
                TelegramBot::sendMessage("⚠️ این نام کاربری (<code>{$pref}</code>) قبلاً در سیستم ثبت شده است. لطفاً نام دیگری انتخاب فرمایید:", $chatId, null, $botToken);
                return;
            }

            $data['preferred_username'] = $pref;
            self::setSession($pdo, $fromId, 'reseller_apply_sales', $data);

            $msg = "📊 <b>مرحله ۴ از ۴ (پایانی):</b>\n\n"
                 . "لطفاً <b>تعداد فروش تقریبی کلاینت در ماه</b> یا سابقه فعالیت خود را به صورت کوتاه بیان فرمایید:\n"
                 . "<i>(مثال: پیش‌بینی فروش ۵۰ الی ۱۰۰ اشتراک در ماه)</i>";
            TelegramBot::sendMessage($msg, $chatId, null, $botToken);
            return;
        }

        if ($step === 'reseller_apply_sales') {
            $data['estimated_sales'] = $text;
            self::clearSession($pdo, $fromId);

            $userTgName = trim(($fromUser['first_name'] ?? '') . ' ' . ($fromUser['last_name'] ?? ''));
            $userTgUsername = $fromUser['username'] ?? '';

            // Save in database
            $stmt = $pdo->prepare("INSERT INTO reseller_applications 
                (user_tg_id, user_tg_name, user_tg_username, brand_name, contact_info, preferred_username, estimated_sales, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $fromId,
                $userTgName,
                $userTgUsername,
                $data['brand_name'] ?? 'بی‌نام',
                $data['contact_info'] ?? '-',
                $data['preferred_username'] ?? ('reseller_' . rand(100, 999)),
                $data['estimated_sales'] ?? '-'
            ]);
            $appId = (int)$pdo->lastInsertId();

            // Confirmation to User
            $doneMsg = "✅ <b>درخواست نمایندگی شما با موفقیت ثبت شد!</b>\n\n"
                     . "🏷 <b>نام برند:</b> {$data['brand_name']}\n"
                     . "👤 <b>نام کاربری انتخابی:</b> <code>{$data['preferred_username']}</code>\n"
                     . "📞 <b>ارتباط:</b> {$data['contact_info']}\n"
                     . "📊 <b>پیش‌بینی فروش:</b> {$data['estimated_sales']}\n\n"
                     . "اطلاعات شما برای مدیریت سیستم ارسال گردید. به محض تایید، اطلاعات ورود به پنل از طریق همین ربات برای شما ارسال خواهد شد.\n\n"
                     . "از همراهی شما سپاسگزاریم 🙏";
            TelegramBot::sendMessage($doneMsg, $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);

            // Notification to Admin
            $adminChatId = self::getContext($pdo)['admin_chat_id'];
            if (!empty($adminChatId)) {
                $adminMsg = "🔔 <b>درخواست جدید اخذ پنل نمایندگی</b>\n\n"
                          . "🏷 <b>نام برند / متقاضی:</b> <b>{$data['brand_name']}</b>\n"
                          . "👤 <b>کاربر تلگرام:</b> @" . ($userTgUsername ?: 'ندارد') . " ({$userTgName})\n"
                          . "🆔 <b>آیدی عددی:</b> <code>{$fromId}</code>\n"
                          . "📞 <b>اطلاعات تماس:</b> {$data['contact_info']}\n"
                          . "🔑 <b>نام کاربری درخواستی:</b> <code>{$data['preferred_username']}</code>\n"
                          . "📊 <b>پیش‌بینی فروش:</b> {$data['estimated_sales']}\n\n"
                          . "جهت تعیین وضعیت این درخواست یکی از دکمه‌های زیر را لمس نمایید:";

                $adminKb = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ تایید و صدور آنی پنل', 'callback_data' => 'approve_reseller_' . $appId],
                            ['text' => '❌ رد درخواست', 'callback_data' => 'reject_reseller_' . $appId]
                        ]
                    ]
                ];
                TelegramBot::sendMessage($adminMsg, $adminChatId, $adminKb, $botToken);
                TelegramBot::sendCategorizedReport('users', $adminMsg, $adminKb, $botToken);
            }
        }
    }

    public static function approveResellerApplication(PDO $pdo, int $appId, string $adminChatId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM reseller_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            TelegramBot::sendMessage("⚠️ درخواست یافت نشد.", $adminChatId);
            return;
        }

        if ($app['status'] === 'approved') {
            TelegramBot::sendMessage("ℹ️ این درخواست قبلاً تایید شده است.", $adminChatId);
            return;
        }

        // Generate temporary password
        $tempPassword = 'res_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $username = strtolower($app['preferred_username']);

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $username .= '_' . rand(10, 99);
        }

        $apiToken = 'reseller_' . bin2hex(random_bytes(16));
        $brandName = !empty($app['brand_name']) ? $app['brand_name'] : $username;

        $stmtUser = $pdo->prepare("INSERT INTO users 
            (username, password_hash, role, full_name, brand_name, wallet_balance, credit_limit, discount_percent, allowed_groups, api_token, support_username) 
            VALUES (?, ?, 'reseller', ?, ?, 0, 0, 15, 'all', ?, ?)");
        $stmtUser->execute([$username, $passwordHash, $brandName, $brandName, $apiToken, $app['contact_info']]);
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

        // Notify User via Telegram
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

        $botToken = self::getContext($pdo)['bot_token'];
        TelegramBot::sendMessage($userMsg, $app['user_tg_id'], null, $botToken);

        $adminResult = "✅ <b>درخواست نمایندگی تایید و صادر شد!</b>\n\n"
                     . "🏷 <b>برند:</b> {$brandName}\n"
                     . "👤 <b>نام کاربری پنل:</b> <code>{$username}</code>\n"
                     . "🔑 <b>رمز عبور:</b> <code>{$tempPassword}</code>\n"
                     . "🆔 <b>تلگرام نماینده:</b> @" . ($app['user_tg_username'] ?: 'ندارد') . " (<code>{$app['user_tg_id']}</code>)\n"
                     . "🌐 اطلاعات ورود برای متقاضی ارسال گردید.";

        if ($messageId) {
            TelegramBot::editMessageText($adminResult, $adminChatId, $messageId, null, $botToken);
        } else {
            TelegramBot::sendMessage($adminResult, $adminChatId, null, $botToken);
        }
    }

    public static function rejectResellerApplication(PDO $pdo, int $appId, string $adminChatId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM reseller_applications WHERE id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) return;

        $pdo->prepare("UPDATE reseller_applications SET status = 'rejected' WHERE id = ?")->execute([$appId]);

        $botToken = self::getContext($pdo)['bot_token'];
        TelegramBot::sendMessage("همکار گرامی، با بررسی مشخصات متأسفانه در حال حاضر امکان پذیرش درخواست نمایندگی جدید میسر نمی‌باشد. در صورت نیاز با پشتیبانی در ارتباط باشید.", $app['user_tg_id'], null, $botToken);

        $adminResult = "❌ درخواست نمایندگی متقاضی @" . ($app['user_tg_username'] ?: $app['user_tg_id']) . " رد شد.";
        if ($messageId) {
            TelegramBot::editMessageText($adminResult, $adminChatId, $messageId, null, $botToken);
        } else {
            TelegramBot::sendMessage($adminResult, $adminChatId, null, $botToken);
        }
    }

    public function botUsers(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        // Auto-sync any Telegram users who placed orders into bot_users table
        try {
            $isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
            if ($isMysql) {
                $pdo->exec("INSERT INTO bot_users (reseller_id, tg_id, first_name, username, created_at, last_active_at)
                            SELECT reseller_id, user_tg_id, MAX(user_tg_name), MAX(user_tg_username), MIN(created_at), MAX(created_at)
                            FROM bot_orders
                            WHERE user_tg_id IS NOT NULL AND user_tg_id != ''
                            GROUP BY user_tg_id
                            ON DUPLICATE KEY UPDATE last_active_at = VALUES(last_active_at)");
            } else {
                $pdo->exec("INSERT OR IGNORE INTO bot_users (reseller_id, tg_id, first_name, username, created_at, last_active_at)
                            SELECT reseller_id, user_tg_id, MAX(user_tg_name), MAX(user_tg_username), MIN(created_at), MAX(created_at)
                            FROM bot_orders
                            WHERE user_tg_id IS NOT NULL AND user_tg_id != ''
                            GROUP BY user_tg_id");
            }
        } catch (Throwable $e) {}

        $search = trim($_GET['q'] ?? '');
        $where = "1=1";
        $params = [];
        if (!empty($search)) {
            $where .= " AND (tg_id LIKE ? OR first_name LIKE ? OR username LIKE ?)";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }

        $stmtUsers = $pdo->prepare("SELECT * FROM bot_users WHERE {$where} ORDER BY last_active_at DESC LIMIT 500");
        $stmtUsers->execute($params);
        $users = $stmtUsers->fetchAll();

        $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM bot_users")->fetchColumn();
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
        $stmtActive = $pdo->prepare("SELECT COUNT(*) FROM bot_users WHERE last_active_at >= ?");
        $stmtActive->execute([$oneDayAgo]);
        $active24hCount = (int)$stmtActive->fetchColumn();
        $hasUsernameCount = (int)$pdo->query("SELECT COUNT(*) FROM bot_users WHERE username IS NOT NULL AND username != ''")->fetchColumn();
        $totalOrdersCount = (int)$pdo->query("SELECT COUNT(*) FROM bot_orders")->fetchColumn();

        require __DIR__ . '/../views/settings/bot_users.php';
    }

    public function sendUserMessage(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/bot-users');
        }

        $userTgId = trim($_POST['user_tg_id'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($userTgId) || empty($message)) {
            Helpers::flash('error', 'شناسه کاربر و متن پیام الزامی است.');
            Helpers::redirect('settings/bot-users');
        }

        $sent = TelegramBot::sendMessage($message, $userTgId);
        if ($sent) {
            Helpers::flash('success', "پیام با موفقیت به کاربر {$userTgId} ارسال شد.");
        } else {
            Helpers::flash('error', "ارسال پیام به کاربر تلگرام ناموفق بود.");
        }

        Helpers::redirect('settings/bot-users');
    }

    public function broadcast(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/bot-users');
        }

        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            Helpers::flash('error', 'متن پیام همگانی الزامی است.');
            Helpers::redirect('settings/bot-users');
        }

        $pdo = Database::getConnection();
        $users = $pdo->query("SELECT tg_id FROM bot_users WHERE is_blocked = 0")->fetchAll();

        $successCount = 0;
        foreach ($users as $u) {
            $sent = TelegramBot::sendMessage($message, (string)$u['tg_id']);
            if ($sent) $successCount++;
            usleep(50000); // 50ms pause to respect Telegram limits
        }

        Helpers::logActivity('bot_broadcast', "ارسال پیام همگانی به {$successCount} کاربر تلگرام", 'system');
        Helpers::flash('success', "پیام همگانی با موفقیت برای {$successCount} نفر از اعضای ربات ارسال گردید.");
        Helpers::redirect('settings/bot-users');
    }

    /**
     * Free Trial Account Handler
     */
    public static function handleFreeTrialRequest(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        $ctx = self::getContext($pdo);
        $resellerId = (int)($ctx['reseller_id'] ?? 1);
        $botToken = $ctx['bot_token'];

        $res = Provisioner::createTrialAccount((string)$fromId, $resellerId);

        if (!$res['success']) {
            $msg = "⚠️ <b>امکان دریافت اکانت تست وجود ندارد:</b>\n\n"
                 . $res['error'] . "\n\n"
                 . "جهت اتصال دائمی و با بالاترین کیفیت، لطفاً از دکمه زیر اشتراک تهیه فرمایید:";
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🛒 خرید اشتراک جدید', 'callback_data' => 'menu_buy']],
                    [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
                ]
            ];
            if ($messageId) {
                TelegramBot::editMessageText($msg, $chatId, $messageId, $kb, $botToken);
            } else {
                TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
            }
            return;
        }

        $subUrl = $res['sub_url'];
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($subUrl);

        $msg = "🎁 <b>اکانت تست رایگان شما با موفقیت فعال گردید!</b>\n\n"
             . "👤 <b>نام کاربری:</b> <code>{$res['username']}</code>\n"
             . "🔑 <b>کلمه عبور:</b> <code>{$res['password']}</code>\n"
             . "📦 <b>حجم تست:</b> {$res['traffic_text']}\n"
             . "⏳ <b>مهلت تست:</b> {$res['hours']} ساعت\n"
             . "🌐 <b>سرور متصل:</b> {$res['server_name']}\n\n"
             . "🔗 <b>لینک اتصال ساب‌لینک هوشمند:</b>\n"
             . "<code>{$subUrl}</code>\n\n"
             . "📱 <i>برای اتصال، لینک بالا را در نرم‌افزارهای v2rayNG یا Streisand وارد فرمایید یا بارکد فوق را اسکن نمایید.</i>";

        $kb = [
            'inline_keyboard' => [
                [['text' => '🛒 خرید اشتراک کامل و پرسرعت', 'callback_data' => 'menu_buy']],
                [['text' => '📱 دانلود نرم‌افزارهای اتصال', 'callback_data' => 'menu_apps']],
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        $photoSent = TelegramBot::sendPhoto($qrUrl, $msg, $chatId, $kb, $botToken);
        if (!$photoSent) {
            TelegramBot::sendMessage($msg, $chatId, $kb, $botToken);
        }
    }

    /**
     * Show Referral & Affiliate Info
     */
    public static function showReferralInfo(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];

        $stmt = $pdo->prepare("SELECT * FROM bot_users WHERE tg_id = ?");
        $stmt->execute([$fromId]);
        $user = $stmt->fetch();

        $invites = (int)($user['referral_count'] ?? 0);
        $balance = (int)($user['referral_balance'] ?? 0);
        $commissionPercent = (int)Setting::get('referral_commission_percent', 10);

        $botUser = $ctx['bot_username'] ?: Setting::get('telegram_bot_username', 'ConnectixBot');
        $refLink = "https://t.me/{$botUser}?start=ref_{$fromId}";

        $msg = "🤝 <b>سامانه کسب درآمد و زیرمجموعه‌گیری ({$ctx['brand_name']})</b>\n\n"
             . "با معرفی دوستان خود، <b>{$commissionPercent}٪ از مبلغ تمامی خریدهای آن‌ها</b> را به عنوان پاداش نقدی در کیف‌پول خود دریافت نمایید!\n\n"
             . "🔗 <b>لینک دعوت اختصاصی شما:</b>\n"
             . "<code>{$refLink}</code>\n\n"
             . "📊 <b>آمار فعالیت شما:</b>\n"
             . "👥 تعداد دعوت‌شدگان: <b>{$invites} نفر</b>\n"
             . "💰 موجودی پاداش شما: <b>" . number_format($balance) . " تومان</b>\n\n"
             . "💡 <b>راهنما:</b> لینک بالا را کپی کرده و برای دوستانتان بفرستید. هر زمان یکی از دعوت‌شدگان خریدی ثبت کند، بلافاصله پورسانت آن به صورت خودکار به شما تعلق خواهد گرفت.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 به‌روزرسانی آمار', 'callback_data' => 'menu_referral'],
                    ['text' => '🔙 بازگشت به منو', 'callback_data' => 'menu_main']
                ]
            ]
        ];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
        }
    }

    /**
     * Record Referral Relationship
     */
    public static function handleReferralStart(PDO $pdo, string $newUserId, string $inviterTgId, array $userData): void {
        try {
            $stmt = $pdo->prepare("SELECT id, referred_by FROM bot_users WHERE tg_id = ?");
            $stmt->execute([$newUserId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                $pdo->prepare("INSERT INTO bot_users (tg_id, first_name, username, referred_by) VALUES (?, ?, ?, ?)")
                    ->execute([$newUserId, $userData['first_name'] ?? '', $userData['username'] ?? '', $inviterTgId]);
                
                $pdo->prepare("UPDATE bot_users SET referral_count = referral_count + 1 WHERE tg_id = ?")->execute([$inviterTgId]);

                $inviterNotice = "🎉 <b>یک کاربر جدید با لینک اختصاصی شما وارد ربات شد!</b>\n\n"
                               . "👤 نام: " . ($userData['first_name'] ?? 'ناشناس') . "\n"
                               . (!empty($userData['username']) ? "🆔 آیدی: @" . $userData['username'] . "\n" : "")
                               . "💡 با هر خریدی که این کاربر انجام دهد، ۱۰٪ از مبلغ به کیف‌پول شما واریز خواهد شد!";
                TelegramBot::sendMessage($inviterNotice, (string)$inviterTgId);
            } elseif (empty($existing['referred_by'])) {
                $pdo->prepare("UPDATE bot_users SET referred_by = ? WHERE tg_id = ?")->execute([$inviterTgId, $newUserId]);
                $pdo->prepare("UPDATE bot_users SET referral_count = referral_count + 1 WHERE tg_id = ?")->execute([$inviterTgId]);
            }
        } catch (Throwable $e) {}
    }

    /**
     * Crypto Tether (TRC20) Payment Step
     */
    public static function showCryptoPayment(PDO $pdo, string $chatId, string $fromId, int $orderId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            TelegramBot::sendMessage("❌ سفارش یافت نشد.", $chatId);
            return;
        }

        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];

        $usdtRate = (int)Setting::get('crypto_usdt_rate', 98000);
        if ($usdtRate <= 0) $usdtRate = 98000;
        $usdtAmount = number_format($order['amount'] / $usdtRate, 2);

        $trc20Wallet = Setting::get('crypto_usdt_trc20_address', 'TYDZSxdW3k9pqm5vWc1qV8tZ4bM7n8k9pL');

        self::setSession($pdo, $fromId, 'awaiting_crypto_txid', [
            'order_id' => $orderId,
            'usdt' => $usdtAmount,
            'wallet' => $trc20Wallet
        ]);

        $msg = "🪙 <b>پرداخت ارزی با تتر (USDT - شبکه TRC20)</b>\n\n"
             . "💰 <b>مبلغ فاکتور:</b> " . number_format($order['amount']) . " تومان\n"
             . "💵 <b>معادل تتری دقیق:</b> <b>{$usdtAmount} USDT</b>\n"
             . "🌐 <b>شبکه انتقال:</b> <code>Tron (TRC-20)</code>\n\n"
             . "📥 <b>آدرس کیف پول جهت واریز:</b>\n"
             . "<code>{$trc20Wallet}</code>\n\n"
             . "⚠️ <b>دستورالعمل تایید و تحویل:</b>\n"
             . "۱. دقیقاً مبلغ <b>{$usdtAmount} USDT</b> را به آدرس بالا منتقل نمایید.\n"
             . "۲. پس از انجام تراکنش، <b>کد رهگیری تراکنش (TXID / Transaction Hash)</b> را همین‌جا ارسال فرمایید.\n\n"
             . "<i>به محض ثبت هش، سفارش شما بررسی و اشتراک به طور خودکار تحویل خواهد شد.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
        }
    }

    /**
     * Handle Crypto TXID submission
     */
    public static function handleCryptoTxidSubmission(PDO $pdo, string $chatId, string $fromId, string $txid, array $sessionData): void {
        $orderId = (int)($sessionData['order_id'] ?? 0);
        $usdtAmount = $sessionData['usdt'] ?? '0.00';
        $wallet = $sessionData['wallet'] ?? '';

        $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            self::clearSession($pdo, $fromId);
            TelegramBot::sendMessage("❌ سفارش یافت نشد.", $chatId);
            return;
        }

        $resellerId = (int)($order['reseller_id'] ?? 1);
        $pdo->prepare("INSERT INTO crypto_payments (user_id, reseller_id, order_id, currency, network, expected_amount_usdt, toman_amount, wallet_address, tx_hash, status) 
                       VALUES (?, ?, ?, 'USDT', 'TRC20', ?, ?, ?, ?, 'pending')")
            ->execute([$resellerId, $resellerId, $orderId, $usdtAmount, $order['amount'], $wallet, $txid]);
        $cryptoId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE bot_orders SET payment_status = 'pending_approval' WHERE id = ?")->execute([$orderId]);
        self::clearSession($pdo, $fromId);

        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];
        $adminId = $ctx['admin_chat_id'];

        $confirmUser = "✅ <b>کد رهگیری تراکنش تتر شما با موفقیت ثبت شد!</b>\n\n"
                     . "🔖 <b>کد سفارش:</b> <code>{$order['order_code']}</code>\n"
                     . "🪙 <b>مبلغ:</b> <b>{$usdtAmount} USDT</b>\n"
                     . "🔗 <b>کد هش (TXID):</b>\n<code>{$txid}</code>\n\n"
                     . "<i>سفارش شما در حال تایید توسط پشتیبانی است و مشخصات اتصال فوراً برای شما ارسال خواهد شد.</i>";
        TelegramBot::sendMessage($confirmUser, $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);

        if (!empty($adminId)) {
            $adminCaption = "🪙 <b>واریز تتری جدید در انتظار تایید (ربات {$ctx['brand_name']})</b>\n\n"
                          . "👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . " (ID: <code>{$fromId}</code>)\n"
                          . "📦 پلن: <b>{$order['plan_title']}</b>\n"
                          . "💰 مبلغ: <b>{$usdtAmount} USDT</b> (" . number_format($order['amount']) . " تومان)\n"
                          . "🔗 هش تراکنش (TXID):\n<code>{$txid}</code>\n\n"
                          . "🔍 بررسی در ترون‌اسکن:\nhttps://tronscan.org/#/transaction/{$txid}";

            $adminKb = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید و فعال‌سازی سفارش', 'callback_data' => 'admin_crypto_approve_' . $cryptoId],
                        ['text' => '❌ رد تراکنش', 'callback_data' => 'admin_crypto_reject_' . $cryptoId]
                    ]
                ]
            ];
            TelegramBot::sendMessage($adminCaption, (string)$adminId, $adminKb, $botToken);
            TelegramBot::sendCategorizedReport('crypto', $adminCaption, $adminKb, $botToken);
        }
    }

    /**
     * Crypto TON Network Payment Step
     */
    public static function showTonPayment(PDO $pdo, string $chatId, string $fromId, int $orderId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM bot_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            TelegramBot::sendMessage("❌ سفارش یافت نشد.", $chatId);
            return;
        }

        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];

        $tonRate = (int)Setting::get('crypto_ton_rate', 380000);
        if ($tonRate <= 0) $tonRate = 380000;
        $tonAmount = round($order['amount'] / $tonRate, 3);
        $nanotons = (int)round($tonAmount * 1000000000);

        $tonWallet = Setting::get('crypto_ton_wallet_address', 'EQD4FPq-PRDieyQKkKZjmNu49pKypDryHyRMvzkBhzsJw6-h');
        $memo = $order['order_code'];

        self::setSession($pdo, $fromId, 'awaiting_ton_txid', [
            'order_id' => $orderId,
            'ton' => $tonAmount,
            'wallet' => $tonWallet,
            'memo' => $memo
        ]);

        $directTonLink = "ton://transfer/{$tonWallet}?amount={$nanotons}&text=" . urlencode($memo);

        $msg = "💎 <b>پرداخت ارزی با ارز دیجیتال تون (TON - ولت تلگرام)</b>\n\n"
             . "💰 <b>مبلغ فاکتور:</b> " . number_format($order['amount']) . " تومان\n"
             . "💎 <b>معادل دقیق TON:</b> <b>{$tonAmount} TON</b>\n"
             . "🌐 <b>شبکه:</b> <code>The Open Network (TON)</code>\n\n"
             . "📥 <b>آدرس کیف پول (Wallet):</b>\n"
             . "<code>{$tonWallet}</code>\n\n"
             . "📝 <b>کد شناسه پرداخت (Comment / Memo الزامی):</b>\n"
             . "<code>{$memo}</code>\n\n"
             . "⚠️ <b>دستورالعمل:</b>\n"
             . "۱. می‌توانید مستقیماً از دکمه «انتقال سریع با ولت تلگرام» استفاده کنید یا مبلغ را به آدرس فوق واریز نمایید.\n"
             . "۲. حتماً عبارت Comment/Memo را برابر <code>{$memo}</code> قرار دهید.\n"
             . "۳. پس از پرداخت، هش تراکنش یا عکس رسید را ارسال فرمایید.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🚀 انتقال سریع با ولت تلگرام (Tonkeeper / Wallet)', 'url' => $directTonLink]],
                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'menu_main']]
            ]
        ];

        if ($messageId) {
            TelegramBot::editMessageText($msg, $chatId, $messageId, $keyboard, $botToken);
        } else {
            TelegramBot::sendMessage($msg, $chatId, $keyboard, $botToken);
        }
    }

    public static function handleTonTxidSubmission(PDO $pdo, string $chatId, string $fromId, string $txid, array $sessionData): void {
        $orderId = (int)($sessionData['order_id'] ?? 0);
        $tonAmount = $sessionData['ton'] ?? '0.00';
        $wallet = $sessionData['wallet'] ?? '';

        $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            self::clearSession($pdo, $fromId);
            TelegramBot::sendMessage("❌ سفارش یافت نشد.", $chatId);
            return;
        }

        $resellerId = (int)($order['reseller_id'] ?? 1);
        $pdo->prepare("INSERT INTO crypto_payments (user_id, reseller_id, order_id, currency, network, expected_amount_usdt, toman_amount, wallet_address, tx_hash, status) 
                       VALUES (?, ?, ?, 'TON', 'TON', ?, ?, ?, ?, 'pending')")
            ->execute([$resellerId, $resellerId, $orderId, $tonAmount, $order['amount'], $wallet, $txid]);
        $cryptoId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE bot_orders SET payment_status = 'pending_approval' WHERE id = ?")->execute([$orderId]);
        self::clearSession($pdo, $fromId);

        $ctx = self::getContext($pdo);
        $botToken = $ctx['bot_token'];
        $adminId = $ctx['admin_chat_id'];

        $confirmUser = "✅ <b>اطلاعات پرداخت ارز TON با موفقیت ثبت شد!</b>\n\n"
                     . "🔖 <b>کد سفارش:</b> <code>{$order['order_code']}</code>\n"
                     . "💎 <b>مبلغ:</b> <b>{$tonAmount} TON</b>\n"
                     . "🔗 <b>کد هش / تراکنش:</b>\n<code>{$txid}</code>\n\n"
                     . "<i>سفارش شما در حال تایید است و اشتراک فوراً تحویل خواهد شد.</i>";
        TelegramBot::sendMessage($confirmUser, $chatId, self::getMainMenuInlineKeyboard($pdo, $fromId), $botToken);

        if (!empty($adminId)) {
            $adminCaption = "💎 <b>تراکنش جدید تون (TON) در انتظار تایید</b>\n\n"
                          . "👤 کاربر: @" . ($order['user_tg_username'] ?: 'ندارد') . " (ID: <code>{$fromId}</code>)\n"
                          . "📦 پلن: <b>{$order['plan_title']}</b>\n"
                          . "💎 مبلغ: <b>{$tonAmount} TON</b> (" . number_format($order['amount']) . " تومان)\n"
                          . "🔗 هش تراکنش (TXID):\n<code>{$txid}</code>\n\n"
                          . "🔍 بررسی در اکسپلورر تون‌اسکن:\nhttps://tonscan.org/tx/{$txid}";

            $adminKb = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تایید و فعال‌سازی سفارش', 'callback_data' => 'admin_crypto_approve_' . $cryptoId],
                        ['text' => '❌ رد تراکنش', 'callback_data' => 'admin_crypto_reject_' . $cryptoId]
                    ]
                ]
            ];
            TelegramBot::sendMessage($adminCaption, (string)$adminId, $adminKb, $botToken);
            TelegramBot::sendCategorizedReport('crypto', $adminCaption, $adminKb, $botToken);
        }
    }

    /**
     * Admin Crypto Approval
     */
    public static function approveCryptoPayment(PDO $pdo, int $cryptoId, string $adminChatId, ?int $messageId = null): void {
        $stmt = $pdo->prepare("SELECT * FROM crypto_payments WHERE id = ?");
        $stmt->execute([$cryptoId]);
        $crypto = $stmt->fetch();

        if (!$crypto || $crypto['status'] === 'confirmed') {
            if ($messageId) TelegramBot::editMessageText("⚠️ این پرداخت قبلاً تایید یا بررسی شده است.", $adminChatId, $messageId);
            return;
        }

        $pdo->prepare("UPDATE crypto_payments SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$cryptoId]);
        
        $orderRes = self::approveOrderAction($pdo, (int)$crypto['order_id'], $adminChatId);
        
        $msg = "✅ <b>تراکنش تتر #{$cryptoId} با موفقیت تایید شد!</b>\nسفارش مربوطه فعال و تحویل داده شد.";
        if ($messageId) {
            TelegramBot::editMessageText($msg, $adminChatId, $messageId);
        } else {
            TelegramBot::sendMessage($msg, $adminChatId);
        }
    }

    /**
     * Admin Crypto Rejection
     */
    public static function rejectCryptoPayment(PDO $pdo, int $cryptoId, string $adminChatId, ?int $messageId = null): void {
        $pdo->prepare("UPDATE crypto_payments SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$cryptoId]);
        if ($messageId) {
            TelegramBot::editMessageText("❌ <b>تراکنش تتر #{$cryptoId} توسط مدیر رد شد.</b>", $adminChatId, $messageId);
        }
    }
}

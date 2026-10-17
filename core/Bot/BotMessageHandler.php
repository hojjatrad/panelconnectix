<?php
/**
 * v4.0.53 BotMessageHandler - Handles text messages with toggle enforcement
 * Extracted from TelegramBotController::processMessage (900 lines)
 */
class BotMessageHandler {
    public static function handle(PDO $pdo, array $msg): void {
        $text = trim($msg['text'] ?? '');
        $fromId = (string)($msg['from']['id'] ?? '');
        $chatId = (string)($msg['chat']['id'] ?? $fromId);
        $ctx = BotContextService::getContext($pdo);
        $botToken = $ctx['bot_token'];

        if (empty($text)) return;

        // Check force join
        if (!BotContextService::checkForceJoin($pdo, $chatId, $fromId, $botToken)) {
            return;
        }

        // Record user
        BotContextService::recordBotUser($pdo, $msg['from'] ?? [], $ctx['reseller_id']);

        // Check session first
        $session = BotSessionService::getSession($pdo, $fromId);
        if ($session && !empty($session['step'])) {
            $step = $session['step'];
            // Reseller application flow
            if (str_starts_with($step, 'reseller_')) {
                try {
                    $ref = new ReflectionMethod('TelegramBotController', 'handleResellerApplicationStep');
                    $ref->setAccessible(true);
                    $ref->invoke(null, $pdo, $chatId, $fromId, $text, $session, $msg['from'] ?? []);
                    return;
                } catch (Throwable $e) {}
            }
            // Crypto TXID
            if ($step === 'await_crypto_txid' || $step === 'await_ton_txid') {
                try {
                    if ($step === 'await_crypto_txid') {
                        $ref = new ReflectionMethod('TelegramBotController', 'handleCryptoTxidSubmission');
                        $ref->setAccessible(true);
                        $ref->invoke(null, $pdo, $chatId, $fromId, $text, $session['data'] ?? []);
                    } else {
                        $ref = new ReflectionMethod('TelegramBotController', 'handleTonTxidSubmission');
                        $ref->setAccessible(true);
                        $ref->invoke(null, $pdo, $chatId, $fromId, $text, $session['data'] ?? []);
                    }
                    return;
                } catch (Throwable $e) {}
            }
            // AI Support
            if ($step === 'ai_support') {
                try {
                    require_once __DIR__ . '/../AiService.php';
                    $response = AiService::chat($pdo, $fromId, $text);
                    TelegramBot::sendMessage($response, $chatId, null, $botToken);
                    return;
                } catch (Throwable $e) {}
            }
        }

        // v4.0.52 FIX: Reply keyboard handlers for monitoring/financial/points/usage were missing
        // Now we enforce toggle and handle them
        $getText = function($key, $default) {
            try {
                return Setting::get($key, $default);
            } catch (Throwable $e) {
                return $default;
            }
        };

        $isEnabled = function($btn) {
            try {
                return Setting::get("btn_{$btn}_enabled", '1') == '1';
            } catch (Throwable $e) {
                return true;
            }
        };

        // Normalize text for comparison (remove emojis variation)
        $normalize = function($t) {
            return trim($t);
        };

        // Main menu text triggers - both emoji and plain
        $triggers = [
            'buy' => [$getText('btn_buy_text', '🛒 خرید اشتراک'), 'خرید', 'خرید اشتراک', '/buy'],
            'my_accounts' => [$getText('btn_my_accounts_text', '👤 اشتراک‌های من'), 'اشتراک‌های من', 'اکانت‌های من', '/myaccounts'],
            'trial' => [$getText('btn_trial_text', '🎁 تست رایگان'), 'تست رایگان', 'تست', '/trial'],
            'wallet' => [$getText('btn_wallet_text', '💰 کیف پول'), 'کیف پول', '/wallet'],
            'apps' => [$getText('btn_apps_text', '📱 دانلود اپ'), 'دانلود اپ', 'اپ', '/apps'],
            'support' => [$getText('btn_support_text', '💬 پشتیبانی'), 'پشتیبانی', '/support'],
            'monitoring' => [$getText('btn_monitoring_text', '📊 وضعیت سرورها LIVE'), 'وضعیت سرورها', 'مانیتورینگ', 'وضعیت', '/monitoring', '/status'],
            'financial' => [$getText('btn_financial_text', '💹 گزارش مالی'), 'گزارش مالی', 'مالی', '/financial'],
            'points' => [$getText('btn_points_text', '🏆 امتیاز و جایزه'), 'امتیاز', 'جایزه', '/points'],
            'usage' => [$getText('btn_usage_text', '📈 مصرف و تاریخچه'), 'مصرف', 'تاریخچه', 'مصرف و تاریخچه', '/usage'],
            'referral' => [$getText('btn_referral_text', '👥 دعوت دوستان'), 'دعوت دوستان', '/referral'],
            'wheel' => [$getText('btn_wheel_text', '🎡 گردونه شانس'), 'گردونه شانس', '/wheel'],
            'guide' => [$getText('btn_guide_text', '📚 راهنما'), 'راهنما', '/guide'],
            'panel' => [$getText('btn_panel_text', '🔑 اطلاعات پنل'), 'اطلاعات پنل', '/panel'],
            'reseller' => [$getText('btn_reseller_text', '🤝 درخواست نمایندگی'), 'نمایندگی', '/reseller'],
        ];

        foreach ($triggers as $btn => $texts) {
            foreach ($texts as $trigger) {
                if ($normalize($text) === $normalize($trigger) || str_contains($text, $trigger)) {
                    if (!$isEnabled($btn)) {
                        TelegramBot::sendMessage("⛔️ این بخش غیرفعال است", $chatId, null, $botToken);
                        return;
                    }
                    // Route to appropriate handler
                    switch ($btn) {
                        case 'buy':
                            BotSupportService::showPlansMenu($pdo, $chatId);
                            return;
                        case 'my_accounts':
                            BotAccountService::showMyAccounts($pdo, $chatId, $fromId);
                            return;
                        case 'trial':
                            TelegramBotController::handleFreeTrialRequest($pdo, $chatId, $fromId);
                            return;
                        case 'wallet':
                            BotWalletService::showWalletMenu($pdo, $chatId, $fromId);
                            return;
                        case 'apps':
                            BotSupportService::showAppsDownload($pdo, $chatId);
                            return;
                        case 'support':
                            try {
                                $ref = new ReflectionMethod('TelegramBotController', 'showSupportInfo');
                                $ref->setAccessible(true);
                                $ref->invoke(null, $chatId);
                            } catch (Throwable $e) {}
                            return;
                        case 'monitoring':
                            BotSupportService::showMonitoringForUser($pdo, $chatId, $fromId);
                            return;
                        case 'financial':
                            BotSupportService::showFinancialForUser($pdo, $chatId, $fromId);
                            return;
                        case 'points':
                            BotSupportService::showPointsForUser($pdo, $chatId, $fromId);
                            return;
                        case 'usage':
                            BotSupportService::showUsageForUser($pdo, $chatId, $fromId);
                            return;
                        case 'referral':
                            BotSupportService::showReferralInfo($pdo, $chatId, $fromId);
                            return;
                        case 'wheel':
                            BotSupportService::handleLuckyWheel($pdo, $chatId, $fromId);
                            return;
                        case 'panel':
                            BotSupportService::showPanelCredentials($pdo, $chatId, $fromId);
                            return;
                        case 'reseller':
                            BotResellerService::startResellerApplication($pdo, $chatId, $fromId);
                            return;
                        default:
                            break;
                    }
                }
            }
        }

        // Start command
        if (str_starts_with($text, '/start')) {
            $parts = explode(' ', $text);
            if (count($parts) > 1) {
                $param = trim($parts[1]);
                // Referral
                if (str_starts_with($param, 'ref_')) {
                    $inviterId = substr($param, 4);
                    TelegramBotController::handleReferralStart($pdo, $fromId, $inviterId, $msg['from'] ?? []);
                }
                // Deep link bind
                if (str_starts_with($param, 'bind_')) {
                    $token = substr($param, 5);
                    try {
                        $ref = new ReflectionMethod('TelegramBotController', 'handleDirectDeepLinkBind');
                        $ref->setAccessible(true);
                        $ref->invoke(null, $pdo, $chatId, $fromId, $token);
                        return;
                    } catch (Throwable $e) {}
                }
            }
            TelegramBotController::sendMainMenu($pdo, $chatId, $fromId, $msg['from']['first_name'] ?? '');
            return;
        }

        // For all other messages, delegate to original handler
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'processMessage');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $msg);
        } catch (Throwable $e) {
            TelegramBot::sendMessage("🤖 دستور نامشخص. از منوی زیر استفاده کنید:", $chatId, BotKeyboardService::getMainMenuReplyKeyboard(), $botToken);
        }
    }
}

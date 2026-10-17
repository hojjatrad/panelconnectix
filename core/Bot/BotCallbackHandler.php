<?php
/**
 * v4.0.53 BotCallbackHandler - Handles callback queries with toggle enforcement + ApcuCache
 * Extracted from TelegramBotController::processCallbackQuery (800 lines)
 * 
 * This is a refactored version that delegates to services and enforces button toggles
 */
class BotCallbackHandler {
    public static function handle(PDO $pdo, array $cb): void {
        $data = $cb['data'] ?? '';
        $fromId = (string)($cb['from']['id'] ?? '');
        $chatId = (string)($cb['message']['chat']['id'] ?? $fromId);
        $messageId = $cb['message']['message_id'] ?? null;
        $ctx = BotContextService::getContext($pdo);
        $botToken = $ctx['bot_token'];

        // v4.0.52 FIX: Enforce button toggle - if button disabled, show message and return
        $enforceToggle = function($btnName) use ($pdo, $chatId, $botToken, $cb) {
            $key = "btn_{$btnName}_enabled";
            $enabled = Setting::get($key, '1') == '1';
            if (!$enabled) {
                $cbId = $cb['id'] ?? '';
                if ($cbId) {
                    TelegramBot::answerCallbackQuery($cbId, "⛔️ این بخش غیرفعال است", true, $botToken);
                }
                return false;
            }
            return true;
        };

        // Check force join first (except for check_join itself)
        if ($data !== 'check_join') {
            if (!BotContextService::checkForceJoin($pdo, $chatId, $fromId, $botToken)) {
                return;
            }
        }

        // Route callbacks
        try {
            // v4.0.53 Use match-like routing with service delegation
            if ($data === 'check_join') {
                TelegramBot::deleteMessage($chatId, $messageId, $botToken);
                TelegramBotController::sendMainMenu($pdo, $chatId, $fromId, $cb['from']['first_name'] ?? '', null);
                TelegramBot::answerCallbackQuery($cb['id'] ?? '', "✅ عضویت تایید شد", false, $botToken);
                return;
            }

            if ($data === 'main_menu' || $data === 'back_to_main') {
                TelegramBotController::sendMainMenu($pdo, $chatId, $fromId, '', $messageId);
                return;
            }

            // Buy
            if ($data === 'buy_sub' || $data === 'buy') {
                if (!$enforceToggle('buy')) return;
                BotSupportService::showPlansMenu($pdo, $chatId, $messageId);
                return;
            }

            // My Accounts
            if ($data === 'my_accounts') {
                if (!$enforceToggle('my_accounts')) return;
                BotAccountService::showMyAccounts($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Trial
            if ($data === 'free_trial') {
                if (!$enforceToggle('trial')) return;
                TelegramBotController::handleFreeTrialRequest($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Wallet
            if ($data === 'wallet_menu') {
                if (!$enforceToggle('wallet')) return;
                BotWalletService::showWalletMenu($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Apps
            if ($data === 'download_apps' || $data === 'apps') {
                if (!$enforceToggle('apps')) return;
                BotSupportService::showAppsDownload($pdo, $chatId, $messageId);
                return;
            }

            // Support
            if ($data === 'support') {
                if (!$enforceToggle('support')) return;
                // Show support info
                try {
                    $ref = new ReflectionMethod('TelegramBotController', 'showSupportInfo');
                    $ref->setAccessible(true);
                    $ref->invoke(null, $chatId, $messageId);
                } catch (Throwable $e) {}
                return;
            }

            // Monitoring - v4.0.52 FIX: was missing toggle enforcement
            if ($data === 'monitoring') {
                if (!$enforceToggle('monitoring')) return;
                BotSupportService::showMonitoringForUser($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Financial - v4.0.52 FIX
            if ($data === 'financial') {
                if (!$enforceToggle('financial')) return;
                BotSupportService::showFinancialForUser($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Points - v4.0.52 FIX
            if ($data === 'points') {
                if (!$enforceToggle('points')) return;
                BotSupportService::showPointsForUser($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Usage - v4.0.52 FIX
            if ($data === 'usage') {
                if (!$enforceToggle('usage')) return;
                BotSupportService::showUsageForUser($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Referral
            if ($data === 'referral_info') {
                if (!$enforceToggle('referral')) return;
                BotSupportService::showReferralInfo($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Wheel
            if ($data === 'lucky_wheel') {
                if (!$enforceToggle('wheel')) return;
                BotSupportService::handleLuckyWheel($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Guide
            if ($data === 'guide') {
                if (!$enforceToggle('guide')) return;
                try {
                    $ref = new ReflectionMethod('TelegramBotController', 'showGuide');
                    $ref->setAccessible(true);
                    $ref->invoke(null, $pdo, $chatId, $messageId);
                } catch (Throwable $e) {
                    TelegramBot::sendMessage("📚 راهنمای استفاده به زودی...", $chatId);
                }
                return;
            }

            // Panel
            if ($data === 'panel_info') {
                if (!$enforceToggle('panel')) return;
                BotSupportService::showPanelCredentials($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // Reseller
            if ($data === 'reseller_apply') {
                if (!$enforceToggle('reseller')) return;
                BotResellerService::startResellerApplication($pdo, $chatId, $fromId, $messageId);
                return;
            }

            // For all other callbacks, delegate to original handler (to avoid breaking complex flows)
            // This ensures backward compatibility while we gradually migrate
            $ref = new ReflectionMethod('TelegramBotController', 'processCallbackQuery');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $cb);

        } catch (Throwable $e) {
            error_log("BotCallbackHandler error for $data: " . $e->getMessage());
            try {
                TelegramBot::answerCallbackQuery($cb['id'] ?? '', "⚠️ خطا: " . $e->getMessage(), true, $botToken);
            } catch (Throwable $e2) {}
        }
    }
}

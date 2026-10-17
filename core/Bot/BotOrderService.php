<?php
/**
 * v4.0.53 BotOrderService - Order creation, invoice rendering, approval
 * Extracted from TelegramBotController (lines 1289-1457, 3784-4100)
 */
class BotOrderService {
    public static function renderOrderInvoice(PDO $pdo, int $orderId, string $chatId, ?int $messageId = null, ?string $botToken = null): void {
        $stmt = $pdo->prepare("SELECT o.*, p.title, p.traffic_gb, p.duration_days, p.ip_limit, 
                                      COALESCE(rp.custom_title, p.title) as display_title
                               FROM bot_orders o
                               LEFT JOIN plans p ON o.plan_id = p.id
                               LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = o.reseller_id
                               WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;

        $ctx = BotContextService::getContext($pdo);
        $botToken = $botToken ?: $ctx['bot_token'];

        require_once __DIR__ . '/../JalaliDate.php';
        require_once __DIR__ . '/../BeautifulInvoice.php';

        $stmtUser = $pdo->prepare("SELECT wallet_balance, referral_balance FROM bot_users WHERE telegram_id = ?");
        $stmtUser->execute([$order['user_tg_id']]);
        $uRow = $stmtUser->fetch();
        $userWallet = (int)($uRow['wallet_balance'] ?? 0) + (int)($uRow['referral_balance'] ?? 0);

        $invoice = BeautifulInvoice::renderPreInvoice($order, $userWallet);
        $msg = $invoice['text'];

        $payCardEnabled = Setting::get('bot_pay_card_enabled','1') === '1';
        $payUsdtEnabled = Setting::get('bot_pay_usdt_enabled','1') === '1';
        $payTonEnabled = Setting::get('bot_pay_ton_enabled','1') === '1';
        $payWalletEnabled = Setting::get('bot_pay_wallet_enabled','1') === '1';

        $buttons = [];
        if ($payWalletEnabled) {
            if ($userWallet >= (int)$order['amount']) {
                $buttons[] = [
                    ['text' => '⚡️ پرداخت آنی از موجودی کیف‌پول (' . number_format($userWallet) . ' تومان)', 'callback_data' => 'pay_wallet_' . $orderId]
                ];
            } else {
                $deficit = (int)$order['amount'] - $userWallet;
                $buttons[] = [
                    ['text' => '💳 موجودی: ' . number_format($userWallet) . ' ت (کسری: ' . number_format($deficit) . ' ت)', 'callback_data' => 'charge_wallet_for_' . $orderId]
                ];
            }
        }

        $payRow = [];
        if ($payCardEnabled) $payRow[] = ['text' => '💳 کارت به کارت', 'callback_data' => 'pay_card_' . $orderId];
        if ($payUsdtEnabled) $payRow[] = ['text' => '🪙 پرداخت تتر (USDT)', 'callback_data' => 'pay_crypto_' . $orderId];
        if (!empty($payRow)) $buttons[] = $payRow;

        $tonAndCoupon = [];
        if ($payTonEnabled) $tonAndCoupon[] = ['text' => '💎 پرداخت با تون (TON)', 'callback_data' => 'pay_ton_' . $orderId];
        if (empty($order['coupon_code'])) {
            $tonAndCoupon[] = ['text' => '🎟 کد تخفیف', 'callback_data' => 'apply_coupon_' . $orderId];
        }
        if (!empty($tonAndCoupon)) $buttons[] = $tonAndCoupon;

        if (!$payCardEnabled && !$payUsdtEnabled && !$payTonEnabled && !$payWalletEnabled) {
            $msg .= "\n\n⚠️ <b>در حال حاضر هیچ روش پرداختی فعال نیست. لطفاً با پشتیبانی تماس بگیرید.</b>";
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

    public static function createOrder(PDO $pdo, array $cb, int $planId, string $orderType = 'new', ?int $clientId = null, ?int $messageId = null): void {
        // Delegate to original for now to avoid duplication risk - will be fully moved in v4.0.54
        // This wrapper allows ApcuCache invalidation
        if (class_exists('ApcuCache')) {
            $ctx = BotContextService::getContext($pdo);
            ApcuCache::delete("bot_plans_{$ctx['reseller_id']}_0");
            ApcuCache::delete("bot_plans_{$ctx['reseller_id']}_1");
        }
        // Call original via TelegramBotController if method exists as private we use reflection
        // For safety, we implement minimal logic here and let TelegramBotController handle if not fully migrated
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'createOrder');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $cb, $planId, $orderType, $clientId, $messageId);
        } catch (Throwable $e) {
            // Fallback: direct logic
            $fromId = (string)($cb['from']['id'] ?? '');
            $chatId = (string)($cb['message']['chat']['id'] ?? $fromId);
            $ctx = BotContextService::getContext($pdo);
            $resellerId = $ctx['reseller_id'];
            $botToken = $ctx['bot_token'];
            $stmt = $pdo->prepare("SELECT p.*, COALESCE(rp.custom_title, p.title) as display_title, COALESCE(rp.retail_price, p.base_price) as display_price FROM plans p LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ? WHERE p.id = ? AND p.is_active = 1");
            $stmt->execute([$resellerId, $planId]);
            $plan = $stmt->fetch();
            if (!$plan) {
                TelegramBot::sendMessage("⚠️ پلن انتخابی معتبر نیست.", $chatId, null, $botToken);
                return;
            }
            // Create order minimal
            $amount = (int)($plan['display_price'] ?? $plan['base_price'] ?? 0);
            $stmtIns = $pdo->prepare("INSERT INTO bot_orders (user_tg_id, reseller_id, plan_id, order_type, client_id, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmtIns->execute([$fromId, $resellerId, $planId, $orderType, $clientId, $amount]);
            $orderId = (int)$pdo->lastInsertId();
            self::renderOrderInvoice($pdo, $orderId, $chatId, $messageId, $botToken);
        }
    }

    public static function approveOrderAction(PDO $pdo, int $orderId, ?string $adminId = null): array {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'approveOrderAction');
            $ref->setAccessible(true);
            return $ref->invoke(null, $pdo, $orderId, $adminId);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function rejectOrderAction(PDO $pdo, int $orderId, ?string $adminId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'rejectOrderAction');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $orderId, $adminId);
        } catch (Throwable $e) {}
    }
}

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Provisioner.php';

class BillingController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();
        $userId = Auth::id();
        $isAdmin = Auth::isAdmin();

        $where = $isAdmin ? "1=1" : "user_id = " . intval($userId);
        $transactions = $pdo->query("SELECT t.*, u.username 
                                     FROM transactions t 
                                     LEFT JOIN users u ON t.user_id = u.id 
                                     WHERE $where 
                                     ORDER BY t.id DESC LIMIT 50")->fetchAll();

        // Gateway Settings
        $tetherWallet = Setting::get('crypto_usdt_trc20_address') ?: Setting::get('tether_wallet', 'TYDZSxdW3k9pqm5vWc1qV8tZ4bM7n8k9pL');
        $usdtRate = (int)(Setting::get('crypto_usdt_rate') ?: Setting::get('usdt_rate', '98000'));
        $bankCard = Setting::get('bank_card', '6037-9975-1234-5678');
        $bankCardOwner = Setting::get('bank_card_owner', 'مدیریت پنل کانکتیکس');

        require __DIR__ . '/../views/billing/index.php';
    }

    public function referrals(): void {
        Auth::requireLogin();
        require_once __DIR__ . '/../core/Referral.php';
        $userId = Auth::id();
        $stats = Referral::getStats($userId);
        require __DIR__ . '/../views/settings/referrals.php';
    }

    public function updateGateways(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('billing');
        }

        Setting::set('tether_wallet', trim($_POST['tether_wallet'] ?? ''));
        Setting::set('usdt_rate', (string)(int)($_POST['usdt_rate'] ?? 95000));
        Setting::set('bank_card', trim($_POST['bank_card'] ?? ''));
        Setting::set('bank_card_owner', trim($_POST['bank_card_owner'] ?? ''));

        Helpers::logActivity('billing_settings', 'به‌روزرسانی تنظیمات درگاه‌های شارژ کیف پول', 'system');
        Helpers::flash('success', 'تنظیمات درگاه‌های پرداخت با موفقیت ذخیره شدند.');
        Helpers::redirect('billing');
    }

    public function topup(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('billing');
        }

        $amount = (int)($_POST['amount'] ?? 0);
        $gateway = trim($_POST['gateway'] ?? 'zarinpal');
        $txid = trim($_POST['txid'] ?? '');
        $userId = Auth::id();

        if ($amount < 50000) {
            Helpers::flash('error', 'حداقل مبلغ شارژ ۵۰,۰۰۰ تومان است.');
            Helpers::redirect('billing');
        }

        $pdo = Database::getConnection();
        $user = Auth::user();
        $newBalance = $user['wallet_balance'] + $amount;
        $refId = ($gateway === 'crypto' ? 'USDT-' : 'ZP-') . rand(100000, 999999);
        if (!empty($txid)) {
            $refId .= ' (' . substr($txid, 0, 8) . '...)';
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES (?, ?, ?, 'wallet_topup', ?, ?, 'completed')")
                ->execute([$userId, $amount, $newBalance, "شارژ کیف پول از درگاه " . ($gateway === 'crypto' ? 'ارز دیجیتال (تتر TRC20)' : 'زرین‌پال / شتاب'), $refId]);
            $pdo->commit();

            Helpers::logActivity('wallet_topup', "شارژ کیف پول به مبلغ " . Helpers::formatMoney($amount) . " با کد پیگیری {$refId}", 'billing', $userId);

            // Send Telegram Notification
            require_once __DIR__ . '/../core/TelegramBot.php';
            $botText = "💳 <b>شارژ جدید کیف پول</b>\n"
                     . "👤 نماینده: <b>{$user['username']}</b>\n"
                     . "💵 مبلغ: <b>" . Helpers::formatMoney($amount) . "</b>\n"
                     . "📈 موجودی جدید: " . Helpers::formatMoney($newBalance) . "\n"
                     . "🔖 شماره پیگیری: <code>{$refId}</code>";
            TelegramBot::sendMessage($botText);

            Helpers::flash('success', "کیف پول شما به مبلغ " . Helpers::formatMoney($amount) . " با موفقیت شارژ شد.");
        } catch (Exception $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در افزایش اعتبار: ' . $e->getMessage());
        }

        Helpers::redirect('billing');
    }
}

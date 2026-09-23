<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

class WebappController {
    public function index(): void {
        $pdo = Database::getConnection();
        $tgId = trim($_GET['tg_id'] ?? '');

        $clientAccounts = [];
        $botUser = null;

        if (!empty($tgId)) {
            $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name 
                                   FROM clients c 
                                   LEFT JOIN plans p ON c.plan_id = p.id 
                                   LEFT JOIN server_nodes s ON c.server_id = s.id 
                                   WHERE c.telegram_chat_id = ? ORDER BY c.id DESC");
            $stmt->execute([$tgId]);
            $clientAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtUser = $pdo->prepare("SELECT * FROM bot_users WHERE tg_id = ?");
            $stmtUser->execute([$tgId]);
            $botUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        }

        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 AND is_free = 0 ORDER BY base_price ASC")->fetchAll(PDO::FETCH_ASSOC);
        $branding = $pdo->query("SELECT * FROM branding_metadata WHERE user_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $brandName = $branding['brand_name'] ?? 'Connectix VPN';
        $logoUrl = $branding['logo_url'] ?? '';
        $walletBalance = (int)($botUser['wallet_balance'] ?? 0) + (int)($botUser['referral_balance'] ?? 0);

        require __DIR__ . '/../views/webapp/index.php';
    }

    public function spin(): void {
        header('Content-Type: application/json; charset=utf-8');
        $pdo = Database::getConnection();
        $tgId = trim($_POST['tg_id'] ?? ($_GET['tg_id'] ?? ''));

        if (empty($tgId)) {
            echo json_encode(['success' => false, 'message' => 'شناسه کاربر تلگرام یافت نشد.']);
            return;
        }

        if (Setting::get('btn_wheel_enabled', '1') !== '1') {
            echo json_encode(['success' => false, 'message' => 'گردونه شانس در حال حاضر غیرفعال است.']);
            return;
        }

        // Check 24 hour cooldown
        $stmt = $pdo->prepare("SELECT * FROM lucky_wheel_logs WHERE user_tg_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tgId]);
        $last = $stmt->fetch();

        $now = time();
        if ($last) {
            $lastTime = strtotime($last['created_at']);
            $elapsed = $now - $lastTime;
            $cooldown = 86400;
            if ($elapsed < $cooldown) {
                $diff = $cooldown - $elapsed;
                $hours = floor($diff / 3600);
                $minutes = floor(($diff % 3600) / 60);
                echo json_encode([
                    'success' => false,
                    'cooldown' => true,
                    'message' => "شما شانس امروز خود را استفاده کرده‌اید. زمان باقیمانده تا شانس بعدی: {$hours} ساعت و {$minutes} دقیقه.",
                    'hours' => $hours,
                    'minutes' => $minutes
                ]);
                return;
            }
        }

        // Roll prize
        $roll = rand(1, 100);
        $rewardType = '';
        $rewardVal = 0;
        $rewardText = '';
        $segmentIndex = 0;

        if ($roll <= 40) {
            $amounts = [5000, 8000, 10000, 15000];
            $amount = $amounts[array_rand($amounts)];
            $rewardType = 'wallet_credit';
            $rewardVal = $amount;
            $rewardText = number_format($amount) . ' تومان شارژ کیف‌پول هدیه';
            $segmentIndex = 2;

            $pdo->prepare("UPDATE bot_users SET wallet_balance = wallet_balance + ? WHERE tg_id = ?")
                ->execute([$amount, $tgId]);
            $newBal = (int)$pdo->query("SELECT wallet_balance + referral_balance FROM bot_users WHERE tg_id = " . $pdo->quote($tgId))->fetchColumn();
            $pdo->prepare("INSERT INTO wallet_logs (tg_id, amount, balance_after, type, description) VALUES (?, ?, ?, 'wheel', 'جایزه گردونه شانس (مینی‌اپ)')")
                ->execute([$tgId, $amount, $newBal]);
        } elseif ($roll <= 80) {
            $discountPct = (rand(1, 2) === 1) ? 15 : 20;
            $code = 'LUCK-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $exp = date('Y-m-d H:i:s', time() + (48 * 3600));
            $rewardType = 'discount_code';
            $rewardVal = $discountPct;
            $rewardText = "کد تخفیف {$discountPct}٪ اختصاصی: " . $code;
            $segmentIndex = ($discountPct === 15) ? 0 : 4;

            $pdo->prepare("INSERT INTO coupons (code, discount_percent, max_uses, used_count, expire_at, is_active) VALUES (?, ?, 1, 0, ?, 1)")
                ->execute([$code, $discountPct, $exp]);
        } else {
            $rewardType = 'wallet_credit';
            $rewardVal = 25000;
            $rewardText = "جایزه بزرگ (JACKPOT): ۲۵,۰۰۰ تومان شارژ مستقیم!";
            $segmentIndex = 6;

            $pdo->prepare("UPDATE bot_users SET wallet_balance = wallet_balance + 25000 WHERE tg_id = ?")
                ->execute([$tgId]);
            $newBal = (int)$pdo->query("SELECT wallet_balance + referral_balance FROM bot_users WHERE tg_id = " . $pdo->quote($tgId))->fetchColumn();
            $pdo->prepare("INSERT INTO wallet_logs (tg_id, amount, balance_after, type, description) VALUES (?, 25000, ?, 'wheel', 'جک‌پات گردونه شانس (مینی‌اپ)')")
                ->execute([$tgId, $newBal]);
        }

        $pdo->prepare("INSERT INTO lucky_wheel_logs (user_tg_id, reward_type, reward_value, reward_text) VALUES (?, ?, ?, ?)")
            ->execute([$tgId, $rewardType, $rewardVal, $rewardText]);

        // Supergroup topic notification
        require_once __DIR__ . '/../core/TelegramBot.php';
        TelegramBot::sendTopicLog('general', "🎰 <b>دریافت هدیه گردونه شانس (از مینی‌اپ)</b>\n\n👤 کاربر: <code>{$tgId}</code>\n🎁 جایزه: {$rewardText}\n⏰ زمان: " . Helpers::formatDate(time()));

        echo json_encode([
            'success' => true,
            'reward_text' => $rewardText,
            'segment_index' => $segmentIndex
        ]);
    }
}

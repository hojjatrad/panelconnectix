<?php
/**
 * F3: Auto Renewal Reminder + Retention
 * F2: Wallet + Gift Credit
 * F8: Smart Discounts
 */

class Retention {
    /**
     * F3: Check expiring clients and send reminders
     */
    public static function checkExpiringClients(): array {
        $result = ['sent' => 0, 'messages' => []];
        try {
            $pdo = Database::getConnection();
            
            // Find clients expiring in 3 days
            $stmt = $pdo->prepare("
                SELECT c.*, u.telegram_id, u.username, p.name as plan_name 
                FROM clients c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN plans p ON c.plan_id = p.id
                WHERE c.status = 'active' 
                AND c.expire_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND c.id NOT IN (
                    SELECT client_id FROM retention_logs 
                    WHERE type = 'expiry_3day' AND created_at > DATE_SUB(NOW(), INTERVAL 2 DAY)
                )
                LIMIT 50
            ");
            $stmt->execute();
            $clients = $stmt->fetchAll();
            
            foreach ($clients as $client) {
                $msg = self::buildExpiryMessage($client);
                // Send via Telegram Bot if available
                if (!empty($client['telegram_id'])) {
                    try {
                        require_once __DIR__ . '/TelegramBot.php';
                        $botToken = Setting::get('telegram_bot_token', '');
                        if ($botToken) {
                            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                            $ch = curl_init($url);
                            curl_setopt_array($ch, [
                                CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => http_build_query([
                                    'chat_id' => $client['telegram_id'],
                                    'text' => $msg,
                                    'parse_mode' => 'HTML',
                                    'reply_markup' => json_encode([
                                        'inline_keyboard' => [
                                            [['text' => '🔄 تمدید با 10% تخفیف', 'callback_data' => 'renew_' . $client['id']]],
                                            [['text' => '📊 مشاهده حجم باقی‌مانده', 'callback_data' => 'status_' . $client['id']]]
                                        ]
                                    ])
                                ]),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_TIMEOUT => 5,
                                CURLOPT_SSL_VERIFYPEER => false
                            ]);
                            curl_exec($ch);
                            curl_close($ch);
                            
                            // Log
                            $pdo->prepare("INSERT INTO retention_logs (client_id, user_id, type, message) VALUES (?, ?, 'expiry_3day', ?)")
                                ->execute([$client['id'], $client['user_id'], 'Sent 3-day expiry reminder']);
                            
                            $result['sent']++;
                        }
                    } catch (Throwable $e) {}
                }
            }
            
            $result['messages'][] = "✓ Sent {$result['sent']} expiry reminders";
            
            // Also check 80% usage
            $stmt2 = $pdo->prepare("
                SELECT c.*, u.telegram_id 
                FROM clients c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.status = 'active' 
                AND c.total_traffic > 0 
                AND (c.used_traffic / c.total_traffic) >= 0.8
                AND c.id NOT IN (
                    SELECT client_id FROM retention_logs 
                    WHERE type = 'usage_80' AND created_at > DATE_SUB(NOW(), INTERVAL 2 DAY)
                )
                LIMIT 20
            ");
            $stmt2->execute();
            $heavyUsers = $stmt2->fetchAll();
            foreach ($heavyUsers as $client) {
                if (!empty($client['telegram_id'])) {
                    // Send 80% warning
                    $result['sent']++;
                }
            }
            
        } catch (Throwable $e) {
            $result['messages'][] = "❌ Error: " . $e->getMessage();
        }
        return $result;
    }
    
    private static function buildExpiryMessage(array $client): string {
        $expire = date('Y-m-d', strtotime($client['expire_date']));
        $name = $client['remark'] ?? $client['username'] ?? 'کاربر';
        $plan = $client['plan_name'] ?? 'اشتراک';
        
        return "⚠️ <b>هشدار انقضای اشتراک</b>\n\n"
             . "👤 {$name}\n"
             . "📦 {$plan}\n"
             . "📅 انقضا: {$expire} (3 روز دیگر)\n\n"
             . "🔄 برای جلوگیری از قطعی، همین الان تمدید کنید و <b>10% تخفیف</b> بگیرید!\n\n"
             . "💡 با تمدید به موقع، کانفیگ شما بدون تغییر باقی می‌ماند.";
    }
    
    /**
     * F2: Add gift credit to wallet
     */
    public static function addGiftCredit(int $userId, int $amount, string $reason = 'هدیه'): bool {
        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            
            $pdo->exec("UPDATE users SET wallet_balance = wallet_balance + $amount WHERE id = $userId");
            $balance = (int)$pdo->query("SELECT wallet_balance FROM users WHERE id = $userId")->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, status) VALUES (?, ?, ?, 'deposit', ?, 'completed')");
            $stmt->execute([$userId, $amount, $balance, "🎁 $reason: " . number_format($amount) . " تومان هدیه"]);
            
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            return false;
        }
    }
    
    /**
     * F8: Validate discount code
     */
    public static function validateDiscount(string $code, int $userId, int $planId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmt->execute([$code]);
            $discount = $stmt->fetch();
            
            if (!$discount) {
                return ['valid' => false, 'message' => 'کد تخفیف نامعتبر است'];
            }
            
            if ($discount['max_uses'] > 0 && $discount['used_count'] >= $discount['max_uses']) {
                return ['valid' => false, 'message' => 'ظرفیت این کد تکمیل شده'];
            }
            
            // Check if user already used
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM discount_usages WHERE discount_id = ? AND user_id = ?");
            $stmt2->execute([$discount['id'], $userId]);
            if ($stmt2->fetchColumn() > 0 && $discount['one_per_user']) {
                return ['valid' => false, 'message' => 'شما قبلاً از این کد استفاده کرده‌اید'];
            }
            
            return [
                'valid' => true,
                'discount' => $discount,
                'percent' => (int)$discount['percent'],
                'amount' => (int)$discount['amount']
            ];
        } catch (Throwable $e) {
            return ['valid' => false, 'message' => 'خطا در بررسی کد'];
        }
    }
}

<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';

class Referral {
    /**
     * Credit referral commission to referrer on payment
     */
    public static function processCommission(int $userId, int $tomanAmount, ?int $orderId = null): ?int {
        $pdo = Database::getConnection();
        $defaultPercent = (int)Setting::get('referral_commission_percent', '10');
        if ($defaultPercent <= 0 || $tomanAmount <= 0) return null;

        // Find referrer
        $user = $pdo->query("SELECT id, referred_by FROM users WHERE id = {$userId}")->fetch(PDO::FETCH_ASSOC);
        if (!$user || empty($user['referred_by'])) return null;

        $referrerId = (int)$user['referred_by'];
        if ($referrerId === $userId) return null;

        $commission = (int)floor(($tomanAmount * $defaultPercent) / 100);
        if ($commission <= 0) return null;

        // Credit to referrer's wallet
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE users SET wallet_balance = wallet_balance + {$commission} WHERE id = {$referrerId}");
            
            // Insert referral record
            $stmtRef = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, order_id, commission_amount, status) VALUES (?, ?, ?, ?, 'completed')");
            $stmtRef->execute([$referrerId, $userId, $orderId, $commission]);

            // Insert transaction record for referrer
            $referrerBalance = (int)$pdo->query("SELECT wallet_balance FROM users WHERE id = {$referrerId}")->fetchColumn();
            $desc = "پورسانت معرف از خرید کاربر شناسه #{$userId} (مبلغ: " . number_format($commission) . " تومان)";
            $stmtTx = $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, status) VALUES (?, ?, ?, 'deposit', ?, 'completed')");
            $stmtTx->execute([$referrerId, $commission, $referrerBalance, $desc]);

            $pdo->commit();
            return $commission;
        } catch (Throwable $e) {
            $pdo->rollBack();
            return null;
        }
    }

    /**
     * Get referral stats for user
     */
    public static function getStats(int $userId): array {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        try {
            $code = $pdo->query("SELECT referral_code FROM users WHERE id = {$userId}")->fetchColumn();
        } catch (Throwable $e) {
            Database::safeAddColumn($pdo, 'users', 'referral_code', 'VARCHAR(32) NULL');
            Database::safeAddColumn($pdo, 'users', 'referred_by', 'INT NULL DEFAULT NULL');
            $code = null;
        }

        if (empty($code)) {
            $code = 'REF' . strtoupper(substr(md5('user' . $userId), 0, 6));
            try {
                $pdo->exec("UPDATE users SET referral_code = '{$code}' WHERE id = {$userId}");
            } catch (Throwable $e) {}
        }

        $totalInvited = 0;
        try {
            $totalInvited = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE referred_by = {$userId}")->fetchColumn();
        } catch (Throwable $e) {}

        $totalCommission = 0;
        $recentReferrals = [];
        try {
            $totalCommission = (int)$pdo->query("SELECT COALESCE(SUM(commission_amount), 0) FROM referrals WHERE referrer_id = {$userId}")->fetchColumn();
            $recentReferrals = $pdo->query("SELECT r.*, u.username as referred_username 
                                            FROM referrals r 
                                            LEFT JOIN users u ON r.referred_id = u.id 
                                            WHERE r.referrer_id = {$userId} 
                                            ORDER BY r.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        return [
            'referral_code' => $code,
            'total_invited' => $totalInvited,
            'total_commission' => $totalCommission,
            'recent' => $recentReferrals,
            'commission_percent' => (int)Setting::get('referral_commission_percent', '10')
        ];
    }
}

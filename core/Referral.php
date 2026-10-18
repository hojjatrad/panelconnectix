<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';

class Referral {
    /**
     * Credit referral commission to referrer on payment - v4.0.71 FIXED SQLi
     */
    public static function processCommission(int $userId, int $tomanAmount, ?int $orderId = null): ?int {
        $pdo = Database::getConnection();
        $defaultPercent = (int)Setting::get('referral_commission_percent', '10');
        if ($defaultPercent <= 0 || $tomanAmount <= 0) return null;

        // v4.0.71 FIX: Use prepared statements instead of direct interpolation
        $stmt = $pdo->prepare("SELECT id, referred_by FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || empty($user['referred_by'])) return null;

        $referrerId = (int)$user['referred_by'];
        if ($referrerId === $userId) return null;

        $commission = (int)floor(($tomanAmount * $defaultPercent) / 100);
        if ($commission <= 0) return null;

        // Credit to referrer's wallet
        $pdo->beginTransaction();
        try {
            $stmtUp = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmtUp->execute([$commission, $referrerId]);
            
            // Insert referral record
            $stmtRef = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, order_id, commission_amount, status) VALUES (?, ?, ?, ?, 'completed')");
            $stmtRef->execute([$referrerId, $userId, $orderId, $commission]);

            // Insert transaction record for referrer
            $stmtBal = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
            $stmtBal->execute([$referrerId]);
            $referrerBalance = (int)$stmtBal->fetchColumn();
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
     * Get referral stats for user - v4.0.71 FIXED SQLi
     */
    public static function getStats(int $userId): array {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        try {
            $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $code = $stmt->fetchColumn();
        } catch (Throwable $e) {
            Database::safeAddColumn($pdo, 'users', 'referral_code', 'VARCHAR(32) NULL');
            Database::safeAddColumn($pdo, 'users', 'referred_by', 'INT NULL DEFAULT NULL');
            $code = null;
        }

        if (empty($code)) {
            $code = 'REF' . strtoupper(substr(md5('user' . $userId), 0, 6));
            try {
                $stmtUp = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                $stmtUp->execute([$code, $userId]);
            } catch (Throwable $e) {}
        }

        $totalInvited = 0;
        try {
            $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
            $stmtCnt->execute([$userId]);
            $totalInvited = (int)$stmtCnt->fetchColumn();
        } catch (Throwable $e) {}

        $totalCommission = 0;
        $recentReferrals = [];
        try {
            $stmtCom = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM referrals WHERE referrer_id = ?");
            $stmtCom->execute([$userId]);
            $totalCommission = (int)$stmtCom->fetchColumn();
            
            $stmtRecent = $pdo->prepare("SELECT r.*, u.username as referred_username 
                                            FROM referrals r 
                                            LEFT JOIN users u ON r.referred_id = u.id 
                                            WHERE r.referrer_id = ? 
                                            ORDER BY r.id DESC LIMIT 10");
            $stmtRecent->execute([$userId]);
            $recentReferrals = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
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

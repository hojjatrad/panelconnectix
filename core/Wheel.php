<?php
/**
 * B8: Wheel of Fortune + Points - Gamification
 */

class Wheel {
    private const PRIZES = [
        ['name' => '5 گیگ هدیه', 'type' => 'traffic', 'value' => 5, 'chance' => 30],
        ['name' => '10% تخفیف', 'type' => 'discount', 'value' => 10, 'chance' => 25],
        ['name' => '2 گیگ هدیه', 'type' => 'traffic', 'value' => 2, 'chance' => 20],
        ['name' => '20% تخفیف', 'type' => 'discount', 'value' => 20, 'chance' => 10],
        ['name' => '1 روز هدیه', 'type' => 'days', 'value' => 1, 'chance' => 8],
        ['name' => '50% تخفیف', 'type' => 'discount', 'value' => 50, 'chance' => 5],
        ['name' => '10 گیگ هدیه', 'type' => 'traffic', 'value' => 10, 'chance' => 2],
    ];
    
    public static function canSpin(int $userId): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wheel_spins WHERE user_id = ? AND DATE(created_at) = CURDATE()");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() == 0;
        } catch (Throwable $e) {
            return true;
        }
    }
    
    public static function spin(int $userId): array {
        if (!self::canSpin($userId)) {
            return ['success' => false, 'message' => 'شما امروز شانس خود را استفاده کرده‌اید. فردا دوباره تلاش کنید!'];
        }
        
        // Weighted random
        $rand = mt_rand(1, 100);
        $cumulative = 0;
        $prize = self::PRIZES[0];
        foreach (self::PRIZES as $p) {
            $cumulative += $p['chance'];
            if ($rand <= $cumulative) {
                $prize = $p;
                break;
            }
        }
        
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_spins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                prize_name VARCHAR(100),
                prize_type VARCHAR(20),
                prize_value INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, created_at)
            )");
            
            $stmt = $pdo->prepare("INSERT INTO wheel_spins (user_id, prize_name, prize_type, prize_value) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $prize['name'], $prize['type'], $prize['value']]);
            
            // Apply prize
            if ($prize['type'] === 'traffic') {
                // Add traffic to user's active clients or wallet
                Retention::addGiftCredit($userId, 0, "🎡 جایزه گردونه: {$prize['name']}");
            }
            
            return ['success' => true, 'prize' => $prize, 'message' => "🎉 تبریک! شما برنده {$prize['name']} شدید!"];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }
    
    public static function getHistory(int $userId, int $limit = 10): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM wheel_spins WHERE user_id = ? ORDER BY id DESC LIMIT $limit");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

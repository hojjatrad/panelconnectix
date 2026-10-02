<?php
/**
 * U4: Points and Levels - User gamification
 * M2: Comments system
 */

class Gamification {
    private const LEVELS = [
        1 => ['name' => 'تازه‌کار', 'min_points' => 0, 'icon' => '🌱'],
        2 => ['name' => 'فعال', 'min_points' => 100, 'icon' => '⭐'],
        3 => ['name' => 'حرفه‌ای', 'min_points' => 500, 'icon' => '🔥'],
        4 => ['name' => 'متخصص', 'min_points' => 1500, 'icon' => '💎'],
        5 => ['name' => 'استاد', 'min_points' => 5000, 'icon' => '👑'],
    ];
    
    private const POINTS = [
        'purchase' => 50,
        'referral' => 100,
        'renew' => 30,
        'daily_login' => 5,
        'wheel_spin' => 10,
        'comment' => 20,
    ];
    
    public static function addPoints(int $userId, string $action, int $customPoints = 0): int {
        $points = $customPoints ?: (self::POINTS[$action] ?? 10);
        
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                points INT NOT NULL,
                action VARCHAR(50),
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_levels (
                user_id INT PRIMARY KEY,
                total_points INT DEFAULT 0,
                level INT DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Add points log
            $stmt = $pdo->prepare("INSERT INTO user_points (user_id, points, action, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $points, $action, "امتیاز برای $action"]);
            
            // Update total
            $pdo->prepare("INSERT INTO user_levels (user_id, total_points, level) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE total_points = total_points + ?, level = ?")
                ->execute([$userId, $points, $points, self::calculateLevel($userId, $points)]);
            
            // Actually calculate correctly
            $total = (int)$pdo->query("SELECT COALESCE(SUM(points),0) FROM user_points WHERE user_id = $userId")->fetchColumn();
            $level = self::calculateLevelFromPoints($total);
            $pdo->prepare("UPDATE user_levels SET total_points = ?, level = ? WHERE user_id = ?")
                ->execute([$total, $level, $userId]);
            
            return $points;
        } catch (Throwable $e) {
            return 0;
        }
    }
    
    public static function calculateLevelFromPoints(int $points): int {
        $level = 1;
        foreach (self::LEVELS as $lvl => $data) {
            if ($points >= $data['min_points']) {
                $level = $lvl;
            }
        }
        return $level;
    }
    
    public static function calculateLevel(int $userId, int $additionalPoints = 0): int {
        try {
            $pdo = Database::getConnection();
            $total = (int)$pdo->query("SELECT COALESCE(SUM(points),0) FROM user_points WHERE user_id = $userId")->fetchColumn();
            $total += $additionalPoints;
            return self::calculateLevelFromPoints($total);
        } catch (Throwable $e) {
            return 1;
        }
    }
    
    public static function getUserLevel(int $userId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM user_levels WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $data = $stmt->fetch();
            
            if (!$data) {
                return ['level' => 1, 'total_points' => 0, 'name' => self::LEVELS[1]['name'], 'icon' => self::LEVELS[1]['icon'], 'next_level_points' => self::LEVELS[2]['min_points']];
            }
            
            $level = (int)$data['level'];
            $nextLevel = $level < 5 ? $level + 1 : 5;
            $nextPoints = self::LEVELS[$nextLevel]['min_points'] ?? 0;
            
            return [
                'level' => $level,
                'total_points' => (int)$data['total_points'],
                'name' => self::LEVELS[$level]['name'] ?? 'نامشخص',
                'icon' => self::LEVELS[$level]['icon'] ?? '⭐',
                'next_level' => $nextLevel,
                'next_level_name' => self::LEVELS[$nextLevel]['name'] ?? '',
                'next_level_points' => $nextPoints,
                'progress' => $nextPoints > 0 ? round((($data['total_points'] - self::LEVELS[$level]['min_points']) / ($nextPoints - self::LEVELS[$level]['min_points']) * 100), 1) : 100
            ];
        } catch (Throwable $e) {
            return ['level' => 1, 'total_points' => 0, 'name' => 'تازه‌کار', 'icon' => '🌱'];
        }
    }
    
    public static function getLeaderboard(int $limit = 10): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("
                SELECT ul.*, u.username, u.full_name 
                FROM user_levels ul
                LEFT JOIN users u ON u.id = ul.user_id
                ORDER BY ul.total_points DESC
                LIMIT $limit
            ");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

// M2: Comments system
class Comments {
    public static function add(int $userId, string $content, int $rating = 5, string $type = 'general'): array {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                content TEXT NOT NULL,
                rating INT DEFAULT 5,
                type VARCHAR(20) DEFAULT 'general',
                is_approved TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_approved (is_approved),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $stmt = $pdo->prepare("INSERT INTO comments (user_id, content, rating, type, is_approved) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$userId, $content, $rating, $type]);
            
            // Add points for comment
            if (class_exists('Gamification')) {
                Gamification::addPoints($userId, 'comment', 20);
            }
            
            return ['success' => true, 'id' => $pdo->lastInsertId()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public static function getApproved(int $limit = 10, string $type = 'general'): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT c.*, u.username, u.full_name FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.is_approved = 1 AND c.type = ? ORDER BY c.id DESC LIMIT $limit");
            $stmt->execute([$type]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

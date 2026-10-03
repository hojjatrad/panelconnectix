<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

class ResellerPointsController {
    
    // Calculate points: 1 point per 10k Toman sales, 5 points per client
    public static function calculateLevel(int $points): array {
        if ($points >= 2000) return ['level'=>'diamond','title'=>'الماس','badge'=>'💎','color'=>'cyan','discount'=>20,'next'=>'حداکثر'];
        if ($points >= 500) return ['level'=>'gold','title'=>'طلا','badge'=>'🥇','color'=>'amber','discount'=>15,'next'=>2000];
        if ($points >= 100) return ['level'=>'silver','title'=>'نقره','badge'=>'🥈','color'=>'slate','discount'=>10,'next'=>500];
        return ['level'=>'bronze','title'=>'برنز','badge'=>'🥉','color'=>'orange','discount'=>5,'next'=>100];
    }

    public static function addPoints(int $resellerId, int $points, string $reason=''): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM reseller_points WHERE reseller_id=?");
            $stmt->execute([$resellerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $newPoints = (int)$row['points'] + $points;
                $levelInfo = self::calculateLevel($newPoints);
                $pdo->prepare("UPDATE reseller_points SET points=?, level=?, total_sales=total_sales+?, updated_at=NOW() WHERE reseller_id=?")
                    ->execute([$newPoints, $levelInfo['level'], $points*10000, $resellerId]);
            } else {
                $levelInfo = self::calculateLevel($points);
                $pdo->prepare("INSERT INTO reseller_points (reseller_id, points, level, total_sales, total_clients) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$resellerId, $points, $levelInfo['level'], $points*10000]);
            }
        } catch (Throwable $e) {}
    }

    public static function onClientSale(int $resellerId, int $amount): void {
        $points = max(1, (int)($amount / 10000)); // 1 point per 10k
        $points = min($points, 50); // cap 50 per sale
        self::addPoints($resellerId, $points, 'sale');
        // Also increment client count
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("UPDATE reseller_points SET total_clients=total_clients+1 WHERE reseller_id=?")->execute([$resellerId]);
        } catch (Throwable $e) {}
    }

    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $points = $pdo->query("SELECT rp.*, u.username, u.full_name, u.wallet_balance FROM reseller_points rp JOIN users u ON u.id=rp.reseller_id ORDER BY rp.points DESC")->fetchAll(PDO::FETCH_ASSOC);
        // Also include resellers without points
        $allResellers = $pdo->query("SELECT u.id, u.username, u.full_name, u.wallet_balance, COALESCE(rp.points,0) as points, COALESCE(rp.level,'bronze') as level FROM users u LEFT JOIN reseller_points rp ON rp.reseller_id=u.id WHERE u.role='reseller' AND u.status='active' ORDER BY points DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        require __DIR__ . '/../views/reseller_points/index.php';
    }

    public function myPoints(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $userId = Auth::id();
        $stmt = $pdo->prepare("SELECT * FROM reseller_points WHERE reseller_id=?");
        $stmt->execute([$userId]);
        $my = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$my) {
            $my = ['points'=>0,'level'=>'bronze','total_sales'=>0,'total_clients'=>0];
        }
        $levelInfo = self::calculateLevel((int)$my['points']);
        $nextLevel = $levelInfo['next'];
        $isMax = $nextLevel === 'حداکثر';
        $progress = $isMax ? 100 : min(100, round(($my['points'] / $nextLevel) * 100));
        
        // Leaderboard
        $leaderboard = $pdo->query("SELECT rp.*, u.username, u.full_name FROM reseller_points rp JOIN users u ON u.id=rp.reseller_id ORDER BY rp.points DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        
        require __DIR__ . '/../views/reseller_points/my.php';
    }

    public function adjust(): void {
        Auth::requireAdmin();
        Helpers::verifyCsrf();
        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        $action = $_POST['action'] ?? 'add';
        if ($action === 'remove') $points = -$points;
        
        self::addPoints($resellerId, $points, 'admin_adjust');
        Helpers::flash('success', "امتیاز $points به نماینده #$resellerId اضافه شد");
        Helpers::redirect('resellers/points');
    }
}

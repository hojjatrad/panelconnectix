<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class DashboardController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        // Condition based on role
        $clientWhere = $isAdmin ? "1=1" : "reseller_id = " . intval($userId);
        $transWhere = $isAdmin ? "1=1" : "user_id = " . intval($userId);

        // 1. Client Statistics
        $totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE $clientWhere")->fetchColumn();
        $activeClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active' AND $clientWhere")->fetchColumn();
        $expiredClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'expired' AND $clientWhere")->fetchColumn();
        $neverConnected = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'never_connected' AND $clientWhere")->fetchColumn();
        
        $tenMinutesAgo = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $stmtOnline = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE last_connected_at >= ? AND $clientWhere");
        $stmtOnline->execute([$tenMinutesAgo]);
        $onlineClients = (int)$stmtOnline->fetchColumn();
        
        $idleClients = max(0, $totalClients - ($onlineClients + $neverConnected + $expiredClients));

        // 2. Plans & Revenue Statistics
        $totalPlans = (int)$pdo->query("SELECT COUNT(*) FROM plans WHERE is_active = 1")->fetchColumn();
        $freePlans = (int)$pdo->query("SELECT COUNT(*) FROM plans WHERE is_free = 1")->fetchColumn();
        $premiumPlans = max(0, $totalPlans - $freePlans);

        $totalTransactions = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE $transWhere")->fetchColumn();
        $totalSpent = (int)$pdo->query("SELECT ABS(SUM(amount)) FROM transactions WHERE amount < 0 AND $transWhere")->fetchColumn();
        $walletBalance = (int)$user['wallet_balance'];

        // 3. Active Nodes & Status
        $activeNodes = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 LIMIT 5")->fetchAll();

        // 4. Recent Clients
        $stmtRecent = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name, u.username as reseller_username 
                                     FROM clients c 
                                     LEFT JOIN plans p ON c.plan_id = p.id 
                                     LEFT JOIN server_nodes s ON c.server_id = s.id 
                                     LEFT JOIN users u ON c.reseller_id = u.id 
                                     WHERE $clientWhere 
                                     ORDER BY c.id DESC LIMIT 6");
        $stmtRecent->execute();
        $recentClients = $stmtRecent->fetchAll();

        // 5. Recent System Announcements
        $announcements = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 3")->fetchAll();

        // 6. Reseller Tier & Progress
        require_once __DIR__ . '/../core/Provisioner.php';
        $tierInfo = Provisioner::getResellerTier($userId);

        // 7. Top Resellers (admin only): active clients + 7-day purchases
        $topResellers = [];
        if ($isAdmin) {
            try {
                $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
                $stmtTop = $pdo->prepare("SELECT u.id, u.full_name, u.username, u.wallet_balance,
                        (SELECT COUNT(*) FROM clients c WHERE c.reseller_id = u.id AND c.status = 'active') AS active_clients,
                        (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.user_id = u.id AND t.status = 'completed' AND t.amount > 0 AND t.created_at >= ?) AS week_sales
                     FROM users u
                     WHERE u.role = 'reseller' AND u.status = 'active'
                     ORDER BY active_clients DESC, week_sales DESC
                     LIMIT 5");
                $stmtTop->execute([$weekAgo]);
                $topResellers = $stmtTop->fetchAll();
            } catch (Throwable $e) {
                $topResellers = [];
            }
        }

        // 7. Net Profit & Sales Analytics
        $totalIncome = (int)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE amount > 0 AND $transWhere")->fetchColumn();
        $netProfit = max(0, (int)round($totalSpent * 0.45)); // Estimated profit margin

        // 8. Top Resellers (for admin overview)
        $topResellers = [];
        if ($isAdmin) {
            $topResellers = $pdo->query("SELECT u.id, u.username, u.full_name, u.wallet_balance,
                                                (SELECT COUNT(*) FROM clients WHERE reseller_id = u.id) as client_count
                                         FROM users u 
                                         WHERE u.role = 'reseller' 
                                         ORDER BY client_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        }

        // 9. Sub-Resellers count (if reseller)
        $subResellerCount = 0;
        if (!$isAdmin) {
            $stmtSubCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE parent_reseller_id = ?");
            $stmtSubCount->execute([$userId]);
            $subResellerCount = (int)$stmtSubCount->fetchColumn();
        }

        // 10. Chart Data (Monthly Sales Simulation from transactions)
        $chartMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $chartData = [12, 19, 25, 38, 45, 62, 78, 95, 110, 135, 160, 219];

        require __DIR__ . '/../views/dashboard/index.php';
    }
}

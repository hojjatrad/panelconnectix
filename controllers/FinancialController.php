<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class FinancialController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        // Monthly income/spent last 12 months
        $monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, 
            SUM(CASE WHEN amount>0 THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN amount<0 THEN ABS(amount) ELSE 0 END) as spent,
            COUNT(*) as tx_count
            FROM transactions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ym ORDER BY ym ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Server profitability
        $servers = $pdo->query("SELECT s.id, s.name, s.driver, s.api_url,
            (SELECT COUNT(*) FROM clients WHERE server_id=s.id AND status='active') as active_clients,
            (SELECT COUNT(*) FROM clients WHERE server_id=s.id) as total_clients
            FROM server_nodes s WHERE s.is_active=1 ORDER BY active_clients DESC")->fetchAll(PDO::FETCH_ASSOC);

        // Top plans
        $topPlans = $pdo->query("SELECT p.title, p.server_group, COUNT(c.id) as sold, SUM(t.amount) as revenue
            FROM plans p
            LEFT JOIN clients c ON c.plan_id=p.id
            LEFT JOIN transactions t ON t.description LIKE CONCAT('%', p.title, '%') AND t.amount<0
            GROUP BY p.id ORDER BY sold DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

        // Daily last 30 days
        $daily = $pdo->query("SELECT DATE(created_at) as d, SUM(CASE WHEN amount>0 THEN amount ELSE 0 END) as income FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY d ORDER BY d ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Totals
        $totalIncome = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE amount>0")->fetchColumn();
        $totalSpent = (int)$pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE amount<0")->fetchColumn();
        $netProfit = (int)($totalIncome - $totalSpent);
        $estimatedProfit = (int)($totalSpent * 0.45);

        // Wallet balances
        $walletTotal = (int)$pdo->query("SELECT COALESCE(SUM(wallet_balance),0) FROM users WHERE role='reseller'")->fetchColumn();
        $resellerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='reseller' AND status='active'")->fetchColumn();

        require __DIR__ . '/../views/financial/index.php';
    }

    public function export(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $rows = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID','User','Amount','Balance After','Type','Description','Date']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['user_id'],$r['amount'],$r['balance_after'],$r['type'],$r['description'],$r['created_at']]);
        }
        fclose($out);
        exit;
    }
}

<?php
/**
 * F7: Financial Reports with Charts
 */

class FinancialReport {
    public static function getDailySales(int $days = 30): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as date, 
                       COUNT(*) as count, 
                       SUM(amount) as total
                FROM transactions 
                WHERE status = 'completed' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
    
    public static function getTopPlans(int $limit = 10): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("
                SELECT p.name, COUNT(c.id) as sales, SUM(t.amount) as revenue
                FROM clients c
                LEFT JOIN plans p ON c.plan_id = p.id
                LEFT JOIN transactions t ON t.user_id = c.user_id AND t.created_at >= c.created_at
                WHERE c.status = 'active'
                GROUP BY p.id, p.name
                ORDER BY sales DESC
                LIMIT $limit
            ");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
    
    public static function getOverview(): array {
        try {
            $pdo = Database::getConnection();
            $today = $pdo->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE() AND status='completed'")->fetchColumn();
            $todayRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status='completed'")->fetchColumn();
            $month = $pdo->query("SELECT COUNT(*) FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status='completed'")->fetchColumn();
            $monthRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status='completed'")->fetchColumn();
            $totalClients = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn();
            $expiring = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active' AND expire_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)")->fetchColumn();
            
            return [
                'today_sales' => (int)$today,
                'today_revenue' => (int)$todayRevenue,
                'month_sales' => (int)$month,
                'month_revenue' => (int)$monthRevenue,
                'active_clients' => (int)$totalClients,
                'expiring_soon' => (int)$expiring
            ];
        } catch (Throwable $e) {
            return [];
        }
    }
}

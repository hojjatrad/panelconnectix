<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class LogController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();
        $userId = Auth::id();

        $actionFilter = trim($_GET['action'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $where = $isAdmin ? ["1=1"] : ["l.user_id = " . intval($userId)];
        $params = [];

        if (!empty($actionFilter)) {
            $where[] = "l.action = ?";
            $params[] = $actionFilter;
        }

        if (!empty($search)) {
            $where[] = "(l.description LIKE ? OR l.ip_address LIKE ? OR u.username LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereSql = implode(' AND ', $where);

        $limit = 100;
        $stmt = $pdo->prepare("SELECT l.*, u.username, u.role 
                               FROM activity_logs l 
                               LEFT JOIN users u ON l.user_id = u.id 
                               WHERE $whereSql 
                               ORDER BY l.id DESC 
                               LIMIT $limit");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get distinct action types for filter
        $distinctActions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

        require __DIR__ . '/../views/logs/index.php';
    }

    public function clear(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('logs');
        }

        $pdo = Database::getConnection();
        $pdo->exec("DELETE FROM activity_logs");

        Helpers::logActivity('logs_cleared', 'تمام لاگ‌های سیستمی توسط مدیر ارشد پاکسازی شدند', 'system');
        Helpers::flash('info', 'سوابق و لاگ‌های امنیتی سیستم با موفقیت پاکسازی شدند.');
        Helpers::redirect('logs');
    }
}

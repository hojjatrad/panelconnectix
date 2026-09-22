<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class PlanController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        $plans = $pdo->query("SELECT p.*, s.name as server_name, s.driver as server_driver, s.sub_domain as server_subdomain 
                              FROM plans p 
                              LEFT JOIN server_nodes s ON p.server_id = s.id 
                              ORDER BY p.is_free DESC, p.base_price ASC")->fetchAll();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        require __DIR__ . '/../views/plans/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('plans');
        }

        $title = trim($_POST['title'] ?? '');
        $traffic = (int)($_POST['traffic_gb'] ?? 0);
        $days = (int)($_POST['duration_days'] ?? 0);
        $basePrice = (int)($_POST['base_price'] ?? 0);
        $resellerPrice = (int)($_POST['reseller_price'] ?? 0);
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $category = trim($_POST['category'] ?? '۱ ماهه');
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 2));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if (empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'لطفاً مقادیر عنوان، حجم و روز را معتبر وارد کنید.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, ip_limit, show_in_bot, is_free) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $traffic, $days, $basePrice, $resellerPrice, $serverGroup, $serverId, $category, $ipLimit, $showInBot, $isFree]);

        Helpers::flash('success', 'پلن جدید با موفقیت ایجاد شد.');
        Helpers::redirect('plans');
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('plans');
        }

        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $traffic = (int)($_POST['traffic_gb'] ?? 0);
        $days = (int)($_POST['duration_days'] ?? 0);
        $basePrice = (int)($_POST['base_price'] ?? 0);
        $resellerPrice = (int)($_POST['reseller_price'] ?? 0);
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $category = trim($_POST['category'] ?? '۱ ماهه');
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 2));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if ($id <= 0 || empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'اطلاعات وارد شده برای ویرایش پلن نامعتبر است.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE plans SET title = ?, traffic_gb = ?, duration_days = ?, base_price = ?, reseller_price = ?, server_group = ?, server_id = ?, category = ?, ip_limit = ?, show_in_bot = ?, is_free = ? WHERE id = ?");
        $stmt->execute([$title, $traffic, $days, $basePrice, $resellerPrice, $serverGroup, $serverId, $category, $ipLimit, $showInBot, $isFree, $id]);

        Helpers::flash('success', "پلن '{$title}' با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('plans');
    }

    public function toggle(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('plans');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->query("UPDATE plans SET is_active = (1 - is_active) WHERE id = $id");
        Helpers::flash('info', 'وضعیت فعال/غیرفعال پلن تغییر یافت.');
        Helpers::redirect('plans');
    }

    public function toggleBot(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('plans');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->query("UPDATE plans SET show_in_bot = (1 - COALESCE(show_in_bot, 1)) WHERE id = $id");
        Helpers::flash('info', 'وضعیت نمایش پلن در ربات تلگرام تغییر یافت.');
        Helpers::redirect('plans');
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر است.');
            Helpers::redirect('plans');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $clientCount = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE plan_id = $id")->fetchColumn();
        if ($clientCount > 0) {
            Helpers::flash('error', "این پلن دارای {$clientCount} مشترک فعال است و قابل حذف نیست. می‌توانید وضعیت آن را غیرفعال کنید.");
            Helpers::redirect('plans');
        }

        $pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'پلن مورد نظر با موفقیت حذف شد.');
        Helpers::redirect('plans');
    }
}

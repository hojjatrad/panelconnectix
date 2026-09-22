<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class PlanController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $plans = $pdo->query("SELECT * FROM plans ORDER BY is_free DESC, base_price ASC")->fetchAll();
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
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if (empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'لطفاً مقادیر عنوان، حجم و روز را معتبر وارد کنید.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, is_free) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $traffic, $days, $basePrice, $resellerPrice, $serverGroup, $isFree]);

        Helpers::flash('success', 'پلن جدید با موفقیت ایجاد شد.');
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
        Helpers::flash('info', 'وضعیت پلن تغییر یافت.');
        Helpers::redirect('plans');
    }
}

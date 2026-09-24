<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class PlanController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        $plans = $pdo->query("SELECT p.*, s.name as server_name, s.driver as server_driver, s.sub_domain as server_subdomain,
                              c.name as cluster_name, c.badge_color as cluster_color, c.icon as cluster_icon, c.slug as cluster_slug 
                              FROM plans p 
                              LEFT JOIN server_nodes s ON p.server_id = s.id 
                              LEFT JOIN categories c ON (p.category_id = c.id OR (p.category_id IS NULL AND p.server_group = c.slug))
                              ORDER BY p.is_free DESC, p.base_price ASC")->fetchAll(PDO::FETCH_ASSOC);
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $allCategories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $serverCategories = array_values(array_filter($allCategories, fn($c) => in_array($c['type'] ?? '', ['servers', 'server', 'both'])));
        $planCategories = array_values(array_filter($allCategories, fn($c) => in_array($c['type'] ?? '', ['plans', 'plan', 'both'])));
        if (empty($planCategories)) {
            $planCategories = $allCategories;
        }
        
        $dbCategories = $serverCategories;
        require __DIR__ . '/../views/plans/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('plans');
        }

        $title = trim($_POST['title'] ?? '');
        $trafficInput = (float)($_POST['traffic_gb'] ?? 0);
        $trafficUnit = strtolower(trim($_POST['traffic_unit'] ?? 'gb'));
        $traffic = ($trafficUnit === 'mb') ? round($trafficInput / 1024, 4) : $trafficInput;
        $days = (int)($_POST['duration_days'] ?? 0);
        $basePrice = (int)($_POST['base_price'] ?? 0);
        $resellerPrice = (int)($_POST['reseller_price'] ?? 0);
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $category = trim($_POST['category'] ?? '');
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 2));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if (empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'لطفاً مقادیر عنوان، حجم و روز را معتبر وارد کنید.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catRow = $pdo->query("SELECT * FROM categories WHERE id = " . (int)$categoryId)->fetch(PDO::FETCH_ASSOC);
            if ($catRow) {
                if (empty($category) || $category === '۱ ماهه') {
                    $category = $catRow['name'];
                }
                if ($catRow['type'] === 'servers' || in_array($catRow['slug'], ['default', 'vip', 'economic', 'iran_access', 'gaming'])) {
                    if (empty($serverGroup) || $serverGroup === 'default') {
                        $serverGroup = $catRow['slug'];
                    }
                }
            }
        }
        if (empty($category)) {
            $category = 'عمومی';
        }

        $startOnFirstUse = isset($_POST['start_on_first_use']) ? 1 : 0;
        $maxDevices = max(1, (int)($_POST['max_devices'] ?? $ipLimit));

        $stmt = $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category_id, category, ip_limit, max_devices, start_on_first_use, show_in_bot, is_free) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $traffic, $days, $basePrice, $resellerPrice, $serverGroup, $serverId, $categoryId, $category, $ipLimit, $maxDevices, $startOnFirstUse, $showInBot, $isFree]);

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
        $trafficInput = (float)($_POST['traffic_gb'] ?? 0);
        $trafficUnit = strtolower(trim($_POST['traffic_unit'] ?? 'gb'));
        $traffic = ($trafficUnit === 'mb') ? round($trafficInput / 1024, 4) : $trafficInput;
        $days = (int)($_POST['duration_days'] ?? 0);
        $basePrice = (int)($_POST['base_price'] ?? 0);
        $resellerPrice = (int)($_POST['reseller_price'] ?? 0);
        $serverGroup = trim($_POST['server_group'] ?? 'default');
        $serverId = !empty($_POST['server_id']) ? (int)$_POST['server_id'] : null;
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $category = trim($_POST['category'] ?? '');
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 2));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if ($id <= 0 || empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'اطلاعات ارسالی پلن ناقص است.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        if ($categoryId) {
            $catRow = $pdo->query("SELECT * FROM categories WHERE id = " . (int)$categoryId)->fetch(PDO::FETCH_ASSOC);
            if ($catRow) {
                if (empty($category) || $category === '۱ ماهه') {
                    $category = $catRow['name'];
                }
                if ($catRow['type'] === 'servers' || in_array($catRow['slug'], ['default', 'vip', 'economic', 'iran_access', 'gaming'])) {
                    if (empty($serverGroup) || $serverGroup === 'default') {
                        $serverGroup = $catRow['slug'];
                    }
                }
            }
        }
        if (empty($category)) {
            $category = 'عمومی';
        }

        $startOnFirstUse = isset($_POST['start_on_first_use']) ? 1 : 0;
        $maxDevices = max(1, (int)($_POST['max_devices'] ?? $ipLimit));

        $stmt = $pdo->prepare("UPDATE plans SET title = ?, traffic_gb = ?, duration_days = ?, base_price = ?, reseller_price = ?, server_group = ?, server_id = ?, category_id = ?, category = ?, ip_limit = ?, max_devices = ?, start_on_first_use = ?, show_in_bot = ?, is_free = ? WHERE id = ?");
        $stmt->execute([$title, $traffic, $days, $basePrice, $resellerPrice, $serverGroup, $serverId, $categoryId, $category, $ipLimit, $maxDevices, $startOnFirstUse, $showInBot, $isFree, $id]);

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

        // Safely detach clients, orders, and reseller mappings before plan deletion
        $pdo->prepare("UPDATE clients SET plan_id = NULL WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE bot_orders SET plan_id = NULL WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reseller_plans WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reserved_plans WHERE plan_id = ?")->execute([$id]);

        $pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'پلن مورد نظر با موفقیت حذف گردید.');
        Helpers::redirect('plans');
    }
}

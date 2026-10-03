<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class PlanController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        try {
            $plans = $pdo->query("SELECT p.*, s.name as server_name, s.driver as server_driver, s.sub_domain as server_subdomain,
                                  c.name as cluster_name, c.badge_color as cluster_color, c.icon as cluster_icon, c.slug as cluster_slug 
                                  FROM plans p 
                                  LEFT JOIN server_nodes s ON p.server_id = s.id 
                                  LEFT JOIN categories c ON (p.category_id = c.id OR (p.category_id IS NULL AND p.server_group = c.slug))
                                  ORDER BY p.is_free DESC, p.base_price ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Fallback query if joins fail on older schemas
            $plans = $pdo->query("SELECT * FROM plans ORDER BY is_free DESC, base_price ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        try {
            $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $servers = [];
        }
        
        try {
            $allCategories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $allCategories = [];
        }

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
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 0));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if (empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'لطفاً مقادیر عنوان، حجم و روز را معتبر وارد کنید.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

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
        } else {
            // Professional merge: normalize to canonical month (1 ماهه, etc.)
            $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
            $normCat = str_replace($persianDigits, $englishDigits, $category);
            $normCatLower = mb_strtolower(str_replace(' ', '', $normCat));
            $canonicalMap = [
                '۱ روزه' => ['1روزه','روزانه','1d','daily'],
                '۳ روزه' => ['3روزه','3d'],
                'هفتگی' => ['هفتگی','7روزه','1هفته','weekly','7d'],
                '۱ ماهه' => ['1ماهه','یکماهه','30روزه','1m'],
                '۲ ماهه' => ['2ماهه','دوماهه','60روزه','2m'],
                '۳ ماهه' => ['3ماهه','سهماهه','90روزه','3m'],
                '۶ ماهه' => ['6ماهه','ششماهه','180روزه','6m'],
                '۱۲ ماهه' => ['12ماهه','یکساله','1ساله','سالانه','365روزه','12m','yearly'],
            ];
            foreach ($canonicalMap as $canon => $variants) {
                $canonNorm = mb_strtolower(str_replace(' ', '', str_replace($persianDigits, $englishDigits, $canon)));
                if ($normCatLower === $canonNorm) { $category = $canon; break; }
                foreach ($variants as $v) {
                    $vNorm = mb_strtolower(str_replace(' ', '', str_replace($persianDigits, $englishDigits, $v)));
                    if ($normCatLower === $vNorm || strpos($normCatLower, $vNorm) !== false) {
                        $category = $canon;
                        break 2;
                    }
                }
            }
        }

        $startOnFirstUse = isset($_POST['start_on_first_use']) ? 1 : 0;
        $maxDevices = max(0, (int)($_POST['max_devices'] ?? $ipLimit));
        $vipPlanId = trim($_POST['vip_plan_id'] ?? '');
        $vipGroupId = trim($_POST['vip_group_id'] ?? '');
        $vipGroupName = trim($_POST['vip_group_name'] ?? '');
        $vipPlanTitle = trim($_POST['vip_plan_title'] ?? '');

        // Intelligently detect available columns in plans table
        $availableCols = [];
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $cStmt = $pdo->query("SHOW COLUMNS FROM `plans`");
                while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $availableCols[$r['Field']] = true;
                }
            } else {
                $cStmt = $pdo->query("PRAGMA table_info(plans)");
                while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $availableCols[$r['name']] = true;
                }
            }
        } catch (Throwable $e) {}

        $data = [
            'title' => $title,
            'traffic_gb' => $traffic,
            'duration_days' => $days,
            'base_price' => $basePrice,
            'reseller_price' => $resellerPrice,
            'server_group' => $serverGroup,
            'is_free' => $isFree
        ];

        if (isset($availableCols['server_id'])) $data['server_id'] = $serverId;
        if (isset($availableCols['category_id'])) $data['category_id'] = $categoryId;
        if (isset($availableCols['category'])) $data['category'] = $category;
        if (isset($availableCols['ip_limit'])) $data['ip_limit'] = $ipLimit;
        if (isset($availableCols['max_devices'])) $data['max_devices'] = $maxDevices;
        if (isset($availableCols['start_on_first_use'])) $data['start_on_first_use'] = $startOnFirstUse;
        if (isset($availableCols['show_in_bot'])) $data['show_in_bot'] = $showInBot;
        if (isset($availableCols['vip_plan_id'])) $data['vip_plan_id'] = $vipPlanId ?: null;
        if (isset($availableCols['vip_group_id'])) $data['vip_group_id'] = $vipGroupId ?: null;
        if (isset($availableCols['vip_group_name'])) $data['vip_group_name'] = $vipGroupName ?: null;
        if (isset($availableCols['vip_plan_title'])) $data['vip_plan_title'] = $vipPlanTitle ?: null;

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO `plans` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $placeholders) . ")";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            Helpers::flash('success', 'پلن جدید با موفقیت ایجاد شد.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ثبت پلن: ' . $e->getMessage());
        }

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
        $ipLimit = max(0, (int)($_POST['ip_limit'] ?? 0));
        $showInBot = isset($_POST['show_in_bot']) ? 1 : 0;
        $isFree = isset($_POST['is_free']) ? 1 : 0;

        if ($id <= 0 || empty($title) || $traffic <= 0 || $days <= 0) {
            Helpers::flash('error', 'اطلاعات ارسالی پلن ناقص است.');
            Helpers::redirect('plans');
        }

        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

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
        } else {
            // Professional merge: normalize to canonical month (1 ماهه, etc.)
            $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
            $normCat = str_replace($persianDigits, $englishDigits, $category);
            $normCatLower = mb_strtolower(str_replace(' ', '', $normCat));
            $canonicalMap = [
                '۱ روزه' => ['1روزه','روزانه','1d','daily'],
                '۳ روزه' => ['3روزه','3d'],
                'هفتگی' => ['هفتگی','7روزه','1هفته','weekly','7d'],
                '۱ ماهه' => ['1ماهه','یکماهه','30روزه','1m'],
                '۲ ماهه' => ['2ماهه','دوماهه','60روزه','2m'],
                '۳ ماهه' => ['3ماهه','سهماهه','90روزه','3m'],
                '۶ ماهه' => ['6ماهه','ششماهه','180روزه','6m'],
                '۱۲ ماهه' => ['12ماهه','یکساله','1ساله','سالانه','365روزه','12m','yearly'],
            ];
            foreach ($canonicalMap as $canon => $variants) {
                $canonNorm = mb_strtolower(str_replace(' ', '', str_replace($persianDigits, $englishDigits, $canon)));
                if ($normCatLower === $canonNorm) { $category = $canon; break; }
                foreach ($variants as $v) {
                    $vNorm = mb_strtolower(str_replace(' ', '', str_replace($persianDigits, $englishDigits, $v)));
                    if ($normCatLower === $vNorm || strpos($normCatLower, $vNorm) !== false) {
                        $category = $canon;
                        break 2;
                    }
                }
            }
        }

        $startOnFirstUse = isset($_POST['start_on_first_use']) ? 1 : 0;
        $maxDevices = max(0, (int)($_POST['max_devices'] ?? $ipLimit));
        $vipPlanId = trim($_POST['vip_plan_id'] ?? '');
        $vipGroupId = trim($_POST['vip_group_id'] ?? '');
        $vipGroupName = trim($_POST['vip_group_name'] ?? '');
        $vipPlanTitle = trim($_POST['vip_plan_title'] ?? '');

        // Intelligently detect available columns in plans table
        $availableCols = [];
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $cStmt = $pdo->query("SHOW COLUMNS FROM `plans`");
                while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $availableCols[$r['Field']] = true;
                }
            } else {
                $cStmt = $pdo->query("PRAGMA table_info(plans)");
                while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $availableCols[$r['name']] = true;
                }
            }
        } catch (Throwable $e) {}

        $data = [
            'title' => $title,
            'traffic_gb' => $traffic,
            'duration_days' => $days,
            'base_price' => $basePrice,
            'reseller_price' => $resellerPrice,
            'server_group' => $serverGroup,
            'is_free' => $isFree
        ];

        if (isset($availableCols['server_id'])) $data['server_id'] = $serverId;
        if (isset($availableCols['category_id'])) $data['category_id'] = $categoryId;
        if (isset($availableCols['category'])) $data['category'] = $category;
        if (isset($availableCols['ip_limit'])) $data['ip_limit'] = $ipLimit;
        if (isset($availableCols['max_devices'])) $data['max_devices'] = $maxDevices;
        if (isset($availableCols['start_on_first_use'])) $data['start_on_first_use'] = $startOnFirstUse;
        if (isset($availableCols['show_in_bot'])) $data['show_in_bot'] = $showInBot;
        if (isset($availableCols['vip_plan_id'])) $data['vip_plan_id'] = $vipPlanId ?: null;
        if (isset($availableCols['vip_group_id'])) $data['vip_group_id'] = $vipGroupId ?: null;
        if (isset($availableCols['vip_group_name'])) $data['vip_group_name'] = $vipGroupName ?: null;
        if (isset($availableCols['vip_plan_title'])) $data['vip_plan_title'] = $vipPlanTitle ?: null;

        $setParts = [];
        $values = [];
        foreach ($data as $f => $val) {
            $setParts[] = "`{$f}` = ?";
            $values[] = $val;
        }
        $values[] = $id;

        $sql = "UPDATE `plans` SET " . implode(", ", $setParts) . " WHERE `id` = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            Helpers::flash('success', "پلن '{$title}' با موفقیت به‌روزرسانی شد.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در به‌روزرسانی پلن: ' . $e->getMessage());
        }

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

        // v6.8.19: Get server_id before delete to disable auto_import for that server (prevent resurrection)
        try {
            $planRow = $pdo->prepare("SELECT server_id FROM plans WHERE id = ?");
            $planRow->execute([$id]);
            $pr = $planRow->fetch();
            if (!empty($pr['server_id'])) {
                $pdo->prepare("UPDATE server_nodes SET auto_import_plans = 0 WHERE id = ?")->execute([$pr['server_id']]);
            }
        } catch (Throwable $e) {}

        // Safely detach clients, orders, and reseller mappings before plan deletion
        $pdo->prepare("UPDATE clients SET plan_id = NULL WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE bot_orders SET plan_id = NULL WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reseller_plans WHERE plan_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reserved_plans WHERE plan_id = ?")->execute([$id]);

        $pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'پلن حذف شد و ایمپورت خودکار سرور مربوطه غیرفعال شد تا دوباره برنگردد.');
        Helpers::redirect('plans');
    }

    public function purgeAll(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            else $pdo->exec("PRAGMA foreign_keys = OFF");

            $pdo->exec("DELETE FROM plans");
            $pdo->exec("DELETE FROM reseller_plans");
            $pdo->exec("DELETE FROM reserved_plans");
            $pdo->exec("UPDATE clients SET plan_id = NULL");
            $pdo->exec("UPDATE bot_orders SET plan_id = NULL");
            // v6.8.19 FIX: Disable auto_import_plans for all servers to prevent resurrection
            $pdo->exec("UPDATE server_nodes SET auto_import_plans = 0");

            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            else $pdo->exec("PRAGMA foreign_keys = ON");

            Helpers::flash('success', 'تمامی پلن‌ها به طور کامل پاکسازی شدند و ایمپورت خودکار غیرفعال شد تا دوباره برنگردند. اکنون می‌توانید پلن‌های اختصاصی خود را تعریف فرمایید.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در پاکسازی پلن‌ها: ' . $e->getMessage());
        }
        Helpers::redirect('plans');
    }
}

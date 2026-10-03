<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class CategoryController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        // Ensure categories table exists
        try {
            Database::ensureExtendedTablesExist($pdo);
        } catch (Throwable $e) {}

        $categories = $pdo->query("SELECT c.*, 
            (SELECT COUNT(*) FROM server_nodes WHERE category_id = c.id OR (category_id IS NULL AND server_group = c.slug)) as server_count,
            (SELECT COUNT(*) FROM plans WHERE category_id = c.id OR (category_id IS NULL AND server_group = c.slug)) as plan_count,
            (SELECT COUNT(*) FROM categories AS child WHERE child.parent_id = c.id) as children_count,
            p.name as parent_name
            FROM categories c 
            LEFT JOIN categories p ON c.parent_id = p.id
            ORDER BY COALESCE(c.parent_id, c.id) ASC, c.parent_id IS NULL DESC, c.sort_order ASC, c.id ASC")->fetchAll();

        // Build tree for dropdowns
        $tree = [];
        $byId = [];
        foreach ($categories as $cat) {
            $byId[$cat['id']] = $cat;
        }
        // Calculate level
        foreach ($categories as &$cat) {
            $lvl = 0;
            $pid = $cat['parent_id'];
            while ($pid && isset($byId[$pid]) && $lvl < 10) {
                $lvl++;
                $pid = $byId[$pid]['parent_id'] ?? null;
            }
            $cat['calc_level'] = $lvl;
        }
        unset($cat);

        // For parent select: only categories that are not child of itself
        $parentOptions = $categories;

        require __DIR__ . '/../views/categories/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('categories');
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? 'both');
        $icon = trim($_POST['icon'] ?? 'fa-server');
        $badgeColor = trim($_POST['badge_color'] ?? 'purple');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $botLabel = trim($_POST['bot_label'] ?? '');
        $botIcon = trim($_POST['bot_icon'] ?? '');

        if (empty($name)) {
            Helpers::flash('error', 'نام دسته‌بندی الزامی است.');
            Helpers::redirect('categories');
        }

        if (empty($slug)) {
            $slug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
            $slug = trim($slug, '_') ?: 'cat_' . time();
        } else {
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($slug));
        }

        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/../core/CategoryManager.php';
        
        // Smart: normalize name to canonical to avoid duplicate
        $canonical = CategoryManager::normalizeCategoryName($name);
        $name = $canonical;
        $slug = CategoryManager::slugify($canonical);

        // Check uniqueness of slug
        $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmtCheck->execute([$slug]);
        $existing = $stmtCheck->fetch();
        if ($existing) {
            // Return existing instead of creating duplicate
            Helpers::flash('info', "دسته‌بندی «{$name}» قبلاً وجود دارد (ادغام شد).");
            Helpers::redirect('categories');
        }

        // Calculate level
        $level = 0;
        if ($parentId) {
            $parent = $pdo->prepare("SELECT level, parent_id FROM categories WHERE id = ?");
            $parent->execute([$parentId]);
            $pr = $parent->fetch();
            if ($pr) {
                $level = (int)($pr['level'] ?? 0) + 1;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order, parent_id, level, bot_label, bot_icon, is_active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $slug, $type, $icon, $badgeColor, $description, $sortOrder, $parentId, $level, $botLabel ?: null, $botIcon ?: null]);
        $newId = (int)$pdo->lastInsertId();
        // Add aliases
        try { CategoryManager::addAliases($pdo, $newId, $name); } catch (Throwable $e) {}

        Helpers::logActivity('category_create', "ایجاد دسته‌بندی جدید {$name} ({$slug})", 'category', (string)$newId);
        Helpers::flash('success', "دسته‌بندی «{$name}» با موفقیت افزوده شد.");
        Helpers::redirect('categories');
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('categories');
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? 'both');
        $icon = trim($_POST['icon'] ?? 'fa-server');
        $badgeColor = trim($_POST['badge_color'] ?? 'purple');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $botLabel = trim($_POST['bot_label'] ?? '');
        $botIcon = trim($_POST['bot_icon'] ?? '');

        if (empty($name) || $id <= 0) {
            Helpers::flash('error', 'اطلاعات ارسالی نامعتبر است.');
            Helpers::redirect('categories');
        }

        if ($parentId === $id) {
            Helpers::flash('error', 'دسته نمی‌تواند والد خودش باشد.');
            Helpers::redirect('categories');
        }

        $pdo = Database::getConnection();

        // Check uniqueness of slug
        if (!empty($slug)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmtCheck->execute([$slug, $id]);
            if ($stmtCheck->fetch()) {
                Helpers::flash('error', 'شناسه یکتا (slug) قبلاً برای دسته دیگری ثبت شده است.');
                Helpers::redirect('categories');
            }
        }

        // Prevent circular parenting
        if ($parentId) {
            $checkId = $parentId;
            $depth = 0;
            while ($checkId && $depth < 10) {
                if ($checkId === $id) {
                    Helpers::flash('error', 'ایجاد حلقه در دسته‌بندی مجاز نیست.');
                    Helpers::redirect('categories');
                }
                $st = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
                $st->execute([$checkId]);
                $row = $st->fetch();
                $checkId = $row['parent_id'] ?? null;
                $depth++;
            }
        }

        $level = 0;
        if ($parentId) {
            $parent = $pdo->prepare("SELECT level FROM categories WHERE id = ?");
            $parent->execute([$parentId]);
            $pr = $parent->fetch();
            if ($pr) $level = (int)($pr['level'] ?? 0) + 1;
        }

        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, type = ?, icon = ?, badge_color = ?, description = ?, sort_order = ?, parent_id = ?, level = ?, bot_label = ?, bot_icon = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $type, $icon, $badgeColor, $description, $sortOrder, $parentId, $level, $botLabel ?: null, $botIcon ?: null, $isActive, $id]);

        // Update children levels recursively
        $updateChildrenLevel = function($parentId, $parentLevel) use ($pdo, &$updateChildrenLevel) {
            $children = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
            $children->execute([$parentId]);
            foreach ($children->fetchAll() as $ch) {
                $newLevel = $parentLevel + 1;
                $pdo->prepare("UPDATE categories SET level = ? WHERE id = ?")->execute([$newLevel, $ch['id']]);
                $updateChildrenLevel($ch['id'], $newLevel);
            }
        };
        $updateChildrenLevel($id, $level);

        Helpers::logActivity('category_update', "ویرایش دسته‌بندی {$name} (شناسه {$id})", 'category', (string)$id);
        Helpers::flash('success', "دسته‌بندی «{$name}» با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('categories');
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('categories');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 1) {
            Helpers::flash('error', 'دسته‌بندی پیش‌فرض سیستم قابل حذف نیست.');
            Helpers::redirect('categories');
        }

        $pdo = Database::getConnection();

        // Get parent of deleting category
        $stmtParent = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
        $stmtParent->execute([$id]);
        $parentRow = $stmtParent->fetch();
        $newParentId = $parentRow['parent_id'] ?? null;

        // Reassign children to new parent (or root)
        if ($newParentId) {
            $pdo->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?")->execute([$newParentId, $id]);
        } else {
            $pdo->prepare("UPDATE categories SET parent_id = NULL, level = 0 WHERE parent_id = ?")->execute([$id]);
        }

        // Reassign servers and plans to default category (id 1)
        $pdo->prepare("UPDATE server_nodes SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE plans SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);

        // Delete category
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);

        // v6.8.22: Global kill switch for categories - prevent resurrection
        try {
            require_once __DIR__ . '/../core/Setting.php';
            Setting::set('categories_auto_seed_disabled', '1');
            Setting::set('categories_last_deleted_at', date('Y-m-d H:i:s'));
        } catch (Throwable $e) {}

        Helpers::logActivity('category_delete', "حذف دسته‌بندی شناسه {$id} و انتقال موارد وابسته به پیش‌فرض", 'category', (string)$id);
        Helpers::flash('info', 'دسته‌بندی حذف شد و سرورها و پلن‌های وابسته به دسته پیش‌فرض منتقل شدند.');
        Helpers::redirect('categories');
    }

    public function mergeDuplicates(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('categories');
        }

        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/../core/CategoryManager.php';

        $result = CategoryManager::mergeDuplicateCategories($pdo);

        Helpers::logActivity('category_merge', "ادغام {$result['merged']} دسته‌بندی تکراری", 'category');
        Helpers::flash('success', "✅ {$result['merged']} دسته‌بندی تکراری ادغام شد. " . implode(' | ', array_slice($result['details'], 0, 3)));
        Helpers::redirect('categories');
    }

    public function fixAll(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('categories');
        }

        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/../core/CategoryManager.php';

        // 1. Merge duplicates
        $mergeResult = CategoryManager::mergeDuplicateCategories($pdo);

        // 2. Fix plans category_id based on duration
        $fixedPlans = 0;
        try {
            $plans = $pdo->query("SELECT id, category, duration_days FROM plans")->fetchAll();
            foreach ($plans as $pl) {
                $catName = CategoryManager::canonicalFromDuration((int)$pl['duration_days']);
                $catRow = CategoryManager::findOrCreateCategory($pdo, $catName, (int)$pl['duration_days'], 'plans');
                if ($catRow && (empty($pl['category']) || $pl['category'] !== $catName)) {
                    $pdo->prepare("UPDATE plans SET category = ?, category_id = ? WHERE id = ?")->execute([$catName, $catRow['id'], $pl['id']]);
                    $fixedPlans++;
                } elseif ($catRow) {
                    $pdo->prepare("UPDATE plans SET category_id = ? WHERE id = ? AND (category_id IS NULL OR category_id = 0)")->execute([$catRow['id'], $pl['id']]);
                }
            }
        } catch (Throwable $e) {}

        Helpers::flash('success', "✅ ادغام: {$mergeResult['merged']} دسته تکراری، {$fixedPlans} پلن اصلاح شد.");
        Helpers::redirect('categories');
    }

    public function bulkDelete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('categories');
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            Helpers::flash('error', 'هیچ دسته‌ای برای حذف انتخاب نشده.');
            Helpers::redirect('categories');
        }

        // Filter and sanitize
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 1); // Don't delete default id 1
        if (empty($ids)) {
            Helpers::flash('error', 'دسته پیش‌فرض قابل حذف نیست.');
            Helpers::redirect('categories');
        }

        $pdo = Database::getConnection();
        $deleted = 0;
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            else $pdo->exec("PRAGMA foreign_keys = OFF");

            foreach ($ids as $id) {
                // Reassign children
                $stmtParent = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
                $stmtParent->execute([$id]);
                $parentRow = $stmtParent->fetch();
                $newParentId = $parentRow['parent_id'] ?? null;

                if ($newParentId) {
                    $pdo->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?")->execute([$newParentId, $id]);
                } else {
                    $pdo->prepare("UPDATE categories SET parent_id = NULL, level = 0 WHERE parent_id = ?")->execute([$id]);
                }

                $pdo->prepare("UPDATE server_nodes SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE plans SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                $deleted++;
            }

            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            else $pdo->exec("PRAGMA foreign_keys = ON");

            // Kill switch
            require_once __DIR__ . '/../core/Setting.php';
            Setting::set('categories_auto_seed_disabled', '1');
            Setting::set('categories_last_deleted_at', date('Y-m-d H:i:s'));
            Setting::set('categories_bulk_deleted', (string)$deleted);

            Helpers::flash('success', "✅ {$deleted} دسته‌بندی با موفقیت حذف شد و ایمپورت خودکار دسته‌ها غیرفعال شد تا دوباره برنگردند.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در حذف گروهی: ' . $e->getMessage());
        }
        Helpers::redirect('categories');
    }

    public function purgeAll(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('categories');
        }
        $pdo = Database::getConnection();
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            else $pdo->exec("PRAGMA foreign_keys = OFF");

            // Keep default id 1
            $pdo->exec("DELETE FROM categories WHERE id > 1");
            $pdo->exec("UPDATE server_nodes SET category_id = 1, server_group = 'default' WHERE category_id > 1");
            $pdo->exec("UPDATE plans SET category_id = 1, server_group = 'default' WHERE category_id > 1");

            if ($driver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            else $pdo->exec("PRAGMA foreign_keys = ON");

            require_once __DIR__ . '/../core/Setting.php';
            Setting::set('categories_auto_seed_disabled', '1');
            Setting::set('categories_purged_at', date('Y-m-d H:i:s'));

            Helpers::flash('success', '🗑️ تمام دسته‌بندی‌ها به جز پیش‌فرض پاک شدند و ایمپورت خودکار غیرفعال شد.');
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: '.$e->getMessage());
        }
        Helpers::redirect('categories');
    }

    public function enableAutoSeed(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('categories');
        }
        require_once __DIR__ . '/../core/Setting.php';
        Setting::set('categories_auto_seed_disabled', '0');
        Helpers::flash('success', '✅ ایمپورت خودکار دسته‌بندی‌ها فعال شد.');
        Helpers::redirect('categories');
    }

    public function disableAutoSeed(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('categories');
        }
        require_once __DIR__ . '/../core/Setting.php';
        Setting::set('categories_auto_seed_disabled', '1');
        Helpers::flash('success', '🚫 ایمپورت خودکار دسته‌بندی‌ها غیرفعال شد. دیگر برنمی‌گردند.');
        Helpers::redirect('categories');
    }
}

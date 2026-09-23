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
            (SELECT COUNT(*) FROM plans WHERE category_id = c.id OR (category_id IS NULL AND server_group = c.slug)) as plan_count
            FROM categories c 
            ORDER BY c.sort_order ASC, c.id ASC")->fetchAll();

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
        
        // Check uniqueness of slug
        $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmtCheck->execute([$slug]);
        if ($stmtCheck->fetch()) {
            $slug .= '_' . time();
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order, is_active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $slug, $type, $icon, $badgeColor, $description, $sortOrder]);

        Helpers::logActivity('category_create', "ایجاد دسته‌بندی جدید {$name} ({$slug})", 'category', (string)$pdo->lastInsertId());
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

        if (empty($name) || $id <= 0) {
            Helpers::flash('error', 'اطلاعات ارسالی نامعتبر است.');
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

        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, type = ?, icon = ?, badge_color = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $type, $icon, $badgeColor, $description, $sortOrder, $isActive, $id]);

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

        // Reassign servers and plans to default category (id 1)
        $pdo->prepare("UPDATE server_nodes SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE plans SET category_id = 1, server_group = 'default' WHERE category_id = ?")->execute([$id]);

        // Delete category
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);

        Helpers::logActivity('category_delete', "حذف دسته‌بندی شناسه {$id} و انتقال موارد وابسته به پیش‌فرض", 'category', (string)$id);
        Helpers::flash('info', 'دسته‌بندی حذف شد و سرورها و پلن‌های وابسته به دسته پیش‌فرض منتقل شدند.');
        Helpers::redirect('categories');
    }
}

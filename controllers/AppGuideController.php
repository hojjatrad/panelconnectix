<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class AppGuideController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $guides = $pdo->query("SELECT * FROM app_guides ORDER BY platform ASC, sort_order ASC, id ASC")->fetchAll();
        require __DIR__ . '/../views/settings/app_guides.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-guides');
        }

        $platform = trim($_POST['platform'] ?? 'android');
        $appName = trim($_POST['app_name'] ?? '');
        $downloadUrl = trim($_POST['download_url'] ?? '');
        $guideUrl = trim($_POST['guide_url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($appName) || empty($downloadUrl)) {
            Helpers::flash('error', 'نام نرم‌افزار و لینک دانلود الزامی هستند.');
            Helpers::redirect('settings/app-guides');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO app_guides (platform, app_name, download_url, guide_url, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$platform, $appName, $downloadUrl, $guideUrl, $description, $sortOrder]);

        Helpers::flash('success', "نرم‌افزار '{$appName}' با موفقیت افزوده شد.");
        Helpers::redirect('settings/app-guides');
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-guides');
        }

        $id = (int)($_POST['id'] ?? 0);
        $platform = trim($_POST['platform'] ?? 'android');
        $appName = trim($_POST['app_name'] ?? '');
        $downloadUrl = trim($_POST['download_url'] ?? '');
        $guideUrl = trim($_POST['guide_url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($id <= 0 || empty($appName) || empty($downloadUrl)) {
            Helpers::flash('error', 'اطلاعات نرم‌افزار معتبر نیست.');
            Helpers::redirect('settings/app-guides');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE app_guides SET platform = ?, app_name = ?, download_url = ?, guide_url = ?, description = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$platform, $appName, $downloadUrl, $guideUrl, $description, $sortOrder, $id]);

        Helpers::flash('success', "اطلاعات نرم‌افزار '{$appName}' با موفقیت به‌روزرسانی شد.");
        Helpers::redirect('settings/app-guides');
    }

    public function toggle(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-guides');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->query("UPDATE app_guides SET is_active = (1 - COALESCE(is_active, 1)) WHERE id = $id");
        Helpers::flash('info', 'وضعیت فعال بودن نرم‌افزار تغییر یافت.');
        Helpers::redirect('settings/app-guides');
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-guides');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->prepare("DELETE FROM app_guides WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'نرم‌افزار با موفقیت حذف گردید.');
        Helpers::redirect('settings/app-guides');
    }
}

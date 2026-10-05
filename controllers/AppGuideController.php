<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class AppGuideController {
    /**
     * Public download & connection guide center (Accessible to all clients and visitors)
     */
    public function publicIndex(): void {
        $pdo = Database::getConnection();
        $guides = [];
        try {
            $guides = $pdo->query("SELECT * FROM app_guides WHERE is_active = 1 ORDER BY platform ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        // Load app_release.json if present
        $manifestPath = dirname(__DIR__) . '/app_release.json';
        $manifest = [];
        if (is_file($manifestPath)) {
            $manifest = @json_decode(file_get_contents($manifestPath), true) ?: [];
        }

        require_once __DIR__ . '/../core/Setting.php';
        $brandName = Setting::get('brand_name', 'Connectix VPN');
        $logoUrl = Setting::get('brand_logo_url', '');
        $supportTelegram = Setting::get('telegram_support', Setting::get('telegram_bot_username', ''));
        $supportWhatsapp = Setting::get('whatsapp_support', '');

        require __DIR__ . '/../views/apps/download.php';
    }

    public function iosGuide(): void {
        $pdo = Database::getConnection();
        $manifestPath = dirname(__DIR__) . '/app_release.json';
        $manifest = [];
        if (is_file($manifestPath)) {
            $manifest = @json_decode(file_get_contents($manifestPath), true) ?: [];
        }
        require_once __DIR__ . '/../core/Setting.php';
        $brandName = Setting::get('brand_name', 'Connectix VPN');
        $logoUrl = Setting::get('brand_logo_url', '');
        require __DIR__ . '/../views/apps/ios_guide_complete.php';
    }

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

    public function resetDefaults(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-guides');
        }

        $pdo = Database::getConnection();
        $cxCount = (int)$pdo->query("SELECT COUNT(*) FROM app_guides WHERE app_name LIKE '%Connectix%'")->fetchColumn();
        if ($cxCount === 0) {
            $stmtIns = $pdo->prepare("INSERT INTO app_guides (platform, app_name, download_url, guide_url, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmtIns->execute([
                'android',
                '🚀 Connectix Android (اپلیکیشن اختصاصی - پیشنهادی)',
                'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.8/Connectix-Android-Universal.apk',
                '',
                'نرم‌افزار رسمی و اختصاصی با ورود آسان تنها با نام کاربری و پسورد، بدون نیاز به کانفیگ دستی و تست خودکار پینگ',
                0
            ]);
            $stmtIns->execute([
                'windows',
                '🚀 Connectix Windows (نرم‌افزار اختصاصی ویندوز - پیشنهادی)',
                'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.8/Connectix-Windows-x64.zip',
                '',
                'کلاینت اختصاصی ویندوز با تونل کل ترافیک سیستم (VPN Mode) و اتصال ۱ کلیک فوق‌العاده سریع',
                0
            ]);
            Helpers::flash('success', 'اپلیکیشن‌های اختصاصی اندروید و ویندوز با موفقیت به لیست نرم‌افزارها افزوده شدند.');
        } else {
            Helpers::flash('info', 'نرم‌افزارهای اختصاصی از قبل در لیست موجود هستند.');
        }
        Helpers::redirect('settings/app-guides');
    }
}

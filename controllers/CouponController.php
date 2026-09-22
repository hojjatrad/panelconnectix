<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class CouponController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
        require __DIR__ . '/../views/settings/coupons.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/coupons');
        }

        $code = strtoupper(trim($_POST['code'] ?? ''));
        $percent = (int)($_POST['discount_percent'] ?? 10);
        $maxUses = (int)($_POST['max_uses'] ?? 100);
        $expireAt = !empty($_POST['expire_at']) ? $_POST['expire_at'] . ' 23:59:59' : null;

        if (empty($code) || $percent <= 0 || $percent > 100) {
            Helpers::flash('error', 'کد تخفیف و درصد باید معتبر باشند (۱ الی ۱۰۰٪).');
            Helpers::redirect('settings/coupons');
        }

        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_percent, max_uses, expire_at, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$code, $percent, $maxUses, $expireAt]);
            Helpers::flash('success', "کد تخفیف '{$code}' با موفقیت تعریف شد.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'این کد تخفیف قبلاً تعریف شده است.');
        }

        Helpers::redirect('settings/coupons');
    }

    public function toggle(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/coupons');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->query("UPDATE coupons SET is_active = (1 - COALESCE(is_active, 1)) WHERE id = $id");
        Helpers::flash('info', 'وضعیت کد تخفیف تغییر یافت.');
        Helpers::redirect('settings/coupons');
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/coupons');
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        Helpers::flash('success', 'کد تخفیف با موفقیت حذف گردید.');
        Helpers::redirect('settings/coupons');
    }
}

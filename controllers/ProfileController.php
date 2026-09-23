<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class ProfileController {
    public function show(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();

        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $currentUser = $stmt->fetch();

        require_once __DIR__ . '/../core/TwoFactor.php';
        $pendingSecret = $_SESSION['pending_2fa_secret'] ?? null;
        if (empty($currentUser['two_factor_enabled']) && empty($pendingSecret)) {
            $pendingSecret = TwoFactor::generateSecret();
            $_SESSION['pending_2fa_secret'] = $pendingSecret;
        } elseif (!empty($currentUser['two_factor_enabled'])) {
            $pendingSecret = $currentUser['two_factor_secret'];
        }

        $otpUrl = TwoFactor::getOtpAuthUrl($currentUser['username'], $pendingSecret ?? '', 'Connectix');
        $qrUrl = TwoFactor::getQrCodeUrl($otpUrl);

        require __DIR__ . '/../views/settings/profile.php';
    }

    public function enable2fa(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('profile');
        }

        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['pending_2fa_secret'] ?? '';

        require_once __DIR__ . '/../core/TwoFactor.php';
        if (empty($code) || empty($secret) || !TwoFactor::verifyCode($secret, $code)) {
            Helpers::flash('error', 'کد ۶ رقمی وارد شده اشتباه یا منقضی است. لطفاً ساعت گوشی خود را بررسی کنید.');
            Helpers::redirect('profile');
        }

        $userId = Auth::id();
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?")->execute([$secret, $userId]);
        unset($_SESSION['pending_2fa_secret']);

        Helpers::logActivity('security_2fa_enabled', 'فعال‌سازی ورود دوعاملی گوگل', 'user', $userId);
        Helpers::flash('success', 'ورود دوعاملی (Google Authenticator) با موفقیت فعال شد.');
        Helpers::redirect('profile');
    }

    public function disable2fa(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('profile');
        }

        $password = trim($_POST['password'] ?? '');
        $userId = Auth::id();
        $pdo = Database::getConnection();
        $hash = $pdo->query("SELECT password_hash FROM users WHERE id = {$userId}")->fetchColumn();

        if (!password_verify($password, $hash)) {
            Helpers::flash('error', 'کلمه عبور جهت غیرفعال‌سازی اشتباه است.');
            Helpers::redirect('profile');
        }

        $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?")->execute([$userId]);
        unset($_SESSION['pending_2fa_secret']);

        Helpers::logActivity('security_2fa_disabled', 'غیرفعال‌سازی ورود دوعاملی', 'user', $userId);
        Helpers::flash('info', 'ورود دوعاملی با موفقیت غیرفعال شد.');
        Helpers::redirect('profile');
    }

    public function updatePassword(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('profile');
        }

        $userId = Auth::id();
        $currentPass = trim($_POST['current_password'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        if (empty($currentPass) || empty($newPass)) {
            Helpers::flash('error', 'کلمه عبور فعلی و جدید الزامی هستند.');
            Helpers::redirect('profile');
        }

        if (strlen($newPass) < 6) {
            Helpers::flash('error', 'کلمه عبور جدید باید حداقل ۶ کاراکتر باشد.');
            Helpers::redirect('profile');
        }

        if ($newPass !== $confirmPass) {
            Helpers::flash('error', 'کلمه عبور جدید با تکرار آن یکسان نیست.');
            Helpers::redirect('profile');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $hash)) {
            Helpers::flash('error', 'کلمه عبور فعلی اشتباه است.');
            Helpers::redirect('profile');
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);

        Helpers::flash('success', 'کلمه عبور با موفقیت به‌روزرسانی شد.');
        Helpers::redirect('profile');
    }

    public function regenerateToken(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('profile');
        }

        $userId = Auth::id();
        $newToken = 'api_' . bin2hex(random_bytes(16));

        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?")->execute([$newToken, $userId]);

        Helpers::flash('success', 'کلید API جدید با موفقیت صادر شد.');
        Helpers::redirect('profile');
    }
}

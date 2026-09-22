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

        require __DIR__ . '/../views/settings/profile.php';
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

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

class AuthController {
    public function showLogin(): void {
        if (Auth::check()) {
            Helpers::redirect('dashboard');
        }

        // Feature: Instant Magic Token Login from Telegram Bot
        $magicToken = trim($_GET['magic_token'] ?? '');
        if (!empty($magicToken)) {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE magic_login_token = ? AND magic_login_expires > ? LIMIT 1");
            $stmt->execute([$magicToken, date('Y-m-d H:i:s')]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                // Invalidate single-use token
                $pdo->prepare("UPDATE users SET magic_login_token = NULL, magic_login_expires = NULL WHERE id = ?")->execute([$u['id']]);
                Auth::loginById((int)$u['id']);
                Helpers::logActivity('magic_login', "ورود فوری و امن از تلگرام برای کاربر {$u['username']}", 'user', $u['id']);
                Helpers::flash('success', "خوش آمدید {$u['username']} عزیز! ورود امن شما از طریق تلگرام با موفقیت انجام شد.");
                Helpers::redirect('dashboard');
                return;
            } else {
                Helpers::flash('error', 'لینک ورود مستقیم منقضی شده یا نامعتبر است. لطفاً از ربات تلگرام مجدداً درخواست دهید.');
            }
        }

        require __DIR__ . '/../views/auth/login.php';
    }

    public function doLogin(): void {
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'خطای امنیتی: توکن CSRF نامعتبر است.');
            Helpers::redirect('login');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $twoFactorCode = trim($_POST['two_factor_code'] ?? '');

        if (empty($username) || empty($password)) {
            Helpers::flash('error', 'نام کاربری و رمز عبور الزامی است.');
            Helpers::redirect('login');
        }

        $res = Auth::login($username, $password, $twoFactorCode);

        if ($res['success']) {
            unset($_SESSION['2fa_pending_username'], $_SESSION['2fa_pending_password']);
            Helpers::logActivity('auth_login', "ورود موفق کاربر {$username} به سامانه", 'user', Auth::id());
            Helpers::flash('success', 'با موفقیت وارد شدید. خوش آمدید!');
            Helpers::redirect('dashboard');
        } elseif ($res['reason'] === 'requires_2fa') {
            $_SESSION['2fa_pending_username'] = $username;
            $_SESSION['2fa_pending_password'] = $password;
            Helpers::redirect('login?step=2fa');
        } elseif ($res['reason'] === 'invalid_2fa') {
            Helpers::flash('error', 'کد ورود دوعاملی اشتباه است یا منقضی شده است.');
            Helpers::redirect('login?step=2fa');
        } else {
            Helpers::logActivity('auth_failed', "تلاش ناموفق برای ورود با نام کاربری '{$username}'", 'user', null);
            Helpers::flash('error', 'نام کاربری یا رمز عبور اشتباه است.');
            Helpers::redirect('login');
        }
    }

    public function logout(): void {
        $username = Auth::user()['username'] ?? 'کاربر';
        $userId = Auth::id();
        Auth::logout();
        Helpers::logActivity('auth_logout', "خروج کاربر {$username} از سامانه", 'user', $userId, $userId);
        Helpers::flash('info', 'از حساب کاربری خود خارج شدید.');
        Helpers::redirect('login');
    }
}

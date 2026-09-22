<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

class AuthController {
    public function showLogin(): void {
        if (Auth::check()) {
            Helpers::redirect('dashboard');
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

        if (empty($username) || empty($password)) {
            Helpers::flash('error', 'نام کاربری و رمز عبور الزامی است.');
            Helpers::redirect('login');
        }

        if (Auth::login($username, $password)) {
            Helpers::logActivity('auth_login', "ورود موفق کاربر {$username} به سامانه", 'user', Auth::id());
            Helpers::flash('success', 'با موفقیت وارد شدید. خوش آمدید!');
            Helpers::redirect('dashboard');
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

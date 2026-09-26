<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

class AuthController {
    /**
     * Brute-force protection: max 5 failed attempts per (IP + username)
     * window; 15-minute lockout afterwards. State lives in system_settings.
     */
    private function loginLocked(): ?int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $username = trim((string)($_POST['username'] ?? ''));
        $lockKey = 'login_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        $state = json_decode((string)Setting::get($lockKey, ''), true);
        if (!is_array($state)) {
            return null;
        }
        if (!empty($state['locked_until']) && $state['locked_until'] > time()) {
            return (int)ceil(($state['locked_until'] - time()) / 60);
        }
        return null;
    }

    private function recordFailedLogin(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $username = trim((string)($_POST['username'] ?? ''));
        $lockKey = 'login_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        $state = json_decode((string)Setting::get($lockKey, ''), true);
        if (!is_array($state)) {
            $state = ['count' => 0, 'first' => time(), 'locked_until' => 0];
        }
        $state['count'] = (int)$state['count'] + 1;
        if ($state['count'] >= 5) {
            $state['locked_until'] = time() + 900;
            $state['count'] = 0;
            $state['last_lock_at'] = time();
            Helpers::logActivity('auth_lockout', "قفل موقت ورود (۱۵ دقیقه) برای {$username} از IP: {$ip}", 'user');
        }
        Setting::set($lockKey, json_encode($state));
    }

    private function clearLoginLock(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $username = trim((string)($_POST['username'] ?? ''));
        $lockKey = 'login_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        Setting::set($lockKey, '');
    }

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

        // Brute-force lockout check (per IP+username)
        $waitMin = $this->loginLocked();
        if ($waitMin !== null) {
            Helpers::flash('error', "به دلیل تلاش‌های مکرر ناموفق، ورود موقتاً قفل شده است. {$waitMin} دقیقه دیگر مجدداً امتحان کنید.");
            Helpers::redirect('login');
        }

        $res = Auth::login($username, $password, $twoFactorCode);

        if ($res['success']) {
            $this->clearLoginLock();
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
            if ($res['reason'] === 'invalid_credentials') {
                $this->recordFailedLogin();
            }
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

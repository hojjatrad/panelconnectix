<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';

class Auth {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $username, string $password): bool {
        self::init();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    }

    public static function check(): bool {
        self::init();
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        self::init();
        return $_SESSION['role'] ?? null;
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isReseller(): bool {
        return self::role() === 'reseller';
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT u.*, b.brand_name, b.theme_color, b.logo_url, b.telegram_support, b.whatsapp_support, b.welcome_message 
                               FROM users u 
                               LEFT JOIN branding_metadata b ON b.user_id = u.id 
                               WHERE u.id = ? LIMIT 1");
        $stmt->execute([self::id()]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            Helpers::flash('error', 'لطفاً ابتدا وارد حساب کاربری خود شوید.');
            Helpers::redirect('login');
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            Helpers::flash('error', 'دسترسی غیرمجاز! این بخش مخصوص مدیریت کل سیستم است.');
            Helpers::redirect('dashboard');
        }
    }

    public static function logout(): void {
        self::init();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

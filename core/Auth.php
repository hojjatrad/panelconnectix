<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';

class Auth {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $username, string $password, ?string $twoFactorCode = null): array {
        self::init();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'reason' => 'invalid_credentials'];
        }

        // Check 2FA
        if (!empty($user['two_factor_enabled'])) {
            require_once __DIR__ . '/TwoFactor.php';
            if (empty($twoFactorCode)) {
                return ['success' => false, 'reason' => 'requires_2fa', 'user_id' => $user['id']];
            }
            if (!TwoFactor::verifyCode($user['two_factor_secret'] ?? '', $twoFactorCode)) {
                return ['success' => false, 'reason' => 'invalid_2fa'];
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return ['success' => true, 'user' => $user];
    }

    public static function loginById(int $userId): bool {
        self::init();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
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
        if (!empty($_SESSION['role'])) {
            return $_SESSION['role'];
        }
        if (!empty($_SESSION['user_id'])) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $role = $stmt->fetchColumn();
                if ($role) {
                    $_SESSION['role'] = $role;
                    return $role;
                }
            } catch (Throwable $e) {}
        }
        return null;
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

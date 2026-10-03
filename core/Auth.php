<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';

class Auth {
    public static function init(): void {
        // v6.8.7 FIX: Bulletproof session - always ensure correct path
        $savePath = __DIR__ . '/../data/sessions';
        $tmpPath = __DIR__ . '/../data/tmp';
        if (!is_dir($savePath)) { @mkdir($savePath, 0755, true); }
        if (!is_dir($tmpPath)) { @mkdir($tmpPath, 0755, true); }
        if (is_dir($savePath) && is_writable($savePath)) {
            $current = ini_get('session.save_path');
            $needFix = false;
            if (empty($current)) $needFix = true;
            elseif (strpos($current, 'ea-php84') !== false) $needFix = true;
            elseif (!@is_dir($current)) $needFix = true;
            elseif (!@is_writable($current)) $needFix = true;
            elseif ($current === '/tmp' || $current === sys_get_temp_dir()) $needFix = true;
            if ($needFix) {
                @ini_set('session.save_path', $savePath);
            }
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public static function login(string $username, string $password, ?string $twoFactorCode = null): array {
        self::init();
        
        // S1: Rate Limit check
        require_once __DIR__ . '/RateLimiter.php';
        $rateKey = RateLimiter::getClientKey('login_');
        $rateCheck = RateLimiter::check($rateKey, 5, 300);
        if (!$rateCheck['allowed']) {
            require_once __DIR__ . '/SecurityLogger.php';
            SecurityLogger::log('login_rate_limited', "Rate limited: $username - {$rateCheck['reason']}");
            return ['success' => false, 'reason' => 'rate_limited', 'message' => $rateCheck['message']];
        }
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($rateKey);
            require_once __DIR__ . '/SecurityLogger.php';
            SecurityLogger::log('login_failed', "Invalid credentials for: $username");
            return ['success' => false, 'reason' => 'invalid_credentials'];
        }

        // Check 2FA
        if (!empty($user['two_factor_enabled'])) {
            require_once __DIR__ . '/TwoFactor.php';
            if (empty($twoFactorCode)) {
                return ['success' => false, 'reason' => 'requires_2fa', 'user_id' => $user['id']];
            }
            if (!TwoFactor::verifyCode($user['two_factor_secret'] ?? '', $twoFactorCode)) {
                RateLimiter::hit($rateKey);
                require_once __DIR__ . '/SecurityLogger.php';
                SecurityLogger::log('login_failed', "Invalid 2FA for: $username");
                return ['success' => false, 'reason' => 'invalid_2fa'];
            }
        }

        // Success - clear rate limit
        RateLimiter::clear($rateKey);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        require_once __DIR__ . '/SecurityLogger.php';
        SecurityLogger::log($user['role'] === 'admin' ? 'admin_login' : 'login_success', "User logged in: $username", $user['id']);
        
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
        // Demo user: block all POST/DELETE/PUT (view-only)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && self::isDemo()) {
            // Allow only login and demo page itself, block everything else that modifies
            $route = $_GET['route'] ?? '';
            // Allow login, demo, promo, logout
            $allowed = ['login', 'demo', 'promo', 'logout'];
            $isAllowed = false;
            foreach ($allowed as $a) {
                if (str_starts_with($route, $a)) { $isAllowed = true; break; }
            }
            if (!$isAllowed) {
                self::blockDemo('انجام عملیات');
            }
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            Helpers::flash('error', 'دسترسی غیرمجاز! این بخش مخصوص مدیریت کل سیستم است.');
            Helpers::redirect('dashboard');
        }
    }

    public static function isDemo(): bool {
        self::init();
        $username = $_SESSION['username'] ?? '';
        if ($username === 'demo') return true;
        try {
            $user = self::user();
            if ($user && ($user['username'] ?? '') === 'demo') return true;
        } catch (Throwable $e) {}
        return false;
    }

    public static function blockDemo(string $action = 'این عملیات'): void {
        if (self::isDemo()) {
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => '👁️ نسخه دمو فقط برای نمایش است - امکان ' . $action . ' وجود ندارد. برای پنل واقعی به @mainAdminpanel پیام دهید.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $siteUrl = Helpers::panelDomain();
            Helpers::flash('error', '👁️ نسخه دمو فقط برای نمایش است - امکان ' . $action . ' وجود ندارد. برای پنل واقعی به @mainAdminpanel پیام دهید. (سایت: ' . $siteUrl . ')');
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if ($referer !== '') {
                header('Location: ' . $referer);
            } else {
                Helpers::redirect('dashboard');
            }
            exit;
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

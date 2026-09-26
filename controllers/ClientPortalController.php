<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

/**
 * Client Self-Service Portal (public page /client)
 *
 * End-customers log in with their username + password (the same credentials
 * the app uses) and see live status, traffic, sublink, QR and one-tap
 * renewal/support links — no panel account required.
 */
class ClientPortalController {

    private function lockState(string $username): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'portal_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        $state = json_decode((string)Setting::get($key, ''), true);
        return is_array($state) ? $state : ['count' => 0, 'locked_until' => 0];
    }

    private function lockedMinutes(string $username): ?int
    {
        $state = $this->lockState($username);
        if (!empty($state['locked_until']) && $state['locked_until'] > time()) {
            return (int)ceil(($state['locked_until'] - time()) / 60);
        }
        return null;
    }

    private function recordFail(string $username): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'portal_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        $state = $this->lockState($username);
        $state['count'] = (int)$state['count'] + 1;
        if ($state['count'] >= 5) {
            $state['locked_until'] = time() + 900;
            $state['count'] = 0;
        }
        Setting::set($key, json_encode($state));
    }

    private function clearFail(string $username): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'portal_lock_' . substr(sha1($ip . '|' . $username), 0, 24);
        Setting::set($key, '');
    }

    private function currentClient(): ?array
    {
        $id = (int)($_SESSION['portal_client_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT c.*, s.name as server_name,
                                      COALESCE(u.brand_name, b.brand_name, 'Connectix VPN') as brand_name,
                                      COALESCE(u.logo_url, b.logo_url) as logo_url,
                                      COALESCE(u.theme_color, b.theme_color, 'violet') as theme_color,
                                      COALESCE(u.support_username, b.telegram_support) as telegram_support,
                                      b.whatsapp_support, b.renewal_url,
                                      COALESCE(u.telegram_bot_username, '') as reseller_bot_username,
                                      p.title as plan_title
                               FROM clients c
                               LEFT JOIN server_nodes s ON c.server_id = s.id
                               LEFT JOIN users u ON u.id = c.reseller_id
                               LEFT JOIN branding_metadata b ON b.user_id = c.reseller_id
                               LEFT JOIN plans p ON p.id = c.plan_id
                               WHERE c.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function index(): void
    {
        $client = $this->currentClient();
        $error = '';
        $flash = '';
        require __DIR__ . '/../views/client_portal/index.php';
    }

    public function login(): void
    {
        $client = $this->currentClient();
        if (!Helpers::verifyCsrf()) {
            $error = 'توکن امنیتی نامعتبر است.';
            require __DIR__ . '/../views/client_portal/index.php';
            return;
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = trim((string)($_POST['password'] ?? ''));

        if ($username === '' || $password === '') {
            $error = 'نام کاربری و کلمه عبور الزامی است.';
            require __DIR__ . '/../views/client_portal/index.php';
            return;
        }

        $waitMin = $this->lockedMinutes($username);
        if ($waitMin !== null) {
            $error = "تلاش‌های مکرر ناموفق؛ {$waitMin} دقیقه دیگر امتحان کنید.";
            require __DIR__ . '/../views/client_portal/index.php';
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username FROM clients WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $client = $stmt->fetch();

        if (!$client || (string)$client['password'] !== $password) {
            $this->recordFail($username);
            Helpers::logActivity('portal_login_failed', "تلاش ناموفق ورود به پورتال مشتری: {$username}", 'client');
            $error = 'نام کاربری یا کلمه عبور اشتباه است.';
            require __DIR__ . '/../views/client_portal/index.php';
            return;
        }

        $this->clearFail($username);
        session_regenerate_id(true);
        $_SESSION['portal_client_id'] = (int)$client['id'];
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        Helpers::logActivity('portal_login', "ورود مشتری {$username} به پورتال خودخدمت", 'client');
        header('Location: ' . Helpers::url('client'));
        exit;
    }

    public function logout(): void
    {
        unset($_SESSION['portal_client_id']);
        header('Location: ' . Helpers::url('client'));
        exit;
    }
}

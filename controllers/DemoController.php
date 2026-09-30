<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

/**
 * Demo Controller - View Only
 * Route: demo
 * No login required for viewing, but if logged in as demo, block all writes
 */
class DemoController {
    public function index(): void {
        // Ensure demo user exists
        try {
            $pdo = Database::getConnection();
            Database::ensureExtendedTablesExist($pdo);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute(['demo']);
            $demo = $stmt->fetch();
            if (!$demo) {
                $hash = password_hash('demo123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)")
                    ->execute(['demo', $hash, 'کاربر دمو - فقط دیدنی', 'reseller', 'active', date('Y-m-d H:i:s')]);
            }
        } catch (Throwable $e) {}

        // If user is logged in and is demo, show view-only dashboard
        // If not logged in, show demo landing with login form
        $isLoggedDemo = false;
        try {
            if (Auth::check() && Auth::isDemo()) {
                $isLoggedDemo = true;
            }
        } catch (Throwable $e) {}

        // For public demo page, we show standalone demo view
        // This file is accessible via /demo/ folder directly, but also via route
        $demoFile = __DIR__ . '/../demo/index.php';
        if (file_exists($demoFile)) {
            require $demoFile;
            exit;
        }

        // Fallback
        require __DIR__ . '/../views/demo/index.php';
    }

    public function login(): void {
        // Auto login as demo
        Auth::init();
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute(['demo']);
            $user = $stmt->fetch();
            if (!$user) {
                $hash = password_hash('demo123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)")
                    ->execute(['demo', $hash, 'کاربر دمو - فقط دیدنی', 'reseller', 'active', date('Y-m-d H:i:s')]);
                $stmt->execute(['demo']);
                $user = $stmt->fetch();
            }
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                Helpers::flash('info', '👁️ وارد نسخه دمو فقط-دیدنی شدید - امکان ساخت و تغییر وجود ندارد. برای پنل واقعی به @mainAdminpanel پیام دهید.');
                Helpers::redirect('dashboard');
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ورود دمو: ' . $e->getMessage());
        }
        Helpers::redirect('login');
    }
}

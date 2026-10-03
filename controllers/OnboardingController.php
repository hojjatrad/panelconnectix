<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class OnboardingController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $userId = Auth::id();
        try {
            $stmt = $pdo->prepare("SELECT * FROM onboarding_progress WHERE user_id=?");
            $stmt->execute([$userId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$progress) {
                $pdo->prepare("INSERT INTO onboarding_progress (user_id, step, completed) VALUES (?, 0, 0)")->execute([$userId]);
                $progress = ['step'=>0,'completed'=>0];
            }
        } catch (Throwable $e) { $progress = ['step'=>0,'completed'=>0]; }

        $serversCount = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1")->fetchColumn();
        $plansCount = (int)$pdo->query("SELECT COUNT(*) FROM plans WHERE is_active=1")->fetchColumn();
        $hasBot = !empty(Setting::get('telegram_bot_token'));
        $hasBackup = (int)$pdo->query("SELECT COUNT(*) FROM server_backups")->fetchColumn() > 0;

        require __DIR__ . '/../views/onboarding/index.php';
    }

    public function complete(): void {
        Auth::requireAdmin();
        Helpers::verifyCsrf();
        $pdo = Database::getConnection();
        $userId = Auth::id();
        try {
            $pdo->prepare("UPDATE onboarding_progress SET completed=1, step=4, updated_at=NOW() WHERE user_id=?")->execute([$userId]);
        } catch (Throwable $e) {}
        Helpers::flash('success','🎉 راه‌اندازی اولیه کامل شد! پنل شما آماده است.');
        Helpers::redirect('dashboard');
    }

    public function skip(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $userId = Auth::id();
        try {
            $pdo->prepare("UPDATE onboarding_progress SET completed=1, updated_at=NOW() WHERE user_id=?")->execute([$userId]);
        } catch (Throwable $e) {}
        Helpers::redirect('dashboard');
    }
}

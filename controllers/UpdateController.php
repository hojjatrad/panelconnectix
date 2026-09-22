<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Updater.php';

class UpdateController {
    public function index(): void {
        Auth::requireAdmin();

        $updateInfo = Updater::checkForUpdates(false);
        $repo = Updater::getRepo();
        $branch = Updater::getBranch();
        $token = Updater::getToken();
        $currentVersion = Updater::CURRENT_VERSION;

        require __DIR__ . '/../views/settings/updater.php';
    }

    public function checkNow(): void {
        Auth::requireAdmin();
        $updateInfo = Updater::checkForUpdates(true);

        if ($updateInfo['has_update']) {
            Helpers::flash('info', "نسخه جدید {$updateInfo['latest_version']} در گیت‌هاب در دسترس است!");
        } else {
            Helpers::flash('success', "پنل شما به‌روز است. نگارش فعال: " . Updater::CURRENT_VERSION);
        }

        Helpers::redirect('updater');
    }

    public function apply(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('updater');
        }

        $result = Updater::applyUpdate();
        if ($result['success']) {
            Helpers::flash('success', $result['message']);
        } else {
            Helpers::flash('error', 'خطا در ارتقا: ' . $result['error']);
        }

        Helpers::redirect('updater');
    }

    public function saveSettings(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('updater');
        }

        $repo = trim($_POST['github_repo'] ?? '');
        $branch = trim($_POST['github_branch'] ?? 'main');
        $token = trim($_POST['github_token'] ?? '');
        $autoApply = !empty($_POST['auto_apply_github_updates']) ? '1' : '0';

        // Remove https://github.com/ if user pasted full URL
        $repo = preg_replace('#^https?://github\.com/#i', '', $repo);
        $repo = rtrim($repo, '/.git');

        Setting::set('github_repo', $repo);
        Setting::set('github_branch', $branch);
        Setting::set('github_token', $token);
        Setting::set('auto_apply_github_updates', $autoApply);

        // Invalidate check cache
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');

        Helpers::flash('success', 'تنظیمات مخزن گیت‌هاب با موفقیت ذخیره شد.');
        Helpers::redirect('updater');
    }

    public function gitPushAction(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('updater');
        }

        $remoteUrl = trim($_POST['git_remote_url'] ?? '');
        $commitMsg = trim($_POST['git_commit_msg'] ?? 'Update Connectix Panel codebase');

        if (empty($remoteUrl)) {
            Helpers::flash('error', 'آدرس مخزن ریموت گیت‌هاب الزامی است.');
            Helpers::redirect('updater');
        }

        $panelDir = realpath(__DIR__ . '/..');

        // Check if git is available
        $cmd = "cd " . escapeshellarg($panelDir) . " && git init && git config user.name 'Connectix Admin' && git config user.email 'admin@connectix.local' && git add -A && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
        $output = shell_exec($cmd);

        $cmdRemote = "cd " . escapeshellarg($panelDir) . " && git remote remove origin 2>/dev/null; git remote add origin " . escapeshellarg($remoteUrl) . " 2>&1";
        shell_exec($cmdRemote);

        $cmdPush = "cd " . escapeshellarg($panelDir) . " && git branch -M main && git push -u origin main 2>&1";
        $pushOutput = shell_exec($cmdPush);

        Helpers::logActivity('git_push', "ارسال فایل‌های پنل به گیت‌هاب: {$remoteUrl}", 'system');
        Helpers::flash('info', "فرمان Git اجرا شد:\n" . substr($pushOutput, 0, 300));
        Helpers::redirect('updater');
    }

    public function webhook(): void {
        header('Content-Type: application/json; charset=utf-8');
        $secret = $_GET['secret'] ?? '';
        $expected = Setting::get('github_webhook_secret', APP_SECRET);
        if (empty($secret) || $secret !== $expected) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized webhook secret']);
            exit;
        }

        $res = Updater::applyUpdate();
        if ($res['success']) {
            try {
                require_once __DIR__ . '/../core/TelegramBot.php';
                TelegramBot::sendMessage("⚡️ <b>آپدیت آنی گیت‌هاب با وب‌هوک اعمال شد!</b>\n\nتغییرات جدید مستقیماً از مخزن گیت‌هاب دریافت و روی پنل هاست مستقر گردید.");
            } catch (Throwable $e) {}
        }
        echo json_encode($res);
        exit;
    }
}

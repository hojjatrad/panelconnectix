<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class NotificationController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $notifications = $pdo->query("SELECT * FROM notifications ORDER BY id DESC")->fetchAll();
        require __DIR__ . '/../views/notifications/index.php';
    }

    public function store(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('notifications');
        }

        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target = trim($_POST['target_role'] ?? 'all');
        $userId = Auth::id();

        if (empty($title) || empty($message)) {
            Helpers::flash('error', 'عنوان و متن پیام الزامی است.');
            Helpers::redirect('notifications');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, target_role, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $message, $target, $userId]);

        $broadcastCount = 0;
        if (!empty($_POST['send_telegram'])) {
            require_once __DIR__ . '/../core/TelegramBot.php';
            try {
                $tgUsers = $pdo->query("SELECT DISTINCT user_tg_id FROM bot_orders WHERE user_tg_id IS NOT NULL UNION SELECT tg_id FROM bot_sessions")->fetchAll(PDO::FETCH_COLUMN);
                $broadcastMsg = "📢 <b>اطلاعیه مهم: {$title}</b>\n\n{$message}";
                foreach ($tgUsers as $tgId) {
                    if (!empty($tgId)) {
                        TelegramBot::sendMessage($broadcastMsg, (string)$tgId);
                        $broadcastCount++;
                    }
                }
            } catch (Throwable $e) {}
        }

        if ($broadcastCount > 0) {
            Helpers::flash('success', "اطلاعیه با موفقیت ثبت و برای {$broadcastCount} کاربر ربات تلگرام ارسال شد.");
        } else {
            Helpers::flash('success', 'اطلاعیه همگانی با موفقیت ارسال شد.');
        }
        Helpers::redirect('notifications');
    }
}

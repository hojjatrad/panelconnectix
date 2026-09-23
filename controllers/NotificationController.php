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
        $segment = trim($_POST['target_segment'] ?? 'all');
        $cluster = trim($_POST['target_cluster'] ?? '');
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
                $tgIds = [];
                if ($segment === 'expired') {
                    // Only expired users
                    $tgIds = $pdo->query("SELECT DISTINCT telegram_chat_id FROM clients WHERE status = 'expired' AND telegram_chat_id IS NOT NULL 
                                          UNION 
                                          SELECT DISTINCT bo.user_tg_id FROM bot_orders bo JOIN clients c ON bo.client_id = c.id WHERE c.status = 'expired' AND bo.user_tg_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($segment === 'active') {
                    // Only active users
                    $tgIds = $pdo->query("SELECT DISTINCT telegram_chat_id FROM clients WHERE status = 'active' AND telegram_chat_id IS NOT NULL 
                                          UNION 
                                          SELECT DISTINCT bo.user_tg_id FROM bot_orders bo JOIN clients c ON bo.client_id = c.id WHERE c.status = 'active' AND bo.user_tg_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($segment === 'cluster' && !empty($cluster)) {
                    // Users on a specific server cluster
                    $stmtClust = $pdo->prepare("SELECT DISTINCT c.telegram_chat_id FROM clients c JOIN server_nodes s ON c.server_id = s.id WHERE s.server_group = ? AND c.telegram_chat_id IS NOT NULL");
                    $stmtClust->execute([$cluster]);
                    $tgIds = $stmtClust->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    // All bot users
                    $tgIds = $pdo->query("SELECT DISTINCT telegram_id FROM bot_users UNION SELECT DISTINCT user_tg_id FROM bot_orders WHERE user_tg_id IS NOT NULL UNION SELECT DISTINCT tg_id FROM bot_sessions")->fetchAll(PDO::FETCH_COLUMN);
                }

                $broadcastMsg = "📢 <b>{$title}</b>\n\n{$message}";
                foreach ($tgIds as $tgId) {
                    if (!empty($tgId)) {
                        TelegramBot::sendMessage($broadcastMsg, (string)$tgId);
                        $broadcastCount++;
                    }
                }
            } catch (Throwable $e) {}
        }

        if ($broadcastCount > 0) {
            Helpers::flash('success', "اطلاعیه با موفقیت ثبت و به صورت تفکیک‌شده برای {$broadcastCount} کاربر ربات ارسال گردید.");
        } else {
            Helpers::flash('success', 'اطلاعیه با موفقیت در تابلوی اعلانات ثبت شد.');
        }
        Helpers::redirect('notifications');
    }
}

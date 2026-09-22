<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';

class TicketController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $user = Auth::user();

        if (Auth::isAdmin()) {
            $statusFilter = trim($_GET['status'] ?? '');
            $where = "1=1";
            $params = [];
            if (!empty($statusFilter)) {
                $where .= " AND t.status = ?";
                $params[] = $statusFilter;
            }

            $stmt = $pdo->prepare("SELECT t.*, u.username, u.full_name, u.brand_name,
                                          (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as msg_count
                                   FROM tickets t
                                   JOIN users u ON t.user_id = u.id
                                   WHERE $where
                                   ORDER BY 
                                     CASE t.status 
                                       WHEN 'open' THEN 1 
                                       WHEN 'waiting_reseller' THEN 2 
                                       WHEN 'answered' THEN 3 
                                       ELSE 4 
                                     END, t.updated_at DESC");
            $stmt->execute($params);
            $tickets = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT t.*, 
                                          (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as msg_count
                                   FROM tickets t
                                   WHERE t.user_id = ?
                                   ORDER BY t.updated_at DESC");
            $stmt->execute([$user['id']]);
            $tickets = $stmt->fetchAll();
        }

        require __DIR__ . '/../views/tickets/index.php';
    }

    public function create(): void {
        Auth::requireLogin();
        require __DIR__ . '/../views/tickets/create.php';
    }

    public function store(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('tickets');
        }

        $userId = Auth::id();
        $subject = trim($_POST['subject'] ?? '');
        $department = trim($_POST['department'] ?? 'فنی');
        $priority = trim($_POST['priority'] ?? 'medium');
        $message = trim($_POST['message'] ?? '');

        if (empty($subject) || empty($message)) {
            Helpers::flash('error', 'لطفاً موضوع و متن پیام را تکمیل فرمایید.');
            Helpers::redirect('tickets/create');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, department, priority, status, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, 'open', datetime('now'), datetime('now'))");
            $stmt->execute([$userId, $subject, $department, $priority]);
            $ticketId = (int)$pdo->lastInsertId();

            $stmtMsg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, created_at) 
                                      VALUES (?, ?, ?, datetime('now'))");
            $stmtMsg->execute([$ticketId, $userId, $message]);
            $pdo->commit();

            // Notify Admin via Telegram
            $user = Auth::user();
            $tgNotice = "📩 <b>تیکت پشتیبانی جدید (#{$ticketId})</b>\n\n"
                      . "👤 ارسال‌کننده: <b>{$user['username']}</b> (" . ($user['brand_name'] ?? 'نماینده') . ")\n"
                      . "🏷 موضوع: <b>{$subject}</b>\n"
                      . "🏢 دپارتمان: {$department} | اولویت: {$priority}\n\n"
                      . "📝 متن پیام:\n<i>" . htmlspecialchars(mb_substr($message, 0, 200)) . "...</i>";
            
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🌐 باز کردن در پنل', 'url' => Helpers::fullUrl('tickets/show?id=' . $ticketId)]]
                ]
            ];
            TelegramBot::sendCategorizedReport('users', $tgNotice, $kb);

            Helpers::flash('success', 'تیکت شما با موفقیت ارسال شد و به زودی پاسخ داده خواهد شد.');
            Helpers::redirect('tickets/show?id=' . $ticketId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ثبت تیکت: ' . $e->getMessage());
            Helpers::redirect('tickets/create');
        }
    }

    public function show(): void {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT t.*, u.username, u.full_name, u.role, u.brand_name 
                               FROM tickets t 
                               JOIN users u ON t.user_id = u.id 
                               WHERE t.id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Helpers::flash('error', 'تیکت یافت نشد.');
            Helpers::redirect('tickets');
        }

        if (!Auth::isAdmin() && (int)$ticket['user_id'] !== Auth::id()) {
            Helpers::flash('error', 'دسترسی غیرمجاز.');
            Helpers::redirect('tickets');
        }

        $stmtMsg = $pdo->prepare("SELECT m.*, u.username, u.role, u.full_name 
                                  FROM ticket_messages m 
                                  JOIN users u ON m.sender_id = u.id 
                                  WHERE m.ticket_id = ? 
                                  ORDER BY m.id ASC");
        $stmtMsg->execute([$id]);
        $messages = $stmtMsg->fetchAll();

        require __DIR__ . '/../views/tickets/show.php';
    }

    public function reply(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('tickets');
        }

        $id = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $userId = Auth::id();

        if (empty($message) || $id <= 0) {
            Helpers::flash('error', 'متن پاسخ نمی‌تواند خالی باشد.');
            Helpers::redirect('tickets/show?id=' . $id);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Helpers::flash('error', 'تیکت یافت نشد.');
            Helpers::redirect('tickets');
        }

        if (!Auth::isAdmin() && (int)$ticket['user_id'] !== $userId) {
            Helpers::flash('error', 'دسترسی غیرمجاز.');
            Helpers::redirect('tickets');
        }

        $newStatus = Auth::isAdmin() ? 'answered' : 'waiting_reseller';

        $pdo->beginTransaction();
        try {
            $stmtMsg = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, created_at) 
                                      VALUES (?, ?, ?, datetime('now'))");
            $stmtMsg->execute([$id, $userId, $message]);

            $stmtUp = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = datetime('now') WHERE id = ?");
            $stmtUp->execute([$newStatus, $id]);
            $pdo->commit();

            Helpers::flash('success', 'پاسخ شما با موفقیت ثبت شد.');
            Helpers::redirect('tickets/show?id=' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Helpers::flash('error', 'خطا در ثبت پاسخ: ' . $e->getMessage());
            Helpers::redirect('tickets/show?id=' . $id);
        }
    }

    public function close(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('tickets');
        }

        $id = (int)($_POST['ticket_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket || (!Auth::isAdmin() && (int)$ticket['user_id'] !== Auth::id())) {
            Helpers::flash('error', 'تیکت یافت نشد یا دسترسی مجاز نیست.');
            Helpers::redirect('tickets');
        }

        $pdo->prepare("UPDATE tickets SET status = 'closed', updated_at = datetime('now') WHERE id = ?")->execute([$id]);
        Helpers::flash('info', "تیکت شماره #{$id} بسته شد.");
        Helpers::redirect('tickets/show?id=' . $id);
    }
}

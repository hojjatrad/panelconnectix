<?php
/**
 * S3: Security Logging - Log all sensitive actions
 */

class SecurityLogger {
    public static function log(string $action, string $details = '', ?int $userId = null): void {
        try {
            $pdo = Database::getConnection();
            
            // Ensure table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_user (user_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ip = explode(',', $ip)[0];
            $ip = trim(substr($ip, 0, 45));
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            
            if ($userId === null && class_exists('Auth') && Auth::check()) {
                $userId = Auth::id();
            }
            
            $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip, $ua]);
            
            // Also log to file for backup
            $logDir = __DIR__ . '/../cache/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
            $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
            $line = date('Y-m-d H:i:s') . " | IP: $ip | User: " . ($userId ?? 'guest') . " | Action: $action | Details: $details\n";
            @file_put_contents($logFile, $line, FILE_APPEND);
            
            // Critical actions -> Telegram alert
            $critical = ['login_failed', 'login_success', 'admin_login', 'settings_changed', 'backup_created', 'user_deleted', 'server_deleted'];
            if (in_array($action, $critical)) {
                self::sendTelegramAlert($action, $details, $ip, $userId);
            }
            
        } catch (Throwable $e) {
            // Don't break main flow on logging error
            error_log("SecurityLogger error: " . $e->getMessage());
        }
    }
    
    private static function sendTelegramAlert(string $action, string $details, string $ip, ?int $userId): void {
        try {
            $botToken = Setting::get('telegram_bot_token', '');
            $adminChatId = Setting::get('telegram_admin_chat_id', '');
            if (empty($botToken) || empty($adminChatId)) return;
            
            $actionFa = [
                'login_failed' => '❌ ورود ناموفق',
                'login_success' => '✅ ورود موفق',
                'admin_login' => '👑 ورود ادمین',
                'settings_changed' => '⚙️ تغییر تنظیمات',
                'backup_created' => '💾 بک‌آپ ایجاد شد',
                'user_deleted' => '🗑️ حذف کاربر',
                'server_deleted' => '🗑️ حذف سرور'
            ];
            
            $fa = $actionFa[$action] ?? $action;
            $msg = "🚨 <b>هشدار امنیتی پنل</b>\n\n"
                 . "🔹 <b>عملیات:</b> $fa\n"
                 . "👤 <b>کاربر:</b> " . ($userId ?? 'مهمان') . "\n"
                 . "🌐 <b>IP:</b> <code>$ip</code>\n"
                 . "📝 <b>جزئیات:</b> $details\n"
                 . "🕐 <b>زمان:</b> " . date('Y-m-d H:i:s');
            
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'chat_id' => $adminChatId,
                    'text' => $msg,
                    'parse_mode' => 'HTML'
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {}
    }
    
    public static function getRecent(int $limit = 50): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT $limit");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

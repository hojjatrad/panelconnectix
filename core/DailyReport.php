<?php
/**
 * A4: Daily Report to Telegram - Automatic at 9 AM
 * Updated: 2026-10-02 - Domain independent
 */

require_once __DIR__ . '/Helpers.php';

class DailyReport {
    public static function generate(): array {
        try {
            $pdo = Database::getConnection();
            
            // Yesterday stats
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $today = date('Y-m-d');
            
            $yesterdaySales = $pdo->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = '$yesterday' AND status='completed'")->fetchColumn();
            $yesterdayRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE DATE(created_at) = '$yesterday' AND status='completed'")->fetchColumn();
            
            $todaySales = $pdo->query("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE() AND status='completed'")->fetchColumn();
            $monthSales = $pdo->query("SELECT COUNT(*) FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status='completed'")->fetchColumn();
            $monthRevenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status='completed'")->fetchColumn();
            
            $activeClients = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn();
            $expiring3Days = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active' AND expire_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)")->fetchColumn();
            $expiringToday = $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active' AND DATE(expire_date) = CURDATE()")->fetchColumn();
            
            $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
            $newUsersYesterday = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$yesterday'")->fetchColumn();
            
            $servers = $pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1")->fetchColumn();
            
            return [
                'yesterday_date' => $yesterday,
                'yesterday_sales' => (int)$yesterdaySales,
                'yesterday_revenue' => (int)$yesterdayRevenue,
                'today_sales' => (int)$todaySales,
                'month_sales' => (int)$monthSales,
                'month_revenue' => (int)$monthRevenue,
                'active_clients' => (int)$activeClients,
                'expiring_3days' => (int)$expiring3Days,
                'expiring_today' => (int)$expiringToday,
                'total_users' => (int)$totalUsers,
                'new_users_yesterday' => (int)$newUsersYesterday,
                'servers' => (int)$servers
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public static function formatMessage(array $stats): string {
        if (isset($stats['error'])) {
            return "❌ خطا در گزارش: " . $stats['error'];
        }
        
        $yesterdayRevenueFormatted = number_format($stats['yesterday_revenue']);
        $monthRevenueFormatted = number_format($stats['month_revenue']);
        $dashboardUrl = Helpers::fullUrl('dashboard');
        
        return "📊 <b>گزارش روزانه Connectix</b>\n"
             . "📅 تاریخ: " . date('Y-m-d H:i:s') . "\n"
             . "━━━━━━━━━━━━━━━━\n"
             . "💰 <b>فروش دیروز ({$stats['yesterday_date']}):</b>\n"
             . "   • تعداد: {$stats['yesterday_sales']} فروش\n"
             . "   • مبلغ: {$yesterdayRevenueFormatted} تومان\n\n"
             . "📈 <b>این ماه:</b>\n"
             . "   • تعداد: {$stats['month_sales']} فروش\n"
             . "   • مبلغ: {$monthRevenueFormatted} تومان\n\n"
             . "👥 <b>کاربران:</b>\n"
             . "   • کل کاربران: {$stats['total_users']}\n"
             . "   • جدید دیروز: {$stats['new_users_yesterday']}\n"
             . "   • کلاینت فعال: {$stats['active_clients']}\n\n"
             . "⚠️ <b>هشدار انقضا:</b>\n"
             . "   • امروز منقضی: {$stats['expiring_today']}\n"
             . "   • 3 روز آینده: {$stats['expiring_3days']}\n\n"
             . "🖥️ <b>سرورها:</b> {$stats['servers']} فعال\n"
             . "━━━━━━━━━━━━━━━━\n"
             . "🔗 <a href=\"{$dashboardUrl}\">مشاهده داشبورد</a>";
    }
    
    public static function send(): array {
        $stats = self::generate();
        $message = self::formatMessage($stats);
        
        try {
            require_once __DIR__ . '/TelegramBot.php';
            $botToken = Setting::get('telegram_bot_token', '');
            $adminChatId = Setting::get('telegram_admin_chat_id', '');
            
            if (empty($botToken) || empty($adminChatId)) {
                return ['success' => false, 'message' => 'توکن ربات یا چت ادمین تنظیم نشده'];
            }
            
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'chat_id' => $adminChatId,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                Setting::set('last_daily_report_at', date('Y-m-d H:i:s'));
                return ['success' => true, 'message' => 'گزارش روزانه ارسال شد', 'stats' => $stats];
            }
            
            return ['success' => false, 'message' => "خطا HTTP $httpCode"];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

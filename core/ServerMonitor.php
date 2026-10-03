<?php
/**
 * ServerMonitor - مانیتورینگ زنده سرورها + آلارم تلگرام v6.9.0 PRO MAX
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class ServerMonitor {
    
    public static function checkAllServers(): array {
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        
        foreach ($servers as $server) {
            $result = self::checkServer($server);
            $results[] = $result;
            
            // Log
            try {
                $pdo->prepare("INSERT INTO server_monitor_logs (server_id, latency_ms, status, error_message, checked_at) VALUES (?, ?, ?, ?, NOW())")
                    ->execute([$server['id'], $result['latency_ms'], $result['status'], $result['error'] ?? null]);
            } catch (Throwable $e) {}
            
            // Update server_nodes health
            try {
                $pdo->prepare("UPDATE server_nodes SET health_status = ?, latency_ms = ?, last_checked_at = NOW(), error_message = ? WHERE id = ?")
                    ->execute([$result['status'], $result['latency_ms'], $result['error'] ?? null, $server['id']]);
            } catch (Throwable $e) {}
            
            // Telegram alert if down
            if ($result['status'] === 'offline') {
                self::sendDownAlert($server, $result);
            } elseif ($result['status'] === 'online' && self::wasPreviouslyDown($server['id'])) {
                self::sendUpAlert($server, $result);
            }
        }
        
        // Cleanup old logs (keep 7 days)
        try {
            $pdo->exec("DELETE FROM server_monitor_logs WHERE checked_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        } catch (Throwable $e) {
            try { $pdo->exec("DELETE FROM server_monitor_logs WHERE checked_at < datetime('now', '-7 days')"); } catch (Throwable $e2) {}
        }
        
        return $results;
    }
    
    public static function checkServer(array $server): array {
        $start = microtime(true);
        $status = 'offline';
        $latency = 0;
        $error = null;
        
        try {
            $driver = DriverFactory::create($server);
            if ($driver->authenticate()) {
                $users = $driver->listUsers();
                $latency = (int)((microtime(true) - $start) * 1000);
                $status = 'online';
                if ($latency > 2000) $status = 'slow';
            } else {
                $error = $driver->getLastError() ?? 'Authentication failed';
                $latency = (int)((microtime(true) - $start) * 1000);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $latency = (int)((microtime(true) - $start) * 1000);
        }
        
        return [
            'server_id' => $server['id'],
            'server_name' => $server['name'],
            'status' => $status,
            'latency_ms' => $latency,
            'error' => $error,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private static function wasPreviouslyDown(int $serverId): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT status FROM server_monitor_logs WHERE server_id = ? ORDER BY id DESC LIMIT 2");
            $stmt->execute([$serverId]);
            $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($logs) >= 2) {
                return $logs[1] === 'offline' && $logs[0] === 'online';
            }
        } catch (Throwable $e) {}
        return false;
    }
    
    public static function sendDownAlert(array $server, array $result): void {
        try {
            require_once __DIR__ . '/TelegramBot.php';
            $botToken = Setting::get('telegram_bot_token');
            $adminChatId = Setting::get('telegram_admin_chat_id');
            if (empty($botToken) || empty($adminChatId)) return;
            
            // Avoid spam: only alert if down for 2 consecutive checks
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM server_monitor_logs WHERE server_id = ? AND status = 'offline' AND checked_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmt->execute([$server['id']]);
            $downCount = (int)$stmt->fetchColumn();
            if ($downCount < 2) return;
            
            // Check if already alerted recently
            $lastAlert = Setting::get('last_down_alert_'.$server['id'], '0');
            if (time() - (int)$lastAlert < 1800) return; // 30 min cooldown
            
            $msg = "🚨 <b>سرور آفلاین شد!</b>\n\n"
                 . "🖥 سرور: <code>{$server['name']}</code>\n"
                 . "🔌 درایور: {$server['driver']}\n"
                 . "🌐 آدرس: {$server['api_url']}\n"
                 . "⏱ تاخیر: {$result['latency_ms']}ms\n"
                 . "❌ خطا: ".htmlspecialchars($result['error'] ?? 'نامشخص')."\n"
                 . "📅 زمان: ".date('Y-m-d H:i:s')."\n\n"
                 . "#ServerDown #Alert";
            
            TelegramBot::sendMessage($adminChatId, $msg, $botToken);
            Setting::set('last_down_alert_'.$server['id'], (string)time());
        } catch (Throwable $e) {}
    }
    
    public static function sendUpAlert(array $server, array $result): void {
        try {
            require_once __DIR__ . '/TelegramBot.php';
            $botToken = Setting::get('telegram_bot_token');
            $adminChatId = Setting::get('telegram_admin_chat_id');
            if (empty($botToken) || empty($adminChatId)) return;
            
            $msg = "✅ <b>سرور آنلاین شد!</b>\n\n"
                 . "🖥 سرور: <code>{$server['name']}</code>\n"
                 . "⏱ تاخیر: {$result['latency_ms']}ms\n"
                 . "📅 زمان: ".date('Y-m-d H:i:s')."\n\n"
                 . "#ServerUp";
            
            TelegramBot::sendMessage($adminChatId, $msg, $botToken);
        } catch (Throwable $e) {}
    }
    
    public static function getUptimeStats(int $serverId, int $hours = 24): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT 
                COUNT(*) as total_checks,
                SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online_checks,
                AVG(latency_ms) as avg_latency,
                MAX(latency_ms) as max_latency,
                MIN(latency_ms) as min_latency
                FROM server_monitor_logs 
                WHERE server_id = ? AND checked_at > DATE_SUB(NOW(), INTERVAL ? HOUR)");
            $stmt->execute([$serverId, $hours]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $uptime = $row['total_checks'] > 0 ? round(($row['online_checks'] / $row['total_checks']) * 100, 2) : 0;
            return [
                'uptime_percent' => $uptime,
                'total_checks' => (int)$row['total_checks'],
                'online_checks' => (int)$row['online_checks'],
                'avg_latency' => (int)$row['avg_latency'],
                'max_latency' => (int)$row['max_latency'],
                'min_latency' => (int)$row['min_latency'],
            ];
        } catch (Throwable $e) {
            return ['uptime_percent'=>0,'total_checks'=>0,'online_checks'=>0,'avg_latency'=>0,'max_latency'=>0,'min_latency'=>0];
        }
    }
    
    public static function getRecentLogs(int $serverId, int $limit = 50): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM server_monitor_logs WHERE server_id = ? ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $serverId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }
}

<?php
/**
 * O8: Cron Optimization - Single file for all crons
 * I1: Uptime Monitoring
 */

class CronOptimizer {
    /**
     * O8: Single cron runner - call every 5 minutes instead of every minute
     */
    public static function runAll(): array {
        $results = [];
        $start = microtime(true);
        
        // 1. Cleanup old cache and rate limits
        try {
            if (class_exists('RateLimiter')) {
                $results['ratelimit_cleanup'] = RateLimiter::cleanup();
            }
        } catch (Throwable $e) {
            $results['ratelimit_cleanup'] = 'error: ' . $e->getMessage();
        }
        
        // 2. Check expiring clients (F3)
        try {
            if (class_exists('Retention')) {
                $results['retention'] = Retention::checkExpiringClients();
            }
        } catch (Throwable $e) {
            $results['retention'] = 'error: ' . $e->getMessage();
        }
        
        // 3. Send smart notifications (B7)
        try {
            if (class_exists('BotPayment')) {
                $results['notifications'] = BotPayment::sendSmartNotifications();
            }
        } catch (Throwable $e) {
            $results['notifications'] = 'error: ' . $e->getMessage();
        }
        
        // 4. Check daily backup (S4)
        try {
            if (class_exists('Backup')) {
                $results['backup_check'] = Backup::checkDailyBackup();
            }
        } catch (Throwable $e) {
            $results['backup_check'] = 'error: ' . $e->getMessage();
        }
        
        // 5. Cleanup old backups
        try {
            if (class_exists('Backup')) {
                $results['backup_cleanup'] = Backup::cleanupOldBackups();
            }
        } catch (Throwable $e) {
            $results['backup_cleanup'] = 'error: ' . $e->getMessage();
        }
        
        // 6. Optimize image batch (O3)
        try {
            if (class_exists('ImageOptimizer')) {
                $results['image_optimize'] = ImageOptimizer::batchConvert(5);
            }
        } catch (Throwable $e) {
            $results['image_optimize'] = 'error: ' . $e->getMessage();
        }
        
        // 7. Update server status (F10/I1)
        try {
            if (class_exists('StatusPage')) {
                $servers = StatusPage::getServersStatus();
                foreach ($servers as $server) {
                    StatusPage::logUptime($server['id'], $server['is_online'], $server['response_time'] ?? null);
                }
                $results['uptime_logged'] = count($servers);
            }
        } catch (Throwable $e) {
            $results['uptime_logged'] = 'error: ' . $e->getMessage();
        }
        
        $results['total_time'] = round((microtime(true) - $start)*1000, 2) . 'ms';
        $results['run_at'] = date('Y-m-d H:i:s');
        
        // Log to file
        $logDir = __DIR__ . '/../cache/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        @file_put_contents($logDir . '/cron_' . date('Y-m-d') . '.log', 
            date('Y-m-d H:i:s') . " | " . json_encode($results, JSON_UNESCAPED_UNICODE) . "\n", 
            FILE_APPEND
        );
        
        return $results;
    }
    
    /**
     * Get cron logs
     */
    public static function getLogs(int $limit = 20): array {
        $logFile = __DIR__ . '/../cache/logs/cron_' . date('Y-m-d') . '.log';
        if (!is_file($logFile)) return [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }
}

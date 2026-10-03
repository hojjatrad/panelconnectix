<?php
/**
 * SyncQueue - صف همگام‌سازی با پیشرفت و جلوگیری از 520 v6.9.0 PRO MAX
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/NodeSync.php';
require_once __DIR__ . '/CategoryManager.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class SyncQueue {
    
    public static function create(int $serverId, string $type = 'full', int $userId = 0): array {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare("INSERT INTO sync_queue (server_id, type, status, progress, total, created_by, created_at) VALUES (?, ?, 'pending', 0, 100, ?, NOW())");
            $stmt->execute([$serverId, $type, $userId ?: null]);
            $id = (int)$pdo->lastInsertId();
            return ['success'=>true, 'id'=>$id];
        } catch (Throwable $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }
    
    public static function get(int $id): ?array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT q.*, s.name as server_name FROM sync_queue q LEFT JOIN server_nodes s ON s.id = q.server_id WHERE q.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }
    
    public static function listRecent(int $limit = 20): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT q.*, s.name as server_name FROM sync_queue q LEFT JOIN server_nodes s ON s.id = q.server_id ORDER BY q.id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }
    
    public static function process(int $queueId): array {
        $queue = self::get($queueId);
        if (!$queue) return ['success'=>false, 'error'=>'صف یافت نشد'];
        
        $pdo = Database::getConnection();
        $serverId = (int)$queue['server_id'];
        $type = $queue['type'];
        
        try {
            $pdo->prepare("UPDATE sync_queue SET status = 'running', progress = 5, started_at = NOW() WHERE id = ?")->execute([$queueId]);
        } catch (Throwable $e) {}
        
        $result = [
            'categories' => 0,
            'plans' => 0,
            'clients_added' => 0,
            'clients_updated' => 0,
            'errors' => []
        ];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$server) throw new Exception('سرور یافت نشد');
            
            // Step 1: Categories (20%)
            try {
                $pdo->prepare("UPDATE sync_queue SET progress = 10 WHERE id = ?")->execute([$queueId]);
            } catch (Throwable $e) {}
            
            if (in_array($type, ['full','categories'])) {
                try {
                    $driver = DriverFactory::create($server);
                    if (method_exists($driver, 'getVipPlans')) {
                        $data = $driver->getVipPlans();
                        $groups = $data['groups'] ?? [];
                        foreach ($groups as $g) {
                            if (!is_array($g)) continue;
                            $gName = trim($g['name'] ?? $g['title'] ?? '');
                            if ($gName === '') continue;
                            $cat = CategoryManager::findOrCreateCategory($pdo, $gName, null, 'servers');
                            if ($cat) $result['categories']++;
                        }
                    }
                } catch (Throwable $e) { $result['errors'][] = "دسته: ".$e->getMessage(); }
            }
            
            try { $pdo->prepare("UPDATE sync_queue SET progress = 30 WHERE id = ?")->execute([$queueId]); } catch (Throwable $e) {}
            
            // Step 2: Plans (50%)
            if (in_array($type, ['full','plans'])) {
                try {
                    $driver = DriverFactory::create($server);
                    if (method_exists($driver, 'getVipPlans')) {
                        $data = $driver->getVipPlans();
                        $vipPlans = $data['plans'] ?? [];
                        $vipGroups = $data['groups'] ?? [];
                        foreach ($vipPlans as $vp) {
                            try {
                                if (!is_array($vp)) continue;
                                $norm = CategoryManager::normalizePlan($vp, $vipGroups);
                                $dup = $pdo->prepare("SELECT id FROM plans WHERE traffic_gb = ? AND duration_days = ? AND server_group = ? AND server_id = ? LIMIT 1");
                                $dup->execute([$norm['traffic_gb'], $norm['duration_days'], $norm['server_group'], $serverId]);
                                if ($dup->fetch()) continue;
                                $effGroup = $norm['server_group'];
                                if ($norm['traffic_gb'] <= 0.5) $effGroup = 'free';
                                $catRow = CategoryManager::findOrCreateVipCategory($pdo, $effGroup, $norm['duration_days'], 'plans');
                                $catId = $catRow['id'] ?? null;
                                $catName = $catRow['name'] ?? CategoryManager::canonicalFromDuration($norm['duration_days']);
                                $basePrice = $norm['price'] ?? 120000;
                                $resellerPrice = (int)($basePrice * 0.7);
                                $localTitle = str_replace(['Economic', 'Iran Access', 'Business Class'], ['اقتصادی', 'ایران‌اکسس', 'بیزنس'], $vp['title'] ?? 'پلن') . ' - VIP';
                                $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, category_id, vip_plan_id, vip_group_id, vip_group_name, vip_plan_title, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)")
                                    ->execute([$localTitle, $norm['traffic_gb'], $norm['duration_days'], $basePrice, $resellerPrice, $effGroup, $serverId, $catName, $catId, $vp['id'] ?? null, $norm['group_id'], $norm['group_name'], $vp['title'] ?? '']);
                                $result['plans']++;
                            } catch (Throwable $e) {}
                        }
                    }
                } catch (Throwable $e) { $result['errors'][] = "پلن: ".$e->getMessage(); }
            }
            
            try { $pdo->prepare("UPDATE sync_queue SET progress = 60 WHERE id = ?")->execute([$queueId]); } catch (Throwable $e) {}
            
            // Step 3: Clients (90%) - most important with exact sublink
            if (in_array($type, ['full','clients'])) {
                try {
                    $syncResult = NodeSync::syncServer($pdo, $server);
                    $result['clients_added'] = $syncResult['added'] ?? 0;
                    $result['clients_updated'] = $syncResult['updated'] ?? 0;
                    if (!empty($syncResult['errors'])) {
                        $result['errors'] = array_merge($result['errors'], $syncResult['errors']);
                    }
                } catch (Throwable $e) { $result['errors'][] = "کلاینت: ".$e->getMessage(); }
            }
            
            try { $pdo->prepare("UPDATE sync_queue SET progress = 90 WHERE id = ?")->execute([$queueId]); } catch (Throwable $e) {}
            
            // Auto backup after sync
            try {
                require_once __DIR__ . '/ServerBackupManager.php';
                ServerBackupManager::createBackup($serverId, 'full', true, 'بکاپ خودکار بعد از همگام‌سازی صف #'.$queueId);
            } catch (Throwable $e) {}
            
            // Finish
            $finalResult = json_encode($result, JSON_UNESCAPED_UNICODE);
            try {
                $pdo->prepare("UPDATE sync_queue SET status = 'completed', progress = 100, finished_at = NOW(), result = ? WHERE id = ?")
                    ->execute([$finalResult, $queueId]);
            } catch (Throwable $e) {}
            
            // Telegram notification
            try {
                require_once __DIR__ . '/TelegramBot.php';
                $botToken = Setting::get('telegram_bot_token');
                $adminChatId = Setting::get('telegram_admin_chat_id');
                if (!empty($botToken) && !empty($adminChatId)) {
                    $msg = "✅ <b>همگام‌سازی کامل شد!</b>\n\n"
                         . "🖥 سرور: {$server['name']}\n"
                         . "📂 دسته: {$result['categories']}\n"
                         . "📋 پلن: {$result['plans']}\n"
                         . "👥 کلاینت جدید: {$result['clients_added']}\n"
                         . "🔄 بروزرسانی: {$result['clients_updated']}\n"
                         . "📦 ساب‌لینک‌ها دقیقاً از سرور اصلی استخراج شدند\n"
                         . "#SyncCompleted";
                    TelegramBot::sendMessage($adminChatId, $msg, $botToken);
                }
            } catch (Throwable $e) {}
            
            return ['success'=>true, 'result'=>$result];
            
        } catch (Throwable $e) {
            try {
                $pdo->prepare("UPDATE sync_queue SET status = 'failed', finished_at = NOW(), result = ? WHERE id = ?")
                    ->execute([$e->getMessage(), $queueId]);
            } catch (Throwable $e2) {}
            return ['success'=>false, 'error'=>$e->getMessage(), 'result'=>$result];
        }
    }
    
    public static function processPending(): int {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT id FROM sync_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 5");
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $count = 0;
            foreach ($ids as $id) {
                self::process((int)$id);
                $count++;
                // Avoid long running
                if ($count >= 3) break;
            }
            return $count;
        } catch (Throwable $e) { return 0; }
    }
}

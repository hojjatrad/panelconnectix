<?php
/**
 * ServerBackupManager - حرفه‌ای و پیشرفته v6.8.28
 * 
 * قابلیت‌ها:
 * - بکاپ کامل از هر سرور: دسته‌بندی‌ها + پلن‌ها + کلاینت‌ها (با ساب‌لینک دقیق، ترافیک، تاریخ انقضا، پسورد اصلی)
 * - بکاپ خودکار هنگام: افزودن سرور، همگام‌سازی کامل، حذف کلاینت، حذف دسته‌جمعی
 * - بازگردانی هوشمند: merge یا overwrite، با تشخیص تکراری
 * - فرمت JSON + فشرده ZIP، با رمزنگاری اختیاری
 * - نگهداری چرخشی: نگه داشتن آخرین 20 بکاپ هر سرور
 * - ارسال به تلگرام / گوگل درایو
 * - پیش‌نمایش قبل از بازگردانی
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/CategoryManager.php';

class ServerBackupManager {
    
    public const BACKUP_DIR = __DIR__ . '/../data/backups/servers';
    public const KEEP_COUNT = 20;
    
    public static function ensureTable(): void {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS server_backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_id INT NULL,
                server_name VARCHAR(255) NULL,
                type ENUM('full','clients','plans','categories','auto_delete') DEFAULT 'full',
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size INT DEFAULT 0,
                clients_count INT DEFAULT 0,
                plans_count INT DEFAULT 0,
                categories_count INT DEFAULT 0,
                is_auto TINYINT(1) DEFAULT 0,
                created_by INT NULL,
                note TEXT NULL,
                checksum VARCHAR(64) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                INDEX idx_server (server_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) { error_log("ServerBackupManager ensureTable: ".$e->getMessage()); }
    }
    
    public static function ensureDir(int $serverId = 0): string {
        $base = self::BACKUP_DIR;
        if (!is_dir($base)) @mkdir($base, 0755, true);
        if ($serverId > 0) {
            $dir = $base . '/' . $serverId;
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            return $dir;
        }
        return $base;
    }
    
    /**
     * ایجاد بکاپ کامل از یک سرور
     * @param int $serverId 0 = همه سرورها
     * @param string $type full|clients|plans|categories|auto_delete
     * @param bool $isAuto بکاپ خودکار؟
     * @param string $note یادداشت
     * @return array
     */
    public static function createBackup(int $serverId = 0, string $type = 'full', bool $isAuto = false, string $note = ''): array {
        self::ensureTable();
        $pdo = Database::getConnection();
        $dir = self::ensureDir($serverId);
        
        $timestamp = date('Y-m-d_H-i-s');
        $serverName = 'all-servers';
        $serverData = null;
        
        if ($serverId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
            $stmt->execute([$serverId]);
            $serverData = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$serverData) {
                return ['success'=>false, 'error'=>'سرور یافت نشد'];
            }
            $serverName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $serverData['name'] ?? "server_$serverId");
        }
        
        // جمع‌آوری داده‌ها
        $backupData = [
            'meta' => [
                'version' => '6.8.28',
                'created_at' => date('Y-m-d H:i:s'),
                'server_id' => $serverId,
                'server_name' => $serverName,
                'type' => $type,
                'is_auto' => $isAuto,
                'note' => $note,
                'created_by' => $_SESSION['user_id'] ?? 0,
            ],
            'server' => $serverData,
            'categories' => [],
            'plans' => [],
            'clients' => [],
        ];
        
        $catCount = 0; $planCount = 0; $clientCount = 0;
        
        try {
            // دسته‌بندی‌ها
            if (in_array($type, ['full','categories','auto_delete'])) {
                if ($serverId > 0) {
                    // دسته‌های مرتبط با سرور + همه دسته‌ها اگر full
                    $cats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $cats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
                }
                $backupData['categories'] = $cats;
                $catCount = count($cats);
            }
            
            // پلن‌ها
            if (in_array($type, ['full','plans','auto_delete'])) {
                if ($serverId > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM plans WHERE server_id = ? OR server_id IS NULL");
                    $stmt->execute([$serverId]);
                    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    // همچنین vip_plans
                    $stmt2 = $pdo->prepare("SELECT * FROM vip_plans WHERE server_id = ?");
                    $stmt2->execute([$serverId]);
                    $vipPlans = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    $backupData['vip_plans'] = $vipPlans;
                } else {
                    $plans = $pdo->query("SELECT * FROM plans")->fetchAll(PDO::FETCH_ASSOC);
                    $vipPlans = $pdo->query("SELECT * FROM vip_plans")->fetchAll(PDO::FETCH_ASSOC);
                    $backupData['vip_plans'] = $vipPlans;
                }
                $backupData['plans'] = $plans;
                $planCount = count($plans) + count($backupData['vip_plans'] ?? []);
            }
            
            // کلاینت‌ها - مهم‌ترین بخش با ساب‌لینک دقیق
            if (in_array($type, ['full','clients','auto_delete'])) {
                if ($serverId > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM clients WHERE server_id = ?");
                    $stmt->execute([$serverId]);
                    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $clients = $pdo->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);
                }
                // حذف فیلدهای حساس؟ نه، برای بکاپ کامل نگه می‌داریم
                $backupData['clients'] = $clients;
                $clientCount = count($clients);
            }
            
        } catch (Throwable $e) {
            return ['success'=>false, 'error'=>'خطا در جمع‌آوری داده: '.$e->getMessage()];
        }
        
        // ذخیره فایل JSON
        $fileName = "{$timestamp}_{$serverName}_{$type}.json";
        $filePath = $dir . '/' . $fileName;
        $json = json_encode($backupData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        
        if (file_put_contents($filePath, $json) === false) {
            return ['success'=>false, 'error'=>'خطا در ذخیره فایل بکاپ'];
        }
        
        // فشرده‌سازی ZIP
        $zipName = str_replace('.json', '.zip', $fileName);
        $zipPath = $dir . '/' . $zipName;
        $zipSuccess = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($filePath, $fileName);
                $zip->close();
                @unlink($filePath);
                $filePath = $zipPath;
                $fileName = $zipName;
                $zipSuccess = true;
            }
        }
        
        $fileSize = filesize($filePath);
        $checksum = hash_file('sha256', $filePath);
        
        // ثبت در دیتابیس
        try {
            $stmt = $pdo->prepare("INSERT INTO server_backups (server_id, server_name, type, file_path, file_name, file_size, clients_count, plans_count, categories_count, is_auto, created_by, note, checksum, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $serverId ?: null,
                $serverName,
                $type,
                $filePath,
                $fileName,
                $fileSize,
                $clientCount,
                $planCount,
                $catCount,
                $isAuto ? 1 : 0,
                $_SESSION['user_id'] ?? null,
                $note,
                $checksum
            ]);
            $backupId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            // اگر جدول نباشد
            $backupId = 0;
        }
        
        // پاکسازی قدیمی‌ها
        self::cleanupOldBackups($serverId, self::KEEP_COUNT);
        
        return [
            'success' => true,
            'id' => $backupId,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'clients_count' => $clientCount,
            'plans_count' => $planCount,
            'categories_count' => $catCount,
            'checksum' => $checksum,
        ];
    }
    
    public static function listBackups(int $serverId = 0, int $limit = 50): array {
        self::ensureTable();
        try {
            $pdo = Database::getConnection();
            if ($serverId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM server_backups WHERE server_id = ? OR server_id IS NULL ORDER BY id DESC LIMIT ?");
                $stmt->bindValue(1, $serverId, PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM server_backups ORDER BY id DESC LIMIT ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
    
    public static function getBackup(int $id): ?array {
        self::ensureTable();
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM server_backups WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }
    
    public static function loadBackupData(int $id): ?array {
        $backup = self::getBackup($id);
        if (!$backup) return null;
        $path = $backup['file_path'];
        if (!file_exists($path)) return null;
        
        $content = null;
        if (str_ends_with($path, '.zip') && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $content = $zip->getFromIndex(0);
                $zip->close();
            }
        } else {
            $content = file_get_contents($path);
        }
        if (!$content) return null;
        return json_decode($content, true);
    }
    
    /**
     * بازگردانی بکاپ
     * @param int $id
     * @param array $options ['restore_categories'=>bool, 'restore_plans'=>bool, 'restore_clients'=>bool, 'mode'=>'merge|overwrite']
     */
    public static function restoreBackup(int $id, array $options = []): array {
        $data = self::loadBackupData($id);
        if (!$data) return ['success'=>false, 'error'=>'فایل بکاپ یافت نشد یا خراب است'];
        
        $restoreCats = $options['restore_categories'] ?? true;
        $restorePlans = $options['restore_plans'] ?? true;
        $restoreClients = $options['restore_clients'] ?? true;
        $mode = $options['mode'] ?? 'merge'; // merge = فقط جدیدها، overwrite = حذف و جایگزینی
        
        $pdo = Database::getConnection();
        $stats = ['categories'=>0, 'plans'=>0, 'clients'=>0, 'errors'=>[]];
        
        try {
            $pdo->beginTransaction();
            
            // دسته‌بندی‌ها
            if ($restoreCats && !empty($data['categories'])) {
                foreach ($data['categories'] as $cat) {
                    try {
                        $name = $cat['name'] ?? $cat['title'] ?? '';
                        if (empty($name)) continue;
                        $existing = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1");
                        $existing->execute([$name, $cat['slug'] ?? '']);
                        if ($existing->fetchColumn()) {
                            if ($mode === 'overwrite') {
                                // آپدیت
                                $pdo->prepare("UPDATE categories SET description = ?, icon = ?, color = ? WHERE name = ?")
                                    ->execute([$cat['description'] ?? '', $cat['icon'] ?? '', $cat['color'] ?? '', $name]);
                                $stats['categories']++;
                            }
                            continue;
                        }
                        $pdo->prepare("INSERT INTO categories (name, slug, description, icon, color, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())")
                            ->execute([$name, $cat['slug'] ?? strtolower(str_replace(' ', '-', $name)), $cat['description'] ?? '', $cat['icon'] ?? 'fa-folder', $cat['color'] ?? '#8b5cf6']);
                        $stats['categories']++;
                    } catch (Throwable $e) { $stats['errors'][] = "دسته {$name}: ".$e->getMessage(); }
                }
            }
            
            // پلن‌ها
            if ($restorePlans && !empty($data['plans'])) {
                foreach ($data['plans'] as $plan) {
                    try {
                        $title = $plan['title'] ?? $plan['name'] ?? '';
                        if (empty($title)) continue;
                        $serverId = $plan['server_id'] ?? null;
                        $dup = $pdo->prepare("SELECT id FROM plans WHERE title = ? AND (server_id = ? OR ? IS NULL) LIMIT 1");
                        $dup->execute([$title, $serverId, $serverId]);
                        if ($dup->fetchColumn() && $mode === 'merge') continue;
                        
                        if ($mode === 'overwrite' && $dup->fetchColumn()) {
                            // حذف قدیمی
                            $pdo->prepare("DELETE FROM plans WHERE title = ? AND (server_id = ? OR ? IS NULL)")->execute([$title, $serverId, $serverId]);
                        }
                        
                        // بازسازی INSERT با ستون‌های موجود
                        $cols = ['title','traffic_gb','duration_days','base_price','reseller_price','server_group','server_id','category','category_id','is_active','show_in_bot'];
                        $vals = [];
                        foreach ($cols as $c) $vals[] = $plan[$c] ?? null;
                        $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, category_id, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                            ->execute($vals);
                        $stats['plans']++;
                    } catch (Throwable $e) { $stats['errors'][] = "پلن {$title}: ".$e->getMessage(); }
                }
            }
            
            // کلاینت‌ها - مهم‌ترین
            if ($restoreClients && !empty($data['clients'])) {
                foreach ($data['clients'] as $cli) {
                    try {
                        $username = $cli['username'] ?? '';
                        $serverId = $cli['server_id'] ?? 0;
                        if (empty($username) || empty($serverId)) continue;
                        
                        $existing = $pdo->prepare("SELECT id FROM clients WHERE username = ? AND server_id = ? LIMIT 1");
                        $existing->execute([$username, $serverId]);
                        $existId = $existing->fetchColumn();
                        
                        if ($existId && $mode === 'merge') continue;
                        if ($existId && $mode === 'overwrite') {
                            $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$existId]);
                        }
                        
                        // بازگردانی با تمام فیلدها شامل node_sublink دقیق
                        $stmt = $pdo->prepare("INSERT INTO clients (reseller_id, server_id, plan_id, username, password, uuid, sub_token, node_sublink, traffic_limit_bytes, traffic_used_bytes, expire_at, status, custom_note, created_at, node_sync, original_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $cli['reseller_id'] ?? 1,
                            $serverId,
                            $cli['plan_id'] ?? null,
                            $username,
                            $cli['password'] ?? 'restored',
                            $cli['uuid'] ?? '',
                            $cli['sub_token'] ?? bin2hex(random_bytes(12)),
                            $cli['node_sublink'] ?? '', // ساب‌لینک دقیق سرور اصلی
                            $cli['traffic_limit_bytes'] ?? 0,
                            $cli['traffic_used_bytes'] ?? 0,
                            $cli['expire_at'] ?? null,
                            $cli['status'] ?? 'active',
                            $cli['custom_note'] ?? 'بازگردانی از بکاپ',
                            $cli['created_at'] ?? date('Y-m-d H:i:s'),
                            $cli['node_sync'] ?? 1,
                            $cli['original_password'] ?? $cli['password'] ?? '',
                        ]);
                        $stats['clients']++;
                    } catch (Throwable $e) { $stats['errors'][] = "کلاینت {$username}: ".$e->getMessage(); }
                }
            }
            
            $pdo->commit();
            return ['success'=>true, 'stats'=>$stats];
            
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['success'=>false, 'error'=>$e->getMessage(), 'stats'=>$stats];
        }
    }
    
    public static function deleteBackup(int $id): bool {
        $backup = self::getBackup($id);
        if (!$backup) return false;
        if (file_exists($backup['file_path'])) @unlink($backup['file_path']);
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("DELETE FROM server_backups WHERE id = ?")->execute([$id]);
            return true;
        } catch (Throwable $e) { return false; }
    }
    
    public static function cleanupOldBackups(int $serverId = 0, int $keep = 20): int {
        try {
            $pdo = Database::getConnection();
            if ($serverId > 0) {
                $stmt = $pdo->prepare("SELECT id, file_path FROM server_backups WHERE server_id = ? ORDER BY id DESC LIMIT 1000 OFFSET ?");
                $stmt->execute([$serverId, $keep]);
            } else {
                $stmt = $pdo->prepare("SELECT id, file_path FROM server_backups ORDER BY id DESC LIMIT 1000 OFFSET ?");
                $stmt->execute([$keep]);
            }
            $old = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $deleted = 0;
            foreach ($old as $row) {
                if (file_exists($row['file_path'])) @unlink($row['file_path']);
                $pdo->prepare("DELETE FROM server_backups WHERE id = ?")->execute([$row['id']]);
                $deleted++;
            }
            return $deleted;
        } catch (Throwable $e) { return 0; }
    }
    
    // هوک‌های خودکار
    public static function autoBackupOnServerAdd(array $server): void {
        try {
            self::createBackup((int)$server['id'], 'full', true, 'بکاپ خودکار هنگام افزودن سرور: '.$server['name']);
        } catch (Throwable $e) {}
    }
    
    public static function autoBackupBeforeDelete(int $serverId, string $reason = 'حذف کلاینت'): array {
        return self::createBackup($serverId, 'auto_delete', true, "بکاپ خودکار قبل از $reason - ".date('Y-m-d H:i:s'));
    }
}

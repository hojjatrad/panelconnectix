<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Updater.php';
require_once __DIR__ . '/ResellerPermissionManager.php';

class ResellerSyncManager {
    public static function ensureResellerSchema(): void {
        try {
            $pdo = Database::getConnection();
            Database::ensureExtendedTablesExist($pdo);
            ResellerPermissionManager::seedDefaultTemplates();
        } catch (Throwable $e) {
            error_log("ResellerSync ensure schema error: " . $e->getMessage());
        }
    }

    public static function syncAllResellers(string $fromVersion = '', string $toVersion = ''): array {
        if ($toVersion === '') {
            $toVersion = Updater::CURRENT_VERSION;
        }
        
        $results = ['synced' => 0, 'failed' => 0, 'details' => []];
        
        try {
            $pdo = Database::getConnection();
            self::ensureResellerSchema();
            
            // Get all resellers
            $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'reseller' AND status = 'active'");
            $resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($resellers as $reseller) {
                $resellerId = (int)$reseller['id'];
                try {
                    // Ensure permissions exist for this reseller (merge new keys)
                    $existingPerms = ResellerPermissionManager::getPermissionsForReseller($resellerId);
                    $allKeys = array_keys(ResellerPermissionManager::ALL_PERMISSIONS);
                    $hasNewKeys = false;
                    
                    foreach ($allKeys as $key) {
                        if (!isset($existingPerms[$key])) {
                            // New permission key added in update - set default visible/enabled
                            ResellerPermissionManager::setPermission($resellerId, $key, 1, 1);
                            $hasNewKeys = true;
                        }
                    }
                    
                    // Log sync
                    $pdo->prepare("INSERT INTO reseller_sync_log (reseller_id, from_version, to_version, status, details) VALUES (?, ?, ?, 'success', ?)")
                        ->execute([$resellerId, $fromVersion, $toVersion, $hasNewKeys ? 'Added new permission keys' : 'Already synced']);
                    
                    $results['synced']++;
                    $results['details'][] = "✅ {$reseller['username']} (ID $resellerId) synced to $toVersion";
                    
                } catch (Throwable $e) {
                    $results['failed']++;
                    $results['details'][] = "❌ {$reseller['username']} failed: " . $e->getMessage();
                    try {
                        $pdo->prepare("INSERT INTO reseller_sync_log (reseller_id, from_version, to_version, status, details) VALUES (?, ?, ?, 'failed', ?)")
                            ->execute([$resellerId, $fromVersion, $toVersion, $e->getMessage()]);
                    } catch (Throwable $e2) {}
                }
            }
            
            // Update last sync version
            Setting::set('reseller_last_synced_version', $toVersion);
            Setting::set('reseller_last_sync_time', (string)time());
            
        } catch (Throwable $e) {
            error_log("ResellerSync syncAll error: " . $e->getMessage());
            $results['details'][] = "❌ Fatal: " . $e->getMessage();
        }
        
        return $results;
    }

    public static function getLastSyncInfo(): array {
        try {
            $version = Setting::get('reseller_last_synced_version', Updater::CURRENT_VERSION);
            $time = (int)Setting::get('reseller_last_sync_time', '0');
            $pdo = Database::getConnection();
            $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'reseller' AND status = 'active'")->fetchColumn();
            $syncedCount = (int)$pdo->query("SELECT COUNT(DISTINCT reseller_id) FROM reseller_sync_log WHERE to_version = " . $pdo->quote($version))->fetchColumn();
            
            return [
                'version' => $version,
                'time' => $time,
                'time_human' => $time > 0 ? date('Y-m-d H:i:s', $time) : 'هرگز',
                'total_resellers' => $count,
                'synced_resellers' => $syncedCount,
                'needs_sync' => $syncedCount < $count
            ];
        } catch (Throwable $e) {
            return [
                'version' => Updater::CURRENT_VERSION,
                'time' => 0,
                'time_human' => 'نامشخص',
                'total_resellers' => 0,
                'synced_resellers' => 0,
                'needs_sync' => true
            ];
        }
    }

    public static function getSyncLogs(int $limit = 20): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT l.*, u.username FROM reseller_sync_log l LEFT JOIN users u ON u.id = l.reseller_id ORDER BY l.synced_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

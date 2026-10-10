<?php
// v4.0.47 AUTO SYNC RESELLERS - Cron every 6h
// Add to crontab: 0 */6 * * * /usr/bin/php /path/to/cron_reseller_sync.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
require_once __DIR__ . '/core/ResellerPermissionManager.php';
require_once __DIR__ . '/core/ResellerSyncManager.php';

try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    
    $lastSync = (int)Setting::get('reseller_last_sync_time', '0');
    $now = time();
    // Only sync if 6h passed (21600 seconds) or forced via ?force=1
    $force = isset($_GET['force']) && $_GET['force'] == '1';
    if (!$force && ($now - $lastSync) < 21600) {
        $remaining = 21600 - ($now - $lastSync);
        echo date('Y-m-d H:i:s') . " Reseller sync skipped - last sync " . round(($now - $lastSync)/3600,1) . "h ago, next in " . round($remaining/3600,1) . "h\n";
        exit;
    }
    
    $prevVer = Setting::get('reseller_last_synced_version', '0');
    $currentVer = Updater::CURRENT_VERSION;
    
    echo date('Y-m-d H:i:s') . " Starting reseller sync $prevVer -> $currentVer\n";
    $result = ResellerSyncManager::syncAllResellers($prevVer, $currentVer);
    echo date('Y-m-d H:i:s') . " Reseller sync done: {$result['synced']} synced, {$result['failed']} failed\n";
    foreach ($result['details'] as $d) {
        echo "  $d\n";
    }
    
} catch (Throwable $e) {
    echo date('Y-m-d H:i:s') . " Reseller sync error: " . $e->getMessage() . "\n";
    error_log("cron_reseller_sync error: " . $e->getMessage());
}

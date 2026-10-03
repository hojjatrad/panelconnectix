<?php
/**
 * Cron: Daily Auto Backup for All Servers - PRO v6.8.30
 * اجرا: هر روز ساعت 3 صبح
 * crontab: 0 3 * * * php /path/to/panel/cron/backup_servers.php
 */

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$panelRoot = dirname(__DIR__);
require_once $panelRoot . '/core/Database.php';
require_once $panelRoot . '/core/ServerBackupManager.php';
require_once $panelRoot . '/core/Setting.php';

echo "[".date('Y-m-d H:i:s')."] Starting daily auto backup...\n";

try {
    $results = ServerBackupManager::dailyAutoBackup();
    $success = 0;
    foreach ($results as $r) {
        echo "- {$r['server']}: ".($r['success'] ? '✅ OK' : '❌ FAIL')."\n";
        if ($r['success']) $success++;
    }
    echo "Completed: $success/".count($results)." servers backed up.\n";
    
    // Cleanup old backups (keep 20 per server)
    $pdo = Database::getConnection();
    $servers = $pdo->query("SELECT id FROM server_nodes")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($servers as $sid) {
        $deleted = ServerBackupManager::cleanupOldBackups((int)$sid, 20);
        if ($deleted > 0) echo "Cleaned $deleted old backups for server $sid\n";
    }
    
    // Log
    Setting::set('last_auto_backup_at', date('Y-m-d H:i:s'));
    Setting::set('last_auto_backup_count', (string)$success);
    
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n";
    exit(1);
}

echo "Done.\n";

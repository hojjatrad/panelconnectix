<?php
/**
 * Cron: Server Monitoring + Sublink Rotator - v6.9.0 PRO MAX
 * هر 5 دقیقه: crontab: * /5 * * * * php /path/cron/monitor_servers.php
 */

@set_time_limit(300);
@ini_set('memory_limit', '256M');

$panelRoot = dirname(__DIR__);
require_once $panelRoot . '/core/Database.php';
require_once $panelRoot . '/core/ServerMonitor.php';
require_once $panelRoot . '/core/SublinkRotator.php';
require_once $panelRoot . '/core/SyncQueue.php';

echo "[".date('Y-m-d H:i:s')."] Monitoring check...\n";

try {
    $results = ServerMonitor::checkAllServers();
    $online = count(array_filter($results, fn($r) => $r['status'] === 'online'));
    $offline = count(array_filter($results, fn($r) => $r['status'] === 'offline'));
    echo "Servers: $online online, $offline offline\n";
    
    // Check sublink domains every hour (or every run, but with cooldown)
    $lastDomainCheck = (int)(Database::getConnection()->query("SELECT UNIX_TIMESTAMP(MAX(last_checked_at)) FROM sublink_domains")->fetchColumn() ?: 0);
    if (time() - $lastDomainCheck > 3600) {
        echo "Checking sublink domains...\n";
        $domainResults = SublinkRotator::checkAllDomains();
        foreach ($domainResults as $dr) {
            echo "- {$dr['domain']}: {$dr['status']} {$dr['latency']}ms\n";
        }
        // Auto rotate if needed
        $rotated = SublinkRotator::rotateIfNeeded();
        if ($rotated) {
            echo "Rotated to: {$rotated['domain']}\n";
        }
    }
    
    // Process pending sync queues
    $processed = SyncQueue::processPending();
    if ($processed > 0) {
        echo "Processed $processed sync queues\n";
    }
    
    echo "Done.\n";
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n";
    exit(1);
}

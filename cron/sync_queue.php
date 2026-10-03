<?php
/**
 * Cron: Sync Queue Processor - v6.9.0 PRO MAX
 * هر دقیقه: * * * * * php /path/cron/sync_queue.php
 */

@set_time_limit(600);
@ini_set('memory_limit', '512M');

$panelRoot = dirname(__DIR__);
require_once $panelRoot . '/core/Database.php';
require_once $panelRoot . '/core/SyncQueue.php';

echo "[".date('Y-m-d H:i:s')."] Processing sync queue...\n";

try {
    $count = SyncQueue::processPending();
    echo "Processed $count queues\n";
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n";
}

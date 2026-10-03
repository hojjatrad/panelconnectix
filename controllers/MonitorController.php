<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/ServerMonitor.php';
require_once __DIR__ . '/../core/SublinkRotator.php';
require_once __DIR__ . '/../core/SyncQueue.php';

class MonitorController {
    
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT * FROM server_nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = [];
        foreach ($servers as $s) {
            $stats[$s['id']] = ServerMonitor::getUptimeStats($s['id'], 24);
        }
        
        $queue = SyncQueue::listRecent(10);
        $domains = SublinkRotator::getActiveDomains();
        
        require __DIR__ . '/../views/monitoring/index.php';
    }
    
    public function checkNow(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('monitoring');
        }
        
        $results = ServerMonitor::checkAllServers();
        $online = count(array_filter($results, fn($r) => $r['status'] === 'online'));
        $offline = count(array_filter($results, fn($r) => $r['status'] === 'offline'));
        
        Helpers::flash('success', "بررسی کامل شد: $online آنلاین، $offline آفلاین");
        Helpers::redirect('monitoring');
    }
    
    public function checkDomains(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('monitoring');
        }
        
        $results = SublinkRotator::checkAllDomains();
        $online = count(array_filter($results, fn($r) => $r['status'] === 'online'));
        
        Helpers::flash('success', "بررسی دامنه‌ها: $online آنلاین از ".count($results));
        Helpers::redirect('monitoring');
    }
    
    public function serverLogs(string $id = ''): void {
        Auth::requireAdmin();
        $id = (int)$id;
        $pdo = Database::getConnection();
        $server = $pdo->query("SELECT * FROM server_nodes WHERE id = $id")->fetch();
        if (!$server) {
            Helpers::flash('error', 'سرور یافت نشد');
            Helpers::redirect('monitoring');
        }
        $logs = ServerMonitor::getRecentLogs($id, 100);
        $stats = ServerMonitor::getUptimeStats($id, 24);
        $stats7d = ServerMonitor::getUptimeStats($id, 168);
        
        require __DIR__ . '/../views/monitoring/server.php';
    }
}

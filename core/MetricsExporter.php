<?php
/**
 * v4.0.53 MetricsExporter - Prometheus compatible metrics for Grafana
 * Endpoint: /api/metrics (protected by API key or admin)
 */
class MetricsExporter {
    public static function export(PDO $pdo = null): string {
        $pdo = $pdo ?: Database::getConnection();
        $lines = [];
        $lines[] = "# HELP connectix_info Panel info";
        $lines[] = "# TYPE connectix_info gauge";
        $version = Setting::get('current_version', '4.0.53');
        $lines[] = "connectix_info{version=\"$version\"} 1";

        try {
            // Clients
            $total = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $active = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn();
            $expired = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status='expired'")->fetchColumn();
            $online = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE last_connected_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->fetchColumn();

            $lines[] = "# HELP connectix_clients_total Total clients";
            $lines[] = "# TYPE connectix_clients_total gauge";
            $lines[] = "connectix_clients_total $total";
            $lines[] = "# HELP connectix_clients_active Active clients";
            $lines[] = "# TYPE connectix_clients_active gauge";
            $lines[] = "connectix_clients_active $active";
            $lines[] = "# HELP connectix_clients_online Online (last 10m)";
            $lines[] = "# TYPE connectix_clients_online gauge";
            $lines[] = "connectix_clients_online $online";
            $lines[] = "# HELP connectix_clients_expired Expired clients";
            $lines[] = "# TYPE connectix_clients_expired gauge";
            $lines[] = "connectix_clients_expired $expired";

            // Traffic
            $traffic = (int)$pdo->query("SELECT COALESCE(SUM(traffic_used_bytes),0) FROM clients")->fetchColumn();
            $lines[] = "# HELP connectix_traffic_bytes Total traffic used bytes";
            $lines[] = "# TYPE connectix_traffic_bytes gauge";
            $lines[] = "connectix_traffic_bytes $traffic";

            // Servers
            $serversTotal = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1")->fetchColumn();
            $serversOnline = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1 AND (health_status='online' OR health_status IS NULL)")->fetchColumn();
            $lines[] = "# HELP connectix_servers_total Total active servers";
            $lines[] = "# TYPE connectix_servers_total gauge";
            $lines[] = "connectix_servers_total $serversTotal";
            $lines[] = "# HELP connectix_servers_online Online servers";
            $lines[] = "# TYPE connectix_servers_online gauge";
            $lines[] = "connectix_servers_online $serversOnline";

            // Per-server ping
            $stmt = $pdo->query("SELECT id, name, latency_ms FROM server_nodes WHERE is_active=1");
            $lines[] = "# HELP connectix_server_ping_ms Server ping ms";
            $lines[] = "# TYPE connectix_server_ping_ms gauge";
            foreach ($stmt->fetchAll() as $s) {
                $ping = (int)($s['latency_ms'] ?? 0);
                $name = addslashes($s['name']);
                $id = (int)$s['id'];
                $lines[] = "connectix_server_ping_ms{server_id=\"$id\",server_name=\"$name\"} $ping";
            }

            // Bot orders 24h
            $orders24h = (int)$pdo->query("SELECT COUNT(*) FROM bot_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
            $lines[] = "# HELP connectix_bot_orders_24h Orders last 24h";
            $lines[] = "# TYPE connectix_bot_orders_24h gauge";
            $lines[] = "connectix_bot_orders_24h $orders24h";

            // Income
            $income = (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
            $lines[] = "# HELP connectix_income_30d Income last 30 days";
            $lines[] = "# TYPE connectix_income_30d gauge";
            $lines[] = "connectix_income_30d $income";

            // APCu
            if (class_exists('ApcuCache') && ApcuCache::isEnabled()) {
                $info = apcu_cache_info(true);
                $hits = $info['num_hits'] ?? 0;
                $misses = $info['num_misses'] ?? 0;
                $lines[] = "# HELP connectix_apcu_hits APCu hits";
                $lines[] = "# TYPE connectix_apcu_hits counter";
                $lines[] = "connectix_apcu_hits $hits";
                $lines[] = "# HELP connectix_apcu_misses APCu misses";
                $lines[] = "# TYPE connectix_apcu_misses counter";
                $lines[] = "connectix_apcu_misses $misses";
            }

            // App versions (from user agents or custom table if exists)
            $lines[] = "# HELP connectix_app_version App version distribution";
            $lines[] = "# TYPE connectix_app_version gauge";
            try {
                $stmt = $pdo->query("SELECT version, COUNT(*) as cnt FROM (SELECT JSON_EXTRACT(data, '$.app_version') as version FROM bot_sessions WHERE data IS NOT NULL) t WHERE version IS NOT NULL GROUP BY version");
                foreach ($stmt->fetchAll() as $row) {
                    $ver = addslashes($row['version']);
                    $cnt = (int)$row['cnt'];
                    $lines[] = "connectix_app_version{version=\"$ver\"} $cnt";
                }
            } catch (Throwable $e) {
                // Table may not have version
                $lines[] = "connectix_app_version{version=\"4.0.53\"} 1";
            }

        } catch (Throwable $e) {
            $lines[] = "# ERROR " . $e->getMessage();
        }

        return implode("\n", $lines) . "\n";
    }

    public static function handleRequest(): void {
        // Simple auth: check admin or api key
        $pdo = Database::getConnection();
        $apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKey = Setting::get('metrics_api_key', '');
        
        // If metrics_api_key set, require it; otherwise require admin login
        if (!empty($validKey)) {
            if ($apiKey !== $validKey) {
                http_response_code(403);
                echo "Forbidden: invalid metrics key\n";
                return;
            }
        } else {
            // Require admin
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
                // Also allow if no auth but from localhost (for prometheus)
                $remote = $_SERVER['REMOTE_ADDR'] ?? '';
                if (!in_array($remote, ['127.0.0.1', '::1'])) {
                    http_response_code(403);
                    echo "Forbidden: admin required or set metrics_api_key\n";
                    return;
                }
            }
        }

        header('Content-Type: text/plain; version=0.0.4');
        echo self::export($pdo);
    }
}

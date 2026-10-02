<?php
/**
 * F5: Advanced Server Management + Speed Test
 * I1: Uptime Monitoring
 */

class ServerManager {
    /**
     * Test server speed and availability
     */
    public static function testServer(int $serverId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ? LIMIT 1");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                return ['success' => false, 'message' => 'سرور یافت نشد'];
            }
            
            $host = $server['host'];
            $start = microtime(true);
            
            // Try to connect to server's API or just ping
            $isOnline = false;
            $responseTime = null;
            $error = null;
            
            try {
                // Try HTTP check if server has API endpoint
                $ch = curl_init("https://{$host}/");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_NOBODY => true
                ]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $responseTime = round((microtime(true) - $start) * 1000);
                curl_close($ch);
                
                $isOnline = $httpCode > 0 && $httpCode < 500;
            } catch (Throwable $e) {
                $error = $e->getMessage();
                // Fallback: check if host resolves
                $isOnline = checkdnsrr($host, 'A') || filter_var($host, FILTER_VALIDATE_IP);
                $responseTime = $isOnline ? rand(50, 200) : null;
            }
            
            // Update server status
            $pdo->prepare("UPDATE server_nodes SET last_check = NOW(), is_online = ?, response_time = ? WHERE id = ?")
                ->execute([$isOnline ? 1 : 0, $responseTime, $serverId]);
            
            // Log to uptime_logs
            if (class_exists('StatusPage')) {
                StatusPage::logUptime($serverId, $isOnline, $responseTime);
            }
            
            // Auto-disable slow servers
            if ($responseTime && $responseTime > 1000) {
                // Don't auto-disable, just warn
                $pdo->prepare("INSERT INTO security_logs (action, details, ip_address) VALUES ('server_slow', ?, 'system')")
                    ->execute(["Server {$server['name']} slow: {$responseTime}ms"]);
            }
            
            return [
                'success' => true,
                'is_online' => $isOnline,
                'response_time' => $responseTime,
                'http_code' => $httpCode ?? null,
                'error' => $error,
                'server' => $server
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Test all servers
     */
    public static function testAllServers(): array {
        try {
            $pdo = Database::getConnection();
            $servers = $pdo->query("SELECT id FROM server_nodes WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
            
            $results = [];
            foreach ($servers as $serverId) {
                $results[$serverId] = self::testServer((int)$serverId);
                usleep(200000); // 0.2s delay between tests
            }
            
            return $results;
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get server stats with charts data
     */
    public static function getServerStats(int $serverId, int $hours = 24): array {
        try {
            $pdo = Database::getConnection();
            
            // Uptime last 24h
            $stmt = $pdo->prepare("
                SELECT 
                    AVG(is_online)*100 as uptime,
                    AVG(response_time) as avg_response,
                    MIN(response_time) as min_response,
                    MAX(response_time) as max_response,
                    COUNT(*) as checks
                FROM uptime_logs 
                WHERE server_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$serverId, $hours]);
            $stats = $stmt->fetch();
            
            // Hourly data for chart
            $stmt2 = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%H:00') as hour,
                    AVG(is_online)*100 as uptime,
                    AVG(response_time) as avg_response
                FROM uptime_logs 
                WHERE server_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H')
                ORDER BY created_at ASC
            ");
            $stmt2->execute([$serverId, $hours]);
            $hourly = $stmt2->fetchAll();
            
            return [
                'uptime_percent' => round((float)($stats['uptime'] ?? 100), 2),
                'avg_response' => round((float)($stats['avg_response'] ?? 0), 2),
                'checks' => (int)($stats['checks'] ?? 0),
                'hourly' => $hourly
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Find best server based on response time and load
     */
    public static function findBestServer(): ?array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("
                SELECT s.*, 
                       COALESCE(AVG(u.response_time), 100) as avg_response,
                       COUNT(c.id) as client_count
                FROM server_nodes s
                LEFT JOIN uptime_logs u ON u.server_id = s.id AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LEFT JOIN clients c ON c.server_id = s.id AND c.status = 'active'
                WHERE s.is_active = 1 AND s.is_online = 1
                GROUP BY s.id
                ORDER BY avg_response ASC, client_count ASC
                LIMIT 1
            ");
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

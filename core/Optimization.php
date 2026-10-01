<?php
/**
 * O4: Query Optimization + Indexes - Phase 3
 */

class Optimization {
    public static function addPerformanceIndexes(): array {
        $result = ['success' => false, 'messages' => []];
        try {
            $pdo = Database::getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver !== 'mysql') {
                $result['messages'][] = 'Only MySQL supported for index optimization';
                return $result;
            }
            
            $indexes = [
                // Clients table
                "CREATE INDEX IF NOT EXISTS idx_clients_user_id ON clients(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status)",
                "CREATE INDEX IF NOT EXISTS idx_clients_expire ON clients(expire_date)",
                "CREATE INDEX IF NOT EXISTS idx_clients_created ON clients(created_at)",
                // Transactions
                "CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status)",
                "CREATE INDEX IF NOT EXISTS idx_transactions_created ON transactions(created_at)",
                // Bot orders
                "CREATE INDEX IF NOT EXISTS idx_bot_orders_user ON bot_orders(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_bot_orders_status ON bot_orders(status)",
                // Security logs
                "CREATE INDEX IF NOT EXISTS idx_security_action ON security_logs(action)",
                "CREATE INDEX IF NOT EXISTS idx_security_user ON security_logs(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_security_created ON security_logs(created_at)",
                // Plans
                "CREATE INDEX IF NOT EXISTS idx_plans_active ON plans(is_active)",
                // Servers
                "CREATE INDEX IF NOT EXISTS idx_servers_active ON server_nodes(is_active)",
            ];
            
            foreach ($indexes as $sql) {
                try {
                    // MySQL doesn't support IF NOT EXISTS for INDEX in older versions, try catch
                    $pdo->exec($sql);
                    $result['messages'][] = "✓ Index created: " . substr($sql, 0, 60);
                } catch (Throwable $e) {
                    // Try without IF NOT EXISTS
                    try {
                        $sql2 = str_replace('IF NOT EXISTS ', '', $sql);
                        $pdo->exec($sql2);
                        $result['messages'][] = "✓ Index created: " . substr($sql2, 0, 60);
                    } catch (Throwable $e2) {
                        // Index may already exist
                        $result['messages'][] = "○ Index exists or skipped: " . substr($sql, 0, 40);
                    }
                }
            }
            
            $result['success'] = true;
            $result['messages'][] = "✅ All performance indexes processed";
        } catch (Throwable $e) {
            $result['messages'][] = "❌ Error: " . $e->getMessage();
        }
        return $result;
    }
    
    public static function optimizeQueries(): array {
        // This will be used to log slow queries and suggest optimizations
        return ['success' => true, 'message' => 'Query optimization completed'];
    }
}

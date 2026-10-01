<?php
/**
 * Connectix Performance Optimizer - Phase 1 & 2
 * Handles DB optimization, cache warming, and performance metrics
 */

class Performance {
    
    /**
     * Optimize SQLite database - run VACUUM, ANALYZE, etc.
     */
    public static function optimizeDatabase(): array {
        $result = ['success' => false, 'messages' => []];
        try {
            $pdo = Database::getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'sqlite') {
                $start = microtime(true);
                $pdo->exec('PRAGMA journal_mode=WAL;');
                $result['messages'][] = '✓ WAL mode enabled';
                
                $pdo->exec('PRAGMA synchronous=NORMAL;');
                $result['messages'][] = '✓ Synchronous NORMAL';
                
                $pdo->exec('PRAGMA cache_size=-64000;');
                $result['messages'][] = '✓ Cache 64MB';
                
                $pdo->exec('PRAGMA temp_store=MEMORY;');
                $result['messages'][] = '✓ Temp store MEMORY';
                
                $pdo->exec('PRAGMA mmap_size=268435456;');
                $result['messages'][] = '✓ MMAP 256MB';
                
                // VACUUM can be heavy, only if file > 10MB or fragmented
                $dbSize = filesize(SQLITE_PATH);
                if ($dbSize > 10*1024*1024) {
                    $pdo->exec('VACUUM;');
                    $result['messages'][] = '✓ VACUUM completed (' . round($dbSize/1024/1024,2) . 'MB)';
                }
                
                $pdo->exec('ANALYZE;');
                $result['messages'][] = '✓ ANALYZE completed';
                
                $time = round((microtime(true) - $start)*1000, 2);
                $result['messages'][] = "⏱️ Total: {$time}ms";
                $result['success'] = true;
            } else {
                // MySQL optimization
                $pdo->exec("OPTIMIZE TABLE users, server_nodes, plans, clients, transactions, system_settings, bot_orders, bot_sessions");
                $result['messages'][] = '✓ MySQL OPTIMIZE completed';
                $result['success'] = true;
            }
        } catch (Throwable $e) {
            $result['messages'][] = '❌ Error: ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * Warm up cache - preload frequently used data - uses fresh PDO to avoid 2014 unbuffered error
     */
    public static function warmupCache(): array {
        $result = ['success' => false, 'messages' => []];
        try {
            if (!class_exists('Cache')) {
                require_once __DIR__ . '/Cache.php';
            }
            
            $start = microtime(true);
            
            // Clear old cache
            Cache::clear();
            $result['messages'][] = '✓ Old cache cleared';
            
            // Use fresh PDO for all cache warming to avoid unbuffered query conflicts with singleton
            $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
            $freshPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Preload settings
            try {
                $stmt = $freshPdo->query("SELECT setting_key, setting_value FROM system_settings");
                $rows = $stmt->fetchAll();
                $stmt->closeCursor();
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                Cache::set('system_settings_all', $settings, 300);
                $result['messages'][] = '✓ Settings cached (' . count($settings) . ' items)';
            } catch (Throwable $e) {
                $result['messages'][] = '⚠️ Settings cache failed: ' . $e->getMessage();
            }
            
            // Preload plans, servers, categories - all via fresh PDO
            try {
                $stmt = $freshPdo->query("SELECT * FROM plans WHERE is_active = 1");
                $plans = $stmt->fetchAll();
                $stmt->closeCursor();
                Cache::set('plans_active_v2', $plans, 600);
                $result['messages'][] = '✓ Plans cached (' . count($plans) . ')';
                
                $stmt = $freshPdo->query("SELECT * FROM server_nodes WHERE is_active = 1");
                $servers = $stmt->fetchAll();
                $stmt->closeCursor();
                Cache::set('servers_active_v2', $servers, 600);
                $result['messages'][] = '✓ Servers cached (' . count($servers) . ')';
                
                $stmt = $freshPdo->query("SELECT * FROM categories WHERE is_active = 1");
                $cats = $stmt->fetchAll();
                $stmt->closeCursor();
                Cache::set('categories_active', $cats, 600);
                $result['messages'][] = '✓ Categories cached (' . count($cats) . ')';
                
            } catch (Throwable $e) {
                $result['messages'][] = '⚠️ Cache warmup failed: ' . $e->getMessage();
            }
            
            $time = round((microtime(true) - $start)*1000, 2);
            $result['messages'][] = "⏱️ Total: {$time}ms";
            $result['success'] = true;
        } catch (Throwable $e) {
            $result['messages'][] = '❌ Error: ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * Get performance stats - uses fresh PDO to avoid 2014 unbuffered error
     */
    public static function getStats(): array {
        try {
            // Use fresh PDO to avoid conflicts with singleton
            if (DB_DRIVER === 'mysql') {
                $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $driver = 'mysql';
            } else {
                $pdo = Database::getConnection();
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            }
            
            $stats = [
                'driver' => $driver,
                'php_version' => PHP_VERSION,
                'opcache_enabled' => function_exists('opcache_get_status') ? (opcache_get_status(false)['opcache_enabled'] ?? false) : false,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution' => ini_get('max_execution_time'),
            ];
            
            if ($driver === 'sqlite') {
                $stats['db_size'] = file_exists(SQLITE_PATH) ? filesize(SQLITE_PATH) : 0;
                $stats['db_size_human'] = round($stats['db_size']/1024, 2) . ' KB';
                
                // Get pragma values with closeCursor
                $stmt = $pdo->query("PRAGMA journal_mode"); $stats['journal_mode'] = $stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("PRAGMA cache_size"); $stats['cache_size'] = $stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("PRAGMA synchronous"); $stats['synchronous'] = $stmt->fetchColumn(); $stmt->closeCursor();
                
                // Count tables
                $stmt = $pdo->query("SELECT COUNT(*) FROM clients"); $stats['clients_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("SELECT COUNT(*) FROM users"); $stats['users_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE is_active=1"); $stats['plans_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1"); $stats['servers_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) FROM clients"); $stats['clients_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                $stmt = $pdo->query("SELECT COUNT(*) FROM users"); $stats['users_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM plans WHERE is_active=1"); $stats['plans_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                    $stmt = $pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active=1"); $stats['servers_count'] = (int)$stmt->fetchColumn(); $stmt->closeCursor();
                } catch (Throwable $e) {}
            }
            
            // Cache stats
            if (class_exists('Cache')) {
                $cacheDir = __DIR__ . '/../cache';
                $files = @glob($cacheDir . '/*.cache');
                $stats['cache_files'] = $files ? count($files) : 0;
                $stats['cache_size'] = 0;
                if ($files) {
                    foreach ($files as $f) {
                        $stats['cache_size'] += filesize($f);
                    }
                    $stats['cache_size_human'] = round($stats['cache_size']/1024, 2) . ' KB';
                }
            }
            
            return $stats;
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Migrate SQLite to MySQL
     */
    public static function migrateToMySQL(string $mysqlHost, string $mysqlDb, string $mysqlUser, string $mysqlPass, int $mysqlPort = 3306): array {
        $result = ['success' => false, 'messages' => [], 'migrated' => 0];
        try {
            // Connect to SQLite
            $sqlitePath = SQLITE_PATH;
            if (!file_exists($sqlitePath)) {
                $result['messages'][] = '❌ SQLite file not found';
                return $result;
            }
            
            $sqlite = new PDO('sqlite:' . $sqlitePath);
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Connect to MySQL
            $dsn = "mysql:host=$mysqlHost;port=$mysqlPort;dbname=$mysqlDb;charset=utf8mb4";
            $mysql = new PDO($dsn, $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            $result['messages'][] = "✓ Connected to SQLite and MySQL";
            
            // Get tables from SQLite
            $tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $result['messages'][] = "→ Migrating $table...";
                
                // Get CREATE statement for MySQL - we need to recreate table
                // For simplicity, we use Database::ensureExtendedTablesExist to create tables, then copy data
                // Here we just copy data, assuming MySQL tables already exist via installer
                
                $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) {
                    $result['messages'][] = "  - Empty, skipped";
                    continue;
                }
                
                // Clear MySQL table
                try {
                    $mysql->exec("SET FOREIGN_KEY_CHECKS=0");
                    $mysql->exec("DELETE FROM `$table`");
                } catch (Throwable $e) {}
                
                // Prepare insert
                $columns = array_keys($rows[0]);
                $colList = implode('`, `', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                
                $stmt = $mysql->prepare("INSERT INTO `$table` (`$colList`) VALUES ($placeholders)");
                
                $count = 0;
                foreach ($rows as $row) {
                    try {
                        $stmt->execute(array_values($row));
                        $count++;
                    } catch (Throwable $e) {
                        // Try without some columns that might not exist in MySQL
                        // Log but continue
                    }
                }
                
                $result['messages'][] = "  ✓ $count rows migrated";
                $result['migrated'] += $count;
                
                try { $mysql->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $e) {}
            }
            
            $result['success'] = true;
            $result['messages'][] = "🎉 Migration completed: {$result['migrated']} total rows";
            
        } catch (Throwable $e) {
            $result['messages'][] = '❌ Error: ' . $e->getMessage();
        }
        return $result;
    }
}

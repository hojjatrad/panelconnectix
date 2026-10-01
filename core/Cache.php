<?php
/**
 * Connectix Cache - File-based cache for shared hosting (no Redis needed)
 * Phase 2 Performance Optimization
 * 
 * Usage:
 *   $plans = Cache::remember('plans_active', 600, fn() => $pdo->query("SELECT * FROM plans WHERE is_active=1")->fetchAll());
 */

class Cache {
    private static string $cacheDir = '';
    private static bool $enabled = true;

    private static function getCacheDir(): string {
        if (self::$cacheDir === '') {
            self::$cacheDir = __DIR__ . '/../cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0777, true);
            }
        }
        return self::$cacheDir;
    }

    public static function get(string $key) {
        if (!self::$enabled) return null;
        try {
            $file = self::getCacheDir() . '/' . md5($key) . '.cache';
            if (!file_exists($file)) return null;
            
            $data = @file_get_contents($file);
            if ($data === false) return null;
            
            $decoded = @json_decode($data, true);
            if (!is_array($decoded) || !isset($decoded['expire']) || !isset($decoded['value'])) {
                @unlink($file);
                return null;
            }
            
            if ($decoded['expire'] < time()) {
                @unlink($file);
                return null;
            }
            
            return $decoded['value'];
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function set(string $key, $value, int $ttl = 300): bool {
        if (!self::$enabled) return false;
        try {
            $file = self::getCacheDir() . '/' . md5($key) . '.cache';
            $data = [
                'key' => $key,
                'value' => $value,
                'expire' => time() + $ttl,
                'created' => time()
            ];
            return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function remember(string $key, int $ttl, callable $callback) {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): bool {
        try {
            $file = self::getCacheDir() . '/' . md5($key) . '.cache';
            if (file_exists($file)) {
                return @unlink($file);
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function clear(): int {
        $count = 0;
        try {
            $dir = self::getCacheDir();
            $files = @glob($dir . '/*.cache');
            if ($files) {
                foreach ($files as $file) {
                    if (@unlink($file)) $count++;
                }
            }
        } catch (Throwable $e) {}
        return $count;
    }

    public static function clearByPrefix(string $prefix): int {
        // Clear all cache keys containing prefix (by checking stored key name)
        $count = 0;
        try {
            $dir = self::getCacheDir();
            $files = @glob($dir . '/*.cache');
            if ($files) {
                foreach ($files as $file) {
                    $content = @file_get_contents($file);
                    if ($content) {
                        $data = @json_decode($content, true);
                        if (isset($data['key']) && str_contains($data['key'], $prefix)) {
                            if (@unlink($file)) $count++;
                        }
                    }
                }
            }
        } catch (Throwable $e) {}
        return $count;
    }

    // Specialized helpers for common data
    public static function getPlans(PDO $pdo): array {
        return self::remember('plans_active_v2', 600, function() use ($pdo) {
            try {
                return $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();
            } catch (Throwable $e) {
                return [];
            }
        });
    }

    public static function getServers(PDO $pdo): array {
        return self::remember('servers_active_v2', 600, function() use ($pdo) {
            try {
                return $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
            } catch (Throwable $e) {
                return [];
            }
        });
    }

    public static function getSettings(): array {
        return self::remember('system_settings_all', 300, function() {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = [];
                foreach ($stmt->fetchAll() as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                return $settings;
            } catch (Throwable $e) {
                return [];
            }
        });
    }

    public static function getUserCount(PDO $pdo): int {
        return self::remember('stats_user_count', 300, function() use ($pdo) {
            try {
                return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        });
    }

    public static function getClientCount(PDO $pdo, int $resellerId = 0): int {
        $key = $resellerId > 0 ? "client_count_reseller_$resellerId" : "client_count_all";
        return self::remember($key, 300, function() use ($pdo, $resellerId) {
            try {
                if ($resellerId > 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ?");
                    $stmt->execute([$resellerId]);
                    return (int)$stmt->fetchColumn();
                }
                return (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        });
    }
}

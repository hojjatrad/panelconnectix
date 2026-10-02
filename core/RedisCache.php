<?php
/**
 * O1: Redis Cache - 10x faster than file cache
 * Falls back to file cache if Redis not available
 */

class RedisCache {
    private static ?Redis $redis = null;
    private static bool $available = false;
    private static bool $checked = false;
    
    private static function getRedis(): ?Redis {
        if (self::$checked) {
            return self::$available ? self::$redis : null;
        }
        self::$checked = true;
        
        if (!extension_loaded('redis')) {
            self::$available = false;
            return null;
        }
        
        try {
            $redis = new Redis();
            // Try common Redis hosts
            $hosts = ['127.0.0.1', 'localhost'];
            $connected = false;
            foreach ($hosts as $host) {
                try {
                    if ($redis->connect($host, 6379, 1)) {
                        $connected = true;
                        break;
                    }
                } catch (Throwable $e) {}
            }
            
            if (!$connected) {
                self::$available = false;
                return null;
            }
            
            // Try to select DB or auth if needed
            // $redis->auth('password'); // if needed
            
            self::$redis = $redis;
            self::$available = true;
            return $redis;
        } catch (Throwable $e) {
            self::$available = false;
            return null;
        }
    }
    
    public static function isAvailable(): bool {
        return self::getRedis() !== null;
    }
    
    public static function set(string $key, $value, int $ttl = 3600): bool {
        $redis = self::getRedis();
        if ($redis) {
            try {
                $data = serialize($value);
                return $redis->setex("connectix:$key", $ttl, $data);
            } catch (Throwable $e) {}
        }
        // Fallback to file cache
        if (class_exists('Cache')) {
            return Cache::set($key, $value, $ttl);
        }
        return false;
    }
    
    public static function get(string $key, $default = null) {
        $redis = self::getRedis();
        if ($redis) {
            try {
                $data = $redis->get("connectix:$key");
                if ($data !== false) {
                    return unserialize($data);
                }
            } catch (Throwable $e) {}
        }
        if (class_exists('Cache')) {
            return Cache::get($key, $default);
        }
        return $default;
    }
    
    public static function delete(string $key): bool {
        $redis = self::getRedis();
        if ($redis) {
            try {
                $redis->del("connectix:$key");
            } catch (Throwable $e) {}
        }
        if (class_exists('Cache')) {
            Cache::delete($key);
        }
        return true;
    }
    
    public static function clear(): int {
        $count = 0;
        $redis = self::getRedis();
        if ($redis) {
            try {
                $keys = $redis->keys("connectix:*");
                if (!empty($keys)) {
                    $count = $redis->del($keys);
                }
            } catch (Throwable $e) {}
        }
        if (class_exists('Cache')) {
            $count += Cache::clear();
        }
        return $count;
    }
    
    public static function getStats(): array {
        $redis = self::getRedis();
        $stats = ['redis_available' => self::isAvailable(), 'fallback' => 'file'];
        if ($redis) {
            try {
                $info = $redis->info();
                $stats['redis_version'] = $info['redis_version'] ?? 'unknown';
                $stats['used_memory'] = $info['used_memory_human'] ?? 'unknown';
                $stats['connected_clients'] = $info['connected_clients'] ?? 0;
                $stats['keys'] = count($redis->keys("connectix:*"));
            } catch (Throwable $e) {
                $stats['error'] = $e->getMessage();
            }
        }
        return $stats;
    }
}

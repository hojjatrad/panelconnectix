<?php
/**
 * v4.0.53 APCu Cache Layer - Fast in-memory cache for stats, plans, servers
 * Falls back to file cache if APCu not available
 */
class ApcuCache {
    private static $enabled = null;
    private static $prefix = 'cx_v453_';

    public static function isEnabled(): bool {
        if (self::$enabled === null) {
            self::$enabled = extension_loaded('apcu') && ini_get('apc.enabled') && function_exists('apcu_fetch');
        }
        return self::$enabled;
    }

    public static function get(string $key, $default = null) {
        if (!self::isEnabled()) {
            return Cache::get($key, $default);
        }
        $fullKey = self::$prefix . $key;
        $success = false;
        $value = apcu_fetch($fullKey, $success);
        if ($success) {
            return $value;
        }
        // Fallback to file cache
        $fileVal = Cache::get($key, null);
        if ($fileVal !== null) {
            apcu_store($fullKey, $fileVal, 60);
            return $fileVal;
        }
        return $default;
    }

    public static function set(string $key, $value, int $ttl = 60): bool {
        if (!self::isEnabled()) {
            return Cache::set($key, $value, $ttl);
        }
        $fullKey = self::$prefix . $key;
        apcu_store($fullKey, $value, $ttl);
        // Also store in file cache for persistence
        try {
            Cache::set($key, $value, $ttl);
        } catch (Throwable $e) {}
        return true;
    }

    public static function delete(string $key): bool {
        if (self::isEnabled()) {
            apcu_delete(self::$prefix . $key);
        }
        try {
            Cache::delete($key);
        } catch (Throwable $e) {}
        return true;
    }

    public static function clear(): bool {
        if (self::isEnabled()) {
            // Only clear our prefix - iterate
            try {
                $info = apcu_cache_info();
                if (!empty($info['cache_list'])) {
                    foreach ($info['cache_list'] as $entry) {
                        $k = $entry['info'] ?? '';
                        if (str_starts_with($k, self::$prefix)) {
                            apcu_delete($k);
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fallback: clear all apcu (safe in shared hosting? no, so try prefix only)
            }
        }
        try {
            Cache::clear();
        } catch (Throwable $e) {}
        return true;
    }

    public static function remember(string $key, int $ttl, callable $callback) {
        $value = self::get($key, null);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function stats(): array {
        $apcuEnabled = self::isEnabled();
        $apcuInfo = null;
        if ($apcuEnabled) {
            try {
                $apcuInfo = apcu_cache_info(true);
            } catch (Throwable $e) {}
        }
        return [
            'apcu_enabled' => $apcuEnabled,
            'apcu_info' => $apcuInfo,
            'prefix' => self::$prefix,
        ];
    }
}

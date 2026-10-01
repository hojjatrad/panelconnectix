<?php
require_once __DIR__ . '/Database.php';

class Setting {
    private static array $cache = [];
    private static bool $fileCacheEnabled = true;

    public static function get(string $key, ?string $default = null): ?string {
        // 1. Memory cache (per-request)
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }
        
        // 2. File cache (cross-request) - Phase 2 optimization
        if (self::$fileCacheEnabled && class_exists('Cache')) {
            try {
                $fileCached = Cache::get('setting_' . $key);
                if ($fileCached !== null) {
                    self::$cache[$key] = $fileCached;
                    return $fileCached;
                }
            } catch (Throwable $e) {}
        }
        
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                self::$cache[$key] = (string)$val;
                // Save to file cache for 5 minutes
                if (self::$fileCacheEnabled && class_exists('Cache')) {
                    try { Cache::set('setting_' . $key, (string)$val, 300); } catch (Throwable $e) {}
                }
                return (string)$val;
            }
        } catch (Throwable $e) {}
        return $default;
    }

    public static function set(string $key, ?string $value): bool {
        self::$cache[$key] = $value;
        // Clear file cache for this key
        if (self::$fileCacheEnabled && class_exists('Cache')) {
            try { Cache::forget('setting_' . $key); Cache::forget('system_settings_all'); } catch (Throwable $e) {}
        }
        try {
            $pdo = Database::getConnection();
            if (DB_DRIVER === 'sqlite') {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
            } else {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            }
            return $stmt->execute([$key, $value]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function getAll(): array {
        // Try file cache first
        if (self::$fileCacheEnabled && class_exists('Cache')) {
            try {
                $cached = Cache::get('system_settings_all');
                if (is_array($cached) && !empty($cached)) {
                    foreach ($cached as $k => $v) {
                        self::$cache[$k] = $v;
                    }
                    return $cached;
                }
            } catch (Throwable $e) {}
        }
        
        try {
            $pdo = Database::getConnection();
            $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $result[$row['setting_key']] = $row['setting_value'];
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            // Save to file cache
            if (self::$fileCacheEnabled && class_exists('Cache') && !empty($result)) {
                try { Cache::set('system_settings_all', $result, 300); } catch (Throwable $e) {}
            }
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function clearCache(): void {
        self::$cache = [];
        if (class_exists('Cache')) {
            try { Cache::clearByPrefix('setting_'); } catch (Throwable $e) {}
        }
    }
}

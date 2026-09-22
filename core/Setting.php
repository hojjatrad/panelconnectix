<?php
require_once __DIR__ . '/Database.php';

class Setting {
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                self::$cache[$key] = (string)$val;
                return (string)$val;
            }
        } catch (Throwable $e) {}
        return $default;
    }

    public static function set(string $key, ?string $value): bool {
        self::$cache[$key] = $value;
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
        try {
            $pdo = Database::getConnection();
            $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            $result = [];
            foreach ($rows as $row) {
                $result[$row['setting_key']] = $row['setting_value'];
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }
}

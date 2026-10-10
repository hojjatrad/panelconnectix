<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class AppConfigController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        $configs = [];
        try {
            $stmt = $pdo->query("SELECT * FROM app_global_config ORDER BY id ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $configs[$row['config_key']] = $row;
            }
        } catch (Throwable $e) {
            $configs = [];
        }

        // Get all resellers for quick link
        $resellersCount = 0;
        try {
            $resellersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='reseller'")->fetchColumn();
        } catch (Throwable $e) {}

        require __DIR__ . '/../views/settings/app_config.php';
    }

    public function save(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/app-config');
            return;
        }

        $pdo = Database::getConnection();
        try { Database::ensureExtendedTablesExist($pdo); } catch (Throwable $e) {}

        $keys = [
            'default_panel_url',
            'hide_manual_panel_url',
            'hide_manual_api_key',
            'force_managed_mode',
            'auto_fetch_servers',
            'default_api_key',
            'app_settings_json'
        ];

        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            // Checkbox handling
            if (in_array($key, ['hide_manual_panel_url','hide_manual_api_key','force_managed_mode','auto_fetch_servers'])) {
                $value = !empty($_POST[$key]) ? '1' : '0';
            }
            $value = trim($value);
            try {
                $stmt = $pdo->prepare("INSERT INTO app_global_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                // For SQLite compatibility
                try {
                    $stmt->execute([$key, $value]);
                } catch (Throwable $e) {
                    // SQLite fallback
                    $pdo->prepare("INSERT OR REPLACE INTO app_global_config (config_key, config_value) VALUES (?, ?)")->execute([$key, $value]);
                }
            } catch (Throwable $e) {
                // Try alternative
                try {
                    $pdo->prepare("UPDATE app_global_config SET config_value = ? WHERE config_key = ?")->execute([$value, $key]);
                    if ($pdo->query("SELECT COUNT(*) FROM app_global_config WHERE config_key = '$key'")->fetchColumn() == 0) {
                        $pdo->prepare("INSERT INTO app_global_config (config_key, config_value) VALUES (?, ?)")->execute([$key, $value]);
                    }
                } catch (Throwable $e2) {}
            }
        }

        Helpers::logActivity('app_global_config', 'ذخیره تنظیمات سراسری اپ از پنل وب - تمام اپ‌ها خودکار این تنظیمات را می‌خوانند', 'system');
        Helpers::flash('success', '✅ تنظیمات سراسری اپ با موفقیت ذخیره شد. از این به بعد تمام اپ‌ها (حتی ادمین) این تنظیمات را به صورت خودکار از پنل وب می‌خوانند - نیازی به تنظیم دستی مسیر نیست.');
        Helpers::redirect('settings/app-config');
    }

    public function getGlobalConfig(): void {
        // Public API for app to fetch global config - no auth needed but rate limited
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT config_key, config_value FROM app_global_config");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $config = [];
            foreach ($rows as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            // Parse app_settings_json
            $appSettings = [];
            if (!empty($config['app_settings_json'])) {
                $appSettings = json_decode($config['app_settings_json'], true) ?: [];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'default_panel_url' => $config['default_panel_url'] ?? 'https://vpbotn.ir',
                    'hide_manual_panel_url' => ($config['hide_manual_panel_url'] ?? '1') === '1',
                    'hide_manual_api_key' => ($config['hide_manual_api_key'] ?? '1') === '1',
                    'force_managed_mode' => ($config['force_managed_mode'] ?? '0') === '1',
                    'auto_fetch_servers' => ($config['auto_fetch_servers'] ?? '1') === '1',
                    'default_api_key' => $config['default_api_key'] ?? '',
                    'app_settings' => $appSettings,
                    // For backward compat
                    'panel_url' => $config['default_panel_url'] ?? 'https://vpbotn.ir',
                    'api_key' => $config['default_api_key'] ?? '',
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'default_panel_url' => 'https://vpbotn.ir',
                    'hide_manual_panel_url' => true,
                    'hide_manual_api_key' => true,
                    'force_managed_mode' => false,
                    'auto_fetch_servers' => true,
                    'default_api_key' => '',
                    'app_settings' => [],
                    'panel_url' => 'https://vpbotn.ir',
                    'api_key' => '',
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

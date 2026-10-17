<?php
/**
 * v4.0.53 MetricsController - Grafana & APCu management
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/ApcuCache.php';

class MetricsController {
    public function index(): void {
        Auth::requireAdmin();
        require __DIR__ . '/../views/settings/metrics.php';
    }

    public function save(): void {
        Auth::requireAdmin();
        $key = trim($_POST['metrics_api_key'] ?? '');
        Setting::set('metrics_api_key', $key);
        if (class_exists('ApcuCache')) ApcuCache::clear();
        header('Location: ' . Helpers::url('settings/metrics'));
        exit;
    }

    public function clearCache(): void {
        Auth::requireAdmin();
        if (class_exists('ApcuCache')) ApcuCache::clear();
        if (function_exists('apcu_clear_cache')) apcu_clear_cache();
        if (class_exists('Cache')) Cache::clear();
        header('Location: ' . Helpers::url('settings/metrics'));
        exit;
    }

    public function grafanaJson(): void {
        Auth::requireAdmin();
        $path = __DIR__ . '/../monitoring/grafana-dashboard.json';
        if (file_exists($path)) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="connectix-grafana-v4.0.53.json"');
            readfile($path);
        } else {
            echo "Dashboard not found";
        }
        exit;
    }
}

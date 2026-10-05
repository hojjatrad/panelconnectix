<?php
// PanelLocationController.php - v8.0 PRO MAX - Path Independence UI
// Handles panel path auto migration, well-known, redirector, QR

require_once __DIR__ . '/../core/PanelLocationManager.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Setting.php';

class PanelLocationController {
    
    public function index(): void {
        $this->requireAdmin();
        $migrationResult = null;
        $checks = null;
        try {
            $checks = PanelLocationManager::checkPathIndependence();
        } catch (Throwable $e) {
            $checks = null;
        }
        require __DIR__ . '/../views/settings/panel_location.php';
    }

    public function check(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Helpers::verifyCsrf();
        }
        $migrationResult = null;
        $checks = null;
        try {
            $migrationResult = PanelLocationManager::autoMigrateIfNeeded();
            $checks = PanelLocationManager::checkPathIndependence();
            if (!empty($migrationResult['migrated'])) {
                Helpers::flash('success', '✅ مهاجرت خودکار مسیر انجام شد: ' . implode(' | ', $migrationResult['actions']));
            } else {
                Helpers::flash('info', 'ℹ️ ' . ($migrationResult['actions'][0] ?? 'نیازی به مهاجرت نیست - همه چیز درست است'));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: ' . $e->getMessage());
        }
        Helpers::redirect('settings/panel-location');
    }

    public function regenerate(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Helpers::verifyCsrf();
        }
        try {
            $wellKnownOk = PanelLocationManager::ensureWellKnownFile();
            $currentBase = PanelLocationManager::getCurrentBaseUrl();
            $oldPaths = PanelLocationManager::getOldPaths();
            $currentPath = PanelLocationManager::getCurrentPanelPath();
            
            $redirectorCount = 0;
            foreach ($oldPaths as $oldPath) {
                if ($oldPath === $currentPath || empty($oldPath)) continue;
                if (PanelLocationManager::ensureOldPathRedirector($oldPath, $currentBase)) {
                    $redirectorCount++;
                }
            }
            
            $msg = "✅ بازسازی انجام شد: ";
            $msg .= $wellKnownOk ? "well-known ساخته شد، " : "well-known خطا، ";
            $msg .= "$redirectorCount ریدایرکتور ساخته شد";
            
            Helpers::flash('success', $msg);
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در بازسازی: ' . $e->getMessage());
        }
        Helpers::redirect('settings/panel-location');
    }

    public function wellKnownJson(): void {
        // Public endpoint, no auth needed
        try {
            require_once __DIR__ . '/../core/PanelLocationManager.php';
            PanelLocationManager::emitCanonicalHeaders();
            
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            
            $data = PanelLocationManager::getPanelLocationData();
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function requireAdmin(): void {
        if (!Auth::check()) Helpers::redirect('login');
        if (!Auth::isAdmin()) { http_response_code(403); die('Forbidden - Admin only'); }
    }
}

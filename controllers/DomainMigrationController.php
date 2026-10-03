<?php
// DomainMigrationController.php - v6.9.2 PRO MAX - Domain Independence UI (Fixed 404)
// Handles auto migration check and manual migration - instance methods for Router

require_once __DIR__ . '/../core/DomainMigrationManager.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Setting.php';

class DomainMigrationController {
    
    public function index(): void {
        $this->requireAdmin();
        $migrationResult = null;
        $checks = null;
        try {
            $checks = DomainMigrationManager::checkDomainIndependence();
        } catch (Throwable $e) {
            $checks = null;
        }
        require __DIR__ . '/../views/settings/domain_migration.php';
    }

    public function check(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Helpers::verifyCsrf();
        }
        $migrationResult = null;
        $checks = null;
        try {
            $migrationResult = DomainMigrationManager::autoMigrateIfNeeded();
            $checks = DomainMigrationManager::checkDomainIndependence();
            if (!empty($migrationResult['migrated'])) {
                Helpers::flash('success', '✅ مهاجرت خودکار انجام شد: ' . implode(' | ', $migrationResult['actions']));
            } else {
                Helpers::flash('info', 'ℹ️ ' . ($migrationResult['actions'][0] ?? 'نیازی به مهاجرت نیست - همه چیز درست است'));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: ' . $e->getMessage());
        }
        Helpers::redirect('settings/domain-migration');
    }

    public function migrate(): void {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::redirect('settings/domain-migration');
        }
        Helpers::verifyCsrf();
        $newDomain = strtolower(trim($_POST['new_domain'] ?? ''));
        $newDomain = preg_replace('#^https?://#', '', $newDomain);
        $newDomain = trim($newDomain, '/ ');
        if ($newDomain === '' || !str_contains($newDomain, '.')) {
            Helpers::flash('error', 'دامنه نامعتبر است');
            Helpers::redirect('settings/domain-migration');
        }
        try {
            $result = DomainMigrationManager::fullMigration($newDomain);
            Helpers::flash('success', '🚀 مهاجرت کامل به ' . $newDomain . ' انجام شد: ' . implode(' | ', $result['actions']));
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در مهاجرت: ' . $e->getMessage());
        }
        Helpers::redirect('settings/domain-migration');
    }

    private function requireAdmin(): void {
        if (!Auth::check()) Helpers::redirect('login');
        if (!Auth::isAdmin()) { http_response_code(403); die('Forbidden - Admin only'); }
    }
}

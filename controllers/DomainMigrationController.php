<?php
// DomainMigrationController.php - v6.9.1 PRO MAX - Domain Independence UI
// Handles auto migration check and manual migration

require_once __DIR__ . '/../core/DomainMigrationManager.php';

class DomainMigrationController {
    public static function index() {
        self::requireAdmin();
        $migrationResult = null;
        $checks = null;
        try {
            // Quick check without auto migrate
            $checks = DomainMigrationManager::checkDomainIndependence();
        } catch (Throwable $e) {
            $checks = null;
        }
        require __DIR__ . '/../views/settings/domain_migration.php';
    }

    public static function check() {
        self::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') Helpers::verifyCsrf();
        $migrationResult = null;
        $checks = null;
        try {
            $migrationResult = DomainMigrationManager::autoMigrateIfNeeded();
            $checks = DomainMigrationManager::checkDomainIndependence();
            if ($migrationResult['migrated']) {
                Helpers::flash('success', '✅ مهاجرت خودکار انجام شد: ' . implode(' | ', $migrationResult['actions']));
            } else {
                Helpers::flash('info', 'ℹ️ ' . ($migrationResult['actions'][0] ?? 'نیازی به مهاجرت نیست - همه چیز درست است'));
            }
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا: ' . $e->getMessage());
        }
        Helpers::redirect('settings/domain-migration');
    }

    public static function migrate() {
        self::requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Helpers::redirect('settings/domain-migration');
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

    private static function requireAdmin() {
        if (!Auth::check()) Helpers::redirect('login');
        if (!Auth::isAdmin()) { http_response_code(403); die('Forbidden'); }
    }
}

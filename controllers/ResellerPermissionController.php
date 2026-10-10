<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/ResellerPermissionManager.php';
require_once __DIR__ . '/../core/ResellerSyncManager.php';

class ResellerPermissionController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        ResellerPermissionManager::seedDefaultTemplates();

        $resellerId = (int)($_GET['reseller_id'] ?? 0);
        if ($resellerId <= 0) {
            Helpers::flash('error', 'شناسه نماینده نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? AND role = 'reseller'");
        $stmt->execute([$resellerId]);
        $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reseller) {
            Helpers::flash('error', 'نماینده یافت نشد.');
            Helpers::redirect('resellers');
        }

        $permissions = ResellerPermissionManager::getPermissionsForReseller($resellerId);
        $templates = ResellerPermissionManager::getTemplates();
        $allPerms = ResellerPermissionManager::ALL_PERMISSIONS;

        // Group by group
        $grouped = [];
        foreach ($allPerms as $key => $info) {
            $group = $info['group'];
            if (!isset($grouped[$group])) $grouped[$group] = [];
            $grouped[$group][$key] = $info;
        }

        require __DIR__ . '/../views/resellers/permissions.php';
    }

    public function save(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        if ($resellerId <= 0) {
            Helpers::flash('error', 'شناسه نماینده نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $permissions = [];
        foreach (ResellerPermissionManager::ALL_PERMISSIONS as $key => $info) {
            $visible = (int)($_POST["perm_{$key}_visible"] ?? 0);
            $enabled = (int)($_POST["perm_{$key}_enabled"] ?? 0);
            // If not visible, enabled must be 0
            if (!$visible) $enabled = 0;
            $permissions[$key] = ['visible' => $visible, 'enabled' => $enabled];
        }

        $ok = ResellerPermissionManager::setBulkPermissions($resellerId, $permissions);
        if ($ok) {
            Helpers::flash('success', '✅ سطح دسترسی نماینده با موفقیت ذخیره شد.');
        } else {
            Helpers::flash('error', '❌ خطا در ذخیره دسترسی‌ها.');
        }

        Helpers::redirect("resellers/permissions?reseller_id=$resellerId");
    }

    public function applyTemplate(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('resellers');
        }

        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $templateId = (int)($_POST['template_id'] ?? 0);

        if ($resellerId <= 0 || $templateId <= 0) {
            Helpers::flash('error', 'پارامتر نامعتبر.');
            Helpers::redirect('resellers');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT permissions_json FROM permission_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Helpers::flash('error', 'قالب یافت نشد.');
            Helpers::redirect("resellers/permissions?reseller_id=$resellerId");
        }

        $perms = json_decode($row['permissions_json'], true);
        if (!is_array($perms)) {
            Helpers::flash('error', 'قالب نامعتبر است.');
            Helpers::redirect("resellers/permissions?reseller_id=$resellerId");
        }

        $ok = ResellerPermissionManager::setBulkPermissions($resellerId, $perms);
        if ($ok) {
            Helpers::flash('success', '✅ قالب دسترسی اعمال شد.');
        } else {
            Helpers::flash('error', '❌ خطا در اعمال قالب.');
        }

        Helpers::redirect("resellers/permissions?reseller_id=$resellerId");
    }

    public function syncAll(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('updater');
        }

        $from = $_POST['from_version'] ?? '';
        $to = $_POST['to_version'] ?? \Updater::CURRENT_VERSION;

        $result = ResellerSyncManager::syncAllResellers($from, $to);
        $msg = "✅ همگام‌سازی نماینده‌ها: {$result['synced']} موفق، {$result['failed']} ناموفق (نسخه $to)";
        Helpers::flash($result['failed'] > 0 ? 'info' : 'success', $msg);
        Helpers::redirect('updater');
    }
}

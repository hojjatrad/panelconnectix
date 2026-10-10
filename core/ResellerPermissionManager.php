<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';

class ResellerPermissionManager {
    // All available permission keys
    public const ALL_PERMISSIONS = [
        // Menu permissions
        'menu_dashboard' => ['label' => 'داشبورد', 'group' => 'main', 'icon' => 'fa-gauge'],
        'menu_orders' => ['label' => 'سفارشات', 'group' => 'main', 'icon' => 'fa-cart-shopping'],
        'menu_plans' => ['label' => 'پلن‌ها', 'group' => 'main', 'icon' => 'fa-box'],
        'menu_bot' => ['label' => 'ربات تلگرام', 'group' => 'bot', 'icon' => 'fa-robot'],
        'menu_banking' => ['label' => 'اطلاعات بانکی', 'group' => 'finance', 'icon' => 'fa-credit-card'],
        'menu_branding' => ['label' => 'برندینگ', 'group' => 'system', 'icon' => 'fa-palette'],
        'menu_sub_resellers' => ['label' => 'زیرنماینده‌ها', 'group' => 'resellers', 'icon' => 'fa-users'],
        'menu_ai' => ['label' => 'هوش مصنوعی', 'group' => 'system', 'icon' => 'fa-brain'],
        'menu_monitoring' => ['label' => 'وضعیت سرورها LIVE', 'group' => 'infra', 'icon' => 'fa-heart-pulse'],
        'menu_financial' => ['label' => 'گزارش مالی', 'group' => 'finance', 'icon' => 'fa-chart-line'],
        'menu_usage' => ['label' => 'مصرف کلاینت‌ها', 'group' => 'finance', 'icon' => 'fa-chart-simple'],
        'menu_points' => ['label' => 'امتیازات', 'group' => 'finance', 'icon' => 'fa-star'],
        'menu_clients' => ['label' => 'لیست کلاینت‌ها', 'group' => 'resellers', 'icon' => 'fa-users'],
        // Capability permissions
        'can_create_client' => ['label' => 'ایجاد کلاینت', 'group' => 'capability', 'icon' => 'fa-plus'],
        'can_delete_client' => ['label' => 'حذف کلاینت', 'group' => 'capability', 'icon' => 'fa-trash'],
        'can_edit_price' => ['label' => 'ویرایش قیمت', 'group' => 'capability', 'icon' => 'fa-pen'],
        'can_custom_plans' => ['label' => 'پلن سفارشی', 'group' => 'capability', 'icon' => 'fa-box-open'],
        'can_view_wallet' => ['label' => 'دیدن کیف پول', 'group' => 'capability', 'icon' => 'fa-wallet'],
        'can_withdraw' => ['label' => 'برداشت', 'group' => 'capability', 'icon' => 'fa-money-bill-transfer'],
        'can_manage_sub_resellers' => ['label' => 'مدیریت زیرنماینده', 'group' => 'capability', 'icon' => 'fa-user-tie'],
        'can_use_ai' => ['label' => 'استفاده از AI', 'group' => 'capability', 'icon' => 'fa-robot'],
    ];

    public static function getDefaultPermissions(): array {
        $defaults = [];
        foreach (self::ALL_PERMISSIONS as $key => $info) {
            $defaults[$key] = ['visible' => 1, 'enabled' => 1];
        }
        return $defaults;
    }

    public static function getPermissionsForReseller(int $resellerId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT permission_key, is_visible, is_enabled FROM reseller_permissions WHERE reseller_id = ?");
            $stmt->execute([$resellerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $perms = self::getDefaultPermissions();
            foreach ($rows as $row) {
                $key = $row['permission_key'];
                if (isset($perms[$key])) {
                    $perms[$key] = [
                        'visible' => (int)$row['is_visible'],
                        'enabled' => (int)$row['is_enabled']
                    ];
                }
            }
            return $perms;
        } catch (Throwable $e) {
            return self::getDefaultPermissions();
        }
    }

    public static function canSee(int $resellerId, string $permissionKey): bool {
        if ($resellerId === 1) return true; // Admin sees all
        $perms = self::getPermissionsForReseller($resellerId);
        return isset($perms[$permissionKey]) ? (bool)$perms[$permissionKey]['visible'] : true;
    }

    public static function canUse(int $resellerId, string $permissionKey): bool {
        if ($resellerId === 1) return true; // Admin can use all
        $perms = self::getPermissionsForReseller($resellerId);
        if (!isset($perms[$permissionKey])) return true;
        return (bool)$perms[$permissionKey]['visible'] && (bool)$perms[$permissionKey]['enabled'];
    }

    public static function setPermission(int $resellerId, string $key, int $visible, int $enabled): bool {
        try {
            $pdo = Database::getConnection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("INSERT INTO reseller_permissions (reseller_id, permission_key, is_visible, is_enabled) VALUES (?, ?, ?, ?) ON CONFLICT(reseller_id, permission_key) DO UPDATE SET is_visible = excluded.is_visible, is_enabled = excluded.is_enabled, updated_at = CURRENT_TIMESTAMP");
            } else {
                $stmt = $pdo->prepare("INSERT INTO reseller_permissions (reseller_id, permission_key, is_visible, is_enabled) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible), is_enabled = VALUES(is_enabled), updated_at = CURRENT_TIMESTAMP");
            }
            return $stmt->execute([$resellerId, $key, $visible, $enabled]);
        } catch (Throwable $e) {
            error_log("ResellerPermission set error: " . $e->getMessage());
            return false;
        }
    }

    public static function setBulkPermissions(int $resellerId, array $permissions): bool {
        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();
            foreach ($permissions as $key => $val) {
                $visible = (int)($val['visible'] ?? 1);
                $enabled = (int)($val['enabled'] ?? 1);
                self::setPermission($resellerId, $key, $visible, $enabled);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            try { $pdo->rollBack(); } catch (Throwable $e2) {}
            error_log("Bulk permission error: " . $e->getMessage());
            return false;
        }
    }

    public static function getTemplates(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM permission_templates ORDER BY is_default DESC, id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function createTemplate(string $name, string $desc, array $perms, bool $isDefault = false): bool {
        try {
            $pdo = Database::getConnection();
            if ($isDefault) {
                $pdo->exec("UPDATE permission_templates SET is_default = 0");
            }
            $stmt = $pdo->prepare("INSERT INTO permission_templates (name, description, permissions_json, is_default) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$name, $desc, json_encode($perms, JSON_UNESCAPED_UNICODE), $isDefault ? 1 : 0]);
        } catch (Throwable $e) {
            error_log("Create template error: " . $e->getMessage());
            return false;
        }
    }

    public static function seedDefaultTemplates(): void {
        try {
            $pdo = Database::getConnection();
            $count = (int)$pdo->query("SELECT COUNT(*) FROM permission_templates")->fetchColumn();
            if ($count > 0) return;

            // Template 1: Full access
            $full = self::getDefaultPermissions();
            self::createTemplate('دسترسی کامل', 'همه منوها و قابلیت‌ها فعال', $full, true);

            // Template 2: Simple reseller (no financial, no banking, no branding)
            $simple = self::getDefaultPermissions();
            $simple['menu_financial'] = ['visible' => 0, 'enabled' => 0];
            $simple['menu_banking'] = ['visible' => 0, 'enabled' => 0];
            $simple['menu_branding'] = ['visible' => 0, 'enabled' => 0];
            $simple['menu_sub_resellers'] = ['visible' => 0, 'enabled' => 0];
            $simple['can_edit_price'] = ['visible' => 0, 'enabled' => 0];
            self::createTemplate('نماینده ساده', 'بدون دسترسی مالی و برندینگ', $simple);

            // Template 3: Limited (only orders, plans, clients)
            $limited = [];
            foreach (self::ALL_PERMISSIONS as $k => $v) {
                $limited[$k] = ['visible' => 0, 'enabled' => 0];
            }
            $limited['menu_dashboard'] = ['visible' => 1, 'enabled' => 1];
            $limited['menu_orders'] = ['visible' => 1, 'enabled' => 1];
            $limited['menu_plans'] = ['visible' => 1, 'enabled' => 1];
            $limited['menu_clients'] = ['visible' => 1, 'enabled' => 1];
            $limited['menu_usage'] = ['visible' => 1, 'enabled' => 1];
            $limited['can_create_client'] = ['visible' => 1, 'enabled' => 1];
            $limited['can_view_wallet'] = ['visible' => 1, 'enabled' => 1];
            self::createTemplate('نماینده محدود', 'فقط سفارش، پلن و کلاینت', $limited);

        } catch (Throwable $e) {
            error_log("Seed templates error: " . $e->getMessage());
        }
    }
}

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/CategoryManager.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

class VipPlanController {
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        // Get VIP server
        $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' AND is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$vipServer) {
            $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE api_url LIKE '%connectix.vip%' ORDER BY id DESC LIMIT 1")->fetch();
        }

        $vipPlans = [];
        $vipGroups = [];
        $error = null;

        if ($vipServer) {
            try {
                $driver = DriverFactory::create($vipServer);
                if (method_exists($driver, 'getVipPlans')) {
                    $data = $driver->getVipPlans();
                    $vipPlans = $data['plans'] ?? [];
                    $vipGroups = $data['groups'] ?? [];
                } else {
                    // Fallback via direct API call
                    $token = $vipServer['api_token'] ?? '';
                    $ch = curl_init('https://api.connectix.vip/v1/seller/clients/meta-data');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Accept: application/json',
                        'Authorization: Bearer '.$token,
                        'X-Api-Token: '.$token,
                    ]);
                    $res = curl_exec($ch);
                    curl_close($ch);
                    $json = json_decode($res, true);
                    $vipPlans = $json['seller_plans'] ?? [];
                    $vipGroups = $json['groups'] ?? [];
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        // Get local plans
        $localPlans = $pdo->query("SELECT * FROM plans ORDER BY base_price ASC")->fetchAll();

        // Parse VIP plans for display - categorize by type using smart normalizer
        $categorized = [
            'free' => [],
            'economic' => [],
            'vip' => [], // ویژه
            'iran' => [],
            'business' => [],
            'other' => [],
        ];

        foreach ($vipPlans as $vp) {
            $norm = CategoryManager::normalizePlan($vp, $vipGroups);
            $sg = $norm['server_group'];
            $traffic = $norm['traffic_gb'];
            if ($traffic <= 0.5) {
                $categorized['free'][] = $vp;
            } elseif ($sg === 'economic') {
                $categorized['economic'][] = $vp;
            } elseif ($sg === 'iran_access') {
                $categorized['iran'][] = $vp;
            } elseif ($sg === 'business') {
                $categorized['business'][] = $vp;
            } elseif ($sg === 'default') {
                $categorized['vip'][] = $vp;
            } else {
                $categorized['other'][] = $vp;
            }
        }

        require __DIR__ . '/../views/vip_plans/index.php';
    }

    public function saveMapping(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('vip_plans');
        }

        $pdo = Database::getConnection();
        $mappings = $_POST['mapping'] ?? [];

        foreach ($mappings as $localPlanId => $vipData) {
            $localPlanId = (int)$localPlanId;
            $vipPlanId = trim($vipData['vip_plan_id'] ?? '');
            $vipGroupId = trim($vipData['vip_group_id'] ?? '');
            $vipGroupName = trim($vipData['vip_group_name'] ?? '');
            $vipPlanTitle = trim($vipData['vip_plan_title'] ?? '');

            if ($localPlanId <= 0) continue;

            try {
                $stmt = $pdo->prepare("UPDATE plans SET vip_plan_id = ?, vip_group_id = ?, vip_group_name = ?, vip_plan_title = ? WHERE id = ?");
                $stmt->execute([
                    $vipPlanId ?: null,
                    $vipGroupId ?: null,
                    $vipGroupName ?: null,
                    $vipPlanTitle ?: null,
                    $localPlanId
                ]);
            } catch (Throwable $e) {}
        }

        Helpers::flash('success', '✅ نگاشت پلن‌های VIP با موفقیت ذخیره شد - از این به بعد یوزرها دقیقاً در پلن انتخابی ساخته می‌شوند');
        Helpers::redirect('vip_plans');
    }

    public function autoMap(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' AND is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$vipServer) {
            Helpers::flash('error', 'سرور VIP یافت نشد');
            Helpers::redirect('vip_plans');
        }

        try {
            $driver = DriverFactory::create($vipServer);
            $data = $driver->getVipPlans();
            $vipPlans = $data['plans'] ?? [];
            $vipGroups = $data['groups'] ?? [];

            // Build lookup: title -> id
            $planLookup = [];
            foreach ($vipPlans as $vp) {
                $planLookup[$vp['title']] = $vp['id'];
            }

            $groupLookup = [];
            foreach ($vipGroups as $vg) {
                $groupLookup[$vg['name']] = $vg['id'];
                $groupLookup[$vg['name_translations']['fa'] ?? ''] = $vg['id'];
            }

            $localPlans = $pdo->query("SELECT * FROM plans")->fetchAll();
            $mapped = 0;

            foreach ($localPlans as $lp) {
                $traffic = (float)$lp['traffic_gb'];
                $groupSlug = $lp['server_group'] ?? 'default';
                $title = $lp['title'] ?? '';

                // Determine target group
                $targetGroupId = null;
                $targetGroupName = null;
                if (stripos($groupSlug, 'economic') !== false || stripos($title, 'اقتصادی') !== false) {
                    $targetGroupId = $groupLookup['Economic'] ?? $groupLookup['اقتصادی'] ?? null;
                    $targetGroupName = 'Economic';
                } elseif (stripos($groupSlug, 'iran') !== false || stripos($title, 'ایران') !== false) {
                    $targetGroupId = $groupLookup['Iran Access'] ?? null;
                    $targetGroupName = 'Iran Access';
                } else {
                    $targetGroupId = $groupLookup['default'] ?? null;
                    $targetGroupName = 'default';
                }

                // Find best matching VIP plan by traffic using normalized data
                $bestPlan = null;
                $bestDiff = PHP_FLOAT_MAX;
                foreach ($vipPlans as $vp) {
                    $norm = CategoryManager::normalizePlan($vp, $vipGroups);
                    // Match group type
                    if ($targetGroupName === 'Economic' && $norm['server_group'] !== 'economic') continue;
                    if ($targetGroupName === 'Iran Access' && $norm['server_group'] !== 'iran_access') continue;

                    $diff = abs($norm['traffic_gb'] - $traffic);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestPlan = $vp;
                    }
                }

                if ($bestPlan) {
                    $pdo->prepare("UPDATE plans SET vip_plan_id = ?, vip_group_id = ?, vip_group_name = ?, vip_plan_title = ? WHERE id = ?")
                        ->execute([$bestPlan['id'], $targetGroupId, $targetGroupName, $bestPlan['title'], $lp['id']]);
                    $mapped++;
                }
            }

            Helpers::flash('success', "✅ $mapped پلن به صورت خودکار به پلن‌های VIP نگاشت شد (اقتصادی/ویژه/ایران‌اکسس)");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در نگاشت خودکار: '.$e->getMessage());
        }

        Helpers::redirect('vip_plans');
    }

    public function importAll(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);

        $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' AND is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$vipServer) {
            $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE api_url LIKE '%connectix.vip%' ORDER BY id DESC LIMIT 1")->fetch();
        }
        if (!$vipServer) {
            Helpers::flash('error', 'سرور VIP یافت نشد');
            Helpers::redirect('vip_plans');
        }

        try {
            $driver = DriverFactory::create($vipServer);
            $data = $driver->getVipPlans();
            $vipPlans = $data['plans'] ?? [];
            $vipGroups = $data['groups'] ?? [];

            // Group lookup
            $groupLookup = [];
            $groupNameLookup = [];
            foreach ($vipGroups as $vg) {
                $groupLookup[$vg['id']] = $vg;
                $groupNameLookup[$vg['name']] = $vg['id'];
                if (!empty($vg['name_translations']['fa'])) {
                    $groupNameLookup[$vg['name_translations']['fa']] = $vg['id'];
                }
            }

            // Allowed groups for this seller
            $allowedGroups = [];
            try {
                $ch = curl_init('https://api.connectix.vip/v1/seller/seller-data');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Authorization: Bearer '.($vipServer['api_token'] ?? ''),
                    'X-Api-Token: '.($vipServer['api_token'] ?? ''),
                ]);
                $res = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($res, true);
                $allowedGroups = $json['seller']['role_details']['allowed_seller_groups'] ?? [];
            } catch (Throwable $e) {}

            $imported = 0;
            $skipped = 0;
            $categoriesCreated = 0;

            foreach ($vipPlans as $vp) {
                $vpId = $vp['id'];
                $vpTitle = $vp['title'] ?? $vp['name'] ?? '';

                // Smart duplicate check: vip_plan_id OR traffic+duration+group+server composite
                $exists = $pdo->prepare("SELECT id FROM plans WHERE vip_plan_id = ? LIMIT 1");
                $exists->execute([$vpId]);
                if ($exists->fetch()) {
                    $skipped++;
                    continue;
                }

                // Normalize using real API fields
                $norm = CategoryManager::normalizePlan($vp, $vipGroups);
                $trafficGb = $norm['traffic_gb'];
                $durationDays = $norm['duration_days'];
                $serverGroup = $norm['server_group'];
                $targetGroupName = $norm['group_name'];
                $targetGroupId = $norm['group_id'];

                // If group_id not from normalizer, try to find from lookup
                if (empty($targetGroupId)) {
                    if ($serverGroup === 'economic') {
                        foreach ($vipGroups as $vg) {
                            if ($vg['name'] === 'Economic') { $targetGroupId = $vg['id']; break; }
                        }
                    } elseif ($serverGroup === 'iran_access') {
                        foreach ($vipGroups as $vg) {
                            if ($vg['name'] === 'Iran Access') { $targetGroupId = $vg['id']; break; }
                        }
                    } elseif ($serverGroup === 'business') {
                        foreach ($vipGroups as $vg) {
                            if (stripos($vg['name'], 'Business') !== false) { $targetGroupId = $vg['id']; break; }
                        }
                    } else {
                        foreach ($vipGroups as $vg) {
                            if ($vg['name'] === 'default') { $targetGroupId = $vg['id']; break; }
                        }
                    }
                }

                // Check if group is allowed for this seller
                if (!empty($allowedGroups) && $targetGroupId && !in_array($targetGroupId, $allowedGroups)) {
                    $targetGroupId = $allowedGroups[0] ?? $targetGroupId;
                }

                // Composite duplicate check: same traffic + duration + group + server
                $dupCheck = $pdo->prepare("SELECT id FROM plans WHERE traffic_gb = ? AND duration_days = ? AND server_group = ? AND server_id = ? LIMIT 1");
                $dupCheck->execute([$trafficGb, $durationDays, $serverGroup, $vipServer['id']]);
                if ($dupCheck->fetch()) {
                    $skipped++;
                    continue;
                }

                // NEW v4.1: Hierarchical category handling — Type (Economic/ویژه) -> Duration (۱ ماهه)
                // For 100% accuracy per user request: اقتصادی/ویژه first, then month
                // Also handle Free plans as separate type
                $effectiveGroup = $serverGroup;
                if ($trafficGb <= 0.5) {
                    $effectiveGroup = 'free';
                    $serverGroup = 'free';
                }
                $categoryRow = CategoryManager::findOrCreateVipCategory($pdo, $effectiveGroup, $durationDays, 'plans');
                $categoryId = $categoryRow['id'] ?? null;
                $categoryName = $categoryRow['name'] ?? CategoryManager::canonicalFromDuration($durationDays);
                // Parent category info for logging
                $parentTypeLabel = CategoryManager::getVipTypeLabel($effectiveGroup);

                // Generate local title in Persian - keep original volume+price visible
                $persianTitle = $vpTitle;
                $persianTitle = str_replace(['Economic', 'Iran Access', 'Business Class', 'BCSublink', 'Sublink', 'Free', 'Unlimited'], ['اقتصادی', 'ایران‌اکسس', 'بیزنس', 'بیزنس ساب‌لینک', 'ساب‌لینک', 'رایگان', 'نامحدود'], $persianTitle);
                $localTitle = $persianTitle . ' - VIP';

                // Base price - use real price if available from API, else auto-calc but DON'T overwrite if manually edited later
                $basePrice = $norm['price'] ?? 0;
                if (empty($basePrice) || $basePrice < 1000) {
                    $basePrice = 100000;
                    if ($trafficGb <= 0.3) $basePrice = 20000;
                    elseif ($trafficGb <= 0.5) $basePrice = 30000;
                    elseif ($trafficGb <= 1) $basePrice = 50000;
                    elseif ($trafficGb <= 5) $basePrice = 80000;
                    elseif ($trafficGb <= 10) $basePrice = 120000;
                    elseif ($trafficGb <= 20) $basePrice = 180000;
                    elseif ($trafficGb <= 50) $basePrice = 300000;
                    elseif ($trafficGb <= 100) $basePrice = 500000;
                    elseif ($trafficGb >= 1000) $basePrice = 800000;

                    if ($effectiveGroup === 'economic') $basePrice = (int)($basePrice * 0.7);
                    if ($effectiveGroup === 'iran_access') $basePrice = (int)($basePrice * 0.9);
                    if ($effectiveGroup === 'free') $basePrice = (int)($basePrice * 0.3);
                }

                $resellerPrice = (int)($basePrice * 0.7);

                try {
                    $stmt = $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, category_id, vip_plan_id, vip_group_id, vip_group_name, vip_plan_title, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
                    $stmt->execute([
                        $localTitle,
                        $trafficGb,
                        $durationDays,
                        $basePrice,
                        $resellerPrice,
                        $serverGroup,
                        $vipServer['id'],
                        $categoryName,
                        $categoryId,
                        $vpId,
                        $targetGroupId,
                        $targetGroupName,
                        $vpTitle
                    ]);
                    $imported++;
                } catch (Throwable $e) {
                    error_log("VIP import error for $vpTitle: ".$e->getMessage());
                }
            }

            // Log sync
            try {
                $pdo->prepare("INSERT INTO server_sync_logs (server_id, action, details, plans_imported, plans_skipped, categories_created) VALUES (?, 'import_vip_plans', ?, ?, ?, 0)")
                    ->execute([$vipServer['id'], "Imported from VIP API: ".count($vipPlans)." plans", $imported, $skipped]);
            } catch (Throwable $e) {}

            Helpers::flash('success', "✅ $imported پلن VIP با موفقیت ایمپورت شد ( $skipped قبلاً وجود داشت) - دسته‌بندی‌های موجود حفظ شدند، تکراری ساخته نشد. پلن‌ها بر اساس اقتصادی/ویژه/ایران‌اکسس دسته‌بندی شدند و به سرور VIP متصل هستند.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ایمپورت: '.$e->getMessage());
        }

        Helpers::redirect('vip_plans');
    }

    /**
     * New: Merge duplicate categories button handler
     */
    public function mergeCategories(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('vip_plans');
        }
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        $result = CategoryManager::mergeDuplicateCategories($pdo);
        Helpers::flash('success', "✅ {$result['merged']} دسته‌بندی تکراری ادغام شد: " . implode(' | ', array_slice($result['details'], 0, 5)));
        Helpers::redirect('categories');
    }
}

<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
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

        // Parse VIP plans for display - categorize by type
        $categorized = [
            'free' => [],
            'economic' => [],
            'vip' => [], // ویژه
            'iran' => [],
            'business' => [],
            'other' => [],
        ];

        foreach ($vipPlans as $vp) {
            $title = $vp['title'] ?? '';
            $lower = strtolower($title);
            if (stripos($title, 'Free') !== false || stripos($title, '0.1GB') !== false || stripos($title, '0.25GB') !== false) {
                $categorized['free'][] = $vp;
            } elseif (stripos($title, 'Economic') !== false || stripos($title, 'اقتصادی') !== false) {
                $categorized['economic'][] = $vp;
            } elseif (stripos($title, 'Iran Access') !== false || stripos($title, 'ایران') !== false) {
                $categorized['iran'][] = $vp;
            } elseif (stripos($title, 'Business') !== false || stripos($title, 'بیزنس') !== false) {
                $categorized['business'][] = $vp;
            } elseif (stripos($title, '3D') !== false || stripos($title, 'Unlimited') !== false || preg_match('/\d+GB/i', $title)) {
                // Check if has Economic or Iran in title
                if (stripos($title, 'Economic') === false && stripos($title, 'Iran') === false && stripos($title, 'Business') === false) {
                    $categorized['vip'][] = $vp; // ویژه - بدون پسوند اقتصادی/ایران
                } else {
                    $categorized['other'][] = $vp;
                }
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

                // Find best matching VIP plan by traffic
                $bestPlan = null;
                $bestDiff = PHP_FLOAT_MAX;
                foreach ($vipPlans as $vp) {
                    $vpTitle = $vp['title'] ?? '';
                    // Filter by group type in title
                    $isEconomic = stripos($vpTitle, 'Economic') !== false;
                    $isIran = stripos($vpTitle, 'Iran Access') !== false;
                    
                    // Match group type
                    if ($targetGroupName === 'Economic' && !$isEconomic) continue;
                    if ($targetGroupName === 'Iran Access' && !$isIran) continue;
                    if ($targetGroupName === 'default' && ($isEconomic || $isIran)) {
                        // For ویژه, prefer plans without Economic/Iran
                        // But allow if no better match
                    }

                    if (preg_match('/(\d+)\s*GB/i', $vpTitle, $m)) {
                        $gb = (int)$m[1];
                        $diff = abs($gb - $traffic);
                        if ($diff < $bestDiff) {
                            $bestDiff = $diff;
                            $bestPlan = $vp;
                        }
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
}

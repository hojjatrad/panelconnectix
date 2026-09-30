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
            $sellerDataRes = $pdo->query("SELECT * FROM server_nodes WHERE id = {$vipServer['id']}")->fetch();
            // Fetch seller-data to get allowed groups
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

            foreach ($vipPlans as $vp) {
                $vpId = $vp['id'];
                $vpTitle = $vp['title'];

                // Check if already exists
                $exists = $pdo->prepare("SELECT id FROM plans WHERE vip_plan_id = ? LIMIT 1");
                $exists->execute([$vpId]);
                if ($exists->fetch()) {
                    $skipped++;
                    continue;
                }

                // Parse traffic GB
                $trafficGb = 10; // default
                if (preg_match('/(\d+)\s*GB/i', $vpTitle, $m)) {
                    $trafficGb = (int)$m[1];
                } elseif (stripos($vpTitle, 'Unlimited') !== false) {
                    $trafficGb = 1000; // represent unlimited as 1000GB
                } elseif (preg_match('/(\d+)\s*MB/i', $vpTitle, $m)) {
                    $trafficGb = round((int)$m[1] / 1024, 3);
                }

                // Parse duration
                $durationDays = 30; // default 1M
                if (preg_match('/(\d+)\s*M/i', $vpTitle, $m)) {
                    // 1M = 30 days, 3M = 90 days
                    $months = (int)$m[1];
                    $durationDays = $months * 30;
                } elseif (preg_match('/(\d+)\s*D/i', $vpTitle, $m)) {
                    $durationDays = (int)$m[1];
                    // Handle +10D etc: if title has +10D, add it
                    if (preg_match('/\+\s*(\d+)\s*D/i', $vpTitle, $m2)) {
                        $durationDays += (int)$m2[1];
                    }
                }
                // Special handling for +10D
                if (preg_match('/\+\s*(\d+)\s*D/i', $vpTitle, $m)) {
                    // If we already have months, add days
                    if ($durationDays >= 30) {
                        // Keep months + add days if title like 100GB-3M + 10D
                        // Already handled above, but ensure
                    } else {
                        $durationDays += (int)$m[1];
                    }
                }

                // Determine group based on title
                $targetGroupId = null;
                $targetGroupName = 'default';
                $serverGroup = 'default';

                if (stripos($vpTitle, 'Economic') !== false) {
                    $targetGroupId = $groupNameLookup['Economic'] ?? $groupLookup['affb6513-cd8d-4dad-b04e-02007f8c2a51']['id'] ?? null;
                    // Find Economic group id
                    foreach ($vipGroups as $vg) {
                        if ($vg['name'] === 'Economic') { $targetGroupId = $vg['id']; break; }
                    }
                    $targetGroupName = 'Economic';
                    $serverGroup = 'economic';
                } elseif (stripos($vpTitle, 'Iran Access') !== false) {
                    foreach ($vipGroups as $vg) {
                        if ($vg['name'] === 'Iran Access') { $targetGroupId = $vg['id']; break; }
                    }
                    $targetGroupName = 'Iran Access';
                    $serverGroup = 'iran_access';
                } elseif (stripos($vpTitle, 'Business') !== false) {
                    foreach ($vipGroups as $vg) {
                        if (stripos($vg['name'], 'Business') !== false) { $targetGroupId = $vg['id']; break; }
                    }
                    $targetGroupName = 'Business Class';
                    $serverGroup = 'business';
                } else {
                    // ویژه - default
                    foreach ($vipGroups as $vg) {
                        if ($vg['name'] === 'default') { $targetGroupId = $vg['id']; break; }
                    }
                    $targetGroupName = 'default';
                    $serverGroup = 'default';
                }

                // Check if group is allowed for this seller
                if (!empty($allowedGroups) && $targetGroupId && !in_array($targetGroupId, $allowedGroups)) {
                    // Skip if not allowed, or use first allowed group as fallback
                    // For now, skip to avoid creation error
                    // But we can still import with default group if allowed
                    if (!in_array($targetGroupId, $allowedGroups)) {
                        // Try to find first allowed group that matches type, or use default allowed
                        $targetGroupId = $allowedGroups[0] ?? $targetGroupId;
                    }
                }

                // Generate local title in Persian
                $persianTitle = $vpTitle;
                // Translate to Persian for local display
                $persianTitle = str_replace(['Economic', 'Iran Access', 'Business Class', 'BCSublink', 'Sublink', 'Free', 'Unlimited'], ['اقتصادی', 'ایران‌اکسس', 'بیزنس', 'بیزنس ساب‌لینک', 'ساب‌لینک', 'رایگان', 'نامحدود'], $persianTitle);
                $localTitle = $persianTitle . ' - VIP';

                // Determine category based on duration
                $category = '۱ ماهه';
                if ($durationDays >= 365) $category = '۱۲ ماهه';
                elseif ($durationDays >= 180) $category = '۶ ماهه';
                elseif ($durationDays >= 90) $category = '۳ ماهه';
                elseif ($durationDays >= 60) $category = '۲ ماهه';
                elseif ($durationDays <= 7) $category = 'هفتگی';

                // Base price - auto calculate based on traffic
                $basePrice = 100000; // default
                if ($trafficGb <= 1) $basePrice = 50000;
                elseif ($trafficGb <= 5) $basePrice = 80000;
                elseif ($trafficGb <= 10) $basePrice = 120000;
                elseif ($trafficGb <= 20) $basePrice = 180000;
                elseif ($trafficGb <= 50) $basePrice = 300000;
                elseif ($trafficGb <= 100) $basePrice = 500000;
                elseif ($trafficGb >= 1000) $basePrice = 800000;

                // Adjust for group
                if ($serverGroup === 'economic') $basePrice = (int)($basePrice * 0.7); // اقتصادی ارزان‌تر
                if ($serverGroup === 'iran_access') $basePrice = (int)($basePrice * 0.9);

                $resellerPrice = (int)($basePrice * 0.7); // 30% discount for reseller

                try {
                    $stmt = $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, vip_plan_id, vip_group_id, vip_group_name, vip_plan_title, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
                    $stmt->execute([
                        $localTitle,
                        $trafficGb,
                        $durationDays,
                        $basePrice,
                        $resellerPrice,
                        $serverGroup,
                        $vipServer['id'],
                        $category,
                        $vpId,
                        $targetGroupId,
                        $targetGroupName,
                        $vpTitle
                    ]);
                    $imported++;
                } catch (Throwable $e) {
                    // Log error but continue
                    error_log("VIP import error for $vpTitle: ".$e->getMessage());
                }
            }

            Helpers::flash('success', "✅ $imported پلن VIP با موفقیت ایمپورت شد ( $skipped قبلاً وجود داشت) - دیگر نیازی به ساخت دستی نیست! پلن‌ها بر اساس اقتصادی/ویژه/ایران‌اکسس دسته‌بندی شدند و به سرور VIP متصل هستند.");
        } catch (Throwable $e) {
            Helpers::flash('error', 'خطا در ایمپورت: '.$e->getMessage());
        }

        Helpers::redirect('vip_plans');
    }
}

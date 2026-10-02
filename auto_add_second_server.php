<?php
// Auto-add second server Speedur - Pasarguard
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Setting.php';
require __DIR__ . '/drivers/DriverFactory.php';
require __DIR__ . '/core/CategoryManager.php';
require __DIR__ . '/core/Provisioner.php';

$key = $_GET['key'] ?? '';
if ($key !== 'auto_server_406' && $key !== 'vip_sync_405' && $key !== 'CONNECTIX2026') {
    die("Unauthorized - use ?key=auto_server_406");
}

$pdo = Database::getConnection();
Database::ensureExtendedTablesExist($pdo);

echo "=== Auto-add second server Speedur ===\n";

// Check if server already exists
$exists = $pdo->prepare("SELECT id FROM server_nodes WHERE api_url LIKE '%speedur.org%' LIMIT 1");
$exists->execute();
$existing = $exists->fetch();
if ($existing) {
    echo "Server Speedur already exists with ID ".$existing['id']."\n";
    $serverId = $existing['id'];
} else {
    // Auto-detect
    $serverData = [
        'name' => 'VIP-2 Speedur TR',
        'driver' => 'pasargad',
        'api_url' => 'https://www.speedur.org:2096/',
        'api_username' => '',
        'api_password' => '',
        'api_token' => 'pg_key_9cb71a1c-a9d9-48f2-ad8a-12c7ec6a63b6',
        'sub_domain' => 'https://sub.speedur.org:2096',
    ];
    
    $autoDetected = Provisioner::autoDetectServerCategory($serverData, []);
    echo "Auto-detected: ".json_encode($autoDetected, JSON_UNESCAPED_UNICODE)."\n";
    
    $stmt = $pdo->prepare("INSERT INTO server_nodes (name, driver, api_url, api_username, api_password, api_token, server_group, sub_domain, max_clients, is_vip, auto_import_plans, price_multiplier, region, seller_code, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 500, 1, 1, 0.9, ?, ?, 1)");
    $stmt->execute([
        $serverData['name'],
        $serverData['driver'],
        $serverData['api_url'],
        $serverData['api_username'],
        $serverData['api_password'],
        $serverData['api_token'],
        $autoDetected['server_group'] ?? 'default',
        $serverData['sub_domain'],
        $autoDetected['region'] ?? 'TR',
        $autoDetected['seller_code'] ?? 'speedur'
    ]);
    $serverId = (int)$pdo->lastInsertId();
    echo "✅ Server created with ID $serverId\n";
}

// Test connection
$server = $pdo->query("SELECT * FROM server_nodes WHERE id = $serverId")->fetch();
$driver = DriverFactory::create($server);
$auth = $driver->authenticate();
echo "Auth: ".($auth?'OK':'FAIL')." Err: ".$driver->getLastError()."\n";

if ($auth) {
    $stats = $driver->getNodeStats();
    echo "Stats: ".json_encode($stats, JSON_UNESCAPED_UNICODE)."\n";
    
    // Import plans from templates
    if ($server['driver'] === 'pasargad') {
        // For Pasargad, get user_templates as plans
        $res = $driver->request('/api/user_templates');
        if (!empty($res['data']) && is_array($res['data'])) {
            $templates = $res['data'];
            echo "Found ".count($templates)." templates\n";
            $imported = 0;
            foreach ($templates as $tpl) {
                $trafficGb = round(($tpl['data_limit'] ?? 0) / 1073741824, 2);
                $durationDays = round(($tpl['expire_duration'] ?? 2592000) / 86400);
                $title = $tpl['name'] ?? "Template {$tpl['id']}";
                
                // Check duplicate
                $dup = $pdo->prepare("SELECT id FROM plans WHERE vip_plan_id = ? AND server_id = ? LIMIT 1");
                $dup->execute(["pg_tpl_".$tpl['id'], $serverId]);
                if ($dup->fetch()) continue;
                
                $dup2 = $pdo->prepare("SELECT id FROM plans WHERE traffic_gb = ? AND duration_days = ? AND server_id = ? LIMIT 1");
                $dup2->execute([$trafficGb, $durationDays, $serverId]);
                if ($dup2->fetch()) continue;
                
                $catRow = CategoryManager::findOrCreateVipCategory($pdo, 'default', $durationDays, 'plans');
                $catId = $catRow['id'] ?? null;
                $catName = $catRow['name'] ?? CategoryManager::canonicalFromDuration($durationDays);
                
                $basePrice = 100000;
                if ($trafficGb <= 10) $basePrice = 90000;
                elseif ($trafficGb <= 20) $basePrice = 130000;
                elseif ($trafficGb <= 30) $basePrice = 180000;
                elseif ($trafficGb <= 50) $basePrice = 250000;
                elseif ($trafficGb <= 100) $basePrice = 400000;
                else $basePrice = 600000;
                
                // Apply multiplier 0.9 for Speedur
                $basePrice = (int)($basePrice * 0.9);
                $resellerPrice = (int)($basePrice * 0.7);
                
                $localTitle = $title . " - Speedur VIP";
                
                $pdo->prepare("INSERT INTO plans (title, traffic_gb, duration_days, base_price, reseller_price, server_group, server_id, category, category_id, vip_plan_id, vip_plan_title, is_active, show_in_bot) VALUES (?, ?, ?, ?, ?, 'default', ?, ?, ?, ?, ?, 1, 1)")
                    ->execute([$localTitle, $trafficGb, $durationDays, $basePrice, $resellerPrice, $serverId, $catName, $catId, "pg_tpl_".$tpl['id'], $title]);
                $imported++;
                echo "  Imported $localTitle - {$trafficGb}GB {$durationDays} days - {$basePrice} Toman\n";
            }
            echo "✅ Imported $imported plans from Speedur templates\n";
        }
    }
    
    // Sync users
    require_once __DIR__ . '/core/NodeSync.php';
    $syncResult = NodeSync::syncServer($pdo, $server);
    echo "Sync result: ".json_encode($syncResult, JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n=== DONE ===\n";
echo "Server 1: Connectix - 94 users - api.connectix.vip\n";
echo "Server 2: Speedur - 40 users - www.speedur.org:2096\n";
echo "Both auto-detect by category/type and provision automatically\n";

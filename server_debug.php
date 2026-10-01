<?php
/**
 * SERVER DEBUG - Why node-users shows 0 but stats shows count
 * Updated for Connectix Seller driver
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/drivers/DriverFactory.php';

header('Content-Type: text/html; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $pdo = Database::getConnection();
    $servers = $pdo->query("SELECT id, name, driver, api_url FROM server_nodes ORDER BY id ASC")->fetchAll();
    echo "<h3>Select server to debug:</h3><ul>";
    foreach ($servers as $s) {
        echo "<li><a href='?id={$s['id']}'>{$s['id']} - {$s['name']} ({$s['driver']}) - {$s['api_url']}</a></li>";
    }
    echo "</ul>";
    exit;
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
$stmt->execute([$id]);
$server = $stmt->fetch();
if (!$server) die("Server not found");

echo "<h2>Debug Server: {$server['name']} (ID $id)</h2>";
echo "<p>Driver: {$server['driver']} | URL: {$server['api_url']} | Token len: ".strlen($server['api_token'] ?? '')."</p>";

$driver = DriverFactory::create($server);
echo "<p>Actual driver class: ".get_class($driver)."</p>";

echo "<h3>1. Authenticate</h3>";
$auth = $driver->authenticate();
echo $auth ? "✅ Auth OK" : "❌ Auth FAILED - " . htmlspecialchars($driver->getLastError() ?? 'unknown');
echo "<br>";

if (!$auth) {
    echo "<p>Cannot proceed without auth</p>";
    echo "<p><a href='server_driver_fix.php'>Fix driver</a></p>";
    exit;
}

echo "<h3>2. getNodeStats (shows count in server list)</h3>";
$stats = $driver->getNodeStats();
echo "<pre>" . json_encode($stats, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>3. listUsers (used in node-users page)</h3>";
if ($server['driver'] === 'mock') {
    echo "<div style='background:#7f1d1d; color:#fca5a5; padding:10px; border-radius:8px;'>❌ درایورش mock هست! Mock همیشه listUsers خالی برمی‌گردونه ولی getNodeStats عدد رندوم نشون میده.</div><br>";
}

if (get_class($driver) === 'ConnectixSellerDriver') {
    echo "<div style='background:#065f46; color:#d1fae5; padding:10px; border-radius:8px;'>✅ Connectix Seller Driver فعال - API درست api.connectix.vip</div><br>";
}

echo "<h3>4. Final listUsers() result</h3>";
$users = $driver->listUsers();
echo "Count: " . count($users) . "<br>";
if (count($users) > 0) {
    echo "<pre>" . json_encode(array_slice($users, 0, 3), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
    echo "<p style='color:green'>✅ باید تو صفحه node-users هم همین تعداد را ببینی</p>";
} else {
    echo "Empty - this is why node-users page shows 0<br>";
    if (get_class($driver) !== 'ConnectixSellerDriver') {
        echo "<p>این سرور URL اش connectix.vip هست ولی درایورش ".htmlspecialchars($server['driver'])." هست. باید به connectix_seller تغییر بدی.</p>";
        echo "<a href='server_driver_fix.php?fix_id=$id&driver=connectix_seller'>Fix to Connectix Seller</a>";
    }
}

echo "<h3>5. Check panel clients for this server</h3>";
$countPanel = $pdo->query("SELECT COUNT(*) FROM clients WHERE server_id = $id")->fetchColumn();
echo "Panel clients count for server $id: $countPanel<br>";

echo "<p><a href='servers/$id/node-users'>Go to node-users page</a> | <a href='server_driver_fix.php'>Fix drivers</a> | <a href='seller_api_debug_v2.php?id=$id'>Seller API Debug V2</a></p>";

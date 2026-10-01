<?php
/**
 * SERVER DRIVER FIX - Fix mock driver showing fake count but 0 clients
 * Now supports Connectix Seller API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = Database::getConnection();
$servers = $pdo->query("SELECT id, name, driver, api_url FROM server_nodes ORDER BY id ASC")->fetchAll();

if (isset($_GET['fix_id'])) {
    $id = (int)$_GET['fix_id'];
    $newDriver = $_GET['driver'] ?? 'pasargad';
    $pdo->prepare("UPDATE server_nodes SET driver = ? WHERE id = ?")->execute([$newDriver, $id]);
    // Also fix api_url if it's old seller-api
    if (str_contains(strtolower($_GET['driver']), 'connectix')) {
        $pdo->prepare("UPDATE server_nodes SET api_url = 'https://api.connectix.vip' WHERE id = ?")->execute([$id]);
    }
    echo "<p style='color:green'>✅ Fixed server $id to driver $newDriver</p>";
}

echo "<h2>Server Drivers</h2>";
echo "<table border=1 cellpadding=8 style='border-collapse:collapse; font-size:12px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Driver</th><th>URL</th><th>Fix</th></tr>";
foreach ($servers as $s) {
    $isMock = $s['driver'] === 'mock';
    $isConnectixUrl = str_contains(strtolower($s['api_url']), 'connectix.vip');
    $style = $isMock ? " style='background:#7f1d1d; color:#fca5a5;'" : ($isConnectixUrl && $s['driver'] !== 'connectix_seller' ? " style='background:#f59e0b; color:#000;'" : "");
    echo "<tr$style>";
    echo "<td>{$s['id']}</td>";
    echo "<td>" . htmlspecialchars($s['name']) . "</td>";
    echo "<td>" . htmlspecialchars($s['driver']) . ($isMock ? " ❌ FAKE" : "") . "</td>";
    echo "<td>" . htmlspecialchars($s['api_url']) . "</td>";
    echo "<td>";
    if ($isMock || $isConnectixUrl) {
        echo "<a href='?fix_id={$s['id']}&driver=connectix_seller' style='background:#e11d48; color:white; padding:4px 8px; border-radius:6px; text-decoration:none;'>Fix to Connectix Seller ✅</a> ";
        echo "<a href='?fix_id={$s['id']}&driver=pasargad' style='background:#7c3aed; color:white; padding:4px 8px; border-radius:6px; text-decoration:none;'>Pasargad</a> ";
        echo "<a href='?fix_id={$s['id']}&driver=marzban' style='background:#059669; color:white; padding:4px 8px; border-radius:6px; text-decoration:none;'>Marzban</a>";
    } else {
        echo "OK";
    }
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>توضیح:</h3>";
echo "<p style='font-size:12px; line-height:1.8;'>
درایور mock فقط برای تست است - عدد رندوم 40-180 نشون میده ولی listUsers خالی [] برمی‌گردونه.<br>
برای سرور VIP با URL <code>seller-api.connectix.vip/externel/v1</code> این URL قدیمی و مرده است (404). API درست <code>https://api.connectix.vip/v1/seller/clients</code> هست که با توکن فعلی شما 85 کلاینت برمی‌گردونه.<br>
<b>راه حل:</b> دکمه <span style='background:#e11d48;color:white;padding:2px 6px;border-radius:4px'>Fix to Connectix Seller</span> را بزنید تا درایور به connectix_seller تغییر کنه و URL به api.connectix.vip اصلاح بشه.<br>
بعد به <a href='server_debug.php?id=2'>server_debug.php?id=2</a> برید و باید 85 کاربر ببینید.
</p>";

echo "<p><a href='server_debug.php?id=2'>← Server Debug</a> | <a href='servers'>← Servers List</a></p>";

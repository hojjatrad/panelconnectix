<?php
/**
 * AUTO FIX NAMES V2 - Deep fix for customer_name not showing
 * https://vpbotn.ir/contax/auto_fix_names_v2.php?key=CONNECTIX2026
 * 
 * Does:
 * 1. Force update view file from GitHub if needed
 * 2. Force run ensureCustomerNamesFixed without daily limit
 * 3. Show debug info
 */

header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

$pdo = Database::getConnection();

echo "<h2>Deep Fix - Customer Names V2</h2>";

// Force fix
echo "<h3>1. Force fixing empty customer_name</h3>";
Setting::set('last_customer_name_autofix', '0');
Updater::ensureCustomerNamesFixed($pdo);
echo "✅ Force fix executed<br>";

$total = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$with = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != '' AND TRIM(customer_name) != ''")->fetchColumn();
$without = $total - $with;
echo "After: Total $total | With name: $with | Without: $without<br>";

if ($without > 0) {
    echo "<p style='color:red'>Still $without without name - running direct fallback</p>";
    $pdo->exec("UPDATE clients SET customer_name = username WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = ''");
    $with2 = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != ''")->fetchColumn();
    echo "After direct SQL: With name: $with2<br>";
}

// Check view file
echo "<h3>2. View file check</h3>";
$viewPath = __DIR__ . '/views/clients/index.php';
$viewContent = file_get_contents($viewPath);
$checks = [
    'initials()' => strpos($viewContent, 'function initials') !== false,
    'togglePwd' => strpos($viewContent, 'togglePwd') !== false,
    'customer_name bold' => strpos($viewContent, 'customer_name') !== false && strpos($viewContent, 'text-[14px] font-bold') !== false,
    'VIP style' => strpos($viewContent, 'مشتری / احراز هویت') !== false,
];
foreach ($checks as $k=>$v) {
    echo "$k: ".($v?'✅ YES':'❌ NO')."<br>";
}
if (!$checks['initials()'] || !$checks['VIP style']) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:10px;border-radius:8px;'>❌ View file OLD - needs update from GitHub. Run auto_update.</div>";
    echo "<p>Downloading new view from GitHub...</p>";
    $githubView = @file_get_contents('https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/views/clients/index.php?cb='.time());
    if ($githubView && strlen($githubView) > 1000 && strpos($githubView, 'initials') !== false) {
        $bak = $viewPath . '.bak.' . date('Ymd_His');
        copy($viewPath, $bak);
        file_put_contents($viewPath, $githubView);
        echo "✅ View updated from GitHub, backup: $bak<br>";
    } else {
        echo "❌ Could not download view from GitHub, length: ".strlen($githubView ?? '')."<br>";
    }
} else {
    echo "<p style='color:green'>✅ View file is new professional version</p>";
}

// Show sample
echo "<h3>3. Sample clients (should now show name)</h3>";
$rows = $pdo->query("SELECT id, username, customer_name, password FROM clients ORDER BY id DESC LIMIT 5")->fetchAll();
echo "<table border=1 cellpadding=5 style='border-collapse:collapse;font-size:12px;'><tr><th>ID</th><th>username</th><th>customer_name</th><th>password</th></tr>";
foreach ($rows as $r) {
    echo "<tr><td>{$r['id']}</td><td>".htmlspecialchars($r['username'])."</td><td style='font-weight:bold;color:green'>".htmlspecialchars($r['customer_name'])."</td><td>".htmlspecialchars($r['password'])."</td></tr>";
}
echo "</table>";

echo "<hr><p><a href='clients'>← مشاهده کلاینت‌ها (باید الان نام داشته باشند)</a></p>";
echo "<p><a href='debug_customer_names.php?key=CONNECTIX2026'>Debug detailed</a></p>";

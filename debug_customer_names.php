<?php
/**
 * DEBUG CUSTOMER NAMES - Deep investigation
 * https://vpbotn.ir/contax/debug_customer_names.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

$pdo = Database::getConnection();

echo "<h2>Deep Debug - Customer Names</h2>";

// 1. Table schema
echo "<h3>1. Table Schema clients</h3>";
try {
    $cols = $pdo->query("PRAGMA table_info(clients)")->fetchAll();
    if (!$cols) {
        // MySQL
        $cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll();
        echo "<pre>".json_encode($cols, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
    } else {
        echo "<pre>".json_encode($cols, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
    }
} catch (Throwable $e) {
    echo "Schema error: ".$e->getMessage()."<br>";
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll();
        echo "<pre>".json_encode($cols, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
    } catch (Throwable $e2) {
        echo "MySQL schema error: ".$e2->getMessage();
    }
}

// 2. Raw data sample
echo "<h3>2. Sample clients raw data (first 10)</h3>";
$rows = $pdo->query("SELECT id, username, password, customer_name, plan_id, server_id, status, expire_at FROM clients ORDER BY id DESC LIMIT 10")->fetchAll();
echo "<table border=1 cellpadding=5 style='border-collapse:collapse;font-size:12px;'><tr><th>ID</th><th>username</th><th>password</th><th>customer_name (RAW)</th><th>customer_name hex</th><th>len</th><th>is null?</th><th>is empty?</th></tr>";
foreach ($rows as $r) {
    $cn = $r['customer_name'];
    $hex = $cn !== null ? bin2hex($cn) : 'NULL';
    $len = $cn !== null ? strlen($cn) : 'NULL';
    $isNull = $cn === null ? 'YES' : 'NO';
    $isEmpty = trim((string)$cn) === '' ? 'YES' : 'NO';
    echo "<tr><td>{$r['id']}</td><td>".htmlspecialchars($r['username'])."</td><td>".htmlspecialchars($r['password'])."</td><td>".htmlspecialchars($cn ?? 'NULL')."</td><td>$hex</td><td>$len</td><td>$isNull</td><td>$isEmpty</td></tr>";
}
echo "</table>";

// 3. Counts with different conditions
echo "<h3>3. Counts</h3>";
$total = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$c1 = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NULL")->fetchColumn();
$c2 = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name = ''")->fetchColumn();
$c3 = $pdo->query("SELECT COUNT(*) FROM clients WHERE TRIM(customer_name) = ''")->fetchColumn();
$c4 = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != '' AND TRIM(customer_name) != ''")->fetchColumn();
echo "Total: $total | IS NULL: $c1 | = '': $c2 | TRIM='': $c3 | Has name: $c4<br>";

// 4. Check Setting last_customer_name_autofix
echo "<h3>4. Settings</h3>";
$lastFix = Setting::get('last_customer_name_autofix', 'not set');
echo "last_customer_name_autofix: $lastFix (".($lastFix!=='not set'?date('Y-m-d H:i:s', (int)$lastFix):'').")<br>";
echo "Current time: ".time()." (".date('Y-m-d H:i:s').")<br>";
echo "Diff: ".(time() - (int)$lastFix)." seconds<br>";

// 5. Check view file version
echo "<h3>5. View file check</h3>";
$viewPath = __DIR__ . '/views/clients/index.php';
$content = file_get_contents($viewPath);
$hasInitials = strpos($content, 'function initials') !== false ? 'YES' : 'NO';
$hasIsConnectix = strpos($content, 'isConnectix') !== false ? 'YES' : 'NO';
$hasTogglePwd = strpos($content, 'togglePwd') !== false ? 'YES' : 'NO';
$hasCustomerNameBold = strpos($content, 'customer_name') !== false ? 'YES' : 'NO';
echo "has initials(): $hasInitials | has isConnectix: $hasIsConnectix | has togglePwd: $hasTogglePwd | has customer_name: $hasCustomerNameBold<br>";
echo "View file size: ".strlen($content)." bytes<br>";
echo "First 500 chars: <pre>".htmlspecialchars(substr($content,0,500))."</pre>";

// 6. Test Updater auto-fix manually
echo "<h3>6. Manual run ensureCustomerNamesFixed (force)</h3>";
if (isset($_GET['force_fix'])) {
    require_once __DIR__ . '/core/Updater.php';
    // Force by resetting setting
    Setting::set('last_customer_name_autofix', '0');
    Updater::ensureCustomerNamesFixed($pdo);
    echo "Forced fix executed<br>";
    $c4_after = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != '' AND TRIM(customer_name) != ''")->fetchColumn();
    echo "After fix - Has name: $c4_after<br>";
    echo "<a href='?key=CONNECTIX2026'>Reload without force</a><br>";
} else {
    echo "<a href='?key=CONNECTIX2026&force_fix=1' style='background:#e11d48;color:white;padding:8px 16px;border-radius:8px;text-decoration:none;'>Force Run Auto-Fix Now</a><br>";
}

// 7. Check if any client has name with spaces that might be trimmed incorrectly
echo "<h3>7. Check for whitespace issues</h3>";
$rows = $pdo->query("SELECT id, username, customer_name FROM clients WHERE customer_name IS NOT NULL LIMIT 20")->fetchAll();
foreach ($rows as $r) {
    if (trim($r['customer_name']) === '') {
        echo "ID {$r['id']} username {$r['username']} has whitespace-only customer_name: '".htmlspecialchars($r['customer_name'])."' hex=".bin2hex($r['customer_name'])."<br>";
    }
}

echo "<hr><p><a href='clients'>Clients</a> | <a href='fix_customer_names.php?key=CONNECTIX2026'>Fix names</a></p>";

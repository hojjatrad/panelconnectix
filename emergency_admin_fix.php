<?php
// Emergency Admin Fix - No GitHub needed, only DB fix
// Copy this file content via cPanel File Manager if quick_update fails
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Emergency Admin Fix - No GitHub</h2><pre>";

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    
    echo "DB: ".$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."\n";
    
    // Show users before
    $users = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "Before:\n";
    foreach ($users as $u) echo "ID={$u['id']} user={$u['username']} role={$u['role']} status={$u['status']}\n";
    
    // Fix roles
    $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE id=1");
    $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE LOWER(username)='admin'");
    $pdo->exec("UPDATE users SET role='reseller', status='active', two_factor_enabled=0 WHERE LOWER(username)='novinvpn'");
    $pdo->exec("UPDATE users SET status='active' WHERE role='admin'");
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
    
    // Fix proxy columns
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('is_proxy_only', $cols)) {
                $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only TINYINT(1) NOT NULL DEFAULT 0");
                echo "Added is_proxy_only\n";
            }
            if (!in_array('proxy_type', $cols)) {
                $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type VARCHAR(20) NOT NULL DEFAULT 'all'");
                echo "Added proxy_type\n";
            }
        } else {
            try { $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only INTEGER DEFAULT 0"); echo "Added is_proxy_only SQLite\n"; } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type TEXT DEFAULT 'all'"); echo "Added proxy_type SQLite\n"; } catch (Exception $e) {}
        }
    } catch (Exception $e) { echo "Proxy col error: ".$e->getMessage()."\n"; }
    
    // Show after
    $usersAfter = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nAfter:\n";
    foreach ($usersAfter as $u) echo "ID={$u['id']} user={$u['username']} role={$u['role']} status={$u['status']}\n";
    
    echo "\n✅ Fixed! Now logout and login with admin / admin123\n";
    echo "Then go to: /dashboard - should show admin\n";
    echo "Proxy menu: /proxies\n";
    
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n".$e->getTraceAsString();
}
echo "</pre>";
?>

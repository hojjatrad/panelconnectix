<?php
// fix_role.php - Force correct roles for admin and reseller
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    echo "<h2>🔧 فیکس نقش کاربران</h2><div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px'>";
    
    // Show before
    $users = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id")->fetchAll();
    echo "<h3>قبل از فیکس:</h3><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزر</th><th>نقش</th><th>وضعیت</th></tr>";
    foreach ($users as $u) { echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['role']}</td><td>{$u['status']}</td></tr>"; }
    echo "</table>";
    
    // Fix roles
    if ($driver === 'mysql') {
        $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0 WHERE id=1 OR LOWER(username)='admin'");
        $pdo->exec("UPDATE users SET role='reseller', status='active', two_factor_enabled=0, wallet_balance=500000, discount_percent=15 WHERE id=2 OR LOWER(username)='novinvpn'");
    } else {
        $pdo->exec("UPDATE users SET role='admin', status='active' WHERE id=1 OR LOWER(username)='admin'");
        $pdo->exec("UPDATE users SET role='reseller', status='active', wallet_balance=500000 WHERE id=2 OR LOWER(username)='novinvpn'");
    }
    
    // Show after
    $users2 = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id")->fetchAll();
    echo "<h3>بعد از فیکس:</h3><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزر</th><th>نقش</th><th>وضعیت</th></tr>";
    foreach ($users2 as $u) { 
        $color = $u['role']==='admin'?'#a78bfa':'#22d3ee';
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td style='color:$color;font-weight:bold'>{$u['role']}</td><td>{$u['status']}</td></tr>"; 
    }
    echo "</table>";
    
    echo "<div style='background:#065f46;padding:12px;border-radius:8px;margin-top:12px'>✅ نقش‌ها فیکس شد:<br>admin → admin<br>novinvpn → reseller</div>";
    echo "<br><a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a> ";
    echo "<a href='ultimate_fix.php' style='background:#334155;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>ultimate_fix</a>";
    echo "</div>";
} catch (Throwable $e) {
    echo "❌ ".$e->getMessage();
}

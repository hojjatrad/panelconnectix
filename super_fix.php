<?php
// super_fix.php - v7.2.5 SUPER FIX - Fixes ALL admin/reseller login issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessDir = __DIR__ . '/data/sessions';
$cacheDir = __DIR__ . '/cache/ratelimit';
foreach ([$sessDir, __DIR__.'/data/tmp', $cacheDir, __DIR__.'/data', __DIR__.'/cache'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0777, true);
    @chmod($d, 0777);
}
@ini_set('session.save_path', $sessDir);
@session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<html><head><meta charset='utf-8'><title>SUPER FIX v7.2.5</title><style>body{background:#020617;color:#e2e8f0;font-family:sans-serif;padding:20px;direction:rtl} .ok{color:#22c55e} .err{color:#ef4444} .warn{color:#f59e0b} .box{background:#1e293b;border:1px solid #334155;padding:14px;border-radius:10px;margin:10px 0} table{border-collapse:collapse;width:100%;background:#1e293b} td,th{border:1px solid #334155;padding:6px;font-size:12px} a{color:#a78bfa} input,select{padding:8px;border-radius:6px;border:1px solid #334155;background:#0f172a;color:#fff;margin:2px} button{padding:10px 18px;border-radius:8px;border:0;background:#7c3aed;color:#fff;cursor:pointer;font-weight:bold;margin:2px} </style></head><body>";
echo "<h1>🚀 SUPER FIX v7.2.5 - فیکس کامل ادمین و نماینده</h1>";

try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<div class='box ok'>✅ DB: $driver</div>";
} catch (Throwable $e) {
    echo "<div class='box err'>❌ DB: ".$e->getMessage()."</div>";
    exit;
}

// Clear locks
try { $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'"); } catch (Throwable $e) {}
foreach (glob($cacheDir.'/*.json') as $f) @unlink($f);
foreach (glob($sessDir.'/*') as $f) { if(is_file($f) && basename($f)!='.htaccess') @unlink($f); }
echo "<div class='box ok'>✅ قفل‌ها و سشن‌ها پاک شد</div>";

// Show current users
$users = $pdo->query("SELECT id, username, role, status, two_factor_enabled, wallet_balance FROM users ORDER BY id")->fetchAll();
echo "<h2>👥 کاربران فعلی</h2><table><tr><th>ID</th><th>یوزر</th><th>نقش</th><th>وضعیت</th><th>2FA</th><th>موجودی</th></tr>";
foreach ($users as $u) {
    $rc = $u['role']==='admin'?'#a78bfa':'#22d3ee';
    echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td style='color:$rc'>{$u['role']}</td><td>{$u['status']}</td><td>{$u['two_factor_enabled']}</td><td>".number_format($u['wallet_balance'])."</td></tr>";
}
echo "</table>";

// Handle fix actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'fix_roles') {
        // Fix roles: admin->admin, novinvpn->reseller, others keep but ensure active
        if ($driver==='mysql') {
            $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE LOWER(username)='admin'");
            $pdo->exec("UPDATE users SET role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000, discount_percent=15 WHERE LOWER(username)='novinvpn'");
            $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE role IN ('admin','reseller')");
        } else {
            $pdo->exec("UPDATE users SET role='admin', status='active' WHERE LOWER(username)='admin'");
            $pdo->exec("UPDATE users SET role='reseller', status='active' WHERE LOWER(username)='novinvpn'");
        }
        echo "<div class='box ok'>✅ نقش‌ها فیکس شد: admin→admin, novinvpn→reseller</div>";
    }
    
    if ($action === 'reset_defaults') {
        $ah = password_hash('admin123', PASSWORD_BCRYPT);
        $rh = password_hash('123456', PASSWORD_BCRYPT);
        if ($driver==='mysql') {
            $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES ('admin', ?, 'admin', 'مدیر ارشد', 'admin@local', 0, 'admin_token', 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin', status='active', two_factor_enabled=0")->execute([$ah]);
            $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES ('novinvpn', ?, 'reseller', 'نوین', 'novin@local', 500000, 15, 'reseller_token', 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='reseller', status='active', two_factor_enabled=0, wallet_balance=500000")->execute([$rh]);
        } else {
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES (1, 'admin', ?, 'admin', 'مدیر', 'admin@local', 0, 'admin_token', 'active')")->execute([$ah]);
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین', 'novin@local', 500000, 15, 'reseller_token', 'active')")->execute([$rh]);
        }
        echo "<div class='box ok'>✅ admin/admin123 و novinvpn/123456 ریست شدند با هش تازه</div>";
    }
    
    if ($action === 'reset_custom' && !empty($_GET['uid'])) {
        $uid = (int)$_GET['uid'];
        $newPass = $_GET['newpass'] ?? 'admin123';
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=?, status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE id=?")->execute([$newHash, $uid]);
        echo "<div class='box ok'>✅ یوزر ID $uid پسوردش به <b>$newPass</b> ریست شد</div>";
    }
    
    if ($action === 'make_admin' && !empty($_GET['uid'])) {
        $uid = (int)$_GET['uid'];
        $pdo->prepare("UPDATE users SET role='admin', status='active', two_factor_enabled=0 WHERE id=?")->execute([$uid]);
        echo "<div class='box ok'>✅ یوزر ID $uid نقشش admin شد</div>";
    }
    
    if ($action === 'make_reseller' && !empty($_GET['uid'])) {
        $uid = (int)$_GET['uid'];
        $pdo->prepare("UPDATE users SET role='reseller', status='active', two_factor_enabled=0 WHERE id=?")->execute([$uid]);
        echo "<div class='box ok'>✅ یوزر ID $uid نقشش reseller شد</div>";
    }
    
    // Refresh users list
    $users = $pdo->query("SELECT id, username, role, status, two_factor_enabled FROM users ORDER BY id")->fetchAll();
}

// Show users with actions
echo "<h2>🔧 عملیات فیکس</h2><div class='box'>";
echo "<a href='?action=fix_roles'><button>🔧 فیکس نقش‌ها (admin→admin, novinvpn→reseller)</button></a> ";
echo "<a href='?action=reset_defaults'><button style='background:#059669'>🔑 ریست admin/admin123 + novinvpn/123456</button></a><br><br>";

echo "<h3>ریست پسورد هر یوزر:</h3><table><tr><th>ID</th><th>یوزر</th><th>نقش</th><th>عملیات</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['role']}</td><td>
        <a href='?action=reset_custom&uid={$u['id']}&newpass=admin123'><button style='background:#f59e0b;color:#000;padding:4px 8px;font-size:11px'>ریست به admin123</button></a>
        <a href='?action=reset_custom&uid={$u['id']}&newpass=123456'><button style='background:#06b6d4;padding:4px 8px;font-size:11px'>ریست به 123456</button></a>
        <a href='?action=make_admin&uid={$u['id']}'><button style='background:#7c3aed;padding:4px 8px;font-size:11px'>نقش admin</button></a>
        <a href='?action=make_reseller&uid={$u['id']}'><button style='background:#334155;padding:4px 8px;font-size:11px'>نقش reseller</button></a>
        <form style='display:inline' method='GET'><input type='hidden' name='action' value='reset_custom'><input type='hidden' name='uid' value='{$u['id']}'><input type='text' name='newpass' placeholder='پسورد جدید' style='width:90px'><button type='submit' style='padding:4px 8px;font-size:11px'>ریست دلخواه</button></form>
    </td></tr>";
}
echo "</table>";
echo "</div>";

// Test login
echo "<h2>🧪 تست لاگین</h2><div class='box'>";
echo "<form method='POST'><input type='hidden' name='test_login' value='1'><input type='text' name='tu' placeholder='یوزرنیم' value='novinvpn'><input type='text' name='tp' placeholder='پسورد' value='123456'><button type='submit'>تست لاگین</button></form>";

if (!empty($_POST['test_login'])) {
    require_once __DIR__ . '/core/Auth.php';
    require_once __DIR__ . '/core/RateLimiter.php';
    RateLimiter::clear(RateLimiter::getClientKey('login_'));
    $_SESSION = [];
    $tu = trim($_POST['tu'] ?? '');
    $tp = $_POST['tp'] ?? '';
    $res = Auth::login($tu, $tp);
    if ($res['success']) {
        echo "<div class='ok'>✅ لاگین موفق: {$res['user']['username']} - نقش: {$res['user']['role']} - ID: {$res['user']['id']}<br>الان باید به ".($res['user']['role']==='admin'?'صفحه مدیر':'صفحه نماینده')." برود</div>";
        echo "<div style='margin-top:8px'><a href='dashboard' style='background:#22c55e;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>رفتن به داشبورد</a></div>";
        Auth::logout();
        @session_start();
    } else {
        echo "<div class='err'>❌ لاگین ناموفق: {$res['reason']}</div>";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
        $stmt->execute([$tu]);
        $u = $stmt->fetch();
        if ($u) {
            echo "<div>یوزر یافت شد: ID {$u['id']} role {$u['role']} status {$u['status']} 2FA {$u['two_factor_enabled']}<br>verify('{$tp}') = ".(password_verify($tp, $u['password_hash'])?'true':'false')."</div>";
        } else {
            echo "<div class='err'>یوزر در DB نیست!</div>";
        }
    }
}
echo "</div>";

// Magic links
echo "<h2>🔑 لینک ورود مستقیم (بدون پسورد)</h2><div class='box'>";
try {
    $m1 = bin2hex(random_bytes(16));
    $m2 = bin2hex(random_bytes(16));
    $exp = date('Y-m-d H:i:s', time()+3600);
    $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE LOWER(username)='admin'")->execute([$m1, $exp]);
    $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE LOWER(username)='novinvpn'")->execute([$m2, $exp]);
    // Also for all admins
    $allAdmins = $pdo->query("SELECT id, username FROM users WHERE role='admin' ORDER BY id")->fetchAll();
    foreach ($allAdmins as $adm) {
        $mt = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE id=?")->execute([$mt, $exp, $adm['id']]);
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
        $base = $base==='/'||$base==='.'?'':rtrim($base,'/');
        $url = $proto.$host.$base."/login?magic_token=".$mt;
        echo "<div>👤 {$adm['username']} (ID {$adm['id']}): <a href='$url' style='word-break:break-all'>$url</a> <a href='$url'><button style='padding:4px 8px;font-size:11px'>ورود</button></a></div>";
    }
} catch (Throwable $e) { echo "<div class='err'>❌ ".$e->getMessage()."</div>"; }
echo "</div>";

echo "<div class='box' style='font-size:12px'>📌 <b>دستورالعمل نهایی:</b><br>1. روی 'ریست admin/admin123 + novinvpn/123456' کلیک کن<br>2. اگر یوزر سفارشی داری (مثلاً myadmin)، کنارش 'ریست به admin123' بزن<br>3. 'فیکس نقش‌ها' بزن<br>4. با فرم تست لاگین، یوزر/پسورد را تست کن<br>5. سپس به <a href='login'>/login</a> برو و وارد شو<br>6. اگر با یوزر سفارشی نصب وارد می‌شوی و می‌گه اشتباه است، کنارش ریست بزن</div>";

echo "<br><a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>لاگین</a> ";
echo "<a href='fix_role.php' style='background:#334155;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>fix_role</a> ";
echo "<a href='ultimate_fix.php' style='background:#334155;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>ultimate_fix</a>";

echo "<div style='font-size:11px;color:#64748b;margin-top:20px'>بعد از فیکس پاک کن! | v7.2.5 | ".date('Y-m-d H:i:s')."</div>";
echo "</body></html>";

} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px'>❌ ".$e->getMessage()."<pre>".$e->getTraceAsString()."</pre></div>";
}

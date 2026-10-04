<?php
// fix_all_logins.php - v7.2 ULTRA FIX - Reset all logins including reseller
// Upload to public_html/ and open https://yourdomain.com/fix_all_logins.php
// After use, DELETE this file!

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bulletproof session fix
$sessDir = __DIR__ . '/data/sessions';
$tmpDir = __DIR__ . '/data/tmp';
$cacheDir = __DIR__ . '/cache/ratelimit';
foreach ([$sessDir, $tmpDir, $cacheDir, __DIR__.'/data', __DIR__.'/cache'] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
@ini_set('session.save_path', $sessDir);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 فیکس کامل لاگین - Connectix v7.2 ULTRA RESYNC</h2>";
    echo "<div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px;max-width:800px'>";

    // 1. Clear all locks
    try {
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
        echo "<div style='background:#1e293b;padding:8px;border-radius:6px;font-size:12px;margin-bottom:8px'>🧹 قفل‌های لاگین پاک شد (system_settings)</div>";
    } catch (Throwable $e) {
        echo "<div style='background:#7f1d1d;padding:8px;border-radius:6px;font-size:12px'>❌ پاکسازی قفل: ".$e->getMessage()."</div>";
    }
    $deleted = 0;
    foreach (glob($cacheDir.'/*') as $f) { if(is_file($f)) { @unlink($f); $deleted++; } }
    echo "<div style='background:#1e293b;padding:8px;border-radius:6px;font-size:12px;margin-bottom:8px'>🧹 ریت‌لیمیت کش پاک شد ($deleted فایل)</div>";
    $deleted2 = 0;
    foreach (glob($sessDir.'/*') as $f) { if(is_file($f)) { @unlink($f); $deleted2++; } }
    echo "<div style='background:#1e293b;padding:8px;border-radius:6px;font-size:12px;margin-bottom:8px'>🧹 سشن‌های قدیمی پاک شد ($deleted2 فایل)</div>";

    // 2. Show current users
    $users = $pdo->query("SELECT id, username, role, email, status, two_factor_enabled, wallet_balance, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>👥 کاربران فعلی (".count($users)."):</h3><table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#1e293b;margin-bottom:12px'><tr><th>ID</th><th>یوزرنیم</th><th>نقش</th><th>وضعیت</th><th>2FA</th><th>موجودی</th></tr>";
    foreach ($users as $u) {
        $two = !empty($u['two_factor_enabled']) ? 'فعال' : 'غیرفعال';
        $statusColor = $u['status']==='active' ? '#10b981' : '#ef4444';
        echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['role']}</td><td style='color:$statusColor'>{$u['status']}</td><td>{$two}</td><td>".number_format($u['wallet_balance'])."</td></tr>";
    }
    echo "</table>";

    // 3. Fix all users
    if (isset($_GET['fix'])) {
        $adminPass = $_GET['admin_pass'] ?? 'admin123';
        $resellerPass = $_GET['reseller_pass'] ?? '123456';
        
        $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
        $resellerHash = password_hash($resellerPass, PASSWORD_BCRYPT);

        // Fix admin
        if ($driver === 'mysql') {
            $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES (1, 'admin', ?, 'admin', 'مدیر ارشد سامانه', 'admin@connectix.local', 0, 'admin_secret_token_123', 'active') ON DUPLICATE KEY UPDATE username='admin', password_hash=VALUES(password_hash), role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL")->execute([$adminHash]);
        } else {
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES (1, 'admin', ?, 'admin', 'مدیر ارشد سامانه', 'admin@connectix.local', 0, 'admin_secret_token_123', 'active')")->execute([$adminHash]);
            $pdo->prepare("UPDATE users SET password_hash=?, role='admin', status='active', two_factor_enabled=0 WHERE id=1 OR username='admin'")->execute([$adminHash]);
        }

        // Fix reseller novinvpn
        if ($driver === 'mysql') {
            $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان (نماینده نمونه)', 'novin@example.com', 500000, 15, 'reseller_novin_token_456', 'active') ON DUPLICATE KEY UPDATE username='novinvpn', password_hash=VALUES(password_hash), role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000, discount_percent=15")->execute([$resellerHash]);
            // Also ensure any other resellers are active and no 2FA
            $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE role='reseller'");
        } else {
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان (نماینده نمونه)', 'novin@example.com', 500000, 15, 'reseller_novin_token_456', 'active')")->execute([$resellerHash]);
            $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0 WHERE role='reseller'");
        }

        // Force all admins active
        $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE role='admin'");

        // Verify
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username='admin' LIMIT 1");
        $stmt->execute();
        $ah = $stmt->fetchColumn();
        $adminOk = $ah && password_verify($adminPass, $ah) ? '✅' : '❌';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username='novinvpn' LIMIT 1");
        $stmt->execute();
        $rh = $stmt->fetchColumn();
        $resellerOk = $rh && password_verify($resellerPass, $rh) ? '✅' : '❌';

        echo "<div style='background:#065f46;padding:16px;border-radius:8px;color:#10b981;margin-bottom:12px'>
            ✅ فیکس انجام شد!<br>
            $adminOk ادمین: <b>admin / $adminPass</b><br>
            $resellerOk ریسلر: <b>novinvpn / $resellerPass</b><br>
            🔓 همه قفل‌ها باز شد، 2FA غیرفعال، وضعیت فعال<br>
        </div>";
        echo "<div style='background:#1e293b;padding:12px;border-radius:8px;margin-bottom:12px'>
            <b>تست لاگین:</b><br>
            <a href='login' style='background:#7c3aed;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;display:inline-block;margin:4px'>🔑 ورود ادمین (admin / $adminPass)</a>
            <a href='login' style='background:#06b6d4;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;display:inline-block;margin:4px'>👤 ورود ریسلر (novinvpn / $resellerPass)</a>
        </div>";
    } else {
        echo "<p style='background:#1e293b;padding:12px;border-radius:8px'>برای فیکس کامل لاگین ادمین و ریسلر کلیک کن:</p>";
        echo "<a href='?fix=1&admin_pass=admin123&reseller_pass=123456' style='background:#7c3aed;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:bold;margin-bottom:12px'>🔧 فیکس کامل: admin/admin123 + novinvpn/123456</a><br>";
        echo "<form method='GET' style='background:#1e293b;padding:16px;border-radius:8px;margin-top:12px'><h4>فیکس با پسورد دلخواه:</h4>
            <div style='display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center'>
                <label>ادمین پسورد:</label><input type='text' name='admin_pass' value='admin123' style='padding:8px;border-radius:6px;border:1px solid #334155;background:#0f172a;color:#fff'>
                <label>ریسلر پسورد:</label><input type='text' name='reseller_pass' value='123456' style='padding:8px;border-radius:6px;border:1px solid #334155;background:#0f172a;color:#fff'>
                <input type='hidden' name='fix' value='1'>
                <button type='submit' style='background:#059669;color:#fff;padding:8px 16px;border-radius:6px;border:0'>فیکس</button>
            </div></form><br>";
        echo "<div style='background:#0f172a;padding:12px;border-radius:8px;border:1px solid #334155;font-size:12px'>
            <b>📌 مشکل ریسلر لاگین؟</b><br>
            1. روی دکمه بالا کلیک کن<br>
            2. سپس با <code>novinvpn / 123456</code> وارد شو<br>
            3. اگر باز هم نشد، <code>/reset_admin.php?reset=1&newpass=admin123</code> را هم امتحان کن<br>
            4. مطمئن شو <code>data/sessions</code> قابل نوشتن است (chmod 755)<br>
        </div><br>";
    }

    echo "<p style='font-size:11px;color:#94a3b8;margin-top:16px'>⚠️ بعد از استفاده، فایل fix_all_logins.php را پاک کن! | v7.2 ULTRA RESYNC</p>";
    echo "</div>";

} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px;font-family:sans-serif;direction:rtl'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "<br><pre style='font-size:11px;overflow:auto'>".htmlspecialchars($e->getTraceAsString())."</pre></div>";
}

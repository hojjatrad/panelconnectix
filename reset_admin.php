<?php
// Reset Admin Password - v6.8.7 FINAL - Path Independent
// Upload to public_html/ and open https://yourdomain.com/reset_admin.php
// After use, DELETE this file!

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bulletproof session fix
$sessDir = __DIR__ . '/data/sessions';
$tmpDir = __DIR__ . '/data/tmp';
if (!is_dir($sessDir)) @mkdir($sessDir, 0755, true);
if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
@ini_set('session.save_path', $sessDir);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 ریست پسورد ادمین - Connectix v6.8.7 FINAL</h2>";
    echo "<div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px;max-width:700px'>";

    $users = $pdo->query("SELECT id, username, role, email, status, two_factor_enabled, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>👥 کاربران (".count($users)."):</h3><table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزرنیم</th><th>نقش</th><th>وضعیت</th><th>2FA</th></tr>";
    foreach ($users as $u) {
        $two = !empty($u['two_factor_enabled']) ? 'فعال' : 'غیرفعال';
        echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['role']}</td><td>{$u['status']}</td><td>{$two}</td></tr>";
    }
    echo "</table><br>";

    // Clear rate limits and login locks
    try {
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
        echo "<div style='background:#1e293b;padding:8px;border-radius:6px;font-size:12px'>🧹 قفل‌های لاگین پاک شد</div><br>";
    } catch (Throwable $e) {}
    foreach (glob(__DIR__.'/cache/ratelimit/*') as $f) { @unlink($f); }
    foreach (glob(__DIR__.'/data/sessions/*') as $f) { if(is_file($f)) @unlink($f); }

    if (isset($_GET['reset'])) {
        $newPass = $_GET['newpass'] ?? 'admin123';
        $newUser = $_GET['user'] ?? '';
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        
        // Reset all admins + id=1 + username admin
        $stmt = $pdo->prepare("UPDATE users SET password_hash=?, status='active', role='admin', two_factor_enabled=0, two_factor_secret=NULL WHERE role='admin' OR id=1 OR username='admin'");
        $stmt->execute([$hash]);
        $cnt = $stmt->rowCount();
        
        if (!empty($newUser)) {
            $s = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $s->execute([$newUser]);
            $found = $s->fetch();
            if ($found) {
                $pdo->prepare("UPDATE users SET password_hash=?, status='active', role='admin', two_factor_enabled=0 WHERE id=?")->execute([$hash, $found['id']]);
            } else {
                $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, status, wallet_balance, api_token) VALUES (?, ?, 'admin', 'مدیر ارشد', 'admin@local', 'active', 0, ?)")->execute([$newUser, $hash, 'admin_'.bin2hex(random_bytes(8))]);
            }
            echo "<div style='background:#065f46;padding:12px;border-radius:8px;color:#10b981'>✅ یوزر <b>$newUser</b> با پسورد <b>$newPass</b> ساخته/آپدیت شد</div><br>";
        }
        
        echo "<div style='background:#065f46;padding:12px;border-radius:8px;color:#10b981'>✅ پسورد تمام ادمین‌ها به <b>$newPass</b> تغییر کرد + فعال شد + 2FA غیرفعال شد (تعداد: $cnt)</div><br>";
        echo "<div style='background:#1e293b;padding:12px;border-radius:8px'><b>تست لاگین:</b><br>یوزرنیم: <code>admin</code> یا <code>".htmlspecialchars($newUser ?: 'admin')."</code><br>پسورد: <code>$newPass</code></div><br>";
        echo "<a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a><br><br>";
    } else {
        echo "<p>برای ریست پسورد ادمین به <b>admin123</b> کلیک کن:</p>";
        echo "<a href='?reset=1&newpass=admin123' style='background:#7c3aed;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>🔑 ریست به admin123</a> ";
        echo "<a href='?reset=1&newpass=123456' style='background:#f59e0b;color:#000;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>ریست به 123456</a><br><br>";
        echo "<form method='GET' style='background:#1e293b;padding:16px;border-radius:8px'><h4>ریست با یوزر/پسورد دلخواه:</h4><div style='display:flex;gap:8px;flex-wrap:wrap;margin-top:8px'><input type='text' name='user' placeholder='یوزرنیم (مثلاً admin)' value='admin' style='padding:8px;border-radius:6px;border:1px solid #334155;background:#0f172a;color:#fff'><input type='text' name='newpass' placeholder='پسورد جدید' value='admin123' style='padding:8px;border-radius:6px;border:1px solid #334155;background:#0f172a;color:#fff'><input type='hidden' name='reset' value='1'><button type='submit' style='background:#059669;color:#fff;padding:8px 16px;border-radius:6px;border:0'>ریست</button></div></form><br>";
    }

    if (file_exists(__DIR__ . '/install.lock')) {
        echo "<div style='background:#1e293b;padding:10px;border-radius:8px;font-size:12px'>ℹ️ install.lock وجود دارد. برای نصب مجدد، اول این فایل رو پاک کن و تیک پاکسازی دیتابیس بزن.</div><br>";
    }

    echo "<p style='font-size:12px;color:#94a3b8'>⚠️ بعد از استفاده، فایل reset_admin.php را پاک کن!</p>";
    echo "</div>";

} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px;font-family:sans-serif;direction:rtl'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
}

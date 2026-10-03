<?php
// Reset Admin Password - v6.8.6 - Path Independent - Final Fix
// Upload to public_html/ and open https://yourdomain.com/reset_admin.php
// Upload to public_html/ and open https://yourdomain.com/reset_admin.php
// After use, DELETE this file!

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix session path for cPanel
$sessDir = __DIR__ . '/data/sessions';
if (!is_dir($sessDir)) @mkdir($sessDir, 0755, true);
@ini_set('session.save_path', $sessDir);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 ریست پسورد ادمین - Connectix v6.8.5</h2>";
    echo "<div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px;max-width:600px'>";

    // Show all users
    $users = $pdo->query("SELECT id, username, role, email, status, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>👥 کاربران موجود:</h3><table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزرنیم</th><th>نقش</th><th>وضعیت</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['role']}</td><td>{$u['status']}</td></tr>";
    }
    echo "</table><br>";

    // If ?reset=1, reset admin password to admin123 - v6.8.6 FIX: also set active
    if (isset($_GET['reset'])) {
        $newPass = $_GET['newpass'] ?? 'admin123';
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        // Update all admins and first user, ensure active status
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active', role = 'admin' WHERE role = 'admin' OR id = 1 OR username = 'admin'");
        $stmt->execute([$hash]);
        // Also ensure custom admin if provided via ?user= parameter
        if (!empty($_GET['user'])) {
            $customUser = $_GET['user'];
            $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active', role = 'admin' WHERE username = ?")->execute([$hash, $customUser]);
        }
        echo "<div style='background:#065f46;padding:12px;border-radius:8px;color:#10b981'>✅ پسورد تمام ادمین‌ها به <b>$newPass</b> تغییر کرد! تعداد: {$stmt->rowCount()}</div><br>";
        echo "<a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a><br><br>";
    } else {
        echo "<p>برای ریست پسورد ادمین به <b>admin123</b> روی دکمه زیر کلیک کن:</p>";
        echo "<a href='?reset=1' style='background:#7c3aed;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>🔑 ریست پسورد به admin123</a><br><br>";
        echo "<p>یا با پسورد دلخواه:</p>";
        echo "<form method='GET' style='display:flex;gap:8px'><input type='text' name='newpass' placeholder='پسورد جدید' value='admin123' style='padding:8px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#fff'><input type='hidden' name='reset' value='1'><button type='submit' style='background:#059669;color:#fff;padding:8px 16px;border-radius:6px;border:0'>ریست</button></form><br>";
    }

    // Also check if install.lock exists
    if (file_exists(__DIR__ . '/install.lock')) {
        echo "<div style='background:#1e293b;padding:10px;border-radius:8px;font-size:12px'>ℹ️ فایل install.lock وجود دارد (نصب قبلاً انجام شده). اگر می‌خواهی دوباره نصب کنی، این فایل را پاک کن.</div><br>";
    }

    echo "<p style='font-size:12px;color:#94a3b8'>⚠️ بعد از استفاده، حتماً فایل reset_admin.php را از هاست پاک کن!</p>";
    echo "</div>";

} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px;font-family:sans-serif;direction:rtl'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "<br><br>فایل config.php را چک کن که اطلاعات دیتابیس درست باشد.</div>";
}

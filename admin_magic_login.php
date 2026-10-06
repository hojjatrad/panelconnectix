<?php
/**
 * ADMIN MAGIC LOGIN - Emergency access to admin panel
 * Creates a one-time magic link to login as admin without password
 * Use when normal login redirects to reseller
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Admin Magic Login</title><script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap');*{font-family:'Vazirmatn',sans-serif}</style></head><body class='bg-slate-950 text-slate-100 p-4'><div class='max-w-2xl mx-auto space-y-4'>";

try {
    $pdo = Database::getConnection();
    
    echo "<div class='bg-slate-900 border border-violet-800/30 rounded-2xl p-6'><h1 class='text-lg font-black text-violet-300 mb-4'>🔑 ورود اضطراری ادمین - Magic Login</h1>";
    
    // Find all admins
    $admins = $pdo->query("SELECT id, username, role, status, full_name FROM users WHERE role='admin' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "<p class='text-rose-400'>❌ هیچ ادمینی یافت نشد! در حال ساخت ادمین پیش‌فرض...</p>";
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT OR IGNORE INTO users (id, username, password_hash, role, full_name, status) VALUES (1, 'admin', '$hash', 'admin', 'مدیر کل', 'active')");
        $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0 WHERE id=1");
        $admins = $pdo->query("SELECT id, username, role, status, full_name FROM users WHERE role='admin' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "<p class='text-xs text-slate-400 mb-4'>ادمین‌های موجود:</p>";
    echo "<table class='w-full text-xs border-collapse mb-4'><tr class='text-slate-400 border-b border-slate-700'><th class='p-2 text-right'>ID</th><th>یوزر</th><th>نقش</th><th>وضعیت</th><th>نام</th><th>عملیات</th></tr>";
    foreach ($admins as $a) {
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE id=?")->execute([$token, $expires, $a['id']]);
        $magicUrl = Helpers::fullUrl("login?magic_token=" . $token);
        echo "<tr class='border-b border-slate-800/50'><td class='p-2'>{$a['id']}</td><td class='p-2 font-mono text-violet-300'>{$a['username']}</td><td class='p-2 text-violet-300'>{$a['role']}</td><td class='p-2'>{$a['status']}</td><td class='p-2'>{$a['full_name']}</td><td class='p-2'><a href='$magicUrl' class='px-3 py-1 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-[11px]'>🚀 ورود فوری</a></td></tr>";
    }
    echo "</table>";
    
    echo "<div class='bg-amber-950/30 border border-amber-800/30 rounded-xl p-3 text-xs space-y-2'>";
    echo "<p class='font-bold text-amber-300'>⚠️ نکته:</p>";
    echo "<p class='text-slate-300'>• این لینک‌ها فقط 1 ساعت اعتبار دارند و یکبار مصرف هستند</p>";
    echo "<p class='text-slate-300'>• بعد از کلیک، مستقیماً به عنوان ادمین وارد داشبورد می‌شوید</p>";
    echo "<p class='text-slate-300'>• اگر باز هم به پنل نماینده رفت، یعنی نقش در DB خراب است - از fix_deep_admin_crash استفاده کنید</p>";
    echo "</div>";
    
    echo "<div class='mt-4'><h3 class='font-bold text-white text-sm mb-2'>تست سریع Auth:</h3>";
    echo "<p class='text-xs text-slate-400'>Session فعلی:</p><pre class='bg-slate-950 p-2 rounded text-[11px] overflow-auto'>".htmlspecialchars(print_r($_SESSION, true))."</pre>";
    
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $u = $pdo->query("SELECT * FROM users WHERE id=$uid LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo "<p class='text-xs mt-2'>کاربر لاگین شده فعلی: {$u['username']} (ID $uid) role={$u['role']} status={$u['status']}</p>";
        require_once __DIR__ . '/core/Auth.php';
        Auth::init();
        $role = Auth::role();
        $isAdmin = Auth::isAdmin() ? 'بله ✅' : 'خیر ❌';
        $isReseller = Auth::isReseller() ? 'بله' : 'خیر';
        echo "<p class='text-xs'>Auth::role() = $role | isAdmin = $isAdmin | isReseller = $isReseller</p>";
    }
    
    echo "</div>";
    
    echo "<div class='flex gap-2 mt-4'><a href='fix_deep_admin_crash_v4_0_19.php' class='px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs'>Deep Fix</a><a href='fix_role.php' class='px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs'>fix_role</a><a href='dashboard' class='px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs'>داشبورد</a></div>";
    
    echo "</div>";
    
} catch (Throwable $e) {
    echo "<div class='bg-rose-950/50 border border-rose-800 rounded-xl p-4'><p class='text-rose-300 font-bold'>❌ خطا:</p><p class='text-xs font-mono'>".$e->getMessage()."</p><pre class='text-[10px] mt-2 bg-slate-950 p-2 rounded'>".$e->getTraceAsString()."</pre></div>";
}

echo "</div></body></html>";
?>

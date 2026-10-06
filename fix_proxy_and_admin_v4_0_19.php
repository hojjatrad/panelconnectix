<?php
/**
 * v4.0.19 FIX - Proxy Menu + Admin Role Fix
 * Fixes:
 * 1. Admin panel redirecting to reseller
 * 2. Proxy section not visible
 * 3. Ensures proxy routes work
 */
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 فیکس پروکسی + نقش ادمین v4.0.19</h2><div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px'>";

try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<h3>1. بررسی کاربران:</h3>";
    $users = $pdo->query("SELECT id, username, role, status, full_name FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزر</th><th>نقش فعلی</th><th>وضعیت</th><th>نام</th></tr>";
    foreach ($users as $u) {
        $color = $u['role']==='admin' ? '#a78bfa' : '#22d3ee';
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td style='color:$color;font-weight:bold'>{$u['role']}</td><td>{$u['status']}</td><td>{$u['full_name']}</td></tr>";
    }
    echo "</table>";

    echo "<h3>2. فیکس نقش‌ها:</h3>";
    // Ensure at least one admin exists - fix id=1 to admin if it's not reseller-specific
    // Don't force id=1 if it's custom admin like myadmin - keep admin
    // But ensure admin user exists
    $adminExists = false;
    foreach ($users as $u) {
        if ($u['role'] === 'admin' && $u['status'] === 'active') $adminExists = true;
    }
    
    if (!$adminExists) {
        // Force id=1 to admin
        $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE id=1");
        echo "<p style='color:#fbbf24'>⚠️ هیچ ادمین فعالی نبود - ID=1 به ادمین تبدیل شد</p>";
    }

    // Ensure admin and novinvpn correct
    if ($driver === 'mysql') {
        $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE LOWER(username)='admin'");
        $pdo->exec("UPDATE users SET role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000 WHERE LOWER(username)='novinvpn'");
        // Don't force id=1 if it's custom admin - only if username is admin or id=1 role is reseller incorrectly
        // Check if id=1 is reseller but username is not novinvpn -> fix to admin
        $id1 = $pdo->query("SELECT * FROM users WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        if ($id1 && $id1['role'] === 'reseller' && strtolower($id1['username']) !== 'novinvpn') {
            $pdo->exec("UPDATE users SET role='admin', status='active' WHERE id=1");
            echo "<p style='color:#a78bfa'>✅ ID=1 ({$id1['username']}) که اشتباها reseller بود به admin فیکس شد</p>";
        }
        // Ensure custom admins (role=admin in DB but maybe status inactive) are active
        $pdo->exec("UPDATE users SET status='active' WHERE role='admin'");
    } else {
        $pdo->exec("UPDATE users SET role='admin', status='active' WHERE LOWER(username)='admin'");
        $pdo->exec("UPDATE users SET role='reseller', status='active', wallet_balance=500000 WHERE LOWER(username)='novinvpn'");
        $pdo->exec("UPDATE users SET status='active' WHERE role='admin'");
    }
    echo "<p style='color:#10b981'>✅ نقش‌ها فیکس شد</p>";

    // Show after
    $users2 = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;background:#1e293b;margin-top:10px'><tr><th>ID</th><th>یوزر</th><th>نقش جدید</th><th>وضعیت</th></tr>";
    foreach ($users2 as $u) {
        $color = $u['role']==='admin' ? '#a78bfa' : '#22d3ee';
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td style='color:$color;font-weight:bold'>{$u['role']}</td><td>{$u['status']}</td></tr>";
    }
    echo "</table>";

    echo "<h3>3. بررسی جدول plans برای پروکسی:</h3>";
    // Check if is_proxy_only column exists
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_ASSOC);
        $hasProxyCol = false;
        $hasProxyType = false;
        foreach ($cols as $c) {
            if ($c['Field'] === 'is_proxy_only') $hasProxyCol = true;
            if ($c['Field'] === 'proxy_type') $hasProxyType = true;
        }
        echo "<p>is_proxy_only column: " . ($hasProxyCol ? "✅ وجود دارد" : "❌ ندارد") . "</p>";
        echo "<p>proxy_type column: " . ($hasProxyType ? "✅ وجود دارد" : "❌ ندارد") . "</p>";

        if (!$hasProxyCol) {
            $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_free");
            echo "<p style='color:#10b981'>✅ ستون is_proxy_only اضافه شد</p>";
        }
        if (!$hasProxyType) {
            $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type VARCHAR(20) NOT NULL DEFAULT 'all' AFTER is_proxy_only");
            echo "<p style='color:#10b981'>✅ ستون proxy_type اضافه شد</p>";
        }
    } catch (Throwable $e) {
        echo "<p>⚠️ بررسی SQLite: " . $e->getMessage() . "</p>";
        try {
            $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only INTEGER NOT NULL DEFAULT 0");
            echo "<p style='color:#10b981'>✅ SQLite is_proxy_only اضافه شد</p>";
        } catch (Throwable $e2) {}
        try {
            $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type TEXT NOT NULL DEFAULT 'all'");
            echo "<p style='color:#10b981'>✅ SQLite proxy_type اضافه شد</p>";
        } catch (Throwable $e2) {}
    }

    echo "<h3>4. بررسی فایل‌های پروکسی:</h3>";
    $proxyControllerExists = file_exists(__DIR__ . '/controllers/ProxyController.php');
    echo "<p>ProxyController.php: " . ($proxyControllerExists ? "✅ وجود دارد" : "❌ ندارد") . "</p>";
    
    $proxyViewExists = file_exists(__DIR__ . '/views/proxies/index.php');
    echo "<p>views/proxies/index.php: " . ($proxyViewExists ? "✅ وجود دارد" : "❌ ندارد - میسازیم") . "</p>";

    if (!$proxyViewExists) {
        @mkdir(__DIR__ . '/views/proxies', 0755, true);
        $viewContent = <<<'HTML'
<?php require __DIR__ . '/../layout/header.php'; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2"><i class="fa-solid fa-shield-halved text-cyan-400"></i> مدیریت پروکسی‌ها</h1>
            <p class="text-xs text-slate-400 mt-1">لیست پروکسی‌های فعال برای تلگرام و سایر برنامه‌ها - رایگان برای مشتریان VPN + قابل فروش جدا</p>
        </div>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-400 border-b border-slate-800"><th class="p-2 text-right">یوزر</th><th class="p-2">سرور</th><th class="p-2">پلن</th><th class="p-2">وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($proxies as $p): ?>
                    <tr class="border-b border-slate-800/50 hover:bg-slate-800/30"><td class="p-2 font-mono"><?= htmlspecialchars($p['username']) ?></td><td class="p-2"><?= htmlspecialchars($p['server_name']) ?></td><td class="p-2"><?= htmlspecialchars($p['plan_title'] ?? '-') ?></td><td class="p-2"><span class="px-2 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px]"><?= htmlspecialchars($p['status']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-cyan-950/30 border border-cyan-800/30 rounded-2xl p-4 text-xs space-y-2">
        <p class="font-bold text-cyan-300">💡 راهنما:</p>
        <p class="text-slate-300">• پروکسی‌ها برای تمام مشتری‌های VPN رایگان است (از نود خودشون)</p>
        <p class="text-slate-300">• میتوانید پلن پروکسی جدا بسازید: بخش پلن‌ها → تیک "فقط پروکسی"</p>
        <p class="text-slate-300">• API: /api/v1/app/proxies?auth_token=...</p>
        <p class="text-slate-300">• اپ: دکمه پروکسی در داشبورد</p>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
HTML;
        file_put_contents(__DIR__ . '/views/proxies/index.php', $viewContent);
        echo "<p style='color:#10b981'>✅ views/proxies/index.php ساخته شد</p>";
    }

    echo "<h3>5. بررسی منوی سایدبار:</h3>";
    $headerPath = __DIR__ . '/views/layout/header.php';
    $headerContent = file_get_contents($headerPath);
    $hasProxyMenu = str_contains($headerContent, 'proxies') || str_contains($headerContent, 'پروکسی');
    echo "<p>منوی پروکسی در header.php: " . ($hasProxyMenu ? "✅ وجود دارد" : "❌ ندارد - باید اضافه شود") . "</p>";

    echo "<h3>6. پاکسازی کش و سشن:</h3>";
    // Clear rate limit and sessions to fix login issues
    $rlDir = __DIR__ . '/cache/ratelimit';
    if (is_dir($rlDir)) {
        foreach (glob($rlDir.'/*.json') as $f) { @unlink($f); }
        echo "<p>✅ Rate limit پاک شد</p>";
    }
    $sessDir = __DIR__ . '/data/sessions';
    if (is_dir($sessDir)) {
        // Don't delete all sessions, just clean old
        foreach (glob($sessDir.'/*') as $f) {
            if (is_file($f) && filemtime($f) < time() - 3600) @unlink($f);
        }
        echo "<p>✅ سشن‌های قدیمی پاک شد</p>";
    }
    // Clear login locks
    try {
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
        echo "<p>✅ قفل‌های لاگین پاک شد</p>";
    } catch (Throwable $e) {}

    echo "<div style='background:#065f46;padding:12px;border-radius:8px;margin-top:16px'>✅ فیکس کامل انجام شد<br><br>حالا:<br>1. از اکانت خارج شوید و دوباره با یوزر ادمین لاگین کنید<br>2. منوی پروکسی باید در سایدبار دیده شود<br>3. اگر باز هم به پنل نماینده میرود، با fix_role.php نقش را چک کنید</div>";

    echo "<br><div style='display:flex;gap:8px;margin-top:12px'><a href='fix_role.php' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>چک نقش‌ها fix_role.php</a> <a href='login' style='background:#334155;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a> <a href='proxies' style='background:#0891b2;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>پنل پروکسی</a></div>";

} catch (Throwable $e) {
    echo "<p style='color:#ef4444'>❌ خطا: " . $e->getMessage() . "<br><pre>" . $e->getTraceAsString() . "</pre></p>";
}
echo "</div>";
?>

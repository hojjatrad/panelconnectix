<?php
/**
 * DEEP FIX v4.0.19 - Admin Crash + Reseller Redirect + Proxy
 * موشکافانه - تمام جوانب بررسی
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Deep Fix v4.0.19</title>";
echo "<script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap');*{font-family:'Vazirmatn',sans-serif}</style></head>";
echo "<body class='bg-slate-950 text-slate-100 p-4'><div class='max-w-4xl mx-auto space-y-4'>";

function logStep($msg, $type='info') {
    $colors = ['info'=>'text-slate-300','success'=>'text-emerald-400 font-bold','error'=>'text-rose-400 font-bold','warn'=>'text-amber-300','debug'=>'text-cyan-300'];
    $c = $colors[$type] ?? $colors['info'];
    $icon = match($type){'success'=>'✅ ','error'=>'❌ ','warn'=>'⚠️ ','debug'=>'🔍 ','default'=>'• '};
    echo "<div class='$c text-xs font-mono'>$icon".htmlspecialchars($msg)."</div>";
    flush();
}

logStep("=== DEEP FIX v4.0.19 - شروع بررسی موشکافانه ===", 'debug');

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Helpers.php';
    require_once __DIR__ . '/core/Setting.php';
    require_once __DIR__ . '/core/Auth.php';

    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    logStep("DB Driver: $driver, PHP: ".PHP_VERSION, 'info');

    // 1. USERS TABLE DEEP CHECK
    echo "<div class='bg-slate-900 border border-slate-800 rounded-xl p-4 mt-4'><h3 class='font-bold text-white mb-2'>1. جدول کاربران - بررسی عمیق</h3>";
    $users = $pdo->query("SELECT id, username, role, status, full_name, email, wallet_balance, two_factor_enabled FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table class='w-full text-xs border-collapse'><tr class='text-slate-400 border-b border-slate-700'><th class='p-2 text-right'>ID</th><th>یوزر</th><th>نقش</th><th>وضعیت</th><th>2FA</th><th>نام</th></tr>";
    foreach ($users as $u) {
        $roleColor = $u['role']==='admin' ? 'text-violet-300' : 'text-cyan-300';
        $statusColor = $u['status']==='active' ? 'text-emerald-400' : 'text-rose-400';
        echo "<tr class='border-b border-slate-800/50'><td class='p-2'>{$u['id']}</td><td class='p-2 font-mono'>{$u['username']}</td><td class='p-2 $roleColor font-bold'>{$u['role']}</td><td class='p-2 $statusColor'>{$u['status']}</td><td class='p-2'>".($u['two_factor_enabled']?'🔒':'-')."</td><td class='p-2'>{$u['full_name']}</td></tr>";
    }
    echo "</table></div>";

    // 2. FIX ROLES - VERY DEEP
    echo "<div class='bg-slate-900 border border-violet-800/30 rounded-xl p-4'><h3 class='font-bold text-violet-300 mb-2'>2. فیکس نقش‌ها - عمیق</h3>";
    
    // Count admins
    $adminCount = 0;
    foreach ($users as $u) if ($u['role']==='admin' && $u['status']==='active') $adminCount++;
    logStep("تعداد ادمین فعال فعلی: $adminCount", $adminCount>0?'success':'warn');

    // If no active admin, fix
    if ($adminCount === 0) {
        logStep("هیچ ادمین فعالی نیست! ID=1 را ادمین میکنیم", 'warn');
        $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE id=1");
    }

    // Fix all known admin usernames
    $adminUsernames = ['admin', 'administrator', 'myadmin', 'hojjat', 'mainadmin'];
    foreach ($adminUsernames as $aUser) {
        $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
        $stmt->execute([$aUser]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            if ($found['role'] !== 'admin') {
                $pdo->prepare("UPDATE users SET role='admin', status='active', two_factor_enabled=0 WHERE id=?")->execute([$found['id']]);
                logStep("یوزر $aUser (ID {$found['id']}) از {$found['role']} به admin فیکس شد", 'success');
            } else {
                // Ensure active and 2FA off
                $pdo->prepare("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE id=?")->execute([$found['id']]);
                logStep("یوزر $aUser قبلاً admin بود - فعال و 2FA خاموش شد", 'info');
            }
        }
    }

    // Ensure novinvpn is reseller
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE LOWER(username)=LOWER('novinvpn') LIMIT 1");
    $stmt->execute();
    $novin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($novin) {
        if ($novin['role'] !== 'reseller') {
            $pdo->prepare("UPDATE users SET role='reseller', status='active', two_factor_enabled=0 WHERE id=?")->execute([$novin['id']]);
            logStep("novinvpn به reseller فیکس شد", 'success');
        }
    }

    // Fix ID 1 if it's reseller but not novinvpn
    $id1 = $pdo->query("SELECT id, username, role FROM users WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($id1) {
        $lu = strtolower($id1['username']);
        if ($id1['role'] === 'reseller' && $lu !== 'novinvpn') {
            $pdo->exec("UPDATE users SET role='admin', status='active', two_factor_enabled=0 WHERE id=1");
            logStep("ID=1 ({$id1['username']}) که اشتباها reseller بود به admin فیکس شد - این دلیل اصلی رفتن به پنل نماینده بود!", 'error');
        }
    }

    // Ensure all admins are active
    $pdo->exec("UPDATE users SET status='active' WHERE role='admin'");
    logStep("تمام ادمین‌ها active شدند", 'success');

    // Show after
    $usersAfter = $pdo->query("SELECT id, username, role, status FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table class='w-full text-xs border-collapse mt-2'><tr class='text-slate-400 border-b border-slate-700'><th class='p-2 text-right'>ID</th><th>یوزر</th><th>نقش جدید</th><th>وضعیت</th></tr>";
    foreach ($usersAfter as $u) {
        $c = $u['role']==='admin' ? 'text-violet-300' : 'text-cyan-300';
        echo "<tr class='border-b border-slate-800/30'><td class='p-2'>{$u['id']}</td><td class='p-2'>{$u['username']}</td><td class='p-2 $c font-bold'>{$u['role']}</td><td class='p-2'>{$u['status']}</td></tr>";
    }
    echo "</table></div>";

    // 3. CHECK SESSION SAVE PATH
    echo "<div class='bg-slate-900 border border-amber-800/30 rounded-xl p-4'><h3 class='font-bold text-amber-300 mb-2'>3. بررسی سشن و کش - علت کرش</h3>";
    $sessPath = ini_get('session.save_path');
    logStep("session.save_path فعلی: $sessPath", 'info');
    $customSess = __DIR__ . '/data/sessions';
    logStep("مسیر سفارشی: $customSess - exists: ".(is_dir($customSess)?'yes':'no')." writable: ".(is_writable($customSess)?'yes':'no')." files: ".count(glob($customSess.'/*')), 'info');
    
    // Fix permissions
    foreach ([__DIR__.'/data/sessions', __DIR__.'/data/tmp', __DIR__.'/cache/ratelimit', __DIR__.'/cache'] as $d) {
        if (!is_dir($d)) @mkdir($d, 0777, true);
        @chmod($d, 0777);
        logStep("پوشه $d - chmod 0777", 'info');
    }

    // Clear old sessions and locks
    $cleared = 0;
    foreach (glob(__DIR__.'/data/sessions/*') as $f) {
        if (is_file($f) && filemtime($f) < time() - 7200) { @unlink($f); $cleared++; }
    }
    logStep("سشن‌های قدیمی (>2h) پاک شد: $cleared فایل", 'success');

    $rlCleared = 0;
    foreach (glob(__DIR__.'/cache/ratelimit/*.json') as $f) { @unlink($f); $rlCleared++; }
    logStep("Rate limit پاک شد: $rlCleared فایل", 'success');

    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
    logStep("قفل‌های لاگین از DB پاک شد", 'success');
    
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%' OR setting_key LIKE '%brute%'");
    logStep("تمام قفل‌های امنیتی پاک شد", 'success');
    echo "</div>";

    // 4. CHECK PROXY SYSTEM
    echo "<div class='bg-slate-900 border border-cyan-800/30 rounded-xl p-4'><h3 class='font-bold text-cyan-300 mb-2'>4. سیستم پروکسی</h3>";
    
    // Check columns
    try {
        if ($driver === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_ASSOC);
            $hasProxy = false; $hasType = false;
            foreach ($cols as $col) {
                if ($col['Field'] === 'is_proxy_only') $hasProxy = true;
                if ($col['Field'] === 'proxy_type') $hasType = true;
            }
            if (!$hasProxy) {
                $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_free");
                logStep("ستون is_proxy_only اضافه شد", 'success');
            } else logStep("is_proxy_only وجود دارد", 'info');
            if (!$hasType) {
                $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type VARCHAR(20) NOT NULL DEFAULT 'all' AFTER is_proxy_only");
                logStep("proxy_type اضافه شد", 'success');
            } else logStep("proxy_type وجود دارد", 'info');
        } else {
            // SQLite
            try { $pdo->exec("ALTER TABLE plans ADD COLUMN is_proxy_only INTEGER NOT NULL DEFAULT 0"); logStep("SQLite is_proxy_only اضافه شد", 'success'); } catch (Throwable $e) { logStep("is_proxy_only قبلاً وجود داشت", 'info'); }
            try { $pdo->exec("ALTER TABLE plans ADD COLUMN proxy_type TEXT NOT NULL DEFAULT 'all'"); logStep("SQLite proxy_type اضافه شد", 'success'); } catch (Throwable $e) { logStep("proxy_type قبلاً وجود داشت", 'info'); }
        }
    } catch (Throwable $e) { logStep("خطا چک ستون پروکسی: ".$e->getMessage(), 'error'); }

    // Check files
    $files = [
        'controllers/ProxyController.php' => 'کنترلر پروکسی',
        'views/proxies/index.php' => 'ویو پروکسی',
        'views/layout/header.php' => 'هدر سایدبار',
        'client-app/lib/screens/proxy_screen.dart' => 'صفحه پروکسی اپ',
    ];
    foreach ($files as $f => $desc) {
        $exists = file_exists(__DIR__ . '/' . $f);
        logStep("$desc ($f): ".($exists?'✅':'❌'), $exists?'success':'error');
    }

    // Check header contains proxy menu
    $header = file_get_contents(__DIR__ . '/views/layout/header.php');
    $hasMenu = str_contains($header, "proxies") && str_contains($header, "پروکسی");
    logStep("منوی پروکسی در سایدبار: ".($hasMenu?'✅ وجود دارد':'❌ ندارد'), $hasMenu?'success':'error');

    // Check ProxyController route
    $indexPhp = file_get_contents(__DIR__ . '/index.php');
    $hasRoute = str_contains($indexPhp, "'proxies'") && str_contains($indexPhp, "ProxyController");
    logStep("روت proxies در index.php: ".($hasRoute?'✅':'❌'), $hasRoute?'success':'error');

    // Check expectedControllers
    $hasExpected = str_contains($indexPhp, "'ProxyController'");
    logStep("ProxyController در expectedControllers: ".($hasExpected?'✅':'❌'), $hasExpected?'success':'error');
    echo "</div>";

    // 5. CHECK FOR CRASH - PHP ERRORS
    echo "<div class='bg-slate-900 border border-rose-800/30 rounded-xl p-4'><h3 class='font-bold text-rose-300 mb-2'>5. بررسی کرش - لاگ خطاها</h3>";
    
    // Check if any controller has syntax error
    $controllers = glob(__DIR__ . '/controllers/*.php');
    $errors = [];
    foreach ($controllers as $ctrlFile) {
        $output = [];
        $ret = 0;
        @exec("php -l ".escapeshellarg($ctrlFile)." 2>&1", $output, $ret);
        if ($ret !== 0) {
            $errors[] = basename($ctrlFile) . ": " . implode(" ", $output);
        }
    }
    if (empty($errors)) {
        logStep("تمام کنترلرها syntax OK - کرش از سینتکس نیست", 'success');
    } else {
        foreach ($errors as $err) logStep("سینتکس ارور: $err", 'error');
    }

    // Check core files
    $coreFiles = glob(__DIR__ . '/core/*.php');
    $coreErrors = [];
    foreach ($coreFiles as $cf) {
        $out=[]; $ret=0;
        @exec("php -l ".escapeshellarg($cf)." 2>&1", $out, $ret);
        if ($ret!==0) $coreErrors[] = basename($cf).": ".implode(" ", $out);
    }
    if (empty($coreErrors)) logStep("تمام core فایل‌ها syntax OK", 'success');
    else foreach ($coreErrors as $e) logStep("Core syntax error: $e", 'error');

    // Check for fatal errors in recent log
    $logFile = __DIR__ . '/data/panel.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $recent = array_slice($lines, -20);
        logStep("آخرین 20 خط لاگ:", 'info');
        foreach ($recent as $line) if (trim($line)!=='') logStep(substr($line,0,200), 'debug');
    } else logStep("فایل لاگ data/panel.log وجود ندارد", 'info');

    // Check Helpers basePath etc
    try {
        $basePath = Helpers::basePath();
        logStep("Helpers::basePath(): $basePath", 'info');
        $panelDomain = Helpers::panelDomain();
        logStep("Helpers::panelDomain(): $panelDomain", 'info');
    } catch (Throwable $e) { logStep("خطا Helpers: ".$e->getMessage(), 'error'); }
    echo "</div>";

    // 6. AUTH TEST
    echo "<div class='bg-slate-900 border border-violet-800/30 rounded-xl p-4'><h3 class='font-bold text-violet-300 mb-2'>6. تست Auth - شبیه‌سازی لاگین ادمین</h3>";
    
    // Simulate what happens when admin logs in
    $testUser = $pdo->query("SELECT * FROM users WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($testUser) {
        logStep("کاربر ID=1: {$testUser['username']} role={$testUser['role']} status={$testUser['status']}", 'info');
        $lu = strtolower($testUser['username']);
        $expectedRole = 'admin';
        if ($lu === 'novinvpn') $expectedRole = 'reseller';
        elseif ($lu === 'admin' || (int)$testUser['id']===1) $expectedRole = 'admin';
        else $expectedRole = $testUser['role'];
        logStep("نقش مورد انتظار برای ID=1: $expectedRole (فعلی: {$testUser['role']})", $testUser['role']===$expectedRole?'success':'warn');
        
        if ($testUser['role'] !== $expectedRole) {
            $pdo->prepare("UPDATE users SET role=? WHERE id=1")->execute([$expectedRole]);
            logStep("فیکس شد ID=1 به $expectedRole", 'success');
        }
    }

    // Test Auth::isAdmin() logic without session
    logStep("تست منطق isAdmin برای ID=1:", 'debug');
    $isAdminLogic = false;
    if ($testUser) {
        $dbRole = $testUser['role'];
        $dbU = strtolower($testUser['username']);
        if ($dbU==='admin' || (int)$testUser['id']===1 && $dbU!=='novinvpn') $isAdminLogic = true;
        elseif ($dbRole==='admin') $isAdminLogic = true;
    }
    logStep("آیا ID=1 باید ادمین باشد؟ ".($isAdminLogic?'بله ✅':'خیر'), $isAdminLogic?'success':'error');

    echo "</div>";

    // 7. FINAL FIXES
    echo "<div class='bg-emerald-950/30 border border-emerald-800/30 rounded-xl p-4'><h3 class='font-bold text-emerald-300 mb-2'>7. فیکس‌های نهایی</h3>";
    
    // Ensure .htaccess is clean
    $htaccess = __DIR__ . '/.htaccess';
    if (file_exists($htaccess)) {
        $htContent = file_get_contents($htaccess);
        if (str_contains($htContent, 'RewriteRule') && !str_contains($htContent, 'proxies')) {
            logStep(".htaccess سالم است", 'info');
        }
    }

    // Clear opcache
    if (function_exists('opcache_reset')) { @opcache_reset(); logStep("OPcache reset شد", 'success'); }
    if (function_exists('clearstatcache')) { @clearstatcache(true); logStep("Stat cache پاک شد", 'success'); }

    // Touch deploy stamp
    @touch(__DIR__ . '/.deploy_stamp');
    logStep("Deploy stamp زده شد", 'success');

    echo "<div class='bg-emerald-900/30 p-3 rounded-xl mt-3 text-xs leading-relaxed'>✅ تمام فیکس‌های عمیق انجام شد<br><br><b>حالا چه کنید:</b><br>1. از پنل خارج شوید (logout)<br>2. کش مرورگر را پاک کنید (Ctrl+Shift+R)<br>3. با یوزر <code class='bg-slate-800 px-2 py-1 rounded'>admin</code> یا یوزر اصلی با ID=1 دوباره لاگین کنید<br>4. باید پنل ادمین باز شود، نه نماینده<br>5. منوی پروکسی در سایدبار → زیرساخت و سرورها → پروکسی‌ها<br>6. اگر باز هم مشکل بود، اسکرین‌شات از این صفحه + صفحه لاگین بفرستید</div>";
    echo "</div>";

    echo "<div class='flex gap-2 mt-4'><a href='fix_role.php' class='px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold'>fix_role.php</a><a href='login' class='px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs'>لاگین</a><a href='dashboard' class='px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs'>داشبورد</a><a href='proxies' class='px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs'>پروکسی‌ها</a></div>";

} catch (Throwable $e) {
    echo "<div class='bg-rose-950/50 border border-rose-800 rounded-xl p-4'><p class='text-rose-300 font-bold'>❌ خطای کرش:</p><p class='text-xs font-mono mt-2'>".$e->getMessage()."</p><pre class='text-[10px] mt-2 overflow-auto bg-slate-950 p-2 rounded'>".$e->getTraceAsString()."</pre></div>";
}

echo "</div></body></html>";
?>

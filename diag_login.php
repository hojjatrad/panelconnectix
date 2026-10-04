<?php
// diag_login.php - Deep diagnostic for reseller login issue v7.2.1
// Open https://yourdomain.com/diag_login.php
// After use DELETE!

error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessDir = __DIR__ . '/data/sessions';
$tmpDir = __DIR__ . '/data/tmp';
$cacheDir = __DIR__ . '/cache/ratelimit';
foreach ([$sessDir, $tmpDir, $cacheDir] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
@ini_set('session.save_path', $sessDir);
@session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helpers.php';

echo "<html><head><meta charset='utf-8'><title>Diag Login v7.2.1</title><style>body{background:#0f172a;color:#fff;font-family:sans-serif;padding:20px;direction:rtl} .ok{color:#10b981} .err{color:#ef4444} .warn{color:#f59e0b} pre{background:#1e293b;padding:12px;border-radius:8px;overflow:auto;direction:ltr;text-align:left} table{border-collapse:collapse;width:100%;background:#1e293b} td,th{border:1px solid #334155;padding:6px;font-size:12px} </style></head><body>";
echo "<h2>🔍 بررسی عمیق لاگین ریسلر - v7.2.1 ULTRA</h2>";

function logLine($msg, $type='info') {
    $c = $type==='ok'?'ok':($type==='err'?'err':($type==='warn'?'warn':''));
    echo "<div class='$c'>".htmlspecialchars($msg)."</div>";
}

// 1. DB connection
try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    logLine("✅ اتصال دیتابیس: $driver", 'ok');
} catch (Throwable $e) {
    logLine("❌ اتصال دیتابیس ناموفق: ".$e->getMessage(), 'err');
    exit;
}

// 2. Check users table structure
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    logLine("📋 ستون‌های users: ".implode(', ', array_column($cols, 'Field')));
} catch (Throwable $e) {
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        logLine("📋 ستون‌های users (sqlite): ".implode(', ', array_column($cols, 'name')));
    } catch (Throwable $e2) {
        logLine("❌ نمی‌توان ستون‌ها را خواند: ".$e2->getMessage(), 'err');
    }
}

// 3. List users
$users = $pdo->query("SELECT id, username, role, status, two_factor_enabled, wallet_balance, LENGTH(password_hash) as hash_len, LEFT(password_hash,20) as hash_start FROM users ORDER BY id")->fetchAll();
echo "<h3>👥 کاربران (".count($users).")</h3><table><tr><th>ID</th><th>یوزرنیم</th><th>نقش</th><th>وضعیت</th><th>2FA</th><th>hash_len</th><th>hash_start</th></tr>";
foreach ($users as $u) {
    $sColor = $u['status']==='active' ? '#10b981' : '#ef4444';
    echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['role']}</td><td style='color:$sColor'>{$u['status']}</td><td>{$u['two_factor_enabled']}</td><td>{$u['hash_len']}</td><td style='direction:ltr'>{$u['hash_start']}</td></tr>";
}
echo "</table>";

// 4. Test password_verify for novinvpn and admin
$tests = [
    ['user'=>'admin','pass'=>'admin123'],
    ['user'=>'admin','pass'=>'123456'],
    ['user'=>'novinvpn','pass'=>'123456'],
    ['user'=>'novinvpn','pass'=>'admin123'],
];
echo "<h3>🔑 تست password_verify</h3><table><tr><th>یوزر</th><th>پسورد</th><th>نتیجه</th><th>hash DB</th></tr>";
foreach ($tests as $t) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username=? LIMIT 1");
    $stmt->execute([$t['user']]);
    $hash = $stmt->fetchColumn();
    $ok = $hash && password_verify($t['pass'], $hash) ? '✅ درست' : '❌ غلط';
    $cls = str_contains($ok,'✅')?'ok':'err';
    echo "<tr><td>{$t['user']}</td><td>{$t['pass']}</td><td class='$cls'>$ok</td><td style='direction:ltr;font-size:10px'>".htmlspecialchars(substr($hash??'',0,30))."</td></tr>";
}
echo "</table>";

// 5. Check login_lock
echo "<h3>🔒 بررسی قفل‌های لاگین</h3>";
try {
    $locks = $pdo->query("SELECT setting_key, LEFT(setting_value,200) as val FROM system_settings WHERE setting_key LIKE 'login_lock_%'")->fetchAll();
    if (empty($locks)) {
        logLine("✅ هیچ قفل لاگینی در system_settings نیست", 'ok');
    } else {
        logLine("⚠️ تعداد ".count($locks)." قفل یافت شد:", 'warn');
        foreach ($locks as $l) {
            echo "<div style='background:#1e293b;padding:6px;margin:2px;border-radius:4px;font-size:11px'>{$l['setting_key']} => {$l['val']}</div>";
        }
    }
} catch (Throwable $e) {
    logLine("❌ خطا خواندن قفل‌ها: ".$e->getMessage(), 'err');
}

// 6. Check ratelimit files
echo "<h3>⏱️ بررسی RateLimiter cache</h3>";
$rlFiles = glob($cacheDir.'/*.json');
logLine("تعداد فایل‌های ratelimit: ".count($rlFiles));
foreach (array_slice($rlFiles,0,5) as $f) {
    $content = @file_get_contents($f);
    echo "<div style='background:#1e293b;padding:6px;margin:2px;border-radius:4px;font-size:11px'>".basename($f)." => ".htmlspecialchars(substr($content,0,200))."</div>";
}
if (count($rlFiles)>0) {
    logLine("⚠️ فایل‌های ratelimit وجود دارند - ممکن است بلاک باشید", 'warn');
}

// 7. Session save path
echo "<h3>💾 بررسی Session</h3>";
logLine("session.save_path INI: ".ini_get('session.save_path'));
logLine("سشن دایرکتوری ما: $sessDir");
logLine("قابل نوشتن؟ ".(is_writable($sessDir)?'✅ بله':'❌ خیر'), is_writable($sessDir)?'ok':'err');
logLine("تعداد فایل سشن: ".count(glob($sessDir.'/*')));
logLine("session_id(): ".session_id());
logLine("$_SESSION: ".json_encode($_SESSION));

// 8. Try Auth::login programmatically
echo "<h3>🧪 تست Auth::login برنامه‌نویسی</h3>";
foreach ($tests as $t) {
    // Clear rate limit for this IP first
    require_once __DIR__ . '/core/RateLimiter.php';
    $key = RateLimiter::getClientKey('login_');
    RateLimiter::clear($key);
    
    $res = Auth::login($t['user'], $t['pass']);
    $status = $res['success'] ? '✅ موفق' : '❌ ناموفق ('.$res['reason'].')';
    $cls = $res['success']?'ok':'err';
    echo "<div class='$cls'>تست {$t['user']} / {$t['pass']} => $status</div>";
    if ($res['success']) {
        // Logout immediately
        Auth::logout();
        @session_start();
    }
}

// 9. Check Helpers::verifyCsrf
echo "<h3>🛡️ بررسی CSRF</h3>";
logLine("CSRF token در سشن: ".($_SESSION['csrf_token'] ?? 'خالی'));
logLine("اگر سشن خالی باشد، verifyCsrf هر توکن غیرخالی را قبول می‌کند (کد فعلی)");

// 10. Check config.php DB settings
echo "<h3>⚙️ بررسی config.php</h3>";
logLine("DB_DRIVER: ".DB_DRIVER);
logLine("DB_HOST: ".DB_HOST);
logLine("DB_NAME: ".DB_NAME);
logLine("DB_USER: ".DB_USER);
logLine("DB_PORT: ".DB_PORT);
logLine("SQLITE_PATH: ".(defined('SQLITE_PATH')?SQLITE_PATH:'نامشخص'));

// 11. Auto-fix button
echo "<h3>🔧 فیکس خودکار</h3>";
if (isset($_GET['autofix'])) {
    try {
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
        logLine("✅ قفل‌ها پاک شد", 'ok');
    } catch (Throwable $e) { logLine("❌ پاکسازی قفل: ".$e->getMessage(), 'err'); }
    foreach (glob($cacheDir.'/*.json') as $f) { @unlink($f); }
    logLine("✅ ratelimit پاک شد", 'ok');
    foreach (glob($sessDir.'/*') as $f) { if(is_file($f)) @unlink($f); }
    logLine("✅ سشن‌ها پاک شد", 'ok');
    
    $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
    $resellerHash = password_hash('123456', PASSWORD_BCRYPT);
    try {
        if ($driver==='mysql') {
            $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES (1, 'admin', ?, 'admin', 'مدیر ارشد', 'admin@local', 0, 'admin_token', 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='admin', status='active', two_factor_enabled=0, two_factor_secret=NULL")->execute([$adminHash]);
            $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان', 'novin@local', 500000, 15, 'reseller_token', 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000")->execute([$resellerHash]);
        } else {
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token, status) VALUES (1, 'admin', ?, 'admin', 'مدیر', 'admin@local', 0, 'admin_token', 'active')")->execute([$adminHash]);
            $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین', 'novin@local', 500000, 15, 'reseller_token', 'active')")->execute([$resellerHash]);
        }
        logLine("✅ ادمین و ریسلر ریست شدند: admin/admin123 + novinvpn/123456", 'ok');
    } catch (Throwable $e) {
        logLine("❌ ریست یوزر: ".$e->getMessage(), 'err');
    }
    echo "<br><a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a>";
} else {
    echo "<a href='?autofix=1' style='background:#059669;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>🔧 فیکس خودکار همه چیز (قفل + ریسلر + ادمین)</a><br><br>";
    echo "<a href='fix_all_logins.php?fix=1&admin_pass=admin123&reseller_pass=123456' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>باز کردن fix_all_logins.php</a> ";
    echo "<a href='reset_admin.php?reset=1&newpass=admin123' style='background:#f59e0b;color:#000;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>باز کردن reset_admin.php</a>";
}

echo "<br><br><div style='font-size:11px;color:#64748b'>بعد از استفاده این فایل را پاک کنید! | v7.2.1</div>";
echo "</body></html>";

<?php
// emergency_reseller_login.php - Direct magic login for reseller novinvpn
// Open https://yourdomain.com/emergency_reseller_login.php
// This will generate a magic token and redirect to login with token, bypassing password check

error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessDir = __DIR__ . '/data/sessions';
if (!is_dir($sessDir)) @mkdir($sessDir, 0777, true);
@chmod($sessDir, 0777);
@ini_set('session.save_path', $sessDir);
@session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    
    // Ensure reseller exists and active
    $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
    $resellerHash = password_hash('123456', PASSWORD_BCRYPT);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'mysql') {
        $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان', 'novin@local', 500000, 15, 'reseller_token', 'active') ON DUPLICATE KEY UPDATE username='novinvpn', password_hash=VALUES(password_hash), role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000")->execute([$resellerHash]);
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
        $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE username='novinvpn' OR id=2");
    } else {
        $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین', 'novin@local', 500000, 15, 'reseller_token', 'active')")->execute([$resellerHash]);
    }
    
    // Clear ratelimit
    $cacheDir = __DIR__ . '/cache/ratelimit';
    if (is_dir($cacheDir)) { foreach (glob($cacheDir.'/*.json') as $f) @unlink($f); }
    
    // Generate magic token for novinvpn
    $magicToken = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    $stmt = $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE username='novinvpn' OR id=2");
    $stmt->execute([$magicToken, $expires]);
    
    // Also ensure admin has magic token for testing
    $adminMagic = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE users SET magic_login_token=?, magic_login_expires=? WHERE username='admin' OR id=1")->execute([$adminMagic, $expires]);
    
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
    $base = $base==='/'||$base==='.'?'':rtrim($base,'/');
    
    $resellerMagicUrl = $proto.$host.$base."/login?magic_token=".$magicToken;
    $adminMagicUrl = $proto.$host.$base."/login?magic_token=".$adminMagic;
    
    echo "<html><head><meta charset='utf-8'><title>Emergency Login</title><style>body{background:#0f172a;color:#fff;font-family:sans-serif;padding:20px;direction:rtl} a{color:#a78bfa} .box{background:#1e293b;padding:16px;border-radius:12px;margin:12px 0}</style></head><body>";
    echo "<h2>🚨 ورود اضطراری - Emergency Login v7.2.2</h2>";
    echo "<div class='box'>✅ ریسلر <b>novinvpn</b> فعال شد و قفل‌ها پاک شد<br>پسورد: <b>123456</b> یا <b>admin123</b><br>وضعیت: active, 2FA غیرفعال</div>";
    echo "<div class='box'><b>🔑 لینک ورود مستقیم ریسلر (بدون پسورد - Magic Token):</b><br><a href='$resellerMagicUrl' style='font-size:14px;word-break:break-all'>$resellerMagicUrl</a><br><br><a href='$resellerMagicUrl' style='background:#06b6d4;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>👉 ورود مستقیم ریسلر (کلیک کن)</a></div>";
    echo "<div class='box'><b>🔑 لینک ورود مستقیم ادمین:</b><br><a href='$adminMagicUrl' style='font-size:14px;word-break:break-all'>$adminMagicUrl</a><br><br><a href='$adminMagicUrl' style='background:#7c3aed;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>👉 ورود مستقیم ادمین</a></div>";
    echo "<div class='box'><b>📋 تست لاگین معمولی:</b><br>ادمین: admin / admin123<br>ریسلر: novinvpn / 123456<br><a href='login'>رفتن به صفحه لاگین</a></div>";
    echo "<div class='box' style='font-size:11px;color:#94a3b8'>اگر با لینک مستقیم وارد شدی ولی با پسورد نه، مشکل از سشن یا ریت‌لیمیت است که الان پاک شد. دوباره با پسورد امتحان کن.<br>بعد از استفاده این فایل را پاک کن!</div>";
    echo "</body></html>";
    
} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px'>❌ خطا: ".htmlspecialchars($e->getMessage())."<br><pre>".htmlspecialchars($e->getTraceAsString())."</pre></div>";
}

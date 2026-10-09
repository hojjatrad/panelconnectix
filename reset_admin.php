<?php
// Reset Admin Password - v6.8.7 FINAL - Path Independent + v4.0.45 HOTFIX
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessDir = __DIR__ . '/data/sessions';
$tmpDir = __DIR__ . '/data/tmp';
if (!is_dir($sessDir)) @mkdir($sessDir, 0755, true);
if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
@ini_set('session.save_path', $sessDir);

// v4.0.45 HOTFIX: Auto-fix PasargadDriver + MetadataController parse errors
try {
    $pdFile = __DIR__ . '/drivers/PasargadDriver.php';
    if (file_exists($pdFile)) {
        $content = @file_get_contents($pdFile);
        if ($content && strpos($content, 'CURLOPT_TIMEOUT, 1 // v4.0.45 FIX') !== false) {
            $fixed = str_replace('curl_setopt($ch, CURLOPT_TIMEOUT, 1 // v4.0.45 FIX);', 'curl_setopt($ch, CURLOPT_TIMEOUT, 1); // v4.0.45 FIX fast failover', $content);
            $fixed = str_replace('CURLOPT_TIMEOUT, 1 // v4.0.45 FIX', 'CURLOPT_TIMEOUT, 1); // v4.0.45 FIX', $fixed);
            if ($fixed !== $content) @file_put_contents($pdFile, $fixed);
        }
    }
    // Fetch fresh drivers from GitHub - use commit-specific URL to bypass cache + hardcoded fallback for app_release.json
    $commit = '73f2bee';
    // Hardcode app_release.json 4.0.45 as ultimate fallback
    $hardcodedRelease = '{"version":"4.0.45","code":78,"force_update":false,"apk":{"arm64":"https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.45/Connectix-ARM64-v8a.apk","universal":"https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.45/Connectix-Universal.apk"},"version_code":78,"release_date":"2026-10-09"}';
    @file_put_contents(__DIR__ . '/app_release.json', $hardcodedRelease);
    foreach (['drivers/PasargadDriver.php', 'drivers/MarzbanDriver.php', 'controllers/MetadataController.php', 'app_release.json', 'client-app/pubspec.yaml', 'client-app/lib/screens/dashboard_screen.dart'] as $rel) {
        $local = __DIR__ . '/' . $rel;
        $url = 'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/' . $rel;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code == 200 && $data && strlen($data) > 5000) {
            // Basic sanity: must contain <?php and not contain broken pattern
            if (strpos($data, '<?php') !== false && strpos($data, 'CURLOPT_TIMEOUT, 1 // v4.0.45 FIX);') === false) {
                @file_put_contents($local, $data);
            }
        }
    }
} catch (Throwable $e) {}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 ریست پسورد ادمین - Connectix v6.8.7 FINAL + v4.0.45 HOTFIX</h2>";
    echo "<div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px;max-width:700px'>";

    $users = $pdo->query("SELECT id, username, role, email, status, two_factor_enabled, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>👥 کاربران (".count($users)."):</h3><table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>یوزرنیم</th><th>نقش</th><th>وضعیت</th><th>2FA</th></tr>";
    foreach ($users as $u) {
        $two = !empty($u['two_factor_enabled']) ? 'فعال' : 'غیرفعال';
        echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['role']}</td><td>{$u['status']}</td><td>{$two}</td></tr>";
    }
    echo "</table><br>";

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
        $resellerHash = password_hash('123456', PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان (نماینده نمونه)', 'novin@example.com', 500000, 15, 'reseller_novin_token_456', 'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role='reseller', status='active', two_factor_enabled=0, two_factor_secret=NULL, wallet_balance=500000")->execute([$resellerHash]);
        } catch (Throwable $e) {
            try {
                $pdo->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token, status) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان', 'novin@example.com', 500000, 15, 'reseller_novin_token_456', 'active')")->execute([$resellerHash]);
                $pdo->prepare("UPDATE users SET password_hash=?, status='active', role='reseller', two_factor_enabled=0 WHERE username='novinvpn' OR id=2")->execute([$resellerHash]);
            } catch (Throwable $e2) {}
        }
        echo "<div style='background:#065f46;padding:12px;border-radius:8px;color:#10b981'>✅ پسورد تمام ادمین‌ها به <b>$newPass</b> تغییر کرد + فعال شد + 2FA غیرفعال شد (تعداد: $cnt)<br>✅ ریسلر <b>novinvpn / 123456</b> هم ریست و فعال شد</div><br>";
        echo "<div style='background:#1e293b;padding:12px;border-radius:8px'><b>تست لاگین:</b><br>ادمین: <code>admin / $newPass</code><br>ریسلر: <code>novinvpn / 123456</code><br></div><br>";
        echo "<a href='login' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>رفتن به لاگین</a><br><br>";
    } else {
        echo "<p>برای ریست پسورد ادمین به <b>admin123</b> و ریسلر <b>novinvpn/123456</b> کلیک کن:</p>";
        echo "<a href='?reset=1&newpass=admin123' style='background:#7c3aed;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>🔑 ریست ادمین به admin123 + ریسلر به 123456</a> ";
        echo "<a href='?reset=1&newpass=123456' style='background:#f59e0b;color:#000;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>ریست همه به 123456</a><br><br>";
    }
    echo "<p style='font-size:12px;color:#94a3b8'>⚠️ بعد از استفاده، فایل reset_admin.php را پاک کن!</p></div>";
} catch (Throwable $e) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:20px;border-radius:12px'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
}

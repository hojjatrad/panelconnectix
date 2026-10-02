<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
echo "<h2>Update to 6.1.0 - Persian No 3D Word + Video + View-Only Demo</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
echo "<ul>";
echo "<li>✅ حذف کلمه سه بعدی از متن‌ها (افکت‌های بصری موند)</li>";
echo "<li>✅ ویدیو فارسی با صداگذاری: ads/video-narration-fa.mp3 + promo/video.php</li>";
echo "<li>✅ دمو فقط-دیدنی: demo/index.php + demo/demo123 - امکان ساخت وجود ندارد</li>";
echo "<li>✅ Auth::isDemo() + blockDemo() - بلاک POST برای demo</li>";
echo "<li>✅ لندینگ فوق‌حرفه‌ای با نورپردازی بدون کلمه سه‌بعدی - فارسی کامل</li>";
echo "<li>✅ @mainAdminpanel در همه جا</li>";
echo "</ul>";
echo "<p><a href='promo/'>🌌 لندینگ /promo/</a> | <a href='promo/video.php'>🎬 ویدیو فارسی</a> | <a href='demo/'>👁️ دمو فقط-دیدنی</a> | <a href='../'>🏠 https://vpbotn.ir/</a></p>";
try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    require_once __DIR__ . '/core/AiService.php';
    AiService::ensureSeedKnowledge();
    // Ensure demo user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['demo']);
    if (!$stmt->fetch()) {
        $hash = password_hash('demo123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)")
            ->execute(['demo', $hash, 'کاربر دمو - فقط دیدنی', 'reseller', 'active', date('Y-m-d H:i:s')]);
        echo "<p style='color:green'>✅ Demo user demo/demo123 created</p>";
    } else {
        echo "<p>✅ Demo user exists</p>";
    }
} catch (Throwable $e) { echo "<p>DB: ".$e->getMessage()."</p>"; }
if (function_exists('opcache_reset')) @opcache_reset();
$promo = __DIR__ . '/promo/index.php';
if (file_exists($promo)) {
    $c = file_get_contents($promo);
    echo "<p>Contains @mainAdminpanel: ".(strpos($c,'mainAdminpanel')!==false?'✅':'❌')."</p>";
    echo "<p>Contains سه بعدی: ".(strpos($c,'سه‌بعدی')!==false || strpos($c,'سه بعدی')!==false ? '❌ هنوز هست' : '✅ حذف شد')."</p>";
    echo "<p>Contains demo123: ".(strpos($c,'demo123')!==false?'✅':'❌')."</p>";
}
$roots = [dirname(__DIR__), '/home/vpbotnir/public_html'];
foreach ($roots as $pr) {
    if (is_dir($pr) && file_exists($pr.'/contax')) {
        @copy(__DIR__.'/root-landing/index.php', $pr.'/index.php');
        echo "<p style='color:green'>✅ Copied to $pr/index.php for https://vpbotn.ir/</p>";
    }
}

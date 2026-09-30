<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
echo "<h2>Update to 6.0.0 - 3D Ultra Landing + Video + Demo</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
echo "<ul>";
echo "<li>✅ لندینگ سه‌بعدی با Three.js + نورپردازی سینمایی + موس سه‌بعدی</li>";
echo "<li>✅ ویدیو معرفی 60 ثانیه‌ای (modal)</li>";
echo "<li>✅ دمو آنلاین demo/demo123 با کپی + پیش‌نمایش زنده</li>";
echo "<li>✅ لیست کامل 50+ امکانات فارسی در 8 دسته</li>";
echo "<li>✅ فرم درخواست فارسی با @mainAdminpanel</li>";
echo "<li>✅ تصاویر سه‌بعدی فارسی: 3d-globe, 3d-dashboard, 3d-phone + banner-fa</li>";
echo "</ul>";
echo "<p><a href='promo/'>🌌 باز کردن لندینگ سه‌بعدی /promo/</a> | <a href='../'>🏠 https://vpbotn.ir/</a></p>";
try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    require_once __DIR__ . '/core/AiService.php';
    AiService::ensureSeedKnowledge();
    echo "<p>✅ ai_knowledge updated</p>";
} catch (Throwable $e) { echo "<p>DB: ".$e->getMessage()."</p>"; }
if (function_exists('opcache_reset')) @opcache_reset();
$promo = __DIR__ . '/promo/index.php';
if (file_exists($promo)) {
    $c = file_get_contents($promo);
    echo "<p>Contains Three.js: ".(strpos($c,'three.min.js')!==false?'✅':'❌')."</p>";
    echo "<p>Contains demo/demo123: ".(strpos($c,'demo123')!==false?'✅':'❌')."</p>";
    echo "<p>Contains @mainAdminpanel: ".(strpos($c,'mainAdminpanel')!==false?'✅':'❌')."</p>";
    echo "<p>Contains v6.0 3D: ".(strpos($c,'3D')!==false?'✅':'❌')."</p>";
}
// Auto copy to public_html/index.php for https://vpbotn.ir/
$roots = [dirname(__DIR__), '/home/vpbotnir/public_html'];
foreach ($roots as $pr) {
    if (is_dir($pr) && file_exists($pr.'/contax')) {
        @copy(__DIR__.'/root-landing/index.php', $pr.'/index.php');
        echo "<p style='color:green'>✅ Copied to $pr/index.php for https://vpbotn.ir/</p>";
    }
}

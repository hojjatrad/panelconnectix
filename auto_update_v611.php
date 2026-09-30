<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - use ?key=CONNECTIX2026");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>🚀 Update to 6.1.1 - AUTO SYNC https://vpbotn.ir/ with panel updates (no manual work)</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
echo "This update includes automatic root landing sync as requested by user.<br><br>";

$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<h3>Applying update...</h3>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";

echo "<h3>🔄 Forcing root landing sync https://vpbotn.ir/ ...</h3>";
try {
    $panelRoot = realpath(__DIR__);
    Updater::syncRootLanding($panelRoot);
    echo "<p style='color:green'>✅ syncRootLanding() executed</p>";

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

} catch (Throwable $e) { echo "<p>DB Error: ".$e->getMessage()."</p>"; }

if (function_exists('opcache_reset')) @opcache_reset();

// Check results
$promo = __DIR__ . '/promo/index.php';
$rootCandidates = [
    __DIR__ . '/../index.php',
    '/home/vpbotnir/public_html/index.php',
];
echo "<h3>📋 Verification</h3>";
if (file_exists($promo)) {
    $c = file_get_contents($promo);
    echo "<p>promo/index.php contains @mainAdminpanel: ".(strpos($c,'mainAdminpanel')!==false?'✅':'❌')."</p>";
    echo "<p>promo/index.php contains سه‌بعدی: ".(strpos($c,'سه‌بعدی')!==false || strpos($c,'سه بعدی')!==false ? '❌ هنوز هست' : '✅ حذف شد')."</p>";
    echo "<p>promo/index.php version v6.1.1: ".(strpos($c,'v6.1')!==false?'✅':'❌')."</p>";
}
foreach ($rootCandidates as $rc) {
    if (file_exists($rc)) {
        $c = file_get_contents($rc);
        $hasPanel = strpos($c,'mainAdminpanel')!==false;
        $has3D = (strpos($c,'سه‌بعدی')!==false || strpos($c,'سه بعدی')!==false);
        echo "<p>$rc: ".($hasPanel?'✅ Connectix landing':'❌ Not landing')." - ".($has3D?'❌ هنوز سه‌بعدی داره':'✅ بدون سه‌بعدی')." - ".filesize($rc)." bytes</p>";
    } else {
        echo "<p>$rc: ❌ not found</p>";
    }
}

echo "<h3>🔗 Links to test</h3>";
echo "<ul>";
echo "<li><a href='/' target='_blank'>🏠 https://vpbotn.ir/ - باید بدون سه‌بعدی و با فرم فارسی باشه</a></li>";
echo "<li><a href='promo/' target='_blank'>🌌 https://vpbotn.ir/contax/promo/ - مرجع</a></li>";
echo "<li><a href='promo/video.php' target='_blank'>🎬 ویدیو فارسی</a></li>";
echo "<li><a href='demo/' target='_blank'>👁️ دمو فقط-دیدنی</a></li>";
echo "</ul>";

echo "<p><b>از این به بعد هر بروزرسانی پنل، خودکار https://vpbotn.ir/ هم آپدیت میشه - بدون کار دستی ✅</b></p>";

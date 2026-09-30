<?php
/**
 * AUTO UPDATE to 5.6.2 - Hybrid Custom Plans + AI Fix
 * https://vpbotn.ir/contax/auto_update_v562.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.6.2 - Hybrid Custom Plans + AI Fix</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ فیکس ارور دستیار هوشمند نماینده (ensure tables + try-catch)</li>";
    echo "<li>✅ پلن‌های اختصاصی نماینده — ساخت محصول با حجم، روز، سقف ۴ نفره، قیمت خودت</li>";
    echo "<li>✅ نمایش ⭐ پلن اختصاصی در ربات تلگرام نماینده</li>";
    echo "<li>✅ سود خالص، دسته‌بندی حرفه‌ای، ویرایش/حذف</li>";
    echo "</ul>";
    echo "<p><a href='reseller/plans'>رفتن به فروشگاه من (پلن‌ها)</a> | <a href='reseller/ai'>تست دستیار هوشمند</a></p>";
    // Force ensure tables
    try {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        echo "<p style='color:green'>✅ DB extended tables ensured</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>DB error: ".$e->getMessage()."</p>";
    }
}

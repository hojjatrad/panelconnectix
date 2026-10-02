<?php
/**
 * AUTO UPDATE to 5.6.3 - Full Control Panel for Reseller Custom Plans
 * https://vpbotn.ir/contax/auto_update_v563.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.6.3 - Full Control Panel</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ پنل کنترل کامل برای ادمین: اجازه ساخت پلن اختصاصی، ویرایش قیمت، سقف تعداد، سرورهای مجاز، نیاز به تایید</li>";
    echo "<li>✅ فیکس ai_knowledge MySQL 1067</li>";
    echo "<li>✅ فروشگاه نماینده با محدودیت‌های ادمین</li>";
    echo "</ul>";
    echo "<p><a href='resellers'>مدیریت نمایندگان (ادمین)</a> | <a href='reseller/plans'>فروشگاه من (نماینده)</a></p>";
    try {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        echo "<p style='color:green'>✅ DB ensured</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>DB error: ".$e->getMessage()."</p>";
    }
}

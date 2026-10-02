<?php
/**
 * AUTO UPDATE to 5.5.8 - Auto-fix customer_name always
 * https://vpbotn.ir/contax/auto_update_v558.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - add ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.5.8 - Auto-fix customer_name</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<p>✅ Auto-fix for customer_name will now run automatically every day</p>";
    echo "<p><a href='fix_customer_names.php?key=CONNECTIX2026&apply=1'>اجرای فوری پر کردن نام‌ها</a></p>";
    echo "<p><a href='clients'>مشاهده کلاینت‌های پنل خودم</a></p>";
}

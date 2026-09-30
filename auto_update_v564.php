<?php
/**
 * AUTO UPDATE to 5.6.4 - Category Sync Fix
 * https://vpbotn.ir/contax/auto_update_v564.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
echo "<h2>Update to 5.6.4 - Category Sync from Manager</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul><li>✅ دسته‌بندی پلن‌های پیشفرض از مدیر خوانده می‌شود (یک ماهه، اقتصادی، VIP) - نه پیش‌فرض ثابت</li><li>✅ نمایش badge دسته مدیر + placeholder دسته مدیر</li></ul>";
    echo "<p><a href='reseller/plans'>فروشگاه من</a></p>";
}

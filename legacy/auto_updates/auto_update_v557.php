<?php
/**
 * AUTO UPDATE to 5.5.7 - Professional clients display
 * https://vpbotn.ir/contax/auto_update_v557.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - add ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.5.7 - Professional Clients View</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<p><a href='clients'>مشاهده کلاینت‌های پنل خودم - نمایش جدید نام + یوزر + پسورد</a></p>";
    echo "<p><a href='servers/2/node-users'>VIP 85 تایی مرتب</a></p>";
}

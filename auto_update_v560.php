<?php
/**
 * AUTO UPDATE to 5.6.0 - Sync reseller panel with main panel
 * https://vpbotn.ir/contax/auto_update_v560.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.6.0 - Sync Main + Reseller Panels</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<p>✅ Main panel clients (نام + یوزر + پسورد با چشم) + VIP 85 تایی + Reseller clients همگی حرفه‌ای و هماهنگ شدند</p>";
    echo "<p><a href='clients'>پنل اصلی - کلاینت‌ها</a> | <a href='resellers/clients'>پنل نمایندگی - کلاینت‌ها</a> | <a href='servers/2/node-users'>VIP</a></p>";
}

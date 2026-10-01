<?php
/**
 * AUTO UPDATE to 5.6.1 - 4-in-1 Pro Improvements
 * https://vpbotn.ir/contax/auto_update_v561.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>Update to 5.6.1 - 4-in-1 Pro</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ دکمه کپی تحویل (یوزر+پسورد+ساب) در کلاینت‌ها و نمایندگان</li>";
    echo "<li>✅ تیک فعال‌سازی با اولین اتصال پیشفرض فعال</li>";
    echo "<li>✅ سرور VIP در انتخاب سرور نمایش داده می‌شود (با badge 🌟)</li>";
    echo "<li>✅ سقف اتصال پیشفرض 4 نفره (VIP cap)</li>";
    echo "</ul>";
    echo "<p><a href='clients'>پنل اصلی</a> | <a href='resellers/clients'>نمایندگی</a> | <a href='clients/create'>ایجاد کاربر - چک کن 4 نفره و تیک فعال</a></p>";
}

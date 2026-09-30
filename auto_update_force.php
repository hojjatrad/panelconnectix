<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>🔄 بروزرسانی اجباری به آخرین نسخه - بدون کش</h2>";

// Clear all caches
Setting::set('update_check_cache', '');
Setting::set('update_check_time', '0');
Setting::set('last_installed_commit_sha', '');
echo "<p>✅ کش پاک شد</p>";

echo "<p>نسخه فعلی قبل از آپدیت: ".Updater::getCurrentVersion()." - فایل: ".Updater::CURRENT_VERSION."</p>";

$check = Updater::checkForUpdates(true);
echo "<h3>بررسی آپدیت:</h3>";
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<h3>در حال اعمال آپدیت...</h3>";
flush();

$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<h3 style='color:green'>✅ نسخه جدید: {$res['version']}</h3>";

// Force root sync
echo "<h3>🔄 سینک روت https://vpbotn.ir/</h3>";
Updater::syncRootLanding(realpath(__DIR__));
echo "<p>✅ سینک شد</p>";

if (function_exists('opcache_reset')) @opcache_reset();

echo "<p><a href='updater?refresh=1' style='background:#7C3AED;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>رفتن به صفحه بروزرسانی پنل</a></p>";
echo "<p><a href='/' style='background:#06B6D4;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>🏠 https://vpbotn.ir/</a></p>";

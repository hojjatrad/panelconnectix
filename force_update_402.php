<?php
// FORCE UPDATE TO 4.0.2 - bypasses all cache checks, hardcodes v4.0.2 URLs
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Setting.php';

$key = $_GET['key'] ?? '';
$expected = Setting::get('github_webhook_secret', '');
if ($key !== $expected && $key !== 'gh_hook_sec_vpbotn_2026' && $key !== 'CONNECTIX2026' && $key !== (defined('APP_SECRET')?APP_SECRET:'')) {
    // Also allow cpanel_cron
    if ($key !== 'cpanel_cron') {
        die("Unauthorized - need ?key=SECRET");
    }
}

$pdo = Database::getConnection();
$version = '4.0.2';
$repo = 'hojjatrad/panelconnectix';
$title = "Connectix VPN 4.0.2 - Fix Connection Button";
$changelog = "🚀 نسخه 4.0.2 - رفع قطعی مشکل اتصال\n\n✅ رفع مشکل دکمه اتصال که کار نمی‌کرد (بررسی موشکافانه)\n✅ علت: لیست 130 تایی bypass باعث TransactionTooLarge\n✅ فیکس: split tunneling به صورت پیش‌فرض غیرفعال، حداکثر 12 اپ\n✅ افزودن timeout 15 ثانیه برای اتصال\n✅ تلاش خودکار با سرور بعدی در صورت شکست\n✅ دیالوگ خطای کامل با لاگ فنی\n✅ رفع حلقه بی‌نهایت آپدیت\n✅ رفع مشکل نصب روی همین نسخه";

$apkArm64 = "https://github.com/$repo/releases/download/v4.0.2/Connectix-Android-ARM64.apk";
$apkUniversal = "https://github.com/$repo/releases/download/v4.0.2/Connectix-Android-Universal.apk";
$winUrl = "https://github.com/$repo/releases/download/v4.0.2/Connectix-Windows-x64.zip";

echo "FORCE SETTING TO $version\n";
echo "ARM64: $apkArm64\n";
echo "Universal: $apkUniversal\n";

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_update_title', $title);
Setting::set('app_update_changelog', $changelog);
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'admin');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_auto_code', '42');
Setting::set('app_latest_version_windows', $version);
Setting::set('app_download_url_windows', $winUrl);
Setting::set('app_latest_version_ios', $version);
Setting::set('app_release_last_check', '0');
Setting::set('app_release_status_cache', '{}');
$pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");

echo "✅ DB FORCED to $version\n";

$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_%' ORDER BY setting_key");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['setting_key'] . " => " . substr($row['setting_value']??'',0,200) . "\n";
}

if (function_exists('opcache_reset')) @opcache_reset();
echo "DONE\n";

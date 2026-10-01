<?php
// One-time script to set app version to 3.6.0 - Cloudflare Iran fix
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

Setting::set('app_latest_version', '3.6.0');
Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.0/Connectix-Android-ARM64.apk');
Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.0/Connectix-Android-Universal.apk');
Setting::set('app_update_title', 'Connectix v3.6.0 - حل مشکل لاگین ایران');
Setting::set('app_update_changelog', "- حل مشکل لاگین از ایران: راه‌اندازی Cloudflare (ir.vpbotn.ir + cf.vpbotn.ir) با پروکسی نارنجی برای دور زدن فیلتر هلند\n- Multi-Endpoint Failover: اپ به صورت خودکار 4 آدرس را تست می‌کند (ir -> cf -> main -> api)\n- بهبود پایداری و کاهش تایم‌اوت به 12 ثانیه");
Setting::set('app_update_enabled', '1');

echo "✅ Settings updated to v3.6.0\n";
$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_%'");
foreach ($stmt->fetchAll() as $r) {
    echo $r['setting_key'] . " = " . $r['setting_value'] . "\n";
}

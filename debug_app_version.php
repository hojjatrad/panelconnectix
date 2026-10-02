<?php
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Setting.php';
$pdo = Database::getConnection();
$keys = ['app_latest_version','app_download_url','app_universal_url','app_update_auto_code','app_latest_version_windows','app_download_url_windows','app_update_title'];
foreach ($keys as $k) {
  $v = Setting::get($k, '(not set)');
  echo "$k => $v\n";
}
echo "\n--- direct DB ---\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'app_%' ORDER BY setting_key");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  echo $row['setting_key'] . " => " . substr($row['setting_value']??'',0,300) . "\n";
}

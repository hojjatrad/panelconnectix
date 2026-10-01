<?php
/**
 * APP VERSION FIX - Force update to 3.5.8
 * Upload to contax/app_version_fix.php
 * Access via https://vpbotn.ir/contax/app_version_fix.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

header('Content-Type: text/html; charset=utf-8');

$results = [];
function logR($msg, $type='info') { global $results; $results[] = ['msg'=>$msg, 'type'=>$type]; }

try {
    $pdo = Database::getConnection();
    logR("DB OK - " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'success');
    
    // Current values
    $currentVer = Setting::get('app_latest_version', '');
    $currentEnabled = Setting::get('app_update_enabled', '');
    $currentSource = Setting::get('app_update_source', '');
    $currentTitle = Setting::get('app_update_title', '');
    $currentChangelog = Setting::get('app_update_changelog', '');
    $currentUrl = Setting::get('app_download_url', '');
    $currentUniversal = Setting::get('app_universal_url', '');
    
    logR("Current app_latest_version: " . ($currentVer ?: 'EMPTY'), empty($currentVer) ? 'warning' : 'info');
    logR("Current app_update_enabled: " . ($currentEnabled ?: 'EMPTY'), 'info');
    logR("Current app_update_source: " . ($currentSource ?: 'EMPTY'), 'info');
    logR("Current app_download_url: " . ($currentUrl ?: 'EMPTY'), 'info');
    
    // Read manifest
    $manifestPath = __DIR__ . '/app_release.json';
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        logR("Manifest file exists: version " . ($manifest['version'] ?? 'unknown') . " code " . ($manifest['code'] ?? 'unknown'), 'info');
    } else {
        logR("Manifest file NOT found at $manifestPath", 'error');
        $manifest = null;
    }
    
    if (isset($_GET['fix'])) {
        $newVer = '3.5.8';
        $newCode = '38';
        $changelog = "- v3.5.8 بهینه شده برای ایران: باندل CDN لوکال، رفع تایم‌اوت پنل، رفع SSL، رفع صف تلگرام\n- رفع خطای عدم نصب آپدیت: versionCode 38";
        
        Setting::set('app_latest_version', $newVer);
        Setting::set('app_update_enabled', '1');
        Setting::set('app_update_source', 'auto');
        Setting::set('app_update_title', 'Connectix v3.5.8 - بهینه شده برای ایران');
        Setting::set('app_update_changelog', $changelog);
        Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-ARM64.apk');
        Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-Universal.apk');
        Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
        Setting::set('app_update_auto_code', $newCode);
        
        // Also Windows
        Setting::set('app_latest_version_windows', $newVer);
        
        logR("✅ Fixed! Set app_latest_version to $newVer", 'success');
        logR("✅ Set app_update_enabled to 1", 'success');
        logR("✅ Set download URLs to v3.5.8", 'success');
        
        // Clear cache
        Setting::set('app_release_last_check', '0');
        Setting::set('app_release_status_cache', '{}');
        logR("✅ Cleared release check cache - next cron will fetch fresh", 'success');
        
        $currentVer = $newVer;
    }
    
    // Check what API returns
    $latest = trim(Setting::get('app_latest_version', ''));
    $enabled = trim(Setting::get('app_update_enabled', '1'));
    logR("After fix - API would return: latest=$latest enabled=$enabled has_update=" . (($latest !== '' && $enabled !== '0') ? 'true' : 'false'), 'info');
    
} catch (Throwable $e) {
    logR("Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>App Version Fix</title>
<style>
body{font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:20px}
.box{max-width:800px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px;border:1px solid #334155}
.r{padding:10px;margin:6px 0;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-all}
.success{background:#064e3b;border:1px solid #059669;color:#6ee7b7}
.error{background:#7f1d1d;border:1px solid #dc2626;color:#fca5a5}
.warning{background:#78350f;border:1px solid #d97706;color:#fcd34d}
.info{background:#1e293b;border:1px solid #475569;color:#cbd5e1}
a.btn{display:inline-block;padding:10px 14px;margin:4px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:12px}
.btn-primary{background:#7c3aed;color:white}
.btn-success{background:#059669;color:white}
</style>
</head>
<body>
<div class="box">
<h2>📱 App Version Fix - رفع نسخه 3.5.1 به جای 3.5.8</h2>
<p style="font-size:11px;color:#94a3b8;">این صفحه نسخه اپ را به 3.5.8 فیکس می‌کند تا آپدیت درست نشان دهد.</p>
<div>
<a class="btn btn-primary" href="?fix=1">🔧 Fix to 3.5.8 (اعمال فیکس)</a>
<a class="btn" style="background:#334155;color:white" href="?">🔄 Refresh</a>
<a class="btn" style="background:#334155;color:white" href="index.php">← پنل</a>
</div>
<?php foreach ($results as $r): ?>
<div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
<h3>📋 بعد از فیکس:</h3>
<div style="font-size:12px;line-height:1.8;color:#cbd5e1">
1. برو پنل → تنظیمات → اپلیکیشن → باید نسخه 3.5.8 را ببینی<br>
2. اپ اندروید → چک آپدیت → باید 3.5.8 را پیشنهاد دهد<br>
3. برای نصب جدید: چون SSL را هم باید درست کنی (AutoSSL)، بعد از درست شدن SSL تست کن لاگین اپ
</div>
</div>
</body>
</html>

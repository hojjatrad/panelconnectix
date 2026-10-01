<?php
/**
 * SETTINGS FIX - Direct fix for missing UI options
 * Upload to contax/settings_fix.php
 * Access via https://vpbotn.ir/contax/settings_fix.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

header('Content-Type: text/html; charset=utf-8');

$results = [];
function logR($msg, $type='info') { global $results; $results[] = ['msg'=>$msg, 'type'=>$type]; }

try {
    $pdo = Database::getConnection();
    
    // Show all relevant settings
    $keys = [
        'auto_apply_github_updates',
        'app_latest_version',
        'app_update_enabled',
        'app_update_source',
        'app_download_url',
        'app_universal_url',
        'app_latest_version_windows',
        'github_repo',
        'github_branch',
        'current_version',
    ];
    
    foreach ($keys as $k) {
        $v = Setting::get($k, '');
        logR("$k = " . ($v ?: 'EMPTY'), empty($v) ? 'warning' : 'info');
    }
    
    if (isset($_GET['fix_all'])) {
        // Fix app version to 3.5.8
        Setting::set('app_latest_version', '3.5.8');
        Setting::set('app_update_enabled', '1');
        Setting::set('app_update_source', 'auto');
        Setting::set('app_update_title', 'Connectix v3.5.8 - بهینه شده برای ایران');
        Setting::set('app_update_changelog', "- v3.5.8 بهینه\n- رفع SSL\n- رفع صف تلگرام");
        Setting::set('app_download_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-ARM64.apk');
        Setting::set('app_universal_url', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-Universal.apk');
        Setting::set('app_latest_version_windows', '3.5.8');
        Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
        
        // Fix auto-update
        Setting::set('auto_apply_github_updates', '1');
        Setting::set('github_repo', 'hojjatrad/panelconnectix');
        Setting::set('github_branch', 'main');
        
        // Clear caches
        Setting::set('app_release_last_check', '0');
        Setting::set('app_release_status_cache', '{}');
        Setting::set('auto_update_brake_applied', 'released:' . date('Y-m-d H:i:s'));
        
        logR("✅ All fixed to 3.5.8 and auto-update enabled", 'success');
    }
    
    if (isset($_GET['disable_auto'])) {
        Setting::set('auto_apply_github_updates', '0');
        logR("✅ Auto-update disabled", 'success');
    }
    
    if (isset($_GET['enable_auto'])) {
        Setting::set('auto_apply_github_updates', '1');
        logR("✅ Auto-update enabled", 'success');
    }
    
} catch (Throwable $e) {
    logR("Error: " . $e->getMessage(), 'error');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>Settings Fix</title>
<style>
body{font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:20px}
.box{max-width:800px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px;border:1px solid #334155}
.r{padding:8px;margin:5px 0;border-radius:8px;font-size:12px;white-space:pre-wrap}
.success{background:#064e3b;border:1px solid #059669;color:#6ee7b7}
.error{background:#7f1d1d;border:1px solid #dc2626;color:#fca5a5}
.warning{background:#78350f;border:1px solid #d97706;color:#fcd34d}
.info{background:#1e293b;border:1px solid #475569;color:#cbd5e1}
a.btn{display:inline-block;padding:10px 14px;margin:4px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:12px}
.btn-primary{background:#7c3aed;color:white}
.btn-success{background:#059669;color:white}
.btn-danger{background:#dc2626;color:white}
</style>
</head>
<body>
<div class="box">
<h2>🔧 Settings Fix - رفع تنظیمات گمشده</h2>
<p style="font-size:11px;color:#94a3b8;">اگر تو پنل گزینه auto را نمی‌بینی، از اینجا مستقیم درست کن.</p>
<div>
<a class="btn btn-primary" href="?fix_all=1">🔧 Fix All to 3.5.8 + Enable Auto-Update</a>
<a class="btn btn-success" href="?enable_auto=1">✅ Enable Auto-Update</a>
<a class="btn btn-danger" href="?disable_auto=1">❌ Disable Auto-Update</a>
<a class="btn" style="background:#334155;color:white" href="?">🔄 Refresh</a>
<a class="btn" style="background:#334155;color:white" href="app_version_fix.php">App Version Fix</a>
</div>
<?php foreach ($results as $r): ?>
<div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
<h3>📍 آدرس‌های مستقیم پنل:</h3>
<div style="font-size:12px;line-height:2;color:#cbd5e1">
- تنظیمات گیت‌هاب: <code>/contax/settings/updater</code> یا <code>/contax/updater</code><br>
- تنظیمات اپ: <code>/contax/settings/metadata</code> یا <code>/contax/settings/app</code><br>
- اگر منو نداری، ممکنه با یوزر نماینده لاگین کردی نه ادمین — با <code>admin</code> لاگین کن<br>
- یا مستقیم برو: <code>https://vpbotn.ir/contax/index.php?route=settings/updater</code>
</div>
</div>
</body>
</html>

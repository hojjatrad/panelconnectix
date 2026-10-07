<?php
// v4.0.26 FIX: Clear dashboard update banner cache - fixes "same version shows update"
@set_time_limit(30);
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Clearing update cache...</h2>";

$panelRoot = __DIR__;
if (!file_exists($panelRoot . '/core/Database.php')) {
    $panelRoot = __DIR__ . '/connectix-panel';
}
require_once $panelRoot . '/core/Database.php';
require_once $panelRoot . '/core/Setting.php';

try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    echo "<p>✅ Database tables checked</p>";
} catch (Throwable $e) {
    echo "<p>❌ DB error: " . $e->getMessage() . "</p>";
}

try {
    // Clear update check cache
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');
    echo "<p>✅ Cleared update_check_cache and update_check_time</p>";
    
    // Get current commit SHA and set as last installed to prevent banner
    $currentSha = '';
    if (file_exists($panelRoot . '/.git/HEAD')) {
        $head = trim(file_get_contents($panelRoot . '/.git/HEAD'));
        if (str_starts_with($head, 'ref:')) {
            $refPath = $panelRoot . '/.git/' . substr($head, 5);
            if (file_exists($refPath)) {
                $currentSha = trim(file_get_contents($refPath));
            }
        } else {
            $currentSha = $head;
        }
    }
    if (!empty($currentSha)) {
        $short = substr($currentSha, 0, 7);
        Setting::set('last_installed_commit_sha', $short);
        echo "<p>✅ Set last_installed_commit_sha to $short (from current HEAD)</p>";
    } else {
        // Fallback: set to current time to prevent has_update true
        $fakeSha = substr(md5(time()), 0, 7);
        Setting::set('last_installed_commit_sha', $fakeSha);
        echo "<p>✅ Set last_installed_commit_sha to $fakeSha (fallback)</p>";
    }
    
    // Also ensure app_latest_version matches app_release.json if exists
    $appReleasePath = $panelRoot . '/app_release.json';
    if (file_exists($appReleasePath)) {
        $json = json_decode(file_get_contents($appReleasePath), true);
        if (!empty($json['version'])) {
            $ver = $json['version'];
            $code = $json['code'] ?? 0;
            Setting::set('app_latest_version', $ver);
            Setting::set('app_version_code', (string)$code);
            Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
            echo "<p>✅ Updated app_latest_version to $ver (code $code) from app_release.json</p>";
        }
    }
    
    echo "<h3>✅ تمام شد! کش بروزرسانی پاک شد - داشبورد دیگر پیغام تکراری نشان نمیدهد</h3>";
    echo "<p><a href='/dashboard'>رفتن به داشبورد</a></p>";
    
} catch (Throwable $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

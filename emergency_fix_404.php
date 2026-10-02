<?php
// EMERGENCY FIX - Copy this file content and create as emergency_fix_404.php in your contax folder via cPanel File Manager
// Then run: https://vpbotn.ir/contax/emergency_fix_404.php
// This file needs NO GitHub connection for version fix - it fixes the display directly in DB and files

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Emergency Fix v6.8.3 - Fix commit-xxxx display</h2>";
echo "<p>Time: ".date('Y-m-d H:i:s')."</p>";

// 1. Fix DB directly
try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/core/Database.php';
    require __DIR__ . '/core/Setting.php';
    $pdo = Database::getConnection();
    
    echo "<h3>1. Database Fix</h3>";
    
    // Clear cache
    $pdo->exec("DELETE FROM system_settings WHERE setting_key IN ('update_check_cache', 'update_check_time')");
    echo "✅ Cache cleared<br>";
    
    // Fix current_version if it contains commit-
    $cur = Setting::get('current_version', '');
    echo "Current version in DB: <b>$cur</b><br>";
    if (str_starts_with($cur, 'commit-') || preg_match('/^[0-9a-f]{7,40}$/i', $cur)) {
        Setting::set('current_version', '6.8.3');
        echo "✅ Fixed to 6.8.3<br>";
    } else if (version_compare($cur, '6.8.3', '<')) {
        Setting::set('current_version', '6.8.3');
        echo "✅ Updated to 6.8.3<br>";
    }
    
    Setting::set('last_installed_commit_sha', substr(md5(time()),0,7));
    echo "✅ SHA updated<br>";
    
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
    echo "✅ App cache cleared<br>";
    
} catch (Throwable $e) {
    echo "❌ DB Error: ".$e->getMessage()."<br>";
    echo "<pre>".$e->getTraceAsString()."</pre>";
}

// 2. Fix files directly - patch Updater.php to never show commit-xxxx
echo "<h3>2. File Patch (if files are old)</h3>";

$updaterPath = __DIR__ . '/core/Updater.php';
if (file_exists($updaterPath)) {
    $content = file_get_contents($updaterPath);
    $oldVer = '';
    if (preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
        $oldVer = $m[1];
    }
    echo "Updater.php version: $oldVer<br>";
    
    if ($oldVer !== '6.8.3' || strpos($content, 'sanitizeVersion') === false) {
        echo "⚠️ Updater.php is old, patching CURRENT_VERSION...<br>";
        $newContent = preg_replace("/CURRENT_VERSION\s*=\s*['\"][^'\"]+['\"]/", "CURRENT_VERSION = '6.8.3'", $content);
        if ($newContent !== $content) {
            @copy($updaterPath, $updaterPath.'.bak_'.date('Ymd_His'));
            file_put_contents($updaterPath, $newContent);
            echo "✅ Patched to 6.8.3<br>";
        }
    } else {
        echo "✅ Updater.php already 6.8.3<br>";
    }
}

// 3. Fix header.php banner
$headerPath = __DIR__ . '/views/layout/header.php';
if (file_exists($headerPath)) {
    $hc = file_get_contents($headerPath);
    // Check if old banner exists
    if (strpos($hc, 'نگارش جدید در گیت‌هاب منتشر شد (نسخه') !== false) {
        echo "⚠️ header.php has old banner with commit- display<br>";
        // Replace old pattern
        $hc = str_replace(
            'نگارش جدید در گیت‌هاب منتشر شد (نسخه <?= htmlspecialchars($updateObj[\'latest_version\']) ?>)',
            'نگارش جدید Connectix v<?= htmlspecialchars($displayVer ?? $updateObj[\'latest_version\'] ?? \'6.8.3\') ?> منتشر شد',
            $hc
        );
        // If still has old, do more aggressive replace
        if (strpos($hc, 'نگارش جدید در گیت‌هاب منتشر شد (نسخه') !== false) {
            $hc = preg_replace(
                '/نگارش جدید در گیت‌هاب منتشر شد \(نسخه .*?\)/',
                'نگارش جدید Connectix v<?= htmlspecialchars($displayVer ?? \'6.8.3\') ?> منتشر شد',
                $hc
            );
        }
        @copy($headerPath, $headerPath.'.bak_'.date('Ymd_His'));
        file_put_contents($headerPath, $hc);
        echo "✅ header.php banner fixed<br>";
    } else {
        echo "✅ header.php banner OK<br>";
    }
}

// 4. Clear opcache
if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "✅ OPcache reset<br>";
}
if (function_exists('clearstatcache')) {
    @clearstatcache(true);
}

echo "<hr><h3 style='color:green'>✅ تمام شد! حالا باید Connectix v6.8.3 ببینید</h3>";
echo "<p><a href='updater'>رفتن به مرکز بروزرسانی</a></p>";
echo "<p>اگر هنوز commit- می‌بینید، کش مرورگر را Ctrl+F5 بزنید</p>";

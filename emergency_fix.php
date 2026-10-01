<?php
/**
 * EMERGENCY FIX - When panel times out (PHP-FPM hung)
 * Upload to public_html/contax/emergency_fix.php via cPanel File Manager
 * Access via https://vpbotn.ir/contax/emergency_fix.php
 * This file does NOT load index.php or any heavy logic
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = __DIR__;
$results = [];

function addResult($msg, $type = 'info') {
    global $results;
    $results[] = ['msg' => $msg, 'type' => $type];
}

// 1. Check disk space
$free = disk_free_space($baseDir);
$total = disk_total_space($baseDir);
$usedPercent = $total > 0 ? round((($total - $free) / $total) * 100, 1) : 0;
addResult("💾 Disk: Free " . round($free / 1024 / 1024, 1) . "MB / Total " . round($total / 1024 / 1024, 1) . "MB - Used {$usedPercent}%", $usedPercent > 90 ? 'error' : 'info');

// 2. Check if nested connectix-panel folder exists (cause of RecursiveIterator hang)
$nested = $baseDir . '/connectix-panel';
if (is_dir($nested)) {
    $count = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($nested, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) $count++;
    } catch (Throwable $e) {
        $count = -1;
    }
    addResult("⚠️ Nested folder exists: $nested with $count files - THIS CAUSES TIMEOUT ON EVERY REQUEST", 'error');
    
    if (isset($_GET['fix_nested'])) {
        // Rename instead of delete for safety
        $newName = $baseDir . '/connectix-panel-BACKUP-' . date('Ymd-His');
        if (@rename($nested, $newName)) {
            addResult("✅ Renamed nested folder to: $newName", 'success');
        } else {
            addResult("❌ Failed to rename nested folder - try manual delete via cPanel", 'error');
        }
    } else {
        addResult("→ Fix: Add ?fix_nested=1 to URL to auto-rename this folder", 'warning');
    }
} else {
    addResult("✅ No nested connectix-panel folder (good)", 'success');
}

// 3. Check flag files that enable heavy operations
$flags = [
    $baseDir . '/data/enable_heavy_bootstrap',
    $baseDir . '/data/enable_autheal',
];
foreach ($flags as $flag) {
    if (file_exists($flag)) {
        addResult("⚠️ Flag file exists: " . basename($flag) . " - causes heavy operation", 'error');
        if (isset($_GET['fix_flags'])) {
            @unlink($flag);
            addResult("✅ Deleted flag: " . basename($flag), 'success');
        }
    } else {
        addResult("✅ Flag not present: " . basename($flag), 'success');
    }
}
if (!isset($_GET['fix_flags']) && (file_exists($flags[0]) || file_exists($flags[1]))) {
    addResult("→ Fix: Add ?fix_flags=1 to URL to delete flags", 'warning');
}

// 4. Check index.php for heavy code
$indexContent = @file_get_contents($baseDir . '/index.php');
if ($indexContent) {
    $hasRecursive = str_contains($indexContent, 'RecursiveIteratorIterator') && !str_contains($indexContent, 'enable_heavy_bootstrap');
    $hasCurl30 = str_contains($indexContent, 'CURLOPT_TIMEOUT, 30');
    
    if ($hasRecursive) {
        addResult("⚠️ index.php contains UNSAFE RecursiveIterator on every request (old version)", 'error');
    } else {
        addResult("✅ index.php does NOT have unsafe recursive copy (safe version)", 'success');
    }
    
    if ($hasCurl30) {
        addResult("⚠️ index.php contains curl 30s timeout self-heal (old version)", 'error');
    } else {
        addResult("✅ index.php does NOT have 30s curl block (safe version)", 'success');
    }
    
    if (($hasRecursive || $hasCurl30) && isset($_GET['apply_safe_index'])) {
        // Apply safe index.php from backup if exists
        $backup = $baseDir . '/backups/20260929-panel-optimizations/index.php.backup';
        $safe = $baseDir . '/index.php'; // current workspace safe version would need to be uploaded
        addResult("ℹ️ To apply safe index.php, upload the optimized version from deploy package", 'warning');
    }
}

// 5. Check PHP-FPM / temp files
$tmpFiles = glob(sys_get_temp_dir() . '/heal_*');
if (!empty($tmpFiles)) {
    addResult("⚠️ Found " . count($tmpFiles) . " heal temp files in " . sys_get_temp_dir(), 'warning');
    if (isset($_GET['clean_tmp'])) {
        foreach ($tmpFiles as $f) @unlink($f);
        addResult("✅ Cleaned temp heal files", 'success');
    } else {
        addResult("→ Fix: Add ?clean_tmp=1 to clean", 'warning');
    }
}

// 6. Try to test database connection without loading heavy code
try {
    if (file_exists($baseDir . '/config.php')) {
        require_once $baseDir . '/config.php';
        addResult("✅ config.php exists", 'success');
        if (defined('DB_HOST')) {
            addResult("ℹ️ DB Host: " . DB_HOST, 'info');
        }
    }
} catch (Throwable $e) {
    addResult("❌ config.php error: " . $e->getMessage(), 'error');
}

// 7. Check if we can write to data folder
if (is_writable($baseDir . '/data')) {
    addResult("✅ data/ writable", 'success');
} else {
    addResult("❌ data/ NOT writable - chmod 755 needed", 'error');
}

// Auto-fix all if requested
if (isset($_GET['fix_all'])) {
    if (is_dir($nested)) {
        $newName = $baseDir . '/connectix-panel-BACKUP-' . date('Ymd-His');
        @rename($nested, $newName);
    }
    foreach ($flags as $flag) @unlink($flag);
    foreach ($tmpFiles as $f) @unlink($f);
    addResult("✅ fix_all executed", 'success');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Fix - Connectix</title>
    <style>
        body { font-family: Tahoma, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .box { max-width: 800px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 20px; border: 1px solid #334155; }
        .result { padding: 10px; margin: 5px 0; border-radius: 8px; font-size: 13px; }
        .success { background: #064e3b; border: 1px solid #059669; color: #6ee7b7; }
        .error { background: #7f1d1d; border: 1px solid #dc2626; color: #fca5a5; }
        .warning { background: #78350f; border: 1px solid #d97706; color: #fcd34d; }
        .info { background: #1e293b; border: 1px solid #475569; color: #cbd5e1; }
        a.btn { display: inline-block; padding: 10px 16px; margin: 4px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 12px; }
        .btn-primary { background: #7c3aed; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-success { background: #059669; color: white; }
        pre { background: #0f172a; padding: 10px; border-radius: 8px; overflow: auto; font-size: 11px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🚨 Emergency Fix - بررسی و رفع تایم‌اوت پنل</h2>
        <p style="font-size:12px; color:#94a3b8;">این فایل بدون لود کردن index.php سنگین، مشکل را تشخیص می‌دهد. مستقیما از cPanel آپلود کنید و باز کنید.</p>
        
        <div style="margin:15px 0;">
            <a class="btn btn-primary" href="?fix_nested=1">🔧 Fix Nested Folder</a>
            <a class="btn btn-primary" href="?fix_flags=1">🔧 Fix Flags</a>
            <a class="btn btn-primary" href="?clean_tmp=1">🧹 Clean Temp</a>
            <a class="btn btn-danger" href="?fix_all=1">⚡ Fix All (همه)</a>
            <a class="btn btn-success" href="index.php">← بازگشت به پنل</a>
        </div>

        <h3>نتایج بررسی:</h3>
        <?php foreach ($results as $r): ?>
            <div class="result <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
        <?php endforeach; ?>

        <h3 style="margin-top:20px;">📋 دستورالعمل دستی (اگر auto-fix کار نکرد):</h3>
        <div style="font-size:12px; line-height:1.8; color:#cbd5e1;">
            1. وارد cPanel → File Manager → <code>public_html/contax/</code> شوید<br>
            2. اگر پوشه <code>connectix-panel</code> داخلش وجود دارد → آن را Rename کنید به <code>connectix-panel-BACKUP</code> یا Delete کنید<br>
            3. پوشه <code>data/</code> را باز کنید → اگر فایل <code>enable_heavy_bootstrap</code> یا <code>enable_autheal</code> وجود دارد → Delete کنید<br>
            4. cPanel → Disk Usage را چک کنید → اگر 100% پر است → لاگ‌ها یا بک‌آپ‌های قدیمی را پاک کنید<br>
            5. cPanel → Select PHP Version → اگر PHP-FPM هنگ کرده → Switch به PHP 8.1 و دوباره به 8.2<br>
            6. اگر باز هم بالا نیامد → فایل <code>index.php</code> بهینه شده از بسته <code>deploy_20260929</code> را آپلود کنید<br>
            7. برای بازگشت کامل: <a href="rollback_20260929.php" style="color:#a78bfa;">rollback_20260929.php</a>
        </div>

        <h3 style="margin-top:20px;">🔍 اطلاعات سرور:</h3>
        <pre>Base: <?= htmlspecialchars($baseDir) ?>
PHP: <?= PHP_VERSION ?> | SAPI: <?= php_sapi_name() ?>
Time: <?= date('Y-m-d H:i:s') ?>
Free Disk: <?= round(disk_free_space($baseDir)/1024/1024,1) ?> MB
Nested Exists: <?= is_dir($nested) ? 'YES' : 'NO' ?>
Index Size: <?= file_exists($baseDir.'/index.php') ? filesize($baseDir.'/index.php').' bytes' : 'NOT FOUND' ?>
</pre>
    </div>
</body>
</html>

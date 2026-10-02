<?php
// ULTRA TINY DISK FIX - 2KB - No dependencies, frees space immediately
// Run: https://vpbotn.ir/contax/fix_disk_now.php?key=fix_disk_2026
header('Content-Type: text/html; charset=utf-8');
$key = $_GET['key'] ?? '';
if ($key !== 'fix_disk_2026' && $key !== 'CONNECTIX2026' && $key !== 'diag_409') {
    die("Unauthorized - use ?key=fix_disk_2026");
}

echo "<h2>🧹 Emergency Disk Cleanup v6.8.4</h2>";
echo "<p>Time: ".date('Y-m-d H:i:s')."</p>";

$freed = 0;
$deleted = [];

function delTree($dir) {
    global $freed;
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $path = "$dir/$f";
        if (is_dir($path)) delTree($path);
        else {
            $size = @filesize($path) ?: 0;
            if (@unlink($path)) $freed += $size;
        }
    }
    @rmdir($dir);
}

// 1. Clean temp
foreach ([sys_get_temp_dir(), '/tmp', __DIR__.'/data'] as $tmpDir) {
    if (!is_dir($tmpDir)) continue;
    foreach (['cx_*', 'connectix_*', 'repair_*', 'tmp_*'] as $pat) {
        foreach (glob($tmpDir.'/'.$pat) as $f) {
            if (is_file($f)) {
                $size = @filesize($f) ?: 0;
                if (@unlink($f)) {
                    $freed += $size;
                    $deleted[] = basename($f)." (".round($size/1024)."KB)";
                }
            }
        }
    }
}

// 2. Clean backups
$contax = __DIR__;
$patterns = [
    $contax.'/*.old.*',
    $contax.'/*.bak_*',
    $contax.'/index_backup_*.php',
    $contax.'/force_update_*.php.bak_*',
    $contax.'/__canary_*.txt',
    $contax.'/.opcache_reset_done_*',
    $contax.'/data/*.log',
    dirname($contax).'/index_backup_*.php'
];

foreach ($patterns as $pat) {
    foreach (glob($pat) as $f) {
        if (is_file($f)) {
            $size = @filesize($f) ?: 0;
            if (@unlink($f)) {
                $freed += $size;
                $deleted[] = basename($f)." (".round($size/1024)."KB)";
            }
        }
    }
}

// 3. Rollback dir (can be 10MB+)
$rollback = $contax.'/.rollback_backup_20261002';
if (is_dir($rollback)) {
    $sizeBefore = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rollback, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) { $sizeBefore += $file->getSize(); }
    delTree($rollback);
    $freed += $sizeBefore;
    $deleted[] = ".rollback_backup_20261002 (".round($sizeBefore/1024/1024,1)."MB)";
}

// 4. Keep only latest 2 force_update
$forceFiles = glob($contax.'/force_update_*.php');
if (count($forceFiles) > 2) {
    usort($forceFiles, function($a,$b){return filemtime($b)-filemtime($a);});
    foreach (array_slice($forceFiles,2) as $f) {
        $size = @filesize($f) ?: 0;
        if (@unlink($f)) {
            $freed += $size;
            $deleted[] = basename($f)." (old, ".round($size/1024)."KB)";
        }
    }
}

// 5. Clear DB cache
try {
    require __DIR__.'/config.php';
    require __DIR__.'/core/Database.php';
    require __DIR__.'/core/Setting.php';
    $pdo = Database::getConnection();
    $pdo->exec("DELETE FROM system_settings WHERE setting_key IN ('update_check_cache','update_check_time')");
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
    Setting::set('current_version','6.8.4');
    echo "<p>✅ DB cache cleared, version set to 6.8.4</p>";
} catch (Throwable $e) {
    echo "<p>⚠️ DB: ".$e->getMessage()."</p>";
}

echo "<h3>✅ Freed: ".round($freed/1024/1024,2)." MB</h3>";
echo "<ul>";
foreach ($deleted as $d) echo "<li>$d</li>";
echo "</ul>";
echo "<p>Free space now: ".round(disk_free_space(__DIR__)/1024/1024,2)." MB</p>";

if (function_exists('opcache_reset')) @opcache_reset();

echo "<hr><p><a href='quick_update.php' style='background:#7c3aed;color:white;padding:10px 20px;border-radius:8px;text-decoration:none'>🚀 حالا quick_update.php را اجرا کنید</a></p>";
echo "<p><a href='updater'>رفتن به مرکز بروزرسانی</a></p>";

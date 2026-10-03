<?php
/**
 * OPcache Self-Healing Prepend + Emergency Disk Cleanup + Session Fix (v6.8.5)
 * Runs before EVERY PHP script in this directory, in EVERY PHP-FPM pool.
 * - After each deployment: resets OPcache once per pool
 * - When disk free < 50MB: auto-cleanup old backups and temp files
 * - Fixes session save_path for cPanel (ea-php84 missing dir)
 */

// === SESSION FIX v6.8.5 - Fix cPanel session path ===
$__sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($__sessionDir)) {
    @mkdir($__sessionDir, 0755, true);
}
if (is_dir($__sessionDir) && is_writable($__sessionDir)) {
    // Only set if default path is not writable or missing
    $__defaultPath = ini_get('session.save_path');
    if (empty($__defaultPath) || !is_dir($__defaultPath) || !is_writable($__defaultPath)) {
        @ini_set('session.save_path', $__sessionDir);
    } else {
        // Check if default path exists, if not, use our own
        if (!@is_dir($__defaultPath)) {
            @ini_set('session.save_path', $__sessionDir);
        }
    }
    // Also check for ea-php84 specific issue
    if (strpos(ini_get('session.save_path'), 'ea-php84') !== false && !is_dir(ini_get('session.save_path'))) {
        @ini_set('session.save_path', $__sessionDir);
    }
}
unset($__sessionDir, $__defaultPath);

$__stampFile = __DIR__ . '/.deploy_stamp';
if (is_file($__stampFile)) {
    $__stampM = (int)@filemtime($__stampFile);
    if ($__stampM > 0 && function_exists('opcache_reset')) {
        $__doneMarker = __DIR__ . '/.opcache_reset_done_' . $__stampM;
        if (!is_file($__doneMarker)) {
            @opcache_reset();
            if (function_exists('clearstatcache')) {
                @clearstatcache(true);
            }
            @file_put_contents($__doneMarker, (string)$__stampM, LOCK_EX);
        }
        // Housekeeping: drop markers older than the current stamp (keep last 3)
        $__old = glob(__DIR__ . '/.opcache_reset_done_*') ?: [];
        if (count($__old) > 3) {
            usort($__old, function ($a, $b) {
                return (int)substr(basename($b), 20) <=> (int)substr(basename($a), 20);
            });
            foreach (array_slice($__old, 3) as $__f) {
                @unlink($__f);
            }
        }
    }
}

// === EMERGENCY DISK CLEANUP v6.8.4 - Auto cleanup when disk low ===
$__freeSpace = @disk_free_space(__DIR__);
if ($__freeSpace !== false && $__freeSpace < 50*1024*1024) { // Less than 50MB free
    // Clean temp files
    foreach ([sys_get_temp_dir(), '/tmp'] as $__tmpDir) {
        if (!is_dir($__tmpDir)) continue;
        foreach (glob($__tmpDir . '/cx_*') as $__f) { if (is_file($__f)) @unlink($__f); }
        foreach (glob($__tmpDir . '/connectix_*') as $__f) { if (is_file($__f)) @unlink($__f); }
    }
    // Clean old backups
    foreach (glob(__DIR__ . '/*.old.*') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/*.bak_*') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/index_backup_*.php') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/__canary_*.txt') as $__f) { @unlink($__f); }
    // Clean rollback dir if large
    $__rollback = __DIR__ . '/.rollback_backup_20261002';
    if (is_dir($__rollback)) {
        $__it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($__rollback, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($__it as $__file) {
            if ($__file->isFile()) @unlink($__file->getPathname());
            else @rmdir($__file->getPathname());
        }
        @rmdir($__rollback);
    }
}
unset($__stampFile, $__stampM, $__doneMarker, $__old, $__f, $__freeSpace, $__tmpDir, $__rollback, $__it, $__file);

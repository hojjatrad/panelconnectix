<?php
/**
 * OPcache Self-Healing Prepend + Emergency Disk Cleanup + Session Fix (v6.8.6 Final)
 * Runs before EVERY PHP script in this directory, in EVERY PHP-FPM pool.
 * - Fixes session save_path for cPanel (ea-php84 missing dir)
 * - Ensures data/sessions and data/tmp exist and are writable
 * - After each deployment: resets OPcache once per pool
 * - When disk free < 50MB: auto-cleanup old backups and temp files
 */

// === SESSION FIX v6.8.6 - Fix cPanel session path (critical for login) ===
$__sessionDir = __DIR__ . '/data/sessions';
$__tmpDir = __DIR__ . '/data/tmp';
if (!is_dir($__sessionDir)) {
    @mkdir($__sessionDir, 0755, true);
}
if (!is_dir($__tmpDir)) {
    @mkdir($__tmpDir, 0755, true);
}
if (is_dir($__sessionDir) && is_writable($__sessionDir)) {
    $__defaultPath = ini_get('session.save_path');
    if (empty($__defaultPath) || !is_dir($__defaultPath) || !is_writable($__defaultPath)) {
        @ini_set('session.save_path', $__sessionDir);
    } else {
        if (!@is_dir($__defaultPath)) {
            @ini_set('session.save_path', $__sessionDir);
        }
    }
    if (strpos(ini_get('session.save_path'), 'ea-php84') !== false && !is_dir(ini_get('session.save_path'))) {
        @ini_set('session.save_path', $__sessionDir);
    }
}
unset($__sessionDir, $__tmpDir, $__defaultPath);

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

// === EMERGENCY DISK CLEANUP v6.8.6 ===
$__freeSpace = @disk_free_space(__DIR__);
if ($__freeSpace !== false && $__freeSpace < 50*1024*1024) {
    foreach ([sys_get_temp_dir(), '/tmp'] as $__tmpDir) {
        if (!is_dir($__tmpDir)) continue;
        foreach (glob($__tmpDir . '/cx_*') as $__f) { if (is_file($__f)) @unlink($__f); }
        foreach (glob($__tmpDir . '/connectix_*') as $__f) { if (is_file($__f)) @unlink($__f); }
    }
    foreach (glob(__DIR__ . '/*.old.*') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/*.bak_*') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/index_backup_*.php') as $__f) { @unlink($__f); }
    foreach (glob(__DIR__ . '/__canary_*.txt') as $__f) { @unlink($__f); }
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

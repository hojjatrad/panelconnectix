<?php
/**
 * OPcache Self-Healing Prepend + Emergency Disk Cleanup + Session Fix (v6.8.7 FINAL)
 * Bulletproof session fix - critical for login on cPanel ea-php84
 */

$__sessionDir = __DIR__ . '/data/sessions';
$__tmpDir = __DIR__ . '/data/tmp';
$__cacheDir = __DIR__ . '/cache/ratelimit';
foreach ([$__sessionDir, $__tmpDir, $__cacheDir, __DIR__.'/data', __DIR__.'/cache'] as $__d) {
    if (!is_dir($__d)) { @mkdir($__d, 0755, true); }
}
if (is_dir($__sessionDir) && is_writable($__sessionDir)) {
    $__cur = ini_get('session.save_path');
    $__need = false;
    if (empty($__cur)) $__need = true;
    elseif (strpos($__cur, 'ea-php84') !== false) $__need = true;
    elseif (!@is_dir($__cur)) $__need = true;
    elseif (!@is_writable($__cur)) $__need = true;
    elseif ($__cur === '/tmp' || $__cur === sys_get_temp_dir()) $__need = true;
    if ($__need) { @ini_set('session.save_path', $__sessionDir); }
}
unset($__sessionDir, $__tmpDir, $__cacheDir, $__d, $__cur, $__need);

$__stampFile = __DIR__ . '/.deploy_stamp';
if (is_file($__stampFile)) {
    $__stampM = (int)@filemtime($__stampFile);
    if ($__stampM > 0 && function_exists('opcache_reset')) {
        $__doneMarker = __DIR__ . '/.opcache_reset_done_' . $__stampM;
        if (!is_file($__doneMarker)) {
            @opcache_reset();
            @clearstatcache(true);
            @file_put_contents($__doneMarker, (string)$__stampM, LOCK_EX);
        }
        $__old = glob(__DIR__ . '/.opcache_reset_done_*') ?: [];
        if (count($__old) > 3) {
            usort($__old, function ($a, $b) { return (int)substr(basename($b), 20) <=> (int)substr(basename($a), 20); });
            foreach (array_slice($__old, 3) as $__f) { @unlink($__f); }
        }
    }
}
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
}
unset($__stampFile, $__stampM, $__doneMarker, $__old, $__f, $__freeSpace, $__tmpDir);

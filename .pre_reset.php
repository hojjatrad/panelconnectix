<?php
/**
 * OPcache Self-Healing Prepend (runs before EVERY PHP script in this directory,
 * in EVERY PHP-FPM pool that serves it — including pools with frozen OPcache).
 *
 * After each deployment the updater touches .deploy_stamp. The first request
 * that reaches a given pool after a deployment resets that pool's OPcache
 * exactly once, forcing a recompile of ALL scripts from the fresh on-disk
 * files. Subsequent requests in the same pool skip the reset (marker file).
 * Cost: one tiny file check per request; one cache reset per pool per deploy.
 */

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
unset($__stampFile, $__stampM, $__doneMarker, $__old, $__f);

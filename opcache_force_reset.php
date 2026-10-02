<?php
// Force opcache reset across all pools
if (function_exists('opcache_reset')) { opcache_reset(); echo "opcache_reset done\n"; }
if (function_exists('clearstatcache')) { clearstatcache(true); echo "clearstatcache done\n"; }
$stamp = __DIR__ . '/.deploy_stamp';
@touch($stamp);
echo "touched $stamp mtime=".date('Y-m-d H:i:s', filemtime($stamp))."\n";
// delete old markers
foreach (glob(__DIR__ . '/.opcache_reset_done_*') as $f) { @unlink($f); echo "deleted $f\n"; }
// also try to delete opcache files
echo "---\n";
echo file_get_contents(__DIR__ . '/set_app_version_361.php');
echo "\n---\n";
echo "Now try include:\n";
include __DIR__ . '/set_app_version_361.php';

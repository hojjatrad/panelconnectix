<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dir = __DIR__;
$dirsFixed = 0;
$filesFixed = 0;

@chmod($dir, 0755);

try {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            @chmod($path, 0755);
            $dirsFixed++;
        } else {
            @chmod($path, 0666);
            $filesFixed++;
        }
    }
} catch (Throwable $e) {}

// Also clear opcache
if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);

echo json_encode([
    'success' => true,
    'dirs' => $dirsFixed,
    'files' => $filesFixed
]);

<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dir = __DIR__;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$matches = [];

foreach ($files as $file) {
    if ($file->isDir()) continue;
    $ext = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
    if (!in_array($ext, ['php', 'sql', 'json', 'sqlite'])) continue;
    $content = @file_get_contents($file->getPathname());
    if ($content && (str_contains($content, 'mci_reality') || str_contains($content, 'mock_pbk'))) {
        $matches[] = [
            'file' => str_replace($dir, '', $file->getPathname()),
            'size' => strlen($content),
            'mtime' => date('Y-m-d H:i:s', $file->getMTime())
        ];
    }
}

echo json_encode([
    'matches' => $matches,
    'scanned_dir' => $dir
], JSON_PRETTY_PRINT);

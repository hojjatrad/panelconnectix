<?php
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die('Unauthorized');
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Optimization.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== O4: Adding Performance Indexes ===\n";
$res = Optimization::addPerformanceIndexes();
foreach ($res['messages'] as $msg) {
    echo $msg . "\n";
}
echo "\nDONE\n";

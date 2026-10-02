<?php
// O8: Optimized Cron - Single file for all tasks, run every 5 minutes
// Usage: Add to cPanel cron: php {PANEL_DIR}/cron/optimized_cron.php >> /dev/null 2>&1

define('CONNECTIX_CRON', true);
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/CronOptimizer.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Connectix Optimized Cron - " . date('Y-m-d H:i:s') . " ===\n";

$results = CronOptimizer::runAll();

foreach ($results as $key => $value) {
    if (is_array($value)) {
        echo "$key: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "$key: $value\n";
    }
}

echo "=== Done ===\n";

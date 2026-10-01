<?php
// S5: Cleanup debug files on host - removes dangerous debug files
define('CONNECTIX_NO_DIE', true);
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die('Unauthorized');

$panelRoot = __DIR__;
$deleted = [];
$patterns = [
    'debug_*.php',
    'check_*.php',
    'live_fix_*.php',
    'fix_*.php',
    'fix_direct.php',
    'server_debug.php',
    'seller_api_debug*.php',
    'webhook_debug.php',
    'webhook_verbose_test.php',
    'bot_test_*.php',
    'bot_force_join_fix.php',
    'bot_full_audit.php',
    'telegram_webhook_fix.php',
    'settings_fix.php',
    'emergency_fix.php',
    'rollback_*.php',
    'apply_*.php',
    'auto_fix_*.php',
    'app_version_fix.php',
    'client-app/lib/services/api_service.dart.bak.*',
    'client-app/lib/services/api_service.dart.new',
    '__canary_*.txt',
    '.deploy_stamp'
];

foreach ($patterns as $pattern) {
    $files = glob($panelRoot . '/' . $pattern);
    $files2 = glob($panelRoot . '/**/' . $pattern, GLOB_BRACE);
    $all = array_merge($files ?: [], $files2 ?: []);
    foreach ($all as $file) {
        if (is_file($file) && strpos($file, 'cleanup_host.php') === false) {
            $size = filesize($file);
            if (@unlink($file)) {
                $deleted[] = str_replace($panelRoot.'/', '', $file) . " ($size bytes)";
            }
        }
    }
}

// Also check root subdirectories
$extraDirs = ['views/demo', 'backups'];
foreach ($extraDirs as $dir) {
    $path = $panelRoot . '/' . $dir;
    if (is_dir($path)) {
        // Keep directory but list files
        $files = glob($path . '/*');
        foreach ($files as $f) {
            if (is_file($f)) {
                $deleted[] = "FOUND (not deleted, manual check needed): " . str_replace($panelRoot.'/', '', $f);
            }
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Cleanup Debug Files (S5) ===\n";
echo "Deleted " . count($deleted) . " files:\n";
foreach ($deleted as $d) {
    echo "- $d\n";
}
echo "\nDONE - Security improved\n";

// Also remove cleanup file itself after 1 use? No, keep for now

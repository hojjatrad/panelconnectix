<?php
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die('Unauthorized');
$root = __DIR__;
$patterns = [
    'debug_*.php','check_*.php','live_fix*.php','fix_*.php','cleanup_host.php','final_cleanup.php',
    'server_debug.php','seller_api_debug*.php','webhook_debug.php','webhook_verbose*.php',
    'bot_test_*.php','bot_force_join_fix.php','bot_full_audit.php','telegram_webhook_fix.php',
    'settings_fix.php','emergency_fix.php','rollback_*.php','apply_*.php','auto_fix_*.php','app_version_fix.php',
    '__canary_*.txt','.deploy_stamp','client-app/lib/services/api_service.dart.bak.*','client-app/lib/services/api_service.dart.new'
];
$deleted = 0;
foreach ($patterns as $pat) {
    foreach (glob($root.'/'.$pat) as $f) {
        if (is_file($f)) { @unlink($f); $deleted++; }
    }
    foreach (glob($root.'/*/'.$pat) as $f) {
        if (is_file($f)) { @unlink($f); $deleted++; }
    }
    foreach (glob($root.'/*/*/'.$pat) as $f) {
        if (is_file($f)) { @unlink($f); $deleted++; }
    }
}
// Clean opt_patch and views/demo if exists
foreach (['opt_patch','views/demo','backups'] as $dir) {
    $p = $root.'/'.$dir;
    if (is_dir($p)) {
        $files = glob($p.'/*');
        foreach ($files as $f) if (is_file($f)) { @unlink($f); $deleted++; }
        @rmdir($p);
    }
}
echo "Deleted $deleted files - security cleanup done";

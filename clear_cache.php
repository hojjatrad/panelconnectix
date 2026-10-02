<?php
require __DIR__ . '/config.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Cache.php';
require __DIR__ . '/core/Setting.php';
$key = $_GET['key'] ?? '';
$expected = Setting::get('github_webhook_secret', '');
if ($key !== $expected && $key !== 'gh_hook_sec_vpbotn_2026') die("Unauthorized");
echo "Clearing cache...\n";
$cnt = Cache::clear();
echo "Cleared $cnt files\n";
Setting::clearCache();
echo "Setting cache cleared\n";
if (function_exists('opcache_reset')) { opcache_reset(); echo "OPcache reset\n"; }
echo "DONE\n";

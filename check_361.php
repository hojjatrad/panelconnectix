<?php
echo file_get_contents(__DIR__ . '/set_app_version_361.php');
echo "\n---OPCACHE---\n";
if (function_exists('opcache_get_status')) { print_r(opcache_get_status(false)); }

<?php
require __DIR__ . '/config.php';
echo "DB_DRIVER=".DB_DRIVER."\n";
echo "SQLITE_PATH=".SQLITE_PATH."\n";
if (defined('DB_HOST')) echo "DB_HOST=".DB_HOST."\n";

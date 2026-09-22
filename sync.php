<?php
/**
 * Public Root Cron Job Entry Point
 * Usage:
 * CLI: php /path/to/contax/sync.php
 * Web / cURL: curl -s -k "https://yourdomain.com/contax/sync.php?secret=YOUR_APP_SECRET"
 */
require_once __DIR__ . '/cron/sync.php';

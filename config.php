<?php
/**
 * Auto-Generated Configuration by Easy Installer
 * Date: 2026-09-21 16:32:43
 */

define('APP_NAME', 'نوین نت پرو');
define('APP_ENV', 'production');
define('APP_URL', 'http://127.0.0.1:8000');

// Database Configuration
define('DB_DRIVER', 'sqlite');
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('SQLITE_PATH', __DIR__ . '/data/panel.sqlite');

// Application Encryption Secret
define('APP_SECRET', '0b17bce475b83aa3b154c7311f28ea8e76e51b874b5cf05b');

// Telegram Bot Integration
define('TELEGRAM_BOT_TOKEN', '123456:FAKE');
define('TELEGRAM_ADMIN_CHAT_ID', '987654321');

date_default_timezone_set('Asia/Tehran');
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

<?php
/**
 * Auto-Generated Configuration by Easy Installer
 * Date: 2026-09-21 16:32:43
 * Updated: 2026-10-02 - Domain independent dynamic version
 */

/**
 * Optional local secrets override (kept OUT of git — see .gitignore).
 * Create config.secrets.php returning an array; any key present here
 * overrides the value defined below (e.g. APP_SECRET, DB_PASS).
 */
$__connectix_secrets = is_file(__DIR__ . '/config.secrets.php') ? (array)require __DIR__ . '/config.secrets.php' : [];
function __connectix_secret(string $key, $fallback) {
    global $__connectix_secrets;
    return isset($__connectix_secrets[$key]) && $__connectix_secrets[$key] !== '' ? $__connectix_secrets[$key] : $fallback;
}

// Dynamic BASE_PATH detection — works regardless of installation directory
if (!defined('BASE_PATH')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
    // Allow override via env
    if (!empty($_ENV['PANEL_BASE_PATH'])) {
        $basePath = rtrim($_ENV['PANEL_BASE_PATH'], '/');
    }
    define('BASE_PATH', $basePath);
}

// Dynamic APP_URL — no hardcoded 127.0.0.1
if (!function_exists('__connectix_detect_app_url')) {
    function __connectix_detect_app_url(): string {
        // If secrets has APP_URL, use it unless it's the default 127.0.0.1
        global $__connectix_secrets;
        $secretUrl = $__connectix_secrets['APP_URL'] ?? '';
        if (!empty($secretUrl) && $secretUrl !== 'http://127.0.0.1:8000') {
            return rtrim($secretUrl, '/');
        }
        // Try to detect from current request
        if (!empty($_SERVER['HTTP_HOST'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            return rtrim($proto . $_SERVER['HTTP_HOST'] . $base, '/');
        }
        // Fallback to env or localhost
        if (!empty($_SERVER['SERVER_NAME'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            return rtrim($proto . $_SERVER['SERVER_NAME'] . $base, '/');
        }
        return 'http://localhost' . (defined('BASE_PATH') ? BASE_PATH : '');
    }
}

define('APP_NAME', __connectix_secret('APP_NAME', 'نوین نت پرو'));
define('APP_ENV', __connectix_secret('APP_ENV', 'production'));
define('APP_URL', __connectix_secret('APP_URL', __connectix_detect_app_url()));

// Database Configuration
define('DB_DRIVER', __connectix_secret('DB_DRIVER', 'sqlite'));
define('DB_HOST', __connectix_secret('DB_HOST', 'localhost'));
define('DB_PORT', __connectix_secret('DB_PORT', '3306'));
define('DB_NAME', __connectix_secret('DB_NAME', ''));
define('DB_USER', __connectix_secret('DB_USER', ''));
define('DB_PASS', __connectix_secret('DB_PASS', ''));
define('SQLITE_PATH', __connectix_secret('SQLITE_PATH', __DIR__ . '/data/panel.sqlite'));

// Application Encryption Secret - generate random if not set
$__default_secret = '0b17bce475b83aa3b154c7311f28ea8e76e51b874b5cf05b';
$__app_secret = __connectix_secret('APP_SECRET', $__default_secret);
// If still default and secrets file doesn't exist, try to generate one
if ($__app_secret === $__default_secret && empty($__connectix_secrets['APP_SECRET']) && !empty($_SERVER['HTTP_HOST'])) {
    // Keep default for now, but install.php should generate random
}
define('APP_SECRET', $__app_secret);

// Telegram Bot Integration
define('TELEGRAM_BOT_TOKEN', __connectix_secret('TELEGRAM_BOT_TOKEN', '123456:FAKE'));
define('TELEGRAM_ADMIN_CHAT_ID', __connectix_secret('TELEGRAM_ADMIN_CHAT_ID', '987654321'));

date_default_timezone_set('Asia/Tehran');
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', __connectix_secret('APP_DEBUG', true));
}
// v6.8.9 CRITICAL: For AJAX updater, FORCE silence even if APP_DEBUG=true to prevent <br> before JSON
$isAjaxUpdaterCfg = (defined('IS_AJAX_UPDATER') && IS_AJAX_UPDATER) || str_contains($_SERVER['REQUEST_URI'] ?? '', 'updater/ajax-apply') || ($_GET['route'] ?? '') === 'updater/ajax-apply';
if ($isAjaxUpdaterCfg) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    while (ob_get_level() > 0) { @ob_end_clean(); }
} elseif (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Panel domain settings — dynamic, no hardcoded vpbotn.ir
if (!defined('PANEL_DOMAIN')) {
    $panelDomain = __connectix_secret('PANEL_DOMAIN', '');
    if (empty($panelDomain) && !empty($_SERVER['HTTP_HOST'])) {
        $panelDomain = $_SERVER['HTTP_HOST'];
    }
    define('PANEL_DOMAIN', $panelDomain ?: 'localhost');
}

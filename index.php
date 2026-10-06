<?php
// v6.8.9 CRITICAL: Detect AJAX updater BEFORE any output - force silent mode
$__isAjaxUpdater = false;
$__reqUri = $_SERVER['REQUEST_URI'] ?? '';
$__routeParam = $_GET['route'] ?? '';
if (str_contains($__reqUri, 'updater/ajax-apply') || $__routeParam === 'updater/ajax-apply' || str_contains($__reqUri, 'updater%2Fajax-apply')) {
    $__isAjaxUpdater = true;
}
if ($__isAjaxUpdater) {
    // ABSOLUTE SILENCE - never output <br> warnings before JSON
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    // Clean any pre-existing output buffers
    while (ob_get_level() > 0) { @ob_end_clean(); }
    ob_start();
    define('IS_AJAX_UPDATER', true);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 1);
    define('IS_AJAX_UPDATER', false);
}

// v6.8.7 FIX: Bulletproof session path - MUST be before any session_start
$__sessFix = __DIR__ . '/data/sessions';
$__tmpFix = __DIR__ . '/data/tmp';
if (!is_dir($__sessFix)) { @mkdir($__sessFix, 0755, true); }
if (!is_dir($__tmpFix)) { @mkdir($__tmpFix, 0755, true); }
if (is_dir($__sessFix) && is_writable($__sessFix)) {
    $__curPath = ini_get('session.save_path');
    // Always force our path if current is empty, not writable, contains ea-php84, or is /tmp
    $__needFix = false;
    if (empty($__curPath)) $__needFix = true;
    elseif (strpos($__curPath, 'ea-php84') !== false) $__needFix = true;
    elseif (!is_dir($__curPath)) $__needFix = true;
    elseif (!is_writable($__curPath)) $__needFix = true;
    elseif ($__curPath === '/tmp' || $__curPath === sys_get_temp_dir()) $__needFix = true;
    if ($__needFix) {
        @ini_set('session.save_path', $__sessFix);
    }
}
unset($__sessFix, $__tmpFix, $__curPath, $__needFix);

// Safely start session before any output
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// v3.5.8 SAFE: Heavy nested folder relocation DISABLED on every request for performance
// Original code caused 2-3s delay on every page load (RecursiveIteratorIterator)
// Now only runs if explicitly enabled via data/enable_heavy_bootstrap flag or ?force_bootstrap=1
// To revert: create file data/enable_heavy_bootstrap or restore from backups/20260929-panel-optimizations/
// To rollback completely: cp backups/20260929-panel-optimizations/index.php.backup index.php
$subfolder = __DIR__ . '/connectix-panel';
$enableHeavyBootstrap = file_exists(__DIR__ . '/data/enable_heavy_bootstrap') || isset($_GET['force_bootstrap']);
if ($enableHeavyBootstrap && is_dir($subfolder) && file_exists($subfolder . '/index.php')) {
    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($subfolder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $target = __DIR__ . DIRECTORY_SEPARATOR . $iter->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0755, true);
            } else {
                if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
                @copy($item->getPathname(), $target);
                @chmod($target, 0644);
            }
        }
    } catch (Throwable $e) {}
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

// Direct install or repair route handling
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
// v6.8.5 FIX: Path-independent routing - strip basePath from requestPath for consistent routing
$basePathForRouting = $basePath;
if (!empty($basePathForRouting) && str_starts_with($requestPath, $basePathForRouting)) {
    $requestPath = substr($requestPath, strlen($basePathForRouting));
    if (empty($requestPath)) $requestPath = '/';
}
$routeParam = $_GET['route'] ?? '';

if (str_ends_with($requestPath, 'install.php') || str_ends_with($requestPath, '/install') || $routeParam === 'install.php' || $routeParam === 'install') {
    require_once __DIR__ . '/install.php';
    exit;
}

if (str_ends_with($requestPath, 'repair.php') || str_ends_with($requestPath, '/repair') || $routeParam === 'repair.php' || $routeParam === 'repair') {
    require_once __DIR__ . '/repair.php';
    exit;
}

if (str_ends_with($requestPath, 'purge_all.php') || str_ends_with($requestPath, '/purge_all') || $routeParam === 'purge_all.php' || $routeParam === 'purge_all') {
    require_once __DIR__ . '/purge_all.php';
    exit;
}

if (str_ends_with($requestPath, 'fix_now.php') || str_ends_with($requestPath, '/fix_now') || $routeParam === 'fix_now.php' || $routeParam === 'fix_now') {
    require_once __DIR__ . '/fix_now.php';
    exit;
}

if (str_ends_with($requestPath, 'quick_update.php') || str_ends_with($requestPath, '/quick_update') || $routeParam === 'quick_update.php' || $routeParam === 'quick_update') {
    if (file_exists(__DIR__ . '/quick_update.php')) {
        require_once __DIR__ . '/quick_update.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/quick_update.php')) {
        require_once __DIR__ . '/connectix-panel/quick_update.php';
    } else {
        echo "<h3 dir='rtl' style='font-family:sans-serif;color:#ef4444;text-align:center;margin-top:50px;'>فایل quick_update.php در مسیر سرور یافت نشد. لطفاً ابتدا فایل cpanel_fix.php یا repair.php را اجرا فرمایید.</h3>";
    }
    exit;
}

if (str_ends_with($requestPath, 'update_to_4.0.18.php') || str_ends_with($requestPath, '/update_to_4.0.18') || $routeParam === 'update_to_4.0.18.php' || $routeParam === 'update_to_4.0.18') {
    if (file_exists(__DIR__ . '/update_to_4.0.18.php')) {
        require_once __DIR__ . '/update_to_4.0.18.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/update_to_4.0.18.php')) {
        require_once __DIR__ . '/connectix-panel/update_to_4.0.18.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'update_apks_to_4.0.18.php') || str_ends_with($requestPath, '/update_apks_to_4.0.18') || $routeParam === 'update_apks_to_4.0.18.php' || $routeParam === 'update_apks_to_4.0.18') {
    if (file_exists(__DIR__ . '/update_apks_to_4.0.18.php')) {
        require_once __DIR__ . '/update_apks_to_4.0.18.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/update_apks_to_4.0.18.php')) {
        require_once __DIR__ . '/connectix-panel/update_apks_to_4.0.18.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'update_apks_to_4.0.19.php') || str_ends_with($requestPath, '/update_apks_to_4.0.19') || $routeParam === 'update_apks_to_4.0.19.php' || $routeParam === 'update_apks_to_4.0.19') {
    if (file_exists(__DIR__ . '/update_apks_to_4.0.19.php')) {
        require_once __DIR__ . '/update_apks_to_4.0.19.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/update_apks_to_4.0.19.php')) {
        require_once __DIR__ . '/connectix-panel/update_apks_to_4.0.19.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'browser_update.php') || $routeParam === 'browser_update.php' || $routeParam === 'browser_update') {
    if (file_exists(__DIR__ . '/browser_update.php')) {
        require_once __DIR__ . '/browser_update.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'check_crash_log.php') || $routeParam === 'check_crash_log.php' || $routeParam === 'check_crash_log') {
    if (file_exists(__DIR__ . '/check_crash_log.php')) {
        require_once __DIR__ . '/check_crash_log.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'admin_magic_login.php') || $routeParam === 'admin_magic_login.php' || $routeParam === 'admin_magic_login') {
    if (file_exists(__DIR__ . '/admin_magic_login.php')) {
        require_once __DIR__ . '/admin_magic_login.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'fix_deep_admin_crash_v4_0_19.php') || $routeParam === 'fix_deep_admin_crash_v4_0_19.php' || $routeParam === 'fix_deep_admin_crash_v4_0_19') {
    if (file_exists(__DIR__ . '/fix_deep_admin_crash_v4_0_19.php')) {
        require_once __DIR__ . '/fix_deep_admin_crash_v4_0_19.php';
    }
    exit;
}

if (str_ends_with($requestPath, 'fix_proxy_and_admin_v4_0_19.php') || $routeParam === 'fix_proxy_and_admin_v4_0_19.php' || $routeParam === 'fix_proxy_and_admin_v4_0_19') {
    if (file_exists(__DIR__ . '/fix_proxy_and_admin_v4_0_19.php')) {
        require_once __DIR__ . '/fix_proxy_and_admin_v4_0_19.php';
    }
    exit;
}

// v6.8.32 Auto fix all
if (str_ends_with($requestPath, 'auto_fix_all.php') || str_ends_with($requestPath, '/auto_fix_all') || $routeParam === 'auto_fix_all.php' || $routeParam === 'auto_fix_all') {
    if (file_exists(__DIR__ . '/auto_fix_all.php')) {
        require_once __DIR__ . '/auto_fix_all.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/auto_fix_all.php')) {
        require_once __DIR__ . '/connectix-panel/auto_fix_all.php';
    } else {
        echo "<h3>فایل auto_fix_all.php یافت نشد</h3>";
    }
    exit;
}

// v6.8.30 Cloudflare repair tool
if (str_ends_with($requestPath, 'repair_cloudflare.php') || str_ends_with($requestPath, '/repair_cloudflare') || $routeParam === 'repair_cloudflare.php' || $routeParam === 'repair_cloudflare') {
    if (file_exists(__DIR__ . '/repair_cloudflare.php')) {
        require_once __DIR__ . '/repair_cloudflare.php';
    } elseif (file_exists(__DIR__ . '/connectix-panel/repair_cloudflare.php')) {
        require_once __DIR__ . '/connectix-panel/repair_cloudflare.php';
    } else {
        echo "<h3 dir='rtl' style='font-family:sans-serif;color:#ef4444;text-align:center;margin-top:50px;'>فایل repair_cloudflare.php یافت نشد. لطفاً پنل را به v6.8.30 آپدیت کنید.</h3>";
    }
    exit;
}

// Intercept Telegram Webhook on ANY variation
if (str_ends_with($requestPath, 'webhook.php') || str_ends_with($requestPath, '/webhook') 
    || $routeParam === 'webhook.php' || $routeParam === 'webhook' || $routeParam === 'telegram/webhook') {
    require_once __DIR__ . '/webhook.php';
    exit;
}

if (!str_contains($requestPath, 'servers/') && !str_contains($requestPath, 'node-users/') && 
    (str_contains($requestPath, 'cron/sync.php') || str_contains($requestPath, 'sync.php') || str_ends_with($requestPath, '/cron/sync') || $requestPath === '/sync' || str_ends_with($requestPath, 'contax/sync') || $routeParam === 'cron/sync.php' || $routeParam === 'cron/sync' || $routeParam === 'sync')) {
    require_once __DIR__ . '/cron/sync.php';
    exit;
}

// 1. Dynamic autoloader for core, controllers, and drivers
spl_autoload_register(function ($class) {
    $searchPaths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/controllers/' . $class . '.php',
        __DIR__ . '/drivers/' . $class . '.php'
    ];
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
});

// 2. Load Core Components Safely
$coreComponents = [
    'config.php',
    'core/Database.php',
    'core/Helpers.php',
    'core/Setting.php',
    'core/TelegramBot.php',
    'core/Auth.php',
    'core/Provisioner.php',
    'core/Payment.php',
    'core/Updater.php',
    'core/Router.php'
];

foreach ($coreComponents as $component) {
    $cPath = __DIR__ . '/' . $component;
    if (file_exists($cPath)) {
        require_once $cPath;
    }
}

// 2b. EMERGENCY BRAKE (one-shot, stored in shared DB): on a fresh deployment
//     of this code the cron auto-apply of GitHub updates is stopped until the
//     zip-download path is verified.
//     Incident 2026-09-25: a network-cached stale zip was applied by cron
//     and overwrote a healthy deployment.
//     State values of auto_update_brake_applied:
//       ''            -> first boot after deploy -> brake engages (sets '1:...')
//       '1:...'       -> brake engaged (auto_apply_github_updates forced '0')
//       'released:...'-> brake deliberately released via release_brake.php
//     The brake engages ONLY from the '' state, so a deliberate release
//     ('released:...') is never re-braked by later requests.
try {
    if (trim((string)Setting::get('auto_update_brake_applied', '')) === '') {
        Setting::set('auto_apply_github_updates', '0');
        Setting::set('auto_update_brake_applied', '1:' . date('Y-m-d H:i:s'));
    }
} catch (Throwable $e) {}

// v6.9.1 PRO MAX: Auto domain migration - runs after core loaded, ensures domain independence (hourly)
try {
    require_once __DIR__ . '/core/DomainMigrationManager.php';
    if (class_exists('Setting') && class_exists('DomainMigrationManager')) {
        $lastMigrationCheck = (int)Setting::get('last_domain_migration_check', '0');
        if (time() - $lastMigrationCheck > 3600) {
            DomainMigrationManager::autoMigrateIfNeeded();
            Setting::set('last_domain_migration_check', (string)time());
        }
    }
} catch (Throwable $e) {}

// v8.0 PRO MAX: Panel Location Manager - Path independence + auto discovery (hourly + on every API request)
try {
    require_once __DIR__ . '/core/PanelLocationManager.php';
    if (class_exists('Setting') && class_exists('PanelLocationManager')) {
        // Always emit canonical headers (for app auto-update)
        PanelLocationManager::emitCanonicalHeaders();
        
        $lastPathCheck = (int)Setting::get('last_panel_location_check', '0');
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || isset($_GET['route']) && str_starts_with($_GET['route'], 'api/');
        
        // Check hourly, or immediately on API requests (for fast migration detection)
        if (time() - $lastPathCheck > 3600 || $isApiRequest) {
            PanelLocationManager::autoMigrateIfNeeded();
            if (!$isApiRequest) {
                Setting::set('last_panel_location_check', (string)time());
            }
        }
    }
} catch (Throwable $e) {}

// 3. Load All Controllers Safely
$expectedControllers = [
    'AuthController', 'DashboardController', 'ClientController', 'PlanController',
    'ServerController', 'CategoryController', 'ResellerController', 'BillingController',
    'MetadataController', 'NotificationController', 'SublinkController', 'SublinkControllerV2', 'TelegramBotController',
    'PaymentController', 'ApiController', 'ApiControllerV2', 'ProfileController', 'ResellerPortalController',
    'LogController', 'UpdateController', 'TicketController', 'AppGuideController', 'CouponController',
    'ProxyController', 'VipPlanController', 'MonitorController', 'DomainMigrationController', 'PanelLocationController'
];

$missingControllers = [];
foreach ($expectedControllers as $ctrl) {
    $file = __DIR__ . '/controllers/' . $ctrl . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        $missingControllers[] = $ctrl;
    }
}

// 4. If critical controllers are missing - v3.5.8 SAFE: NO GitHub download on web request (moved to cron)
// Original code did curl to GitHub with 30s timeout on EVERY request if a controller was missing, causing 30s block
// Now: Show fast error page with local assets, self-heal only if flag file exists or via repair.php / cron
// To revert: restore from backups/20260929-panel-optimizations/index.php.backup
// To enable auto-heal on web (old behavior): create file data/enable_autheal
if (!empty($missingControllers)) {
    $enableAutoHeal = file_exists(__DIR__ . '/data/enable_autheal') || isset($_GET['force_heal']);
    $healed = false;

    if ($enableAutoHeal) {
        // Only if explicitly enabled - attempt heal with short timeout (5s not 30s)
        $repo = 'hojjatrad/panelconnectix';
        $token = '';
        try {
            if (class_exists('Setting')) {
                $repo = Setting::get('github_repo', $repo);
                $token = Setting::get('github_token', '');
            }
        } catch (Throwable $e) {}

        $url = "https://api.github.com/repos/{$repo}/zipball/main";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // v3.5.8: 5s not 30s
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $headers = ['User-Agent: Connectix-AutoHeal'];
        if (!empty($token)) $headers[] = "Authorization: token {$token}";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && strlen($zipData) > 5000) {
            $tmpZip = sys_get_temp_dir() . '/heal_' . time() . '.zip';
            $tmpExtract = sys_get_temp_dir() . '/heal_ext_' . time();
            file_put_contents($tmpZip, $zipData);

            if (class_exists('Updater') && Updater::extractZip($tmpZip, $tmpExtract)) {
                $subDirs = glob($tmpExtract . '/*', GLOB_ONLYDIR);
                $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExtract;
                if (is_dir($sourceDir . '/controllers')) {
                    Updater::copyDirectory($sourceDir . '/controllers', __DIR__ . '/controllers', []);
                    $healed = true;
                }
            }
            @unlink($tmpZip);
        }

        if ($healed) {
            foreach ($missingControllers as $ctrl) {
                $file = __DIR__ . '/controllers/' . $ctrl . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }

    if (!$healed) {
        http_response_code(500);
        // v3.5.8: Use local assets, not CDN (for Iran)
        $baseUrl = Helpers::basePath();
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>بازیابی اضطراری سیستم | Connectix Panel</title>
            <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/fontawesome.min.css">
            <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/vazirmatn.css">
            <script src="<?= $baseUrl ?>/assets/js/tailwind.js"></script>
            <style> * { font-family: 'Vazirmatn', sans-serif; } </style>
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 text-center space-y-4 shadow-2xl">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 mx-auto flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h1 class="text-lg font-bold text-white">برخی فایل‌های کنترلی سیستم در هاست یافت نشدند</h1>
                <p class="text-xs text-slate-400 leading-relaxed">
                    فایل‌های زیر در مسیر controllers یافت نشدند (احتمالاً به دلیل اکسترکت ناقص):<br>
                    <code class="text-amber-300 font-mono text-[11px] mt-2 inline-block bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800"><?= htmlspecialchars(implode(', ', $missingControllers)) ?></code>
                </p>
                <div class="pt-3 space-y-2">
                    <a href="repair.php?restore_files=1" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-purple-900/30">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                        <span>دانلود و ترمیم خودکار فایل‌های گمشده از گیت‌هاب</span>
                    </a>
                    <a href="index.php" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium rounded-xl text-xs transition block">
                        تلاش مجدد و بارگذاری صفحه
                    </a>
                    <p class="text-[10px] text-slate-500 mt-2">برای بازگشت به حالت قبل: <code>cp backups/20260929-panel-optimizations/index.php.backup index.php</code></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$router = new Router();

// Root Redirects
$router->get('', function() {
    Helpers::redirect('dashboard');
});
$router->get('index.php', function() {
    Helpers::redirect('dashboard');
});

// Authentication
$router->get('login', [AuthController::class, 'showLogin']);
$router->post('login', [AuthController::class, 'doLogin']);
$router->get('logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('dashboard', [DashboardController::class, 'index']);

// Client Management & Operations
$router->get('clients', [ClientController::class, 'index']);
$router->get('clients/create', [ClientController::class, 'create']);
$router->get('clients/bulk', [ClientController::class, 'bulk']);
$router->post('clients/bulk-store', [ClientController::class, 'bulkStore']);
$router->get('clients/bulk-result', [ClientController::class, 'bulkResult']);
$router->get('clients/export', [ClientController::class, 'exportCsv']);
$router->get('clients/configs', [ClientController::class, 'getConfigs']);
$router->post('clients/test-account', [ClientController::class, 'createTestAccount']);
$router->post('clients/bulk', [ClientController::class, 'bulkAction']);
$router->post('clients/optimize-purge', [ClientController::class, 'optimizePurge']);
$router->get('clients/restore-traffic', [ClientController::class, 'restoreTrafficFromBackup']);
$router->post('clients/restore-traffic', [ClientController::class, 'restoreTrafficFromBackup']);
$router->post('clients/store', [ClientController::class, 'store']);
$router->post('clients/update', [ClientController::class, 'update']);
$router->post('clients/renew', [ClientController::class, 'renew']);
$router->post('clients/reserve', [ClientController::class, 'reservePlan']);
$router->post('clients/delete', [ClientController::class, 'delete']);

// Plans Management
$router->get('plans', [PlanController::class, 'index']);
$router->post('plans/store', [PlanController::class, 'store']);
$router->post('plans/update', [PlanController::class, 'update']);
$router->post('plans/toggle', [PlanController::class, 'toggle']);
$router->post('plans/toggle-bot', [PlanController::class, 'toggleBot']);
$router->post('plans/delete', [PlanController::class, 'delete']);
$router->post('plans/purge-all', [PlanController::class, 'purgeAll']);
$router->get('plans/purge-all', [PlanController::class, 'purgeAll']);
$router->post('plans/enable-auto-import', [PlanController::class, 'enableAutoImport']);
$router->get('plans/enable-auto-import', [PlanController::class, 'enableAutoImport']);
$router->post('plans/disable-auto-import', [PlanController::class, 'disableAutoImport']);
$router->get('plans/disable-auto-import', [PlanController::class, 'disableAutoImport']);

// VIP Plans Mapping - Economic / ویژه / Iran Access
$router->get('vip_plans', [VipPlanController::class, 'index']);
$router->post('vip_plans/save-mapping', [VipPlanController::class, 'saveMapping']);
$router->post('vip_plans/auto-map', [VipPlanController::class, 'autoMap']);
$router->get('vip_plans/auto-map', [VipPlanController::class, 'autoMap']);
$router->post('vip_plans/import-all', [VipPlanController::class, 'importAll']);
$router->get('vip_plans/import-all', [VipPlanController::class, 'importAll']);

// Server Nodes Management (Admin only)
$router->get('servers', [ServerController::class, 'index']);
$router->post('servers/store', [ServerController::class, 'store']);
$router->post('servers/update', [ServerController::class, 'update']);
$router->post('servers/migrate', [ServerController::class, 'migrateClients']);
$router->post('servers/delete', [ServerController::class, 'delete']);
$router->post('servers/clear-all', [ServerController::class, 'clearAll']);
$router->post('servers/purge-all-samples', [ServerController::class, 'purgeAllSamples']);
$router->get('servers/purge-all-samples', [ServerController::class, 'purgeAllSamples']);
$router->get('servers/test', [ServerController::class, 'testConnection']);
$router->post('servers/test-raw', [ServerController::class, 'testRawConnection']);
$router->get('servers/test-raw', [ServerController::class, 'testRawConnection']);
$router->post('servers/fetch-inbounds-sample', [ServerController::class, 'fetchInboundsAndSample']);
$router->get('servers/fetch-inbounds-sample', [ServerController::class, 'fetchInboundsAndSample']);
$router->get('servers/ping', [ServerController::class, 'ping']);
$router->get('servers/ping-all', [ServerController::class, 'pingAll']);
$router->get('servers/stats', [ServerController::class, 'stats']);
$router->post('servers/ping-all', [ServerController::class, 'pingAll']);

// Live node client manager (all users on the node + links/credentials)
$router->get('servers/{id}/node-users', [ServerController::class, 'nodeUsers']);
$router->post('servers/node-users/action', [ServerController::class, 'nodeUsersAction']);
$router->post('servers/{id}/node-users/sync', [ServerController::class, 'nodeUsersSync']);
$router->post('servers/{id}/full-sync', [ServerController::class, 'fullSync']);
$router->get('servers/{id}/node-users/export', [ServerController::class, 'nodeUsersExport']);
$router->get('servers/sync', [ServerController::class, 'syncNow']);
$router->post('servers/sync', [ServerController::class, 'syncNow']);
$router->get('servers/health-check', [ServerController::class, 'checkHealth']);
$router->post('servers/health-check', [ServerController::class, 'checkHealth']);

// v6.9.0 PRO MAX: Live Monitoring + Sync Queue (anti-520) + Rotating Sublink Domains (anti-filter)
$router->get('monitoring', [MonitorController::class, 'index']);
$router->post('monitoring/check-now', [MonitorController::class, 'checkNow']);
$router->post('monitoring/check-domains', [MonitorController::class, 'checkDomains']);
$router->get('monitoring/server/{id}', [MonitorController::class, 'serverLogs']);
$router->get('servers/{id}/sync-queue/status', [ServerController::class, 'syncQueueStatus']);
$router->post('servers/{id}/sync-queue/process', [ServerController::class, 'syncQueueProcess']);
// Backward compat for old view URLs
$router->get('servers/queue/{id}/status', [ServerController::class, 'syncQueueStatus']);
$router->post('monitoring/queue/{id}/process', [ServerController::class, 'syncQueueProcess']);
$router->get('monitoring/queue/{id}/status', [ServerController::class, 'syncQueueStatus']);
$router->get('settings/sublink-domains', [SublinkController::class, 'index']);
$router->post('settings/sublink-domains/add', [SublinkController::class, 'add']);
$router->post('settings/sublink-domains/store', [SublinkController::class, 'add']);
$router->post('settings/sublink-domains/{id}/delete', [SublinkController::class, 'delete']);
$router->post('settings/sublink-domains/{id}/toggle', [SublinkController::class, 'toggle']);
$router->post('settings/sublink-domains/{id}/set-primary', [SublinkController::class, 'setPrimary']);
$router->post('settings/sublink-domains/check', [SublinkController::class, 'check']);

// v6.9.1 PRO MAX: Domain Migration & Independence
$router->get('settings/domain-migration', [DomainMigrationController::class, 'index']);
$router->post('settings/domain-migration/check', [DomainMigrationController::class, 'check']);
$router->post('settings/domain-migration/migrate', [DomainMigrationController::class, 'migrate']);

// v8.0 PRO MAX: Panel Location Manager - Path Independence
$router->get('settings/panel-location', [PanelLocationController::class, 'index']);
$router->post('settings/panel-location/check', [PanelLocationController::class, 'check']);
$router->post('settings/panel-location/regenerate', [PanelLocationController::class, 'regenerate']);

// Resellers Management (Admin only)
$router->get('resellers', [ResellerController::class, 'index']);
$router->post('resellers/store', [ResellerController::class, 'store']);
$router->post('resellers/adjust', [ResellerController::class, 'adjustBalance']);
$router->post('resellers/set-credit-limit', [ResellerController::class, 'setCreditLimit']);
$router->post('resellers/update-discount', [ResellerController::class, 'updateDiscount']);
$router->post('resellers/update-custom-perms', [ResellerController::class, 'updateCustomPlanPermissions']);
$router->post('resellers/reset-password', [ResellerController::class, 'resetPassword']);
$router->post('resellers/delete', [ResellerController::class, 'delete']);
$router->get('resellers/clients', [ResellerController::class, 'clients']);
$router->get('resellers/invoice', [ResellerController::class, 'invoice']);
$router->get('resellers/invoice-export', [ResellerController::class, 'exportInvoiceCsv']);
$router->get('resellers/backup', [ResellerController::class, 'backupAction']);
$router->get('resellers/export-financial', [ResellerController::class, 'exportFinancial']);
$router->get('resellers/applications', [ResellerController::class, 'applications']);
$router->post('resellers/applications/approve', [ResellerController::class, 'approveApplication']);
$router->post('resellers/applications/reject', [ResellerController::class, 'rejectApplication']);

// Reseller Dedicated Portal
$router->get('reseller/bot', [ResellerPortalController::class, 'bot']);
$router->post('reseller/bot', [ResellerPortalController::class, 'saveBot']);
$router->get('reseller/banking', [ResellerPortalController::class, 'banking']);
$router->post('reseller/banking', [ResellerPortalController::class, 'saveBanking']);
$router->get('reseller/branding', [ResellerPortalController::class, 'branding']);
$router->post('reseller/branding', [ResellerPortalController::class, 'saveBranding']);
$router->get('reseller/plans', [ResellerPortalController::class, 'plans']);
$router->post('reseller/plans', [ResellerPortalController::class, 'savePlans']);
$router->post('reseller/custom-plans/create', [ResellerPortalController::class, 'createCustomPlan']);
$router->post('reseller/custom-plans/update', [ResellerPortalController::class, 'updateCustomPlan']);
$router->post('reseller/custom-plans/delete', [ResellerPortalController::class, 'deleteCustomPlan']);
$router->get('reseller/invoice', [ResellerPortalController::class, 'invoice']);
$router->get('reseller/invoice-export', [ResellerPortalController::class, 'exportInvoiceCsv']);
$router->get('reseller/orders', [ResellerPortalController::class, 'orders']);
$router->post('reseller/orders/approve', [ResellerPortalController::class, 'approveOrder']);
$router->post('reseller/orders/reject', [ResellerPortalController::class, 'rejectOrder']);
$router->get('reseller/sub-resellers', [ResellerPortalController::class, 'subResellers']);
$router->post('reseller/sub-resellers/store', [ResellerPortalController::class, 'storeSubReseller']);
$router->post('reseller/sub-resellers/transfer', [ResellerPortalController::class, 'transferCredit']);

// Billing & Prepaid Wallet & Referrals
$router->get('billing', [BillingController::class, 'index']);
$router->post('billing/topup', [BillingController::class, 'topup']);
$router->post('billing/gateways', [BillingController::class, 'updateGateways']);
$router->get('settings/referrals', [BillingController::class, 'referrals']);

// White-Label Settings & Metadata
$router->get('settings/metadata', [MetadataController::class, 'index']);
$router->post('settings/metadata', [MetadataController::class, 'update']);
$router->post('app/apk-mirror', [MetadataController::class, 'mirrorApk']);
$router->get('app/apk-mirror-force', [MetadataController::class, 'forceMirrorApk']);
$router->post('app/apk-mirror-force', [MetadataController::class, 'forceMirrorApk']);
$router->get('settings/backup', [MetadataController::class, 'backup']);
$router->get('settings/backup-telegram', [MetadataController::class, 'backupTelegram']);
$router->post('settings/restore', [MetadataController::class, 'restore']);

// Dynamic Clusters & Categories Management — domain independent + smart merge
$router->get('categories', [CategoryController::class, 'index']);
$router->post('categories/store', [CategoryController::class, 'store']);
$router->post('categories/update', [CategoryController::class, 'update']);
$router->post('categories/delete', [CategoryController::class, 'delete']);
$router->post('categories/bulk-delete', [CategoryController::class, 'bulkDelete']);
$router->post('categories/purge-all', [CategoryController::class, 'purgeAll']);
$router->get('categories/purge-all', [CategoryController::class, 'purgeAll']);
$router->post('categories/enable-auto-seed', [CategoryController::class, 'enableAutoSeed']);
$router->get('categories/enable-auto-seed', [CategoryController::class, 'enableAutoSeed']);
$router->post('categories/disable-auto-seed', [CategoryController::class, 'disableAutoSeed']);
$router->get('categories/disable-auto-seed', [CategoryController::class, 'disableAutoSeed']);
$router->post('categories/merge_duplicates', [CategoryController::class, 'mergeDuplicates']);
$router->post('categories/fix_all', [CategoryController::class, 'fixAll']);
$router->get('categories/merge_duplicates', [CategoryController::class, 'mergeDuplicates']);
$router->get('categories/fix_all', [CategoryController::class, 'fixAll']);

// VIP plans merge route (alternative)
$router->post('vip_plans/merge_categories', [VipPlanController::class, 'mergeCategories']);
$router->get('vip_plans/merge_categories', [VipPlanController::class, 'mergeCategories']);

// Domain independence tools
$router->get('migrate_host', function() { require __DIR__ . '/migrate_host.php'; });
$router->post('migrate_host', function() { require __DIR__ . '/migrate_host.php'; });
$router->get('test_host_independence', function() { require __DIR__ . '/test_host_independence.php'; });
$router->post('test_host_independence', function() { require __DIR__ . '/test_host_independence.php'; });

// Profile & Security & 2FA
$router->get('profile', [ProfileController::class, 'show']);
$router->post('profile/password', [ProfileController::class, 'updatePassword']);
$router->post('profile/regenerate-token', [ProfileController::class, 'regenerateToken']);
$router->post('profile/2fa/enable', [ProfileController::class, 'enable2fa']);
$router->post('profile/2fa/disable', [ProfileController::class, 'disable2fa']);

// Backup & Cloud Export
$router->get('settings/backup', function() {
    Auth::requireAdmin();
    require_once __DIR__ . '/core/Backup.php';
    $b = Backup::createBackupFile();
    if ($b['success'] && file_exists($b['path'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $b['filename'] . '"');
        header('Content-Length: ' . filesize($b['path']));
        readfile($b['path']);
        @unlink($b['path']);
        exit;
    }
    Helpers::flash('error', 'خطا در ایجاد فایل پشتیبان.');
    Helpers::redirect('settings/metadata');
});
$router->post('settings/backup/telegram', function() {
    Auth::requireAdmin();
    if (!Helpers::verifyCsrf()) {
        Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
        Helpers::redirect('settings/metadata');
    }
    require_once __DIR__ . '/core/Backup.php';
    $res = Backup::sendBackupToTelegram();
    if ($res['success']) {
        Helpers::flash('success', $res['message']);
    } else {
        Helpers::flash('error', $res['message']);
    }
    Helpers::redirect('settings/metadata');
});
$router->get('settings/backup-telegram', function() {
    Auth::requireAdmin();
    require_once __DIR__ . '/core/Backup.php';
    $res = Backup::sendBackupToTelegram();
    if ($res['success']) {
        Helpers::flash('success', $res['message']);
    } else {
        Helpers::flash('error', $res['message']);
    }
    Helpers::redirect('settings/metadata');
});

// Telegram Bot Management & Webhook
$router->get('settings/bot', [TelegramBotController::class, 'manage']);
$router->get('settings/bot-users', [TelegramBotController::class, 'botUsers']);
$router->post('settings/bot-users/send-msg', [TelegramBotController::class, 'sendUserMessage']);
$router->post('settings/bot-broadcast', [TelegramBotController::class, 'broadcast']);
$router->post('settings/bot/channel-post', [TelegramBotController::class, 'channelPost']);
$router->post('settings/bot', [TelegramBotController::class, 'updateSettings']);
$router->post('settings/bot/auto-create-topics', [TelegramBotController::class, 'autoCreateTopicsAction']);
$router->post('bot/auto-create-topics', [TelegramBotController::class, 'autoCreateTopicsAction']);
$router->post('settings/bot/set-webhook', [TelegramBotController::class, 'setWebhookAction']);
$router->post('settings/bot/test-message', [TelegramBotController::class, 'testMessageAction']);
$router->post('settings/bot/delete-webhook', [TelegramBotController::class, 'deleteWebhookAction']);
$router->post('settings/bot/approve', [TelegramBotController::class, 'approveWeb']);
$router->post('settings/bot/reject', [TelegramBotController::class, 'rejectWeb']);
$router->get('telegram/webhook', [TelegramBotController::class, 'handleWebhook']);
$router->post('telegram/webhook', [TelegramBotController::class, 'handleWebhook']);

// Telegram Mini App (WebApp)
$router->get('webapp', [WebappController::class, 'index']);
$router->post('webapp/spin', [WebappController::class, 'spin']);

// App Guides & Download Tutorials
$router->get('apps', [AppGuideController::class, 'publicIndex']);
$router->get('download', [AppGuideController::class, 'publicIndex']);
$router->get('ios-guide', [AppGuideController::class, 'iosGuide']);
$router->get('apps/ios', [AppGuideController::class, 'iosGuide']);
$router->get('download/ios', [AppGuideController::class, 'iosGuide']);
$router->get('settings/app-guides', [AppGuideController::class, 'index']);
$router->post('settings/app-guides/store', [AppGuideController::class, 'store']);
$router->post('settings/app-guides/update', [AppGuideController::class, 'update']);
$router->post('settings/app-guides/toggle', [AppGuideController::class, 'toggle']);
$router->post('settings/app-guides/delete', [AppGuideController::class, 'delete']);
$router->post('settings/app-guides/reset-defaults', [AppGuideController::class, 'resetDefaults']);

// Discount Coupons
$router->get('settings/coupons', [CouponController::class, 'index']);
$router->post('settings/coupons/store', [CouponController::class, 'store']);
$router->post('settings/coupons/toggle', [CouponController::class, 'toggle']);
$router->post('settings/coupons/delete', [CouponController::class, 'delete']);

// In-Panel Tickets & Support
$router->get('tickets', [TicketController::class, 'index']);
$router->get('tickets/create', [TicketController::class, 'create']);
$router->post('tickets/store', [TicketController::class, 'store']);
$router->get('tickets/show', [TicketController::class, 'show']);
$router->post('tickets/reply', [TicketController::class, 'reply']);
$router->post('tickets/close', [TicketController::class, 'close']);
$router->post('tickets/bulk-close', [TicketController::class, 'bulkClose']);
$router->post('tickets/bulk-action', [TicketController::class, 'bulkAction']);
$router->post('tickets/ai-draft/send', [TicketController::class, 'aiDraftSend']);
$router->post('tickets/ai-draft/discard', [TicketController::class, 'aiDraftDiscard']);

// AI Assistant (Admin only — مدیرکل)
$router->get('settings/ai', [AiController::class, 'index']);
$router->post('settings/ai/save', [AiController::class, 'save']);
$router->post('settings/ai/test', [AiController::class, 'testProvider']);
$router->post('settings/ai/refresh-models', [AiController::class, 'refreshModels']);
$router->get('settings/ai/knowledge', [AiController::class, 'knowledge']);
$router->post('settings/ai/knowledge/store', [AiController::class, 'knowledgeStore']);
$router->post('settings/ai/knowledge/update', [AiController::class, 'knowledgeUpdate']);
$router->post('settings/ai/knowledge/toggle', [AiController::class, 'knowledgeToggle']);
$router->post('settings/ai/knowledge/delete', [AiController::class, 'knowledgeDelete']);
$router->get('settings/ai/resellers', [AiController::class, 'resellers']);
$router->post('settings/ai/resellers/activate', [AiController::class, 'activate']);
$router->post('settings/ai/resellers/renew', [AiController::class, 'renew']);
$router->post('settings/ai/resellers/revoke', [AiController::class, 'revoke']);
$router->get('settings/ai/logs', [AiController::class, 'logs']);

// AI feature for resellers (charge with expiry)
$router->get('reseller/ai', [ResellerPortalController::class, 'ai']);
$router->post('reseller/ai/request', [ResellerPortalController::class, 'aiRequest']);

// Online Payments
$router->get('payment/pay', [PaymentController::class, 'payBotOrder']);
$router->get('payment/callback', [PaymentController::class, 'callback']);

// Notifications & Broadcasts
$router->get('notifications', [NotificationController::class, 'index']);
$router->post('notifications/store', [NotificationController::class, 'store']);

$router->get('logs', [LogController::class, 'index']);
$router->post('logs/clear', [LogController::class, 'clear']);

// External Monitoring Heartbeat (lightweight, for uptime monitors)
$router->get('monitor/heartbeat', function() {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/core/Setting.php';
    $out = ['ok' => true, 'service' => 'connectix-panel', 'time' => date('c'), 'version' => ''];
    try {
        require_once __DIR__ . '/core/Database.php';
        $pdo = Database::getConnection();
        $pdo->query("SELECT 1")->fetchColumn();
        $out['db'] = 'ok';
    } catch (Throwable $e) {
        $out['ok'] = false;
        $out['db'] = 'error: ' . substr($e->getMessage(), 0, 120);
    }
    try {
        $last = (int)Setting::get('last_cron_sync_at', '0');
        $out['cron_age_sec'] = $last > 0 ? time() - $last : null;
        $out['cron_ok'] = $last > 0 && (time() - $last <= 600);
    } catch (Throwable $e) {}
    try {
        require_once __DIR__ . '/core/Updater.php';
        $out['version'] = Updater::getCurrentVersion();
    } catch (Throwable $e) {}
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
});

// Server-side diagnostics (key-protected; temporary — used for production debugging)
$router->get('monitor/diag', [DiagController::class, 'run']);

// Cron Job Execution Endpoints
$router->get('sync', function() {
    require_once __DIR__ . '/cron/sync.php';
});
$router->get('cron/sync', function() {
    require_once __DIR__ . '/cron/sync.php';
});

// Operations (key-protected, operator-facing)
$router->get('ops/set-setting', [OpsController::class, 'setSetting']);
$router->post('ops/set-setting', [OpsController::class, 'setSetting']);
$router->get('ops/rotate-webhook-secret', [OpsController::class, 'rotateWebhookSecret']);
$router->post('ops/rotate-webhook-secret', [OpsController::class, 'rotateWebhookSecret']);

$router->get('updater', [UpdateController::class, 'index']);
$router->get('updater/check', [UpdateController::class, 'checkNow']);
$router->get('updater/cloudflare', [UpdateController::class, 'cloudflare']);
$router->get('settings/cloudflare', [UpdateController::class, 'cloudflare']);
$router->get('settings/api-tokens', [ApiTokenController::class, 'index']);
$router->post('settings/api-tokens/save', [ApiTokenController::class, 'save']);
$router->post('settings/api-tokens/auto-direct', [ApiTokenController::class, 'autoDirect']);

// Server Backups PRO v6.8.28
$router->get('backups', [BackupController::class, 'index']);
$router->post('backups/create', [BackupController::class, 'create']);
$router->post('backups/auto-all', [BackupController::class, 'autoBackupAll']);
$router->get('backups/export-excel', [BackupController::class, 'exportExcel']);
$router->get('backups/{id}/download', [BackupController::class, 'download']);
$router->get('backups/{id}/preview', [BackupController::class, 'preview']);
$router->post('backups/{id}/delete', [BackupController::class, 'delete']);
$router->post('backups/{id}/restore', [BackupController::class, 'restore']);
$router->post('backups/{id}/send-telegram', [BackupController::class, 'sendTelegram']);
$router->post('updater/apply', [UpdateController::class, 'apply']);
$router->post('updater/ajax-apply', [UpdateController::class, 'ajaxApply']);
$router->post('updater/settings', [UpdateController::class, 'saveSettings']);
$router->post('updater/git-push', [UpdateController::class, 'gitPushAction']);
$router->post('updater/webhook', [UpdateController::class, 'webhook']);
$router->get('updater/webhook', [UpdateController::class, 'webhook']);

// v6.8.13: Switch root - panel vs promo
$router->get('switch_root', function() { require __DIR__ . '/switch_root.php'; });
$router->post('switch_root', function() { require __DIR__ . '/switch_root.php'; });
$router->get('settings/switch_root', function() { require __DIR__ . '/switch_root.php'; });

// v6.8.15: Bank auto-verification
// v7.0 ULTRA: Onboarding Wizard + Financial Reports PRO
$router->get('onboarding', [OnboardingController::class, 'index']);
$router->post('onboarding/complete', [OnboardingController::class, 'complete']);
$router->post('onboarding/skip', [OnboardingController::class, 'skip']);
// v7.0 ULTRA: Financial Reports PRO
$router->get('financial', [FinancialController::class, 'index']);
$router->get('financial/export', [FinancialController::class, 'export']);
// v7.1 ULTRA COMPLETE: Loyalty, Usage History, Theme
$router->get('resellers/points', [ResellerPointsController::class, 'index']);
$router->get('reseller/points', [ResellerPointsController::class, 'myPoints']);
$router->post('resellers/points/adjust', [ResellerPointsController::class, 'adjust']);
$router->get('clients/{id}/usage', [ClientController::class, 'usageHistory']);
$router->get('clients/{id}/usage-history', [ClientController::class, 'usageHistory']);
$router->get('settings/bank-verification', [BankVerificationController::class, 'index']);
$router->post('settings/bank-verification/save', [BankVerificationController::class, 'save']);
$router->post('settings/bank-verification/test-sms', [BankVerificationController::class, 'testSms']);
$router->post('settings/bank-verification/manual-match', [BankVerificationController::class, 'manualMatch']);
$router->get('api/bank-webhook', [BankVerificationController::class, 'webhook']);
$router->post('api/bank-webhook', [BankVerificationController::class, 'webhook']);

// Public Subscription & Dynamic QR Landing Endpoint
$router->get('sub/{token}', [SublinkControllerV2::class, 'show']);

// Client Self-Service Portal (end-customers, username+password)
$router->get('client', [ClientPortalController::class, 'index']);
$router->post('client/login', [ClientPortalController::class, 'login']);
$router->get('client/logout', [ClientPortalController::class, 'logout']);

// Reseller & Bot REST API (v1)
$router->get('api/v1/reseller/brand/{id}', [ApiControllerV2::class, 'resellerBrand']);
$router->get('api/v1/wallet', [ApiControllerV2::class, 'getWallet']);
$router->get('api/v1/plans', [ApiControllerV2::class, 'getPlans']);
$router->post('api/v1/client/create', [ApiControllerV2::class, 'createClient']);
$router->get('api/v1/client/info', [ApiControllerV2::class, 'getClientInfo']);

// Dedicated Client Mobile & Desktop App Endpoints (v1)
$router->get('settings/app-api', [ApiControllerV2::class, 'showAppApiDoc']);
$router->post('api/v1/app/login', [ApiControllerV2::class, 'appLogin']);
$router->get('api/v1/app/profile', [ApiControllerV2::class, 'appProfile']);
$router->post('api/v1/app/profile', [ApiControllerV2::class, 'appProfile']);
$router->get('api/v1/app/configs', [ApiControllerV2::class, 'appConfigs']);
$router->post('api/v1/app/configs', [ApiControllerV2::class, 'appConfigs']);
$router->get('api/v1/app/announcements', [ApiControllerV2::class, 'appAnnouncements']);
$router->post('api/v1/app/feedback', [ApiControllerV2::class, 'appFeedback']);
$router->get('api/v1/app/check-update', [ApiControllerV2::class, 'checkAppUpdate']);
$router->post('api/v1/app/check-update', [ApiControllerV2::class, 'checkAppUpdate']);

// v8.0 PRO MAX: Panel Location Discovery - No auth required, for smart app resolver
$router->get('api/v1/app/panel-location', [ApiControllerV2::class, 'panelLocation']);
$router->post('api/v1/app/panel-location', [ApiControllerV2::class, 'panelLocation']);
$router->get('api/panel-location', [ApiControllerV2::class, 'panelLocation']);
$router->get('.well-known/connectix.json', [ApiControllerV2::class, 'wellKnown']);
$router->get('well-known/connectix.json', [ApiControllerV2::class, 'wellKnown']);

// v4.0.18 PROXY: Proxy configs for Telegram and other apps - Free for VPN + Sellable
$router->get('api/v1/app/proxies', [ProxyController::class, 'appProxies']);
$router->post('api/v1/app/proxies', [ProxyController::class, 'appProxies']);
$router->get('proxies', [ProxyController::class, 'adminProxies']);

// Dispatch Request with graceful error protection
try {
    $router->dispatch();
} catch (Throwable $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorFile = basename($e->getFile());
    $errorLine = $e->getLine();
    error_log("Connectix Critical Error: {$errorMsg} in {$errorFile}:{$errorLine}");
    
    // v6.8.9: If this is ajax-apply, ALWAYS return JSON never HTML
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $route = $_GET['route'] ?? '';
    $isAjaxUpdaterReq = (defined('IS_AJAX_UPDATER') && IS_AJAX_UPDATER) || str_contains($path, 'updater/ajax-apply') || $route === 'updater/ajax-apply' || str_contains($_SERVER['REQUEST_URI'] ?? '', 'updater/ajax-apply');
    if ($isAjaxUpdaterReq) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'خطای سیستمی: ' . $errorMsg . ' (' . $errorFile . ':' . $errorLine . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Check if it's an API request or sublink
    if (str_contains($path, '/api/') || (isset($_GET['route']) && str_starts_with($_GET['route'], 'api/'))) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $errorMsg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Elegant user-friendly error screen
    echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>خطای سیستم | Connectix Panel</title><script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap'); *{font-family:'Vazirmatn',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4'><div class='bg-slate-900 border border-rose-900/50 p-8 rounded-3xl max-w-lg w-full text-center shadow-2xl space-y-4'><div class='w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center text-3xl font-black'>⚠️</div><h2 class='text-xl font-black text-white'>خطای اجرای سامانه (Error 500)</h2><div class='bg-slate-950/80 p-4 rounded-xl border border-rose-900/30 text-right space-y-1.5'><p class='text-xs text-rose-300 font-mono break-all font-semibold'>" . htmlspecialchars($errorMsg) . "</p><p class='text-[11px] text-slate-500 font-mono'>در فایل: " . htmlspecialchars($errorFile) . " (خط " . $errorLine . ")</p></div><div class='pt-2 flex flex-col gap-2'><a href='" . Helpers::url('dashboard') . "' class='w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md'>تلاش مجدد و بازگشت به داشبورد</a><a href='install.php?reinstall=1' class='w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition-all border border-slate-700'>ورود به نصب‌کننده خودکار دیتابیس</a></div></div></body></html>";
    exit;
}

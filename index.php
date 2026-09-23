<?php
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

// Direct install or repair route handling
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$routeParam = $_GET['route'] ?? '';

if (str_ends_with($requestPath, 'install.php') || str_ends_with($requestPath, '/install') || $routeParam === 'install.php' || $routeParam === 'install') {
    require_once __DIR__ . '/install.php';
    exit;
}

if (str_ends_with($requestPath, 'repair.php') || str_ends_with($requestPath, '/repair') || $routeParam === 'repair.php' || $routeParam === 'repair') {
    require_once __DIR__ . '/repair.php';
    exit;
}

// Intercept Telegram Webhook on ANY variation
if (str_ends_with($requestPath, 'webhook.php') || str_ends_with($requestPath, '/webhook') 
    || $routeParam === 'webhook.php' || $routeParam === 'webhook' || $routeParam === 'telegram/webhook') {
    require_once __DIR__ . '/webhook.php';
    exit;
}

if (str_contains($requestPath, 'cron/sync.php') || str_contains($requestPath, 'sync.php') || str_ends_with($requestPath, '/sync') || $routeParam === 'cron/sync.php' || $routeParam === 'cron/sync' || $routeParam === 'sync') {
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

// 3. Load All Controllers Safely
$expectedControllers = [
    'AuthController', 'DashboardController', 'ClientController', 'PlanController',
    'ServerController', 'CategoryController', 'ResellerController', 'BillingController',
    'MetadataController', 'NotificationController', 'SublinkController', 'TelegramBotController',
    'PaymentController', 'ApiController', 'ProfileController', 'ResellerPortalController',
    'LogController', 'UpdateController', 'TicketController', 'AppGuideController', 'CouponController'
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

// 4. If critical controllers are missing, attempt instant self-heal
if (!empty($missingControllers)) {
    $healed = false;
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
    } else {
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>بازیابی اضطراری سیستم | Connectix Panel</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap');
                * { font-family: 'Vazirmatn', sans-serif; }
            </style>
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 text-center space-y-4 shadow-2xl">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 mx-auto flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h1 class="text-lg font-bold text-white">برخی فایل‌های کنترلی سیستم در هاست یافت نشدند</h1>
                <p class="text-xs text-slate-400 leading-relaxed">
                    فایل‌های زیر در مسیر controllers یافت نشدند (احتمالاً به دلیل اکسترکت ناقص یا اختلال دسترسی فایل‌ها در هاست):<br>
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
$router->get('servers/sync', [ServerController::class, 'syncNow']);
$router->post('servers/sync', [ServerController::class, 'syncNow']);
$router->get('servers/health-check', [ServerController::class, 'checkHealth']);
$router->post('servers/health-check', [ServerController::class, 'checkHealth']);

// Resellers Management (Admin only)
$router->get('resellers', [ResellerController::class, 'index']);
$router->post('resellers/store', [ResellerController::class, 'store']);
$router->post('resellers/adjust', [ResellerController::class, 'adjustBalance']);
$router->post('resellers/set-credit-limit', [ResellerController::class, 'setCreditLimit']);
$router->post('resellers/update-discount', [ResellerController::class, 'updateDiscount']);
$router->post('resellers/reset-password', [ResellerController::class, 'resetPassword']);
$router->post('resellers/delete', [ResellerController::class, 'delete']);
$router->get('resellers/clients', [ResellerController::class, 'clients']);
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
$router->get('settings/backup', [MetadataController::class, 'backup']);
$router->get('settings/backup-telegram', [MetadataController::class, 'backupTelegram']);
$router->post('settings/restore', [MetadataController::class, 'restore']);

// Dynamic Clusters & Categories Management
$router->get('categories', [CategoryController::class, 'index']);
$router->post('categories/store', [CategoryController::class, 'store']);
$router->post('categories/update', [CategoryController::class, 'update']);
$router->post('categories/delete', [CategoryController::class, 'delete']);

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
$router->get('settings/app-guides', [AppGuideController::class, 'index']);
$router->post('settings/app-guides/store', [AppGuideController::class, 'store']);
$router->post('settings/app-guides/update', [AppGuideController::class, 'update']);
$router->post('settings/app-guides/toggle', [AppGuideController::class, 'toggle']);
$router->post('settings/app-guides/delete', [AppGuideController::class, 'delete']);

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

// Online Payments
$router->get('payment/pay', [PaymentController::class, 'payBotOrder']);
$router->get('payment/callback', [PaymentController::class, 'callback']);

// Notifications & Broadcasts
$router->get('notifications', [NotificationController::class, 'index']);
$router->post('notifications/store', [NotificationController::class, 'store']);

$router->get('logs', [LogController::class, 'index']);
$router->post('logs/clear', [LogController::class, 'clear']);

// Cron Job Execution Endpoints
$router->get('sync', function() {
    require_once __DIR__ . '/cron/sync.php';
});
$router->get('cron/sync', function() {
    require_once __DIR__ . '/cron/sync.php';
});

$router->get('updater', [UpdateController::class, 'index']);
$router->get('updater/check', [UpdateController::class, 'checkNow']);
$router->post('updater/apply', [UpdateController::class, 'apply']);
$router->post('updater/ajax-apply', [UpdateController::class, 'ajaxApply']);
$router->post('updater/settings', [UpdateController::class, 'saveSettings']);
$router->post('updater/git-push', [UpdateController::class, 'gitPushAction']);
$router->post('updater/webhook', [UpdateController::class, 'webhook']);
$router->get('updater/webhook', [UpdateController::class, 'webhook']);

// Public Subscription & Dynamic QR Landing Endpoint
$router->get('sub/{token}', [SublinkController::class, 'show']);

// Reseller & Bot REST API (v1)
$router->get('api/v1/wallet', [ApiController::class, 'getWallet']);
$router->get('api/v1/plans', [ApiController::class, 'getPlans']);
$router->post('api/v1/client/create', [ApiController::class, 'createClient']);
$router->get('api/v1/client/info', [ApiController::class, 'getClientInfo']);

// Dedicated Client Mobile & Desktop App Endpoints (v1)
$router->get('settings/app-api', [ApiController::class, 'showAppApiDoc']);
$router->post('api/v1/app/login', [ApiController::class, 'appLogin']);
$router->get('api/v1/app/profile', [ApiController::class, 'appProfile']);
$router->get('api/v1/app/configs', [ApiController::class, 'appConfigs']);
$router->get('api/v1/app/announcements', [ApiController::class, 'appAnnouncements']);
$router->post('api/v1/app/feedback', [ApiController::class, 'appFeedback']);

// Dispatch Request with graceful error protection
try {
    $router->dispatch();
} catch (Throwable $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorFile = basename($e->getFile());
    $errorLine = $e->getLine();
    error_log("Connectix Critical Error: {$errorMsg} in {$errorFile}:{$errorLine}");
    
    // Check if it's an API request or sublink
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (str_contains($path, '/api/') || (isset($_GET['route']) && str_starts_with($_GET['route'], 'api/'))) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $errorMsg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Elegant user-friendly error screen
    echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>خطای سیستم | Connectix Panel</title><script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap'); *{font-family:'Vazirmatn',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4'><div class='bg-slate-900 border border-rose-900/50 p-8 rounded-3xl max-w-lg w-full text-center shadow-2xl space-y-4'><div class='w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center text-3xl font-black'>⚠️</div><h2 class='text-xl font-black text-white'>خطای اجرای سامانه (Error 500)</h2><div class='bg-slate-950/80 p-4 rounded-xl border border-rose-900/30 text-right space-y-1.5'><p class='text-xs text-rose-300 font-mono break-all font-semibold'>" . htmlspecialchars($errorMsg) . "</p><p class='text-[11px] text-slate-500 font-mono'>در فایل: " . htmlspecialchars($errorFile) . " (خط " . $errorLine . ")</p></div><div class='pt-2 flex flex-col gap-2'><a href='" . Helpers::url('dashboard') . "' class='w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md'>تلاش مجدد و بازگشت به داشبورد</a><a href='install.php?reinstall=1' class='w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition-all border border-slate-700'>ورود به نصب‌کننده خودکار دیتابیس</a></div></div></body></html>";
    exit;
}

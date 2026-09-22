<?php
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

// Direct install route handling
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (str_ends_with($requestPath, 'install.php') || str_ends_with($requestPath, '/install')) {
    require_once __DIR__ . '/install.php';
    exit;
}

// Intercept Telegram Webhook on ANY variation
$routeParam = $_GET['route'] ?? '';
if (str_ends_with($requestPath, 'webhook.php') || str_ends_with($requestPath, '/webhook') 
    || $routeParam === 'webhook.php' || $routeParam === 'webhook' || $routeParam === 'telegram/webhook') {
    require_once __DIR__ . '/webhook.php';
    exit;
}

if (str_contains($requestPath, 'cron/sync.php') || str_contains($requestPath, 'sync.php') || str_ends_with($requestPath, '/sync') || $routeParam === 'cron/sync.php' || $routeParam === 'cron/sync' || $routeParam === 'sync') {
    require_once __DIR__ . '/cron/sync.php';
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Router.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ClientController.php';
require_once __DIR__ . '/controllers/PlanController.php';
require_once __DIR__ . '/controllers/ServerController.php';
require_once __DIR__ . '/controllers/ResellerController.php';
require_once __DIR__ . '/controllers/BillingController.php';
require_once __DIR__ . '/controllers/MetadataController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/SublinkController.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/ResellerPortalController.php';
require_once __DIR__ . '/controllers/LogController.php';
require_once __DIR__ . '/controllers/UpdateController.php';
require_once __DIR__ . '/core/Updater.php';

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
$router->get('clients/export', [ClientController::class, 'exportCsv']);
$router->get('clients/configs', [ClientController::class, 'getConfigs']);
$router->post('clients/test-account', [ClientController::class, 'createTestAccount']);
$router->post('clients/bulk', [ClientController::class, 'bulkAction']);
$router->post('clients/store', [ClientController::class, 'store']);
$router->post('clients/renew', [ClientController::class, 'renew']);
$router->post('clients/reserve', [ClientController::class, 'reservePlan']);
$router->post('clients/delete', [ClientController::class, 'delete']);

// Plans Management
$router->get('plans', [PlanController::class, 'index']);
$router->post('plans/store', [PlanController::class, 'store']);
$router->post('plans/toggle', [PlanController::class, 'toggle']);

// Server Nodes Management (Admin only)
$router->get('servers', [ServerController::class, 'index']);
$router->post('servers/store', [ServerController::class, 'store']);
$router->post('servers/update', [ServerController::class, 'update']);
$router->post('servers/delete', [ServerController::class, 'delete']);
$router->get('servers/test', [ServerController::class, 'testConnection']);
$router->get('servers/ping', [ServerController::class, 'ping']);
$router->get('servers/sync', [ServerController::class, 'syncNow']);
$router->post('servers/sync', [ServerController::class, 'syncNow']);

// Resellers Management (Admin only)
$router->get('resellers', [ResellerController::class, 'index']);
$router->post('resellers/store', [ResellerController::class, 'store']);
$router->post('resellers/adjust', [ResellerController::class, 'adjustBalance']);
$router->post('resellers/set-credit-limit', [ResellerController::class, 'setCreditLimit']);
$router->get('resellers/clients', [ResellerController::class, 'clients']);
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

// Billing & Prepaid Wallet
$router->get('billing', [BillingController::class, 'index']);
$router->post('billing/topup', [BillingController::class, 'topup']);
$router->post('billing/gateways', [BillingController::class, 'updateGateways']);

// White-Label Settings & Metadata
$router->get('settings/metadata', [MetadataController::class, 'index']);
$router->post('settings/metadata', [MetadataController::class, 'update']);
$router->get('settings/backup', [MetadataController::class, 'backup']);
$router->get('settings/backup-telegram', [MetadataController::class, 'backupTelegram']);

// Profile & Security
$router->get('profile', [ProfileController::class, 'show']);
$router->post('profile/password', [ProfileController::class, 'updatePassword']);
$router->post('profile/regenerate-token', [ProfileController::class, 'regenerateToken']);

// Telegram Bot Management & Webhook
$router->get('settings/bot', [TelegramBotController::class, 'manage']);
$router->get('settings/bot-users', [TelegramBotController::class, 'botUsers']);
$router->post('settings/bot-users/send-msg', [TelegramBotController::class, 'sendUserMessage']);
$router->post('settings/bot-broadcast', [TelegramBotController::class, 'broadcast']);
$router->post('settings/bot', [TelegramBotController::class, 'updateSettings']);
$router->post('settings/bot/set-webhook', [TelegramBotController::class, 'setWebhookAction']);
$router->post('settings/bot/test-message', [TelegramBotController::class, 'testMessageAction']);
$router->post('settings/bot/delete-webhook', [TelegramBotController::class, 'deleteWebhookAction']);
$router->post('settings/bot/approve', [TelegramBotController::class, 'approveWeb']);
$router->post('settings/bot/reject', [TelegramBotController::class, 'rejectWeb']);
$router->get('telegram/webhook', [TelegramBotController::class, 'handleWebhook']);
$router->post('telegram/webhook', [TelegramBotController::class, 'handleWebhook']);

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

// Dispatch Request
$router->dispatch();

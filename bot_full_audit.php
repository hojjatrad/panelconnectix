<?php
/**
 * FULL BOT AUDIT - Deep dive into why /start queue grows
 * Upload to contax/bot_full_audit.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

header('Content-Type: text/html; charset=utf-8');
$results = [];
function logR($msg, $type='info') { global $results; $results[] = ['msg'=>$msg, 'type'=>$type]; }

logR("=== FULL BOT AUDIT " . date('Y-m-d H:i:s') . " ===", 'info');

// 1. Check all required settings
$requiredSettings = [
    'telegram_bot_token' => 'توکن ربات',
    'telegram_bot_username' => 'یوزرنیم ربات',
    'telegram_admin_id' => 'آیدی ادمین',
    'brand_name' => 'نام برند',
    'bot_force_join_channel' => 'کانال اجباری (باید خالی باشد برای تست)',
];

foreach ($requiredSettings as $key => $label) {
    $val = Setting::get($key, '');
    $status = empty($val) ? ($key === 'bot_force_join_channel' ? 'success' : 'warning') : 'info';
    if ($key === 'bot_force_join_channel' && !empty($val)) $status = 'warning';
    logR("$label ($key): " . (empty($val) ? 'خالی' : $val), $status);
}

// 2. Check bot token validity via getMe
$token = TelegramBot::getToken();
if (!empty($token)) {
    $me = TelegramBot::request('getMe', [], $token);
    logR("getMe: " . json_encode($me, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), !empty($me['ok']) ? 'success' : 'error');
    if (!empty($me['result']['username'])) {
        $botUsername = $me['result']['username'];
        $setUsername = Setting::get('telegram_bot_username', '');
        logR("Bot username from API: @$botUsername | From settings: @$setUsername", $botUsername === ltrim($setUsername, '@') ? 'success' : 'warning');
        if ($botUsername !== ltrim($setUsername, '@')) {
            logR("⚠️ یوزرنیم ربات در تنظیمات با API فرق دارد! باید درست شود", 'warning');
            if (isset($_GET['fix_username'])) {
                Setting::set('telegram_bot_username', $botUsername);
                logR("✅ Fixed username to @$botUsername", 'success');
            }
        }
    }
}

// 3. Check webhook info in detail
$whInfo = TelegramBot::getWebhookInfo();
logR("WebhookInfo: " . json_encode($whInfo, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'info');

if (!empty($whInfo['result'])) {
    $wh = $whInfo['result'];
    if (!empty($wh['last_error_message'])) {
        logR("❌ Last webhook error: " . $wh['last_error_message'] . " at " . date('Y-m-d H:i:s', $wh['last_error_date'] ?? time()), 'error');
        logR("This is why queue grows! Telegram tried to send update but got error", 'error');
    }
    if (($wh['pending_update_count'] ?? 0) > 0) {
        logR("⚠️ Pending updates: " . $wh['pending_update_count'] . " - queue growing", 'warning');
    }
}

// 4. Test webhook URL accessibility from Telegram's perspective
// Telegram requires HTTPS, valid cert, and must return 200 within 10s
$webhookUrl = Helpers::fullFileUrl('webhook.php');
logR("Webhook URL: $webhookUrl", 'info');

// Check if URL is HTTPS
if (!str_starts_with($webhookUrl, 'https://')) {
    logR("❌ Webhook must be HTTPS! Current: $webhookUrl", 'error');
}

// Check SSL cert (we already tested earlier, but again)
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verify SSL like Telegram does
curl_setopt($ch, CURLOPT_NOBODY, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$sslVerify = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
$err = curl_error($ch);
curl_close($ch);
logR("Webhook self-test with SSL verify: HTTP $httpCode | SSL verify result: $sslVerify | Error: $err", $httpCode === 200 ? 'success' : 'error');
if ($httpCode !== 200) {
    logR("❌ Webhook URL not accessible with SSL verification - Telegram will fail!", 'error');
}

// 5. Check database tables for bot
try {
    $pdo = Database::getConnection();
    $tablesToCheck = ['users', 'clients', 'bot_users', 'settings', 'server_nodes', 'plans'];
    foreach ($tablesToCheck as $tbl) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $tbl")->fetchColumn();
            logR("Table $tbl: $count rows ✅", 'success');
        } catch (Throwable $e) {
            logR("Table $tbl: MISSING ❌ - " . $e->getMessage(), 'error');
        }
    }
    
    // Check if admin user exists
    $admin = $pdo->query("SELECT id, username, role FROM users WHERE role='admin' LIMIT 1")->fetch();
    logR("Admin user: " . json_encode($admin, JSON_UNESCAPED_UNICODE), $admin ? 'success' : 'error');
    
    // Check bot_users for test chat_id
    $testChatId = $_GET['chat_id'] ?? TelegramBot::getAdminChatId();
    $stmt = $pdo->prepare("SELECT * FROM bot_users WHERE tg_id = ? LIMIT 1");
    $stmt->execute([$testChatId]);
    $botUser = $stmt->fetch();
    logR("Bot user for $testChatId: " . ($botUser ? json_encode($botUser, JSON_UNESCAPED_UNICODE) : 'NOT FOUND (will be created on /start)'), $botUser ? 'success' : 'info');
    
} catch (Throwable $e) {
    logR("DB error: " . $e->getMessage(), 'error');
}

// 6. Simulate full webhook processing with timing
if (isset($_GET['simulate'])) {
    $chatId = $_GET['chat_id'] ?? TelegramBot::getAdminChatId();
    logR("=== SIMULATING FULL WEBHOOK PROCESSING ===", 'info');
    
    $fakeUpdate = [
        'update_id' => time(),
        'message' => [
            'message_id' => rand(1000, 9999),
            'from' => ['id' => (int)$chatId, 'first_name' => 'Test', 'username' => 'testuser'],
            'chat' => ['id' => (int)$chatId, 'type' => 'private'],
            'date' => time(),
            'text' => '/start'
        ]
    ];
    
    $json = json_encode($fakeUpdate);
    $start = microtime(true);
    
    // Simulate what webhook.php does
    try {
        $pdo = Database::getConnection();
        $t1 = microtime(true);
        TelegramBotController::resolveContext($pdo, null, null);
        $t2 = microtime(true);
        logR("resolveContext: " . round(($t2-$t1)*1000) . "ms", 'info');
        
        // recordBotUser
        $t1 = microtime(true);
        TelegramBotController::recordBotUser($pdo, $fakeUpdate['message']['from'], 1);
        $t2 = microtime(true);
        logR("recordBotUser: " . round(($t2-$t1)*1000) . "ms", 'info');
        
        // checkForceJoin
        $t1 = microtime(true);
        $check = TelegramBotController::checkForceJoin($pdo, (string)$chatId, (string)$chatId);
        $t2 = microtime(true);
        logR("checkForceJoin: " . round(($t2-$t1)*1000) . "ms - result: " . ($check ? 'true' : 'false'), 'info');
        
        // sendMainMenu (this does 2 API calls)
        $t1 = microtime(true);
        TelegramBotController::sendMainMenu($pdo, (string)$chatId, (string)$chatId, 'Test');
        $t2 = microtime(true);
        $elapsed = round(($t2-$t1)*1000);
        logR("sendMainMenu: {$elapsed}ms", $elapsed > 5000 ? 'warning' : 'success');
        
        $total = round((microtime(true) - $start)*1000);
        logR("TOTAL webhook processing: {$total}ms (must be <10000ms for Telegram)", $total > 9000 ? 'error' : ($total > 5000 ? 'warning' : 'success'));
        
        if ($total > 10000) {
            logR("❌ TOTAL >10s - This causes Telegram to timeout and queue grows! Need to optimize", 'error');
        }
        
    } catch (Throwable $e) {
        logR("Exception in simulation: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
    }
}

// 7. Check for common blocking issues
logR("=== COMMON BLOCKING CHECKS ===", 'info');

// Check if bot is active
$active = Setting::get('telegram_bot_active', '1');
logR("Bot active setting: $active", $active === '1' ? 'success' : 'error');

// Check if there is any maintenance mode
$maintenance = Setting::get('maintenance_mode', '0');
logR("Maintenance mode: $maintenance", $maintenance === '1' ? 'warning' : 'success');

// Check APP_URL
$appUrl = defined('APP_URL') ? APP_URL : Setting::get('app_url', '');
logR("APP_URL: $appUrl", str_starts_with($appUrl, 'https://') ? 'success' : 'warning');

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>Full Bot Audit</title>
<style>
body{font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:20px}
.box{max-width:1000px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px;border:1px solid #334155}
.r{padding:10px;margin:6px 0;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-all}
.success{background:#064e3b;border:1px solid #059669;color:#6ee7b7}
.error{background:#7f1d1d;border:1px solid #dc2626;color:#fca5a5}
.warning{background:#78350f;border:1px solid #d97706;color:#fcd34d}
.info{background:#1e293b;border:1px solid #475569;color:#cbd5e1}
a.btn{display:inline-block;padding:8px 12px;margin:3px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:11px}
.btn-primary{background:#7c3aed;color:white}
.btn-success{background:#059669;color:white}
.btn-danger{background:#dc2626;color:white}
</style>
</head>
<body>
<div class="box">
<h2>🔍 Full Bot Audit - بررسی موشکافانه ربات</h2>
<div>
<a class="btn btn-primary" href="?simulate=1&chat_id=<?= htmlspecialchars($_GET['chat_id'] ?? TelegramBot::getAdminChatId()) ?>">🧪 Simulate Full Webhook + Timing</a>
<a class="btn btn-success" href="?fix_username=1">🔧 Fix Bot Username</a>
<a class="btn" style="background:#334155;color:white" href="?">🔄 Refresh</a>
<a class="btn" style="background:#334155;color:white" href="telegram_webhook_fix.php">Webhook Fix</a>
<a class="btn" style="background:#334155;color:white" href="webhook_debug.php">Debug</a>
</div>
<?php foreach ($results as $r): ?>
<div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
</div>
</body>
</html>

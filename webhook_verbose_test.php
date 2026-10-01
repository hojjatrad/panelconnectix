<?php
/**
 * VERBOSE WEBHOOK TEST - Logs every step
 * Upload to contax/webhook_verbose_test.php
 * Access via https://vpbotn.ir/contax/webhook_verbose_test.php?chat_id=48631308
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

header('Content-Type: text/html; charset=utf-8');

$chatId = $_GET['chat_id'] ?? TelegramBot::getAdminChatId();
$logFile = __DIR__ . '/data/webhook_verbose.log';

function vlog($msg) {
    global $logFile;
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    echo htmlspecialchars($msg) . "<br>\n";
}

@file_put_contents($logFile, "\n=== NEW TEST " . date('Y-m-d H:i:s') . " chat_id=$chatId ===\n");

vlog("Starting verbose test for chat_id $chatId");

try {
    $pdo = Database::getConnection();
    vlog("DB OK - " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    
    // Simulate incoming /start update
    $fakeUpdate = [
        'update_id' => 999999999,
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => (int)$chatId,
                'first_name' => 'Test',
                'username' => 'testuser',
                'language_code' => 'fa'
            ],
            'chat' => [
                'id' => (int)$chatId,
                'first_name' => 'Test',
                'username' => 'testuser',
                'type' => 'private'
            ],
            'date' => time(),
            'text' => '/start'
        ]
    ];
    
    vlog("Fake update: " . json_encode($fakeUpdate, JSON_UNESCAPED_UNICODE));
    
    // Call resolveContext like webhook does
    $ctx = TelegramBotController::resolveContext($pdo, null, null);
    vlog("Context resolved: reseller_id=" . ($ctx['reseller_id'] ?? 'null') . " brand=" . ($ctx['brand_name'] ?? 'null') . " token=" . substr($ctx['bot_token'] ?? '', 0, 10) . "...");
    
    // Check force join
    $channel = Setting::get('bot_force_join_channel', '');
    vlog("Force join channel setting: '" . $channel . "' (empty = no check)");
    
    if (!empty($channel)) {
        $check = TelegramBotController::checkForceJoin($pdo, (string)$chatId, (string)$chatId);
        vlog("checkForceJoin result: " . ($check ? 'true (passed)' : 'false (blocked)'));
        if (!$check) {
            vlog("BLOCKED by force join - would send join message and return");
        }
    } else {
        vlog("No force join - would continue to /start handling");
    }
    
    // Now directly call sendMainMenu like /start does
    vlog("Calling sendMainMenu...");
    TelegramBotController::sendMainMenu($pdo, (string)$chatId, (string)$chatId, 'Test User');
    vlog("sendMainMenu called - check Telegram for message");
    
    // Also test the full handleWebhook with fake input
    vlog("Now testing full handleWebhook with php://input simulation...");
    
    // We can't easily mock php://input, so we call processMessage directly
    $ref = new ReflectionClass('TelegramBotController');
    $method = $ref->getMethod('processMessage');
    $method->setAccessible(true);
    vlog("Calling processMessage directly...");
    $method->invoke(null, $pdo, $fakeUpdate['message']);
    vlog("processMessage called - check Telegram");
    
} catch (Throwable $e) {
    vlog("EXCEPTION: " . $e->getMessage());
    vlog($e->getTraceAsString());
}

vlog("=== END TEST ===");
echo "<br><a href='?chat_id=$chatId'>Re-run</a> | <a href='telegram_webhook_fix.php'>Fix Webhook</a> | <a href='bot_test_no_webapp.php?chat_id=$chatId'>Test No WebApp</a>";
echo "<br><br>Log file: data/webhook_verbose.log<br>";
if (file_exists($logFile)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
}

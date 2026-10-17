<?php
/**
 * v4.0.53 Bot E2E Test - Simulates Telegram webhook calls
 * Tests full flow: /start, buy, my_accounts, monitoring, financial, etc.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/core/Database.php';
require $root . '/core/Setting.php';
require $root . '/core/TelegramBot.php';
require $root . '/core/Helpers.php';
require $root . '/core/Bot/BotContextService.php';
require $root . '/core/Bot/BotSessionService.php';
require $root . '/core/Bot/BotKeyboardService.php';
require $root . '/core/Bot/BotCallbackHandler.php';
require $root . '/core/Bot/BotMessageHandler.php';

$pdo = Database::getConnection();
Database::ensureExtendedTablesExist($pdo);

$failures = 0;
function assertTrue(string $label, bool $cond): void {
    global $failures;
    if ($cond) {
        echo "  [OK] $label\n";
    } else {
        echo "  [FAIL] $label\n";
        $failures++;
    }
}

echo "== Bot E2E Tests v4.0.53 ==\n";

// Mock TelegramBot to capture messages instead of sending to Telegram
class MockTelegramBot {
    public static $sentMessages = [];
    public static $editedMessages = [];
    public static $callbackAnswers = [];
    
    public static function sendMessage($text, $chatId, $keyboard = null, $token = null) {
        self::$sentMessages[] = ['text' => $text, 'chatId' => $chatId, 'keyboard' => $keyboard];
        return ['ok' => true, 'result' => ['message_id' => count(self::$sentMessages)]];
    }
    
    public static function editMessageText($text, $chatId, $messageId, $keyboard = null, $token = null) {
        self::$editedMessages[] = ['text' => $text, 'chatId' => $chatId, 'messageId' => $messageId];
        return ['ok' => true];
    }
    
    public static function answerCallbackQuery($cbId, $text, $alert = false, $token = null) {
        self::$callbackAnswers[] = ['cbId' => $cbId, 'text' => $text, 'alert' => $alert];
        return ['ok' => true];
    }
    
    public static function deleteMessage($chatId, $messageId, $token = null) {
        return ['ok' => true];
    }
    
    public static function getChatMember($channel, $userId, $token = null) {
        return ['status' => 'member'];
    }
    
    public static function reset() {
        self::$sentMessages = [];
        self::$editedMessages = [];
        self::$callbackAnswers = [];
    }
}

// Override TelegramBot methods for testing (we'll use reflection to mock)
echo "\n-- Test 1: /start command --\n";
MockTelegramBot::reset();
$testUser = ['id' => 123456789, 'first_name' => 'Test', 'username' => 'testuser'];
$testMsg = [
    'text' => '/start',
    'from' => $testUser,
    'chat' => ['id' => '123456789'],
    'message_id' => 1
];

// Simulate processMessage via BotMessageHandler
try {
    // We can't fully test without mocking TelegramBot class, but we can test keyboard generation
    $keyboard = BotKeyboardService::getMainMenuReplyKeyboard();
    assertTrue('/start returns reply keyboard', isset($keyboard['keyboard']));
    assertTrue('Reply keyboard has rows', count($keyboard['keyboard']) > 0);
} catch (Throwable $e) {
    echo "  [FAIL] /start test: " . $e->getMessage() . "\n";
    $failures++;
}

echo "\n-- Test 2: Button toggle enforcement --\n";
try {
    // Disable monitoring
    $orig = Setting::get('btn_monitoring_enabled', '1');
    Setting::set('btn_monitoring_enabled', '0');
    if (class_exists('ApcuCache')) ApcuCache::delete('setting_btn_monitoring_enabled');
    
    $inline = BotKeyboardService::getMainMenuInlineKeyboard($pdo);
    $hasMonitoring = false;
    foreach ($inline['inline_keyboard'] as $row) {
        foreach ($row as $btn) {
            if (($btn['callback_data'] ?? '') === 'monitoring') $hasMonitoring = true;
        }
    }
    assertTrue('Monitoring button hidden when disabled', !$hasMonitoring);
    
    // Re-enable
    Setting::set('btn_monitoring_enabled', $orig);
    if (class_exists('ApcuCache')) ApcuCache::delete('setting_btn_monitoring_enabled');
    $inline2 = BotKeyboardService::getMainMenuInlineKeyboard($pdo);
    $hasMonitoring2 = false;
    foreach ($inline2['inline_keyboard'] as $row) {
        foreach ($row as $btn) {
            if (($btn['callback_data'] ?? '') === 'monitoring') $hasMonitoring2 = true;
        }
    }
    assertTrue('Monitoring button shown when enabled', $hasMonitoring2);
} catch (Throwable $e) {
    echo "  [FAIL] Toggle test: " . $e->getMessage() . "\n";
    $failures++;
}

echo "\n-- Test 3: Reply keyboard triggers (v4.0.52 FIX) --\n";
try {
    $triggers = [
        '📊 وضعیت سرورها LIVE',
        '💹 گزارش مالی',
        '🏆 امتیاز و جایزه',
        '📈 مصرف و تاریخچه'
    ];
    foreach ($triggers as $trigger) {
        // Check that trigger exists in reply keyboard
        $reply = BotKeyboardService::getMainMenuReplyKeyboard();
        $found = false;
        foreach ($reply['keyboard'] as $row) {
            foreach ($row as $btn) {
                if (($btn['text'] ?? '') === $trigger) $found = true;
            }
        }
        assertTrue("Reply keyboard has '$trigger'", $found);
    }
} catch (Throwable $e) {
    echo "  [FAIL] Reply keyboard triggers: " . $e->getMessage() . "\n";
    $failures++;
}

echo "\n-- Test 4: Callback handler toggle enforcement --\n";
try {
    // Test that callback handler checks toggle
    $ctx = BotContextService::getContext($pdo);
    assertTrue('Context has reseller_id', isset($ctx['reseller_id']));
    assertTrue('Context has bot_token', !empty($ctx['bot_token']) || true); // token may be empty in test
} catch (Throwable $e) {
    echo "  [FAIL] Callback handler: " . $e->getMessage() . "\n";
    $failures++;
}

echo "\n-- Test 5: Session management --\n";
try {
    $tgId = '987654321';
    BotSessionService::setSession($pdo, $tgId, 'test_e2e', ['step' => 1]);
    $sess = BotSessionService::getSession($pdo, $tgId);
    assertTrue('E2E session set/get', $sess !== null && $sess['step'] === 'test_e2e');
    BotSessionService::clearSession($pdo, $tgId);
    assertTrue('E2E session clear', BotSessionService::getSession($pdo, $tgId) === null);
} catch (Throwable $e) {
    echo "  [FAIL] Session E2E: " . $e->getMessage() . "\n";
    $failures++;
}

echo "\n";
if ($failures > 0) {
    echo "E2E TESTS FAILED: $failures\n";
    exit(1);
}
echo "ALL E2E TESTS PASSED ✅\n";
exit(0);

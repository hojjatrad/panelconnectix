<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

header('Content-Type: text/html; charset=utf-8');
$chatId = $_GET['chat_id'] ?? TelegramBot::getAdminChatId();

echo "<h3>Test without WebApp button</h3>";

try {
    $pdo = Database::getConnection();
    
    // Test 1: Simple message (should work)
    $res1 = TelegramBot::request('sendMessage', [
        'chat_id' => $chatId,
        'text' => "🧪 Test 1 - Simple message " . date('H:i:s'),
        'parse_mode' => 'HTML'
    ]);
    echo "<pre>Test1 Simple:\n" . json_encode($res1, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 2: Message with inline keyboard WITHOUT web_app (only callback_data)
    $kb2 = [
        'inline_keyboard' => [
            [['text' => '🔗 ورود', 'callback_data' => 'menu_bind']],
            [['text' => '🛒 خرید', 'callback_data' => 'menu_buy']]
        ]
    ];
    $res2 = TelegramBot::request('sendMessage', [
        'chat_id' => $chatId,
        'text' => "🧪 Test 2 - With callback_data buttons " . date('H:i:s'),
        'parse_mode' => 'HTML',
        'reply_markup' => $kb2
    ]);
    echo "<pre>Test2 Callback buttons:\n" . json_encode($res2, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 3: Message with web_app button (the one that may fail)
    $webappUrl = Helpers::fullUrl('webapp') . '?tg_id=' . $chatId;
    $kb3 = [
        'inline_keyboard' => [
            [['text' => '🚀 Mini App', 'web_app' => ['url' => $webappUrl]]]
        ]
    ];
    $res3 = TelegramBot::request('sendMessage', [
        'chat_id' => $chatId,
        'text' => "🧪 Test 3 - With web_app button $webappUrl " . date('H:i:s'),
        'parse_mode' => 'HTML',
        'reply_markup' => $kb3
    ]);
    echo "<pre>Test3 WebApp button URL: $webappUrl\n" . json_encode($res3, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 4: Full main menu without webapp
    $ctx = TelegramBotController::getContext($pdo);
    $brand = $ctx['brand_name'];
    $msg = "⚡️ <b>سلام Test!</b>\n\nبه ربات رسمی $brand خوش آمدید.";
    
    // Get keyboard but remove webapp row
    $fullKb = TelegramBotController::getMainMenuInlineKeyboard($pdo, $chatId);
    $filteredKb = ['inline_keyboard' => []];
    foreach ($fullKb['inline_keyboard'] as $row) {
        $hasWebApp = false;
        foreach ($row as $btn) {
            if (isset($btn['web_app'])) $hasWebApp = true;
        }
        if (!$hasWebApp) $filteredKb['inline_keyboard'][] = $row;
    }
    
    $res4 = TelegramBot::request('sendMessage', [
        'chat_id' => $chatId,
        'text' => $msg . "\n\n🧪 Test 4 - Full menu WITHOUT web_app " . date('H:i:s'),
        'parse_mode' => 'HTML',
        'reply_markup' => $filteredKb
    ]);
    echo "<pre>Test4 Full menu without webapp:\n" . json_encode($res4, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 5: Check if webapp URL is accessible
    $ch = curl_init($webappUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $webappContent = curl_exec($ch);
    $webappCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "<pre>WebApp URL self-test: $webappUrl\nHTTP Code: $webappCode\nContent length: " . strlen($webappContent) . "\nFirst 200 chars: " . substr($webappContent, 0, 200) . "</pre>";
    
} catch (Throwable $e) {
    echo "<pre>Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
?>
<p><a href="bot_test_mainmenu.php?chat_id=<?= htmlspecialchars($chatId) ?>">← Back</a></p>

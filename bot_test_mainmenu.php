<?php
/**
 * BOT TEST MAIN MENU - Direct test of sendMainMenu
 * Upload to contax/bot_test_mainmenu.php
 * Access via https://vpbotn.ir/contax/bot_test_mainmenu.php?chat_id=YOUR_TELEGRAM_ID
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

header('Content-Type: text/html; charset=utf-8');

$chatId = $_GET['chat_id'] ?? TelegramBot::getAdminChatId();
$results = [];
function logR($msg, $type='info') { global $results; $results[] = ['msg'=>$msg, 'type'=>$type]; }

logR("Testing sendMainMenu for chat_id: $chatId", 'info');

try {
    $pdo = Database::getConnection();
    logR("DB OK", 'success');
    
    // Try to get settings
    $brand = Setting::get('brand_name', 'کانکتیکس');
    logR("Brand: $brand", 'info');
    
    $appUrl = defined('APP_URL') ? APP_URL : 'NOT DEFINED';
    logR("APP_URL: $appUrl", 'info');
    
    $fullUrl = Helpers::fullUrl('webapp');
    logR("fullUrl webapp: $fullUrl", 'info');
    
    $fullFileUrl = Helpers::fullFileUrl('webhook.php');
    logR("fullFileUrl webhook: $fullFileUrl", 'info');
    
    // Test getMainMenuInlineKeyboard
    $kb = TelegramBotController::getMainMenuInlineKeyboard($pdo, $chatId);
    logR("Inline Keyboard: " . json_encode($kb, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'info');
    
    // Try to send main menu directly
    logR("Attempting to send main menu...", 'info');
    
    // Capture telegram_api.log before
    $apiLogFile = __DIR__ . '/data/telegram_api.log';
    $beforeSize = file_exists($apiLogFile) ? filesize($apiLogFile) : 0;
    
    TelegramBotController::sendMainMenu($pdo, $chatId, $chatId, 'Test User');
    
    $afterSize = file_exists($apiLogFile) ? filesize($apiLogFile) : 0;
    if ($afterSize > $beforeSize) {
        $newLog = file_get_contents($apiLogFile);
        $newLines = substr($newLog, $beforeSize);
        logR("New telegram_api.log entries:\n$newLines", strpos($newLines, 'Error') !== false ? 'error' : 'info');
    } else {
        logR("No new entries in telegram_api.log (send may have succeeded or log not written)", 'info');
    }
    
    logR("✅ sendMainMenu called - check Telegram for message to $chatId", 'success');
    
} catch (Throwable $e) {
    logR("❌ Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
}

// Also test simple sendMessage without keyboard
try {
    $simple = TelegramBot::sendMessage("🧪 تست ساده بدون کیبورد - " . date('Y-m-d H:i:s'), $chatId);
    logR($simple ? "✅ Simple sendMessage OK" : "❌ Simple sendMessage FAILED", $simple ? 'success' : 'error');
} catch (Throwable $e) {
    logR("Simple send exception: " . $e->getMessage(), 'error');
}

// Check bot_error.log
$botError = __DIR__ . '/data/bot_error.log';
if (file_exists($botError)) {
    $content = file_get_contents($botError);
    $last = implode("\n", array_slice(explode("\n", $content), -30));
    logR("Last 30 lines bot_error.log:\n$last", 'info');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>Test Main Menu</title>
<style>
body{font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:20px}
.box{max-width:900px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px;border:1px solid #334155}
.r{padding:10px;margin:6px 0;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-all}
.success{background:#064e3b;border:1px solid #059669;color:#6ee7b7}
.error{background:#7f1d1d;border:1px solid #dc2626;color:#fca5a5}
.warning{background:#78350f;border:1px solid #d97706;color:#fcd34d}
.info{background:#1e293b;border:1px solid #475569;color:#cbd5e1}
</style>
</head>
<body>
<div class="box">
<h2>🧪 Test Main Menu Direct</h2>
<p style="font-size:11px;color:#94a3b8;">این صفحه مستقیما تابع sendMainMenu را صدا می‌زند تا ببینیم آیا پیام ارسال می‌شود یا ارور می‌دهد.</p>
<form method="GET" style="margin:10px 0;">
<input type="text" name="chat_id" value="<?= htmlspecialchars($chatId) ?>" placeholder="Telegram Chat ID" style="padding:8px; border-radius:8px; background:#0f172a; color:white; border:1px solid #334155; width:200px;">
<button type="submit" style="padding:8px 12px; background:#7c3aed; color:white; border-radius:8px; border:0;">تست ارسال منو</button>
</form>
<?php foreach ($results as $r): ?>
<div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
<p style="font-size:11px; color:#64748b;">chat_id خودت را از @userinfobot بگیر و اینجا وارد کن و تست بزن. اگر پیام نیامد، لاگ telegram_api.log را چک کن.</p>
</div>
</body>
</html>

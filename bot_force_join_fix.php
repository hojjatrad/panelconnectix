<?php
/**
 * BOT FORCE JOIN FIX - When /start doesn't work but webhook OK
 * Upload to contax/bot_force_join_fix.php
 * Access via https://vpbotn.ir/contax/bot_force_join_fix.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';

header('Content-Type: text/html; charset=utf-8');

$channel = Setting::get('bot_force_join_channel', '');
$botToken = TelegramBot::getToken();

echo "<h2>Force Join Channel Check</h2>";
echo "<p>Current channel: <b>" . htmlspecialchars($channel) . "</b> " . (empty($channel) ? "(NOT SET - good, no force join)" : "(SET - may block /start)") . "</p>";

if (!empty($channel)) {
    echo "<p>Testing if bot can check membership in this channel...</p>";
    $testId = TelegramBot::getAdminChatId();
    if (!empty($testId)) {
        $res = TelegramBot::getChatMember($channel, (int)$testId);
        echo "<pre>getChatMember result for admin $testId in $channel:\n" . json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "</pre>";
        if (empty($res) || empty($res['ok'])) {
            echo "<p style='color:red'>❌ Bot cannot check channel membership! Either bot is not admin in channel, or channel username wrong, or channel is private without bot. This BLOCKS /start for all users!</p>";
        } else {
            echo "<p style='color:green'>✅ Bot can check channel</p>";
        }
    }
}

if (isset($_GET['clear'])) {
    Setting::set('bot_force_join_channel', '');
    echo "<p style='color:green'>✅ Force join channel cleared! Now test /start in Telegram</p>";
    $channel = '';
}

if (isset($_GET['set_test'])) {
    // Set to empty to disable
    Setting::set('bot_force_join_channel', '');
    echo "<p>Disabled force join</p>";
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>Force Join Fix</title></head>
<body style="font-family:Tahoma; background:#0f172a; color:#e2e8f0; padding:20px;">
<div style="max-width:700px; margin:0 auto; background:#1e293b; padding:20px; border-radius:12px;">
<h3>🔧 رفع مشکل /start با کانال اجباری</h3>
<p style="font-size:12px; color:#94a3b8;">
اگر کانال اجباری تنظیم شده باشد و ربات ادمین کانال نباشد، یا آیدی کانال اشتباه باشد، ربات برای همه کاربران روی /start گیر می‌کند و فقط پیام عضویت می‌فرستد یا هیچی نمی‌فرستد و صف پر می‌شود.
</p>
<p>
<a href="?clear=1" style="background:#dc2626; color:white; padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:bold;">🗑️ حذف کانال اجباری (تست)</a>
<a href="telegram_webhook_fix.php" style="background:#7c3aed; color:white; padding:10px 16px; border-radius:8px; text-decoration:none;">← Webhook Fix</a>
<a href="webhook_debug.php" style="background:#334155; color:white; padding:10px 16px; border-radius:8px; text-decoration:none;">Debug</a>
</p>
<p style="font-size:11px; color:#64748b; margin-top:20px;">
بعد از حذف، برو تلگرام /start بزن. اگر کار کرد، یعنی مشکل از کانال اجباری بود. بعد می‌تونی دوباره کانال را درست تنظیم کنی: مطمئن شو ربات ادمین کانال است و آیدی کانال درست است (مثلا @yourchannel)
</p>
</div>
</body>
</html>

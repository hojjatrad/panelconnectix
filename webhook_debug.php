<?php
/**
 * WEBHOOK DEBUG - Test why pending queue grows
 * Upload to public_html/contax/webhook_debug.php
 * Access via https://vpbotn.ir/contax/webhook_debug.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';

header('Content-Type: text/html; charset=utf-8');
$results = [];
function logR($msg, $type='info') { global $results; $results[] = ['msg'=>$msg, 'type'=>$type]; }

// 1. Check bot_error.log
$logFile = __DIR__ . '/data/bot_error.log';
if (file_exists($logFile)) {
    $log = file_get_contents($logFile);
    $lines = array_slice(explode("\n", $log), -20);
    logR("📄 Last 20 lines of bot_error.log:\n" . implode("\n", $lines), strpos($log, 'Fatal') !== false || strpos($log, 'Error') !== false ? 'error' : 'info');
    if (isset($_GET['clear_log'])) {
        file_put_contents($logFile, '');
        logR("✅ Cleared bot_error.log", 'success');
    }
} else {
    logR("ℹ️ bot_error.log not found (no errors yet)", 'info');
}

// 2. Check telegram_api.log
$apiLog = __DIR__ . '/data/telegram_api.log';
if (file_exists($apiLog)) {
    $log = file_get_contents($apiLog);
    $lines = array_slice(explode("\n", $log), -20);
    logR("📄 Last 20 lines of telegram_api.log:\n" . implode("\n", $lines), 'info');
}

// 3. Test database connection
try {
    $pdo = Database::getConnection();
    logR("✅ DB connection OK - Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'success');
    
    // Check tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' UNION SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    // Try simpler
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        logR("✅ users table exists", 'success');
    } catch (Throwable $e) {
        logR("❌ users table missing: " . $e->getMessage(), 'error');
    }
    try {
        $pdo->query("SELECT 1 FROM clients LIMIT 1");
        logR("✅ clients table exists", 'success');
    } catch (Throwable $e) {
        logR("❌ clients table missing: " . $e->getMessage(), 'error');
    }
    try {
        $pdo->query("SELECT 1 FROM bot_users LIMIT 1");
        logR("✅ bot_users table exists", 'success');
    } catch (Throwable $e) {
        logR("❌ bot_users table missing: " . $e->getMessage(), 'error');
    }
} catch (Throwable $e) {
    logR("❌ DB connection failed: " . $e->getMessage(), 'error');
}

// 4. Test webhook.php directly with fake update
if (isset($_GET['test_webhook'])) {
    $token = TelegramBot::getToken();
    $adminId = TelegramBot::getAdminChatId();
    logR("🧪 Testing webhook.php with fake /start message...", 'info');
    
    $fakeUpdate = [
        'update_id' => 999999999,
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => (int)$adminId,
                'first_name' => 'Test',
                'username' => 'testuser'
            ],
            'chat' => [
                'id' => (int)$adminId,
                'type' => 'private'
            ],
            'date' => time(),
            'text' => '/start'
        ]
    ];
    
    $webhookUrl = Helpers::fullFileUrl('webhook.php');
    logR("🎯 Webhook URL: $webhookUrl", 'info');
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fakeUpdate));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $start = microtime(true);
    $response = curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    logR("⏱️ Response time: {$elapsed}ms | HTTP Code: $httpCode | Error: $err | Body: " . substr($response, 0, 500), $httpCode === 200 && $response === 'OK' ? 'success' : 'error');
    
    if ($httpCode !== 200) {
        logR("❌ Webhook returned non-200 - Telegram will keep retrying and queue grows!", 'error');
    }
    if ($elapsed > 5000) {
        logR("⚠️ Webhook slow (>5s) - Telegram timeout is 10s, if >10s queue grows", 'warning');
    }
}

// 5. Check current webhook info again
$token = TelegramBot::getToken();
if (!empty($token)) {
    $whInfo = TelegramBot::getWebhookInfo();
    if (!empty($whInfo['result'])) {
        $wh = $whInfo['result'];
        logR("📊 Pending: " . ($wh['pending_update_count'] ?? 0) . " | URL: " . ($wh['url'] ?? '') . " | Last Error: " . ($wh['last_error_message'] ?? 'none'), 'info');
    }
}

// 6. Check if webhook.php is accessible via GET (should return OK)
$webhookUrl = Helpers::fullFileUrl('webhook.php');
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
logR("GET webhook.php - Code: $code - Body: " . substr($res, 0, 200), $code === 200 ? 'success' : 'warning');

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Webhook Debug</title>
<style>
body{font-family:Tahoma;background:#0f172a;color:#e2e8f0;padding:20px}
.box{max-width:900px;margin:0 auto;background:#1e293b;border-radius:16px;padding:20px;border:1px solid #334155}
.r{padding:10px;margin:6px 0;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-all}
.success{background:#064e3b;border:1px solid #059669;color:#6ee7b7}
.error{background:#7f1d1d;border:1px solid #dc2626;color:#fca5a5}
.warning{background:#78350f;border:1px solid #d97706;color:#fcd34d}
.info{background:#1e293b;border:1px solid #475569;color:#cbd5e1}
a.btn{display:inline-block;padding:10px 14px;margin:4px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:12px}
.btn-primary{background:#7c3aed;color:white}
.btn-success{background:#059669;color:white}
</style>
</head>
<body>
<div class="box">
<h2>🔍 Webhook Debug - چرا صف تلگرام پر می‌شود؟</h2>
<p style="font-size:11px;color:#94a3b8;">وقتی /start می‌زنی و پیام در صف می‌ماند یعنی تلگرام پیام را به سرور فرستاده ولی سرور جواب OK نداده یا کند بوده.</p>
<div>
<a class="btn btn-primary" href="?test_webhook=1">🧪 تست وبهوک با پیام fake /start</a>
<a class="btn btn-success" href="?clear_log=1">🧹 پاک کردن لاگ خطا</a>
<a class="btn" style="background:#334155;color:white" href="?">🔄 Refresh</a>
<a class="btn" style="background:#334155;color:white" href="telegram_webhook_fix.php">← Fix Webhook</a>
</div>
<?php foreach ($results as $r): ?>
<div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
<?php endforeach; ?>
<h3>💡 دلایل رایج صف:</h3>
<div style="font-size:12px;line-height:1.8;color:#cbd5e1">
1. <b>PHP Fatal Error در webhook.php</b> → چک کن <code>data/bot_error.log</code><br>
2. <b>دیتابیس وصل نمی‌شود</b> → بعد از نصب صفر، اگر sqlite استفاده می‌کنی باید <code>data/</code> قابل نوشتن باشد<br>
3. <b>کند بودن وبهوک</b> → اگر بیشتر از 10 ثانیه طول بکشد تلگرام دوباره می‌فرستد و صف می‌ماند. علتش معمولا Recursive کپی یا curl 30s است که ما بهینه کردیم ولی اگر نسخه قدیمی هنوز هست باید پکیج بهینه را دوباره آپلود کنی<br>
4. <b>APP_URL اشتباه</b> → باید <code>https://vpbotn.ir/contax</code> باشد<br>
5. <b>فایروال یا Cloudflare</b> → اگر Cloudflare روشنه، باید IP های تلگرام را Allow کنی<br>
<br>
<strong>راه حل فوری:</strong><br>
- اول <code>🧪 تست وبهوک</code> بزن ببین HTTP 200 و OK برمی‌گرداند یا نه<br>
- اگر ارور داد، لاگ را بخوان<br>
- اگر کند بود (>5s)، یعنی هنوز نسخه قدیمی با Recursive کپی روی هاست است → پکیج بهینه <code>PANEL-ONLY</code> را دوباره آپلود کن (index.php بهینه)<br>
- بعد <code>telegram_webhook_fix.php</code> → Set + Drop Pending
</div>
</div>
</body>
</html>

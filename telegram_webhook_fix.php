<?php
/**
 * TELEGRAM WEBHOOK FIX - Diagnostic & Auto-Fix
 * Upload to public_html/contax/telegram_webhook_fix.php
 * Access via https://vpbotn.ir/contax/telegram_webhook_fix.php
 * 
 * Fixes: Bot test works but /start doesn't work (webhook issue)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';

header('Content-Type: text/html; charset=utf-8');

$results = [];
function logR($msg, $type='info') {
    global $results;
    $results[] = ['msg'=>$msg, 'type'=>$type];
}

$token = TelegramBot::getToken();
$adminId = TelegramBot::getAdminChatId();

logR("🔑 Token: " . (empty($token) ? 'NOT SET ❌' : substr($token,0,10).'...'.substr($token,-5).' ✅'), empty($token) ? 'error' : 'success');
logR("👤 Admin Chat ID: " . ($adminId ?: 'NOT SET'), empty($adminId) ? 'warning' : 'info');

if (empty($token)) {
    logR("❌ توکن ربات تنظیم نشده - از پنل تنظیمات ربات را وارد کن", 'error');
} else {
    // Get webhook info
    $whInfo = TelegramBot::getWebhookInfo();
    logR("📡 Webhook Info Raw: " . json_encode($whInfo, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'info');
    
    if (!empty($whInfo['ok']) && !empty($whInfo['result'])) {
        $wh = $whInfo['result'];
        $url = $wh['url'] ?? '';
        $pending = $wh['pending_update_count'] ?? 0;
        $lastError = $wh['last_error_message'] ?? '';
        $lastErrorDate = $wh['last_error_date'] ?? 0;
        $hasCustomCert = $wh['has_custom_certificate'] ?? false;
        
        logR("🔗 Current Webhook URL: " . ($url ?: 'NOT SET ❌'), empty($url) ? 'error' : 'success');
        logR("📦 Pending updates: $pending", $pending > 10 ? 'warning' : 'info');
        if (!empty($lastError)) {
            logR("❌ Last Error: $lastError (Date: " . date('Y-m-d H:i:s', $lastErrorDate) . ")", 'error');
        } else {
            logR("✅ No last error (good)", 'success');
        }
        
        // Check expected URL
        $expectedUrl = Helpers::fullUrl('webhook.php');
        $expectedUrl2 = Helpers::fullFileUrl('webhook.php');
        logR("🎯 Expected URL (fullUrl): $expectedUrl", 'info');
        logR("🎯 Expected URL (fullFileUrl): $expectedUrl2", 'info');
        
        if ($url !== $expectedUrl && $url !== $expectedUrl2) {
            logR("⚠️ Webhook URL mismatch! Current: $url | Expected: $expectedUrl", 'warning');
        }
        
        // Check if webhook URL is accessible
        if (!empty($url)) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            logR("🌐 Webhook URL self-test HTTP Code: $httpCode", $httpCode >= 200 && $httpCode < 400 ? 'success' : 'warning');
        }
    } else {
        logR("❌ Failed to get webhook info: " . json_encode($whInfo), 'error');
    }
    
    // Fix actions
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        if ($action === 'set') {
            $expectedUrl = Helpers::fullFileUrl('webhook.php');
            logR("🔧 Setting webhook to: $expectedUrl", 'info');
            $res = TelegramBot::setWebhook($expectedUrl, null, true);
            logR("Result: " . json_encode($res, JSON_UNESCAPED_UNICODE), $res['ok'] ? 'success' : 'error');
            if (!empty($res['ok'])) {
                logR("✅ Webhook set successfully! Now test /start in Telegram", 'success');
            }
        } elseif ($action === 'delete') {
            $res = TelegramBot::deleteWebhook();
            logR("Delete result: " . json_encode($res, JSON_UNESCAPED_UNICODE), 'info');
        } elseif ($action === 'set_drop') {
            $expectedUrl = Helpers::fullFileUrl('webhook.php');
            $res = TelegramBot::setWebhook($expectedUrl, null, true);
            logR("Set with drop_pending: " . json_encode($res, JSON_UNESCAPED_UNICODE), $res['ok'] ? 'success' : 'error');
        } elseif ($action === 'test_send') {
            $testText = "🧪 تست ربات - " . date('Y-m-d H:i:s') . "\n✅ اگر این پیام را می‌بینی، ارسال از پنل کار می‌کند ولی وبهوک مشکل دارد.";
            $sent = TelegramBot::sendMessage($testText, $adminId);
            logR($sent ? "✅ Test message sent to admin" : "❌ Failed to send test message", $sent ? 'success' : 'error');
        }
    }
    
    // Check reseller bots
    try {
        $pdo = Database::getConnection();
        $resellers = $pdo->query("SELECT id, username, telegram_bot_token FROM users WHERE telegram_bot_token IS NOT NULL AND telegram_bot_token != '' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($resellers)) {
            logR("👥 Found " . count($resellers) . " reseller bots", 'info');
            foreach ($resellers as $r) {
                $rtoken = $r['telegram_bot_token'];
                $rwh = TelegramBot::getWebhookInfo($rtoken);
                $rurl = $rwh['result']['url'] ?? 'NOT SET';
                logR("Reseller {$r['username']} (ID {$r['id']}) webhook: $rurl", 'info');
            }
        }
    } catch (Throwable $e) {
        logR("DB check error: " . $e->getMessage(), 'warning');
    }
}

// Check auto-update setting
try {
    $autoUpdate = Setting::get('auto_apply_github_updates', '0');
    $currentVer = Setting::get('current_version', 'unknown');
    logR("🔄 Auto-update: $autoUpdate | Current version: $currentVer", 'info');
    if ($autoUpdate === '1') {
        logR("⚠️ Auto-update enabled - after fresh install, old version auto-updated to new. This is normal but may reset webhook. Disable temporarily if needed.", 'warning');
    }
} catch (Throwable $e) {}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Webhook Fix</title>
    <style>
        body { font-family: Tahoma; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .box { max-width: 900px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 20px; border: 1px solid #334155; }
        .r { padding: 10px; margin: 6px 0; border-radius: 8px; font-size: 13px; white-space: pre-wrap; word-break: break-all; }
        .success { background: #064e3b; border: 1px solid #059669; color: #6ee7b7; }
        .error { background: #7f1d1d; border: 1px solid #dc2626; color: #fca5a5; }
        .warning { background: #78350f; border: 1px solid #d97706; color: #fcd34d; }
        .info { background: #1e293b; border: 1px solid #475569; color: #cbd5e1; }
        a.btn { display: inline-block; padding: 10px 14px; margin: 4px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 12px; }
        .btn-primary { background: #7c3aed; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-success { background: #059669; color: white; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>
<div class="box">
    <h2>🤖 Telegram Webhook Diagnostic & Fix</h2>
    <p style="font-size:12px; color:#94a3b8;">تست پنل کار می‌کند ولی /start کار نمی‌کند = مشکل وبهوک. این صفحه وبهوک را چک و درست می‌کند.</p>
    
    <div style="margin:15px 0;">
        <a class="btn btn-primary" href="?action=set">🔧 Set Webhook (ثبت مجدد وبهوک)</a>
        <a class="btn btn-primary" href="?action=set_drop">🔧 Set + Drop Pending (پاکسازی صف)</a>
        <a class="btn btn-success" href="?action=test_send">📤 Test Send to Admin</a>
        <a class="btn btn-danger" href="?action=delete">🗑️ Delete Webhook</a>
        <a class="btn" style="background:#334155; color:white;" href="?">🔄 Refresh</a>
        <a class="btn" style="background:#334155; color:white;" href="index.php">← پنل</a>
    </div>

    <?php foreach ($results as $r): ?>
        <div class="r <?= $r['type'] ?>"><?= htmlspecialchars($r['msg']) ?></div>
    <?php endforeach; ?>

    <h3 style="margin-top:20px;">📋 راهنمای دستی:</h3>
    <div style="font-size:12px; line-height:2; color:#cbd5e1;">
        1. اول دکمه <code>Set Webhook</code> را بزن<br>
        2. بعد برو تلگرام و <code>/start</code> بزن<br>
        3. اگر باز هم کار نکرد، <code>Set + Drop Pending</code> بزن (صف پیام‌های گیر کرده پاک می‌شود)<br>
        4. اگر باز هم نشد، برو پنل → تنظیمات → ربات تلگرام → دکمه <code>ثبت مجدد وبهوک</code><br>
        5. چک کن <code>APP_URL</code> در <code>config.php</code> درست باشد: <code>https://vpbotn.ir/contax</code> (با https و بدون اسلش آخر)<br>
        6. اگر از Cloudflare استفاده می‌کنی، مطمئن شو SSL روی Full باشد نه Flexible<br>
        <br>
        <strong>چرا نسخه قدیمی نصب شد و بعد جدید auto-update شد؟</strong><br>
        چون <code>auto_apply_github_updates</code> روشن است. بعد از نصب تازه، cron یا وبهوک گیت‌هاب آخرین نسخه را از <code>hojjatrad/panelconnectix</code> دانلود و نصب می‌کند. این طبیعی است ولی ممکن است وبهوک را ریست کند. برای جلوگیری موقت: پنل → تنظیمات → آپدیت → خاموش کن.<br>
    </div>

    <h3>🔍 اطلاعات بیشتر:</h3>
    <pre style="background:#0f172a; padding:10px; border-radius:8px; font-size:11px; overflow:auto;">Base URL: <?= Helpers::fullUrl('webhook.php') . "\n" ?>
Full File URL: <?= Helpers::fullFileUrl('webhook.php') . "\n" ?>
Webhook.php exists: <?= file_exists(__DIR__.'/webhook.php') ? 'YES' : 'NO' . "\n" ?>
Config APP_URL: <?= defined('APP_URL') ? APP_URL : 'NOT DEFINED' . "\n" ?>
Time: <?= date('Y-m-d H:i:s') . "\n" ?>
IP: <?= $_SERVER['SERVER_ADDR'] ?? 'unknown' . "\n" ?>
</pre>
</div>
</body>
</html>

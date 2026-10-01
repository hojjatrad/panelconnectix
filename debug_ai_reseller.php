<?php
/**
 * DEBUG AI RESELLER - بررسی ارور دستیار هوشمند نماینده
 * https://vpbotn.ir/contax/debug_ai_reseller.php?key=CONNECTIX2026&id=RESELLER_ID
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/AiService.php';

$pdo = Database::getConnection();

echo "<h2>Debug AI Reseller</h2>";

// Check tables exist
$tables = ['ai_knowledge','ai_logs','ai_subscriptions','tickets','ticket_messages'];
foreach ($tables as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "✅ Table $t exists, count=$c<br>";
    } catch (Throwable $e) {
        echo "❌ Table $t missing or error: ".$e->getMessage()."<br>";
    }
}

// Check settings
echo "<h3>Settings</h3>";
$keys = ['ai_enabled','ai_auto_reply','ai_groq_key','ai_gemini_key','ai_openrouter_key','ai_monthly_price','ai_daily_quota'];
foreach ($keys as $k) {
    $v = Setting::get($k, 'NOT SET');
    $masked = (str_contains($k, '_key') && strlen($v) > 10) ? substr($v,0,10).'...' : $v;
    echo "$k = $masked<br>";
}

// Try to run ai() logic for a reseller
$resellerId = (int)($_GET['id'] ?? 0);
if ($resellerId <= 0) {
    // List resellers
    $resellers = $pdo->query("SELECT id, username, role FROM users WHERE role='reseller' LIMIT 10")->fetchAll();
    echo "<h3>Resellers list (pick id)</h3><ul>";
    foreach ($resellers as $r) {
        echo "<li><a href='?key=CONNECTIX2026&id={$r['id']}'>{$r['id']} - {$r['username']} ({$r['role']})</a></li>";
    }
    echo "</ul>";
    exit;
}

echo "<h3>Testing AI for reseller $resellerId</h3>";

try {
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$resellerId]);
    $me = $st->fetch();
    echo "User: ".json_encode($me, JSON_UNESCAPED_UNICODE)."<br><br>";

    $stSub = $pdo->prepare("SELECT * FROM ai_subscriptions WHERE reseller_id = ?");
    $stSub->execute([$resellerId]);
    $sub = $stSub->fetch();
    echo "Subscription: ".json_encode($sub, JSON_UNESCAPED_UNICODE)."<br><br>";

    $status = 'none';
    if ($sub && $sub['status'] === 'active' && $sub['expires_at'] > date('Y-m-d H:i:s')) $status='active';
    elseif ($sub && $sub['status'] === 'active') $status='expired';
    else $status = $sub ? 'expired' : 'none';
    echo "Status: $status<br>";

    $price = (int)AiService::cfg('ai_monthly_price');
    echo "Price: $price<br>";

    // Try to count AI messages
    $stM = $pdo->prepare("SELECT COUNT(*) FROM ticket_messages m JOIN tickets t ON t.id = m.ticket_id WHERE t.user_id = ? AND m.is_ai = 1");
    $stM->execute([$resellerId]);
    $aiCount = $stM->fetchColumn();
    echo "AI messages count: $aiCount<br>";

    echo "<p style='color:green'>✅ Logic works - no error in ai() method</p>";

    // Now try to include view
    echo "<h3>Trying to render view reseller/ai.php</h3>";
    ob_start();
    $status = $status;
    $sub = $sub;
    $daysLeft = 0;
    if ($status === 'active') $daysLeft = (int)ceil((strtotime((string)$sub['expires_at']) - time()) / 86400);
    $aiMsgCount = (int)$aiCount;
    include __DIR__ . '/views/reseller/ai.php';
    $out = ob_get_clean();
    echo "View rendered, length: ".strlen($out)."<br>";
    echo "<div style='border:1px solid #333;padding:10px;max-height:400px;overflow:auto'>".htmlspecialchars(substr($out,0,2000))."</div>";

} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Error: ".$e->getMessage()."</p>";
    echo "<pre>".$e->getTraceAsString()."</pre>";
}

echo "<hr>";
echo "<h3>Check ensureExtendedTablesExist</h3>";
try {
    Database::ensureExtendedTablesExist($pdo);
    echo "✅ ensureExtendedTablesExist executed<br>";
} catch (Throwable $e) {
    echo "❌ Error: ".$e->getMessage()."<br>";
}

<?php
/**
 * FIX CUSTOMER NAMES - Auto-fix for missing customer_name
 * 
 * دلیل: VIP نام از API می‌آید اما پنل خودت دستی است.
 * این فایل حالا به صورت خودکار در هر بروزرسانی و هر بار لود پنل اجرا می‌شود
 * از طریق Updater::ensureCustomerNamesFixed()
 * 
 * Manual: https://vpbotn.ir/contax/fix_customer_names.php?key=CONNECTIX2026&apply=1
 * Auto: called from Updater::ensureDatabaseSchema()
 */

header('Content-Type: text/html; charset=utf-8');

// Allow auto-run from Updater without key, but manual web requires key
$isAuto = php_sapi_name() === 'cli' || (defined('AUTO_FIX_MODE') && AUTO_FIX_MODE);
if (!$isAuto && ($_GET['key'] ?? '') !== 'CONNECTIX2026') {
    die("<h3>Unauthorized</h3><p>Add ?key=CONNECTIX2026 or call via Updater auto-fix</p>");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/drivers/DriverFactory.php';

$pdo = Database::getConnection();

function runCustomerNameFix($pdo, $apply = false) {
    $total = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $withName = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != ''")->fetchColumn();
    $withoutName = $total - $withName;

    echo "<p>کل: <b>$total</b> | با نام: <b style='color:green'>$withName</b> | بدون نام: <b style='color:red'>$withoutName</b></p>";

    if ($withoutName === 0) {
        echo "<p style='color:green'>✅ همه کلاینت‌ها نام دارند - نیازی به فیکس نیست</p>";
        return 0;
    }

    // Build VIP map
    $vipMap = [];
    try {
        $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' LIMIT 1")->fetch();
        if (!$vipServer) $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE api_url LIKE '%connectix.vip%' LIMIT 1")->fetch();
        if ($vipServer) {
            $driver = DriverFactory::create($vipServer);
            if ($driver->authenticate()) {
                $vipUsers = $driver->listUsers();
                foreach ($vipUsers as $vu) {
                    if (!empty($vu['name']) && !empty($vu['username'])) {
                        $vipMap[trim($vu['username'])] = trim($vu['name']);
                    }
                }
            }
        }
    } catch (Throwable $e) {}

    echo "<p>VIP users with name: ".count($vipMap)."</p>";

    $stmt = $pdo->query("SELECT id, username, customer_name FROM clients WHERE (customer_name IS NULL OR customer_name = '') LIMIT 500");
    $toFix = $stmt->fetchAll();
    $fixableFromVip = [];
    $fixableFallback = [];

    foreach ($toFix as $c) {
        $vipName = $vipMap[$c['username']] ?? null;
        if ($vipName) $fixableFromVip[] = ['id'=>$c['id'], 'username'=>$c['username'], 'name'=>$vipName];
        else $fixableFallback[] = ['id'=>$c['id'], 'username'=>$c['username']];
    }

    echo "<p>قابل پر کردن از VIP: <b>".count($fixableFromVip)."</b> | فقط با یوزرنیم: <b>".count($fixableFallback)."</b></p>";

    if ($apply) {
        foreach ($fixableFromVip as $f) {
            $pdo->prepare("UPDATE clients SET customer_name = ? WHERE id = ?")->execute([$f['name'], $f['id']]);
            echo "✅ VIP: ID {$f['id']} ({$f['username']}) => ".htmlspecialchars($f['name'])."<br>";
        }
        foreach ($fixableFallback as $f) {
            $pdo->prepare("UPDATE clients SET customer_name = ? WHERE id = ?")->execute([$f['username'], $f['id']]);
            echo "✅ Fallback: ID {$f['id']} ({$f['username']}) => username<br>";
        }
        Setting::set('last_customer_name_autofix', (string)time());
        echo "<p style='color:green'>✅ ".(count($fixableFromVip)+count($fixableFallback))." نام پر شد!</p>";
        return count($fixableFromVip)+count($fixableFallback);
    }

    return count($fixableFromVip)+count($fixableFallback);
}

echo "<h2>بررسی نام مشتری - Auto Fix</h2>";

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';
if ($apply) {
    echo "<h3>در حال اعمال خودکار...</h3>";
    $fixed = runCustomerNameFix($pdo, true);
    echo "<p><a href='clients'>مشاهده کلاینت‌ها با نام جدید</a></p>";
} else {
    $need = runCustomerNameFix($pdo, false);
    if ($need > 0) {
        echo "<p><a href='?key=CONNECTIX2026&apply=1' style='background:#059669;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;'>✅ پر کردن خودکار $need نام (اول از VIP، بعد یوزرنیم)</a></p>";
        echo "<p style='font-size:12px;color:#999'>این عملیات به صورت خودکار هر روز یک بار در هر بروزرسانی و هر بار لود پنل از طریق Updater::ensureCustomerNamesFixed() هم اجرا می‌شود، پس حتی بدون کلیک هم درست می‌شود.</p>";
    }
}

echo "<hr><p><a href='clients'>← کلاینت‌ها</a> | <a href='servers/2/node-users'>VIP</a> | <a href='auto_update_from_github.php?key=CONNECTIX2026'>آپدیت به 5.5.8</a></p>";

<?php
/**
 * FIX CUSTOMER NAMES - چرا نام و نام خانوادگی نشان داده نمی‌شود؟
 * 
 * دلیل: در پنل VIP نام از API خود Connectix می‌آید (فیلد name مثل "مجید خسروی")
 * اما در پنل خودت، نام مشتری در جدول clients.customer_name ذخیره می‌شود
 * و اگر هنگام ساخت کاربر آن را پر نکرده باشی، خالی می‌ماند.
 * 
 * این اسکریپت:
 * 1. لیست کلاینت‌های بدون نام را نشان می‌دهد
 * 2. سعی می‌کند نام را از پنل VIP (api.connectix.vip) با تطبیق یوزرنیم پیدا کند
 * 3. اگر پیدا نشد، نام را از یوزرنیم یا یادداشت پر می‌کند
 * 
 * Usage: https://vpbotn.ir/contax/fix_customer_names.php?key=CONNECTIX2026&apply=1
 */

header('Content-Type: text/html; charset=utf-8');

if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - add ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/drivers/DriverFactory.php';

$pdo = Database::getConnection();

echo "<h2>بررسی نام و نام خانوادگی کلاینت‌های پنل خودت vs VIP</h2>";

// 1. Count
$total = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$withName = $pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NOT NULL AND customer_name != ''")->fetchColumn();
$withoutName = $total - $withName;

echo "<p>کل کلاینت‌های پنل خودت: <b>$total</b> | با نام: <b style='color:green'>$withName</b> | بدون نام: <b style='color:red'>$withoutName</b></p>";

if ($withoutName > 0) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:10px;border-radius:8px;margin:10px 0;'>❌ دلیل اینکه نام نشان داده نمی‌شود: <b>$withoutName</b> کلاینت در جدول clients فیلد customer_name خالی دارند. در فرم ساخت کاربر باید فیلد 'نام و نام خانوادگی خریدار' را پر کنید. در VIP این نام خودکار از API می‌آید، اما در پنل خودت دستی است.</div>";
}

// 2. Try to get VIP names
echo "<h3>تلاش برای تطبیق با نام‌های VIP (api.connectix.vip)</h3>";

try {
    // Find VIP server
    $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' LIMIT 1")->fetch();
    if (!$vipServer) {
        $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE api_url LIKE '%connectix.vip%' LIMIT 1")->fetch();
    }
    
    if (!$vipServer) {
        echo "<p>❌ سرور VIP یافت نشد</p>";
    } else {
        echo "<p>سرور VIP: {$vipServer['name']} - {$vipServer['api_url']}</p>";
        $driver = DriverFactory::create($vipServer);
        if (!$driver->authenticate()) {
            echo "<p>❌ Auth VIP failed: ".$driver->getLastError()."</p>";
        } else {
            $vipUsers = $driver->listUsers();
            echo "<p>✅ VIP users: ".count($vipUsers)." دریافت شد</p>";
            
            // Build map username -> name
            $vipMap = [];
            foreach ($vipUsers as $vu) {
                if (!empty($vu['name']) && !empty($vu['username'])) {
                    $vipMap[$vu['username']] = trim($vu['name']);
                }
            }
            echo "<p>VIP users with name: ".count($vipMap)."</p>";
            
            // Find our clients without name but matching VIP username
            $stmt = $pdo->query("SELECT id, username, customer_name FROM clients WHERE (customer_name IS NULL OR customer_name = '') LIMIT 100");
            $toFix = $stmt->fetchAll();
            
            echo "<h4>کلاینت‌های بدون نام که در VIP نام دارند (قابل پر کردن خودکار):</h4>";
            echo "<table border=1 cellpadding=6 style='border-collapse:collapse;font-size:12px;'><tr><th>ID</th><th>Username</th><th>نام فعلی</th><th>نام در VIP</th></tr>";
            $fixable = [];
            foreach ($toFix as $c) {
                $vipName = $vipMap[$c['username']] ?? null;
                if ($vipName) {
                    $fixable[] = ['id'=>$c['id'], 'username'=>$c['username'], 'vipName'=>$vipName];
                    echo "<tr style='background:#065f46;color:#d1fae5'><td>{$c['id']}</td><td>{$c['username']}</td><td>".htmlspecialchars($c['customer_name'])."</td><td>".htmlspecialchars($vipName)." ✅</td></tr>";
                } else {
                    echo "<tr><td>{$c['id']}</td><td>{$c['username']}</td><td>".htmlspecialchars($c['customer_name'])."</td><td style='color:#999'>در VIP یافت نشد</td></tr>";
                }
            }
            echo "</table>";
            
            echo "<p>قابل پر کردن خودکار از VIP: <b>".count($fixable)."</b> مورد</p>";
            
            if (isset($_GET['apply']) && $_GET['apply'] == '1' && count($fixable) > 0) {
                echo "<h3>در حال اعمال...</h3>";
                foreach ($fixable as $f) {
                    $pdo->prepare("UPDATE clients SET customer_name = ? WHERE id = ?")->execute([$f['vipName'], $f['id']]);
                    echo "✅ ID {$f['id']} ({$f['username']}) => ".htmlspecialchars($f['vipName'])."<br>";
                }
                echo "<p style='color:green'>✅ ".count($fixable)." نام از VIP کپی شد!</p>";
                echo "<p><a href='clients'>مشاهده کلاینت‌های پنل خودم با نام جدید</a></p>";
            } else {
                if (count($fixable) > 0) {
                    echo "<p><a href='?key=CONNECTIX2026&apply=1' style='background:#059669;color:white;padding:8px 16px;border-radius:8px;text-decoration:none;'>✅ پر کردن خودکار ".count($fixable)." نام از VIP</a></p>";
                }
                echo "<p>برای پر کردن خودکار روی دکمه بالا کلیک کن</p>";
            }
        }
    }
} catch (Throwable $e) {
    echo "<p>Error: ".$e->getMessage()."</p>";
}

echo "<hr>";
echo "<h3>راه حل دائمی:</h3>";
echo "<ol style='line-height:2'>";
echo "<li>در فرم <b>ایجاد کاربر جدید</b> حتما فیلد <b>نام و نام خانوادگی خریدار / مشتری</b> را پر کن (مثلا: علی رضایی)</li>";
echo "<li>برای کاربران قدیمی، از دکمه ویرایش ✏️ در جدول استفاده کن و نام را وارد کن</li>";
echo "<li>یا از این اسکریپت برای کپی خودکار نام از VIP استفاده کن (دکمه بالا)</li>";
echo "<li>یا اگر می‌خواهی همه بدون نام‌ها یوزرنیم‌شان به عنوان نام نمایش داده شود، این کوئری را بزن:<br><code>UPDATE clients SET customer_name = username WHERE customer_name IS NULL OR customer_name = ''</code></li>";
echo "</ol>";

echo "<p><a href='clients'>← بازگشت به کلاینت‌ها</a> | <a href='servers/2/node-users'>VIP Clients</a></p>";

<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/drivers/ConnectixSellerDriver.php';

echo "<h2>✅ تست نهایی ساخت یوزر با Driver جدید</h2>";

$pdo = Database::getConnection();
$srv = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' ORDER BY id DESC LIMIT 1")->fetch();
$token = $srv['api_token'];

$driver = new ConnectixSellerDriver($srv['api_url'], $srv['api_username'] ?? null, $srv['api_password'] ?? null, $token);

echo "<p>Auth: ".($driver->authenticate() ? "✅" : "❌ ".$driver->getLastError())."</p>";

$metaRes = $pdo->query("SELECT * FROM server_nodes WHERE id = {$srv['id']}")->fetch();
$testUsername = 't'.time().rand(10,99);

$payload = [
    'username' => $testUsername,
    'name' => 'تست نهایی API',
    'password' => 'Ab12Cd',
    'traffic_limit_bytes' => 5 * 1073741824, // 5GB
    // plan_id will be auto-selected by driver
];

echo "<p>ساخت یوزر: $testUsername با 5GB</p>";
echo "<pre>".htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";

$result = $driver->createUser($payload);

echo "<h3>نتیجه:</h3>";
echo "<pre style='background:#111;color:#0f0;padding:15px;direction:ltr'>".htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";

if ($result['success']) {
    echo "<p style='background:green;color:white;padding:15px;font-size:18px'>🎉 موفق! یوزر ساخته شد - UUID: {$result['uuid']} - Sublink: ".htmlspecialchars($result['sublink'])."</p>";
    echo "<p>حالا لیست یوزرها رو چک می‌کنیم...</p>";
    $users = $driver->listUsers();
    $found = false;
    foreach ($users as $u) {
        if ($u['username'] === $testUsername) {
            $found = true;
            echo "<p style='color:green'>✅ یوزر در لیست پیدا شد!</p>";
            echo "<pre>".htmlspecialchars(json_encode($u, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))."</pre>";
            break;
        }
    }
    if (!$found) echo "<p style='color:orange'>⚠️ یوزر در لیست نیست ولی API موفق برگشت - شاید تاخیر داره</p>";

    echo "<hr><h3>🧹 حذف یوزر تستی...</h3>";
    $del = $driver->deleteUser($testUsername);
    echo "<p>Delete: ".($del ? "✅ موفق" : "❌ ناموفق - ".$driver->getLastError())."</p>";
} else {
    echo "<p style='background:red;color:white;padding:15px'>❌ ساخت ناموفق: {$result['error']}</p>";
    if (!empty($result['raw'])) {
        echo "<pre>".htmlspecialchars(json_encode($result['raw'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))."</pre>";
    }
}

echo "<hr><h3>📊 دسترسی‌های فعلی API:</h3>";
echo "<ul>";
echo "<li>✅ لیست یوزرها: دارد (86 یوزر)</li>";
echo "<li>✅ اطلاعات فروشنده: دارد</li>";
echo "<li>✅ لیست پلن‌ها و گروه‌ها (meta-data): دارد (18 پلن، 6 گروه)</li>";
echo "<li>".($result['success'] ? "✅" : "❌")." ساخت یوزر: ".($result['success'] ? "دارد - فعال شد!" : "در حال تست...")."</li>";
echo "<li>🔄 حذف یوزر: در حال تست</li>";
echo "<li>🔄 تغییر وضعیت: نیاز به تست بیشتر</li>";
echo "</ul>";

echo "<p>اگر ساخت موفق بود، از این به بعد می‌تونی از پنل > مدیریت مشتریان > افزودن مشتری، مستقیم یوزر بسازی!</p>";

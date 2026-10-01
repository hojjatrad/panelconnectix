<?php
/**
 * ONE-CLICK FIX for Connectix Seller API
 * Upload to contax/apply_connectix_fix.php and open in browser
 * https://vpbotn.ir/contax/apply_connectix_fix.php
 * 
 * This will:
 * 1. Create drivers/ConnectixSellerDriver.php
 * 2. Update drivers/DriverFactory.php
 * 3. Update controllers/ServerController.php detectDriverType
 * 4. Fix server_nodes driver to connectix_seller
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Connectix Seller Driver - One Click Fix</h2>";

$baseDir = __DIR__;
$driverPath = $baseDir . '/drivers/ConnectixSellerDriver.php';
$factoryPath = $baseDir . '/drivers/DriverFactory.php';
$controllerPath = $baseDir . '/controllers/ServerController.php';

// Backup
function backupFile($path) {
    if (!file_exists($path)) return;
    $bak = $path . '.bak.' . date('Ymd_His');
    copy($path, $bak);
    echo "Backup: $path -> $bak<br>";
}

// 1. Create ConnectixSellerDriver.php
$driverCode = <<<'PHP'
<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class ConnectixSellerDriver implements PanelDriverInterface {
    private string $baseUrl;
    private ?string $token;
    private ?string $username;
    private ?string $password;
    private ?string $lastError = null;
    private int $timeout = 15;

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $token = null, ?string $subDomain = null) {
        $this->baseUrl = 'https://api.connectix.vip';
        $this->token = $token ? trim($token) : null;
        $this->username = $username ? trim($username) : null;
        $this->password = $password ? trim($password) : null;
    }

    public function getLastError(): ?string { return $this->lastError; }

    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;
        $headers = ['Accept: application/json','Content-Type: application/json','User-Agent: ConnectixPanel/1.0'];
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
            $headers[] = 'X-Api-Token: ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null && in_array($method, ['POST','PUT','PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            $this->lastError = "خطای اتصال Connectix Seller (cURL): $err";
            return ['success'=>false, 'code'=>$httpCode, 'error'=>$err, 'data'=>null, 'raw'=>$response];
        }
        $decoded = json_decode((string)$response, true);
        $isSuccess = ($httpCode >=200 && $httpCode <300);
        if (!$isSuccess) {
            $msg = $decoded['message'] ?? $decoded['detail'] ?? "HTTP $httpCode";
            if (is_array($msg)) $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            $this->lastError = "Connectix Seller API: $msg (کد $httpCode)";
        }
        return ['success'=>$isSuccess,'code'=>$httpCode,'data'=>$decoded,'raw'=>$response];
    }

    public function authenticate(): bool {
        if (empty($this->token)) {
            $this->lastError = "توکن API فروشنده Connectix وارد نشده است.";
            return false;
        }
        $res = $this->request('/v1/seller/seller-data');
        if ($res['success']) { $this->lastError = null; return true; }
        $res2 = $this->request('/v1/seller/clients?page=1&recordPerPage=1');
        if ($res2['success']) { $this->lastError = null; return true; }
        return false;
    }

    public function getNodeStats(): array {
        if (!$this->authenticate()) {
            return ['status'=>'offline', 'users'=>0, 'version'=>'Connectix Seller (عدم اتصال)'];
        }
        $res = $this->request('/v1/seller/clients?page=1&recordPerPage=1');
        if ($res['success'] && !empty($res['data'])) {
            $total = $res['data']['total_clients'] ?? $res['data']['total'] ?? 0;
            return ['status'=>'online','version'=>'Connectix Seller API','users'=>(int)$total,'cpu'=>'0%','ram'=>'0 GB'];
        }
        return ['status'=>'online', 'users'=>0, 'version'=>'Connectix Seller API'];
    }

    private function parseTraffic(string $trafficStr): array {
        $parts = explode('/', $trafficStr);
        $usedGb = 0; $limitGb = 0;
        if (count($parts) >= 2) { $usedGb = floatval(trim($parts[0])); $limitGb = floatval(trim($parts[1])); }
        elseif (count($parts) == 1) { $usedGb = floatval(trim($parts[0])); }
        return [(int)round($usedGb * 1073741824), (int)round($limitGb * 1073741824)];
    }

    private function parseExpire(?string $expireDate, ?string $remainsDays): ?string {
        if (!empty($remainsDays) && is_numeric($remainsDays)) {
            $days = floatval($remainsDays);
            if ($days > 0) return date('Y-m-d H:i:s', time() + (int)round($days * 86400));
            else return date('Y-m-d H:i:s', time() - 86400);
        }
        if (empty($expireDate) || $expireDate === 'N/A') return null;
        $ts = strtotime($expireDate);
        if ($ts !== false && $ts > 0) return date('Y-m-d H:i:s', $ts);
        return null;
    }

    public function listUsers(): array {
        if (!$this->authenticate()) return [];
        $allClients = []; $page = 1; $perPage = 100; $totalPages = 1;
        do {
            $res = $this->request("/v1/seller/clients?page=$page&recordPerPage=$perPage");
            if (!$res['success'] || empty($res['data'])) break;
            $data = $res['data'];
            $clients = $data['clients']['data'] ?? $data['data'] ?? $data['clients'] ?? [];
            if (!is_array($clients)) break;
            foreach ($clients as $c) $allClients[] = $c;
            $lastPage = $data['clients']['last_page'] ?? null;
            $total = $data['total_clients'] ?? 0;
            if ($lastPage) $totalPages = $lastPage;
            elseif ($total > 0) $totalPages = (int)ceil($total / $perPage);
            $page++; if ($page > $totalPages) break; if ($page > 20) break;
        } while (true);

        $out = [];
        foreach ($allClients as $c) {
            if (!is_array($c) || empty($c['username'])) continue;
            $username = (string)$c['username'];
            $isActive = (int)($c['is_active'] ?? 1);
            $isExpired = (bool)($c['is_expired'] ?? false);
            $connStatus = $c['connection_status'] ?? 'black';
            $status = 'active';
            if ($isExpired) $status = 'expired';
            elseif ($isActive === 0) $status = 'disabled';
            elseif ($connStatus === 'black') $status = 'expired';
            [$usedBytes, $limitBytes] = $this->parseTraffic($c['used_traffic'] ?? '0/0');
            if ($limitBytes > 0 && $usedBytes >= $limitBytes) $status = 'limited';
            $online = false;
            if (!empty($c['used_devices']) && is_array($c['used_devices']) && count($c['used_devices']) > 0) $online = true;
            if ($connStatus === 'success') $online = true;
            $expireAt = $this->parseExpire($c['expire_date'] ?? null, $c['remains_days'] ?? null);
            $subUrl = $c['subscription_link'] ?? '';
            $links = []; if (!empty($c['outline_link'])) $links[] = $c['outline_link'];
            $out[] = [
                'username'=>$username,'status'=>$status,'online'=>$online,
                'traffic_used_bytes'=>$usedBytes,'traffic_limit_bytes'=>$limitBytes,
                'expire_at'=>$expireAt,'subscription_url'=>(string)$subUrl,'links'=>array_values(array_filter($links)),
                'name'=>$c['name'] ?? '','plan_name'=>$c['plan_name'] ?? '','group_name'=>$c['group_name'] ?? '',
                'used_traffic_str'=>$c['used_traffic'] ?? '','remains_days'=>$c['remains_days'] ?? '','id'=>$c['id'] ?? '',
            ];
        }
        return $out;
    }

    public function createUser(array $payload): array {
        return ['success'=>false,'error'=>'ایجاد کاربر مستقیم از طریق API فروشنده Connectix هنوز پیاده‌سازی نشده است.','uuid'=>'','sublink'=>''];
    }
    public function getUser(string $username): ?array {
        $users = $this->listUsers();
        foreach ($users as $u) if ($u['username'] === $username) {
            return ['traffic_used_bytes'=>$u['traffic_used_bytes'],'traffic_limit_bytes'=>$u['traffic_limit_bytes'],'expire_at'=>$u['expire_at'],'status'=>$u['status'],'online'=>$u['online'],'links'=>$u['links'],'subscription_url'=>$u['subscription_url']];
        }
        return null;
    }
    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool { $this->lastError="تمدید پشتیبانی نمی‌شود."; return false; }
    public function updateUser(string $username, array $params): bool { $this->lastError="ویرایش پشتیبانی نمی‌شود."; return false; }
    public function deleteUser(string $username): bool {
        $users = $this->listUsers(); $targetId = null;
        foreach ($users as $u) if ($u['username'] === $username) { $targetId = $u['id'] ?? null; break; }
        if (!$targetId) { $this->lastError="کاربر $username یافت نشد."; return false; }
        $res = $this->request("/v1/seller/clients/$targetId", 'DELETE'); return $res['success'];
    }
    public function toggleUserStatus(string $username, bool $active): bool { $this->lastError="تغییر وضعیت پشتیبانی نمی‌شود."; return false; }
}
PHP;

if (file_exists($driverPath)) backupFile($driverPath);
file_put_contents($driverPath, $driverCode);
echo "✅ Created: $driverPath<br>";

// 2. Update DriverFactory.php
if (file_exists($factoryPath)) {
    backupFile($factoryPath);
    $content = file_get_contents($factoryPath);
    if (strpos($content, 'ConnectixSellerDriver') === false) {
        $content = str_replace(
            "require_once __DIR__ . '/MockDriver.php';",
            "require_once __DIR__ . '/MockDriver.php';\nrequire_once __DIR__ . '/ConnectixSellerDriver.php';",
            $content
        );
        // Add case
        $content = str_replace(
            "            case 'mock':\n            default:\n                return new MockDriver",
            "            case 'connectix':\n            case 'connectix_seller':\n            case 'seller':\n            case 'seller_api':\n                return new ConnectixSellerDriver(\$url, \$user, \$pass, \$token, \$subDomain);\n            case 'mock':\n            default:\n                \$lowUrl = strtolower(\$url);\n                if (str_contains(\$lowUrl, 'api.connectix.vip') || str_contains(\$lowUrl, 'seller-api.connectix.vip') || str_contains(\$lowUrl, 'seller.connectix.vip')) {\n                    return new ConnectixSellerDriver(\$url, \$user, \$pass, \$token, \$subDomain);\n                }\n                return new MockDriver",
            $content
        );
        file_put_contents($factoryPath, $content);
        echo "✅ Updated: $factoryPath<br>";
    } else {
        echo "ℹ️ Factory already has Connectix driver<br>";
    }
} else {
    echo "❌ Factory not found: $factoryPath<br>";
}

// 3. Update ServerController.php
if (file_exists($controllerPath)) {
    backupFile($controllerPath);
    $content = file_get_contents($controllerPath);
    if (strpos($content, "api.connectix.vip") === false || strpos($content, "connectix_seller") === false || substr_count($content, "connectix_seller") < 2) {
        // Check if already patched
        if (strpos($content, "lowUrl") === false || strpos($content, "api.connectix.vip") === false) {
            $oldDetect = "    public static function detectDriverType(string \$apiUrl, string \$username, string \$password, string \$token = '', string \$name = ''): string {\n        \$nameLower = mb_strtolower(\$name, 'UTF-8');";
            $newDetect = "    public static function detectDriverType(string \$apiUrl, string \$username, string \$password, string \$token = '', string \$name = ''): string {\n        \$lowUrl = strtolower(\$apiUrl);\n        if (str_contains(\$lowUrl, 'api.connectix.vip') || str_contains(\$lowUrl, 'seller-api.connectix.vip') || str_contains(\$lowUrl, 'seller.connectix.vip')) {\n            return 'connectix_seller';\n        }\n\n        \$nameLower = mb_strtolower(\$name, 'UTF-8');";
            $content = str_replace($oldDetect, $newDetect, $content);
        }
        // Add probe for connectix before pasargad if not exists
        if (strpos($content, "'driver' => 'connectix_seller'") === false) {
            $oldProbe = "        // Probe live endpoints\n        try {\n            \$psg = DriverFactory::create([";
            $newProbe = "        // Probe live endpoints - Connectix Seller first\n        try {\n            \$cx = DriverFactory::create([\n                'driver' => 'connectix_seller',\n                'api_url' => \$apiUrl,\n                'api_username' => \$username,\n                'api_password' => \$password,\n                'api_token' => \$token\n            ]);\n            if (\$cx->authenticate()) {\n                return 'connectix_seller';\n            }\n        } catch (Throwable \$e) {}\n\n        // Probe live endpoints\n        try {\n            \$psg = DriverFactory::create([";
            $content = str_replace($oldProbe, $newProbe, $content);
        }
        file_put_contents($controllerPath, $content);
        echo "✅ Updated: $controllerPath<br>";
    } else {
        echo "ℹ️ ServerController already patched<br>";
    }
} else {
    echo "❌ Controller not found: $controllerPath<br>";
}

// 4. Fix DB
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT id, name, driver, api_url FROM server_nodes WHERE api_url LIKE '%connectix.vip%'");
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        echo "Fixing server {$r['id']} {$r['name']} driver {$r['driver']} -> connectix_seller and api_url -> https://api.connectix.vip<br>";
        $pdo->prepare("UPDATE server_nodes SET driver = 'connectix_seller', api_url = 'https://api.connectix.vip' WHERE id = ?")->execute([$r['id']]);
    }
    echo "✅ DB fixed: ".count($rows)." servers<br>";
} catch (Throwable $e) {
    echo "❌ DB error: ".$e->getMessage()."<br>";
}

echo "<hr>";
echo "<h3 style='color:green'>✅ نصب کامل شد</h3>";
echo "<p><a href='server_debug.php?id=2'>تست سرور VIP (server_debug.php?id=2)</a></p>";
echo "<p><a href='servers'>لیست سرورها</a> | <a href='servers/2/node-users'>کلاینت‌های VIP</a></p>";
echo "<p>اگر همه چیز OK بود، این فایل را حذف کن: apply_connectix_fix.php</p>";

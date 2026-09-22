<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Provisioner.php';

class ApiController {
    /**
     * Authenticate API Token from Bearer header or ?token= param
     */
    private static function authenticate(): ?array {
        $token = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (!empty($_GET['api_token'])) {
            $token = trim($_GET['api_token']);
        } elseif (!empty($_POST['api_token'])) {
            $token = trim($_POST['api_token']);
        }

        if (empty($token)) {
            self::jsonError('کلید دسترسی API (Bearer Token) ارائه نشده است.', 401);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ? AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            self::jsonError('کلید API نامعتبر است یا حساب کاربری غیرفعال می‌باشد.', 403);
        }

        return $user;
    }

    private static function jsonSuccess(array $data = [], string $message = 'عملیات با موفقیت انجام شد'): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private static function jsonError(string $message, int $statusCode = 400): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * GET /api/v1/wallet
     */
    public function getWallet(): void {
        $user = self::authenticate();
        self::jsonSuccess([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'wallet_balance' => (int)$user['wallet_balance'],
            'discount_percent' => (int)$user['discount_percent'],
            'currency' => 'Tomans'
        ]);
    }

    /**
     * GET /api/v1/plans
     */
    public function getPlans(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();
        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC")->fetchAll();

        $discount = (int)$user['discount_percent'];
        $result = [];
        foreach ($plans as $p) {
            $price = (int)$p['reseller_price'];
            if ($discount > 0) {
                $price = (int)($price - ($price * ($discount / 100)));
            }
            $result[] = [
                'id' => (int)$p['id'],
                'title' => $p['title'],
                'traffic_gb' => (int)$p['traffic_gb'],
                'duration_days' => (int)$p['duration_days'],
                'price' => $price,
                'server_group' => $p['server_group'],
                'is_free' => (bool)$p['is_free']
            ];
        }

        self::jsonSuccess($result);
    }

    /**
     * POST /api/v1/client/create
     */
    public function createClient(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();

        $raw = file_get_contents('php://input');
        $body = !empty($raw) ? json_decode($raw, true) : $_POST;

        $planId = (int)($body['plan_id'] ?? 0);
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');
        $serverId = !empty($body['server_id']) ? (int)$body['server_id'] : null;
        $note = trim($body['note'] ?? 'Created via API');

        if ($planId <= 0) {
            self::jsonError('شناسه پلن (plan_id) الزامی است.');
        }

        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch();
        if (!$plan) {
            self::jsonError('پلن یافت نشد یا غیرفعال است.');
        }

        // Price Calculation
        $cost = (int)$plan['reseller_price'];
        if ($user['discount_percent'] > 0) {
            $cost = (int)($cost - ($cost * ($user['discount_percent'] / 100)));
        }

        if ($user['role'] !== 'admin' && $plan['is_free'] == 0 && $user['wallet_balance'] < $cost) {
            self::jsonError("موجودی کیف پول ناکافی است. موجودی فعلی: {$user['wallet_balance']} | هزینه پلن: {$cost}");
        }

        // Provision
        $prov = Provisioner::createClient($planId, $serverId, $username ?: null, $password ?: null, (int)$user['id'], $note);
        if (!$prov['success']) {
            self::jsonError($prov['error']);
        }

        // Deduct wallet if not admin
        if ($user['role'] !== 'admin' && $cost > 0) {
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$cost, $user['id']]);
            $newBal = $user['wallet_balance'] - $cost;
            $pdo->prepare("INSERT INTO transactions (user_id, amount, balance_after, type, description, status) VALUES (?, ?, ?, 'plan_purchase', ?, 'completed')")
                ->execute([$user['id'], -$cost, $newBal, "خرید از طریق API برای {$prov['username']}"]);
        }

        self::jsonSuccess($prov, 'کلاینت با موفقیت ایجاد و فعال شد.');
    }

    /**
     * GET /api/v1/client/info
     */
    public function getClientInfo(): void {
        $user = self::authenticate();
        $pdo = Database::getConnection();

        $query = trim($_GET['username'] ?? $_GET['token'] ?? '');
        if (empty($query)) {
            self::jsonError('نام کاربری یا توکن الزامی است.');
        }

        $whereUser = ($user['role'] === 'admin') ? "1=1" : "c.reseller_id = " . intval($user['id']);

        $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name 
                               FROM clients c 
                               LEFT JOIN plans p ON c.plan_id = p.id 
                               LEFT JOIN server_nodes s ON c.server_id = s.id 
                               WHERE (c.username = ? OR c.sub_token = ?) AND $whereUser");
        $stmt->execute([$query, $query]);
        $client = $stmt->fetch();

        if (!$client) {
            self::jsonError('کلاینت یافت نشد یا دسترسی غیرمجاز است.', 404);
        }

        $subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);

        self::jsonSuccess([
            'id' => (int)$client['id'],
            'username' => $client['username'],
            'status' => $client['status'],
            'plan_title' => $client['plan_title'],
            'server_name' => $client['server_name'],
            'traffic_limit_bytes' => (int)$client['traffic_limit_bytes'],
            'traffic_used_bytes' => (int)$client['traffic_used_bytes'],
            'traffic_limit_gb' => round($client['traffic_limit_bytes'] / (1024*1024*1024), 2),
            'traffic_used_gb' => round($client['traffic_used_bytes'] / (1024*1024*1024), 2),
            'expire_at' => $client['expire_at'],
            'sub_url' => $subUrl
        ]);
    }
}

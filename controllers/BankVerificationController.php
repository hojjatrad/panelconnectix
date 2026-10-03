<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/BankVerification.php';

class BankVerificationController {
    
    public function index(): void {
        Auth::requireAdmin();
        
        $stats = BankVerification::getStats();
        
        // Get settings
        $settings = [
            'auto_verify_enabled' => Setting::get('auto_verify_enabled', '1'),
            'verify_method_unique_amount' => Setting::get('verify_method_unique_amount', '1'),
            'verify_method_sms_forwarder' => Setting::get('verify_method_sms_forwarder', '1'),
            'verify_method_ocr' => Setting::get('verify_method_ocr', '0'),
            'verify_method_gateway' => Setting::get('verify_method_gateway', '1'),
            'bank_sms_secret' => Setting::get('bank_sms_secret', bin2hex(random_bytes(16))),
            'bank_card_number' => Setting::get('bank_card_number', '6037-xxxx-xxxx-xxxx'),
            'bank_card_last4' => Setting::get('bank_card_last4', ''),
            'bank_sms_sender_numbers' => Setting::get('bank_sms_sender_numbers', '200033, 200044, 3000'),
            'unique_amount_expire_minutes' => Setting::get('unique_amount_expire_minutes', '15'),
            'bank_min_amount' => Setting::get('bank_min_amount', '1000'),
        ];
        
        // Recent transactions
        $pdo = Database::getConnection();
        $recentTx = $pdo->query("SELECT * FROM bank_transactions ORDER BY received_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        $pendingOrders = $pdo->query("SELECT * FROM bot_orders WHERE payment_status IN ('pending_receipt','pending_approval') ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        
        require __DIR__ . '/../views/settings/bank_verification.php';
    }
    
    public function save(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر');
            Helpers::redirect('settings/bank-verification');
        }
        
        $fields = [
            'auto_verify_enabled',
            'verify_method_unique_amount',
            'verify_method_sms_forwarder',
            'verify_method_ocr',
            'verify_method_gateway',
            'bank_sms_secret',
            'bank_card_number',
            'bank_card_last4',
            'bank_sms_sender_numbers',
            'unique_amount_expire_minutes',
            'bank_min_amount',
        ];
        
        foreach ($fields as $f) {
            $val = $_POST[$f] ?? '';
            if (str_starts_with($f, 'verify_method_') || $f === 'auto_verify_enabled') {
                $val = !empty($_POST[$f]) ? '1' : '0';
            }
            Setting::set($f, trim($val));
        }
        
        Helpers::flash('success', 'تنظیمات تایید خودکار بانکی ذخیره شد');
        Helpers::redirect('settings/bank-verification');
    }
    
    // Webhook for SMS forwarder app
    public function webhook(): void {
        header('Content-Type: application/json; charset=utf-8');
        
        $secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
        $expectedSecret = Setting::get('bank_sms_secret', '');
        
        // Allow via APP_SECRET as well
        $appSecret = defined('APP_SECRET') ? APP_SECRET : '';
        
        $isValid = false;
        if (!empty($secret) && !empty($expectedSecret) && hash_equals($expectedSecret, $secret)) {
            $isValid = true;
        }
        if (!$isValid && !empty($secret) && !empty($appSecret) && hash_equals($appSecret, $secret)) {
            $isValid = true;
        }
        // For testing, allow if auto_verify_enabled and secret empty in DB (first setup)
        if (!$isValid && empty($expectedSecret) && !empty($secret) && strlen($secret) > 10) {
            Setting::set('bank_sms_secret', $secret);
            $isValid = true;
        }
        
        if (!$isValid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid secret'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Get data - support both GET and POST and JSON
        $amount = 0;
        $rawSms = '';
        $tracking = '';
        $cardLast4 = '';
        $sender = '';
        
        // Try JSON body
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $json = json_decode($rawInput, true);
            if (is_array($json)) {
                $amount = (int)($json['amount'] ?? 0);
                $rawSms = $json['sms'] ?? $json['raw_sms'] ?? $json['message'] ?? '';
                $tracking = $json['tracking'] ?? $json['tracking_code'] ?? $json['ref'] ?? '';
                $cardLast4 = $json['card'] ?? $json['card_last4'] ?? '';
                $sender = $json['sender'] ?? $json['from'] ?? '';
                if (empty($amount) && !empty($rawSms)) {
                    $amount = self::extractAmountFromSms($rawSms);
                }
            }
        }
        
        // Fallback to POST/GET params
        if (empty($amount)) {
            $amount = (int)($_POST['amount'] ?? $_GET['amount'] ?? 0);
        }
        if (empty($rawSms)) {
            $rawSms = $_POST['sms'] ?? $_POST['message'] ?? $_GET['sms'] ?? '';
        }
        if (empty($tracking)) {
            $tracking = $_POST['tracking'] ?? $_GET['tracking'] ?? $_POST['ref'] ?? '';
        }
        if (empty($cardLast4)) {
            $cardLast4 = $_POST['card'] ?? $_GET['card'] ?? '';
        }
        if (empty($sender)) {
            $sender = $_POST['sender'] ?? $_GET['sender'] ?? $_POST['from'] ?? '';
        }
        
        // If still no amount but have SMS, extract
        if (empty($amount) && !empty($rawSms)) {
            $amount = self::extractAmountFromSms($rawSms);
        }
        
        if (empty($amount) || $amount < 1000) {
            echo json_encode(['success' => false, 'error' => 'مبلغ نامعتبر - باید حداقل 1000 باشد', 'received_amount' => $amount], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Save transaction
        $result = BankVerification::saveBankTransaction($amount, $rawSms, $tracking, $cardLast4, $sender);
        
        if ($result['success']) {
            $matched = $result['matched'] ?? null;
            if ($matched) {
                echo json_encode([
                    'success' => true,
                    'message' => 'تراکنش ثبت و سفارش ' . $matched['order_code'] . ' خودکار تایید شد',
                    'transaction_id' => $result['id'],
                    'matched_order' => $matched['order_code'],
                    'auto_approved' => true
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'تراکنش ثبت شد ولی سفارش منطبق یافت نشد - مبلغ: ' . number_format($amount),
                    'transaction_id' => $result['id'],
                    'auto_approved' => false
                ], JSON_UNESCAPED_UNICODE);
            }
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    private static function extractAmountFromSms(string $sms): int {
        // Patterns for Iranian banks
        // Example: "واریز 2,901,470 ریال به کارت شما" or "مبلغ 290,147 تومان"
        $patterns = [
            '/واریز\s*([\d,]+)\s*ریال/u',
            '/مبلغ\s*([\d,]+)\s*ریال/u',
            '/([\d,]+)\s*ریال\s*واریز/u',
            '/واریز\s*([\d,]+)\s*تومان/u',
            '/مبلغ\s*([\d,]+)\s*تومان/u',
            '/Amount\s*[:]?\s*([\d,]+)/i',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $sms, $m)) {
                $num = str_replace([',', '،'], '', $m[1]);
                $amount = (int)$num;
                // If amount > 100000, likely Rial, convert to Toman
                if ($amount > 100000) {
                    $amount = (int)($amount / 10);
                }
                return $amount;
            }
        }
        // Try to find any number > 1000
        if (preg_match_all('/\d[\d,]*/', $sms, $matches)) {
            foreach ($matches[0] as $numStr) {
                $num = (int)str_replace(',', '', $numStr);
                if ($num >= 1000 && $num < 10000000) {
                    if ($num > 100000) $num = (int)($num / 10);
                    return $num;
                }
            }
        }
        return 0;
    }
    
    public function testSms(): void {
        Auth::requireAdmin();
        header('Content-Type: application/json; charset=utf-8');
        
        $sms = $_POST['sms'] ?? '';
        if (empty($sms)) {
            echo json_encode(['success' => false, 'error' => 'متن پیامک خالی است'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $amount = self::extractAmountFromSms($sms);
        echo json_encode(['success' => true, 'extracted_amount' => $amount, 'formatted' => number_format($amount) . ' تومان'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function manualMatch(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/bank-verification');
        }
        
        $txId = (int)($_POST['tx_id'] ?? 0);
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        if (empty($txId) || empty($orderId)) {
            Helpers::flash('error', 'شناسه تراکنش یا سفارش نامعتبر');
            Helpers::redirect('settings/bank-verification');
        }
        
        $pdo = Database::getConnection();
        $tx = $pdo->query("SELECT * FROM bank_transactions WHERE id=$txId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $order = $pdo->query("SELECT * FROM bot_orders WHERE id=$orderId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if (!$tx || !$order) {
            Helpers::flash('error', 'تراکنش یا سفارش یافت نشد');
            Helpers::redirect('settings/bank-verification');
        }
        
        BankVerification::approveOrder($order, $txId, 'manual', $tx['tracking_code'] ?? '');
        Helpers::flash('success', "سفارش {$order['order_code']} با تراکنش {$tx['amount']} دستی تایید شد");
        Helpers::redirect('settings/bank-verification');
    }
}

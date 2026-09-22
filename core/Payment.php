<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';

class Payment {
    /**
     * Request payment from ZarinPal
     */
    public static function requestZarinpal(int $amountTomans, string $description, string $callbackUrl, string $email = '', string $mobile = ''): array {
        $merchant = Setting::get('zarinpal_merchant');
        if (empty($merchant)) {
            return ['success' => false, 'error' => 'مرچنت‌کد زرین‌پال تنظیم نشده است.'];
        }

        // ZarinPal expects Rials: Tomans * 10
        $amountRials = $amountTomans * 10;

        $data = [
            'merchant_id' => $merchant,
            'amount' => $amountRials,
            'description' => $description,
            'callback_url' => $callbackUrl,
            'metadata' => ['email' => $email, 'mobile' => $mobile]
        ];

        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Payment/1.0');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true);
        if (isset($res['data']['code']) && $res['data']['code'] == 100) {
            $authority = $res['data']['authority'];
            return [
                'success' => true,
                'authority' => $authority,
                'redirect_url' => "https://www.zarinpal.com/pg/StartPay/{$authority}"
            ];
        }

        return ['success' => false, 'error' => $res['errors']['message'] ?? 'خطا در برقراری ارتباط با درگاه زرین‌پال'];
    }

    /**
     * Verify payment on ZarinPal
     */
    public static function verifyZarinpal(string $authority, int $amountTomans): array {
        $merchant = Setting::get('zarinpal_merchant');
        $amountRials = $amountTomans * 10;

        $data = [
            'merchant_id' => $merchant,
            'amount' => $amountRials,
            'authority' => $authority
        ];

        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true);
        if (isset($res['data']['code']) && in_array($res['data']['code'], [100, 101])) {
            return [
                'success' => true,
                'ref_id' => $res['data']['ref_id'] ?? $authority
            ];
        }

        return ['success' => false, 'error' => $res['errors']['message'] ?? 'پرداخت توسط درگاه زرین‌پال تایید نشد.'];
    }
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Setting.php';

class TelegramBot {
    public static function getToken(): string {
        $token = Setting::get('telegram_bot_token');
        if (!empty($token)) {
            return trim($token);
        }
        return defined('TELEGRAM_BOT_TOKEN') ? trim(TELEGRAM_BOT_TOKEN) : '';
    }

    public static function getAdminChatId(): string {
        $adminId = Setting::get('telegram_admin_id');
        if (!empty($adminId)) {
            return trim($adminId);
        }
        return defined('TELEGRAM_ADMIN_CHAT_ID') ? trim(TELEGRAM_ADMIN_CHAT_ID) : '';
    }

    public static function isActive(): bool {
        $active = Setting::get('telegram_bot_active', '1');
        return ($active === '1' || $active === 'true' || $active === 'on');
    }

    public static function request(string $method, array $params = []): ?array {
        $token = self::getToken();
        if (empty($token)) {
            return null;
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            @file_put_contents(__DIR__ . '/../data/telegram_api.log', date('[Y-m-d H:i:s] ') . "Curl error: {$err}\n", FILE_APPEND);
            return null;
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['ok']) && $decoded['ok'] === false) {
            @file_put_contents(__DIR__ . '/../data/telegram_api.log', date('[Y-m-d H:i:s] ') . "Method: {$method} | Error: " . ($decoded['description'] ?? 'unknown') . "\nPayload: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }

        return $decoded;
    }

    public static function sendMessage(string $text, ?string $chatId = null, $replyMarkup = null): bool {
        $targetChat = $chatId ?: self::getAdminChatId();
        if (empty($targetChat)) {
            return false;
        }

        $params = [
            'chat_id' => $targetChat,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('sendMessage', $params);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function sendPhoto(string $photo, string $caption = '', ?string $chatId = null, $replyMarkup = null): bool {
        $targetChat = $chatId ?: self::getAdminChatId();
        if (empty($targetChat)) {
            return false;
        }

        $params = [
            'chat_id' => $targetChat,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('sendPhoto', $params);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function sendDocument(string $filePath, string $caption = '', ?string $chatId = null): bool {
        $targetChat = $chatId ?: self::getAdminChatId();
        $token = self::getToken();
        if (empty($targetChat) || empty($token) || !file_exists($filePath)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendDocument";
        $ch = curl_init();
        $cfile = new CURLFile($filePath);
        $postData = [
            'chat_id' => $targetChat,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'document' => $cfile
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return false;
        $decoded = json_decode($response, true);
        return isset($decoded['ok']) && $decoded['ok'] === true;
    }

    public static function editMessageText(string $text, string $chatId, int $messageId, $replyMarkup = null): bool {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('editMessageText', $params);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert
        ];
        if ($text !== null) {
            $params['text'] = $text;
        }
        $res = self::request('answerCallbackQuery', $params);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function setWebhook(string $webhookUrl): array {
        $token = self::getToken();
        if (empty($token)) {
            return ['ok' => false, 'description' => 'توکن ربات تلگرام تنظیم نشده است.'];
        }
        $res = self::request('setWebhook', [
            'url' => $webhookUrl,
            'drop_pending_updates' => false
        ]);
        return $res ?: ['ok' => false, 'description' => 'پاسخی از تلگرام دریافت نشد.'];
    }

    public static function getWebhookInfo(): array {
        $res = self::request('getWebhookInfo');
        return $res ?: ['ok' => false, 'description' => 'خطا در ارتباط با تلگرام'];
    }

    public static function deleteWebhook(): array {
        $res = self::request('deleteWebhook', ['drop_pending_updates' => false]);
        return $res ?: ['ok' => false, 'description' => 'خطا در حذف وبهوک'];
    }
}

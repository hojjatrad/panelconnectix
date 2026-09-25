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

    public static function request(string $method, array $params = [], ?string $customToken = null): ?array {
        $token = !empty($customToken) ? trim($customToken) : self::getToken();
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

    public static function sendMessage(string $text, ?string $chatId = null, $replyMarkup = null, ?string $customToken = null, ?int $messageThreadId = null): bool {
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

        if ($messageThreadId !== null && $messageThreadId > 0) {
            $params['message_thread_id'] = $messageThreadId;
        }

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('sendMessage', $params, $customToken);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function sendPhoto(string $photo, string $caption = '', ?string $chatId = null, $replyMarkup = null, ?string $customToken = null, ?int $messageThreadId = null): bool {
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

        if ($messageThreadId !== null && $messageThreadId > 0) {
            $params['message_thread_id'] = $messageThreadId;
        }

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('sendPhoto', $params, $customToken);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function sendDocument(string $filePath, string $caption = '', ?string $chatId = null, ?string $customToken = null, ?int $messageThreadId = null): bool {
        $targetChat = $chatId ?: self::getAdminChatId();
        $token = !empty($customToken) ? trim($customToken) : self::getToken();
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

        if ($messageThreadId !== null && $messageThreadId > 0) {
            $postData['message_thread_id'] = $messageThreadId;
        }

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

    /**
     * Create a forum topic in a supergroup
     */
    public static function createForumTopic(string $chatId, string $name, int $iconColor = 0x6FB9F0, ?string $customToken = null): ?int {
        $params = [
            'chat_id' => $chatId,
            'name' => $name,
            'icon_color' => $iconColor
        ];
        $res = self::request('createForumTopic', $params, $customToken);
        if (isset($res['ok']) && $res['ok'] === true && isset($res['result']['message_thread_id'])) {
            return (int)$res['result']['message_thread_id'];
        }
        return null;
    }

    /**
     * Resolve forum topic thread ID for any category among the 11 specialized topics
     */
    public static function getTopicThreadId(string $category): ?int {
        $key = match(strtolower(trim($category))) {
            'nightly' => 'nightly',
            'backup_reseller' => 'backup_reseller',
            'backup', 'backup_all' => 'backup_all',
            'notifications', 'broadcast' => 'notifications',
            'services' => 'services',
            'sales' => 'sales',
            'finance', 'crypto' => 'finance',
            'trials', 'trial' => 'trials',
            'commissions', 'commission' => 'commissions',
            'errors', 'error', 'servers' => 'errors',
            default => 'general'
        };

        $id = (int)Setting::get("bot_topic_{$key}", 0);
        if ($id > 0) return $id;

        // Fallback to legacy keys if configured
        $legacyKey = match($key) {
            'sales' => 'topic_sales_id',
            'backup_all' => 'topic_backup_id',
            'errors' => 'topic_servers_id',
            'finance' => 'topic_crypto_id',
            default => 'topic_general_id'
        };
        $legacyId = (int)Setting::get($legacyKey, 0);
        return $legacyId > 0 ? $legacyId : null;
    }

    /**
     * Send structured report to a specific Forum Topic by topic key
     */
    public static function sendTopicLog(string $topicKey, string $message, ?array $keyboard = null, ?string $documentPath = null, ?string $photoUrl = null, ?string $customToken = null): bool {
        $logChat = trim(Setting::get('bot_log_channel', Setting::get('telegram_log_channel_id', Setting::get('telegram_admin_id', ''))));
        if (empty($logChat)) return false;

        $threadId = self::getTopicThreadId($topicKey);

        if (!empty($documentPath) && file_exists($documentPath)) {
            return self::sendDocument($documentPath, $message, $logChat, $customToken, $threadId);
        }
        if (!empty($photoUrl)) {
            return self::sendPhoto($photoUrl, $message, $logChat, $keyboard, $customToken, $threadId);
        }
        return (bool)self::sendMessage($message, $logChat, $keyboard, $customToken, $threadId);
    }

    /**
     * Check if user is member of mandatory channel/group (Force Join)
     */
    public static function getChatMember(string $chatId, string $userId, ?string $customToken = null): ?array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => (int)$userId
        ];
        $res = self::request('getChatMember', $params, $customToken);
        if (isset($res['ok']) && $res['ok'] === true && isset($res['result']['status'])) {
            return $res['result'];
        }
        return null;
    }

    /**
     * Send report routed to dedicated topic in log channel
     */
    public static function sendCategorizedReport(string $category, string $text, $replyMarkup = null, ?string $customToken = null): bool {
        return self::sendTopicLog($category, $text, is_array($replyMarkup) ? $replyMarkup : null, null, null, $customToken);
    }

    /**
     * Announce a successfully applied GitHub panel update to the supergroup
     * reports topic — with strict de-duplication so the bot never spams:
     *
     *   - the SAME commit sha is announced at most once per 60 minutes
     *   - only ONE message is sent per applied update (webhook and cron both
     *     call this; Updater::applyUpdate(notify:false) suppresses its own)
     *
     * Returns true when a message was actually sent, false when de-duplicated.
     */
    public static function announcePanelUpdate(string $sha, string $text): bool {
        $sha = trim($sha);
        $lastSha = trim((string)Setting::get('last_panel_update_notify_sha', ''));
        $lastAt = (int)Setting::get('last_panel_update_notify_at', '0');

        if ($sha !== '' && $lastSha === $sha && (time() - $lastAt) < 3600) {
            return false; // same update already announced within the last hour
        }

        $sent = self::sendCategorizedReport('general', $text);
        if (!$sent) {
            $sent = self::sendCategorizedReport('notifications', $text);
        }

        if ($sent && $sha !== '') {
            Setting::set('last_panel_update_notify_sha', $sha);
            Setting::set('last_panel_update_notify_at', (string)time());
        }

        return $sent;
    }

    /**
     * Send backup file routed to backup topic in log channel
     */
    public static function sendCategorizedDocument(string $category, string $filePath, string $caption = '', ?string $customToken = null): bool {
        return self::sendTopicLog($category, $caption, null, $filePath, null, $customToken);
    }

    public static function editMessageText(string $text, string $chatId, int $messageId, $replyMarkup = null, ?string $customToken = null): bool {
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

        $res = self::request('editMessageText', $params, $customToken);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function editMessageCaption(string $caption, string $chatId, int $messageId, $replyMarkup = null, ?string $customToken = null): bool {
        if (mb_strlen($caption) > 1020) {
            $caption = mb_substr($caption, 0, 1016) . '...';
        }
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $res = self::request('editMessageCaption', $params, $customToken);
        return isset($res['ok']) && $res['ok'] === true;
    }

    /**
     * Edit message text OR caption depending on whether original message was text or media (e.g. photo receipts)
     * If editing fails entirely, it safely falls back to sending a reply message so details are never lost.
     */
    public static function editAnyMessage(string $content, string $chatId, int $messageId, $replyMarkup = null, ?string $customToken = null): bool {
        // 1. Try editing message text
        $resText = self::editMessageText($content, $chatId, $messageId, $replyMarkup, $customToken);
        if ($resText) {
            return true;
        }

        // 2. If it was a photo or document message (like a payment receipt), edit caption
        $resCaption = self::editMessageCaption($content, $chatId, $messageId, $replyMarkup, $customToken);
        if ($resCaption) {
            return true;
        }

        // 3. Fallback: If both fail, send as a new message so the result is never silently dropped
        return self::sendMessage($content, $chatId, $replyMarkup, $customToken);
    }

    public static function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false, ?string $customToken = null): bool {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert
        ];
        if ($text !== null) {
            $params['text'] = $text;
        }
        $res = self::request('answerCallbackQuery', $params, $customToken);
        return isset($res['ok']) && $res['ok'] === true;
    }

    public static function setWebhook(string $webhookUrl, ?string $customToken = null, bool $dropPending = false): array {
        $token = !empty($customToken) ? trim($customToken) : self::getToken();
        if (empty($token)) {
            return ['ok' => false, 'description' => 'توکن ربات تلگرام تنظیم نشده است.'];
        }
        $res = self::request('setWebhook', [
            'url' => $webhookUrl,
            'drop_pending_updates' => $dropPending
        ], $token);
        return $res ?: ['ok' => false, 'description' => 'پاسخی از تلگرام دریافت نشد.'];
    }

    public static function getWebhookInfo(?string $customToken = null): array {
        $res = self::request('getWebhookInfo', [], $customToken);
        return $res ?: ['ok' => false, 'description' => 'خطا در ارتباط با تلگرام'];
    }

    public static function deleteWebhook(?string $customToken = null): array {
        $res = self::request('deleteWebhook', ['drop_pending_updates' => false], $customToken);
        return $res ?: ['ok' => false, 'description' => 'خطا در حذف وبهوک'];
    }

    public static function getFile(string $fileId, ?string $customToken = null): ?array {
        $token = !empty($customToken) ? trim($customToken) : self::getToken();
        $res = self::request('getFile', ['file_id' => $fileId], $token);
        if ($res && isset($res['ok']) && $res['ok'] === true) {
            return $res['result'] ?? null;
        }
        return null;
    }

    public static function downloadFile(string $telegramFilePath, ?string $customToken = null): ?string {
        $token = !empty($customToken) ? trim($customToken) : self::getToken();
        if (empty($token) || empty($telegramFilePath)) {
            return null;
        }
        $url = "https://api.telegram.org/file/bot{$token}/{$telegramFilePath}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200 && $content !== false) {
            return $content;
        }
        return null;
    }
}

<?php
/**
 * v4.0.53 BotAccountService - My accounts, detail, configs, renew
 */
class BotAccountService {
    public static function showMyAccounts(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showMyAccounts');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {
            TelegramBot::sendMessage("⚠️ خطا در نمایش اشتراک‌ها: " . $e->getMessage(), $chatId);
        }
    }

    public static function showAccountDetail(PDO $pdo, string $chatId, string $fromId, int $clientId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showAccountDetail');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $clientId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function sendAccountConfigs(PDO $pdo, string $chatId, string $fromId, int $clientId): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'sendAccountConfigs');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $clientId);
        } catch (Throwable $e) {}
    }

    public static function showRenewChoice(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showRenewChoice');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showRenewPlansForClient(PDO $pdo, string $chatId, string $fromId, int $clientId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showRenewPlansForClient');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $clientId, $messageId);
        } catch (Throwable $e) {}
    }
}

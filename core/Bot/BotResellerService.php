<?php
/**
 * v4.0.53 BotResellerService
 */
class BotResellerService {
    public static function startResellerApplication(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'startResellerApplication');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function approveResellerApplication(PDO $pdo, int $appId, string $adminChatId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'approveResellerApplication');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $appId, $adminChatId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function rejectResellerApplication(PDO $pdo, int $appId, string $adminChatId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'rejectResellerApplication');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $appId, $adminChatId, $messageId);
        } catch (Throwable $e) {}
    }
}

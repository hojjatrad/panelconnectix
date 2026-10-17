<?php
/**
 * v4.0.53 BotCryptoService - USDT, TON payments
 */
class BotCryptoService {
    public static function showCryptoPayment(PDO $pdo, string $chatId, string $fromId, int $orderId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showCryptoPayment');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $orderId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showTonPayment(PDO $pdo, string $chatId, string $fromId, int $orderId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showTonPayment');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $orderId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function approveCryptoPayment(PDO $pdo, int $cryptoId, string $adminChatId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'approveCryptoPayment');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $cryptoId, $adminChatId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function rejectCryptoPayment(PDO $pdo, int $cryptoId, string $adminChatId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'rejectCryptoPayment');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $cryptoId, $adminChatId, $messageId);
        } catch (Throwable $e) {}
    }
}

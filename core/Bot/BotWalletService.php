<?php
/**
 * v4.0.53 BotWalletService
 */
class BotWalletService {
    public static function showWalletMenu(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showWalletMenu');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showWalletTopupForOrder(PDO $pdo, string $chatId, string $fromId, int $orderId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showWalletTopupForOrder');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $orderId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function initiateWalletTopup(PDO $pdo, string $chatId, string $fromId, int $amount, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'initiateWalletTopup');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $amount, $messageId);
        } catch (Throwable $e) {}
    }

    public static function processWalletPayment(PDO $pdo, int $orderId, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'processWalletPayment');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $orderId, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function approveWalletChargeOrder(PDO $pdo, int $orderId, string $adminChatId, ?int $messageId = null, ?string $cbId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'approveWalletChargeOrder');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $orderId, $adminChatId, $messageId, $cbId);
        } catch (Throwable $e) {}
    }

    public static function rejectWalletChargeOrder(PDO $pdo, int $orderId, string $adminChatId, ?int $messageId = null, ?string $cbId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'rejectWalletChargeOrder');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $orderId, $adminChatId, $messageId, $cbId);
        } catch (Throwable $e) {}
    }
}

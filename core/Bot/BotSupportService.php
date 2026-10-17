<?php
/**
 * v4.0.53 BotSupportService - Monitoring, Financial, Points, Usage, Panel, Support, Apps, Plans
 */
class BotSupportService {
    public static function showMonitoringForUser(PDO $pdo, string $chatId, string $fromId, ?int $messageId=null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showMonitoringForUser');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {
            // Fallback with ApcuCache
            $cacheKey = "monitoring_status";
            $status = class_exists('ApcuCache') ? ApcuCache::get($cacheKey, null) : null;
            if ($status === null) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN health_status='online' THEN 1 ELSE 0 END) as online FROM server_nodes WHERE is_active=1");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $status = "📊 <b>وضعیت سرورها</b>\n\n✅ آنلاین: {$row['online']}/{$row['total']}";
                    if (class_exists('ApcuCache')) ApcuCache::set($cacheKey, $status, 30);
                } catch (Throwable $e2) {
                    $status = "📊 وضعیت سرورها در دسترس نیست";
                }
            }
            TelegramBot::sendMessage($status, $chatId);
        }
    }

    public static function showFinancialForUser(PDO $pdo, string $chatId, string $fromId, ?int $messageId=null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showFinancialForUser');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showPointsForUser(PDO $pdo, string $chatId, string $fromId, ?int $messageId=null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showPointsForUser');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showUsageForUser(PDO $pdo, string $chatId, string $fromId, ?int $messageId=null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showUsageForUser');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showPanelCredentials(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showPanelCredentials');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function showPlansMenu(PDO $pdo, string $chatId, ?int $messageId = null, ?string $selectedCategory = null, ?string $selectedServer = null, ?string $selectedType = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showPlansMenu');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $messageId, $selectedCategory, $selectedServer, $selectedType);
        } catch (Throwable $e) {}
    }

    public static function showAppsDownload(PDO $pdo, string $chatId, ?int $messageId = null, ?string $selectedPlatform = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showAppsDownload');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $messageId, $selectedPlatform);
        } catch (Throwable $e) {}
    }

    public static function showReferralInfo(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'showReferralInfo');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }

    public static function handleLuckyWheel(PDO $pdo, string $chatId, string $fromId, ?int $messageId = null): void {
        try {
            $ref = new ReflectionMethod('TelegramBotController', 'handleLuckyWheel');
            $ref->setAccessible(true);
            $ref->invoke(null, $pdo, $chatId, $fromId, $messageId);
        } catch (Throwable $e) {}
    }
}

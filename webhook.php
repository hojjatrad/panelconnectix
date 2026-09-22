<?php
// Telegram Bot Webhook Entry Point (Multi-Bot Aware)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

$botToken = $_GET['bot_token'] ?? $_GET['token'] ?? null;
$resellerId = isset($_GET['reseller_id']) ? (int)$_GET['reseller_id'] : null;

// Process webhook request with dynamic multi-bot context
TelegramBotController::handleWebhook($botToken, $resellerId);

<?php
// Telegram Bot Webhook Entry Point
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/controllers/TelegramBotController.php';

// Process webhook request
TelegramBotController::handleWebhook();

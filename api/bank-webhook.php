<?php
// Bank Webhook API - standalone entry point
// URL: https://yourdomain.com/api/bank-webhook.php?secret=xxx&amount=290147
// Or via route: /api/bank-webhook?secret=xxx

// Allow direct access
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/BankVerification.php';

// Call controller webhook
require_once __DIR__ . '/../controllers/BankVerificationController.php';
$ctrl = new BankVerificationController();
$ctrl->webhook();

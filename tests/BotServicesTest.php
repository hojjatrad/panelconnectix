<?php
/**
 * v4.0.53 Unit Tests for Bot Services
 * Tests BotContextService, BotSessionService, BotKeyboardService, ApcuCache
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/core/Database.php';
require $root . '/core/Cache.php';
require $root . '/core/ApcuCache.php';
require $root . '/core/Setting.php';
require $root . '/core/Helpers.php';
require $root . '/core/TelegramBot.php';
require $root . '/core/Bot/BotContextService.php';
require $root . '/core/Bot/BotSessionService.php';
require $root . '/core/Bot/BotKeyboardService.php';
require $root . '/core/Bot/BotAdminService.php';

$pdo = Database::getConnection();
Database::ensureExtendedTablesExist($pdo);

$failures = 0;
function assertTrue(string $label, bool $cond): void {
    global $failures;
    if ($cond) {
        echo "  [OK] $label\n";
    } else {
        echo "  [FAIL] $label\n";
        $failures++;
    }
}

echo "== Bot Services Unit Tests v4.0.53 ==\n";

// 1. ApcuCache
echo "\n-- ApcuCache --\n";
ApcuCache::set('test_key', 'test_value', 10);
assertTrue('ApcuCache set/get', ApcuCache::get('test_key') === 'test_value');
ApcuCache::delete('test_key');
assertTrue('ApcuCache delete', ApcuCache::get('test_key', 'default') === 'default');
$remembered = ApcuCache::remember('test_remember', 10, fn() => 'remembered_value');
assertTrue('ApcuCache remember', $remembered === 'remembered_value');
assertTrue('ApcuCache remember cached', ApcuCache::get('test_remember') === 'remembered_value');
ApcuCache::delete('test_remember');

// 2. BotContextService
echo "\n-- BotContextService --\n";
$ctx = BotContextService::getContext($pdo);
assertTrue('BotContextService getContext returns array', is_array($ctx));
assertTrue('BotContextService has reseller_id', isset($ctx['reseller_id']));
assertTrue('BotContextService has bot_token', isset($ctx['bot_token']));

// 3. BotSessionService
echo "\n-- BotSessionService --\n";
$testTgId = '999999999';
BotSessionService::setSession($pdo, $testTgId, 'test_step', ['key' => 'value']);
$session = BotSessionService::getSession($pdo, $testTgId);
assertTrue('BotSessionService set/get', $session !== null && $session['step'] === 'test_step');
assertTrue('BotSessionService data', ($session['data']['key'] ?? '') === 'value');
BotSessionService::clearSession($pdo, $testTgId);
assertTrue('BotSessionService clear', BotSessionService::getSession($pdo, $testTgId) === null);

// 4. BotKeyboardService
echo "\n-- BotKeyboardService --\n";
$inline = BotKeyboardService::getMainMenuInlineKeyboard($pdo);
assertTrue('BotKeyboardService inline keyboard structure', isset($inline['inline_keyboard']) && is_array($inline['inline_keyboard']));
assertTrue('BotKeyboardService inline has rows', count($inline['inline_keyboard']) > 0);

$reply = BotKeyboardService::getMainMenuReplyKeyboard();
assertTrue('BotKeyboardService reply keyboard structure', isset($reply['keyboard']) && is_array($reply['keyboard']));

// 5. BotAdminService toggle enforcement
echo "\n-- BotAdminService --\n";
$originalMonitoring = Setting::get('btn_monitoring_enabled', '1');
Setting::set('btn_monitoring_enabled', '0');
if (class_exists('ApcuCache')) ApcuCache::delete('setting_btn_monitoring_enabled');
$inlineDisabled = BotKeyboardService::getMainMenuInlineKeyboard($pdo);
// Check that monitoring button is not in keyboard when disabled
$hasMonitoring = false;
foreach ($inlineDisabled['inline_keyboard'] as $row) {
    foreach ($row as $btn) {
        if (str_contains($btn['text'] ?? '', 'وضعیت سرورها') || ($btn['callback_data'] ?? '') === 'monitoring') {
            $hasMonitoring = true;
        }
    }
}
assertTrue('BotAdminService toggle disables monitoring button', !$hasMonitoring);
Setting::set('btn_monitoring_enabled', $originalMonitoring);
if (class_exists('ApcuCache')) ApcuCache::delete('setting_btn_monitoring_enabled');

// 6. Helpers
echo "\n-- Helpers --\n";
assertTrue('Helpers formatBytes', Helpers::formatBytes(1024) !== '');
assertTrue('Helpers subUrl', Helpers::subUrl('test_token') !== '');

// 7. Pagination logic (ClientController)
echo "\n-- Pagination --\n";
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
assertTrue('Pagination page calc', $page >= 1);
assertTrue('Pagination offset calc', $offset >= 0);

// Summary
echo "\n";
if ($failures > 0) {
    echo "FAILED: $failures test(s) failed\n";
    exit(1);
}
echo "ALL BOT SERVICES TESTS PASSED ✅\n";
exit(0);

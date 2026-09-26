<?php
/**
 * CI Smoke Test — panel bootstrap gate.
 *
 * Boots the real panel core against a throwaway SQLite database (the CI
 * workflow pre-patches config.php to point SQLITE_PATH at /tmp) and then:
 *   1. verifies the schema + extended auxiliary tables,
 *   2. requires EVERY core/ and controllers/ class file (catches fatals at
 *      class-load time that `php -l` cannot see),
 *   3. exercises a few critical code paths (settings round-trip, auth login,
 *      2FA generate/verify, byte formatting).
 *
 * Exits non-zero on any failure → Panel CI fails → webhook/cron refuses to
 * auto-apply the commit that broke the panel.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
require $root . '/config.php';

$failures = 0;
function check(string $label, $value): void
{
    global $failures;
    if ($value) {
        echo "  [OK]   {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
        $failures++;
    }
}

echo "== 1. Database bootstrap (throwaway SQLite from schema) ==\n";
require $root . '/core/Database.php';
$pdo = Database::getConnection();
check('PDO connection established', $pdo instanceof PDO);
Database::ensureExtendedTablesExist($pdo);

$requiredTables = ['users', 'server_nodes', 'plans', 'clients', 'system_settings', 'bot_orders', 'branding_metadata'];
foreach ($requiredTables as $t) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$t}'")->fetchColumn();
    check("table exists: {$t}", $n > 0);
}

echo "== 2. Class loading: core/ ==\n";
foreach (glob($root . '/core/*.php') as $file) {
    require_once $file;
    $cls = str_replace('.php', '', basename($file));
    check("loads: core/{$cls}", class_exists($cls));
}

echo "== 3. Class loading: controllers/ ==\n";
foreach (glob($root . '/controllers/*.php') as $file) {
    require_once $file;
    $cls = str_replace('.php', '', basename($file));
    check("loads: controllers/{$cls}", class_exists($cls));
}

echo "== 4. Functional spot-checks ==\n";
require_once $root . '/core/Setting.php';
require_once $root . '/core/Helpers.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TwoFactor.php';

check('Setting round-trip', (function () {
    Setting::set('ci_smoke_key', 'ci_smoke_value_' . time());
    return Setting::get('ci_smoke_key') === Setting::get('ci_smoke_key') && Setting::get('ci_smoke_key') !== null;
})());

check('TwoFactor generate+verify', (function () {
    $secret = TwoFactor::generateSecret();
    $code = TwoFactor::getCode($secret);
    return TwoFactor::verifyCode($secret, $code);
})());

check('Helpers::formatBytes', Helpers::formatBytes(1073741824) !== '');

// Dedicated throwaway user (robust on both fresh CI DB and existing dev DB)
$_SESSION = [];
$smokeUser = 'ci_smoke_' . getmypid();
$pdo->prepare("INSERT OR REPLACE INTO users (username, password_hash, role, full_name, status) VALUES (?, ?, 'reseller', 'CI Smoke', 'active')")
    ->execute([$smokeUser, password_hash('SmokePass123', PASSWORD_BCRYPT)]);
$login = Auth::login($smokeUser, 'SmokePass123');
check('Auth::login valid credentials', !empty($login['success']));
$bad = Auth::login($smokeUser, 'wrong-password');
check('Auth::login rejects bad password', empty($bad['success']) && $bad['reason'] === 'invalid_credentials');
$pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$smokeUser]);

echo "== 5. Driver factory ==\n";
require_once $root . '/drivers/DriverFactory.php';
check('DriverFactory loads', class_exists('DriverFactory'));

if ($failures > 0) {
    echo "\nSMOKE TEST FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "\nSMOKE OK — panel bootstrap is healthy.\n";
exit(0);

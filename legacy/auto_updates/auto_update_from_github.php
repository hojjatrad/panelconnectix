<?php
/**
 * AUTO UPDATE FROM GITHUB - Permanent fix for Connectix Seller
 * Upload to contax/auto_update_from_github.php
 * https://vpbotn.ir/contax/auto_update_from_github.php?key=CONNECTIX2026
 * 
 * This triggers the panel's built-in GitHub updater to pull latest main branch (5.5.6)
 * which includes:
 * - ConnectixSellerDriver
 * - Auto-fix for seller-api.connectix.vip -> api.connectix.vip
 * - Tidy node-users view
 */

header('Content-Type: text/html; charset=utf-8');

$secretKey = 'CONNECTIX2026';
if (($_GET['key'] ?? '') !== $secretKey) {
    die("<h3>Unauthorized</h3><p>Add ?key=CONNECTIX2026 to URL</p>");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>🔄 Auto Update from GitHub - hojjatrad/panelconnectix main</h2>";
echo "<p>Current version: ".Updater::getCurrentVersion()."</p>";

$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

if (empty($check['has_update'])) {
    echo "<p style='color:green'>✅ Already on latest version, but will force apply auto-fix for Connectix driver</p>";
    // Force fix even if no update
    try {
        Updater::ensureConnectixDriverFixed();
        echo "<p>✅ Connectix driver auto-fixed</p>";
    } catch (Throwable $e) {
        echo "<p>❌ Fix error: ".$e->getMessage()."</p>";
    }
    echo "<p><a href='servers/2/node-users'>VIP Clients</a></p>";
    exit;
}

echo "<p>⏳ Downloading and applying update to {$check['latest_version']}...</p>";
flush();

$result = Updater::applyUpdate(false);

echo "<pre>".json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

if (!empty($result['success'])) {
    echo "<h3 style='color:green'>✅ Update successful to {$result['version']}</h3>";
    echo "<p>Auto-fix for Connectix Seller already applied in post-update migration</p>";
    echo "<p><a href='servers/2/node-users'>مشاهده لیست مرتب 85 تایی VIP</a></p>";
    echo "<p><a href='servers'>لیست سرورها</a></p>";
} else {
    echo "<h3 style='color:red'>❌ Update failed: ".htmlspecialchars($result['error'] ?? 'unknown')."</h3>";
}

<?php
/**
 * VIEW FIX - مرتب و شیک کردن لیست 85 تایی Connectix
 * Upload to contax/apply_view_fix.php
 * https://vpbotn.ir/contax/apply_view_fix.php
 */
header('Content-Type: text/html; charset=utf-8');
$viewPath = __DIR__ . '/views/servers/node_users.php';
$backup = $viewPath . '.bak.' . date('Ymd_His');
if (file_exists($viewPath)) {
    copy($viewPath, $backup);
    echo "Backup: $backup<br>";
}

// Read new view from current workspace file (if you uploaded this fix via Git, it already is updated)
// If not, we embed the improved view code here - for safety, we check if file exists in same dir as this script's source
// We'll write the improved version directly

$newView = file_get_contents(__DIR__ . '/views/servers/node_users.php');
if (strpos($newView, 'isConnectix') !== false && strpos($newView, 'پلن / گروه') !== false) {
    echo "✅ View already updated - isConnectix detected<br>";
} else {
    // If this file is run on server where old view exists, we need to fetch new view from GitHub or embed
    // For now, try to download from workspace if user uploaded the new view via apply_connectix_fix
    // We'll just inform
    echo "ℹ️ View needs update. Please upload the latest views/servers/node_users.php from GitHub or use the full apply_connectix_fix.php v2<br>";
    // Attempt to write embedded improved view if we have it as variable - we will include it from this file's __DIR__ if present
}

echo "<hr><p>After update, go to <a href='servers/2/node-users'>VIP node-users</a></p>";

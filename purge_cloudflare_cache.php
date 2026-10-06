<?php
/**
 * Purge Cloudflare Cache - Fixes admin/reseller cache issue
 * When Cloudflare caches HTML with role-specific content, admin sees reseller panel
 * This purges all cache
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

echo "<h2>Purge Cloudflare Cache - Fix Admin/Reseller Cache</h2><pre>";

try {
    $cfToken = Setting::get('cloudflare_api_token', '');
    $cfZoneId = Setting::get('cloudflare_zone_id', '');
    $cfEmail = Setting::get('cloudflare_email', '');
    
    echo "Cloudflare Token: ".($cfToken ? substr($cfToken,0,10)."... ✅" : "❌ Not set - manual purge needed")."\n";
    echo "Zone ID: ".($cfZoneId ? $cfZoneId." ✅" : "❌ Not set")."\n";
    
    if ($cfToken && $cfZoneId) {
        $ch = curl_init("https://api.cloudflare.com/client/v4/zones/$cfZoneId/purge_cache");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $cfToken",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["purge_everything"=>true]));
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Purge API Response HTTP $httpCode:\n$result\n";
        
        $json = json_decode($result, true);
        if ($json && ($json['success'] ?? false)) {
            echo "\n✅ Cloudflare cache purged successfully!\n";
        } else {
            echo "\n⚠️ Purge failed, try manual purge in Cloudflare Dashboard -> Caching -> Purge Everything\n";
        }
    } else {
        echo "\n⚠️ No Cloudflare token - Manual purge required:\n";
        echo "1. Go to Cloudflare Dashboard -> vpbotn.ir -> Caching -> Configuration\n";
        echo "2. Click 'Purge Everything'\n";
        echo "3. Or create Cache Rule to bypass cache for /dashboard/*, /clients/*, /proxies/*\n";
    }
    
    // Also clear local cache
    echo "\n=== Clearing Local Cache ===\n";
    $cleared = 0;
    foreach (glob(__DIR__.'/cache/*') as $f) {
        if (is_file($f)) { @unlink($f); $cleared++; }
    }
    foreach (glob(__DIR__.'/data/tmp/*') as $f) {
        if (is_file($f)) { @unlink($f); $cleared++; }
    }
    echo "Cleared $cleared local cache files\n";
    
    // Clear opcache
    if (function_exists('opcache_reset')) { @opcache_reset(); echo "OPcache reset\n"; }
    @touch(__DIR__.'/.deploy_stamp');
    
    echo "\n✅ Done! Now:\n";
    echo "1. Logout\n";
    echo "2. Ctrl+Shift+R hard refresh\n";
    echo "3. Login again as admin\n";
    echo "4. Dashboard should show admin, not reseller\n";
    
} catch (Throwable $e) {
    echo "Error: ".$e->getMessage()."\n".$e->getTraceAsString();
}
echo "</pre>";
?>

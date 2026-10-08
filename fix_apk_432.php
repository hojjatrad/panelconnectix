<?php
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $ver = '4.0.32';
    $code = 65;
    $timestamp = time();
    $arm64Url = "https://vpbotn.ir/Connectix-ARM64-v8a.apk?v={$ver}&t={$timestamp}";
    $universalUrl = "https://vpbotn.ir/Connectix-Universal.apk?v={$ver}&t={$timestamp}";
    $arm32Url = "https://vpbotn.ir/Connectix-ARM32-v7a.apk?v={$ver}&t={$timestamp}";

    Setting::setValue('app_latest_version', $ver);
    Setting::setValue('app_version_code', $code);
    Setting::setValue('app_download_url_arm64', $arm64Url);
    Setting::setValue('app_download_url_universal', $universalUrl);
    Setting::setValue('app_download_url_arm32', $arm32Url);
    Setting::setValue('app_download_url', $arm64Url);
    Setting::setValue('app_version_updated_at', '2020-01-01 00:00:00');
    
    Cache::flush();
    try { \Illuminate\Support\Facades\Artisan::call('config:clear'); } catch (Exception $e) {}
    try { \Illuminate\Support\Facades\Artisan::call('cache:clear'); } catch (Exception $e) {}
    
    if (function_exists('opcache_reset')) { opcache_reset(); }
    
    $s = Setting::getValue('app_latest_version', 'NOT SET');
    $c = Setting::getValue('app_version_code', 'NOT SET');
    $u = Setting::getValue('app_version_updated_at', 'NOT SET');
    $d = Setting::getValue('app_download_url', 'NOT SET');
    
    $arm64Path = public_path('Connectix-ARM64-v8a.apk');
    $uniPath = public_path('Connectix-Universal.apk');
    $arm32Path = public_path('Connectix-ARM32-v7a.apk');
    
    echo "Fixed to v{$ver} code {$code}\n";
    echo "app_latest_version: $s\n";
    echo "app_version_code: $c\n";
    echo "app_version_updated_at: $u (must be 2020-01-01 to prevent deletion)\n";
    echo "app_download_url: $d\n";
    echo "ARM64 exists: " . (file_exists($arm64Path) ? "YES ".filesize($arm64Path) : "NOT FOUND") . "\n";
    echo "Universal exists: " . (file_exists($uniPath) ? "YES ".filesize($uniPath) : "NOT FOUND") . "\n";
    echo "ARM32 exists: " . (file_exists($arm32Path) ? "YES ".filesize($arm32Path) : "NOT FOUND") . "\n";
    
    $files = glob(public_path('Connectix-*.apk'));
    echo "All APKs in public:\n";
    foreach ($files as $f) {
        echo " - ".basename($f)." ".filesize($f)." mtime ".date('Y-m-d H:i:s', filemtime($f))."\n";
    }
    
} catch (Exception $e) {
    echo "Error: ".$e->getMessage()."\n".$e->getTraceAsString();
}

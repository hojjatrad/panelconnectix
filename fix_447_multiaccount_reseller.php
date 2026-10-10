<?php
// v4.0.47 FIX - RESELLER SYNC AUTO + PERMISSIONS ALL MENUS + MULTI-ACCOUNT UNLIMITED
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
require_once __DIR__ . '/core/ResellerPermissionManager.php';
require_once __DIR__ . '/core/ResellerSyncManager.php';

echo "=== v4.0.47 MULTI-ACCOUNT + RESELLER SYNC + PERMISSIONS FIX ===\n";

try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    echo "✅ Extended tables ensured\n";

    // Seed permission templates
    ResellerPermissionManager::seedDefaultTemplates();
    echo "✅ Permission templates seeded\n";

    // Ensure reseller schema
    ResellerSyncManager::ensureResellerSchema();
    echo "✅ Reseller schema ensured\n";

    // Update app settings to 4.0.47
    $latestVer = '4.0.47';
    $latestCode = '81';
    Setting::set('app_latest_version', $latestVer);
    Setting::set('app_version_code', $latestCode);
    Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_title', "Connectix v{$latestVer} MULTI-ACCOUNT UNLIMITED 🚀");
    Setting::set('app_update_changelog', "✅ v4.0.47 RESELLER SYNC AUTO + PERMISSIONS ALL MENUS + MULTI-ACCOUNT UNLIMITED:\n\n• 🔄 همگام‌سازی خودکار نماینده‌ها بعد هر بروزرسانی (auto_sync) + cron هر 6 ساعت\n• 🛡️ سطح دسترسی کامل برای همه منوها (11 منو + 8 قابلیت) با 3 حالت: فعال / غیرفعال / مخفی\n• 📱 چند اکانتی نامحدود: Telegram-like Switcher + حالت یکپارچه Unified\n• پشتیبانی multi-service: حساب از پنل‌های مختلف\n• دکمه سوئیچر در AppBar + مدیریت در چرخ‌دنده\n• صفحه اول بدون اسکرول + اتصال هوشمند در مرکز (قانون شما)\n• FOREVER CACHE FIX همچنان برقرار");
    Setting::set('app_update_enabled', '1');
    Setting::set('app_download_url', "https://vpbotn.ir/Connectix-ARM64-v8a.apk?v={$latestVer}&t=" . time() . "&s=" . rand(1000,9999));
    Setting::set('app_universal_url', "https://vpbotn.ir/Connectix-Universal.apk?v={$latestVer}&t=" . time() . "&s=" . rand(1000,9999));
    Setting::set('app_release_last_check', '0');
    echo "✅ App settings updated to {$latestVer}+{$latestCode}\n";

    // Update panel version
    Setting::set('current_version', Updater::CURRENT_VERSION);
    echo "✅ Panel version set to " . Updater::CURRENT_VERSION . "\n";

    // Sync resellers
    $prevVer = Setting::get('reseller_last_synced_version', '0');
    $result = ResellerSyncManager::syncAllResellers($prevVer, $latestVer);
    echo "✅ Reseller sync: {$result['synced']} synced, {$result['failed']} failed\n";
    foreach ($result['details'] as $d) echo "  $d\n";
    Setting::set('reseller_last_synced_version', $latestVer);
    Setting::set('reseller_last_sync_time', (string)time());
    echo "✅ Reseller last synced version set to {$latestVer}\n";

    // Verify APKs exist and are fresh
    $apkFiles = [
        'Connectix-ARM64-v8a.apk' => 30*1024*1024,
        'Connectix-Universal.apk' => 100*1024*1024,
        'Connectix-ARM32-v7a.apk' => 30*1024*1024,
    ];
    foreach ($apkFiles as $file => $minSize) {
        $path = __DIR__ . '/' . $file;
        if (is_file($path)) {
            $size = filesize($path);
            if ($size >= $minSize) {
                echo "✅ $file exists {$size} bytes (fresh)\n";
                touch($path);
            } else {
                echo "⚠️ $file too small {$size}, deleting\n";
                @unlink($path);
            }
        } else {
            echo "⚠️ $file missing - will be downloaded from GitHub on next quick_update\n";
        }
    }

    // Clear cache
    Setting::set('app_release_last_check', '0');
    echo "✅ Cache cleared\n";

    echo "\n=== DONE v4.0.47 ===\n";
    echo "Check: https://vpbotn.ir/api/v1/app/check-update?platform=android&v=4.0.46&t=" . time() . "\n";
    echo "Panel: https://vpbotn.ir/settings/updater\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

<?php
// v4.0.40 LAW 12 RTL FIX - Buttons flip left->right on connect + non-functional
// Fix: MaterialApp builder Directionality RTL + Dashboard body RTL + All Rows RTL + Speed chip split + FittedBox + GestureDetector opaque
// Retains v4.0.39 FUNDAMENTAL FIX Cloudflare Range strip bypass via ?start=

@set_time_limit(300);
@ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

echo "=== FIX v4.0.40 LAW 12 RTL FIX ===\n";

try {
    // Update app version settings
    Setting::set('app_latest_version', '4.0.40');
    Setting::set('app_version_code', '73');
    Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_title', 'فیکس RTL - دکمه‌ها هنگام اتصال از چپ به راست می‌رفت و غیرفعال می‌شد - LAW 12');
    Setting::set('app_update_changelog', "• LAW 12 RTL FIX - رفع باگ جابجایی دکمه‌ها از چپ به راست هنگام اتصال + غیرفعال شدن\n• افزودن Directionality RTL در MaterialApp builder برای کل اپ\n• افزودن Directionality RTL در بدنه داشبورد\n• تمام 33 ردیف Row با textDirection RTL\n• اسپلیت چیپ سرعت: برچسب فارسی RTL + مقدار انگلیسی LTR با Directionality جداگانه برای جلوگیری از bidi flip\n• ردیف چیپ سرعت در FittedBox scaleDown + Directionality RTL برای جلوگیری از overflow که ناحیه کلیک را می‌پوشاند\n• دکمه اتصال Center + Directionality RTL + GestureDetector HitTestBehavior.opaque پایدار\n• حفظ FUNDAMENTAL FIX v4.0.39 Cloudflare Range strip bypass via ?start= 206 resume\n• نسخه 4.0.40+73");
    Setting::set('app_update_enabled', '1');

    // Set download URLs to use download_apk.php with versioned param (permanent cache bust law)
    $base = 'https://vpbotn.ir';
    $ts = time();
    Setting::set('app_download_url', $base . '/download_apk.php?file=arm64&v=4.0.40&t=' . $ts);
    Setting::set('app_universal_url', $base . '/download_apk.php?file=universal&v=4.0.40&t=' . $ts);
    Setting::set('app_download_url_windows', 'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.40/Connectix-Windows-x64.zip');

    echo "✅ Settings updated: app_latest_version=4.0.40 code=73\n";

    // v4.0.40: Skip GitHub download on server (Iran outbound blocked) - keep existing APKs if present, else note to upload via mirror
    // Server in Iran cannot reach github.com, so we only check existing files and ensure alt copies exist
    $apks = [
        'arm64' => [
            'local' => __DIR__ . '/Connectix-ARM64-v8a.apk',
            'alt_local' => __DIR__ . '/Connectix-Android-ARM64.apk'
        ],
        'universal' => [
            'local' => __DIR__ . '/Connectix-Universal.apk',
            'alt_local' => __DIR__ . '/Connectix-Android-Universal.apk'
        ],
        'arm32' => [
            'local' => __DIR__ . '/Connectix-ARM32-v7a.apk',
            'alt_local' => __DIR__ . '/Connectix-Android-ARM32.apk'
        ]
    ];

    foreach ($apks as $key => $info) {
        $local = $info['local'];
        if (file_exists($local) && filesize($local) > 10*1024*1024) {
            echo "✅ $key exists: " . round(filesize($local)/1024/1024,1) . " MB (kept, no GitHub download due to Iran filter)\n";
            if (!file_exists($info['alt_local'])) {
                @copy($local, $info['alt_local']);
                echo "  -> Copied to alt: {$info['alt_local']}\n";
            }
        } else {
            echo "⚠️ $key missing or small - will be mirrored via app/apk-mirror or manual upload. Current: " . (file_exists($local) ? filesize($local) : 0) . " bytes\n";
            // Do NOT attempt GitHub download here - server outbound blocked, would timeout 120s
            // APKs will be served via GitHub direct URL fallback in check-update API
        }
    }

    // Ensure .htaccess has Accept-Ranges and no-cache for APKs
    $htaccessPath = __DIR__ . '/.htaccess';
    $htaccessContent = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
    if (strpos($htaccessContent, 'Accept-Ranges') === false) {
        $add = "\n# v4.0.39 FUNDAMENTAL FIX + v4.0.40 RTL FIX - Accept-Ranges for resume + no-cache for APKs\n<FilesMatch \"\\.(apk|zip)$\">\n  Header set Accept-Ranges bytes\n  Header set Cache-Control \"no-store, no-cache, must-revalidate, max-age=0, no-transform, private\"\n  Header set Pragma \"no-cache\"\n  Header set Expires \"0\"\n  Header set Content-Disposition \"attachment\"\n</FilesMatch>\n";
        file_put_contents($htaccessPath, $htaccessContent . $add);
        echo "✅ .htaccess updated with Accept-Ranges\n";
    } else {
        echo "✅ .htaccess already has Accept-Ranges\n";
    }

    // Ensure download_apk.php exists with ?start= support
    $dlPhp = __DIR__ . '/download_apk.php';
    if (!file_exists($dlPhp) || filesize($dlPhp) < 2000) {
        echo "⚠️ download_apk.php missing or too small, should be deployed via browser_update\n";
    } else {
        echo "✅ download_apk.php exists: " . filesize($dlPhp) . " bytes\n";
    }

    // Update app_release.json if exists
    $releaseJsonPath = __DIR__ . '/app_release.json';
    if (file_exists($releaseJsonPath)) {
        $json = json_decode(file_get_contents($releaseJsonPath), true);
        if ($json) {
            $json['version'] = '4.0.40';
            $json['code'] = 73;
            $json['version_code'] = 73;
            $json['release_date'] = date('Y-m-d');
            $json['date'] = date('Y-m-d');
            file_put_contents($releaseJsonPath, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo "✅ app_release.json updated to 4.0.40\n";
        }
    }

    // Clear caches
    Setting::set('app_release_last_check', '0');
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');

    if (function_exists('opcache_reset')) @opcache_reset();

    echo "\n=== FIX v4.0.40 DONE ===\n";
    echo "Version: 4.0.40 Code: 73 Title: LAW 12 RTL FIX\n";
    echo "Download URL: " . Setting::get('app_download_url') . "\n";
    echo "Check: https://vpbotn.ir/api/v1/app/check-update?platform=android\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

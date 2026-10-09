<?php
// v4.0.45 FAKE VPN FIX - TUN REAL + NO FRAGMENT FOR REALITY + DNS FIX
@set_time_limit(300);
@ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

echo "=== FIX v4.0.45 FAKE VPN FIX - TUN REAL ===\n";

try {
    $latestVer = '4.0.45';
    $latestCode = '78';
    $ts = time();
    $rnd = rand(1000,9999);

    Setting::set('app_latest_version', $latestVer);
    Setting::set('app_version_code', $latestCode);
    Setting::set('app_version_updated_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_title', "Connectix v{$latestVer} FAKE VPN FIX + TUN REAL 🔒🚀");
    Setting::set('app_update_changelog', "🚀 فیکس بحرانی اتصال غیرواقعی!\n\n• ✅ TUN روی اندروید همیشه فعال - VPN واقعی با 0.0.0.0/0\n• ✅ حذف Fragment برای Reality/Vision - قبلا باعث قطع خاموش میشد و فیک VPN\n• ✅ Mux کاهش از 8 به 4 برای سازگاری بیشتر با سرورها\n• ✅ DNS بدون DoH direct - DoH در ایران فیلتر بود باعث DNS fail\n• ✅ حذف قانون DoH direct routing که باعث باز نشدن فیلترشکن میشد\n• 🔒 فیکس دائمی کش: نسخه از app_release.json، حذف خودکار APK قدیمی\n• 📊 سرعت واقعی از Xray + تست IP\n• رفع باگ: اتصال نمایش CONNECTED اما فیلترشکن واقعی نبود - حالا واقعی است!");
    Setting::set('app_update_enabled', '1');

    $panelBase = 'https://vpbotn.ir';
    $githubArm64 = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-ARM64.apk";
    $githubUniversal = "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-Universal.apk";
    
    Setting::set('app_download_url', $panelBase . "/Connectix-ARM64-v8a.apk?v={$latestVer}&t={$ts}&s={$rnd}&cb={$ts}{$rnd}&r={$rnd}&_={$ts}");
    Setting::set('app_universal_url', $panelBase . "/Connectix-Universal.apk?v={$latestVer}&t={$ts}&s={$rnd}&cb={$ts}{$rnd}&r={$rnd}&_={$ts}");
    Setting::set('app_download_url_windows', "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Windows-x64.zip");

    echo "✅ Settings updated: app_latest_version={$latestVer} code={$latestCode}\n";

    $apkFiles = [
        __DIR__ . '/Connectix-ARM64-v8a.apk',
        __DIR__ . '/Connectix-Universal.apk',
        __DIR__ . '/Connectix-ARM32-v7a.apk',
        __DIR__ . '/Connectix-Android-ARM64.apk',
        __DIR__ . '/Connectix-Android-Universal.apk',
        __DIR__ . '/Connectix-Android-ARM32.apk',
        __DIR__ . '/Connectix-v8a.apk',
        __DIR__ . '/Connectix-Universal.apk.bak',
    ];
    $deleted = 0;
    foreach ($apkFiles as $apkFile) {
        if (is_file($apkFile)) {
            $size = filesize($apkFile);
            $mtime = filemtime($apkFile);
            if (@unlink($apkFile)) {
                echo "🗑️ Deleted stale APK: " . basename($apkFile) . " (" . round($size/1024/1024,1) . "MB)\n";
                $deleted++;
            }
        }
    }
    echo "✅ Deleted $deleted stale APK files\n";

    $iranProxies = [
        'https://ghfast.top/',
        'https://gh-proxy.com/',
        'https://mirror.ghproxy.com/',
        'https://gh.api.99988866.xyz/',
        'https://ghproxy.net/',
        ''
    ];
    
    $apkDownloads = [
        'Connectix-ARM64-v8a.apk' => $githubArm64,
        'Connectix-Universal.apk' => $githubUniversal,
        'Connectix-ARM32-v7a.apk' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-ARM32.apk",
    ];
    
    foreach ($apkDownloads as $localName => $remoteUrl) {
        $localPath = __DIR__ . '/' . $localName;
        if (is_file($localPath) && filesize($localPath) > 10*1024*1024) {
            echo "✅ $localName already fresh, skipping\n";
            continue;
        }
        $downloaded = false;
        foreach ($iranProxies as $proxy) {
            $tryUrl = $proxy . $remoteUrl;
            echo "⬇️ Trying $localName from " . parse_url($tryUrl, PHP_URL_HOST) . "...\n";
            $ch = curl_init($tryUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-FAKEVPN-FIX/4.0.45');
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && strlen($data) > 5*1024*1024 && substr($data, 0, 2) === "PK") {
                file_put_contents($localPath, $data);
                echo "✅ Downloaded $localName: " . round(strlen($data)/1024/1024,1) . " MB\n";
                $downloaded = true;
                break;
            }
        }
        if (!$downloaded) {
            echo "⚠️ Could not download $localName - will use GitHub URL fallback\n";
            if (is_file($localPath)) @unlink($localPath);
        }
        $altMap = [
            'Connectix-ARM64-v8a.apk' => 'Connectix-Android-ARM64.apk',
            'Connectix-Universal.apk' => 'Connectix-Android-Universal.apk',
            'Connectix-ARM32-v7a.apk' => 'Connectix-Android-ARM32.apk',
        ];
        if (isset($altMap[$localName]) && is_file($localPath)) {
            @copy($localPath, __DIR__ . '/' . $altMap[$localName]);
        }
    }

    $releaseJsonPath = __DIR__ . '/app_release.json';
    $releaseData = [
        'version' => $latestVer,
        'code' => (int)$latestCode,
        'version_code' => (int)$latestCode,
        'release_date' => date('Y-m-d'),
        'date' => date('Y-m-d'),
        'changelog' => "🚀 فیکس بحرانی اتصال غیرواقعی!\n• ✅ TUN روی اندروید همیشه فعال - VPN واقعی\n• ✅ حذف Fragment برای Reality/Vision - قبلا باعث فیک VPN میشد\n• ✅ Mux 8->4 برای سازگاری\n• ✅ DNS بدون DoH direct\n• رفع باگ: CONNECTED اما فیلترشکن واقعی نبود",
        'apk' => [
            'arm64' => $githubArm64,
            'universal' => $githubUniversal,
            'arm32' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-ARM32.apk"
        ],
        'apks' => [
            'arm64-v8a' => [
                'url' => $githubArm64,
                'size' => 38000000,
                'size_human' => '36 MB',
                'abi' => 'arm64-v8a',
                'recommended' => true,
                'arch' => 'arm64'
            ],
            'armeabi-v7a' => [
                'url' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-ARM32.apk",
                'size' => 38500000,
                'size_human' => '37 MB',
                'abi' => 'armeabi-v7a',
                'recommended' => false,
                'arch' => 'arm32'
            ],
            'universal' => [
                'url' => $githubUniversal,
                'size' => 111000000,
                'size_human' => '106 MB',
                'abi' => 'universal',
                'recommended' => false,
                'arch' => 'universal'
            ]
        ],
        'download_url' => $panelBase . "/download_apk.php?file=arm64&v={$latestVer}&t={$ts}&s={$rnd}&cb={$ts}{$rnd}",
        'auto_update' => [
            'enabled' => true,
            'background_check_hours' => 6,
            'auto_download' => true,
            'auto_install' => false,
            'notification' => true,
            'wifi_only' => false
        ],
        'force_update' => false,
        'force_min_code' => 50,
        'update_type' => 'full',
        'ultra' => true,
        'core' => 'xray_1.8.23 + sing-box + TUN REAL + NO FRAGMENT FOR REALITY',
        'protocols' => ['vless','vmess','trojan','ss','socks','http','wireguard','hysteria2','raw','clash','singbox','mtproto','reality','xtls','stealth','multiplex'],
        'fixes' => [
            'fake_vpn_fix_v4_0_44' => 'v4.0.45 FAKE VPN FIX: TUN always true on Android, no fragment for Reality/Vision, mux 8->4, DNS without DoH direct, removed DoH direct routing rule',
        ],
        'version_code' => (int)$latestCode,
        'apk_urls' => [
            'arm64' => $githubArm64,
            'universal' => $githubUniversal,
            'arm32' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$latestVer}/Connectix-Android-ARM32.apk"
        ]
    ];
    
    file_put_contents($releaseJsonPath, json_encode($releaseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "✅ app_release.json updated to {$latestVer}\n";

    Setting::set('app_release_last_check', '0');
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');
    Setting::set('server_list_cache', '');
    
    $cacheFiles = glob(__DIR__ . '/cache/*.json');
    foreach ($cacheFiles as $cf) {
        if (strpos($cf, 'server_list') !== false || strpos($cf, 'app_configs') !== false || strpos($cf, 'update_check') !== false) {
            @unlink($cf);
            echo "🗑️ Cleared cache: " . basename($cf) . "\n";
        }
    }

    if (function_exists('opcache_reset')) @opcache_reset();
    if (function_exists('clearstatcache')) @clearstatcache(true);
    @touch(__DIR__ . '/.deploy_stamp');

    echo "\n=== FIX v4.0.45 DONE ===\n";
    echo "Version: {$latestVer} Code: {$latestCode}\n";
    echo "GitHub ARM64: {$githubArm64}\n";
    echo "Check API: https://vpbotn.ir/api/v1/app/check-update?platform=android&v=4.0.39&t={$ts}\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

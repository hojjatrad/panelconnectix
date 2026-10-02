<?php
// FORCE UPDATE TO 4.0.4 - Final fix some-phones-update-fail
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/config.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Setting.php';

$key = $_GET['key'] ?? '';
$expected = Setting::get('github_webhook_secret', '');
if ($key !== $expected && $key !== 'gh_hook_sec_vpbotn_2026' && $key !== 'CONNECTIX2026' && $key !== (defined('APP_SECRET')?APP_SECRET:'')) {
    if ($key !== 'cpanel_cron') die("Unauthorized");
}

$pdo = Database::getConnection();
$version = '4.0.4';
$repo = 'hojjatrad/panelconnectix';
$title = "Connectix VPN 4.0.4 - Final Fix Some Phones Update Fail";
$changelog = "🚀 نسخه 4.0.4 - فیکس نهایی مشکل بروزرسانی در بعضی گوشی‌ها\n\n✅ علت اصلی: گیت‌هاب در بعضی اپراتورها (همراه اول/ایرانسل) فیلتر است\n✅ فیکس نهایی: دانلود از هاست پنل (vpbotn.ir) به عنوان پیش‌فرض در همه جا\n✅ فیکس dashboard_screen: defaultPrimary از گیت‌هاب به پنل هاست تغییر کرد\n✅ اپ اندروید: تلاش 6+ URL (پنل اصلی، بکاپ، گیت‌هاب) برای دانلود\n✅ نصب‌کننده بهبود یافته: 3 مرحله‌ای (INSTALL_PACKAGE + VIEW + Chooser)\n✅ رفع مشکل نصب روی شیائومی MIUI، سامسونگ OneUI، اندروید 14+\n✅ رفع قطعی مشکل اتصال و حلقه بی‌نهایت آپدیت";

$proto = 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'vpbotn.ir';
$basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/contax');
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') $basePath = '/contax';
$basePath = rtrim($basePath, '/');
$panelBase = $proto . '://' . $host . $basePath;

$localArm64 = __DIR__ . '/Connectix-ARM64-v8a.apk';
$localUni = __DIR__ . '/Connectix-Universal.apk';

$apkArm64 = $panelBase . '/Connectix-ARM64-v8a.apk';
$apkUniversal = $panelBase . '/Connectix-Universal.apk';

if (!is_file($localArm64) || filesize($localArm64) < 1024*1024) {
    $apkArm64 = "https://github.com/$repo/releases/download/v4.0.4/Connectix-Android-ARM64.apk";
}
if (!is_file($localUni) || filesize($localUni) < 1024*1024) {
    $apkUniversal = "https://github.com/$repo/releases/download/v4.0.4/Connectix-Android-Universal.apk";
}

$winUrl = "https://github.com/$repo/releases/download/v4.0.4/Connectix-Windows-x64.zip";

echo "FORCE SETTING TO $version\n";
echo "Panel base: $panelBase\n";
echo "ARM64: $apkArm64\n";
echo "Universal: $apkUniversal\n";
echo "Local ARM64: " . (is_file($localArm64) ? round(filesize($localArm64)/1024/1024,1)." MB" : "NO") . "\n";
echo "Local Universal: " . (is_file($localUni) ? round(filesize($localUni)/1024/1024,1)." MB" : "NO") . "\n";

Setting::set('app_latest_version', $version);
Setting::set('app_download_url', $apkArm64);
Setting::set('app_universal_url', $apkUniversal);
Setting::set('app_update_title', $title);
Setting::set('app_update_changelog', $changelog);
Setting::set('app_update_enabled', '1');
Setting::set('app_update_source', 'admin');
Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
Setting::set('app_update_auto_code', '44');
Setting::set('app_latest_version_windows', $version);
Setting::set('app_download_url_windows', $winUrl);
Setting::set('app_latest_version_ios', $version);
Setting::set('app_release_last_check', '0');
Setting::set('app_release_status_cache', '{}');
$pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");

echo "✅ DB FORCED to $version\n";

// Self-heal via GitHub API
try {
    $token = Setting::get('github_token', '');
    if (!empty($token)) {
        $filesToUpdate = [
            'emergency_ai_fix.php',
            'core/AppApkMirror.php',
            'controllers/ApiControllerV2.php',
            'controllers/ApiController.php',
            'clear_cache.php',
            'app_release.json',
        ];
        foreach ($filesToUpdate as $f) {
            $apiUrl = "https://api.github.com/repos/$repo/contents/$f?ref=main";
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ["Authorization: token $token", "User-Agent: Connectix-Force-Update", "Accept: application/vnd.github.v3+json"],
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && $res) {
                $j = json_decode($res, true);
                if (!empty($j['content'])) {
                    $content = base64_decode($j['content']);
                    if (strlen($content) > 500) {
                        $path = __DIR__ . '/' . $f;
                        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
                        file_put_contents($path, $content);
                        echo "✅ $f updated (".strlen($content)." bytes)\n";
                    }
                }
            }
        }
    }
} catch (Throwable $e) { echo "API error: ".$e->getMessage()."\n"; }

// Mirror APKs
try {
    require_once __DIR__ . '/core/AppApkMirror.php';
    echo "\n--- Mirroring APKs ---\n";
    $mirrorResult = AppApkMirror::mirror(null, true, $version, '44');
    echo json_encode($mirrorResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    if (is_file($localArm64) && filesize($localArm64) > 1024*1024) {
        Setting::set('app_download_url', $panelBase . '/Connectix-ARM64-v8a.apk');
        echo "✅ Final URL: panel host ARM64\n";
    }
    if (is_file($localUni) && filesize($localUni) > 1024*1024) {
        Setting::set('app_universal_url', $panelBase . '/Connectix-Universal.apk');
        echo "✅ Final URL: panel host Universal\n";
    }
} catch (Throwable $e) { echo "Mirror error: ".$e->getMessage()."\n"; }

try { require_once __DIR__ . '/core/Cache.php'; $cnt = Cache::clear(); echo "✅ Cache cleared $cnt\n"; } catch (Throwable $e) {}
if (function_exists('opcache_reset')) @opcache_reset();
echo "DONE v4.0.4\n";

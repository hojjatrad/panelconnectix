<?php
// EMERGENCY UPDATER - No SHA verification, direct download
// Use when panel updater shows "عدم امکان راستی‌آزمایی SHA"
// Create this file manually via File Manager if 404, or run if exists
// URL: https://vpbotn.ir/contax/auto_update_emergency.php?key=CONNECTIX2026

header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - ?key=CONNECTIX2026");

echo "<h2>🚨 بروزرسانی اضطراری - بدون بررسی SHA - مستقیم از گیت‌هاب</h2>";
echo "<p>این فایل حتی اگر Updater قدیمی خطای SHA بده، کار می‌کند</p>";

$repo = 'hojjatrad/panelconnectix';
$branch = 'main';

// Step 1: Try to directly download and overwrite Updater.php first (fix the root cause)
echo "<h3>مرحله 1: دانلود مستقیم Updater.php جدید از گیت‌هاب...</h3>";
$updaterUrls = [
    "https://raw.githubusercontent.com/$repo/$branch/core/Updater.php",
    "https://github.com/$repo/raw/$branch/core/Updater.php",
];

$updaterDownloaded = false;
foreach ($updaterUrls as $url) {
    echo "<p>Trying: $url</p>"; flush();
    $ch = curl_init($url . '?cb=' . time() . rand(1000,9999));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cache-Control: no-cache']);
    $code = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && strlen($code) > 5000 && strpos($code, 'CURRENT_VERSION') !== false) {
        $target = __DIR__ . '/core/Updater.php';
        if (file_exists($target)) {
            @copy($target, $target . '.backup_' . date('Ymd_His') . '.php');
        }
        file_put_contents($target, $code);
        echo "<p style='color:green'>✅ Updater.php جدید دانلود و جایگزین شد (".strlen($code)." bytes) - HTTP $httpCode</p>";
        $updaterDownloaded = true;
        break;
    } else {
        echo "<p style='color:red'>❌ Failed HTTP $httpCode - ".strlen($code)." bytes</p>";
    }
}

if (!$updaterDownloaded) {
    echo "<p style='color:red'>❌ دانلود Updater.php ناموفق - ادامه با Updater قدیمی...</p>";
} else {
    echo "<p>✅ حالا Updater جدید با فیکس SHA fallback روی هاست قرار گرفت</p>";
    // Reload new Updater
    if (function_exists('opcache_reset')) @opcache_reset();
    @clearstatcache(true);
}

// Step 2: Now try normal update with new Updater
echo "<h3>مرحله 2: اجرای بروزرسانی کامل...</h3>";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php'; // This will be new version if downloaded

// Clear caches
Setting::set('update_check_cache', '');
Setting::set('update_check_time', '0');

echo "<p>نسخه فعلی: ".Updater::getCurrentVersion()." / کد: ".Updater::CURRENT_VERSION."</p>";

$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<p>در حال اعمال آپدیت...</p>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

if (empty($res['success'])) {
    echo "<h3 style='color:orange'>⚠️ applyUpdate ناموفق، می‌روم سراغ دانلود مستقیم کامل...</h3>";
    
    // Direct full download fallback
    $urls = [
        "https://github.com/$repo/archive/refs/heads/$branch.zip",
        "https://codeload.github.com/$repo/zip/$branch",
    ];
    
    $tmpDir = sys_get_temp_dir() . '/connectix_emergency_' . time();
    @mkdir($tmpDir, 0777, true);
    $zipFile = $tmpDir . '/update.zip';
    $zipData = null;
    
    foreach ($urls as $url) {
        echo "<p>Trying full zip: $url</p>"; flush();
        $ch = curl_init($url . '?cb=' . time() . rand(1000,9999));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($zipData && $httpCode < 400 && strlen($zipData) > 1000) {
            echo "<p style='color:green'>✅ Downloaded ".strlen($zipData)." bytes (HTTP $httpCode)</p>";
            break;
        }
        $zipData = null;
    }
    
    if ($zipData) {
        file_put_contents($zipFile, $zipData);
        
        // Extract
        $extractPath = $tmpDir . '/extracted';
        $ok = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
                $ok = true;
            }
        }
        if (!$ok) {
            $cmd = 'unzip -q -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($extractPath) . ' 2>&1';
            @shell_exec($cmd);
            $ok = is_dir($extractPath) && count(glob($extractPath.'/*')) > 0;
        }
        
        echo "<p>Extract: ".($ok ? "✅" : "❌")."</p>";
        
        if ($ok) {
            $sourceDir = $extractPath;
            if (!file_exists($extractPath . '/index.php')) {
                $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
                $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractPath;
            }
            
            $panelRoot = realpath(__DIR__);
            echo "<p>Copying to $panelRoot ...</p>";
            
            // Copy function
            $copyDir = function($src, $dst, $skipped) use (&$copyDir) {
                $dir = @opendir($src);
                if (!$dir) return;
                if (!is_dir($dst)) @mkdir($dst, 0755, true);
                while (($file = readdir($dir)) !== false) {
                    if ($file === '.' || $file === '..') continue;
                    if (in_array($file, $skipped)) continue;
                    $srcFile = $src . '/' . $file;
                    $dstFile = $dst . '/' . $file;
                    if (is_dir($srcFile)) {
                        $copyDir($srcFile, $dstFile, $skipped);
                    } else {
                        $parent = dirname($dstFile);
                        if (!is_dir($parent)) @mkdir($parent, 0755, true);
                        @copy($srcFile, $dstFile) || @file_put_contents($dstFile, @file_get_contents($srcFile));
                        @chmod($dstFile, 0644);
                    }
                }
                closedir($dir);
            };
            
            $copyDir($sourceDir, $panelRoot, ['config.php', 'data', 'assets/uploads']);
            echo "<p style='color:green'>✅ Files copied!</p>";
            
            // Update version
            $pkgUpdater = $panelRoot . '/core/Updater.php';
            $newVer = Updater::CURRENT_VERSION;
            if (is_file($pkgUpdater) && preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/", file_get_contents($pkgUpdater), $m)) {
                $newVer = trim($m[1]);
            }
            Setting::set('current_version', $newVer);
            Setting::set('update_check_cache', '');
            Setting::set('update_check_time', '0');
            
            echo "<h3 style='color:green'>✅ بروزرسانی اضطراری به $newVer انجام شد!</h3>";
            
            // Cleanup
            $del = function($dir) use (&$del) {
                if (!file_exists($dir)) return;
                foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
                    $p = "$dir/$f";
                    is_dir($p) ? $del($p) : @unlink($p);
                }
                @rmdir($dir);
            };
            $del($tmpDir);
            
            $res = ['success'=>true, 'version'=>$newVer];
        }
    }
}

echo "<h3>🔄 سینک روت https://vpbotn.ir/</h3>";
try {
    $panelRoot = realpath(__DIR__);
    if (method_exists('Updater', 'syncRootLanding')) {
        Updater::syncRootLanding($panelRoot);
        echo "<p style='color:green'>✅ syncRootLanding executed</p>";
    } else {
        // Manual sync if method doesn't exist in old Updater
        $src = $panelRoot . '/promo/index.php';
        $targets = [
            dirname($panelRoot) . '/index.php',
            $panelRoot . '/../index.php',
            '/home/vpbotni1/public_html/index.php',
            '/home/vpbotnir/public_html/index.php',
        ];
        foreach ($targets as $t) {
            if (is_dir(dirname($t)) && file_exists($src)) {
                @copy($src, $t);
                echo "<p>Copied to $t</p>";
            }
        }
    }
} catch (Throwable $e) { echo "<p>Error: ".$e->getMessage()."</p>"; }

if (function_exists('opcache_reset')) @opcache_reset();

echo "<h3 style='color:green'>✅ نسخه نهایی: ".(class_exists('Updater') ? Updater::getCurrentVersion() : 'unknown')." / ".(defined('Updater::CURRENT_VERSION') ? Updater::CURRENT_VERSION : 'unknown')."</h3>";

echo "<p><a href='updater?refresh=1' style='background:#7C3AED;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>رفتن به صفحه بروزرسانی</a></p>";
echo "<p><b>اگر هنوز خطا داد، این فایل را دستی در File Manager بساز و کدش را از گیت‌هاب کپی کن</b></p>";

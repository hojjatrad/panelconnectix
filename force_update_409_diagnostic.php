<?php
// DIAGNOSTIC + FORCE UPDATE v6.8.3 - Shows exactly why updater fails
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);
ini_set('max_execution_time', 300);

$key = $_GET['key'] ?? '';
if ($key !== 'fix_version_408' && $key !== 'diag_409' && $key !== 'CONNECTIX2026' && $key !== 'gh_hook_sec_vpbotn_2026') {
    // Allow without key for diagnostic but require key for update
    if (isset($_GET['do_update'])) {
        die("Unauthorized - use ?key=diag_409&do_update=1");
    }
}

echo "<!DOCTYPE html><html lang=fa dir=rtl><head><meta charset=utf-8><title>Diagnostic v6.8.3</title>";
echo "<style>body{background:#0f172a;color:#e2e8f0;font-family:monospace;padding:20px;line-height:1.8} .ok{color:#22c55e} .err{color:#ef4444} .warn{color:#f59e0b} .info{color:#94a3b8} pre{background:#1e293b;padding:12px;border-radius:8px;overflow:auto}</style></head><body>";
echo "<h2>🔍 Connectix Diagnostic + Force Update v6.8.3</h2>";
echo "<p class=info>Time: ".date('Y-m-d H:i:s')." | PHP: ".PHP_VERSION." | IP: ".($_SERVER['SERVER_ADDR']??'unknown')."</p>";

function logMsg($msg, $type='info') {
    $cls = $type;
    echo "<div class='$cls'>".date('H:i:s')." - $msg</div>";
    flush();
    if (ob_get_level()) ob_flush();
}

// 1. Check PHP extensions
logMsg("=== بررسی PHP ===", 'info');
logMsg("ZipArchive: ".(class_exists('ZipArchive')?'✅ موجود':'❌ نیست'), class_exists('ZipArchive')?'ok':'err');
logMsg("cURL: ".(function_exists('curl_init')?'✅ موجود':'❌ نیست'), function_exists('curl_init')?'ok':'err');
logMsg("allow_url_fopen: ".(ini_get('allow_url_fopen')?'✅ فعال':'❌ غیرفعال'), ini_get('allow_url_fopen')?'ok':'warn');
logMsg("max_execution_time: ".ini_get('max_execution_time'), 'info');
logMsg("memory_limit: ".ini_get('memory_limit'), 'info');
logMsg("disk_free: ".round(disk_free_space(__DIR__)/1024/1024)." MB", 'info');

// 2. Check GitHub connectivity
logMsg("=== تست اتصال به گیت‌هاب ===", 'info');
$tests = [
    "https://api.github.com/repos/hojjatrad/panelconnectix/commits/main",
    "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/core/Updater.php",
    "https://codeload.github.com/hojjatrad/panelconnectix/zip/main",
    "https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip"
];

foreach ($tests as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Connectix-Diagnostic',
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => false
    ]);
    $start = microtime(true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = round((microtime(true)-$start)*1000);
    curl_close($ch);
    $type = ($code>=200 && $code<400) ? 'ok' : 'err';
    logMsg("  $url => HTTP $code (${time}ms)", $type);
}

// 3. Check DB
logMsg("=== بررسی دیتابیس ===", 'info');
try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/core/Database.php';
    require __DIR__ . '/core/Setting.php';
    $pdo = Database::getConnection();
    logMsg("DB connection: ✅ موفق", 'ok');
    
    $ver = Setting::get('current_version', 'not set');
    logMsg("current_version in DB: $ver", $ver==='6.8.3'?'ok':'warn');
    
    $cache = Setting::get('update_check_cache', '');
    if (!empty($cache)) {
        $data = json_decode($cache, true);
        if (is_array($data)) {
            $lv = $data['latest_version'] ?? 'unknown';
            logMsg("cached latest_version: $lv", str_starts_with($lv, 'commit-')?'err':'ok');
            if (str_starts_with($lv, 'commit-')) {
                logMsg("⚠️ کش قدیمی شامل commit-xxxx است - باید پاک شود", 'err');
            }
        }
        logMsg("cache size: ".strlen($cache)." bytes", 'info');
    } else {
        logMsg("cache: empty (good)", 'ok');
    }
    
    // Check files
    $updaterFile = __DIR__ . '/core/Updater.php';
    if (file_exists($updaterFile)) {
        $content = file_get_contents($updaterFile);
        if (preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
            logMsg("Updater.php CURRENT_VERSION: {$m[1]}", $m[1]==='6.8.3'?'ok':'warn');
        }
        if (strpos($content, 'commit-') !== false && strpos($content, 'sanitizeVersion') === false) {
            logMsg("⚠️ Updater.php هنوز کد قدیمی commit- دارد", 'err');
        } else {
            logMsg("Updater.php: دارای sanitizeVersion ✅", 'ok');
        }
    }
    
    $headerFile = __DIR__ . '/views/layout/header.php';
    if (file_exists($headerFile)) {
        $hc = file_get_contents($headerFile);
        if (strpos($hc, 'commit-acf2d63') !== false || (strpos($hc, 'latest_version') !== false && strpos($hc, 'Connectix v') === false)) {
            logMsg("header.php: هنوز کد قدیمی دارد (commit نمایش می‌دهد)", 'err');
        } else {
            logMsg("header.php: نسخه جدید (Connectix vX) ✅", 'ok');
        }
    }
    
} catch (Throwable $e) {
    logMsg("DB Error: ".$e->getMessage(), 'err');
}

// 4. If do_update requested, perform update WITHOUT GitHub API (direct zip)
if (isset($_GET['do_update'])) {
    echo "<hr><h3>🚀 شروع آپدیت مستقیم از گیت‌هاب (بدون توکن)</h3>";
    logMsg("دانلود پکیج main.zip...", 'info');
    
    $zipUrls = [
        "https://codeload.github.com/hojjatrad/panelconnectix/zip/refs/heads/main",
        "https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip"
    ];
    
    $zipData = false;
    foreach ($zipUrls as $url) {
        logMsg("تلاش: $url", 'info');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Connectix-Updater',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        logMsg("  HTTP $code, size: ".strlen((string)$data), ($code==200 && strlen($data)>10000)?'ok':'err');
        if ($code==200 && strlen($data)>10000) {
            $zipData = $data;
            break;
        }
    }
    
    if (!$zipData) {
        logMsg("❌ دانلود ناموفق - هاست به گیت‌هاب وصل نمی‌شود", 'err');
        echo "<p class=err>راه حل: از فایل force_update_408 که بدون نیاز به اینترنت کار می‌کند استفاده کنید:<br><code>/contax/force_update_408_version_fix_final.php?key=fix_version_408</code></p>";
        exit;
    }
    
    $tmpZip = sys_get_temp_dir().'/cx_'.uniqid().'.zip';
    $tmpExt = sys_get_temp_dir().'/cx_'.uniqid();
    file_put_contents($tmpZip, $zipData);
    logMsg("ذخیره شد: $tmpZip (".round(strlen($zipData)/1024)." KB)", 'ok');
    
    // Extract
    $extracted = false;
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpZip)===true) {
            $zip->extractTo($tmpExt);
            $zip->close();
            $extracted = true;
            logMsg("استخراج با ZipArchive موفق", 'ok');
        } else {
            logMsg("ZipArchive open failed", 'err');
        }
    }
    if (!$extracted) {
        $out = shell_exec('unzip -q -o '.escapeshellarg($tmpZip).' -d '.escapeshellarg($tmpExt).' 2>&1');
        if (!empty(glob($tmpExt.'/*'))) {
            $extracted = true;
            logMsg("استخراج با unzip CLI موفق", 'ok');
        } else {
            logMsg("unzip CLI failed: $out", 'err');
        }
    }
    
    if (!$extracted) {
        logMsg("❌ استخراج ناموفق", 'err');
        exit;
    }
    
    $subDirs = glob($tmpExt.'/*', GLOB_ONLYDIR);
    $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExt;
    logMsg("Source dir: $sourceDir", 'info');
    
    // Copy files - same as quick_update.php
    $repaired = 0;
    $skipped = 0;
    $srcPrefix = rtrim(str_replace('\\','/',$sourceDir),'/').'/';
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($rii as $fileInfo) {
        if ($fileInfo->isDir()) continue;
        $full = str_replace('\\','/', $fileInfo->getPathname());
        if (!str_starts_with($full, $srcPrefix)) continue;
        $rel = substr($full, strlen($srcPrefix));
        if (basename($rel)==='config.php' && dirname($rel)==='.') { $skipped++; continue; }
        $want = @file_get_contents($fileInfo->getPathname());
        if ($want===false || strlen($want)==0) { $skipped++; continue; }
        $live = __DIR__.'/'.$rel;
        if (!is_dir(dirname($live))) @mkdir(dirname($live),0755,true);
        $liveSha = file_exists($live) ? sha1_file($live) : '';
        if ($liveSha===sha1($want)) { $skipped++; continue; }
        if (file_exists($live)) { @chmod($live,0777); @unlink($live); }
        $w = @file_put_contents($live, $want, LOCK_EX);
        @chmod($live,0644);
        if ($w!==false && file_exists($live)) {
            $repaired++;
            if ($repaired<=20) logMsg("✅ $rel", 'ok');
            else if ($repaired==21) logMsg("... و ".($repaired-20)." فایل دیگر", 'info');
        }
    }
    
    logMsg("=== نتیجه: $repaired فایل آپدیت شد، $skipped قبلاً همگام بود ===", 'ok');
    
    // Update DB
    try {
        Setting::set('current_version', '6.8.3');
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');
        $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
        logMsg("DB: version set to 6.8.3, cache cleared", 'ok');
    } catch (Throwable $e) {
        logMsg("DB update failed: ".$e->getMessage(), 'err');
    }
    
    if (function_exists('opcache_reset')) @opcache_reset();
    @unlink($tmpZip);
    // Don't delete tmpExt immediately for forensics
    logMsg("✅ آپدیت کامل شد! حالا باید Connectix v6.8.3 ببینید", 'ok');
    echo "<p><a href='".htmlspecialchars($_SERVER['SCRIPT_NAME'])."?key=diag_409' style='color:#22c55e'>🔄 بررسی مجدد</a> | <a href='updater' style='color:#a78bfa'>رفتن به مرکز بروزرسانی</a></p>";
    
} else {
    echo "<hr><h3>🛠️ اقدامات</h3>";
    echo "<p><a href='?key=diag_409&do_update=1' style='background:#7c3aed;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block'>🚀 اجرای آپدیت مستقیم (بدون نیاز به توکن گیت‌هاب)</a></p>";
    echo "<p class=info>یا از این فایل‌ها استفاده کنید (بدون نیاز به اینترنت):</p>";
    echo "<ul>";
    echo "<li><code>/contax/force_update_408_version_fix_final.php?key=fix_version_408</code> - شامل فایل‌های v6.8.3 به صورت base64 (بدون دانلود)</li>";
    echo "<li><code>/contax/quick_update.php</code> - آپدیتر خودترمیم قوی</li>";
    echo "</ul>";
    echo "<p class=warn>اگر خطای 'خطا در برقراری ارتباط با سرور' می‌بینید، دلیلش timeout شدن ajax-apply است. از لینک‌های بالا استفاده کنید.</p>";
}

echo "</body></html>";

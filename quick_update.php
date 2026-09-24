<?php
/**
 * Connectix Panel - Zero-Dependency One-Click Updater
 * Downloads and applies the latest GitHub release directly without database dependency.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$repo = 'hojjatrad/panelconnectix';
$cacheBuster = time();
$zipUrls = [
    "https://codeload.github.com/{$repo}/zip/refs/heads/main?t={$cacheBuster}",
    "https://github.com/{$repo}/archive/refs/heads/main.zip?t={$cacheBuster}",
    "https://api.github.com/repos/{$repo}/zipball/main"
];

$token = '';
if (file_exists(__DIR__ . '/data/panel.sqlite')) {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/data/panel.sqlite');
        $s = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token'")->fetchColumn();
        if (!empty($s)) $token = trim($s);
    } catch (Throwable $e) {}
}

$zipData = false;
$usedUrl = '';

foreach ($zipUrls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $headers = ['User-Agent: Connectix-Updater'];
    if (!empty($token) && str_contains($url, 'api.github.com')) {
        $headers[] = 'Authorization: token ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && strlen((string)$data) > 5000) {
        $zipData = $data;
        $usedUrl = $url;
        break;
    }
}

if (!$zipData) {
    die("<div style='font-family:sans-serif;direction:rtl;padding:30px;color:#ef4444;'><h2>خطا در دریافت پکیج از گیت‌هاب</h2><p>سرور هاست نتوانست به مخزن گیت‌هاب متصل شود. لطفاً فایل ZIP را به صورت دستی آپلود و Extract فرمایید.</p></div>");
}

$tmpZip = sys_get_temp_dir() . '/cx_update_' . time() . '.zip';
$tmpExt = sys_get_temp_dir() . '/cx_ext_' . time();
file_put_contents($tmpZip, $zipData);

$extracted = false;
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === true) {
        $zip->extractTo($tmpExt);
        $zip->close();
        $extracted = true;
    }
}
if (!$extracted && function_exists('shell_exec')) {
    @shell_exec('unzip -q -o ' . escapeshellarg($tmpZip) . ' -d ' . escapeshellarg($tmpExt) . ' 2>&1');
    if (!empty(glob($tmpExt . '/*'))) $extracted = true;
}

if (!$extracted) {
    @unlink($tmpZip);
    die("<div style='font-family:sans-serif;direction:rtl;padding:30px;color:#ef4444;'><h2>خطا در بازگشایی ZIP</h2><p>اکستنشن ZipArchive روی هاست فعال نیست.</p></div>");
}

$subDirs = glob($tmpExt . '/*', GLOB_ONLYDIR);
$sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExt;

$folders = ['controllers', 'core', 'drivers', 'views', 'cron'];
$copiedFiles = 0;
$copyLog = [];

foreach ($folders as $f) {
    $srcF = $sourceDir . '/' . $f;
    $dstF = __DIR__ . '/' . $f;
    if (is_dir($srcF)) {
        if (!is_dir($dstF)) @mkdir($dstF, 0755, true);
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcF, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $target = $dstF . DIRECTORY_SEPARATOR . $iter->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0755, true);
            } else {
                if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
                $fc = @file_get_contents($item->getPathname());
                if ($fc !== false && strlen($fc) > 0) {
                    if (file_exists($target)) {
                        @chmod($target, 0666);
                        @unlink($target);
                    }
                    $written = @file_put_contents($target, $fc);
                    if ($written !== false && $written > 0) {
                        $copyLog[] = $iter->getSubPathname() . ': OK';
                        $copiedFiles++;
                    } else {
                        $copyLog[] = $iter->getSubPathname() . ': FAIL';
                    }
                }
                @chmod($target, 0644);
            }
        }
    }
}

foreach (['index.php', 'repair.php', 'install.php', 'schema.sql', 'purge_all.php', 'quick_update.php', '.htaccess'] as $rootFile) {
    if (file_exists($sourceDir . '/' . $rootFile)) {
        $data = file_get_contents($sourceDir . '/' . $rootFile);
        if ($data !== false && strlen($data) > 0) {
            $tgt = __DIR__ . '/' . $rootFile;
            if (file_exists($tgt)) {
                @chmod($tgt, 0666);
                @unlink($tgt);
            }
            $written = @file_put_contents($tgt, $data);
            if ($written !== false && $written > 0) {
                $copyLog[] = $rootFile . ': OK';
                $copiedFiles++;
            } else {
                $copyLog[] = $rootFile . ': FAIL';
            }
            @chmod($tgt, 0644);
        }
    }
}

// Invalidate OPcache and stat cache so changes take effect immediately
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
if (function_exists('clearstatcache')) {
    @clearstatcache(true);
}

@unlink($tmpZip);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ارتقا و به‌روزرسانی پنل | Connectix</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 p-8">
    <div class="max-w-xl mx-auto bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
        <h1 class="text-lg font-bold text-emerald-400">فایل‌های ارتقا یافته: <?= $copiedFiles ?></h1>
        <div class="text-xs text-slate-300">ApiController MD5: <?= md5_file(__DIR__ . '/controllers/ApiController.php') ?></div>
        <pre class="bg-black/60 p-4 rounded-xl text-[10px] text-slate-400 max-h-60 overflow-y-auto"><?= htmlspecialchars(implode("\n", $copyLog)) ?></pre>
        <a href="repair.php" class="inline-block px-4 py-2 bg-purple-600 text-white rounded-xl text-xs font-bold">بررسی سلامت</a>
    </div>
</body>
</html>

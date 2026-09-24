<?php
/**
 * Connectix Panel - Zero-Dependency One-Click Updater
 * Downloads and applies the latest GitHub release directly without database dependency.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$repo = 'hojjatrad/panelconnectix';
$zipUrls = [
    "https://codeload.github.com/{$repo}/zip/refs/heads/main",
    "https://github.com/{$repo}/archive/refs/heads/main.zip",
    "https://api.github.com/repos/{$repo}/zipball/main"
];

$zipData = false;
$usedUrl = '';

foreach ($zipUrls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Updater']);
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
                @copy($item->getPathname(), $target);
                @chmod($target, 0644);
                $copiedFiles++;
            }
        }
    }
}

foreach (['index.php', 'repair.php', 'install.php', 'schema.sql'] as $rootFile) {
    if (file_exists($sourceDir . '/' . $rootFile)) {
        @copy($sourceDir . '/' . $rootFile, __DIR__ . '/' . $rootFile);
        @chmod(__DIR__ . '/' . $rootFile, 0644);
        $copiedFiles++;
    }
}

@unlink($tmpZip);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ارتقا و به‌روزرسانی پنل | Connectix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 text-center space-y-4 shadow-2xl">
        <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 mx-auto flex items-center justify-center text-3xl shadow-xl">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h1 class="text-lg font-bold text-white">سامانه با موفقیت به آخرین نسخه ارتقا یافت!</h1>
        <p class="text-xs text-slate-400 leading-relaxed">
            تعداد <strong class="text-emerald-400"><?= $copiedFiles ?></strong> فایل سیستمی مستقیماً از گیت‌هاب جایگزین و بروزرسانی شد.
            باگ تاریخ انقضای پاسارگاد و مکانیزم تحویل مستقیم ساب‌لینک فعال گردید.
        </p>
        <div class="pt-3 space-y-2">
            <a href="login" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition block shadow-lg">
                ورود به پنل مدیریت
            </a>
            <a href="repair.php" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition block border border-slate-700">
                بررسی وضعیت سلامت سیستم (repair.php)
            </a>
        </div>
    </div>
</body>
</html>

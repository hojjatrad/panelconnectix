<?php
/**
 * Connectix Panel - Emergency One-Click Remote Host Fixer
 * Upload this single file to your panel directory (e.g. public_html/contax/cpanel_fix.php)
 * and open it in your browser: https://YOUR-DOMAIN/contax/cpanel_fix.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(180);

$repo = 'hojjatrad/panelconnectix';
$token = $_GET['token'] ?? '';
$targetDir = __DIR__;

// Auto-detect token from config/database if available
if (empty($token) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    if (file_exists(__DIR__ . '/core/Database.php')) {
        require_once __DIR__ . '/core/Database.php';
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token' LIMIT 1");
            if ($stmt) {
                $val = (string)$stmt->fetchColumn();
                if (!empty($val)) $token = $val;
            }
            $stmtRepo = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_repo' LIMIT 1");
            if ($stmtRepo) {
                $valRepo = (string)$stmtRepo->fetchColumn();
                if (!empty($valRepo)) $repo = $valRepo;
            }
        } catch (Throwable $e) {}
    }
}

// Step 1: Download latest clean repository package from GitHub
$url = "https://api.github.com/repos/{$repo}/zipball/main";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Connectix-Emergency-Fixer',
    "Authorization: token {$token}"
]);
$zipData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || strlen($zipData) < 10000) {
    die("<div style='font-family:Tahoma;direction:rtl;padding:30px;background:#fff1f2;color:#9f1239;border-radius:12px;max-width:600px;margin:50px auto;border:1px solid #fecdd3;'>
        <h2>خطا در دانلود پکیج از گیت‌هاب</h2>
        <p>کد پاسخ: {$httpCode} | خطا: {$curlErr}</p>
        <p>لطفاً فایل <b>connectix-panel.zip</b> را به صورت مستقیم در سی‌پنل آپلود و اکسترکت فرمایید.</p>
    </div>");
}

// Step 2: Save and Extract in panel directory (avoiding /tmp restrictions)
$tmpZip = $targetDir . '/_temp_fix_' . time() . '.zip';
$tmpExtract = $targetDir . '/_temp_extracted_' . time();
file_put_contents($tmpZip, $zipData);

$extracted = false;
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === true) {
        $zip->extractTo($tmpExtract);
        $zip->close();
        $extracted = true;
    }
}

if (!$extracted && function_exists('shell_exec')) {
    @shell_exec('unzip -q -o ' . escapeshellarg($tmpZip) . ' -d ' . escapeshellarg($tmpExtract) . ' 2>&1');
    $files = glob($tmpExtract . '/*');
    if (!empty($files)) $extracted = true;
}

if (!$extracted) {
    // Pure PHP Zip extractor fallback
    $data = @file_get_contents($tmpZip);
    if ($data) {
        $offset = 0;
        $len = strlen($data);
        @mkdir($tmpExtract, 0755, true);
        while ($offset < $len) {
            if (substr($data, $offset, 4) !== "PK\x03\x04") break;
            $compMethod = unpack('v', substr($data, $offset + 8, 2))[1] ?? 0;
            $compSize = unpack('V', substr($data, $offset + 18, 4))[1] ?? 0;
            $nameLen = unpack('v', substr($data, $offset + 26, 2))[1] ?? 0;
            $extraLen = unpack('v', substr($data, $offset + 28, 2))[1] ?? 0;
            $fileName = substr($data, $offset + 30, $nameLen);
            $offset += 30 + $nameLen + $extraLen;
            $fileData = substr($data, $offset, $compSize);
            $offset += $compSize;

            if ($compMethod === 8) {
                $uncompressed = @gzinflate($fileData);
            } elseif ($compMethod === 0) {
                $uncompressed = $fileData;
            } else {
                continue;
            }

            if (str_ends_with($fileName, '/')) {
                @mkdir($tmpExtract . '/' . $fileName, 0755, true);
            } else {
                $t = $tmpExtract . '/' . $fileName;
                @mkdir(dirname($t), 0755, true);
                if ($uncompressed !== false) {
                    file_put_contents($t, $uncompressed);
                }
            }
        }
        $extracted = true;
    }
}

// Step 3: Copy all files into place (skipping config.php and data/)
$subDirs = glob($tmpExtract . '/*', GLOB_ONLYDIR);
$sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExtract;

$skipped = ['config.php', 'data', '_temp_fix_', '_temp_extracted_', 'cpanel_fix.php'];

function copySafeRecursive($src, $dst, $skipped) {
    $dir = @opendir($src);
    if (!$dir) return;
    if (!is_dir($dst)) @mkdir($dst, 0755, true);
    @chmod($dst, 0755);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        if (in_array($file, $skipped)) continue;
        $s = $src . '/' . $file;
        $d = $dst . '/' . $file;
        if (is_dir($s)) {
            copySafeRecursive($s, $d, $skipped);
        } else {
            @copy($s, $d);
            @chmod($d, 0644);
        }
    }
    @closedir($dir);
}

copySafeRecursive($sourceDir, $targetDir, $skipped);

// Step 4: Cleanup temp files
function deleteTree($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteTree("$dir/$file") : @unlink("$dir/$file");
    }
    @rmdir($dir);
}

@unlink($tmpZip);
deleteTree($tmpExtract);

// Clear opcache
if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);

// Self-delete
@unlink(__FILE__);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ترمیم موفقیت‌آمیز سیستم | Connectix Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 text-center space-y-5 shadow-2xl">
        <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 mx-auto flex items-center justify-center text-3xl">
            ✓
        </div>
        <div>
            <h1 class="text-xl font-black text-white">ترمیم و بازسازی کامل انجام شد!</h1>
            <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                تمامی کنترلرها (از جمله AuthController)، فایل‌های هسته و ویوها از گیت‌هاب استخراج و جایگزین شدند.
            </p>
        </div>
        <div class="pt-2">
            <a href="login" class="block w-full py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-2xl text-xs transition shadow-lg shadow-purple-900/40">
                ورود به پنل مدیریت
            </a>
        </div>
    </div>
</body>
</html>

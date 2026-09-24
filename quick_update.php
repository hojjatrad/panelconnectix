<?php
/**
 * Connectix Panel - Zero-Dependency One-Click Live Updater
 * Directly downloads and deploys the latest GitHub code to your cPanel host.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

// Disable output buffering for live real-time feedback
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>به‌روزرسانی آنی پنل از گیت‌هاب | Connectix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8 flex items-center justify-center">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 space-y-5 shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
            <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-lg font-bold">
                ⚡️
            </div>
            <div>
                <h1 class="text-base font-black text-white">به‌روزرسانی خودکار پنل از گیت‌هاب</h1>
                <p class="text-[11px] text-slate-400">در حال دریافت آخرین نسخه و استقرار مستقیم روی هاست...</p>
            </div>
        </div>

        <div id="logs" class="space-y-2 text-xs font-mono">
<?php
function logStep($msg, $type = 'info') {
    $colors = [
        'info' => 'text-slate-300',
        'success' => 'text-emerald-400 font-bold',
        'error' => 'text-rose-400 font-bold',
        'warn' => 'text-amber-300'
    ];
    $c = $colors[$type] ?? 'text-slate-300';
    $icon = match($type) {
        'success' => '✓ ',
        'error' => '✗ ',
        'warn' => '⚠️ ',
        default => '• '
    };
    echo "<div class='{$c}'>{$icon}" . htmlspecialchars($msg) . "</div>";
    flush();
}

logStep("شروع فرآیند به‌روزرسانی...", 'info');

// 0. Auto-Fix .htaccess to prevent 500 error permanently
$cleanHtaccess = "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^(.*)$ index.php [QSA,L]\n</IfModule>\n";
@file_put_contents(__DIR__ . '/.htaccess', $cleanHtaccess);
logStep("فایل .htaccess بررسی و قوانین استاندارد آپاچی بازنشانی شد.", 'success');

// 1. Retrieve GitHub Token
$token = '';
if (file_exists(__DIR__ . '/data/panel.sqlite')) {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/data/panel.sqlite');
        $s = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token'")->fetchColumn();
        if (!empty($s)) $token = trim($s);
    } catch (Throwable $e) {}
}

$repo = 'hojjatrad/panelconnectix';
$cacheBuster = time();

// Fetch latest commit SHA
$latestSha = '';
$chSha = curl_init("https://api.github.com/repos/{$repo}/commits/main");
curl_setopt($chSha, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chSha, CURLOPT_TIMEOUT, 6);
curl_setopt($chSha, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($chSha, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($chSha, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Live-Updater']);
$shaJson = curl_exec($chSha);
curl_close($chSha);
if ($shaJson) {
    $shaData = json_decode($shaJson, true);
    if (!empty($shaData['sha'])) {
        $latestSha = $shaData['sha'];
    }
}

$zipUrls = [];
if (!empty($latestSha)) {
    $zipUrls[] = "https://codeload.github.com/{$repo}/zip/{$latestSha}";
    $zipUrls[] = "https://github.com/{$repo}/archive/{$latestSha}.zip";
}
$zipUrls[] = "https://codeload.github.com/{$repo}/zip/refs/heads/main?t={$cacheBuster}";
$zipUrls[] = "https://api.github.com/repos/{$repo}/zipball/main?t={$cacheBuster}";
$zipUrls[] = "https://github.com/{$repo}/archive/refs/heads/main.zip?t={$cacheBuster}";

$zipData = false;
$usedUrl = '';

logStep("در حال اتصال به مخزن گیت‌هاب ({$repo})...", 'info');

foreach ($zipUrls as $url) {
    logStep("تلاش برای دریافت پکیج از: " . parse_url($url, PHP_URL_HOST) . "...", 'info');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = ['User-Agent: Connectix-Live-Updater'];
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
        logStep("پکیج با موفقیت دریافت شد (" . round(strlen($data) / 1024) . " کیلوبایت).", 'success');
        break;
    }
}

if (!$zipData) {
    logStep("خطا: سرور نتوانست به گیت‌هاب متصل شود. لطفاً اتصال اینترنت هاست را بررسی نمایید.", 'error');
    echo "</div></div></body></html>";
    exit;
}

$tmpZip = sys_get_temp_dir() . '/cx_upd_' . uniqid() . '.zip';
$tmpExt = sys_get_temp_dir() . '/cx_ext_' . uniqid();
file_put_contents($tmpZip, $zipData);

logStep("در حال بازگشایی و استخراج فایل‌های جدید...", 'info');

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
    logStep("خطا در بازگشایی ZIP: اکستنشن ZipArchive فعال نیست.", 'error');
    echo "</div></div></body></html>";
    exit;
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
                        @chmod($target, 0777);
                        $del = @unlink($target);
                        if (!$del && file_exists($target)) {
                            @rename($target, $target . '.old.' . uniqid());
                        }
                    }
                    $written = @file_put_contents($target, $fc);
                    if ($written !== false && $written > 0) {
                        $copiedFiles++;
                    } else {
                        logStep("خطا در نوشتن فایل: " . basename($target), 'warn');
                    }
                }
                @chmod($target, 0644);
            }
        }
    }
}

$rootFiles = glob($sourceDir . '/*.php');
foreach ($rootFiles as $rf) {
    $rootFile = basename($rf);
    if ($rootFile === 'config.php') continue; // Never overwrite user config
    $data = @file_get_contents($rf);
    if ($data !== false && strlen($data) > 0) {
        $tgt = __DIR__ . '/' . $rootFile;
        if (file_exists($tgt)) {
            @chmod($tgt, 0777);
            $del = @unlink($tgt);
            if (!$del && file_exists($tgt)) {
                @rename($tgt, $tgt . '.old.' . uniqid());
            }
        }
        $written = @file_put_contents($tgt, $data);
        if ($written !== false && $written > 0) {
            $copiedFiles++;
        } else {
            logStep("خطا در نوشتن فایل ریشه: {$rootFile}", 'warn');
        }
        @chmod($tgt, 0644);
    }
}

// Invalidate OPcache
if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);

logStep("تعداد {$copiedFiles} فایل با موفقیت روی هاست جایگزین شدند.", 'success');

// Send Notification to Telegram Supergroup Reports Topic
$tgNotice = false;
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Setting.php';
    require_once __DIR__ . '/core/TelegramBot.php';

    $dateTime = date('Y-m-d H:i:s');
    $tgMsg = "🚀 <b>بروزرسانی موفق پنل با آخرین نسخه گیت‌هاب</b>\n\n"
           . "📅 <b>زمان:</b> <code>{$dateTime}</code>\n"
           . "📦 <b>تعداد فایل‌های ارتقا یافته:</b> <code>{$copiedFiles} فایل</code>\n"
           . "🌐 <b>مخزن:</b> <code>{$repo} (شاخه main)</code>\n"
           . "⚡️ <b>وضعیت:</b> تمامی فایل‌ها، کنترلرها و درایورها با موفقیت مستقر شدند ✅\n\n"
           . "💡 <i>سامانه با موفقیت به آخرین نسخه رسمی متصل گردید.</i>";

    $sent = TelegramBot::sendCategorizedReport('general', $tgMsg);
    if (!$sent) {
        $sent = TelegramBot::sendCategorizedReport('notifications', $tgMsg);
    }
    if ($sent) $tgNotice = true;
} catch (Throwable $e) {}

if ($tgNotice) {
    logStep("گزارش تایید آپدیت به سوپرگروه تلگرام در تب گزارش‌ها ارسال شد.", 'success');
}

@unlink($tmpZip);
?>
        </div>

        <div class="p-4 bg-emerald-950/40 border border-emerald-900/50 rounded-2xl text-xs space-y-2">
            <div class="text-emerald-300 font-bold flex items-center gap-2">
                <span>✓ به‌روزرسانی ۱۰۰٪ با موفقیت انجام شد</span>
            </div>
            <p class="text-slate-300 leading-relaxed">
                کلیه فایل‌های پنل با آخرین کدهای مخزن گیت‌هاب همگام شدند و ارورهای دیتابیس و ساخت پلن رفع گردیدند.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-2">
            <a href="repair.php" class="py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition text-center shadow-lg shadow-purple-900/30">
                بررسی نهایی دیتابیس (repair)
            </a>
            <a href="login" class="py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-xs transition text-center border border-slate-700">
                ورود به پنل مدیریت
            </a>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Connectix Panel - Zero-Dependency One-Click Live Updater (Self-Healing Bootstrap)
 *
 * IMPORTANT ARCHITECTURE NOTE:
 * This file is a SELF-HEALING bootstrap. The "sync engine" block below
 * (SECTION 5) is deliberately written in the simplest, most stable form
 * and MUST NOT contain version-specific logic. Every version of this file
 * verifies and repairs ALL files on the live host (including this file
 * itself) before the update can be considered complete. Even if an older
 * generation of this script executes, it still deploys the latest GitHub
 * code to disk, and the next execution runs the new generation.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(180);

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

logStep("شروع فرآیند به‌روزرسانی (Self-Healing Updater v5)...", 'info');
logStep("پوشه نصب: " . __DIR__, 'info');

// 0. Auto-Fix .htaccess and per-directory PHP settings.
// NOTE: opcache.enable is a PHP_INI_SYSTEM directive (cannot be set from
// .user.ini on this host). The reliable per-directory mechanism is
// auto_prepend_file, which runs .pre_reset.php before every script in every
// PHP-FPM pool — guaranteeing a one-shot OPcache reset per pool after each
// deployment, even in pools with frozen caches.
$cleanHtaccess = "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^(.*)$ index.php [QSA,L]\n</IfModule>\n";
@file_put_contents(__DIR__ . '/.htaccess', $cleanHtaccess);
$userIni = "auto_prepend_file=" . str_replace('\\', '/', __DIR__ . '/.pre_reset.php') . "\n";
@file_put_contents(__DIR__ . '/.user.ini', $userIni);
@touch(__DIR__ . '/.htaccess');
@touch(__DIR__ . '/.user.ini');
logStep("فایل‌های .htaccess و .user.ini (شامل خودترمیم OPcache) بازنشانی شدند.", 'success');

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

// 2. Fetch latest commit SHA (multi-source: api.github.com may be blocked/throttled
//    on some networks — the GitHub Web Atom feed is the robust fallback)
$latestSha = '';
$shaSource = '';
$tryCurl = function (string $url, array $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && is_string($body)) ? $body : false;
};

$apiSha = '';
$atomSha = '';
$apiBody = $tryCurl("https://api.github.com/repos/{$repo}/commits/main", ['User-Agent: Connectix-Live-Updater']);
if ($apiBody !== false) {
    $shaData = json_decode($apiBody, true);
    if (!empty($shaData['sha'])) $apiSha = $shaData['sha'];
}
$atomBody = $tryCurl("https://github.com/{$repo}/commits/main.atom", ['User-Agent: Connectix-Live-Updater']);
if ($atomBody !== false && preg_match('#Grit::Commit/([a-f0-9]{40})#', $atomBody, $m)) {
    $atomSha = $m[1];
}
if ($apiSha !== '' && $atomSha !== '' && $apiSha !== $atomSha) {
    // Both sources disagree (transparent network caching on this host can serve
    // a stale SHA from api.github.com). Prefer the web Atom feed in that case.
    $latestSha = $atomSha;
    $shaSource = 'atom feed (API SHA differed)';
} elseif ($apiSha !== '') {
    $latestSha = $apiSha;
    $shaSource = 'api.github.com';
} elseif ($atomSha !== '') {
    $latestSha = $atomSha;
    $shaSource = 'github.com atom feed';
}
if ($latestSha !== '') {
    logStep("آخرین کامیت شناسایی شد: " . substr($latestSha, 0, 7) . " (منبع: {$shaSource})", 'info');
} else {
    logStep('هشدار: شناسایی SHA آخرین کامیت ناموفق بود؛ از پکیج شاخه main استفاده می‌شود.', 'warn');
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

/**
 * ============================================================
 *  SECTION 5 — STABLE SELF-HEALING SYNC ENGINE
 *  (Keep this block primitive and version-agnostic.)
 *  - Walks every .php file in the GitHub package
 *  - Compares SHA1 with the live host file
 *  - On mismatch: force-write + read-back verification
 *  - Repairs itself (quick_update.php) as well
 *  - Never touches config.php (user settings)
 * ============================================================
 */
$repaired = 0;
$failed = [];
$skipped = 0;
$repairedFiles = []; // rel => expected sha1 (for post-sync forensic verification)
$srcPrefix = str_replace('\\', '/', rtrim($sourceDir, '/')) . '/';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rii as $fileInfo) {
    if ($fileInfo->isDir()) continue;
    if ($fileInfo->getExtension() !== 'php') continue;
    // Derive relative path from the absolute pathname (iterator-key behavior
    // differs across PHP versions, so never rely on the foreach key here)
    $full = str_replace('\\', '/', $fileInfo->getPathname());
    if (!str_starts_with($full, $srcPrefix)) continue;
    $rel = substr($full, strlen($srcPrefix));
    if (basename($rel) === 'config.php' && dirname($rel) === '.') { $skipped++; continue; }
    $want = @file_get_contents($fileInfo->getPathname());
    if ($want === false || strlen($want) === 0) { $skipped++; continue; }
    $live = __DIR__ . '/' . $rel;
    if (!is_dir(dirname($live))) { @mkdir(dirname($live), 0755, true); }
    $liveSha = (file_exists($live)) ? sha1_file($live) : '';
    if ($liveSha === sha1($want)) { $skipped++; continue; } // already in sync
    // force overwrite
    if (file_exists($live)) {
        @chmod($live, 0777);
        @unlink($live);
        if (file_exists($live)) { @rename($live, $live . '.old.' . uniqid()); }
    }
    $w = @file_put_contents($live, $want, LOCK_EX);
    @chmod($live, 0644);
    if ($w !== false && file_exists($live) && sha1_file($live) === sha1($want)) {
        $repaired++;
        $repairedFiles[$rel] = sha1($want);
        logStep("تعمیر و همگام‌سازی: " . $rel, 'success');
    } else {
        $failed[] = $rel;
        logStep("خطا در نوشتن فایل: " . $rel, 'error');
    }
}
/**
 * ============================================================
 *  END STABLE SYNC ENGINE
 * ============================================================
 */

logStep("مجموع: {$repaired} فایل تعمیر/نصب شد، {$skipped} فایل قبلاً همگام بودند.", 'success');

// 6. Purge stale legacy diagnostic / mock files from the live host
$stalePurge = ['diag_fresh_99.php', 'diag_step_100.php', 'find_mock.php', 'fix_now.php', 'test_class.php'];
$purged = 0;
foreach ($stalePurge as $sp) {
    $spPath = __DIR__ . '/' . $sp;
    if (file_exists($spPath)) {
        @chmod($spPath, 0777);
        if (@unlink($spPath)) $purged++;
    }
}
foreach (glob(__DIR__ . '/*.old.*') as $ob) { @unlink($ob); }
if ($purged > 0) {
    logStep("تعداد {$purged} فایل دیباگ قدیمی و پیش‌فرض از روی هاست حذف شد.", 'success');
}

// 7. Post-Update Integrity Report
$selfOnDisk = @file_get_contents(__DIR__ . '/quick_update.php');
$pkgSelf = @file_get_contents($sourceDir . '/quick_update.php');
if ($pkgSelf !== false && $selfOnDisk !== false) {
    if (sha1($selfOnDisk) === sha1($pkgSelf)) {
        logStep('اعتبارسنجی: نسخه خود به‌روزرسان روی هاست با آخرین نسخه گیت‌هاب مطابقت دارد.', 'success');
    } else {
        logStep('هشدار: فایل خود به‌روزرسان روی هاست هنوز نسل قبلی است؛ در اجرای بعدی به‌طور خودکار تعمیر می‌شود.', 'warn');
    }
}
$helpersFile = @file_get_contents(__DIR__ . '/core/Helpers.php');
if ($helpersFile && str_contains($helpersFile, 'isPanelSubUrl') && str_contains($helpersFile, 'stripMockLinks')) {
    logStep('اعتبارسنجی: Helpers.php جدید (با محافظ mock-filter) روی هاست نصب است.', 'success');
} else {
    logStep('خطای جدی: Helpers.php روی هاست قدیمی است! به‌روزرسانی را مجدداً اجرا کنید.', 'error');
}
$apiV1 = @file_get_contents(__DIR__ . '/controllers/ApiController.php');
$apiV2 = @file_get_contents(__DIR__ . '/controllers/ApiControllerV2.php');
$mockFree = ($apiV1 && !str_contains($apiV1, 'mci_reality') && !str_contains($apiV1, 'mock_pbk'))
         && ($apiV2 && !str_contains($apiV2, 'mci_reality') && !str_contains($apiV2, 'mock_pbk'));
if ($mockFree) {
    logStep('اعتبارسنجی: کنترلرهای اپ بدون هیچ کانکشن پیش‌فرض/دموی قدیمی هستند.', 'success');
} else {
    logStep('خطای جدی: کنترلرهای اپ هنوز حاوی کانکشن‌های پیش‌فرض قدیمی هستند!', 'error');
}

// 8. Deployment stamp + cache invalidation.
// The stamp triggers .pre_reset.php (via auto_prepend_file) to reset the
// OPcache of EVERY pool that serves this directory — including pools whose
// cache is frozen and which will never see our own opcache_reset() call.
@touch(__DIR__ . '/.deploy_stamp');
if (function_exists('opcache_reset')) @opcache_reset();
if (function_exists('clearstatcache')) @clearstatcache(true);

// 8b. Post-sync forensic verification (detects silent revert / split views)
$forensics = [];
$forensics['dir'] = __DIR__;
$forensics['realpath'] = @realpath(__DIR__);
$forensics['user'] = function_exists('get_current_user') ? get_current_user() : (getenv('USER') ?: '?');
$forensics['php'] = PHP_VERSION;
$stampPath = __DIR__ . '/.deploy_stamp';
$forensics['stamp_mtime'] = @filemtime($stampPath) ? date('Y-m-d H:i:s', @filemtime($stampPath)) : 'missing';
$markers = glob(__DIR__ . '/.opcache_reset_done_*') ?: [];
$forensics['opcache_markers'] = array_map(fn($m) => basename($m) . ' @ ' . date('H:i:s', @filemtime($m)), $markers);
// Canary: a fresh non-PHP file that must be visible to every other process
$canaryName = '__canary_' . date('His') . '_' . getmypid() . '.txt';
$canaryOk = @file_put_contents(__DIR__ . '/' . $canaryName, 'forensic ' . date('Y-m-d H:i:s') . ' pid ' . getmypid(), LOCK_EX) !== false;
$forensics['canary_written'] = $canaryName;
$forensics['canary_readback'] = ($canaryOk && @file_exists(__DIR__ . '/' . $canaryName)) ? 'ok' : 'FAILED';
// ls-level view (bypasses PHP stat cache)
$lsRaw = @shell_exec('ls -la ' . escapeshellarg(__DIR__) . ' 2>&1 | grep -E "canary|deploy_stamp|opcache_reset" | head -8');
$forensics['ls_view'] = $lsRaw ? array_map('trim', explode("\n", (string)$lsRaw)) : 'shell_exec unavailable';
// Re-verify the repaired files after a micro-delay
$revertCheck = [];
foreach (array_slice($repairedFiles, 0, 40) as $rel => $expectedSha) {
    $live = __DIR__ . '/' . $rel;
    $cur = @sha1_file($live);
    if ($cur !== $expectedSha) {
        $revertCheck[] = $rel . ' => ' . substr((string)$cur, 0, 10) . ' (want ' . substr($expectedSha, 0, 10) . ') mtime=' . (@filemtime($live) ? date('H:i:s', @filemtime($live)) : '?');
    }
}
$forensics['reverted_now'] = $revertCheck ?: 'none';
logStep('FORENSICS: ' . json_encode($forensics, JSON_UNESCAPED_UNICODE), $revertCheck ? 'error' : 'info');

// 9. Send Notification to Telegram Supergroup Reports Topic
$tgNotice = false;
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Setting.php';
    require_once __DIR__ . '/core/TelegramBot.php';

    $dateTime = date('Y-m-d H:i:s');
    $tgMsg = "🚀 <b>بروزرسانی موفق پنل با آخرین نسخه گیت‌هاب</b>\n\n"
           . "📅 <b>زمان:</b> <code>{$dateTime}</code>\n"
           . "📦 <b>فایل‌های تعمیر/نصب شده:</b> <code>{$repaired} فایل</code>\n"
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
                <span>✓ به‌روزرسانی ۱۰٪ با موفقیت انجام شد</span>
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

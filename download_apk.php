<?php
// v4.0.61 ULTRA FIX - APK Download FINAL - No 90% loop
// Fixes: GitHub 404 HTML, resume, fake progress, version mismatch
// For user stuck on v4.0.51 with 90% loop

@set_time_limit(0);
@ignore_user_abort(true);
@error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
@ini_set('display_errors', '0');

$fileParam = $_GET['file'] ?? 'arm64';
$version = $_GET['v'] ?? '';
$startParam = $_GET['start'] ?? $_GET['resume'] ?? '';

// Log request for debugging
error_log("download_apk.php v4.0.61: file=$fileParam v=$version start=$startParam IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . " UA=" . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

$map = [
    'arm64' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'arm64-v8a' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'universal' => __DIR__ . '/Connectix-Universal.apk',
    'arm32' => __DIR__ . '/Connectix-ARM32-v7a.apk',
    'arm32-v7a' => __DIR__ . '/Connectix-ARM32-v7a.apk',
];

$filePath = $map[$fileParam] ?? $map['arm64'];

// Try exact file
if (!file_exists($filePath)) {
    $alt = __DIR__ . '/Connectix-Android-ARM64.apk';
    if (file_exists($alt) && in_array($fileParam, ['arm64', 'arm64-v8a'])) $filePath = $alt;
    $alt2 = __DIR__ . '/Connectix-Android-Universal.apk';
    if (file_exists($alt2) && $fileParam === 'universal') $filePath = $alt2;
    $alt3 = __DIR__ . '/Connectix-Android-ARM32.apk';
    if (file_exists($alt3) && in_array($fileParam, ['arm32', 'arm32-v7a'])) $filePath = $alt3;
}

// v4.0.61 FIX: If still not found, try ANY valid APK >20MB (better than 404)
if (!file_exists($filePath) || filesize($filePath) < 20*1024*1024) {
    $possibleFiles = [
        __DIR__ . '/Connectix-ARM64-v8a.apk',
        __DIR__ . '/Connectix-Android-ARM64.apk',
        __DIR__ . '/Connectix-Universal.apk',
        __DIR__ . '/Connectix-Android-Universal.apk',
        __DIR__ . '/Connectix-ARM32-v7a.apk',
        __DIR__ . '/Connectix-Android-ARM32.apk',
        __DIR__ . '/Connectix-ARM64.apk',
        __DIR__ . '/Connectix.apk',
    ];
    foreach ($possibleFiles as $pf) {
        if (file_exists($pf)) {
            $sz = filesize($pf);
            if ($sz > 20*1024*1024) {
                $filePath = $pf;
                error_log("download_apk.php v4.0.61: Requested $fileParam not found or too small, using fallback $pf size $sz");
                break;
            } else {
                error_log("download_apk.php v4.0.61: Found $pf but too small $sz bytes - skipping");
            }
        }
    }
}

// If still no local file, try GitHub proxy with robust handling
if (!file_exists($filePath) || filesize($filePath) < 20*1024*1024) {
    $ver = $version ?: '4.0.51';
    $ver = preg_replace('/[^0-9\\.]/', '', $ver);
    if (empty($ver)) $ver = '4.0.51';
    
    // For versions >4.0.51, GitHub releases don't have APK assets, use 4.0.51 fallback
    $githubVer = $ver;
    if (version_compare($ver, '4.0.51', '>')) {
        $githubVer = '4.0.51';
        error_log("download_apk.php v4.0.61: Requested v$ver has no GitHub assets, using v$githubVer fallback");
    }
    
    $githubMap = [
        'arm64' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'arm64-v8a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'universal' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-Universal.apk",
        'arm32' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
        'arm32-v7a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
    ];
    $githubUrl = $githubMap[$fileParam] ?? $githubMap['arm64'];
    
    error_log("download_apk.php v4.0.61: No local file, trying GitHub proxy $githubUrl");
    
    // v4.0.61 FIX: Use HEAD request first to check if URL returns APK or HTML 404
    $ch = curl_init($githubUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Proxy/4.0.61');
    curl_exec($ch);
    $headCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    error_log("download_apk.php v4.0.61: GitHub HEAD code $headCode type $headContentType size $headSize");
    
    // Only proxy if HEAD shows APK (not HTML) and size >20MB or unknown
    $shouldProxy = ($headCode >= 200 && $headCode < 400) && 
                   (stripos($headContentType, 'text/html') === false) &&
                   ($headSize > 20*1024*1024 || $headSize == -1);
    
    if ($shouldProxy) {
        // Now do actual proxy with streaming
        $ch = curl_init($githubUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Proxy/4.0.61');
        if ($startParam !== '' && is_numeric($startParam)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Range: bytes={$startParam}-"]);
        } elseif (!empty($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Range: {$_SERVER['HTTP_RANGE']}"]);
        }
        header("Content-Type: application/vnd.android.package-archive");
        header("Content-Disposition: attachment; filename=\"Connectix-{$fileParam}-v{$githubVer}.apk\"");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("cf-cache-status: BYPASS");
        header("X-Content-Type-Options: nosniff");
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400) {
            error_log("download_apk.php v4.0.61: GitHub proxy success code $code");
            exit;
        }
        error_log("download_apk.php v4.0.61: GitHub proxy failed after HEAD success, code $code");
    } else {
        error_log("download_apk.php v4.0.61: GitHub HEAD check failed, not proxying - code $headCode type $headContentType size $headSize");
    }
    
    // If we reach here, no local file and GitHub proxy failed or not attempted
    // Return helpful HTML page with direct links (better than JSON which client treats as APK)
    http_response_code(200); // Return 200 with HTML, not 404, so client can show message
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $directLink = "https://direct.vpbotn.ir/download_apk.php?file={$fileParam}&v={$ver}";
    $githubLink51 = "https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.51/Connectix-ARM64-v8a.apk";
    $githubLink = "https://github.com/hojjatrad/panelconnectix/releases";
    $qrLink = "https://vpbotn.ir/qr_download.html";
    
    echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>دانلود Connectix</title><style>body{font-family:sans-serif;background:#020617;color:#fff;text-align:center;padding:20px} .card{background:#0F172A;border:1px solid #1E293B;border-radius:16px;padding:24px;max-width:400px;margin:20px auto} a{display:block;background:#6366F1;color:#fff;padding:12px;border-radius:12px;margin:10px 0;text-decoration:none;font-weight:bold} a.green{background:#10B981} a.amber{background:#F59E0B} .warn{background:#FEF3C7;color:#92400E;padding:12px;border-radius:12px;margin:15px 0;font-size:12px}</style></head><body>";
    echo "<div class='card'>";
    echo "<h2>📱 دانلود Connectix VPN</h2>";
    echo "<p style='color:#94A3B8;font-size:13px'>نسخه v{$ver} در سرور یافت نشد، از لینک‌های زیر استفاده کنید:</p>";
    echo "<div class='warn'>⚠️ اگر این صفحه را در اپ می‌بینید، روی لینک‌ها کلیک کنید تا مرورگر باز شود و دانلود شروع شود. بعد از دانلود، فایل را نصب کنید.</div>";
    echo "<a class='green' href='{$directLink}'>📥 دانلود مستقیم ARM64 (پیشنهادی - 36MB)</a>";
    echo "<a href='https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.51'>📥 دانلود مستقیم v4.0.51 (تست شده)</a>";
    echo "<a href='{$githubLink51}'>📥 دانلود از گیت‌هاب v4.0.51 (100% کار میکنه)</a>";
    echo "<a class='amber' href='{$qrLink}'>📱 QR کد دانلود</a>";
    echo "<a href='{$githubLink}'>🔗 تمام نسخه‌ها در گیت‌هاب</a>";
    echo "<p style='font-size:11px;color:#64748B;margin-top:20px'>برای آپدیت از v4.0.51: از لینک گیت‌هاب v4.0.51 دوباره نصب کن (با فیکس جدید)، بعد داخل اپ جدید آپدیت به v4.0.61 خودکار کار میکنه</p>";
    echo "<p style='font-size:10px;color:#475569;margin-top:10px'>v4.0.61 FIX - file=$fileParam v=$ver start=$startParam</p>";
    echo "</div></body></html>";
    exit;
}

$size = filesize($filePath);
$mime = 'application/vnd.android.package-archive';
$filename = basename($filePath);

error_log("download_apk.php v4.0.61: Serving $filePath size $size");

// Handle Range header OR ?start= param for resume
$range = $_SERVER['HTTP_RANGE'] ?? '';
$start = 0;
$end = $size - 1;
$length = $size;
$status = 200;

if ($startParam !== '' && is_numeric($startParam)) {
    $start = intval($startParam);
    if ($start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$size");
        exit;
    }
    $length = $size - $start;
    $end = $size - 1;
    $status = 206;
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$size");
    error_log("download_apk.php v4.0.61: Resume via ?start= $start, length $length, 206");
} elseif (!empty($range) && preg_match('/bytes=(\\d*)-(\\d*)/', $range, $matches)) {
    $rangeStart = $matches[1];
    $rangeEnd = $matches[2];
    
    if ($rangeStart === '' && $rangeEnd !== '') {
        $start = $size - intval($rangeEnd);
        if ($start < 0) $start = 0;
    } elseif ($rangeStart !== '' && $rangeEnd === '') {
        $start = intval($rangeStart);
        if ($start >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header("Content-Range: bytes */$size");
            exit;
        }
    } else {
        $start = intval($rangeStart);
        $end = $rangeEnd !== '' ? intval($rangeEnd) : $size - 1;
        if ($end >= $size) $end = $size - 1;
    }
    
    $length = $end - $start + 1;
    $status = 206;
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$size");
    error_log("download_apk.php v4.0.61: Resume via Range $start-$end, length $length, 206");
} else {
    header("HTTP/1.1 200 OK");
}

header("Content-Type: $mime");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Length: $length");
header("Accept-Ranges: bytes");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, no-transform, private");
header("Pragma: no-cache");
header("Expires: 0");
header("cf-cache-status: BYPASS");
header("CDN-Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
header("X-Accel-Buffering: no");

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$fh = @fopen($filePath, 'rb');
if (!$fh) {
    http_response_code(500);
    error_log("download_apk.php v4.0.61: Failed to open $filePath");
    exit;
}

if ($start > 0) {
    @fseek($fh, $start);
}

$chunkSize = 8192;
$bytesSent = 0;

while (!feof($fh) && $bytesSent < $length && !connection_aborted()) {
    $remaining = $length - $bytesSent;
    $readSize = $remaining < $chunkSize ? $remaining : $chunkSize;
    $buffer = @fread($fh, $readSize);
    if ($buffer === false) break;
    echo $buffer;
    $bytesSent += strlen($buffer);
    @flush();
    @ob_flush();
}

@fclose($fh);
error_log("download_apk.php v4.0.61: Served $bytesSent bytes from $start, status $status");
exit;
?>

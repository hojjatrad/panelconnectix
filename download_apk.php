<?php
// v4.0.70 ULTIMATE FIX - حل کامل عدم تغییر نسخه بعد از نصب
// Fixes: Stale APK serving old version, version mismatch, 90% loop, cache

@set_time_limit(0);
@ignore_user_abort(true);
@error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
@ini_set('display_errors', '0');

$fileParam = $_GET['file'] ?? 'arm64';
$version = $_GET['v'] ?? '';
$startParam = $_GET['start'] ?? $_GET['resume'] ?? '';

// Log request for debugging
error_log("download_apk.php v4.0.79: file=$fileParam v=$version start=$startParam IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . " UA=" . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

$map = [
    'arm64' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'arm64-v8a' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'universal' => __DIR__ . '/Connectix-Universal.apk',
    'arm32' => __DIR__ . '/Connectix-ARM32-v7a.apk',
    'arm32-v7a' => __DIR__ . '/Connectix-ARM32-v7a.apk',
];

$filePath = $map[$fileParam] ?? $map['arm64'];

// v4.0.72 SECURE FIX: Only delete if version mismatch AND file too small (<20MB), not based on mtime (DoS fix)
$requestedVer = $version;
$appReleasePath = __DIR__ . '/app_release.json';
$currentReleaseVer = '4.0.79';
if (file_exists($appReleasePath)) {
    $jsonContent = @file_get_contents($appReleasePath);
    $jsonData = @json_decode($jsonContent, true);
    if (isset($jsonData['version'])) {
        $currentReleaseVer = $jsonData['version'];
    }
}

// v4.0.72: Only delete stale if requested version != current AND file is too small or invalid
// Prevents DoS via mtime manipulation
if (!empty($requestedVer) && $requestedVer !== $currentReleaseVer && file_exists($filePath)) {
    $sz = filesize($filePath);
    if ($sz < 20*1024*1024) {
        error_log("download_apk.php v4.0.79: Version mismatch ($requestedVer vs $currentReleaseVer) and file too small ($sz) - deleting $filePath");
        @unlink($filePath);
    }
}

// Try exact file
if (!file_exists($filePath)) {
    $alt = __DIR__ . '/Connectix-Android-ARM64.apk';
    if (file_exists($alt) && in_array($fileParam, ['arm64', 'arm64-v8a'])) $filePath = $alt;
    $alt2 = __DIR__ . '/Connectix-Android-Universal.apk';
    if (file_exists($alt2) && $fileParam === 'universal') $filePath = $alt2;
    $alt3 = __DIR__ . '/Connectix-Android-ARM32.apk';
    if (file_exists($alt3) && in_array($fileParam, ['arm32', 'arm32-v7a'])) $filePath = $alt3;
}

// v4.0.73 FIX: If still not found, try ANY valid APK >20MB
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
                error_log("download_apk.php v4.0.79: Requested $fileParam not found or too small, using fallback $pf size $sz");
                break;
            } else {
                error_log("download_apk.php v4.0.79: Found $pf but too small $sz bytes - skipping");
            }
        }
    }
}

// If still no local file, try GitHub proxy
if (!file_exists($filePath) || filesize($filePath) < 20*1024*1024) {
    $ver = $version ?: '4.0.79';
    $ver = preg_replace('/[^0-9\.]/', '', $ver);
    if (empty($ver)) $ver = '4.0.79';
    
    $githubVer = $ver;
    $allowedGithubVersions = ['4.0.51', '4.0.64', '4.0.65', '4.0.66', '4.0.67', '4.0.68', '4.0.69', '4.0.70', '4.0.71', '4.0.72', '4.0.73', '4.0.74', '4.0.75', '4.0.76', '4.0.77', '4.0.78', '4.0.79'];
    if (!in_array($ver, $allowedGithubVersions) && version_compare($ver, '4.0.79', '>')) {
        $githubVer = '4.0.79';
        error_log("download_apk.php v4.0.79: Requested v$ver has no GitHub assets yet, using v$githubVer fallback");
    } elseif (version_compare($ver, '4.0.51', '>') && version_compare($ver, '4.0.75', '<')) {
        if (!in_array($ver, ['4.0.64', '4.0.65', '4.0.66', '4.0.67', '4.0.68', '4.0.69', '4.0.70', '4.0.71', '4.0.72', '4.0.73', '4.0.74', '4.0.75'])) {
            $githubVer = '4.0.51';
            error_log("download_apk.php v4.0.79: Requested v$ver has no GitHub assets, using v$githubVer fallback");
        }
    }
    
    $githubMap = [
        'arm64' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'arm64-v8a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'universal' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-Universal.apk",
        'arm32' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
        'arm32-v7a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
    ];
    $githubUrl = $githubMap[$fileParam] ?? $githubMap['arm64'];
    
    error_log("download_apk.php v4.0.79: No local file, trying GitHub proxy $githubUrl");
    
    $ch = curl_init($githubUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Proxy/4.0.79');
    curl_exec($ch);
    $headCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    error_log("download_apk.php v4.0.79: GitHub HEAD code $headCode type $headContentType size $headSize");
    
    $shouldProxy = ($headCode >= 200 && $headCode < 400) && 
                   (stripos($headContentType, 'text/html') === false) &&
                   ($headSize > 20*1024*1024 || $headSize == -1);
    
    if ($shouldProxy) {
        $ch = curl_init($githubUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Proxy/4.0.79');
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
        header("X-APK-Version: $githubVer");
        header("X-APK-Source: github-proxy");
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400) {
            error_log("download_apk.php v4.0.79: GitHub proxy success code $code");
            exit;
        }
        error_log("download_apk.php v4.0.79: GitHub proxy failed after HEAD success, code $code");
    } else {
        error_log("download_apk.php v4.0.79: GitHub HEAD check failed, not proxying - code $headCode type $headContentType size $headSize");
    }
    
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $directLink = "https://direct.vpbotn.ir/download_apk.php?file={$fileParam}&v={$ver}";
    $githubLink68 = "https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.79/Connectix-ARM64-v8a.apk";
    $githubLink = "https://github.com/hojjatrad/panelconnectix/releases";
    $qrLink = "https://vpbotn.ir/qr_download.html";
    
    echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>دانلود Connectix</title><style>body{font-family:sans-serif;background:#020617;color:#fff;text-align:center;padding:20px} .card{background:#0F172A;border:1px solid #1E293B;border-radius:16px;padding:24px;max-width:400px;margin:20px auto} a{display:block;background:#6366F1;color:#fff;padding:12px;border-radius:12px;margin:10px 0;text-decoration:none;font-weight:bold} a.green{background:#10B981} a.amber{background:#F59E0B} .warn{background:#FEF3C7;color:#92400E;padding:12px;border-radius:12px;margin:15px 0;font-size:12px}</style></head><body>";
    echo "<div class='card'>";
    echo "<h2>📱 دانلود Connectix VPN v4.0.79</h2>";
    echo "<p style='color:#94A3B8;font-size:13px'>نسخه v{$ver} در سرور یافت نشد، از لینک‌های زیر استفاده کنید:</p>";
    echo "<div class='warn'>⚠️ اگر این صفحه را در اپ می‌بینید، روی لینک‌ها کلیک کنید تا مرورگر باز شود و دانلود شروع شود. بعد از دانلود، فایل را نصب کنید.</div>";
    echo "<a class='green' href='{$githubLink68}'>📥 دانلود مستقیم از گیت‌هاب v4.0.79 (100% تست شده - 36MB)</a>";
    echo "<a href='https://vpbotn.ir/download_apk.php?file=arm64&v=4.0.75'>📥 دانلود مستقیم v4.0.79 از vpbotn</a>";
    echo "<a class='amber' href='{$qrLink}'>📱 QR کد دانلود</a>";
    echo "<a href='{$githubLink}'>🔗 تمام نسخه‌ها در گیت‌هاب</a>";
    echo "<p style='font-size:11px;color:#64748B;margin-top:20px'>v4.0.79 FIX: اگر نسخه جدید نصب می‌کنید ولی همچنان قدیمی می‌ماند، اول نسخه قدیمی را حذف کنید (Uninstall) سپس v4.0.79 را نصب کنید. یا از لینک گیت‌هاب مستقیم نصب کنید.</p>";
    echo "<p style='font-size:10px;color:#475569;margin-top:10px'>v4.0.79 FIX - file=$fileParam v=$ver start=$startParam</p>";
    echo "</div></body></html>";
    exit;
}

$size = filesize($filePath);
$mime = 'application/vnd.android.package-archive';
$filename = basename($filePath);

error_log("download_apk.php v4.0.79: Serving $filePath size $size version $currentReleaseVer");

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
    error_log("download_apk.php v4.0.79: Resume via ?start= $start, length $length, 206");
} elseif (!empty($range) && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
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
    error_log("download_apk.php v4.0.79: Resume via Range $start-$end, length $length, 206");
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
header("X-APK-Version: $currentReleaseVer");
header("X-APK-Source: local-file");
header("X-APK-Size: $size");

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$fh = @fopen($filePath, 'rb');
if (!$fh) {
    http_response_code(500);
    error_log("download_apk.php v4.0.79: Failed to open $filePath");
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
error_log("download_apk.php v4.0.79: Served $bytesSent bytes from $start, status $status version $currentReleaseVer");
exit;
?>

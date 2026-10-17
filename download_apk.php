<?php
// v4.0.38 FUNDAMENTAL FIX - APK Download with Resume Support via Range + Query Param
// Fixes 15% loop - Cloudflare strips Range header, so support ?start= param as fallback

@set_time_limit(0);
@ignore_user_abort(true);

$fileParam = $_GET['file'] ?? 'arm64';
$version = $_GET['v'] ?? '';
$startParam = $_GET['start'] ?? $_GET['resume'] ?? '';

$map = [
    'arm64' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'arm64-v8a' => __DIR__ . '/Connectix-ARM64-v8a.apk',
    'universal' => __DIR__ . '/Connectix-Universal.apk',
    'arm32' => __DIR__ . '/Connectix-ARM32-v7a.apk',
    'arm32-v7a' => __DIR__ . '/Connectix-ARM32-v7a.apk',
];

$filePath = $map[$fileParam] ?? $map['arm64'];

if (!file_exists($filePath)) {
    $alt = __DIR__ . '/Connectix-Android-ARM64.apk';
    if (file_exists($alt) && $fileParam === 'arm64') $filePath = $alt;
    $alt2 = __DIR__ . '/Connectix-Android-Universal.apk';
    if (file_exists($alt2) && $fileParam === 'universal') $filePath = $alt2;
}

if (!file_exists($filePath)) {
    // v4.0.57 FIX: Try to find ANY existing APK file first (don't fail if requested version not found)
    // Check for any available APK files
    $possibleFiles = [
        __DIR__ . '/Connectix-ARM64-v8a.apk',
        __DIR__ . '/Connectix-Android-ARM64.apk',
        __DIR__ . '/Connectix-Universal.apk',
        __DIR__ . '/Connectix-Android-Universal.apk',
        __DIR__ . '/Connectix-ARM32-v7a.apk',
        __DIR__ . '/Connectix-Android-ARM32.apk',
    ];
    foreach ($possibleFiles as $pf) {
        if (file_exists($pf) && filesize($pf) > 20*1024*1024) {
            // Found a valid APK, use it regardless of requested file type (better than 404)
            $filePath = $pf;
            error_log("download_apk.php v4.0.57: Requested $fileParam not found, using fallback $pf");
            break;
        }
    }
}

if (!file_exists($filePath)) {
    // v4.0.57 PROXY FALLBACK: If local file missing, proxy from GitHub releases
    // But only for versions that actually have releases with assets (<=4.0.51)
    // For newer versions, return helpful error with direct links
    $ver = $version ?: '4.0.51';
    $ver = preg_replace('/[^0-9\.]/', '', $ver);
    if (empty($ver)) $ver = '4.0.51';
    
    // v4.0.57 FIX: For versions >4.0.51, GitHub releases don't have APK assets (only code)
    // So try 4.0.51 as fallback for GitHub proxy
    $githubVer = $ver;
    if (version_compare($ver, '4.0.51', '>')) {
        $githubVer = '4.0.51'; // Latest with actual APK assets
        error_log("download_apk.php v4.0.57: Requested v$ver has no GitHub assets, using v$githubVer fallback");
    }
    
    $githubMap = [
        'arm64' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'arm64-v8a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM64-v8a.apk",
        'universal' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-Universal.apk",
        'arm32' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
        'arm32-v7a' => "https://github.com/hojjatrad/panelconnectix/releases/download/v{$githubVer}/Connectix-ARM32-v7a.apk",
    ];
    $githubUrl = $githubMap[$fileParam] ?? $githubMap['arm64'];
    
    // Try to proxy from GitHub
    try {
        $ch = curl_init($githubUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Connectix-Proxy/4.0.57');
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
        // v4.0.57 FIX: Check content type before streaming - if HTML, don't stream
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
            $len = strlen($header);
            if (stripos($header, 'Content-Type:') === 0) {
                if (stripos($header, 'text/html') !== false) {
                    error_log("download_apk.php v4.0.57: GitHub returned HTML not APK: $header");
                }
            }
            return $len;
        });
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        // v4.0.57 FIX: Only exit if we got actual APK (not HTML 404 page)
        if ($code >= 200 && $code < 400 && stripos($contentType, 'text/html') === false) {
            exit;
        }
        error_log("download_apk.php v4.0.57: GitHub proxy failed code $code type $contentType");
    } catch (Throwable $e) {
        error_log("download_apk.php v4.0.57: GitHub proxy exception: " . $e->getMessage());
    }
    
    // v4.0.57 FIX: Return helpful error with direct links instead of JSON (which client treats as APK and deletes)
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $directLink = "https://direct.vpbotn.ir/download_apk.php?file={$fileParam}&v={$ver}";
    $githubLink = "https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.51/Connectix-ARM64-v8a.apk";
    echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='utf-8'><title>APK یافت نشد</title></head><body style='font-family:sans-serif;background:#020617;color:#fff;text-align:center;padding:30px'>";
    echo "<h2>⚠️ فایل APK نسخه v{$ver} یافت نشد</h2>";
    echo "<p>لطفا از لینک‌های مستقیم زیر استفاده کنید:</p>";
    echo "<p><a href='{$directLink}' style='color:#10B981'>📥 دانلود مستقیم ARM64 (پیشنهادی)</a></p>";
    echo "<p><a href='{$githubLink}' style='color:#6366F1'>📥 دانلود از گیت‌هاب v4.0.51</a></p>";
    echo "<p><a href='https://vpbotn.ir/qr_download.html' style='color:#F59E0B'>📱 QR کد دانلود</a></p>";
    echo "<p style='font-size:12px;color:#94A3B8;margin-top:20px'>اگر این صفحه را در اپ می‌بینید، اپ را ببندید و از مرورگر دانلود کنید</p>";
    echo "</body></html>";
    exit;
}

$size = filesize($filePath);
$mime = 'application/vnd.android.package-archive';
$filename = basename($filePath);

// Handle Range header OR ?start= param for resume - FUNDAMENTAL FIX
$range = $_SERVER['HTTP_RANGE'] ?? '';
$start = 0;
$end = $size - 1;
$length = $size;
$status = 200;

// Check ?start= param first (Cloudflare strips Range header, so use query param)
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
exit;
?>

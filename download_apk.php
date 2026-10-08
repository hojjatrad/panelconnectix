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
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'APK not found', 'file' => $fileParam]);
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

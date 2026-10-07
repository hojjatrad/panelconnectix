<?php
// upload_apk_direct.php - Direct APK upload via chunks, bypasses PHP-only filter
// For fixing 8 update links failure

@set_time_limit(600);
@ini_set('max_execution_time', '600');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_apk_chunk') {
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 1);
    $chunkData = $_POST['chunk_data'] ?? '';
    $sessionId = $_POST['session_id'] ?? 'default';
    $fileName = $_POST['file_name'] ?? 'Connectix-ARM64-v8a.apk';
    
    // Sanitize file name
    $fileName = basename($fileName);
    $allowed = ['Connectix-ARM64-v8a.apk', 'Connectix-Universal.apk', 'Connectix-ARM32-v7a.apk', 'Connectix-Android.apk', 'Connectix-Android-ARM64.apk', 'Connectix-Android-Universal.apk', 'Connectix-Android-ARM32.apk'];
    if (!in_array($fileName, $allowed)) {
        $fileName = 'Connectix-ARM64-v8a.apk';
    }
    
    $tmpDir = __DIR__ . '/data/tmp';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
    $tmpFile = $tmpDir . '/apk_upload_' . $sessionId . '_' . $fileName;
    $finalFile = __DIR__ . '/' . $fileName;
    
    $binary = base64_decode($chunkData);
    if ($chunkIndex === 0) {
        file_put_contents($tmpFile, $binary);
    } else {
        file_put_contents($tmpFile, $binary, FILE_APPEND);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'chunk' => $chunkIndex, 'total' => $totalChunks, 'size' => filesize($tmpFile), 'tmp' => $tmpFile]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_apk') {
    $sessionId = $_POST['session_id'] ?? 'default';
    $fileName = $_POST['file_name'] ?? 'Connectix-ARM64-v8a.apk';
    $fileName = basename($fileName);
    
    $tmpDir = __DIR__ . '/data/tmp';
    $tmpFile = $tmpDir . '/apk_upload_' . $sessionId . '_' . $fileName;
    $finalFile = __DIR__ . '/' . $fileName;
    
    if (!file_exists($tmpFile)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'tmp not found']);
        exit;
    }
    
    // Verify APK header
    $fh = @fopen($tmpFile, 'rb');
    $header = $fh ? @fread($fh, 4) : '';
    if ($fh) @fclose($fh);
    $isApk = $header && $header[0] === "\x50" && $header[1] === "\x4B";
    $size = filesize($tmpFile);
    
    if (!$isApk || $size < 1024*1024) {
        @unlink($tmpFile);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'not apk or too small', 'size' => $size]);
        exit;
    }
    
    // Move to final
    if (!@rename($tmpFile, $finalFile)) {
        @copy($tmpFile, $finalFile);
        @unlink($tmpFile);
    }
    @chmod($finalFile, 0644);
    
    // Also copy to alternative names
    $copies = [];
    if ($fileName === 'Connectix-Android-ARM64.apk') {
        @copy($finalFile, __DIR__ . '/Connectix-ARM64-v8a.apk');
        $copies[] = 'Connectix-ARM64-v8a.apk';
    }
    if ($fileName === 'Connectix-Android-Universal.apk') {
        @copy($finalFile, __DIR__ . '/Connectix-Universal.apk');
        @copy($finalFile, __DIR__ . '/Connectix-Android.apk');
        $copies[] = 'Connectix-Universal.apk, Connectix-Android.apk';
    }
    if ($fileName === 'Connectix-Android-ARM32.apk') {
        @copy($finalFile, __DIR__ . '/Connectix-ARM32-v7a.apk');
        $copies[] = 'Connectix-ARM32-v7a.apk';
    }
    if ($fileName === 'Connectix-ARM64-v8a.apk') {
        @copy($finalFile, __DIR__ . '/Connectix-Android-ARM64.apk');
        $copies[] = 'Connectix-Android-ARM64.apk';
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'file' => $fileName, 'size' => $size, 'copies' => $copies]);
    exit;
}

echo "<h2>APK Direct Upload</h2>";
echo "<p>Use POST with action=upload_apk_chunk and finalize_apk</p>";

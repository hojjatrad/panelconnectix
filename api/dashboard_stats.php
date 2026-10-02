<?php
// O6: AJAX Dashboard Stats - Loads after page for 0.3s initial load
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/FinancialReport.php';

if (!Auth::check()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $overview = FinancialReport::getOverview();
    $daily = FinancialReport::getDailySales(7);
    
    // Get cache stats
    $cacheStats = ['files' => 0, 'size' => 0];
    if (class_exists('Cache')) {
        require_once __DIR__ . '/../core/Cache.php';
        $cacheDir = __DIR__ . '/../cache';
        $files = @glob($cacheDir . '/*.cache');
        $cacheStats['files'] = $files ? count($files) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'overview' => $overview,
        'daily' => $daily,
        'cache' => $cacheStats,
        'generated_at' => date('Y-m-d H:i:s'),
        'load_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])*1000, 2) . 'ms'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

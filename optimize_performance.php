<?php
/**
 * Connectix Performance Optimizer - One-time runner
 * URL: https://vpbotn.ir/contax/optimize_performance.php?key=CONNECTIX2026
 * 
 * Performs Phase 1 & 2 optimizations:
 * - WAL mode, cache, indexes
 * - File cache warmup
 * - DB VACUUM/ANALYZE
 */

define('CONNECTIX_NO_DIE', true);

if (($_GET['key'] ?? '') !== 'CONNECTIX2026' && ($_GET['key'] ?? '') !== 'CONNECTIX_PERF_2026') {
    die('Unauthorized - key required');
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Cache.php';
require_once __DIR__ . '/core/Performance.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>بهینه‌سازی سرعت پنل</title>";
echo "<script src='/contax/assets/js/tailwind.js'></script>";
echo "<link rel='stylesheet' href='/contax/assets/css/fontawesome.min.css'>";
echo "<link rel='stylesheet' href='/contax/assets/css/vazirmatn.css'>";
echo "<style>*{font-family:'Vazirmatn',sans-serif}</style></head>";
echo "<body class='bg-slate-950 text-slate-100 min-h-screen p-6'><div class='max-w-3xl mx-auto space-y-6'>";

echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6'>";
echo "<h1 class='text-xl font-black text-white mb-2'>🚀 بهینه‌سازی سرعت پنل - فاز 1 و 2</h1>";
echo "<p class='text-xs text-slate-400'>در حال اجرای بهینه‌سازی‌های عملکرد...</p>";
echo "</div>";

$startAll = microtime(true);

// 1. DB Optimization
echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-3'>";
echo "<h2 class='font-bold text-purple-400'>1️⃣ بهینه‌سازی دیتابیس</h2>";
echo "<div class='space-y-1 text-xs font-mono'>";
$opt = Performance::optimizeDatabase();
foreach ($opt['messages'] as $msg) {
    echo "<div class='text-slate-300'>$msg</div>";
}
echo "</div></div>";

// 2. Cache Warmup
echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-3'>";
echo "<h2 class='font-bold text-emerald-400'>2️⃣ گرم کردن کش</h2>";
echo "<div class='space-y-1 text-xs font-mono'>";
$warm = Performance::warmupCache();
foreach ($warm['messages'] as $msg) {
    echo "<div class='text-slate-300'>$msg</div>";
}
echo "</div></div>";

// 3. Stats
echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-3'>";
echo "<h2 class='font-bold text-cyan-400'>3️⃣ آمار عملکرد</h2>";
echo "<div class='space-y-1 text-xs font-mono'>";
$stats = Performance::getStats();
foreach ($stats as $k => $v) {
    if (is_array($v)) continue;
    echo "<div class='flex justify-between'><span class='text-slate-400'>$k</span><span class='text-white'>$v</span></div>";
}
echo "</div></div>";

$totalTime = round((microtime(true) - $startAll)*1000, 2);
echo "<div class='bg-emerald-950/40 border border-emerald-800 rounded-2xl p-6 text-center'>";
echo "<div class='text-emerald-400 font-black text-lg'>✅ بهینه‌سازی کامل شد</div>";
echo "<div class='text-xs text-slate-300 mt-2'>زمان کل: {$totalTime}ms | نسخه پنل: " . (defined('Updater::CURRENT_VERSION') ? Updater::CURRENT_VERSION : '6.7.0') . "</div>";
echo "<div class='mt-4 grid grid-cols-2 gap-2'>";
echo "<a href='/contax/dashboard' class='py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs'>رفتن به داشبورد</a>";
echo "<a href='/contax/optimize_performance.php?key=CONNECTIX2026&clear=1' class='py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-xs border border-slate-700'>پاکسازی کش</a>";
echo "</div>";
echo "</div>";

if (isset($_GET['clear'])) {
    $cleared = Cache::clear();
    echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-4 text-xs text-center text-slate-400'>🧹 $cleared فایل کش پاک شد</div>";
}

echo "</div></body></html>";

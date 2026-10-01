<?php
/**
 * Migrate SQLite to MySQL - Phase 2
 * URL: https://vpbotn.ir/contax/migrate_to_mysql.php?key=CONNECTIX2026
 * 
 * Steps:
 * 1. Create MySQL database in cPanel
 * 2. Set config.php to MySQL credentials
 * 3. Run this script to migrate data from SQLite to MySQL
 */

if (($_GET['key'] ?? '') !== 'CONNECTIX2026') {
    die('Unauthorized');
}

require_once __DIR__ . '/config.php';

if (DB_DRIVER !== 'mysql') {
    die("
    <h2>❌ DB_DRIVER هنوز mysql نیست</h2>
    <p>لطفا ابتدا در cPanel یک دیتابیس MySQL بسازید و در config.php این مقادیر را ست کنید:</p>
    <pre>
    define('DB_DRIVER', 'mysql');
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'your_db_name');
    define('DB_USER', 'your_db_user');
    define('DB_PASS', 'your_db_pass');
    </pre>
    <p>بعد دوباره این صفحه را باز کنید.</p>
    ");
}

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Performance.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>مهاجرت به MySQL</title>";
echo "<script src='/contax/assets/js/tailwind.js'></script>";
echo "<link rel='stylesheet' href='/contax/assets/css/fontawesome.min.css'>";
echo "<style>*{font-family:'Vazirmatn',sans-serif}</style></head>";
echo "<body class='bg-slate-950 text-slate-100 min-h-screen p-6'><div class='max-w-3xl mx-auto space-y-6'>";

echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6'>";
echo "<h1 class='text-xl font-black'>🔄 مهاجرت SQLite به MySQL</h1>";
echo "<p class='text-xs text-slate-400 mt-2'>DB: " . DB_NAME . " @ " . DB_HOST . "</p>";
echo "</div>";

if (!file_exists(SQLITE_PATH)) {
    echo "<div class='bg-rose-950/40 border border-rose-800 rounded-2xl p-6 text-rose-300 text-sm'>❌ فایل SQLite پیدا نشد: " . SQLITE_PATH . "</div>";
    exit;
}

echo "<div class='bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-2 text-xs font-mono'>";

$start = microtime(true);
try {
    // First ensure MySQL tables exist
    $pdo = Database::getConnection();
    echo "<div class='text-cyan-400'>✓ اتصال MySQL برقرار شد</div>";
    
    // Now migrate
    $result = Performance::migrateToMySQL(DB_HOST, DB_NAME, DB_USER, DB_PASS, (int)DB_PORT);
    
    foreach ($result['messages'] as $msg) {
        $color = str_contains($msg, '❌') ? 'text-rose-400' : (str_contains($msg, '✓') ? 'text-emerald-400' : 'text-slate-300');
        echo "<div class='$color'>$msg</div>";
    }
    
    $time = round((microtime(true)-$start)*1000,2);
    if ($result['success']) {
        echo "<div class='mt-4 p-4 bg-emerald-950/40 border border-emerald-800 rounded-xl text-emerald-300 text-center'>";
        echo "🎉 مهاجرت کامل شد - {$result['migrated']} ردیف در {$time}ms<br>";
        echo "<span class='text-[11px] text-slate-400'>حالا می‌توانید فایل SQLite را بکاپ بگیرید و حذف کنید (اختیاری)</span>";
        echo "</div>";
    }
} catch (Throwable $e) {
    echo "<div class='text-rose-400'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
echo "<div class='text-center'><a href='/contax/dashboard' class='inline-block py-2.5 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs'>بازگشت به داشبورد</a></div>";
echo "</div></body></html>";

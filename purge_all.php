<?php
/**
 * Connectix Panel - Instant 100% Clean Slate & Purge Utility
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

define('CONNECTIX_REPAIR', true);

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("config.php not found");
}
require_once $configPath;
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    } else {
        $pdo->exec("PRAGMA foreign_keys = OFF;");
    }

    $wipeTables = [
        'bot_orders',
        'trial_logs',
        'reserved_plans',
        'clients',
        'reseller_plans',
        'plans',
        'server_nodes',
        'transactions',
        'lucky_wheel_logs',
        'wallet_logs',
        'crypto_payments',
        'bot_sessions'
    ];

    $deletedCounts = [];
    foreach ($wipeTables as $tbl) {
        try {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
            $pdo->exec("DELETE FROM {$tbl}");
            $deletedCounts[$tbl] = $cnt;
        } catch (Throwable $e) {
            $deletedCounts[$tbl] = 0;
        }
    }

    if ($driver === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } else {
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    // Ensure Admin User Exists
    try {
        $adminUser = $pdo->query("SELECT id, username FROM users WHERE role = 'admin' LIMIT 1")->fetch();
        if (!$adminUser) {
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, api_token) 
                        VALUES ('admin', '{$adminPass}', 'admin', 'مدیر کل سیستم', 'admin@connectix.local', 0, 'admin_secret_123')");
        }
    } catch (Throwable $e) {}

    if (function_exists('opcache_reset')) @opcache_reset();
    if (function_exists('clearstatcache')) @clearstatcache(true);

} catch (Throwable $e) {
    die("<div style='font-family:sans-serif;direction:rtl;padding:40px;color:#ef4444;'><h2>خطا:</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></div>");
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>خام‌سازی کامل سامانه | Connectix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 text-center space-y-5 shadow-2xl">
        <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 mx-auto flex items-center justify-center text-3xl shadow-xl">
            <i class="fa-solid fa-broom"></i>
        </div>
        
        <div>
            <h1 class="text-xl font-bold text-white">سامانه ۱۰۰٪ خام و پاکسازی شد!</h1>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                تمامی سرورهای ماک، پلن‌های تستی، سفارشات آزمایشی، کلاینت‌های نمونه و تراکنش‌ها با موفقیت حذف گردیدند.
            </p>
        </div>

        <div class="bg-slate-950/80 border border-slate-800/80 rounded-2xl p-4 text-xs text-right space-y-2">
            <div class="text-[11px] font-bold text-slate-400 border-b border-slate-800 pb-1.5 flex items-center justify-between">
                <span>📋 گزارش حذف جداول:</span>
                <span class="text-purple-400 font-mono">DB: <?= strtoupper($driver ?? 'SQL') ?></span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[11px]">
                <div class="bg-slate-900/90 p-2 rounded-xl flex items-center justify-between">
                    <span class="text-slate-400">سرورها (server_nodes):</span>
                    <span class="font-bold text-rose-400"><?= $deletedCounts['server_nodes'] ?? 0 ?> حذف شد</span>
                </div>
                <div class="bg-slate-900/90 p-2 rounded-xl flex items-center justify-between">
                    <span class="text-slate-400">پلن‌ها (plans):</span>
                    <span class="font-bold text-rose-400"><?= $deletedCounts['plans'] ?? 0 ?> حذف شد</span>
                </div>
                <div class="bg-slate-900/90 p-2 rounded-xl flex items-center justify-between">
                    <span class="text-slate-400">کلاینت‌ها (clients):</span>
                    <span class="font-bold text-rose-400"><?= $deletedCounts['clients'] ?? 0 ?> حذف شد</span>
                </div>
                <div class="bg-slate-900/90 p-2 rounded-xl flex items-center justify-between">
                    <span class="text-slate-400">سفارشات (bot_orders):</span>
                    <span class="font-bold text-rose-400"><?= $deletedCounts['bot_orders'] ?? 0 ?> حذف شد</span>
                </div>
            </div>
        </div>

        <div class="pt-2 space-y-2">
            <a href="servers" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-purple-900/40">
                <i class="fa-solid fa-server"></i>
                <span>ورود به مدیریت سرورها و تعریف سرور جدید</span>
            </a>
            <a href="plans" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition flex items-center justify-center gap-2 border border-slate-700">
                <i class="fa-solid fa-layer-group"></i>
                <span>ورود به مدیریت پلن‌ها و تعریف پلن اختصاصی</span>
            </a>
            <a href="login" class="w-full py-2 text-slate-500 hover:text-slate-300 text-xs transition block">
                ورود به صفحه اصلی پنل مدیریت
            </a>
        </div>
    </div>
</body>
</html>

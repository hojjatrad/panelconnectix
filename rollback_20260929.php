<?php
/**
 * ROLLBACK SCRIPT - v3.5.8 Panel Optimization
 * Created: 2026-09-29
 * Purpose: One-click revert to exact state before fixes 1-4
 * 
 * Usage: Access via browser https://vpbotn.ir/contax/rollback_20260929.php
 * Or via SSH: php rollback_20260929.php
 * 
 * This restores:
 * - index.php (removes safe flags, restores original heavy bootstrap + self-heal)
 * - .htaccess
 * - views/layout/header.php
 * - views/auth/login.php
 * - views/apps/download.php
 * - views/client_portal/index.php
 * - views/sublink/landing.php
 * - views/webapp/index.php
 * 
 * Also removes local assets if you want (optional)
 */

$backupDir = __DIR__ . '/backups/20260929-panel-optimizations';
$files = [
    'index.php' => $backupDir . '/index.php.backup',
    '.htaccess' => $backupDir . '/htaccess.backup',
    'views/layout/header.php' => $backupDir . '/header.php.backup',
    'views/auth/login.php' => $backupDir . '/login.php.backup',
    'views/apps/download.php' => $backupDir . '/download.php.backup',
    'views/client_portal/index.php' => $backupDir . '/client_portal.php.backup',
    'views/sublink/landing.php' => $backupDir . '/landing.php.backup',
    'views/webapp/index.php' => $backupDir . '/webapp.php.backup',
];

$results = [];
$allOk = true;

foreach ($files as $dest => $src) {
    $destPath = __DIR__ . '/' . $dest;
    if (file_exists($src)) {
        if (@copy($src, $destPath)) {
            $results[] = "✅ Restored $dest from backup";
        } else {
            $results[] = "❌ Failed to restore $dest";
            $allOk = false;
        }
    } else {
        $results[] = "⚠️ Backup not found for $dest ($src)";
    }
}

// Also remove flag files that disable heavy bootstrap
$flags = [
    __DIR__ . '/data/enable_heavy_bootstrap',
    __DIR__ . '/data/enable_autheal',
];
foreach ($flags as $flag) {
    if (file_exists($flag)) {
        @unlink($flag);
        $results[] = "🗑️ Removed flag " . basename($flag);
    }
}

// Check root redirect backup
$rootHtaccess = dirname(__DIR__) . '/.htaccess';
$rootBackup = dirname(__DIR__) . '/.htaccess.backup-root-20260929';
if (file_exists($rootBackup) && file_exists($rootHtaccess)) {
    $results[] = "ℹ️ Root .htaccess backup exists at $rootBackup - if root redirect causes issues, restore via: cp $rootBackup $rootHtaccess";
}

if (php_sapi_name() === 'cli') {
    echo implode("\n", $results) . "\n";
    echo $allOk ? "\n✅ ROLLBACK COMPLETED - Panel restored to pre-optimization state\n" : "\n⚠️ ROLLBACK PARTIAL - Check errors above\n";
} else {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>Rollback - Connectix Panel</title>
        <link rel="stylesheet" href="<?= __DIR__ ?>/assets/css/fontawesome.min.css">
        <link rel="stylesheet" href="<?= __DIR__ ?>/assets/css/vazirmatn.css">
        <script src="<?= __DIR__ ?>/assets/js/tailwind.js"></script>
        <style>*{font-family:'Vazirmatn',sans-serif}</style>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-rotate-left text-amber-400"></i>
                <span>بازگشت به حالت قبل - Rollback</span>
            </h1>
            <div class="bg-slate-950 rounded-xl p-4 border border-slate-800 font-mono text-xs space-y-1">
                <?php foreach ($results as $r): ?>
                    <div><?= htmlspecialchars($r) ?></div>
                <?php endforeach; ?>
            </div>
            <?php if ($allOk): ?>
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl text-xs">
                    ✅ بازگشت با موفقیت انجام شد - پنل به حالت قبل از بهینه‌سازی برگشت
                </div>
            <?php else: ?>
                <div class="p-3 bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl text-xs">
                    ⚠️ بازگشت ناقص - برخی فایل‌ها بازیابی نشدند، لاگ را بررسی کنید
                </div>
            <?php endif; ?>
            <div class="flex gap-2">
                <a href="index.php" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold text-center">بازگشت به پنل</a>
                <a href="fix_root_redirect.php" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold text-center">بررسی ریدایرکت روت</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

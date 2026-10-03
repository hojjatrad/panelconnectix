<?php
// Cleanup emergency files - v6.8.12
while (ob_get_level() > 0) { @ob_end_clean(); }
ini_set('display_errors', 0);
error_reporting(0);

$sp = __DIR__ . '/data/sessions';
if (!is_dir($sp)) @mkdir($sp, 0755, true);
if (is_dir($sp)) @ini_set('session.save_path', $sp);
if (session_status() === PHP_SESSION_NONE) @session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("<h3 style='font-family:sans-serif;text-align:center;margin-top:50px'>⛔ فقط ادمین - لطفاً لاگین کنید</h3>");
}

$deleted = [];
$patterns = [
    __DIR__ . '/emergency_json_fix.php',
    __DIR__ . '/cleanup_emergency.php',
    __DIR__ . '/__canary_*.txt',
    __DIR__ . '/index_backup_*.php',
    __DIR__ . '/*.old.*',
    __DIR__ . '/*.bak_*',
    __DIR__ . '/.opcache_reset_done_*',
    __DIR__ . '/data/tmp/cx_*',
    __DIR__ . '/data/tmp/fix_*',
    __DIR__ . '/data/tmp/connectix_*',
    __DIR__ . '/data/tmp_update_*',
    sys_get_temp_dir() . '/cx_*',
    sys_get_temp_dir() . '/connectix_*',
    __DIR__ . '/quick_update.php.old.*',
];

foreach ($patterns as $pat) {
    foreach (glob($pat) as $f) {
        if (is_file($f)) {
            if (@unlink($f)) $deleted[] = basename($f);
        } elseif (is_dir($f)) {
            // recursive delete dir
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) {
                $file->isFile() ? @unlink($file->getPathname()) : @rmdir($file->getPathname());
            }
            @rmdir($f);
            $deleted[] = basename($f) . '/ (DIR)';
        }
    }
}

// Also clean data/tmp old files older than 1 hour
$tmpDir = __DIR__ . '/data/tmp';
if (is_dir($tmpDir)) {
    foreach (glob($tmpDir . '/*') as $f) {
        if (is_file($f) && filemtime($f) < time() - 3600) {
            if (@unlink($f)) $deleted[] = 'tmp/' . basename($f);
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>پاکسازی</title>
<style>body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;padding:30px;direction:rtl}.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;max-width:600px;margin:20px auto}li{margin:5px 0;font-family:monospace;font-size:12px}.ok{color:#22c55e}</style>
</head>
<body>
<div class="box">
<h3 style="color:#a78bfa">🧹 پاکسازی فایل‌های اضطراری</h3>
<?php if (empty($deleted)): ?>
<p>هیچ فایل اضافی یافت نشد - همه چیز تمیز است ✅</p>
<?php else: ?>
<p class="ok">✅ <?= count($deleted) ?> فایل حذف شد:</p>
<ul>
<?php foreach ($deleted as $d): ?><li><?= htmlspecialchars($d) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>
<p style="margin-top:20px"><a href="index.php?route=updater" style="color:#8b5cf6">→ بازگشت به مرکز آپدیت</a></p>
<p style="font-size:11px;color:#64748b;margin-top:10px">این فایل خودش هم حذف شد - اگر نشد دستی حذف کن: cleanup_emergency.php</p>
</div>
</body>
</html>
<?php
// Self-delete after showing result
@unlink(__FILE__);

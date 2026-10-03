<?php
// Fix root to panel v6.8.13 - standalone, works from any subfolder
while (ob_get_level() > 0) { @ob_end_clean(); }
ini_set('display_errors', 0);
error_reporting(0);

$panelDir = __DIR__;
if (!is_file($panelDir . '/index.php') || !is_file($panelDir . '/core/Database.php')) {
    // Try parent
    if (is_file(dirname($panelDir) . '/index.php') && is_file(dirname($panelDir) . '/core/Database.php')) {
        $panelDir = dirname($panelDir);
    } elseif (is_file($panelDir . '/connectix-panel/index.php')) {
        $panelDir = $panelDir . '/connectix-panel';
    }
}
$panelIndex = $panelDir . '/index.php';

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$publicHtmlCandidates = [
    $docRoot . '/index.php',
    dirname($panelDir) . '/index.php',
    $panelDir . '/../index.php',
    '/home/' . get_current_user() . '/public_html/index.php',
    $_SERVER['HOME'] . '/public_html/index.php' ?? '',
];

$restored = 0;
$logs = [];
foreach ($publicHtmlCandidates as $t) {
    if (empty($t)) continue;
    $dir = dirname($t);
    if (!is_dir($dir)) continue;
    if (realpath($dir) === realpath($panelDir)) continue;
    if (!is_writable($dir)) continue;
    // Backup promo if exists
    if (is_file($t)) {
        $c = @file_get_contents($t);
        if ($c && (strpos($c, 'خرید VPN') !== false || strpos($c, 'Connectix v6') !== false || strpos($c, 'promo') !== false)) {
            @copy($t, $dir . '/index_promo_backup_' . date('Ymd_His') . '.php');
            $logs[] = "بکاپ تبلیغات: " . basename($dir) . '/index_promo_backup_...';
        }
    }
    if (is_file($panelIndex)) {
        if (@copy($panelIndex, $t)) {
            $restored++;
            $logs[] = "✅ بازیابی: $t";
        } else {
            $content = @file_get_contents($panelIndex);
            if ($content && @file_put_contents($t, $content)) {
                $restored++;
                $logs[] = "✅ بازیابی (file_put_contents): $t";
            } else {
                $logs[] = "❌ شکست: $t";
            }
        }
    }
}

// Set setting to prevent re-overwrite
try {
    if (file_exists($panelDir . '/config.php')) {
        require_once $panelDir . '/config.php';
        require_once $panelDir . '/core/Database.php';
        require_once $panelDir . '/core/Setting.php';
        Setting::set('show_promo_at_root', '0');
        $logs[] = "تنظیم show_promo_at_root=0 ذخیره شد";
    }
} catch (Throwable $e) {
    $logs[] = "خطا تنظیم: " . $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>فیکس روت</title><style>body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;direction:rtl}.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;max-width:700px;margin:20px auto}li{margin:5px 0;font-size:12px;font-family:monospace}</style></head><body><div class='box'>";
echo "<h2>🔧 نتیجه بازیابی پنل در روت</h2><p>پنل: $panelDir</p><p>تعداد بازیابی: $restored</p><ul>";
foreach ($logs as $l) echo "<li>" . htmlspecialchars($l) . "</li>";
echo "</ul><hr><p><a href='/login' style='color:#8b5cf6'>→ ورود به پنل ( /login )</a> | <a href='/promo/' style='color:#06b6d4'>تبلیغات ( /promo/ )</a> | <a href='/' style='color:#22c55e'>روت ( / )</a></p>";
echo "<p style='font-size:11px;color:#64748b;margin-top:15px'>اگر هنوز تبلیغات میاد، کش مرورگر Ctrl+F5 بزن یا کلودفلر را Purge کن. این فایل حذف شد.</p></div></body></html>";
@unlink(__FILE__);

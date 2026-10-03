<?php
// Fix root to panel - standalone, no auth needed for emergency (deletes itself)
$panelIndex = __DIR__ . '/index.php';
$publicHtml = dirname(__DIR__) . '/index.php';
if (!is_file($panelIndex)) $panelIndex = __DIR__ . '/../connectix-panel/index.php';

$targets = [
    __DIR__ . '/../index.php',
    dirname(__DIR__) . '/index.php',
    $_SERVER['DOCUMENT_ROOT'] . '/index.php',
];

$restored = 0;
foreach ($targets as $t) {
    if (!is_dir(dirname($t))) continue;
    if (realpath(dirname($t)) === realpath(__DIR__)) continue;
    // Backup promo if exists
    if (is_file($t)) {
        $c = @file_get_contents($t);
        if ($c && (strpos($c, 'خرید VPN') !== false || strpos($c, 'Connectix v6') !== false)) {
            @copy($t, dirname($t) . '/index_promo_backup_' . date('Ymd_His') . '.php');
        }
    }
    if (is_file($panelIndex)) {
        if (@copy($panelIndex, $t)) $restored++;
    }
}

// Also set setting
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Setting.php';
    Setting::set('show_promo_at_root', '0');
} catch (Throwable $e) {}

header('Content-Type: text/html; charset=utf-8');
echo "<h2 style='font-family:sans-serif;text-align:center;margin-top:50px'>✅ پنل در روت بازیابی شد ($restored فایل)<br><br><a href='/login'>ورود به پنل</a> | <a href='/promo/'>تبلیغات</a><br><br><small>این فایل را حذف کنید: fix_root_to_panel.php</small></h2>";
@unlink(__FILE__);

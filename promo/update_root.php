<?php
// Force update https://vpbotn.ir/ from https://vpbotn.ir/contax/promo/
// Access: https://vpbotn.ir/contax/promo/update_root.php?key=CONNECTIX2026
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - key=CONNECTIX2026");

echo "<h2>🔄 بروزرسانی صفحه اصلی https://vpbotn.ir/ از /contax/promo/</h2>";

$source = __DIR__ . '/index.php';
$targets = [
    dirname(__DIR__, 1) . '/../index.php', // public_html if contax in public_html/contax
    dirname(__DIR__, 2) . '/index.php', // one more level
    '/home/vpbotnir/public_html/index.php',
    '/home/vpbotnir/public_html/contax/../index.php',
    __DIR__ . '/../root-landing/index.php', // ensure root-landing is also updated
];

echo "<ul>";
echo "<li>Source: $source - ".(file_exists($source) ? "✅ exists ".filesize($source)." bytes" : "❌ missing")."</li>";

$content = file_get_contents($source);
echo "<li>Source contains @mainAdminpanel: ".(strpos($content,'mainAdminpanel')!==false?'✅':'❌')."</li>";
echo "<li>Source contains سه‌بعدی: ".(strpos($content,'سه‌بعدی')!==false || strpos($content,'سه بعدی')!==false ? '❌ هنوز داره - باید حذف بشه' : '✅ حذف شده')."</li>";
echo "<li>Source contains demo123: ".(strpos($content,'demo123')!==false?'✅':'❌')."</li>";
echo "<li>Source version v6.1: ".(strpos($content,'v6.1')!==false?'✅':'❌')."</li>";
echo "</ul>";

$success = 0;
foreach ($targets as $target) {
    $target = realpath(dirname($target)) ? realpath(dirname($target)).'/'.basename($target) : $target;
    echo "<p>Checking target: $target ... ";
    $dir = dirname($target);
    if (!is_dir($dir)) {
        echo "DIR not exists, skip</p>";
        continue;
    }
    // Backup old
    if (file_exists($target)) {
        $old = file_get_contents($target);
        if (strpos($old, 'Connectix') !== false && strpos($old, 'mainAdminpanel') !== false) {
            // Already our landing, but old version - backup
            @copy($target, $dir.'/index_backup_'.date('Ymd_His').'.php');
        } elseif (strpos($old, 'Connectix') === false) {
            @copy($target, $dir.'/index_backup_'.date('Ymd_His').'.php');
            echo "Backed up old index.php - ";
        }
    }
    if (@copy($source, $target)) {
        echo "<span style='color:green'>✅ Copied! NOW https://vpbotn.ir/ updated!</span></p>";
        $success++;
    } else {
        echo "<span style='color:red'>❌ Failed - permission error</span></p>";
    }
}

// Also ensure root-landing is updated
$rootLanding = __DIR__ . '/../root-landing/index.php';
if (file_exists($rootLanding)) {
    $rlContent = file_get_contents($rootLanding);
    if (strpos($rlContent, 'سه‌بعدی') !== false) {
        // Update root-landing from promo
        @copy($source, $rootLanding);
        echo "<p>✅ Updated root-landing/index.php from promo (removed سه‌بعدی)</p>";
    }
}

if (function_exists('opcache_reset')) @opcache_reset();

echo "<h3>Result: $success targets updated</h3>";
echo "<p><a href='/' target='_blank' style='background:#7C3AED;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>🏠 باز کردن https://vpbotn.ir/ (باید نسخه جدید بدون سه‌بعدی باشه)</a></p>";
echo "<p><a href='./' target='_blank' style='background:#06B6D4;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>🌌 باز کردن https://vpbotn.ir/contax/promo/ (مرجع)</a></p>";

echo "<h4>اگر باز هم نشد، دستی کپی کن:</h4>";
echo "<ol>";
echo "<li>برو File Manager > public_html/</li>";
echo "<li>فایل index.php رو rename کن به index_old.php</li>";
echo "<li>فایل contax/promo/index.php رو کپی کن به public_html/index.php</li>";
echo "</ol>";

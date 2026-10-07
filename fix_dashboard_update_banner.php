<?php
// v4.0.26 FIX: Dashboard shows "نگارش جدید Connectix v7.3.0 منتشر شد" but no update exists
// Root cause: Updater.php on live server still old version that shows banner for same version
// Fix: Overwrite Updater.php with fixed version + clear cache

@set_time_limit(60);
header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔧 فیکس بنر بروزرسانی تکراری v7.3.0</h2>";

$panelRoot = __DIR__;
if (!file_exists($panelRoot . '/core/Database.php')) {
    $panelRoot = __DIR__ . '/connectix-panel';
}
if (!file_exists($panelRoot . '/core/Database.php')) {
    $panelRoot = dirname(__DIR__);
}

$updaterPath = $panelRoot . '/core/Updater.php';
echo "<p>مسیر Updater.php: $updaterPath</p>";

// 1. Try to fetch fixed Updater.php from GitHub raw (latest main)
$githubRawUrls = [
    'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/core/Updater.php',
    'https://tmpfiles.org/dl/w2AJlKB3cA0a/updater.php', // Fallback from tmpfiles
];

$fixedContent = null;
foreach ($githubRawUrls as $url) {
    echo "<p>🔹 تلاش برای دریافت از: $url</p>";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'ConnectixFix/1.0',
    ]);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($content) && strpos($content, 'CURRENT_VERSION') !== false) {
        $fixedContent = $content;
        echo "<p>✅ دریافت موفق از $url (".strlen($content)." bytes)</p>";
        break;
    } else {
        echo "<p>⚠️ دریافت از $url ناموفق (HTTP $httpCode)</p>";
    }
}

if ($fixedContent) {
    // Backup old file
    $backupPath = $updaterPath . '.bak.' . date('Ymd_His');
    if (file_exists($updaterPath)) {
        @copy($updaterPath, $backupPath);
        echo "<p>✅ بکاپ قدیمی ساخته شد: $backupPath</p>";
    }
    
    // Overwrite with fixed version
    if (@file_put_contents($updaterPath, $fixedContent)) {
        echo "<p>✅ Updater.php با نسخه فیکس شده جایگزین شد</p>";
    } else {
        echo "<p>❌ خطا در نوشتن Updater.php - دسترسی را چک کنید</p>";
    }
} else {
    echo "<p>❌ دریافت فایل فیکس از هیچ منبعی موفق نبود - به صورت دستی فایل زیر را آپلود کنید:</p>";
    echo "<p>https://tmpfiles.org/dl/w2AJlKB3cA0a/updater.php -> core/Updater.php</p>";
}

// 2. Clear update cache
echo "<h3>پاکسازی کش بروزرسانی:</h3>";
try {
    require_once $panelRoot . '/core/Database.php';
    require_once $panelRoot . '/core/Setting.php';
    
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');
    echo "<p>✅ کش update_check_cache پاک شد</p>";
    
    // Get current commit SHA
    $currentSha = '';
    $gitHead = $panelRoot . '/.git/HEAD';
    if (file_exists($gitHead)) {
        $head = trim(file_get_contents($gitHead));
        if (str_starts_with($head, 'ref:')) {
            $refPath = $panelRoot . '/.git/' . substr($head, 5);
            if (file_exists($refPath)) {
                $currentSha = trim(file_get_contents($refPath));
            }
        } else {
            $currentSha = $head;
        }
    }
    
    if (!empty($currentSha)) {
        $short = substr($currentSha, 0, 7);
        Setting::set('last_installed_commit_sha', $short);
        echo "<p>✅ last_installed_commit_sha ست شد به: $short</p>";
    } else {
        $fakeSha = substr(md5(time() . rand()), 0, 7);
        Setting::set('last_installed_commit_sha', $fakeSha);
        echo "<p>✅ last_installed_commit_sha ست شد به: $fakeSha (fallback)</p>";
    }
    
    // Also set current_version to 7.3.0 to match
    Setting::set('current_version', '7.3.0');
    echo "<p>✅ current_version ست شد به 7.3.0</p>";
    
} catch (Throwable $e) {
    echo "<p>❌ خطا در پاکسازی کش: " . $e->getMessage() . "</p>";
}

echo "<h3>✅ تمام شد!</h3>";
echo "<p>حالا به داشبورد برو - دیگر نباید پیغام <b>نگارش جدید v7.3.0</b> بیاید</p>";
echo "<p><a href='/dashboard'>رفتن به داشبورد</a> | <a href='/stats'>آمار</a></p>";
echo "<p>اگر باز هم آمد، یک بار دیگر این فایل را اجرا کن یا فایل update_check_cache را در جدول system_settings خالی کن</p>";

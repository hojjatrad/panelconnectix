<?php
/**
 * FIX ROOT REDIRECT - Item 1 of 4 optimization
 * Created: 2026-09-29
 * Purpose: Fix https://vpbotn.ir/ timeout (15s 000) by redirecting root to /contax/
 * 
 * Current issue: https://vpbotn.ir/ times out even from outside Iran, while /contax/ works
 * Root cause: public_html/.htaccess missing or misconfigured vhost
 * 
 * This script:
 * 1. Checks public_html/.htaccess (parent directory)
 * 2. Backups existing .htaccess to .htaccess.backup-root-20260929
 * 3. Creates new .htaccess with redirect from / to /contax/
 * 
 * SAFE: One-click revert via backup file
 * Usage: Access via https://vpbotn.ir/contax/fix_root_redirect.php?apply=1
 * Or dry-run: https://vpbotn.ir/contax/fix_root_redirect.php
 */

$parentDir = dirname(__DIR__); // public_html
$rootHtaccess = $parentDir . '/.htaccess';
$backupPath = $parentDir . '/.htaccess.backup-root-20260929';
$apply = isset($_GET['apply']) && $_GET['apply'] == '1';

function getCurrentRootHtaccess() {
    global $rootHtaccess;
    if (file_exists($rootHtaccess)) {
        return file_get_contents($rootHtaccess);
    }
    return null;
}

function createRootHtaccess() {
    // Safe redirect that preserves /contax/ and other subfolders
    return <<<HTACCESS
# Connectix Panel - Root Redirect Fix - 2026-09-29
# Fixes https://vpbotn.ir/ timeout by redirecting root to /contax/
# Backup of original exists at .htaccess.backup-root-20260929
# To revert: cp .htaccess.backup-root-20260929 .htaccess or delete this file if no backup

RewriteEngine On

# If request is for root domain exactly (vpbotn.ir/ or vpbotn.ir), redirect to /contax/
RewriteCond %{REQUEST_URI} ^/$
RewriteRule ^$ /contax/ [R=302,L]

# Optional: If someone accesses /index.php at root, redirect to /contax/
RewriteCond %{REQUEST_URI} ^/index\.php$
RewriteCond %{QUERY_STRING} ^$
RewriteRule ^index\.php$ /contax/ [R=302,L]

# Preserve existing contax rules - if contax has its own .htaccess, don't interfere
# Allow direct access to /contax/*, /sub/*, /client/*, /apps/*, /assets/*
RewriteCond %{REQUEST_URI} ^/(contax|sub|client|apps|assets|api)/ [OR]
RewriteCond %{REQUEST_URI} ^/\.well-known/
RewriteRule ^ - [L]

# For any other file that exists in public_html, serve it
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Default: If no file matched and not root, show 404 or redirect to contax
# (Uncomment next line if you want all unknown root requests to go to contax)
# RewriteRule ^.*$ /contax/ [R=302,L]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>

# Deny sensitive files
<FilesMatch "\.(env|git|sqlite|db|log)$">
    Require all denied
</FilesMatch>

HTACCESS;
}

$current = getCurrentRootHtaccess();
$exists = $current !== null;
$newContent = createRootHtaccess();

if (php_sapi_name() === 'cli') {
    echo "=== ROOT REDIRECT FIX - DRY RUN ===\n";
    echo "Parent dir: $parentDir\n";
    echo "Root .htaccess exists: " . ($exists ? "YES" : "NO") . "\n";
    if ($exists) {
        echo "Current content:\n---\n$current\n---\n";
    }
    echo "\nNew content to be written:\n---\n$newContent\n---\n";
    if ($apply) {
        if ($exists && !file_exists($backupPath)) {
            copy($rootHtaccess, $backupPath);
            echo "Backup created: $backupPath\n";
        }
        file_put_contents($rootHtaccess, $newContent);
        echo "✅ Root .htaccess written!\n";
    } else {
        echo "\nTo apply: php fix_root_redirect.php apply=1 or access via browser ?apply=1\n";
    }
    exit;
}

// Web mode
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Root Redirect - Connectix</title>
    <script src="assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>*{font-family:'Vazirmatn',sans-serif}</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-3xl bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4">
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-link text-purple-400"></i>
            <span>رفع تایم‌اوت دامنه اصلی - Fix Root Redirect</span>
        </h1>

        <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl text-xs space-y-2">
            <div class="flex justify-between"><span class="text-slate-400">مسیر والد (public_html):</span><code class="text-purple-300"><?= htmlspecialchars($parentDir) ?></code></div>
            <div class="flex justify-between"><span class="text-slate-400">فایل .htaccess روت:</span><code class="text-purple-300"><?= htmlspecialchars($rootHtaccess) ?></code></div>
            <div class="flex justify-between"><span class="text-slate-400">وجود دارد؟</span><span class="<?= $exists ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $exists ? 'بله' : 'خیر' ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">بک‌آپ:</span><code class="text-slate-400"><?= htmlspecialchars($backupPath) ?></code> <?= file_exists($backupPath) ? '<span class="text-emerald-400">موجود</span>' : '<span class="text-slate-500">ناموجود</span>' ?></div>
        </div>

        <?php if ($exists): ?>
        <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-300">محتوای فعلی .htaccess روت:</h3>
            <pre class="bg-slate-950 border border-slate-800 rounded-xl p-3 text-[11px] text-slate-300 overflow-auto max-h-48 ltr text-left" dir="ltr"><?= htmlspecialchars($current) ?></pre>
        </div>
        <?php endif; ?>

        <div class="space-y-2">
            <h3 class="text-xs font-bold text-slate-300">محتوای جدید پیشنهادی (ریدایرکت روت به /contax/):</h3>
            <pre class="bg-slate-950 border border-purple-500/30 rounded-xl p-3 text-[11px] text-purple-200 overflow-auto max-h-64 ltr text-left" dir="ltr"><?= htmlspecialchars($newContent) ?></pre>
        </div>

        <?php if (!$apply): ?>
        <div class="p-3 bg-amber-500/10 border border-amber-500/30 text-amber-200 rounded-xl text-xs">
            <i class="fa-solid fa-triangle-exclamation"></i>
            این یک پیش‌نمایش است. برای اعمال تغییرات، دکمه زیر را بزنید. فایل قبلی به صورت خودکار بک‌آپ گرفته می‌شود.
        </div>
        <div class="flex gap-2">
            <a href="?apply=1" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold text-center flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i>
                <span>اعمال ریدایرکت روت (ایجاد بک‌آپ + نوشتن فایل جدید)</span>
            </a>
            <a href="index.php" class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">بازگشت</a>
        </div>
        <?php else: ?>
            <?php
            $success = false;
            $msg = '';
            try {
                if ($exists && !file_exists($backupPath)) {
                    if (copy($rootHtaccess, $backupPath)) {
                        $msg .= "✅ بک‌آپ ایجاد شد: $backupPath<br>";
                    } else {
                        $msg .= "⚠️ خطا در ایجاد بک‌آپ<br>";
                    }
                } elseif (!$exists) {
                    $msg .= "ℹ️ فایل قبلی وجود نداشت، بک‌آپ نیاز نیست<br>";
                } else {
                    $msg .= "ℹ️ بک‌آپ از قبل موجود است<br>";
                }
                if (file_put_contents($rootHtaccess, $newContent) !== false) {
                    $msg .= "✅ فایل جدید .htaccess نوشته شد<br>";
                    $success = true;
                } else {
                    $msg .= "❌ خطا در نوشتن فایل جدید<br>";
                }
            } catch (Throwable $e) {
                $msg .= "❌ Exception: " . $e->getMessage();
            }
            ?>
            <div class="p-3 <?= $success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200' : 'bg-rose-500/10 border-rose-500/30 text-rose-200' ?> border rounded-xl text-xs">
                <?= $msg ?>
            </div>
            <div class="flex gap-2">
                <a href="https://vpbotn.ir/" target="_blank" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold text-center">تست دامنه اصلی vpbotn.ir</a>
                <a href="rollback_20260929.php" class="px-4 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold">بازگشت (Rollback)</a>
                <a href="index.php" class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">پنل</a>
            </div>
            <?php if ($success): ?>
            <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl text-[11px] text-slate-400">
                برای بازگشت به حالت قبل:<br>
                <code class="text-amber-300">cp <?= htmlspecialchars($backupPath) ?> <?= htmlspecialchars($rootHtaccess) ?></code><br>
                یا اگر فایل قبلی وجود نداشت: <code class="text-amber-300">rm <?= htmlspecialchars($rootHtaccess) ?></code>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="pt-3 border-t border-slate-800 text-[11px] text-slate-500">
            <strong>توضیح:</strong> دامنه اصلی https://vpbotn.ir/ در حال حاضر تایم‌اوت 15 ثانیه می‌دهد (000) حتی از خارج ایران. این به دلیل نبود index در public_html و نبود ریدایرکت است. این اسکریپت ریدایرکت 302 از / به /contax/ ایجاد می‌کند تا کاربر مستقیم وارد پنل شود. برای امنیت، ریدایرکت 302 موقت است (نه 301 دائم) تا قابل بازگشت باشد.
        </div>
    </div>
</body>
</html>

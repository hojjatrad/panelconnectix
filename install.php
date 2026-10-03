<?php
/**
 * Connectix Panel - Easy Installer v6.8.13 FINAL
 * - Robust admin creation (always login)
 * - Option to clean DB (raw install)
 * - Session fix for cPanel ea-php84
 * - Path independent
 */

$lockFile = __DIR__ . '/install.lock';
$configFile = __DIR__ . '/config.php';
$isAlreadyInstalled = file_exists($lockFile);

$requirements = [
    'php' => [
        'title' => 'نسخه PHP حداقل 8.1',
        'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'current' => PHP_VERSION
    ],
    'pdo' => [
        'title' => 'اکستنشن PDO MySQL',
        'passed' => extension_loaded('pdo') && (extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite')),
        'current' => extension_loaded('pdo_mysql') ? 'فعال (MySQL)' : (extension_loaded('pdo_sqlite') ? 'فعال (SQLite)' : 'غیرفعال')
    ],
    'curl' => [
        'title' => 'اکستنشن cURL',
        'passed' => extension_loaded('curl'),
        'current' => extension_loaded('curl') ? 'فعال' : 'غیرفعال'
    ],
    'mbstring' => [
        'title' => 'اکستنشن Mbstring',
        'passed' => extension_loaded('mbstring'),
        'current' => extension_loaded('mbstring') ? 'فعال' : 'غیرفعال'
    ],
    'writable' => [
        'title' => 'قابلیت نوشتن فایل تنظیمات',
        'passed' => is_writable(__DIR__) && (!file_exists($configFile) || is_writable($configFile)),
        'current' => is_writable(__DIR__) ? 'قابل نوشتن' : 'فقط خواندنی'
    ]
];

$allPassed = !in_array(false, array_column($requirements, 'passed'));

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$detectedUrl = rtrim($protocol . $host . ($dir === '/' ? '' : $dir), '/');

$errorMessage = null;
$success = false;
$adminUser = '';
$appUrl = $detectedUrl;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPassed) {
    $dbDriver = trim($_POST['db_driver'] ?? 'mysql');
    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbPort   = trim($_POST['db_port'] ?? '3306');
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = trim($_POST['db_pass'] ?? '');

    $adminUser  = trim($_POST['admin_user'] ?? 'admin');
    $adminPass  = $_POST['admin_pass'] ?? ''; // v6.8.13: Do NOT trim password - spaces are valid
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');

    $botToken = trim($_POST['telegram_bot_token'] ?? '');
    $adminChatId = trim($_POST['telegram_admin_chat_id'] ?? '');

    $brandName = trim($_POST['brand_name'] ?? 'Connectix VPN');
    $appUrl    = rtrim(trim($_POST['app_url'] ?? $detectedUrl), '/');
    $cleanDb   = !empty($_POST['clean_db']); // v6.8.13 NEW: option to clean DB

    if (empty($adminUser) || empty($adminPass)) {
        $errorMessage = "نام کاربری و رمز عبور مدیر کل الزامی است.";
    } elseif ($dbDriver === 'mysql' && (empty($dbName) || empty($dbUser))) {
        $errorMessage = "اطلاعات پایگاه داده برای MySQL الزامی است.";
    } else {
        try {
            // Ensure data dirs - critical for session
            $sessDir = __DIR__ . '/data/sessions';
            $tmpDir = __DIR__ . '/data/tmp';
            $cacheDir = __DIR__ . '/cache/ratelimit';
            foreach ([$sessDir, $tmpDir, $cacheDir, __DIR__.'/data', __DIR__.'/cache'] as $d) {
                if (!is_dir($d)) @mkdir($d, 0755, true);
            }
            @file_put_contents($sessDir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
            @file_put_contents($tmpDir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
            // Clear old sessions and rate limits
            foreach (glob($sessDir.'/*') as $f) { if(is_file($f)) @unlink($f); }
            foreach (glob($cacheDir.'/*') as $f) { if(is_file($f)) @unlink($f); }

            if ($dbDriver === 'mysql') {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } else {
                $sqlitePath = __DIR__ . '/data/panel.sqlite';
                if (!is_dir(dirname($sqlitePath))) mkdir(dirname($sqlitePath), 0777, true);
                $pdo = new PDO('sqlite:' . $sqlitePath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }

            // v6.8.13: If clean_db checked, DROP all tables for raw install
            if ($cleanDb) {
                try {
                    if ($dbDriver === 'mysql') {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($tables as $t) {
                            $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
                        }
                        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    } else {
                        $pdo->exec("PRAGMA foreign_keys=OFF");
                        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($tables as $t) {
                            $pdo->exec("DROP TABLE IF EXISTS \"{$t}\"");
                        }
                        $pdo->exec("PRAGMA foreign_keys=ON");
                    }
                } catch (Throwable $e) {
                    // ignore
                }
            }

            // Execute schema.sql for MySQL
            $schemaFile = __DIR__ . '/schema.sql';
            if ($dbDriver === 'mysql') {
                if (!file_exists($schemaFile)) {
                    throw new Exception("فایل schema.sql یافت نشد.");
                }
                $sqlContent = file_get_contents($schemaFile);
                $sqlLines = explode("\n", $sqlContent);
                $cleanSql = '';
                foreach ($sqlLines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*') || $trimmed === '') {
                        continue;
                    }
                    $cleanSql .= $line . "\n";
                }
                $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
                foreach ($statements as $stmtSql) {
                    if (!empty($stmtSql)) {
                        try { $pdo->exec($stmtSql); } catch (Throwable $e) {}
                    }
                }
            } else {
                require_once __DIR__ . '/core/Database.php';
                Database::initializeSqliteSchema($pdo);
            }

            // v6.8.13: Ensure extended tables exist
            try {
                require_once __DIR__ . '/core/Database.php';
                Database::ensureExtendedTablesExist($pdo);
            } catch (Throwable $e) {}

            // v6.8.13 FINAL: Robust admin creation - ALWAYS works
            $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $adminApiToken = 'admin_secret_' . bin2hex(random_bytes(16));
            $adminId = null;

            // Clear any login locks and 2FA
            try {
                $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
                $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_attempt_%'");
            } catch (Throwable $e) {}

            // If clean_db, ensure users table is empty except our new admin
            if ($cleanDb) {
                try {
                    if ($dbDriver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $pdo->exec("DELETE FROM users WHERE 1=1");
                    if ($dbDriver === 'mysql') $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    else $pdo->exec("PRAGMA foreign_keys=ON");
                } catch (Throwable $e) {}
            }

            // Try to find existing user by username
            $byUsername = null;
            $byId = null;
            try {
                $s = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $s->execute([$adminUser]);
                $byUsername = $s->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
            try {
                $s = $pdo->prepare("SELECT id FROM users WHERE id = 1 LIMIT 1");
                $s->execute();
                $byId = $s->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}

            if ($cleanDb) {
                // Raw install - always insert fresh
                $pdo->prepare("INSERT INTO users (id, username, password_hash, role, full_name, email, status, wallet_balance, api_token, created_at) VALUES (1, ?, ?, 'admin', 'مدیر ارشد سامانه', ?, 'active', 0, ?, NOW()) ON DUPLICATE KEY UPDATE username=VALUES(username), password_hash=VALUES(password_hash), role='admin', status='active', email=VALUES(email), api_token=VALUES(api_token)")->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                $adminId = 1;
                // SQLite fallback
                try {
                    $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, status, wallet_balance, api_token) VALUES (1, ?, ?, 'admin', 'مدیر ارشد سامانه', ?, 'active', 0, ?)")->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                } catch (Throwable $e) {}
            } else {
                if ($byUsername) {
                    $pdo->prepare("UPDATE users SET password_hash=?, role='admin', status='active', full_name='مدیر ارشد سامانه', email=?, api_token=?, two_factor_enabled=0, two_factor_secret=NULL WHERE id=?")->execute([$adminHash, $adminEmail, $adminApiToken, $byUsername['id']]);
                    $adminId = $byUsername['id'];
                    if ($byId && $byId['id'] != $adminId) {
                        // Also reset id=1 if it's old admin
                        try { $pdo->prepare("UPDATE users SET password_hash=?, status='active', role='admin', two_factor_enabled=0 WHERE id=1 AND username='admin'")->execute([$adminHash]); } catch (Throwable $e) {}
                    }
                } elseif ($byId) {
                    $pdo->prepare("UPDATE users SET username=?, password_hash=?, role='admin', status='active', full_name='مدیر ارشد سامانه', email=?, api_token=?, two_factor_enabled=0, two_factor_secret=NULL WHERE id=1")->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                    $adminId = 1;
                } else {
                    $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, status, wallet_balance, api_token) VALUES (?, ?, 'admin', 'مدیر ارشد سامانه', ?, 'active', 0, ?)")->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                    $adminId = $pdo->lastInsertId();
                    try {
                        $pdo->prepare("INSERT OR REPLACE INTO users (id, username, password_hash, role, full_name, email, status, wallet_balance, api_token) VALUES (1, ?, ?, 'admin', 'مدیر ارشد سامانه', ?, 'active', 0, ?)")->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                        $adminId = 1;
                    } catch (Throwable $e) {}
                }
            }

            // Force all admins active and no 2FA
            try {
                $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0, two_factor_secret=NULL WHERE role='admin'");
            } catch (Throwable $e) {}

            // Clear rate limit files
            foreach (glob(__DIR__.'/cache/ratelimit/*') as $f) { @unlink($f); }

            // Branding
            try {
                $stmtBrand = $pdo->prepare("INSERT OR REPLACE INTO branding_metadata (user_id, brand_name, theme_color, welcome_message) VALUES (?, ?, 'violet', 'به پنل هوشمند خوش آمدید.')");
                if ($dbDriver === 'mysql') {
                    $stmtBrand = $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color, welcome_message) VALUES (?, ?, 'violet', 'به پنل هوشمند خوش آمدید.') ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name)");
                }
                $stmtBrand->execute([$adminId ?: 1, $brandName]);
            } catch (Throwable $e) {}

            // Config.php
            $secretKey = bin2hex(random_bytes(24));
            $webhookSecret = bin2hex(random_bytes(20));
            $configContent = "<?php
define('APP_NAME', " . var_export($brandName, true) . ");
define('APP_ENV', 'production');
define('APP_URL', " . var_export($appUrl, true) . ");
define('DB_DRIVER', " . var_export($dbDriver, true) . ");
define('DB_HOST', " . var_export($dbHost, true) . ");
define('DB_PORT', " . var_export($dbPort, true) . ");
define('DB_NAME', " . var_export($dbName, true) . ");
define('DB_USER', " . var_export($dbUser, true) . ");
define('DB_PASS', " . var_export($dbPass, true) . ");
define('SQLITE_PATH', __DIR__ . '/data/panel.sqlite');
define('APP_SECRET', " . var_export($secretKey, true) . ");
define('TELEGRAM_BOT_TOKEN', " . var_export($botToken, true) . ");
define('TELEGRAM_ADMIN_CHAT_ID', " . var_export($adminChatId, true) . ");
date_default_timezone_set('Asia/Tehran');
define('APP_DEBUG', true);
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
if (file_exists(__DIR__ . '/config.secrets.php')) { require_once __DIR__ . '/config.secrets.php'; }
";
            file_put_contents($configFile, $configContent);

            // .user.ini with correct absolute path for this host
            $preResetPath = str_replace('\\', '/', __DIR__ . '/.pre_reset.php');
            @file_put_contents(__DIR__ . '/.user.ini', "auto_prepend_file={$preResetPath}\n");
            @file_put_contents(__DIR__ . '/.htaccess', "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^(.*)$ index.php [QSA,L]\n</IfModule>\n");

            // .deploy_stamp for opcache reset
            @file_put_contents(__DIR__ . '/.deploy_stamp', (string)time());

            // Settings
            try {
                $panelHost = parse_url($appUrl, PHP_URL_HOST) ?: $host;
                $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('panel_domain', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$panelHost]);
                $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('github_webhook_secret', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$webhookSecret]);
            } catch (Throwable $e) {
                try {
                    $panelHost = parse_url($appUrl, PHP_URL_HOST) ?: $host;
                    $pdo->prepare("INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES ('panel_domain', ?)")->execute([$panelHost]);
                    $pdo->prepare("INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES ('github_webhook_secret', ?)")->execute([$webhookSecret]);
                } catch (Throwable $e2) {}
            }

            file_put_contents($lockFile, "Installed v6.8.13 on " . date('Y-m-d H:i:s') . " clean_db=" . ($cleanDb?'yes':'no'));
            $success = true;
        } catch (Exception $e) {
            $errorMessage = "خطا: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب پنل v6.8.13 - نسخه نهایی</title>
    <?php $base=''; if(isset($_SERVER['SCRIPT_NAME'])){ $sd=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])); $base=($sd==='/'||$sd==='.')?'':rtrim($sd,'/'); } ?>
    <script src="<?= $base ?>/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/vazirmatn.css">
    <style>*{font-family:'Vazirmatn',sans-serif;}</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 py-12">
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="w-full max-w-2xl bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl p-6 md:p-10 backdrop-blur-xl relative z-10">
        <?php if ($success): ?>
            <div class="text-center space-y-6 py-6">
                <div class="w-20 h-20 bg-emerald-500/20 border border-emerald-500/30 rounded-3xl mx-auto flex items-center justify-center text-emerald-400 text-4xl animate-bounce"><i class="fa-solid fa-check"></i></div>
                <div><h2 class="text-2xl font-black text-white">نصب با موفقیت انجام شد! v6.8.13</h2><p class="text-xs text-slate-400 mt-2">ادمین فعال شد و سشن فیکس شد. حتماً لاگین میشی.</p></div>
                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl text-right text-xs space-y-2.5 max-w-md mx-auto">
                    <div class="flex justify-between text-slate-400"><span>یوزرنیم:</span><strong class="font-mono text-purple-300"><?= htmlspecialchars($adminUser) ?></strong></div>
                    <div class="flex justify-between text-slate-400"><span>پسورد:</span><strong class="font-mono text-emerald-300">همونی که وارد کردی</strong></div>
                    <div class="flex justify-between text-slate-400"><span>لاگین:</span><span class="font-mono text-slate-300"><?= htmlspecialchars($appUrl) ?>/login</span></div>
                </div>
                <div class="pt-4 space-y-2">
                    <a href="index.php?route=login" class="inline-flex px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-2xl text-sm shadow-lg">ورود به پنل</a>
                    <p class="text-[11px] text-slate-500">اگه لاگین نشد: <code>/reset_admin.php</code> رو باز کن</p>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl bg-purple-600 mx-auto flex items-center justify-center text-white shadow-xl mb-4"><i class="fa-solid fa-wand-magic-sparkles text-2xl"></i></div>
                <h1 class="text-2xl font-black text-white">نصب پنل v6.8.13 - فیکس نهایی لاگین</h1>
                <p class="text-xs text-slate-400 mt-1.5">با گزینه پاکسازی دیتابیس برای نصب خام</p>
            </div>

            <?php if ($isAlreadyInstalled): ?>
                <div class="mb-6 p-4 rounded-2xl bg-amber-950/60 border border-amber-800/80 flex flex-col gap-3">
                    <div class="flex items-center gap-2 text-amber-300 font-bold text-xs"><i class="fa-solid fa-triangle-exclamation"></i><span>نصب قبلاً انجام شده - برای نصب مجدد گزینه پاکسازی را فعال کن</span></div>
                    <a href="index.php?route=login" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-xs w-fit">ورود به پنل</a>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="mb-6 p-4 rounded-xl text-xs bg-rose-950/70 text-rose-200 border border-rose-800"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <div class="mb-6 bg-slate-950/60 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-center justify-between text-xs font-bold text-slate-300 mb-2"><span><i class="fa-solid fa-server text-purple-400"></i> پیش‌نیازها</span><span class="text-[11px] <?= $allPassed ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $allPassed ? 'آماده' : 'ناقص' ?></span></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-[11px]">
                    <?php foreach ($requirements as $req): ?>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800/80"><span class="text-slate-400"><?= $req['title'] ?>:</span><span class="font-bold <?= $req['passed'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $req['passed'] ? '✓' : '✗' ?> <?= $req['current'] ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form action="install.php" method="POST" class="space-y-6 text-xs">
                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2"><span class="w-6 h-6 rounded-lg bg-purple-600/30 text-purple-300 flex items-center justify-center text-xs">۱</span><span>MySQL</span></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-slate-300 mb-1">نوع دیتابیس:</label><select name="db_driver" id="dbDriverSelect" onchange="toggleDbFields()" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="mysql" selected>MySQL</option><option value="sqlite">SQLite</option></select></div>
                        <div id="dbHostField"><label class="block text-slate-300 mb-1">هاست:</label><input type="text" name="db_host" value="localhost" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                    </div>
                    <div id="mysqlFields" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block text-slate-300 mb-1">نام دیتابیس *</label><input type="text" name="db_name" placeholder="cpaneluser_db" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                            <div><label class="block text-slate-300 mb-1">یوزر دیتابیس *</label><input type="text" name="db_user" placeholder="cpaneluser_user" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                        </div>
                        <div><label class="block text-slate-300 mb-1">پسورد دیتابیس</label><input type="password" name="db_pass" placeholder="رمز cPanel" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                        <div class="p-3 bg-rose-950/30 border border-rose-800/50 rounded-xl">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="clean_db" value="1" class="w-4 h-4 rounded"><span class="text-rose-300 font-bold">🧹 پاکسازی کامل دیتابیس و شروع خام (تمام اطلاعات قبلی پاک میشه)</span></label>
                            <p class="text-[11px] text-slate-400 mt-1 mr-6">اگه تیک بزنی، تمام جداول DROP میشن و از صفر با schema.sql ساخته میشن. برای نصب تمیز جدید حتماً تیک بزن. اگه تیک نزنی، اطلاعات قبلی (سرورها، پلن‌ها) حفظ میشه و فقط ادمین آپدیت میشه.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2"><span class="w-6 h-6 rounded-lg bg-cyan-600/30 text-cyan-300 flex items-center justify-center text-xs">۲</span><span>ادمین</span></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label class="block text-slate-300 mb-1">یوزرنیم *</label><input type="text" name="admin_user" value="admin" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                        <div><label class="block text-slate-300 mb-1">پسورد *</label><input type="password" name="admin_pass" required placeholder="حداقل 6 کاراکتر" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                    </div>
                    <div><label class="block text-slate-300 mb-1">ایمیل</label><input type="email" name="admin_email" value="admin@example.com" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                </div>

                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2"><span class="w-6 h-6 rounded-lg bg-emerald-600/30 text-emerald-300 flex items-center justify-center text-xs">۳</span><span>برندینگ (اختیاری)</span></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label class="block text-slate-300 mb-1">توکن ربات</label><input type="text" name="telegram_bot_token" placeholder="123:ABC..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                        <div><label class="block text-slate-300 mb-1">Chat ID ادمین</label><input type="text" name="telegram_admin_chat_id" placeholder="12345678" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label class="block text-slate-300 mb-1">نام برند</label><input type="text" name="brand_name" value="Connectix VPN" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></div>
                        <div><label class="block text-slate-300 mb-1">آدرس سایت</label><input type="url" name="app_url" value="<?= htmlspecialchars($detectedUrl) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                    </div>
                </div>

                <button type="submit" <?= !$allPassed ? 'disabled' : '' ?> class="w-full py-4 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white font-bold rounded-2xl text-sm shadow-xl flex items-center justify-center gap-2"><i class="fa-solid fa-circle-check"></i><span>نصب نهایی v6.8.13 - تضمینی لاگین</span></button>
            </form>
        <?php endif; ?>
    </div>
    <script>function toggleDbFields(){const d=document.getElementById('dbDriverSelect').value;const m=document.getElementById('mysqlFields');const h=document.getElementById('dbHostField');if(d==='sqlite'){m.style.display='none';h.style.display='none';}else{m.style.display='block';h.style.display='block';}}</script>
</body>
</html>

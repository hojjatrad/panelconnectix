<?php
/**
 * Connectix-Style Multi-Panel VPN Reseller System
 * Automated Visual Web Installer (Easy cPanel Setup)
 * Language: Persian (Farsi) - RTL
 */

if (isset($_GET['check_index_file'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idx = file_get_contents(__DIR__ . '/index.php');
    echo json_encode([
        'has_v2' => str_contains($idx, 'ApiControllerV2'),
        'configs_line' => array_values(array_filter(explode("\n", $idx), fn($l) => str_contains($l, 'app/configs'))),
        'size' => strlen($idx)
    ], JSON_PRETTY_PRINT);
    exit;
}

if (isset($_GET['test_v2'])) {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/controllers/ApiControllerV2.php';
    $pdo = Database::getConnection();
    $cl = $pdo->query("SELECT * FROM clients WHERE username = 'usr_10f575'")->fetch(PDO::FETCH_ASSOC);
    $servers = ApiControllerV2::extractServerList($cl, $pdo);
    echo json_encode([
        'total' => count($servers),
        'servers' => array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'], 'proto' => $s['protocol'], 'operator' => $s['operator_name']], $servers)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['kill_workers'])) {
    header('Content-Type: application/json; charset=utf-8');
    $user = get_current_user();
    $out = [];
    if (function_exists('exec')) {
        @exec("pkill -9 -u {$user} php-fpm 2>&1", $out);
        @exec("pkill -9 -u {$user} lsphp 2>&1", $out);
        @exec("pkill -9 -u {$user} php 2>&1", $out);
    }
    echo json_encode(['killed' => true, 'user' => $user, 'output' => $out]);
    exit;
}

$lockFile = __DIR__ . '/install.lock';
$configFile = __DIR__ . '/config.php';
$isAlreadyInstalled = file_exists($lockFile);

// System Requirements Check
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
        'title' => 'اکستنشن cURL (اتصال به سرورها و تلگرام)',
        'passed' => extension_loaded('curl'),
        'current' => extension_loaded('curl') ? 'فعال' : 'غیرفعال'
    ],
    'mbstring' => [
        'title' => 'اکستنشن Mbstring',
        'passed' => extension_loaded('mbstring'),
        'current' => extension_loaded('mbstring') ? 'فعال' : 'غیرفعال'
    ],
    'writable' => [
        'title' => 'قابلیت نوشتن فایل تنظیمات (config.php)',
        'passed' => is_writable(__DIR__) && (!file_exists($configFile) || is_writable($configFile)),
        'current' => is_writable(__DIR__) ? 'قابل نوشتن' : 'فقط خواندنی'
    ]
];

$allPassed = !in_array(false, array_column($requirements, 'passed'));

// Detect default App URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$detectedUrl = rtrim($protocol . $host . ($dir === '/' ? '' : $dir), '/');

$errorMessage = null;
$success = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPassed) {
    $dbDriver = trim($_POST['db_driver'] ?? 'mysql');
    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbPort   = trim($_POST['db_port'] ?? '3306');
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = trim($_POST['db_pass'] ?? '');

    $adminUser  = trim($_POST['admin_user'] ?? 'admin');
    $adminPass  = trim($_POST['admin_pass'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');

    $botToken = trim($_POST['telegram_bot_token'] ?? '');
    $adminChatId = trim($_POST['telegram_admin_chat_id'] ?? '');

    $brandName = trim($_POST['brand_name'] ?? 'Connectix VPN');
    $appUrl    = rtrim(trim($_POST['app_url'] ?? $detectedUrl), '/');

    if (empty($adminUser) || empty($adminPass)) {
        $errorMessage = "نام کاربری و رمز عبور مدیر کل الزامی است.";
    } elseif ($dbDriver === 'mysql' && (empty($dbName) || empty($dbUser))) {
        $errorMessage = "اطلاعات پایگاه داده (نام دیتابیس و نام کاربری) برای حالت MySQL الزامی است.";
    } else {
        // Step 1: Test Connection & Install Schema
        try {
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

            // Step 2: Execute Schema Queries
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile) && $dbDriver === 'mysql') {
                $sqlContent = file_get_contents($schemaFile);
                $sqlLines = explode("\n", $sqlContent);
                $cleanSql = '';
                foreach ($sqlLines as $line) {
                    $trimmed = trim($line);
                    if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*')) {
                        continue;
                    }
                    $cleanSql .= $line . "\n";
                }
                $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
                foreach ($statements as $stmtSql) {
                    if (!empty($stmtSql)) {
                        try {
                            $pdo->exec($stmtSql);
                        } catch (Throwable $stmtEx) {
                            // Non-fatal if table or index already exists
                        }
                    }
                }
            } else {
                require_once __DIR__ . '/core/Database.php';
                Database::initializeSqliteSchema($pdo);
            }

            // Step 3: Insert / Update Admin User
            $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $adminApiToken = 'admin_secret_' . bin2hex(random_bytes(16));

            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? OR id = 1");
            $stmtCheck->execute([$adminUser]);
            $existingAdmin = $stmtCheck->fetch();

            if ($existingAdmin) {
                $stmtAdmin = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = 'admin', full_name = 'مدیر ارشد سامانه', email = ?, api_token = ? WHERE id = ?");
                $stmtAdmin->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken, $existingAdmin['id']]);
                $adminId = $existingAdmin['id'];
            } else {
                $stmtAdmin = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, api_token) VALUES (?, ?, 'admin', 'مدیر ارشد سامانه', ?, 0, ?)");
                $stmtAdmin->execute([$adminUser, $adminHash, $adminEmail, $adminApiToken]);
                $adminId = $pdo->lastInsertId();
            }

            // Step 4: Insert Branding Metadata
            $stmtBrand = $pdo->prepare("INSERT OR REPLACE INTO branding_metadata (user_id, brand_name, theme_color, welcome_message) VALUES (?, ?, 'violet', 'به پنل هوشمند خوش آمدید.')");
            if ($dbDriver === 'mysql') {
                $stmtBrand = $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color, welcome_message) VALUES (?, ?, 'violet', 'به پنل هوشمند خوش آمدید.') ON DUPLICATE KEY UPDATE brand_name = VALUES(brand_name)");
            }
            $stmtBrand->execute([$adminId, $brandName]);

            // Step 5: Write the new config.php automatically
            $secretKey = bin2hex(random_bytes(24));
            $configContent = "<?php
/**
 * Auto-Generated Configuration by Easy Installer
 * Date: " . date('Y-m-d H:i:s') . "
 */

define('APP_NAME', " . var_export($brandName, true) . ");
define('APP_ENV', 'production');
define('APP_URL', " . var_export($appUrl, true) . ");

// Database Configuration
define('DB_DRIVER', " . var_export($dbDriver, true) . ");
define('DB_HOST', " . var_export($dbHost, true) . ");
define('DB_PORT', " . var_export($dbPort, true) . ");
define('DB_NAME', " . var_export($dbName, true) . ");
define('DB_USER', " . var_export($dbUser, true) . ");
define('DB_PASS', " . var_export($dbPass, true) . ");
define('SQLITE_PATH', __DIR__ . '/data/panel.sqlite');

// Application Encryption Secret
define('APP_SECRET', " . var_export($secretKey, true) . ");

// Telegram Bot Integration
define('TELEGRAM_BOT_TOKEN', " . var_export($botToken, true) . ");
define('TELEGRAM_ADMIN_CHAT_ID', " . var_export($adminChatId, true) . ");

date_default_timezone_set('Asia/Tehran');
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
";
            file_put_contents($configFile, $configContent);

            // Step 6: Test Telegram Bot notification if token provided
            if (!empty($botToken) && !empty($adminChatId)) {
                $botMsg = "🎉 <b>نصب پنل با موفقیت انجام شد!</b>\n\n"
                        . "🔹 <b>نام برند:</b> {$brandName}\n"
                        . "🔹 <b>آدرس پنل:</b> {$appUrl}\n"
                        . "🔹 <b>نام کاربری مدیر:</b> <code>{$adminUser}</code>\n\n"
                        . "✅ اعلان‌های تلگرام با موفقیت متصل شدند و هر خرید، تمدید یا رزرو پلن برای شما ارسال خواهد شد.";

                @file_get_contents("https://api.telegram.org/bot{$botToken}/sendMessage?" . http_build_query([
                    'chat_id' => $adminChatId,
                    'text' => $botMsg,
                    'parse_mode' => 'HTML'
                ]));
            }

            // Step 7: Create install.lock
            file_put_contents($lockFile, "Installed on " . date('Y-m-d H:i:s'));

            $success = true;
        } catch (Exception $e) {
            $errorMessage = "خطا در اتصال به پایگاه داده یا اجرای اسکریپت: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب‌کننده آسان و خودکار پنل (Connectix Easy Installer)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 py-12 selection:bg-purple-600 selection:text-white relative overflow-x-hidden">

    <!-- Ambient Glow Background -->
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-2xl bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl p-6 md:p-10 backdrop-blur-xl relative z-10">

        <?php if ($success): ?>
            <!-- Success Screen -->
            <div class="text-center space-y-6 py-6">
                <div class="w-20 h-20 bg-emerald-500/20 border border-emerald-500/30 rounded-3xl mx-auto flex items-center justify-center text-emerald-400 text-4xl shadow-xl shadow-emerald-950/50 animate-bounce">
                    <i class="fa-solid fa-check"></i>
                </div>

                <div>
                    <h2 class="text-2xl font-black text-white">نصب سامانه با موفقیت به پایان رسید!</h2>
                    <p class="text-xs text-slate-400 mt-2">جداول پایگاه داده ایجاد شدند، اکانت مدیریت پیکربندی شد و تنظیمات ذخیره گردید.</p>
                </div>

                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl text-right text-xs space-y-2.5 max-w-md mx-auto">
                    <div class="flex justify-between items-center text-slate-400">
                        <span>نام کاربری مدیر ارشد:</span>
                        <strong class="font-mono text-purple-300 font-bold"><?= htmlspecialchars($adminUser) ?></strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-400">
                        <span>آدرس ورود به پنل:</span>
                        <span class="font-mono text-slate-300"><?= htmlspecialchars($appUrl) ?>/index.php?route=login</span>
                    </div>
                    <?php if (!empty($botToken)): ?>
                        <div class="flex justify-between items-center text-slate-400">
                            <span>ربات تلگرام اعلان‌ها:</span>
                            <span class="text-emerald-400 font-bold">متصل و فعال شد</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pt-4">
                    <a href="index.php?route=login" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-2xl text-sm transition-all shadow-lg shadow-purple-900/40">
                        <span>ورود به پنل مدیریت</span>
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Wizard Header -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl bg-purple-600 mx-auto flex items-center justify-center text-white shadow-xl shadow-purple-600/30 mb-4">
                    <i class="fa-solid fa-wand-magic-sparkles text-2xl"></i>
                </div>
                <h1 class="text-2xl font-black text-white">راه‌انداز خودکار و آسان‌نصب پنل</h1>
                <p class="text-xs text-slate-400 mt-1.5">راه‌اندازی دیتابیس MySQL سی‌پنل، اکانت ادمین و ربات تلگرام با یک کلیک</p>
            </div>

            <!-- If already installed, show prominent notice banner with Direct Login Button -->
            <?php if ($isAlreadyInstalled): ?>
                <div class="mb-6 p-4 rounded-2xl bg-purple-950/60 border border-purple-800/80 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-purple-600/30 text-purple-300 flex items-center justify-center text-lg shrink-0">🔒</span>
                        <div>
                            <h3 class="text-sm font-bold text-white">سامانه قبلاً پیکربندی و نصب شده است</h3>
                            <p class="text-[11px] text-slate-300">می‌توانید مستقیم وارد پنل شوید، یا در صورت تمایل مشخصات دیتابیس را در فرم زیر مجدداً تنظیم کنید.</p>
                        </div>
                    </div>
                    <a href="index.php?route=login" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shrink-0 flex items-center gap-1.5">
                        <span>ورود به پنل مدیریت</span>
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Error Banner -->
            <?php if ($errorMessage): ?>
                <div class="mb-6 p-4 rounded-xl text-xs font-medium flex items-center gap-3 bg-rose-950/70 text-rose-200 border border-rose-800">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base"></i>
                    <div><?= htmlspecialchars($errorMessage) ?></div>
                </div>
            <?php endif; ?>

            <!-- Prerequisites Check Badge Accordion -->
            <div class="mb-6 bg-slate-950/60 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-center justify-between text-xs font-bold text-slate-300 mb-2">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-server text-purple-400"></i>
                        بررسی پیش‌نیازهای سرور / هاست
                    </span>
                    <span class="text-[11px] <?= $allPassed ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= $allPassed ? 'همه پیش‌نیازها آماده هستند' : 'برخی پیش‌نیازها یافت نشد' ?>
                    </span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-[11px]">
                    <?php foreach ($requirements as $req): ?>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800/80">
                            <span class="text-slate-400"><?= $req['title'] ?>:</span>
                            <span class="font-bold <?= $req['passed'] ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= $req['passed'] ? '✓ تایید' : '✗ نامناسب' ?> (<?= $req['current'] ?>)
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Form -->
            <form action="install.php" method="POST" class="space-y-6 text-xs">

                <!-- Step 1: Database Setup -->
                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2">
                        <span class="w-6 h-6 rounded-lg bg-purple-600/30 text-purple-300 flex items-center justify-center text-xs">۱</span>
                        <span>مشخصات دیتابیس MySQL (از سی‌پنل)</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">نوع پایگاه داده:</label>
                            <select name="db_driver" id="dbDriverSelect" onchange="toggleDbFields()" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                                <option value="mysql" selected>MySQL / MariaDB (مخصوص cPanel)</option>
                                <option value="sqlite">SQLite (فقط در صورت فعال بودن pdo_sqlite)</option>
                            </select>
                        </div>
                        <div id="dbHostField">
                            <label class="block text-slate-300 mb-1 font-semibold">آدرس سرور دیتابیس:</label>
                            <input type="text" name="db_host" value="localhost" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                    </div>

                    <div id="mysqlFields" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-300 mb-1 font-semibold">نام دیتابیس (DB Name) *</label>
                                <input type="text" name="db_name" placeholder="مثلاً: cpaneluser_vpn" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            </div>
                            <div>
                                <label class="block text-slate-300 mb-1 font-semibold">نام کاربری دیتابیس (DB User) *</label>
                                <input type="text" name="db_user" placeholder="مثلاً: cpaneluser_admin" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">رمز عبور دیتابیس (DB Password)</label>
                            <input type="password" name="db_pass" placeholder="رمز تعیین‌شده در cPanel" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Super Admin Account -->
                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2">
                        <span class="w-6 h-6 rounded-lg bg-cyan-600/30 text-cyan-300 flex items-center justify-center text-xs">۲</span>
                        <span>ساخت حساب مدیر کل (Super Admin)</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">نام کاربری مدیر *</label>
                            <input type="text" name="admin_user" value="admin" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">رمز عبور دلخواه مدیر *</label>
                            <input type="password" name="admin_pass" required placeholder="حداقل ۶ کاراکتر" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">ایمیل مدیر</label>
                        <input type="email" name="admin_email" value="admin@example.com" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                    </div>
                </div>

                <!-- Step 3: Telegram Bot & Branding -->
                <div class="bg-slate-950/70 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center gap-2 text-sm font-bold text-white border-b border-slate-800/80 pb-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-600/30 text-emerald-300 flex items-center justify-center text-xs">۳</span>
                        <span>ربات تلگرام اعلان‌ها و اطلاعات برندینگ (اختیاری)</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">توکن ربات تلگرام (از @BotFather)</label>
                            <input type="text" name="telegram_bot_token" placeholder="123456789:ABCdefGhI..." dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">آیدی عددی تلگرام ادمین (Chat ID)</label>
                            <input type="text" name="telegram_admin_chat_id" placeholder="مثلاً: 12345678" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">نام برند یا پنل شما</label>
                            <input type="text" name="brand_name" value="Connectix VPN" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">آدرس دامنه سایت (URL)</label>
                            <input type="url" name="app_url" value="<?= htmlspecialchars($detectedUrl) ?>" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        </div>
                    </div>
                </div>

                <button type="submit" <?= !$allPassed ? 'disabled' : '' ?> class="w-full py-4 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white font-bold rounded-2xl text-sm transition-all shadow-xl shadow-purple-900/40 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>نصب خودکار و ساخت جداول دیتابیس</span>
                </button>
            </form>
        <?php endif; ?>

    </div>

    <script>
        function toggleDbFields() {
            const driver = document.getElementById('dbDriverSelect').value;
            const mysqlFields = document.getElementById('mysqlFields');
            const hostField = document.getElementById('dbHostField');
            if (driver === 'sqlite') {
                mysqlFields.style.display = 'none';
                hostField.style.display = 'none';
            } else {
                mysqlFields.style.display = 'block';
                hostField.style.display = 'block';
            }
        }
    </script>
</body>
</html>

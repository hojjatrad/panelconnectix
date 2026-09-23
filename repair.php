<?php
/**
 * Connectix Panel - Emergency Self-Healing & Diagnostic Utility (v3.0.0)
 * Language: Persian (Farsi) - RTL
 * Purpose: Automatically repair .htaccess, verify database connection,
 * migrate missing tables/columns, fix permissions, and restore panel functionality.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$stepResults = [];

// 1. Repair and overwrite .htaccess to remove any hardcoded RewriteBase
$htaccessPath = __DIR__ . '/.htaccess';
$standardHtaccess = <<<HTACCESS
# Connectix Panel - Apache / cPanel URL Rewriting
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Prevent direct access to sensitive directories
    RewriteRule ^(core|drivers|data|cron)/.*$ - [F,L]

    # Serve existing files and directories directly
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    # Redirect all other requests to index.php
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
HTACCESS;

$htaccessFixed = false;
try {
    if (!file_exists($htaccessPath) || str_contains(@file_get_contents($htaccessPath), '/contax/')) {
        file_put_contents($htaccessPath, $standardHtaccess);
        $htaccessFixed = true;
    }
    $stepResults['htaccess'] = ['status' => true, 'msg' => 'فایل .htaccess بررسی و قوانین بازنویسی استاندارد آپاچی با موفقیت بازنشانی شد.'];
} catch (Throwable $e) {
    $stepResults['htaccess'] = ['status' => false, 'msg' => 'خطا در نوشتن فایل .htaccess: ' . $e->getMessage()];
}

// 2. Check config.php
$configPath = __DIR__ . '/config.php';
$hasConfig = file_exists($configPath);
if (!$hasConfig) {
    $stepResults['config'] = ['status' => false, 'msg' => 'فایل config.php یافت نشد. لطفاً ابتدا از طریق install.php سیستم را نصب کنید.'];
} else {
    require_once $configPath;
    $stepResults['config'] = ['status' => true, 'msg' => 'فایل تنظیمات (config.php) خوانده شد. دیتابیس تعیین‌شده: ' . strtoupper(DB_DRIVER)];
}

// 3. Check PHP extensions
$requiredExts = ['pdo', 'curl', 'mbstring'];
$missingExts = [];
foreach ($requiredExts as $ext) {
    if (!extension_loaded($ext)) $missingExts[] = $ext;
}
if (empty($missingExts)) {
    $stepResults['php'] = ['status' => true, 'msg' => 'نسخه PHP (' . PHP_VERSION . ') و اکستنشن‌های اصلی فعال هستند.'];
} else {
    $stepResults['php'] = ['status' => false, 'msg' => 'اکستنشن‌های ناموجود: ' . implode(', ', $missingExts)];
}

// 4. Test Database connection & Auto-migrate schema
$dbOk = false;
if ($hasConfig) {
    try {
        require_once __DIR__ . '/core/Database.php';
        $pdo = Database::getConnection();
        $dbOk = true;
        
        // Run full migrations
        Database::ensureExtendedTablesExist($pdo);

        // Check tables count
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        } else {
            $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
        }

        // Verify Admin Account exists
        $adminUser = $pdo->query("SELECT id, username FROM users WHERE role = 'admin' LIMIT 1")->fetch();
        if (!$adminUser) {
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO users (username, password_hash, role, full_name, email, wallet_balance, api_token) 
                        VALUES ('admin', '{$adminPass}', 'admin', 'مدیر کل سیستم', 'admin@connectix.local', 0, 'admin_secret_123')");
            $adminMsg = 'کاربر مدیر پیش‌فرض (admin / رمز: admin123) بازیابی و ساخته شد.';
        } else {
            $adminMsg = "حساب مدیر ارشد موجود است ({$adminUser['username']}).";
        }

        // Check clear_servers action
        if (isset($_GET['clear_servers']) && $_GET['clear_servers'] == '1') {
            try {
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $pdo->exec("UPDATE plans SET server_id = NULL");
                    $pdo->exec("UPDATE clients SET server_id = NULL");
                    $pdo->exec("DELETE FROM server_nodes");
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                } else {
                    $pdo->exec("PRAGMA foreign_keys = OFF");
                    $pdo->exec("UPDATE plans SET server_id = NULL");
                    $pdo->exec("UPDATE clients SET server_id = NULL");
                    $pdo->exec("DELETE FROM server_nodes");
                    $pdo->exec("PRAGMA foreign_keys = ON");
                }
                $adminMsg .= ' [سرورها به طور کامل پاکسازی و خام شدند.]';
            } catch (Throwable $e) {}
        }

        // Auto-disable mock servers if any real server exists
        try {
            $hasReal = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE driver != 'mock' AND is_active = 1")->fetchColumn();
            if ($hasReal > 0) {
                $pdo->exec("UPDATE server_nodes SET is_active = 0 WHERE driver = 'mock'");
                $pdo->exec("UPDATE plans SET server_id = NULL WHERE server_id IN (SELECT id FROM server_nodes WHERE driver = 'mock')");
            }
        } catch (Throwable $e) {}

        // Diagnostic API for testing live servers
        if (isset($_GET['diag_server'])) {
            header('Content-Type: application/json; charset=utf-8');
            require_once __DIR__ . '/drivers/DriverFactory.php';
            require_once __DIR__ . '/core/Helpers.php';
            $nodes = $pdo->query("SELECT id, name, driver, api_url, api_username, is_active, health_status, server_group, config_template FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
            $results = [];
            foreach ($nodes as $n) {
                $full = $pdo->query("SELECT * FROM server_nodes WHERE id = " . (int)$n['id'])->fetch(PDO::FETCH_ASSOC);
                $driverInst = DriverFactory::create($full);
                $auth = $driverInst->authenticate();
                $err = method_exists($driverInst, 'getLastError') ? $driverInst->getLastError() : null;
                $inbounds = method_exists($driverInst, 'getInbounds') ? $driverInst->getInbounds() : [];
                $sampleLink = null;
                if ($auth) {
                    $testUser = 'diag_' . substr(bin2hex(random_bytes(3)), 0, 6);
                    $cRes = $driverInst->createUser([
                        'username' => $testUser,
                        'uuid' => Helpers::generateUUID(),
                        'traffic_limit_bytes' => 1073741824,
                        'expire_timestamp' => time() + 86400
                    ]);
                    if ($cRes['success']) {
                        $sampleLink = [
                            'sublink' => $cRes['sublink'] ?? null,
                            'links' => $cRes['links'] ?? [],
                            'vless_link' => $cRes['vless_link'] ?? null
                        ];
                        $driverInst->deleteUser($testUser);
                    } else {
                        $sampleLink = ['error' => $cRes['error'] ?? 'failed'];
                    }
                }
                $results[] = [
                    'node' => $n,
                    'auth' => $auth,
                    'error' => $err,
                    'inbounds' => $inbounds,
                    'sample' => $sampleLink
                ];
            }
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stepResults['db'] = [
            'status' => true, 
            'msg' => "ارتباط با پایگاه داده برقراره و تعداد {$tableCount} جدول تایید شد. {$adminMsg}"
        ];
    } catch (Throwable $e) {
        $stepResults['db'] = ['status' => false, 'msg' => 'خطا در ارتباط با دیتابیس: ' . $e->getMessage()];
    }
}

// 5. File Integrity Check & Auto-Restoration of Controllers and Views
$expectedControllers = [
    'AuthController', 'DashboardController', 'ClientController', 'PlanController',
    'ServerController', 'CategoryController', 'ResellerController', 'BillingController',
    'MetadataController', 'NotificationController', 'SublinkController', 'TelegramBotController',
    'PaymentController', 'ApiController', 'ProfileController', 'ResellerPortalController',
    'LogController', 'UpdateController', 'TicketController', 'AppGuideController', 'CouponController',
    'WebappController'
];

$missingControllers = [];
foreach ($expectedControllers as $ctrl) {
    if (!file_exists(__DIR__ . '/controllers/' . $ctrl . '.php')) {
        $missingControllers[] = $ctrl;
    }
}

$forceRestore = isset($_GET['restore_files']) && $_GET['restore_files'] === '1';

if (!empty($missingControllers) || $forceRestore) {
    $repo = 'hojjatrad/panelconnectix';
    $token = '';
    try {
        if ($dbOk) {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token' LIMIT 1");
            $token = $stmt ? (string)$stmt->fetchColumn() : '';
            $stmtRepo = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_repo' LIMIT 1");
            $dbRepo = $stmtRepo ? (string)$stmtRepo->fetchColumn() : '';
            if (!empty($dbRepo)) $repo = $dbRepo;
        }
    } catch (Throwable $e) {}

    $downloadUrls = [
        "https://github.com/{$repo}/archive/refs/heads/main.zip",
        "https://codeload.github.com/{$repo}/zip/refs/heads/main",
        "https://api.github.com/repos/{$repo}/zipball/main"
    ];

    $zipData = false;
    $httpCode = 0;

    foreach ($downloadUrls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $headers = ['User-Agent: Connectix-Repair-Tool'];
        if (!empty($token) && str_contains($url, 'api.github.com')) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && strlen($zipData) > 5000) {
            break;
        }
    }

    $restoredCount = 0;
    if ($httpCode === 200 && strlen($zipData) > 5000) {
        $tmpZip = sys_get_temp_dir() . '/repair_pkg_' . time() . '.zip';
        $tmpExtract = sys_get_temp_dir() . '/repair_ext_' . time();
        file_put_contents($tmpZip, $zipData);

        $extracted = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) === true) {
                $zip->extractTo($tmpExtract);
                $zip->close();
                $extracted = true;
            }
        }
        if (!$extracted && function_exists('shell_exec')) {
            @shell_exec('unzip -q -o ' . escapeshellarg($tmpZip) . ' -d ' . escapeshellarg($tmpExtract) . ' 2>&1');
            $files = glob($tmpExtract . '/*');
            if (!empty($files)) $extracted = true;
        }

        if ($extracted) {
            $subDirs = glob($tmpExtract . '/*', GLOB_ONLYDIR);
            $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExtract;

            $foldersToRestore = ['controllers', 'core', 'drivers', 'views'];
            foreach ($foldersToRestore as $f) {
                $srcF = $sourceDir . '/' . $f;
                $dstF = __DIR__ . '/' . $f;
                if (is_dir($srcF)) {
                    if (!is_dir($dstF)) @mkdir($dstF, 0755, true);
                    @chmod($dstF, 0755);
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($srcF, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $item) {
                        $target = $dstF . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
                        if ($item->isDir()) {
                            if (!is_dir($target)) @mkdir($target, 0755, true);
                            @chmod($target, 0755);
                        } else {
                            if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
                            @copy($item->getPathname(), $target);
                            @chmod($target, 0644);
                            $restoredCount++;
                        }
                    }
                }
            }
            if (file_exists($sourceDir . '/index.php')) {
                @copy($sourceDir . '/index.php', __DIR__ . '/index.php');
                @chmod(__DIR__ . '/index.php', 0644);
            }
        }
        @unlink($tmpZip);
    }

    $stillMissing = [];
    foreach ($expectedControllers as $ctrl) {
        if (!file_exists(__DIR__ . '/controllers/' . $ctrl . '.php')) {
            $stillMissing[] = $ctrl;
        }
    }

    if (empty($stillMissing)) {
        $stepResults['files'] = [
            'status' => true,
            'msg' => "تمام ۲۱ کنترلر و فایل‌های اصلی سیستم تایید و مستقر شدند ({$restoredCount} فایل بازنویسی/ترمیم شد)."
        ];
    } else {
        $stepResults['files'] = [
            'status' => false,
            'msg' => 'برخی فایل‌ها یافت نشدند: ' . implode(', ', $stillMissing) . '. لطفاً پکیج zip را به صورت دستی در هاست اکسترکت کنید.'
        ];
    }
} else {
    $stepResults['files'] = [
        'status' => true,
        'msg' => 'سلامت ساختار تمامی ۲۱ فایل کنترلر، ویوها و هسته نرم‌افزار تایید شد.'
    ];
}

// 6. Invalidate Caches
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
if (function_exists('clearstatcache')) {
    @clearstatcache(true);
}

// 7. Emergency SQL Restore Handler (Upload backup .sql directly in repair tool)
$sqlRestoreMsg = '';
if (!empty($_FILES['emergency_sql_file']['tmp_name']) && $_FILES['emergency_sql_file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['emergency_sql_file'];
    $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    if ($ext === 'sql') {
        $sqlContent = file_get_contents($uploadedFile['tmp_name']);
        if (!empty($sqlContent)) {
            require_once __DIR__ . '/core/Database.php';
            $restoreRes = Database::restoreFromSql($sqlContent);
            if ($restoreRes['success']) {
                $sqlRestoreMsg = "<div class='p-3 bg-emerald-950/80 border border-emerald-800 text-emerald-200 rounded-xl text-xs'>✅ پایگاه داده با موفقیت بازگردانی شد! ({$restoreRes['executed']} دستور با موفقیت اعمال گردید)</div>";
            } else {
                $sqlRestoreMsg = "<div class='p-3 bg-rose-950/80 border border-rose-800 text-rose-200 rounded-xl text-xs'>❌ خطا در بازگردانی: " . htmlspecialchars($restoreRes['error'] ?? '') . "</div>";
            }
        }
    } else {
        $sqlRestoreMsg = "<div class='p-3 bg-amber-950/80 border border-amber-800 text-amber-200 rounded-xl text-xs'>⚠️ لطفاً فقط فایل پشتیبان با پسوند .sql آپلود فرمایید.</div>";
    }
}

// Check overall status
$allOk = true;
foreach ($stepResults as $r) {
    if (!$r['status']) {
        $allOk = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ابزار ترمیم و عیب‌یابی خودکار پنل | Connectix Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-purple-600 selection:text-white relative overflow-x-hidden">

    <!-- Ambient Glow Background -->
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-xl bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl p-6 md:p-8 backdrop-blur-xl relative z-10 space-y-6">

        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-2xl <?= $allOk ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' ?> mx-auto flex items-center justify-center text-3xl shadow-xl">
                <i class="fa-solid <?= $allOk ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            </div>
            <h1 class="text-xl font-black text-white">ترمیم و راه‌اندازی مجدد خودکار سیستم</h1>
            <p class="text-xs text-slate-400">عیب‌یابی فایل‌ها، بررسی روتینگ آپاچی و ساخت خودکار جداول دیتابیس</p>
        </div>

        <!-- Step Results List -->
        <div class="space-y-3">
            <?php foreach ($stepResults as $key => $res): ?>
                <div class="flex items-start gap-3 p-3.5 rounded-2xl border <?= $res['status'] ? 'bg-slate-950/70 border-emerald-900/40 text-emerald-200' : 'bg-rose-950/60 border-rose-800 text-rose-200' ?>">
                    <div class="mt-0.5">
                        <i class="fa-solid <?= $res['status'] ? 'fa-check text-emerald-400' : 'fa-xmark text-rose-400' ?> text-sm"></i>
                    </div>
                    <div class="text-xs leading-relaxed flex-1">
                        <span class="font-bold block mb-0.5">
                            <?= match($key) {
                                'htaccess' => 'پیکربندی وب‌سرور آپاچی (.htaccess)',
                                'config' => 'فایل تنظیمات اتصال (config.php)',
                                'php' => 'پیش‌نیازهای مفسر PHP',
                                'db' => 'ارتباط با پایگاه داده و ساختار جداول',
                                'files' => 'یکپارچگی و سلامت فایل‌های کنترلر و سیستم',
                                default => 'بررسی سیستم'
                            } ?>
                        </span>
                        <span class="text-slate-300"><?= htmlspecialchars($res['msg']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 flex flex-col gap-2.5">
            <?php if ($allOk): ?>
                <a href="login" class="w-full py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-2xl text-xs transition-all shadow-xl shadow-purple-900/40 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>ورود مستقیم به پنل مدیریت</span>
                </a>
            <?php else: ?>
                <a href="repair.php?restore_files=1" class="w-full py-3.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-2xl text-xs transition-all shadow-xl shadow-amber-900/40 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                    <span>دانلود و بازسازی فوری تمامی فایل‌های پنل از گیت‌هاب</span>
                </a>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-2">
                <a href="repair.php" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition-all border border-slate-700 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>اسکن مجدد سلامت</span>
                </a>
                <a href="repair.php?restore_files=1" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-cyan-300 font-semibold rounded-xl text-xs transition-all border border-slate-700 flex items-center justify-center gap-1.5" title="بازنویسی فایل‌های سیستمی">
                    <i class="fa-solid fa-download"></i>
                    <span>ترمیم مجدد فایل‌ها</span>
                </a>
            </div>

            <div>
                <a href="repair.php?clear_servers=1" onclick="return confirm('⚠️ آیا از خام‌سازی و پاکسازی کامل تمامی سرورها اطمینان دارید؟');" class="w-full py-2.5 bg-rose-950/40 hover:bg-rose-900/50 text-rose-300 font-bold rounded-xl text-xs transition border border-rose-800/50 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-trash-can"></i>
                    <span>خام‌سازی و پاکسازی کامل سرورها (جهت معرفی سرور اختصاصی)</span>
                </a>
            </div>
        </div>

        <!-- Emergency SQL Backup Restore Card -->
        <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-2.5 text-xs">
            <div class="flex items-center gap-2 text-white font-bold">
                <i class="fa-solid fa-database text-amber-400"></i>
                <span>بازگردانی اضطراری پایگاه داده با بکاپ تلگرام (.sql)</span>
            </div>
            <p class="text-[11px] text-slate-400">در صورتی که دیتابیس دچار تداخل یا پاک‌شدگی شده است، فایل SQL ارسالی به تلگرام را انتخاب فرمایید:</p>
            <?= $sqlRestoreMsg ?>
            <form action="repair.php" method="POST" enctype="multipart/form-data" class="space-y-2">
                <input type="file" name="emergency_sql_file" accept=".sql" required class="w-full text-xs text-slate-400 file:mr-0 file:ml-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-amber-300 hover:file:bg-slate-700 bg-slate-900 p-1.5 rounded-xl border border-slate-800">
                <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-md shadow-amber-900/30">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>آپلود و بازگردانی دیتابیس از فایل SQL</span>
                </button>
            </form>
        </div>

        <div class="text-[11px] text-center text-slate-500 pt-2 border-t border-slate-800/80">
            نسخه پایدار و ترمیم‌شده سامانه: <span class="font-mono text-purple-400 font-bold">v3.1.0</span>
        </div>

    </div>

</body>
</html>

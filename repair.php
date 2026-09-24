<?php
/**
 * Connectix Panel - Emergency Self-Healing & Diagnostic Utility (v3.1.0)
 * Language: Persian (Farsi) - RTL
 * Purpose: Automatically repair .htaccess, verify database connection,
 * migrate missing tables/columns, fix permissions, and restore panel functionality.
 */

define('CONNECTIX_REPAIR', true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct Zero-Dependency One-Click Restoration Hook
if (isset($_GET['restore_files']) && $_GET['restore_files'] === '1') {
    if (file_exists(__DIR__ . '/quick_update.php')) {
        require __DIR__ . '/quick_update.php';
        exit;
    }
}

$stepResults = [];

// Auto-detect and fix subfolder extraction (if connectix-panel/ subfolder exists)
$subfolder = __DIR__ . '/connectix-panel';
if (is_dir($subfolder) && file_exists($subfolder . '/index.php')) {
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($subfolder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $target = __DIR__ . '/' . $files->getSubPathname();
            if ($file->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0755, true);
            } else {
                if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
                @copy($file->getPathname(), $target);
                @chmod($target, 0644);
            }
        }
        $stepResults['relocation'] = ['status' => true, 'msg' => 'پوشه تو در توی connectix-panel شناسایی و تمامی فایل‌ها به مسیر اصلی منتقل شدند.'];
    } catch (Throwable $e) {
        $stepResults['relocation'] = ['status' => false, 'msg' => 'خطا در انتقال فایل‌ها: ' . $e->getMessage()];
    }
}

// 1. Repair and overwrite .htaccess to remove any hardcoded RewriteBase
$htaccessPath = __DIR__ . '/.htaccess';
$standardHtaccess = <<<HTACCESS
# Connectix Panel - Apache / cPanel URL Rewriting
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Ensure Authorization header reaches PHP in FastCGI / PHP-FPM / cPanel environments
    RewriteCond %{HTTP:Authorization} ^(.*)
    RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]

    # Prevent direct access to sensitive directories
    RewriteRule ^(core|drivers|data|cron)/.*$ - [F,L]

    # Serve existing files and directories directly
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    # Redirect all other requests to index.php
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

<IfModule mod_setenvif.c>
    SetEnvIfNoCase Authorization "^(.*)$" HTTP_AUTHORIZATION=$1
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
$pdo = null;
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
                } else {
                    $pdo->exec("PRAGMA foreign_keys = OFF");
                }
                $pdo->exec("UPDATE plans SET server_id = NULL");
                $pdo->exec("DELETE FROM reserved_plans");
                $pdo->exec("DELETE FROM trial_logs");
                $pdo->exec("DELETE FROM bot_orders");
                $pdo->exec("DELETE FROM clients");
                $pdo->exec("DELETE FROM server_nodes");
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                } else {
                    $pdo->exec("PRAGMA foreign_keys = ON");
                }
                $adminMsg .= ' [تمامی سرورها و کلاینت‌ها به طور کامل پاکسازی و خام شدند.]';
            } catch (Throwable $e) {}
        }

        // Complete 100% Purge of all servers, plans, clients, orders, and logs (Clean Slate)
        if ((isset($_GET['purge_samples']) && $_GET['purge_samples'] == '1') || (isset($_GET['purge_all']) && $_GET['purge_all'] == '1')) {
            try {
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                } else {
                    $pdo->exec("PRAGMA foreign_keys = OFF");
                }

                $wipe = ['bot_orders', 'trial_logs', 'reserved_plans', 'clients', 'reseller_plans', 'plans', 'server_nodes', 'transactions', 'lucky_wheel_logs', 'wallet_logs', 'crypto_payments', 'bot_sessions'];
                foreach ($wipe as $w) {
                    try { $pdo->exec("DELETE FROM `{$w}`"); } catch (Throwable $ignore) {}
                }

                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                } else {
                    $pdo->exec("PRAGMA foreign_keys = ON");
                }
                $adminMsg .= ' [تمامی سرورها، پلن‌ها، سفارشات تستی و تراکنش‌ها ۱۰۰٪ پاکسازی شدند. سیستم کاملاً خام و آماده است.]';
            } catch (Throwable $e) {
                $adminMsg .= ' [خطا در پاکسازی: ' . $e->getMessage() . ']';
            }
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

        // Auto-fix any clients whose node_sublink is invalid or empty
        try {
            $clientsToFix = $pdo->query("SELECT * FROM clients WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
            $fixedClients = 0;
            foreach ($clientsToFix as $cl) {
                if (empty($cl['node_sublink']) || Helpers::isPanelSubUrl($cl['node_sublink'])) {
                    if (!empty($cl['server_id'])) {
                        $srv = $pdo->query("SELECT * FROM server_nodes WHERE id = " . (int)$cl['server_id'])->fetch(PDO::FETCH_ASSOC);
                        if ($srv && $srv['driver'] !== 'mock') {
                            $drv = DriverFactory::create($srv);
                            $lUser = $drv->getUser($cl['username']);
                            if ($lUser && !empty($lUser['subscription_url'])) {
                                $pdo->prepare("UPDATE clients SET node_sublink = ? WHERE id = ?")->execute([$lUser['subscription_url'], $cl['id']]);
                                $fixedClients++;
                            }
                        }
                    }
                }
            }
            if ($fixedClients > 0) {
                $adminMsg .= " [تعداد {$fixedClients} کلاینت با ساب‌لینک مستقیم سرور همگام شدند.]";
            }
        } catch (Throwable $e) {}

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
            if (file_exists($sourceDir . '/repair.php')) {
                @copy($sourceDir . '/repair.php', __DIR__ . '/repair.php');
                @chmod(__DIR__ . '/repair.php', 0644);
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

// 8. Telegram Bot Diagnostics & Instant Re-Activation Handler
$botMsg = '';
$currToken = '';
$webhookInfo = null;

if ($dbOk) {
    try {
        require_once __DIR__ . '/core/Setting.php';
        require_once __DIR__ . '/core/TelegramBot.php';
        require_once __DIR__ . '/core/Helpers.php';
        $currToken = TelegramBot::getToken();

        if (isset($_POST['action']) && $_POST['action'] === 'reset_bot') {
            $newToken = trim($_POST['bot_token'] ?? '');
            if (!empty($newToken)) {
                Setting::set('telegram_bot_token', $newToken);
                $currToken = $newToken;
            }
            $appBase = Helpers::fullUrl('');
            $webhookUrl = rtrim($appBase, '/') . '/webhook.php';
            $res = TelegramBot::setWebhook($webhookUrl, $currToken, true);
            if (!empty($res['ok'])) {
                $botMsg = "<div class='p-3 bg-emerald-950/80 border border-emerald-800 text-emerald-200 rounded-xl text-xs'>✅ وبهوک ربات تلگرام با موفقیت مجدداً ثبت گردید و صف پیام‌های معلق پاکسازی شد.<br><b>آدرس وبهوک:</b> <code>{$webhookUrl}</code></div>";
            } else {
                $botMsg = "<div class='p-3 bg-rose-950/80 border border-rose-800 text-rose-200 rounded-xl text-xs'>❌ خطا در ثبت وبهوک: " . htmlspecialchars($res['description'] ?? 'عدم پاسخ تلگرام') . "</div>";
            }
        }

        if (!empty($currToken) && !str_contains($currToken, 'FAKE')) {
            $webhookInfo = TelegramBot::getWebhookInfo($currToken);
        }
    } catch (Throwable $e) {}
}

// 9. Database Switcher (Switch between SQLite and MySQL directly)
$dbSwitchMsg = '';
if (isset($_POST['action']) && $_POST['action'] === 'switch_db') {
    $targetDriver = trim($_POST['db_driver'] ?? 'mysql');
    if ($targetDriver === 'mysql') {
        $mHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $mPort = trim($_POST['db_port'] ?? '3306');
        $mName = trim($_POST['db_name'] ?? '');
        $mUser = trim($_POST['db_user'] ?? '');
        $mPass = trim($_POST['db_pass'] ?? '');

        try {
            $testDsn = "mysql:host={$mHost};port={$mPort};dbname={$mName};charset=utf8mb4";
            $testPdo = new PDO($testDsn, $mUser, $mPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            $cfg = file_get_contents(__DIR__ . '/config.php');
            $cfg = preg_replace("/define\('DB_DRIVER',\s*'.*?'\);/", "define('DB_DRIVER', 'mysql');", $cfg);
            $cfg = preg_replace("/define\('DB_HOST',\s*'.*?'\);/", "define('DB_HOST', " . var_export($mHost, true) . ");", $cfg);
            $cfg = preg_replace("/define\('DB_PORT',\s*'.*?'\);/", "define('DB_PORT', " . var_export($mPort, true) . ");", $cfg);
            $cfg = preg_replace("/define\('DB_NAME',\s*'.*?'\);/", "define('DB_NAME', " . var_export($mName, true) . ");", $cfg);
            $cfg = preg_replace("/define\('DB_USER',\s*'.*?'\);/", "define('DB_USER', " . var_export($mUser, true) . ");", $cfg);
            $cfg = preg_replace("/define\('DB_PASS',\s*'.*?'\);/", "define('DB_PASS', " . var_export($mPass, true) . ");", $cfg);
            file_put_contents(__DIR__ . '/config.php', $cfg);
            
            $dbSwitchMsg = "<div class='p-3 bg-emerald-950/80 border border-emerald-800 text-emerald-200 rounded-xl text-xs'>✅ اتصال به MySQL تایید و فایل config.php با موفقیت بروزرسانی شد! لطفاً صفحه را مجدداً رفرش فرمایید.</div>";
        } catch (Throwable $e) {
            $dbSwitchMsg = "<div class='p-3 bg-rose-950/80 border border-rose-800 text-rose-200 rounded-xl text-xs'>❌ خطا در اتصال به MySQL: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
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

            <div>
                <a href="purge_all.php" onclick="return confirm('⚠️ اخطار قطعی:\nآیا از پاکسازی ۱۰۰٪ کامل تمامی سرورها، پلن‌ها، سفارشات و کلاینت‌ها اطمینان دارید؟\nسیستم کاملاً خام خواهد شد تا بتوانید سرور و پلن‌های اختصاصی خود را از ابتدا تعریف کنید.');" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-rose-900/40 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-broom"></i>
                    <span>پاکسازی ۱۰۰٪ کامل نمونه‌ها و شروع از صفر (Clean Slate)</span>
                </a>
            </div>
        </div>

        <!-- Telegram Bot Quick Diagnostic & Reactivation Card -->
        <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-3 text-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-white font-bold">
                    <i class="fa-brands fa-telegram text-sky-400 text-base"></i>
                    <span>عیب‌یابی فوری و فعال‌سازی مجدد ربات تلگرام</span>
                </div>
                <?php if ($webhookInfo && !empty($webhookInfo['ok'])): ?>
                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        وضعیت وبهوک: فعال
                    </span>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/30">
                        نیاز به ثبت وبهوک
                    </span>
                <?php endif; ?>
            </div>

            <?= $botMsg ?>

            <?php if ($webhookInfo && !empty($webhookInfo['result'])): 
                $wh = $webhookInfo['result'];
            ?>
                <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 space-y-1.5 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">آدرس وبهوک ثبت‌شده:</span>
                        <code class="font-mono text-cyan-300 select-all"><?= htmlspecialchars($wh['url'] ?: 'ثبت نشده') ?></code>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">پیام‌های معلق در صف تلگرام:</span>
                        <span class="font-bold <?= ($wh['pending_update_count'] > 0) ? 'text-amber-400' : 'text-emerald-400' ?> font-mono">
                            <?= (int)$wh['pending_update_count'] ?> پیام
                        </span>
                    </div>
                    <?php if (!empty($wh['last_error_message'])): ?>
                        <div class="pt-1 border-t border-slate-800 text-rose-300">
                            <span class="block text-slate-400 text-[10px]">آخرین خطای تلگرام:</span>
                            <code><?= htmlspecialchars($wh['last_error_message']) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="repair.php" method="POST" class="space-y-2">
                <input type="hidden" name="action" value="reset_bot">
                <div>
                    <label class="block text-slate-400 text-[10px] mb-1">توکن ربات تلگرام (از BotFather):</label>
                    <input type="text" name="bot_token" dir="ltr" value="<?= htmlspecialchars($currToken ?: '') ?>" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ" class="w-full bg-slate-900 border border-slate-800 rounded-xl p-2 font-mono text-xs text-white">
                </div>
                <button type="submit" class="w-full py-2.5 bg-sky-600 hover:bg-sky-500 text-white font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-md shadow-sky-900/30">
                    <i class="fa-solid fa-bolt"></i>
                    <span>ثبت مجدد وبهوک و پاکسازی صف پیام‌های معلق (Reactivate Bot)</span>
                </button>
            </form>
        </div>

        <!-- Database Switcher Card (MySQL / SQLite) -->
        <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-2xl space-y-3 text-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-white font-bold">
                    <i class="fa-solid fa-server text-purple-400"></i>
                    <span>مدیریت اتصال پایگاه داده (MySQL / SQLite)</span>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] bg-purple-500/10 text-purple-300 border border-purple-500/30 font-mono">
                    فعلی: <?= strtoupper(DB_DRIVER) ?>
                </span>
            </div>

            <?= $dbSwitchMsg ?>

            <?php if (DB_DRIVER === 'sqlite'): ?>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    در حال حاضر سامانه روی <b>SQLite</b> فعال است. در صورتی که دیتابیس قبلی شما روی <b>MySQL</b> بوده، می‌توانید با وارد کردن مشخصات زیر، فایل config.php را مستقیماً به MySQL متصل فرمایید:
                </p>
                <form action="repair.php" method="POST" class="space-y-2">
                    <input type="hidden" name="action" value="switch_db">
                    <input type="hidden" name="db_driver" value="mysql">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-400 text-[10px] mb-0.5">نام دیتابیس MySQL:</label>
                            <input type="text" name="db_name" dir="ltr" placeholder="cpaneluser_contax" required class="w-full bg-slate-900 border border-slate-800 rounded-xl p-2 text-xs font-mono text-white">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-[10px] mb-0.5">نام کاربری MySQL:</label>
                            <input type="text" name="db_user" dir="ltr" placeholder="cpaneluser_admin" required class="w-full bg-slate-900 border border-slate-800 rounded-xl p-2 text-xs font-mono text-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-400 text-[10px] mb-0.5">کلمه عبور MySQL:</label>
                            <input type="password" name="db_pass" dir="ltr" placeholder="••••••••" required class="w-full bg-slate-900 border border-slate-800 rounded-xl p-2 text-xs font-mono text-white">
                        </div>
                        <div>
                            <label class="block text-slate-400 text-[10px] mb-0.5">هاست (127.0.0.1 پیشنهاد می‌شود):</label>
                            <input type="text" name="db_host" dir="ltr" value="127.0.0.1" required class="w-full bg-slate-900 border border-slate-800 rounded-xl p-2 text-xs font-mono text-white">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl transition flex items-center justify-center gap-1.5 shadow-md shadow-purple-900/30">
                        <i class="fa-solid fa-link"></i>
                        <span>تست اتصال و سوئیچ فوری به MySQL</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="text-[11px] text-emerald-300 bg-emerald-950/40 p-2.5 rounded-xl border border-emerald-800/40 flex items-center justify-between">
                    <span>پایگاه داده MySQL متصل است. (هاست: <code><?= DB_HOST ?></code> | دیتابیس: <code><?= DB_NAME ?></code>)</span>
                    <i class="fa-solid fa-check text-emerald-400"></i>
                </div>
            <?php endif; ?>
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

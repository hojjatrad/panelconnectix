<?php
/**
 * Emergency JSON Fix - v6.8.9 FINAL
 * This file directly patches UpdateController.php and all related files
 * to fix Unexpected token '<' error, bypassing broken ajax-apply endpoint
 * Access: https://yourdomain.com/emergency_json_fix.php
 * After fix, delete this file
 */

// Absolute silence - prevent ANY <br> before output
while (ob_get_level() > 0) { @ob_end_clean(); }
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);
ob_start();

$log = [];
function elog($msg, $type='info') {
    global $log;
    $log[] = ['msg'=>$msg, 'type'=>$type, 'time'=>date('H:i:s')];
}

elog("=== Emergency JSON Fix v6.8.9 Started ===", 'info');
elog("Dir: " . __DIR__, 'info');
elog("Free space: " . round(@disk_free_space(__DIR__)/1024/1024,2) . " MB", 'info');

// 1. Fix session path
$sp = __DIR__ . '/data/sessions';
if (!is_dir($sp)) { @mkdir($sp, 0755, true); elog("Created sessions dir", 'success'); }
if (is_dir($sp)) { @chmod($sp, 0755); @ini_set('session.save_path', $sp); }

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// 2. Check auth - allow if already admin session OR via secret key
$secret = $_GET['key'] ?? '';
$valid = false;
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    $valid = true;
    elog("Auth via session: admin OK", 'success');
} elseif (!empty($secret)) {
    // Allow via APP_SECRET or github_webhook_secret
    try {
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
            if (defined('APP_SECRET') && hash_equals(APP_SECRET, $secret)) { $valid = true; elog("Auth via APP_SECRET", 'success'); }
        }
        if (!$valid && file_exists(__DIR__ . '/core/Setting.php')) {
            require_once __DIR__ . '/core/Database.php';
            require_once __DIR__ . '/core/Setting.php';
            $ws = Setting::get('github_webhook_secret', '');
            if (!empty($ws) && hash_equals($ws, $secret)) { $valid = true; elog("Auth via webhook_secret", 'success'); }
        }
    } catch (Throwable $e) {
        elog("Auth check error: " . $e->getMessage(), 'warn');
    }
} else {
    // For emergency, allow if file exists less than 5 minutes and no auth - to allow first fix
    $age = time() - @filemtime(__FILE__);
    if ($age < 300) {
        $valid = true;
        elog("Auth bypass: file age < 5min (emergency mode)", 'warn');
    }
}

if (!$valid) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: text/html; charset=utf-8');
    die("<h2 style='font-family:sans-serif;text-align:center;margin-top:50px;color:#ef4444'>⛔ دسترسی غیرمجاز<br><small>لطفاً ابتدا به عنوان ادمین لاگین کنید یا ?key=APP_SECRET اضافه کنید</small></h2>");
}

// 3. Patch index.php - add IS_AJAX_UPDATER detection at very top
elog("Patching index.php...", 'info');
$indexFile = __DIR__ . '/index.php';
if (is_file($indexFile)) {
    $content = @file_get_contents($indexFile);
    if ($content !== false) {
        if (!str_contains($content, 'IS_AJAX_UPDATER')) {
            $oldStart = "<?php\nerror_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);\nini_set('display_errors', 1);";
            $newStart = "<?php\n// v6.8.9 CRITICAL: Detect AJAX updater BEFORE any output - force silent mode\n\$__isAjaxUpdater = false;\n\$__reqUri = \$_SERVER['REQUEST_URI'] ?? '';\n\$__routeParam = \$_GET['route'] ?? '';\nif (str_contains(\$__reqUri, 'updater/ajax-apply') || \$__routeParam === 'updater/ajax-apply' || str_contains(\$__reqUri, 'updater%2Fajax-apply')) {\n    \$__isAjaxUpdater = true;\n}\nif (\$__isAjaxUpdater) {\n    ini_set('display_errors', '0');\n    ini_set('display_startup_errors', '0');\n    error_reporting(0);\n    while (ob_get_level() > 0) { @ob_end_clean(); }\n    ob_start();\n    define('IS_AJAX_UPDATER', true);\n} else {\n    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);\n    ini_set('display_errors', 1);\n    define('IS_AJAX_UPDATER', false);\n}";
            if (str_contains($content, $oldStart)) {
                $content = str_replace($oldStart, $newStart, $content);
                @file_put_contents($indexFile, $content);
                elog("index.php patched: added IS_AJAX_UPDATER", 'success');
            } else {
                elog("index.php already patched or structure different", 'warn');
            }
        } else {
            elog("index.php already has IS_AJAX_UPDATER", 'info');
        }
    }
}

// 4. Patch UpdateController.php ajaxApply - FULL BULLETPROOF
elog("Patching UpdateController.php...", 'info');
$ucFile = __DIR__ . '/controllers/UpdateController.php';
if (is_file($ucFile)) {
    $ucContent = @file_get_contents($ucFile);
    if ($ucContent !== false) {
        $newAjax = <<<'PHP'
    public function ajaxApply(): void {
        // v6.8.9 FINAL - ABSOLUTELY BULLETPROOF JSON - deep audit fix
        // 1. Clean ALL output buffers (nested)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        ob_start();
        // 2. Force silence
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @ini_set('log_errors', '1');
        @error_reporting(0);
        
        // 3. Session path fix BEFORE any Auth
        $sp = __DIR__ . '/../data/sessions';
        if (!is_dir($sp)) { @mkdir($sp, 0755, true); }
        if (is_dir($sp) && is_writable($sp)) {
            $cur = @ini_get('session.save_path');
            if (empty($cur) || strpos($cur, 'ea-php84') !== false || !@is_dir($cur) || !@is_writable($cur) || $cur === '/tmp' || $cur === sys_get_temp_dir()) {
                @ini_set('session.save_path', $sp);
            }
        }
        // Ensure session started
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        
        // 4. Auth check - but return JSON not redirect if fails (for AJAX)
        try {
            if (empty($_SESSION['user_id'])) {
                throw new Exception('نشست شما منقضی شده - لطفاً دوباره لاگین کنید');
            }
            // Check role via DB, not via requireAdmin which redirects
            $role = $_SESSION['role'] ?? null;
            if ($role !== 'admin') {
                // Try to fetch from DB
                try {
                    $pdo = Database::getConnection();
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([(int)$_SESSION['user_id']]);
                    $role = $stmt->fetchColumn();
                    $_SESSION['role'] = $role;
                } catch (Throwable $e) {}
                if ($role !== 'admin') {
                    throw new Exception('دسترسی غیرمجاز - فقط ادمین');
                }
            }
        } catch (Throwable $authEx) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'احراز هویت: ' . $authEx->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 5. Set limits
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');
        header('Content-Type: application/json; charset=utf-8');

        $startTime = microtime(true);
        try {
            $result = Updater::applyUpdate();
        } catch (Throwable $e) {
            $result = [
                'success' => false,
                'error' => 'خطای سیستمی: ' . $e->getMessage() . ' (فایل: ' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ];
            try { Updater::emergencyDiskCleanup(); } catch (Throwable $e2) {}
        }
        $duration = round(microtime(true) - $startTime, 2);
        $result['duration'] = $duration . ' ثانیه';
        $result['finished_at'] = date('H:i:s (Y/m/d)');
        try {
            $result['free_space_mb'] = round(@disk_free_space(__DIR__ . '/..') / 1024 / 1024, 2);
        } catch (Throwable $e) {
            $result['free_space_mb'] = 0;
        }
        
        // 6. FINAL CLEAN - guarantee ONLY JSON
        while (ob_get_level() > 0) { @ob_end_clean(); }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
PHP;
        // Replace old ajaxApply function
        $pattern = '/public function ajaxApply\(\): void \{.*?^\s{4}\}/ms';
        // Simpler: find start and replace until next public function
        if (preg_match('/public function ajaxApply\(\): void \{.*?\n    public function /s', $ucContent, $m)) {
            // Use custom replace
            $startPos = strpos($ucContent, 'public function ajaxApply(): void {');
            $endPos = strpos($ucContent, 'public function saveSettings(): void {', $startPos);
            if ($startPos !== false && $endPos !== false) {
                $before = substr($ucContent, 0, $startPos);
                $after = substr($ucContent, $endPos);
                $newContent = $before . $newAjax . "\n\n    " . $after;
                @file_put_contents($ucFile, $newContent);
                elog("UpdateController.php ajaxApply patched!", 'success');
            } else {
                elog("Could not locate ajaxApply boundaries", 'error');
            }
        } else {
            elog("Pattern not matched, trying direct write", 'warn');
            // Fallback: overwrite file with fixed version from GitHub latest
            $fixed = @file_get_contents('https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/UpdateController.php');
            if ($fixed && strlen($fixed) > 1000) {
                @file_put_contents($ucFile, $fixed);
                elog("UpdateController.php overwritten from GitHub raw", 'success');
            } else {
                elog("GitHub raw fetch failed", 'error');
            }
        }
    }
}

// 5. Patch config.php to respect IS_AJAX_UPDATER
elog("Patching config.php...", 'info');
$cfgFile = __DIR__ . '/config.php';
if (is_file($cfgFile)) {
    $cfg = @file_get_contents($cfgFile);
    if ($cfg && !str_contains($cfg, 'isAjaxUpdaterCfg')) {
        $oldCfg = "date_default_timezone_set('Asia/Tehran');\nif (!defined('APP_DEBUG')) {\n    define('APP_DEBUG', __connectix_secret('APP_DEBUG', true));\n}\nif (APP_DEBUG) {\n    ini_set('display_errors', 1);\n    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);\n} else {\n    ini_set('display_errors', 0);\n    error_reporting(0);\n}";
        $newCfg = "date_default_timezone_set('Asia/Tehran');\nif (!defined('APP_DEBUG')) {\n    define('APP_DEBUG', __connectix_secret('APP_DEBUG', true));\n}\n// v6.8.9 CRITICAL: For AJAX updater, FORCE silence even if APP_DEBUG=true to prevent <br> before JSON\n\$isAjaxUpdaterCfg = (defined('IS_AJAX_UPDATER') && IS_AJAX_UPDATER) || str_contains(\$_SERVER['REQUEST_URI'] ?? '', 'updater/ajax-apply') || (\$_GET['route'] ?? '') === 'updater/ajax-apply';\nif (\$isAjaxUpdaterCfg) {\n    ini_set('display_errors', 0);\n    ini_set('display_startup_errors', 0);\n    error_reporting(0);\n    while (ob_get_level() > 0) { @ob_end_clean(); }\n} elseif (APP_DEBUG) {\n    ini_set('display_errors', 1);\n    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);\n} else {\n    ini_set('display_errors', 0);\n    error_reporting(0);\n}";
        if (str_contains($cfg, $oldCfg)) {
            $cfg = str_replace($oldCfg, $newCfg, $cfg);
            @file_put_contents($cfgFile, $cfg);
            elog("config.php patched", 'success');
        } else {
            elog("config.php structure different, manual check needed", 'warn');
        }
    } else {
        elog("config.php already patched", 'info');
    }
}

// 6. Patch Database.php
elog("Patching Database.php...", 'info');
$dbFile = __DIR__ . '/core/Database.php';
if (is_file($dbFile)) {
    $dbContent = @file_get_contents($dbFile);
    if ($dbContent && !str_contains($dbContent, 'IS_AJAX_UPDATER') ) {
        $oldDb = "            } catch (Throwable \$e) {\n                if (defined('CONNECTIX_REPAIR') || defined('CONNECTIX_INSTALL') || (defined('CONNECTIX_NO_DIE') && CONNECTIX_NO_DIE)) {\n                    throw \$e;\n                }";
        $newDb = "            } catch (Throwable \$e) {\n                if (defined('CONNECTIX_REPAIR') || defined('CONNECTIX_INSTALL') || (defined('CONNECTIX_NO_DIE') && CONNECTIX_NO_DIE) || (defined('IS_AJAX_UPDATER') && IS_AJAX_UPDATER)) {\n                    throw \$e;\n                }\n                // v6.8.9: For AJAX updater, never die with HTML - throw instead\n                \$isAjax = !empty(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';\n                \$isUpdaterRoute = str_contains(\$_SERVER['REQUEST_URI'] ?? '', 'updater/ajax-apply') || (\$_GET['route'] ?? '') === 'updater/ajax-apply';\n                if (\$isAjax || \$isUpdaterRoute) {\n                    throw \$e;\n                }";
        if (str_contains($dbContent, $oldDb)) {
            $dbContent = str_replace($oldDb, $newDb, $dbContent);
            @file_put_contents($dbFile, $dbContent);
            elog("Database.php patched", 'success');
        } else {
            elog("Database.php already patched or different", 'warn');
        }
    } else {
        elog("Database.php already has fix", 'info');
    }
}

// 7. Now try to perform full update via direct GitHub download (bypass broken Updater)
elog("Attempting full update via direct GitHub download...", 'info');
try {
    $repo = 'hojjatrad/panelconnectix';
    $token = '';
    if (file_exists(__DIR__ . '/data/panel.sqlite')) {
        try {
            $pdo = new PDO('sqlite:' . __DIR__ . '/data/panel.sqlite');
            $s = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'github_token'")->fetchColumn();
            if (!empty($s)) $token = trim($s);
        } catch (Throwable $e) {}
    }
    // Get latest SHA
    $ch = curl_init("https://api.github.com/repos/{$repo}/commits/main");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Fix']);
    $apiBody = curl_exec($ch);
    curl_close($ch);
    $sha = '';
    if ($apiBody) {
        $j = json_decode($apiBody, true);
        $sha = $j['sha'] ?? '';
    }
    $zipUrl = $sha ? "https://codeload.github.com/{$repo}/zip/{$sha}" : "https://codeload.github.com/{$repo}/zip/refs/heads/main";
    elog("Downloading from: " . parse_url($zipUrl, PHP_URL_HOST), 'info');
    
    $ch = curl_init($zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $zipData = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200 && strlen($zipData) > 5000) {
        $tmpBase = __DIR__ . '/data/tmp';
        if (!is_dir($tmpBase)) @mkdir($tmpBase, 0755, true);
        $tmpZip = $tmpBase . '/fix_' . uniqid() . '.zip';
        $tmpExt = $tmpBase . '/fix_ext_' . uniqid();
        @mkdir($tmpExt, 0755, true);
        @file_put_contents($tmpZip, $zipData);
        
        $extracted = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) === true) {
                $zip->extractTo($tmpExt);
                $zip->close();
                $extracted = true;
            }
        }
        if (!$extracted) {
            @shell_exec('unzip -q -o ' . escapeshellarg($tmpZip) . ' -d ' . escapeshellarg($tmpExt));
            if (!empty(glob($tmpExt . '/*'))) $extracted = true;
        }
        
        if ($extracted) {
            $subDirs = glob($tmpExt . '/*', GLOB_ONLYDIR);
            $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $tmpExt;
            elog("Extracted to: $sourceDir", 'info');
            
            // Copy all files except config.php and data
            $skipped = ['config.php', 'data', 'install.lock'];
            $copied = 0;
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($rii as $file) {
                $rel = substr($file->getPathname(), strlen($sourceDir)+1);
                $parts = explode('/', $rel);
                if (in_array($parts[0], $skipped)) continue;
                if ($file->isDir()) {
                    if (!is_dir(__DIR__ . '/' . $rel)) @mkdir(__DIR__ . '/' . $rel, 0755, true);
                } else {
                    $dst = __DIR__ . '/' . $rel;
                    if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
                    if (@copy($file->getPathname(), $dst)) $copied++;
                }
            }
            elog("Full update: $copied files copied!", 'success');
            
            // Cleanup
            @unlink($tmpZip);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpExt, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isFile() ? @unlink($f->getPathname()) : @rmdir($f->getPathname()); }
            @rmdir($tmpExt);
            
            elog("Update completed! Version should now be 6.8.9", 'success');
        } else {
            elog("Failed to extract zip", 'error');
        }
    } else {
        elog("Failed to download zip: HTTP $code", 'error');
    }
} catch (Throwable $e) {
    elog("Update error: " . $e->getMessage(), 'error');
}

// Output result
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Emergency Fix Result - v6.8.9</title>
<style>
body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;direction:rtl}
.log{font-family:monospace;font-size:12px;line-height:1.8}
.info{color:#94a3b8}
.success{color:#22c55e;font-weight:bold}
.error{color:#ef4444;font-weight:bold}
.warn{color:#f59e0b}
.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:20px;max-width:800px;margin:20px auto}
h2{color:#a78bfa}
a{color:#8b5cf6}
</style>
</head>
<body>
<div class="box">
<h2>🔧 نتیجه فیکس اضطراری v6.8.9</h2>
<div class="log">
<?php foreach ($log as $l): ?>
<div class="<?= $l['type'] ?>">[<?= $l['time'] ?>] <?= htmlspecialchars($l['msg']) ?></div>
<?php endforeach; ?>
</div>
<hr style="border-color:#334155;margin:20px 0">
<p>✅ اگر همه مراحل success بود، حالا به <a href="index.php?route=updater">صفحه آپدیت</a> برو و دوباره امتحان کن.</p>
<p>یا مستقیم <a href="quick_update.php">quick_update.php</a> را باز کن.</p>
<p style="color:#f59e0b;font-size:11px;margin-top:15px">⚠️ پس از اطمینان از درست شدن، این فایل emergency_json_fix.php را حذف کن.</p>
</div>
</body>
</html>
<?php
exit;

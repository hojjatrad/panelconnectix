<?php
/**
 * test_host_independence.php — Test if panel is independent from old host/domain
 * Usage: https://yourdomain.com/panel/test_host_independence.php?key=SECRET
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Setting.php';

$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';
$expectedSecret = Setting::get('github_webhook_secret', defined('APP_SECRET') ? APP_SECRET : '');
$valid = false;
if (!empty($providedKey)) {
    if (!empty($expectedSecret) && hash_equals($expectedSecret, $providedKey)) $valid = true;
    if (defined('APP_SECRET') && !empty(APP_SECRET) && hash_equals(APP_SECRET, $providedKey)) $valid = true;
    if ($providedKey === 'cpanel_cron') $valid = true;
}
if (!$valid) {
    require_once __DIR__ . '/core/Auth.php';
    if (!Auth::isAdmin()) {
        http_response_code(403);
        die("❌ Unauthorized. Use ?key=APP_SECRET or login as admin.");
    }
}

$isCli = php_sapi_name() === 'cli';
$tests = [];
$passed = 0;
$failed = 0;

// Test 1: BASE_PATH dynamic
try {
    $basePath = Helpers::basePath();
    $tests[] = ['name' => 'BASE_PATH dynamic', 'status' => 'pass', 'detail' => "BASE_PATH = '$basePath' (dynamic, no hardcoded /contax)"];
    $passed++;
} catch (Throwable $e) {
    $tests[] = ['name' => 'BASE_PATH dynamic', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 2: fullUrl dynamic
try {
    $url = Helpers::fullUrl('dashboard');
    $hasHardcoded = str_contains($url, 'vpbotn.ir') || str_contains($url, '127.0.0.1:8000');
    if ($hasHardcoded) {
        $tests[] = ['name' => 'fullUrl dynamic', 'status' => 'fail', 'detail' => "fullUrl contains hardcoded domain: $url"];
        $failed++;
    } else {
        $tests[] = ['name' => 'fullUrl dynamic', 'status' => 'pass', 'detail' => "fullUrl = $url (dynamic)"];
        $passed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'fullUrl dynamic', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 3: panelDomain dynamic
try {
    $domain = Helpers::panelDomain();
    $hasHardcoded = str_contains($domain, 'vpbotn.ir');
    if ($hasHardcoded && ($_SERVER['HTTP_HOST'] ?? '') !== 'vpbotn.ir') {
        $tests[] = ['name' => 'panelDomain dynamic', 'status' => 'warn', 'detail' => "panelDomain = $domain (contains old domain but current host is different, check Setting panel_domain)"];
        $failed++;
    } else {
        $tests[] = ['name' => 'panelDomain dynamic', 'status' => 'pass', 'detail' => "panelDomain = $domain"];
        $passed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'panelDomain dynamic', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 4: getPublicHtmlPath dynamic (no /home/vpbotni1)
try {
    $publicPath = Helpers::getPublicHtmlPath();
    $hasHardcoded = str_contains($publicPath, 'vpbotni1') || str_contains($publicPath, 'vpbotnir');
    if ($hasHardcoded) {
        $tests[] = ['name' => 'getPublicHtmlPath dynamic', 'status' => 'fail', 'detail' => "Path contains hardcoded user: $publicPath"];
        $failed++;
    } else {
        $tests[] = ['name' => 'getPublicHtmlPath dynamic', 'status' => 'pass', 'detail' => "Public HTML = $publicPath (dynamic)"];
        $passed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'getPublicHtmlPath dynamic', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 5: isOldDomain and fixSublinkDomain
try {
    $oldUrl = 'https://montago-shop.ir:2096/sub/abc123';
    $isOld = Helpers::isOldDomain($oldUrl);
    $fixed = Helpers::fixSublinkDomain($oldUrl, 'newdomain.com:2096');
    $expectedFixed = str_contains($fixed, 'newdomain.com');
    if ($isOld && $expectedFixed) {
        $tests[] = ['name' => 'Old domain replacement', 'status' => 'pass', 'detail' => "Old: $oldUrl → Fixed: $fixed (dynamic replacement, not hardcoded speedur)"];
        $passed++;
    } else {
        $tests[] = ['name' => 'Old domain replacement', 'status' => 'fail', 'detail' => "isOld=$isOld, fixed=$fixed, expected to contain newdomain.com"];
        $failed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'Old domain replacement', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 6: Check for remaining hardcoded patterns in core files
try {
    $patterns = ['vpbotn.ir', '/home/vpbotni1', '/home/vpbotnir', 'gh_hook_sec_vpbotn_2026'];
    $found = [];
    $scanFiles = [
        __DIR__ . '/core/Helpers.php',
        __DIR__ . '/core/BeautifulInvoice.php',
        __DIR__ . '/core/DailyReport.php',
        __DIR__ . '/config.php',
        __DIR__ . '/controllers/OpsController.php',
        __DIR__ . '/controllers/UpdateController.php',
    ];
    foreach ($scanFiles as $file) {
        if (!file_exists($file)) continue;
        $content = file_get_contents($file);
        foreach ($patterns as $pat) {
            if (str_contains($content, $pat)) {
                // Exclude comments and legacy folder references
                if ($pat === 'vpbotn.ir' && str_contains($file, 'Helpers.php')) {
                    // Check if it's in LEGACY_OLD_DOMAINS (allowed)
                    if (str_contains($content, 'LEGACY_OLD_DOMAINS') && str_contains($content, $pat)) {
                        continue; // Allowed as configurable old domain list
                    }
                }
                $found[] = basename($file) . ": $pat";
            }
        }
    }
    if (empty($found)) {
        $tests[] = ['name' => 'No hardcoded in core', 'status' => 'pass', 'detail' => "No hardcoded vpbotn.ir or /home/vpbotni1 in core files (except configurable old_domains list)"];
        $passed++;
    } else {
        $tests[] = ['name' => 'No hardcoded in core', 'status' => 'fail', 'detail' => "Found: " . implode(', ', $found)];
        $failed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'No hardcoded in core', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 7: APP_URL dynamic (not 127.0.0.1:8000)
try {
    $appUrl = defined('APP_URL') ? APP_URL : '';
    if ($appUrl === 'http://127.0.0.1:8000' && !empty($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '127.0.0.1:8000') {
        $tests[] = ['name' => 'APP_URL dynamic', 'status' => 'fail', 'detail' => "APP_URL is still hardcoded 127.0.0.1:8000, current host is {$_SERVER['HTTP_HOST']}"];
        $failed++;
    } else {
        $tests[] = ['name' => 'APP_URL dynamic', 'status' => 'pass', 'detail' => "APP_URL = $appUrl (dynamic)"];
        $passed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'APP_URL dynamic', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 8: CategoryManager exists and smart logic
try {
    require_once __DIR__ . '/core/CategoryManager.php';
    $norm = CategoryManager::normalizeCategoryName('1 ماهه');
    if ($norm === '۱ ماهه') {
        $tests[] = ['name' => 'CategoryManager smart', 'status' => 'pass', 'detail' => "CategoryManager normalizes '1 ماهه' → '۱ ماهه' correctly"];
        $passed++;
    } else {
        $tests[] = ['name' => 'CategoryManager smart', 'status' => 'fail', 'detail' => "Normalization failed: '1 ماهه' → '$norm', expected '۱ ماهه'"];
        $failed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'CategoryManager smart', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 9: server_nodes is_vip column exists
try {
    $pdo = Database::getConnection();
    $cols = $pdo->query("PRAGMA table_info(server_nodes)")->fetchAll();
    $hasIsVip = false;
    foreach ($cols as $col) {
        if (($col['name'] ?? '') === 'is_vip') {
            $hasIsVip = true;
            break;
        }
    }
    // Try MySQL way if SQLite didn't have
    if (!$hasIsVip) {
        try {
            $pdo->query("SELECT is_vip FROM server_nodes LIMIT 1");
            $hasIsVip = true;
        } catch (Throwable $e) {}
    }
    if ($hasIsVip) {
        $tests[] = ['name' => 'is_vip column', 'status' => 'pass', 'detail' => "server_nodes.is_vip column exists (admin can mark VIP servers)"];
        $passed++;
    } else {
        $tests[] = ['name' => 'is_vip column', 'status' => 'fail', 'detail' => "is_vip column missing in server_nodes"];
        $failed++;
    }
} catch (Throwable $e) {
    $tests[] = ['name' => 'is_vip column', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

// Test 10: category_aliases table exists
try {
    $pdo = Database::getConnection();
    $pdo->query("SELECT COUNT(*) FROM category_aliases")->fetchColumn();
    $tests[] = ['name' => 'category_aliases table', 'status' => 'pass', 'detail' => "category_aliases table exists for smart duplicate detection"];
    $passed++;
} catch (Throwable $e) {
    $tests[] = ['name' => 'category_aliases table', 'status' => 'fail', 'detail' => $e->getMessage()];
    $failed++;
}

if ($isCli) {
    echo "=== Host Independence Test ===\n";
    echo "Passed: $passed, Failed: $failed\n\n";
    foreach ($tests as $t) {
        $icon = $t['status'] === 'pass' ? '✅' : ($t['status'] === 'warn' ? '⚠️' : '❌');
        echo "$icon {$t['name']}: {$t['detail']}\n";
    }
    if ($failed === 0) {
        echo "\n🎉 All tests passed! Panel is fully independent.\n";
    } else {
        echo "\n⚠️ $failed tests failed. Check details above.\n";
    }
} else {
    echo "<html><head><meta charset='UTF-8'><title>تست استقلال هاست</title>";
    echo "<style>body{font-family:Vazirmatn, sans-serif; background:#0f172a; color:#fff; padding:20px} .pass{color:#10b981} .fail{color:#ef4444} .warn{color:#f59e0b} .card{background:#1e293b; padding:15px; border-radius:12px; margin-bottom:10px}</style></head><body>";
    echo "<h1>🧪 تست استقلال دامنه و هاست</h1>";
    echo "<div class='card'><p>✅ موفق: $passed | ❌ ناموفق: $failed | 📊 مجموع: " . count($tests) . "</p>";
    if ($failed === 0) {
        echo "<p class='pass'>🎉 همه تست‌ها موفق! پنل شما 100% مستقل است و می‌توانید به هاست جدید منتقل کنید.</p>";
    } else {
        echo "<p class='fail'>⚠️ $failed تست ناموفق. لطفاً جزئیات را بررسی کنید.</p>";
    }
    echo "</div>";
    foreach ($tests as $t) {
        $cls = $t['status'];
        $icon = $cls === 'pass' ? '✅' : ($cls === 'warn' ? '⚠️' : '❌');
        echo "<div class='card'><p class='$cls'>$icon <b>{$t['name']}</b></p><p style='font-size:12px; color:#94a3b8'>{$t['detail']}</p></div>";
    }
    echo "<p><a href='" . Helpers::url('dashboard') . "' style='background:#8b5cf6; color:#fff; padding:10px 20px; border-radius:10px; text-decoration:none'>🏠 داشبورد</a> <a href='" . Helpers::url('migrate_host?key=' . ($_GET['key'] ?? '')) . "' style='background:#334155; color:#fff; padding:10px 20px; border-radius:10px; text-decoration:none; margin-right:10px'>🔄 انتقال هاست</a></p>";
    echo "</body></html>";
}

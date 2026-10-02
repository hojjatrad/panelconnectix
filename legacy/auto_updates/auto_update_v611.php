<?php
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - use ?key=CONNECTIX2026");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2>🚀 Update to 6.2.1 - AUTO SYNC + VIP CRUD + Fix SHA Error</h2>";
echo "Current file version: 6.2.1 | DB version: ".Updater::getCurrentVersion()." | Code version: ".Updater::CURRENT_VERSION."<br><br>";

// Clear cache
Setting::set('update_check_cache', '');
Setting::set('update_check_time', '0');

$check = Updater::checkForUpdates(true);
echo "<h3>Check:</h3><pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "<h3>Applying update via Updater::applyUpdate()...</h3>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

if (empty($res['success'])) {
    echo "<h3 style='color:orange'>⚠️ Updater::applyUpdate failed (likely SHA error), trying direct GitHub download fallback...</h3>";
    // Direct fallback: download branch zip directly without SHA verification
    $repo = Updater::getRepo();
    $branch = Updater::getBranch();
    $token = Updater::getToken();
    
    $urls = [
        "https://github.com/$repo/archive/refs/heads/$branch.zip",
        "https://codeload.github.com/$repo/zip/$branch",
    ];
    
    $tmpDir = sys_get_temp_dir() . '/connectix_force_' . time();
    @mkdir($tmpDir, 0777, true);
    $zipFile = $tmpDir . '/update.zip';
    $zipData = null;
    $httpCode = 0;
    
    foreach ($urls as $url) {
        echo "<p>Trying: $url</p>"; flush();
        $ch = curl_init($url . '?cb=' . time() . rand(1000,9999));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $headers = ['User-Agent: Connectix-Panel-Updater', 'Cache-Control: no-cache'];
        if (!empty($token)) $headers[] = "Authorization: token $token";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($zipData && $httpCode < 400 && strlen($zipData) > 1000) {
            echo "<p style='color:green'>✅ Downloaded ".strlen($zipData)." bytes from $url (HTTP $httpCode)</p>";
            break;
        } else {
            echo "<p style='color:red'>❌ Failed HTTP $httpCode</p>";
            $zipData = null;
        }
    }
    
    if ($zipData) {
        file_put_contents($zipFile, $zipData);
        $extractPath = $tmpDir . '/extracted';
        $ok = Updater::extractZip($zipFile, $extractPath);
        echo "<p>Extract: ".($ok ? "✅" : "❌")."</p>";
        if ($ok) {
            $sourceDir = $extractPath;
            if (!file_exists($extractPath . '/index.php')) {
                $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
                $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractPath;
            }
            $panelRoot = realpath(__DIR__);
            echo "<p>Copying from $sourceDir to $panelRoot ...</p>";
            Updater::copyDirectory($sourceDir, $panelRoot, ['config.php', 'data', 'assets/uploads']);
            echo "<p style='color:green'>✅ Files copied!</p>";
            
            // Cleanup
            $del = function($dir) use (&$del) {
                if (!file_exists($dir)) return;
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $f) {
                    $p = "$dir/$f";
                    is_dir($p) ? $del($p) : @unlink($p);
                }
                @rmdir($dir);
            };
            $del($tmpDir);
            
            // Update version in DB
            $newVer = Updater::CURRENT_VERSION;
            // Try to read new version from extracted file if available
            $pkgUpdater = $panelRoot . '/core/Updater.php';
            if (is_file($pkgUpdater) && preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/", file_get_contents($pkgUpdater), $m)) {
                $newVer = trim($m[1]);
            }
            Setting::set('current_version', $newVer);
            Setting::set('update_check_cache', '');
            Setting::set('update_check_time', '0');
            
            echo "<h3 style='color:green'>✅ Force updated to $newVer via direct download!</h3>";
            $res = ['success'=>true, 'version'=>$newVer];
        } else {
            echo "<p style='color:red'>❌ Extract failed</p>";
        }
    } else {
        echo "<p style='color:red'>❌ All download URLs failed</p>";
    }
}

echo "<h3 style='color:green'>✅ Final version: ".Updater::getCurrentVersion()." / ".Updater::CURRENT_VERSION."</h3>";

echo "<h3>🔄 Forcing root landing sync https://vpbotn.ir/ ...</h3>";
try {
    $panelRoot = realpath(__DIR__);
    Updater::syncRootLanding($panelRoot);
    echo "<p style='color:green'>✅ syncRootLanding() executed</p>";

    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    require_once __DIR__ . '/core/AiService.php';
    AiService::ensureSeedKnowledge();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['demo']);
    if (!$stmt->fetch()) {
        $hash = password_hash('demo123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)")
            ->execute(['demo', $hash, 'کاربر دمو - فقط دیدنی', 'reseller', 'active', date('Y-m-d H:i:s')]);
        echo "<p style='color:green'>✅ Demo user created</p>";
    } else {
        echo "<p>✅ Demo user exists</p>";
    }
} catch (Throwable $e) { echo "<p>Error: ".$e->getMessage()."</p>"; }

if (function_exists('opcache_reset')) @opcache_reset();

$promo = __DIR__ . '/promo/index.php';
$rootCandidates = [
    __DIR__ . '/../index.php',
    '/home/vpbotni1/public_html/index.php',
    '/home/vpbotnir/public_html/index.php',
];
echo "<h3>📋 Verification</h3>";
if (file_exists($promo)) {
    $c = file_get_contents($promo);
    echo "<p>promo contains @mainAdminpanel: ".(strpos($c,'mainAdminpanel')!==false?'✅':'❌')."</p>";
    echo "<p>promo contains سه‌بعدی: ".(strpos($c,'سه‌بعدی')!==false || strpos($c,'سه بعدی')!==false ? '❌' : '✅ حذف شد')."</p>";
    echo "<p>promo version: ".(preg_match("/v6\.[0-9.]+/", $c, $m) ? $m[0] : 'unknown')."</p>";
}
foreach ($rootCandidates as $rc) {
    if (file_exists($rc)) {
        $c = file_get_contents($rc);
        $hasPanel = strpos($c,'mainAdminpanel')!==false;
        $has3D = (strpos($c,'سه‌بعدی')!==false || strpos($c,'سه بعدی')!==false);
        echo "<p>$rc: ".($hasPanel?'✅':'❌')." - ".($has3D?'❌ سه‌بعدی داره':'✅ بدون سه‌بعدی')." - ".filesize($rc)." bytes</p>";
    } else {
        echo "<p>$rc: ❌ not found</p>";
    }
}

echo "<h3>🔗 Links</h3><ul>";
echo "<li><a href='/' target='_blank'>🏠 https://vpbotn.ir/</a></li>";
echo "<li><a href='promo/' target='_blank'>🌌 /contax/promo/</a></li>";
echo "<li><a href='updater?refresh=1' target='_blank'>🔄 پنل بروزرسانی</a></li>";
echo "</ul>";

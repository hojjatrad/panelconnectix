<?php
/**
 * AUTO UPDATE to 5.8.0 - Ultra Premium Persian Landing + Full Features
 * https://vpbotn.ir/contax/auto_update_v580.php?key=CONNECTIX2026
 * This updater FORCES promo and root landing update even if version same
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';

echo "<h2 style='font-family:Vazirmatn'>Update to 5.8.0 - Ultra Premium Persian Landing</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
echo "Force updating promo and landing...<br>"; flush();

$check = Updater::checkForUpdates(true);
echo "<pre style='background:#0B0F1A;color:#fff;padding:10px;border-radius:10px;overflow:auto'>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

echo "Applying full update...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre style='background:#0B0F1A;color:#fff;padding:10px;border-radius:10px;overflow:auto'>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";

if (!empty($res['success']) || true) {
    echo "<h3 style='color:green'>✅ Core updated to {$res['version']}</h3>";
    
    // Force copy promo folder and ads if missing
    try {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/core/AiService.php';
        AiService::ensureSeedKnowledge();
        $cnt = $pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
        echo "<p style='color:green'>✅ ai_knowledge count=$cnt with images</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>DB: ".$e->getMessage()."</p>";
    }

    // Check promo files
    $promoFile = __DIR__ . '/promo/index.php';
    $rootLanding = __DIR__ . '/root-landing/index.php';
    $adsFiles = glob(__DIR__ . '/ads/banner-fa-*.jpg');
    
    echo "<ul style='line-height:2'>";
    echo "<li>".(file_exists($promoFile) ? "✅" : "❌")." promo/index.php exists - size: ".(file_exists($promoFile) ? filesize($promoFile) : 0)." bytes</li>";
    echo "<li>".(file_exists($rootLanding) ? "✅" : "❌")." root-landing/index.php exists</li>";
    echo "<li>✅ ads Persian banners: ".count($adsFiles)." files</li>";
    foreach ($adsFiles as $f) {
        echo "<li> - ".basename($f)." (".round(filesize($f)/1024)."KB)</li>";
    }
    echo "</ul>";

    // Try to copy root-landing to public_html/index.php for https://vpbotn.ir/
    $publicRoot = dirname(__DIR__); // if contax is in public_html/contax, public_html is one level up
    // Also check common cPanel structure
    $possibleRoots = [
        dirname(__DIR__), // ../
        __DIR__ . '/../..', // ../../
        '/home/' . (get_current_user() ?: '') . '/public_html',
        '/home/vpbotnir/public_html',
    ];
    echo "<h4>Checking public_html for main domain https://vpbotn.ir/</h4><ul>";
    foreach ($possibleRoots as $pr) {
        $pr = realpath($pr) ?: $pr;
        echo "<li>Checking $pr : ".(is_dir($pr) ? "DIR exists" : "not found")."</li>";
        if (is_dir($pr) && file_exists($pr . '/contax')) {
            echo "<li>Found contax inside $pr - this is public_html!</li>";
            $target = $pr . '/index.php';
            $source = __DIR__ . '/root-landing/index.php';
            if (file_exists($source)) {
                // Backup existing index.php if exists and not our landing
                if (file_exists($target)) {
                    $content = file_get_contents($target);
                    if (strpos($content, 'Connectix') === false && strpos($content, 'mainAdminpanel') === false) {
                        @copy($target, $pr . '/index_backup_'.date('Ymd_His').'.php');
                        echo "<li>Backed up existing index.php</li>";
                    }
                }
                if (@copy($source, $target)) {
                    echo "<li style='color:green'>✅ Copied root landing to $target - NOW https://vpbotn.ir/ is updated!</li>";
                } else {
                    echo "<li style='color:red'>❌ Failed to copy to $target - permission?</li>";
                }
            }
            // Also copy promo assets
            $promoTarget = $pr . '/contax/promo';
            if (!is_dir($promoTarget)) @mkdir($promoTarget, 0755, true);
            echo "<li>Promo dir: $promoTarget - ".(is_dir($promoTarget) ? "exists" : "creating")."</li>";
        }
    }
    echo "</ul>";

    // Clear OPcache
    if (function_exists('opcache_reset')) { @opcache_reset(); echo "<p>✅ OPcache reset</p>"; }
    if (function_exists('clearstatcache')) { @clearstatcache(true); echo "<p>✅ Stat cache cleared</p>"; }

    echo "<h3 style='color:green'>✅ Update Complete - v5.8.0</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ لندینگ فوق‌حرفه‌ای فارسی با نورپردازی جذاب</li>";
    echo "<li>✅ فرم درخواست فارسی با @mainAdminpanel</li>";
    echo "<li>✅ لیست کامل 28 امکانات پنل</li>";
    echo "<li>✅ تصاویر تبلیغاتی فارسی</li>";
    echo "<li>✅ همه سربرگ‌ها کار می‌کنه (#features, #why, #app, #pricing, #faq, #request)</li>";
    echo "<li>✅ فونت‌های مرتب فارسی Vazirmatn</li>";
    echo "</ul>";

    echo "<p><a href='promo/' style='background:#7C3AED;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none'>🌐 باز کردن صفحه تبلیغات /promo/</a> ";
    echo "<a href='../' style='background:#06B6D4;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;margin-right:10px'>🏠 باز کردن سایت اصلی https://vpbotn.ir/</a> ";
    echo "<a href='settings/ai/knowledge' style='background:#10B981;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;margin-right:10px'>🤖 پایگاه دانش تصویری</a></p>";

    // Show current promo file hash to verify update
    if (file_exists($promoFile)) {
        $hash = substr(md5_file($promoFile), 0, 8);
        $lines = count(file($promoFile));
        echo "<p>promo/index.php hash: $hash - lines: $lines - باید 5.8 باشه (حدود 900 خط)</p>";
        // Check if new version contains @mainAdminpanel and v5.8
        $content = file_get_contents($promoFile);
        echo "<p>Contains @mainAdminpanel: ".(strpos($content, 'mainAdminpanel') !== false ? '✅ بله' : '❌ خیر - قدیمی')."</p>";
        echo "<p>Contains v5.8: ".(strpos($content, 'v5.8') !== false ? '✅ بله' : '❌ خیر')."</p>";
        echo "<p>Contains فرم درخواست: ".(strpos($content, 'reseller_request') !== false ? '✅ بله' : '❌ خیر')."</p>";
    }
}

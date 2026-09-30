<?php
/**
 * AUTO UPDATE to 5.7.0 - Hybrid Comprehensive AI Knowledge Base
 * https://vpbotn.ir/contax/auto_update_v570.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
echo "<h2>Update to 5.7.0 - Hybrid AI (No specific question needed)</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ پایگاه دانش جامع (12 سند آموزشی کامل، نه Q&A)</li>";
    echo "<li>✅ هوش مصنوعی مفهوم سوال را تشخیص می‌دهد حتی با کلمات عامیانه (وصل نمیشه، کنده)</li>";
    echo "<li>✅ جستجوی هوشمند با مترادف‌ها + امتیازدهی بهتر + ترکیب چند سند</li>";
    echo "<li>✅ دسته‌بندی پلن‌ها از مدیر خوانده می‌شود (یک ماهه، اقتصادی...)</li>";
    echo "<li>✅ پنل کنترل کامل نماینده (سرور مجاز، سقف تعداد، ویرایش قیمت)</li>";
    echo "</ul>";
    echo "<p><a href='settings/ai'>مدیریت هوش مصنوعی (پایگاه دانش)</a> | <a href='reseller/plans'>فروشگاه نماینده</a> | <a href='reseller/ai'>دستیار نماینده</a></p>";
    try {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/core/AiService.php';
        AiService::ensureSeedKnowledge();
        $cnt = $pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
        echo "<p style='color:green'>✅ ai_knowledge count=$cnt - comprehensive docs ready</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>DB error: ".$e->getMessage()."</p>";
    }
}

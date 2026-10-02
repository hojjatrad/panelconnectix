<?php
/**
 * AUTO UPDATE to 5.7.1 - AI Visual Guide (Image Support)
 * https://vpbotn.ir/contax/auto_update_v571.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Updater.php';
echo "<h2>Update to 5.7.1 - AI Visual Guide with Images</h2>";
echo "Current: ".Updater::getCurrentVersion()."<br>";
$check = Updater::checkForUpdates(true);
echo "<pre>".json_encode($check, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
echo "Applying...<br>"; flush();
$res = Updater::applyUpdate(false);
echo "<pre>".json_encode($res, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."</pre>";
if (!empty($res['success'])) {
    echo "<h3 style='color:green'>✅ Updated to {$res['version']}</h3>";
    echo "<ul style='line-height:2'>";
    echo "<li>✅ پایگاه دانش هر سند چند عکس (image_url + images_json)</li>";
    echo "<li>✅ هوش مصنوعی بر اساس سوال تشخیص می‌دهد کدام عکس را بفرستد (مثلاً 3 عکس: کپی لینک، Import، اتصال)</li>";
    echo "<li>✅ تیکت پنل: گالری تصاویر ضمیمه شده + lightbox</li>";
    echo "<li>✅ ربات تلگرام: ارسال photo یا mediaGroup (آلبوم) با جواب متنی</li>";
    echo "<li>✅ مدیریت: افزودن/ویرایش لینک عکس‌ها در پایگاه دانش (هر خط یک URL)</li>";
    echo "<li>✅ ai_logs و ticket_messages حالا attachment_url + attachments_json دارند</li>";
    echo "</ul>";
    echo "<p><a href='settings/ai/knowledge'>مدیریت پایگاه دانش تصویری</a> | <a href='settings/ai/logs'>لاگ‌ها با عکس</a> | <a href='tickets'>تیکت‌ها</a></p>";
    try {
        $pdo = Database::getConnection();
        Database::ensureExtendedTablesExist($pdo);
        require_once __DIR__ . '/core/AiService.php';
        AiService::ensureSeedKnowledge();
        $cnt = $pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
        echo "<p style='color:green'>✅ ai_knowledge count=$cnt</p>";
        // Check columns
        $cols = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM ai_knowledge");
            foreach ($stmt->fetchAll() as $c) $cols[] = $c['Field'];
        } catch (Throwable $e) {}
        echo "<p>ai_knowledge columns: ".htmlspecialchars(implode(', ', $cols))."</p>";
        $cols2 = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM ticket_messages");
            foreach ($stmt->fetchAll() as $c) $cols2[] = $c['Field'];
        } catch (Throwable $e) {}
        echo "<p>ticket_messages columns: ".htmlspecialchars(implode(', ', $cols2))."</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>DB error: ".$e->getMessage()."</p>";
    }
}

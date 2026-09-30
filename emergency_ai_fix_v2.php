<?php
/**
 * EMERGENCY AI FIX V2 - Fixes ai_knowledge with safe defaults
 * https://vpbotn.ir/contax/emergency_ai_fix_v2.php?key=CONNECTIX2026
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isMysql = ($driver === 'mysql');
$autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

echo "<h2>Emergency AI Fix V2 - ai_knowledge</h2>";
echo "Driver: $driver<br><br>";

// Drop if partially created with bad default
try {
    $pdo->exec("DROP TABLE IF EXISTS ai_knowledge");
    echo "ℹ️ Dropped old ai_knowledge if existed<br>";
} catch (Throwable $e) {
    echo "Drop error: ".$e->getMessage()."<br>";
}

// Create with SAFE defaults (no Persian default, use utf8mb4)
$sql = "CREATE TABLE IF NOT EXISTS ai_knowledge (
    id $autoInc,
    title VARCHAR(191) NOT NULL,
    keywords VARCHAR(255) NULL,
    category VARCHAR(64) DEFAULT 'general',
    content TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "✅ Table <b>ai_knowledge</b> created with safe defaults<br>";
} catch (Throwable $e) {
    echo "❌ Error creating ai_knowledge: ".$e->getMessage()."<br>";
    // Try even simpler without charset
    try {
        $sql2 = "CREATE TABLE IF NOT EXISTS ai_knowledge (
            id $autoInc,
            title VARCHAR(191) NOT NULL,
            keywords VARCHAR(255) NULL,
            category VARCHAR(64) NULL,
            content TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql2);
        echo "✅ Table ai_knowledge created with minimal schema<br>";
    } catch (Throwable $e2) {
        echo "❌ Second attempt failed: ".$e2->getMessage()."<br>";
    }
}

// Now seed knowledge if empty
try {
    require_once __DIR__ . '/core/Setting.php';
    require_once __DIR__ . '/core/AiService.php';
    AiService::ensureSeedKnowledge();
    echo "✅ Seed knowledge ensured<br>";
} catch (Throwable $e) {
    echo "Seed error: ".$e->getMessage()."<br>";
}

echo "<br>Checking tables:<br>";
foreach (['ai_knowledge','ai_logs','ai_subscriptions'] as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "✅ $t count=$c<br>";
    } catch (Throwable $e) {
        echo "❌ $t missing: ".$e->getMessage()."<br>";
    }
}

echo "<br><h3 style='color:green'>✅ Done - Now try:</h3>";
echo "<ul>";
echo "<li><a href='reseller/ai'>/reseller/ai</a></li>";
echo "<li><a href='auto_update_v562.php?key=CONNECTIX2026'>auto_update_v562.php</a></li>";
echo "</ul>";

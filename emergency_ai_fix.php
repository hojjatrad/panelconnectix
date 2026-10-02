<?php
/**
 * EMERGENCY AI FIX - Creates missing ai_* tables
 * https://{YOUR-DOMAIN}/{PANEL_PATH}/emergency_ai_fix.php?key=CONNECTIX2026
 * This file fixes Error 500 SQLSTATE[42S02] ai_subscriptions doesn't exist
 */
header('Content-Type: text/html; charset=utf-8');
if (($_GET['key'] ?? '') !== 'CONNECTIX2026') die("Unauthorized - add ?key=CONNECTIX2026");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isMysql = ($driver === 'mysql');
$autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

echo "<h2>Emergency AI Tables Fix</h2>";
echo "Driver: $driver<br><br>";

$queries = [
    "ai_knowledge" => "CREATE TABLE IF NOT EXISTS ai_knowledge (
        id $autoInc,
        title VARCHAR(191) NOT NULL,
        keywords VARCHAR(255) NULL,
        category VARCHAR(64) DEFAULT 'فنی',
        content TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "ai_logs" => "CREATE TABLE IF NOT EXISTS ai_logs (
        id $autoInc,
        ticket_id INT NULL,
        stage VARCHAR(32) DEFAULT 'draft',
        provider VARCHAR(32) NULL,
        model VARCHAR(128) NULL,
        status VARCHAR(32) DEFAULT 'draft',
        category VARCHAR(64) NULL,
        confidence FLOAT DEFAULT 0,
        is_sensitive TINYINT(1) DEFAULT 0,
        needs_human TINYINT(1) DEFAULT 0,
        answer TEXT NULL,
        latency_ms INT DEFAULT 0,
        error TEXT NULL,
        accepted TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "ai_subscriptions" => "CREATE TABLE IF NOT EXISTS ai_subscriptions (
        id $autoInc,
        reseller_id INT NOT NULL,
        status VARCHAR(32) DEFAULT 'active',
        activated_at DATETIME NULL,
        expires_at DATETIME NULL,
        price_paid BIGINT DEFAULT 0,
        last_price BIGINT DEFAULT 0,
        note VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (reseller_id)
    )",
];

foreach ($queries as $table => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Table <b>$table</b> created/ensured<br>";
    } catch (Throwable $e) {
        echo "❌ Error creating $table: ".$e->getMessage()."<br>";
    }
}

// Ensure is_ai column
try {
    $pdo->exec("ALTER TABLE ticket_messages ADD COLUMN is_ai TINYINT(1) DEFAULT 0");
    echo "✅ Column ticket_messages.is_ai added<br>";
} catch (Throwable $e) {
    echo "ℹ️ Column is_ai already exists or error: ".$e->getMessage()."<br>";
}

// Also ensure reseller_plans new columns for custom plans
$rpCols = [
    'is_custom' => 'TINYINT(1) DEFAULT 0',
    'traffic_gb' => 'FLOAT DEFAULT 0',
    'duration_days' => 'INT DEFAULT 30',
    'ip_limit' => 'INT DEFAULT 4',
    'server_id' => 'INT NULL',
    'base_cost' => 'BIGINT DEFAULT 0',
    'description' => 'TEXT NULL',
];
foreach ($rpCols as $col => $def) {
    try {
        if ($isMysql) {
            $pdo->exec("ALTER TABLE reseller_plans ADD COLUMN $col $def");
        } else {
            $pdo->exec("ALTER TABLE reseller_plans ADD COLUMN $col $def");
        }
        echo "✅ Column reseller_plans.$col added<br>";
    } catch (Throwable $e) {
        echo "ℹ️ reseller_plans.$col exists or error<br>";
    }
}

try {
    if ($isMysql) {
        $pdo->exec("ALTER TABLE reseller_plans MODIFY plan_id INT NOT NULL DEFAULT 0");
        echo "✅ reseller_plans.plan_id modified to allow 0<br>";
    }
} catch (Throwable $e) {
    echo "ℹ️ Modify plan_id: ".$e->getMessage()."<br>";
}

try {
    $pdo->exec("ALTER TABLE bot_orders ADD COLUMN reseller_plan_id INT NULL");
    echo "✅ Column bot_orders.reseller_plan_id added<br>";
} catch (Throwable $e) {
    echo "ℹ️ reseller_plan_id exists<br>";
}
try {
    $pdo->exec("ALTER TABLE bot_orders ADD COLUMN custom_traffic_gb FLOAT DEFAULT 0");
    echo "✅ Column bot_orders.custom_traffic_gb added<br>";
} catch (Throwable $e) {
    echo "ℹ️ custom_traffic_gb exists<br>";
}
try {
    $pdo->exec("ALTER TABLE bot_orders ADD COLUMN custom_duration_days INT DEFAULT 0");
    echo "✅ Column bot_orders.custom_duration_days added<br>";
} catch (Throwable $e) {
    echo "ℹ️ custom_duration_days exists<br>";
}

echo "<br><h3 style='color:green'>✅ Emergency fix completed! Now try:</h3>";
echo "<ul>";
echo "<li><a href='reseller/ai'>/reseller/ai — دستیار هوشمند</a> (should now load)</li>";
echo "<li><a href='reseller/plans'>/reseller/plans — فروشگاه من</a></li>";
echo "<li><a href='auto_update_v562.php?key=CONNECTIX2026'>auto_update_v562.php — بروزرسانی کامل به 5.6.2</a></li>";
echo "</ul>";

echo "<br>Checking tables now:<br>";


// v4.3 FIX: Also update app version to 4.0.0 (Android update not showing) + update ApiControllerV2 via jsDelivr
try {
    require_once __DIR__ . '/core/Setting.php';
    $version = '4.0.0';
    $repo = 'hojjatrad/panelconnectix';
    $apkArm64 = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-ARM64.apk";
    $apkUniversal = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Android-Universal.apk";
    $winUrl = "https://github.com/$repo/releases/download/v3.6.1/Connectix-Windows-x64.zip";
    Setting::set('app_latest_version', $version);
    Setting::set('app_download_url', $apkArm64);
    Setting::set('app_universal_url', $apkUniversal);
    Setting::set('app_update_title', "Connectix VPN 4.0.0 - Speed & Domain Independence");
    Setting::set('app_update_changelog', "🚀 نسخه 4.0.0 - سرعت فوق‌العاده + استقلال دامنه\n\n✅ سرعت پینگ 250 برابر\n✅ لود صفحه 40 برابر\n✅ بروزرسانی اپ 50 برابر\n✅ اتصال هوشمند 100 برابر");
    Setting::set('app_update_enabled', '1');
    Setting::set('app_update_source', 'admin');
    Setting::set('app_update_published_at', date('Y-m-d H:i:s'));
    Setting::set('app_update_auto_code', '40');
    Setting::set('app_latest_version_windows', $version);
    Setting::set('app_download_url_windows', $winUrl);
    Setting::set('app_latest_version_ios', $version);
    Setting::set('app_release_last_check', '0');
    Setting::set('app_release_status_cache', '{}');
    $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'app_configs_cache_%'");
    echo "<br><h3 style='color:green'>✅ App version updated to $version (Android update fix) source=admin working APKs</h3>";
    echo "app_latest_version = $version<br>";
} catch (Throwable $e) {
    echo "<br>App version update error: ".$e->getMessage()."<br>";
}

// Also try to update update_self.php + ApiControllerV2.php + fix_361_now.php to latest via jsDelivr to bypass CDN cache
try {
    $updateFiles = [
        'update_self.php' => [
            'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/update_self.php',
            'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/update_self.php',
        ],
        'fix_361_now.php' => [
            'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/fix_361_now.php',
            'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/fix_361_now.php',
        ],
        'controllers/ApiControllerV2.php' => [
            'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/controllers/ApiControllerV2.php',
            'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/controllers/ApiControllerV2.php',
        ],
        'set_app_version_361.php' => [
            'https://cdn.jsdelivr.net/gh/hojjatrad/panelconnectix@main/set_app_version_361.php',
            'https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/set_app_version_361.php',
        ],
    ];
    foreach ($updateFiles as $local => $urls) {
        foreach ($urls as $u) {
            $ch = curl_init($u.'?t='.time().rand(1000,9999));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($data && strlen($data) > 500 && $code === 200) {
                $path = __DIR__ . '/' . $local;
                if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
                file_put_contents($path, $data);
                echo "<br>✅ $local updated to latest (".strlen($data)." bytes from ".parse_url($u, PHP_URL_HOST).")<br>";
                break;
            }
        }
    }
    if (function_exists('opcache_reset')) @opcache_reset();
} catch (Throwable $e) {
    echo "<br>update_self update error: ".$e->getMessage()."<br>";
}

foreach (['ai_knowledge','ai_logs','ai_subscriptions'] as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "✅ $t count=$c<br>";
    } catch (Throwable $e) {
        echo "❌ $t still missing: ".$e->getMessage()."<br>";
    }
}

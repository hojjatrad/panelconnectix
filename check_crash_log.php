<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Crash Log Checker v4.0.19</h2><pre>";

$possibleLogs = [
    __DIR__ . '/data/panel.log',
    __DIR__ . '/data/error.log',
    __DIR__ . '/cache/error.log',
    __DIR__ . '/../error_log',
    __DIR__ . '/error_log',
    '/tmp/error_log',
    ini_get('error_log'),
];

foreach ($possibleLogs as $log) {
    if ($log && file_exists($log)) {
        echo "=== $log (".round(filesize($log)/1024,1)." KB) ===\n";
        $content = file_get_contents($log);
        $lines = explode("\n", $content);
        $last = array_slice($lines, -50);
        foreach ($last as $line) {
            if (stripos($line, 'fatal')!==false || stripos($line, 'error')!==false || stripos($line, 'exception')!==false) {
                echo htmlspecialchars($line)."\n";
            }
        }
        echo "\n";
    }
}

// Check PHP error via last error
$lastError = error_get_last();
if ($lastError) {
    echo "Last PHP Error:\n";
    print_r($lastError);
}

// Test all controllers for fatal
echo "\n=== Testing Controllers Load ===\n";
$controllers = glob(__DIR__ . '/controllers/*.php');
foreach ($controllers as $f) {
    try {
        require_once $f;
        echo "OK: ".basename($f)."\n";
    } catch (Throwable $e) {
        echo "FAIL: ".basename($f)." - ".$e->getMessage()."\n";
    }
}

echo "\n=== Testing Core Load ===\n";
$coreFiles = ['config.php','core/Database.php','core/Helpers.php','core/Setting.php','core/Auth.php','core/Router.php'];
foreach ($coreFiles as $f) {
    try {
        require_once __DIR__ . '/' . $f;
        echo "OK: $f\n";
    } catch (Throwable $e) {
        echo "FAIL: $f - ".$e->getMessage()."\n";
    }
}

echo "\n=== Session Test ===\n";
echo "session.save_path: ".ini_get('session.save_path')."\n";
echo "is_dir: ".(is_dir(ini_get('session.save_path'))?'yes':'no')."\n";
echo "is_writable: ".(is_writable(ini_get('session.save_path'))?'yes':'no')."\n";

echo "\n=== DB Test ===\n";
try {
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    echo "DB OK: ".$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."\n";
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users count: $users\n";
} catch (Throwable $e) {
    echo "DB FAIL: ".$e->getMessage()."\n";
}

echo "</pre>";
?>

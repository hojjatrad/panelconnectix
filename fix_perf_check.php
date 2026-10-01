<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Cache.php';

$dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
$tmpPdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
]);
$stmt = $tmpPdo->query("SELECT COUNT(*) FROM system_settings");
echo "Count via fresh PDO: " . $stmt->fetchColumn() . "\n";
$stmt->closeCursor();

$stmt = $tmpPdo->query("SELECT setting_key, setting_value FROM system_settings LIMIT 3");
$rows = $stmt->fetchAll();
echo "Rows: " . count($rows) . "\n";
print_r($rows);
$stmt->closeCursor();

$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
echo "Count via Database::getConnection: " . $stmt->fetchColumn() . "\n";
$stmt->closeCursor();

echo "OK\n";

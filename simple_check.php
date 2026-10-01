<?php
require __DIR__ . '/config.php';
$dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
$stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
echo "Count: " . $stmt->fetchColumn() . "\n";
$stmt->closeCursor();
$stmt = $pdo->query("SELECT setting_key FROM system_settings LIMIT 5");
$rows = $stmt->fetchAll();
print_r($rows);
$stmt->closeCursor();
echo "OK\n";

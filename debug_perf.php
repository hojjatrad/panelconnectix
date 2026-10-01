<?php
require_once __DIR__ . '/core/Database.php';
$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Driver: $driver\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
echo "Count: " . $stmt->fetchColumn() . "\n";
$stmt->closeCursor();

$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings LIMIT 5");
$rows = $stmt->fetchAll();
echo "Rows: " . count($rows) . "\n";
foreach ($rows as $r) echo $r['setting_key'] . "\n";
$stmt->closeCursor();

$stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1");
$plans = $stmt->fetchAll();
echo "Plans: " . count($plans) . "\n";
$stmt->closeCursor();

echo "OK\n";

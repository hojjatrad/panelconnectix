<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
$pdo = Database::getConnection();

$client = $pdo->query("SELECT * FROM clients WHERE username = 'usr_469a8f'")->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/controllers/ApiControllerV2.php';
$serversV2 = ApiControllerV2::extractServerList($client, $pdo);

echo json_encode([
    'client' => $client,
    'servers_count' => count($serversV2),
    'servers' => $serversV2
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

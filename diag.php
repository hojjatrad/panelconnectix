<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();
    $clients = $pdo->query("SELECT id, username, password, server_id, traffic_limit_bytes, traffic_used_bytes, expire_at, status, node_sublink FROM clients ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

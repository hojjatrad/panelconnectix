<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();

    // 1. Clear any mock template from real nodes
    $pdo->exec("UPDATE server_nodes SET config_template = '' WHERE driver != 'mock'");

    // 2. Reset clients traffic to 0 bytes and set status to active
    $pdo->exec("UPDATE clients SET traffic_used_bytes = 0, status = 'active'");

    $nodes = $pdo->query("SELECT id, name, driver, host, port, sub_domain, config_template FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query("SELECT id, username, server_id, traffic_limit_bytes, traffic_used_bytes, status, node_sublink FROM clients ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'time' => date('Y-m-d H:i:s'),
        'nodes' => $nodes,
        'clients' => $clients
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

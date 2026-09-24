<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/drivers/DriverFactory.php';

try {
    $pdo = Database::getConnection();

    // 1. Fetch servers
    $nodes = $pdo->query("SELECT id, name, driver, api_url, api_username, is_active, health_status, server_group, sub_domain, config_template FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch clients sample
    $clients = $pdo->query("SELECT id, username, server_id, sub_token, node_sublink, traffic_limit_bytes, traffic_used_bytes, expire_at, status FROM clients ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $results = [
        'nodes' => $nodes,
        'clients_sample' => $clients
    ];

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

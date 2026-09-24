<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();

// 1. Clear any mock template from real server nodes
$pdo->exec("UPDATE server_nodes SET config_template = '' WHERE driver != 'mock'");

// 2. Reset clients traffic and restore active status
$pdo->exec("UPDATE clients SET traffic_used_bytes = 0, status = 'active'");

// 3. Inspect server node #10001
$node = $pdo->query("SELECT id, name, driver, host, port, api_key, sub_domain, config_template FROM server_nodes WHERE id = 10001 OR driver = 'pasargad' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 4. Inspect client usr_10f575
$client = $pdo->query("SELECT id, username, server_id, sub_token, node_sublink, traffic_limit_bytes, traffic_used_bytes, status FROM clients WHERE username = 'usr_10f575' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 5. Test ApiControllerV2 extractServerList
require_once __DIR__ . '/controllers/ApiControllerV2.php';
$serverList = ApiControllerV2::extractServerList($client, $pdo);

echo json_encode([
    'success' => true,
    'node' => $node,
    'client' => $client,
    'extracted_servers_count' => count($serverList),
    'servers' => array_map(function($s) {
        return [
            'id' => $s['id'] ?? '',
            'name' => $s['name'] ?? '',
            'protocol' => $s['protocol'] ?? '',
            'operator' => $s['operator_name'] ?? '',
            'uri_sample' => substr($s['config_uri'] ?? '', 0, 50) . '...'
        ];
    }, $serverList)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

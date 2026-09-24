<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = Database::getConnection();

    // 1. Clear config_template from all nodes so real nodes never get overridden by mock templates
    $pdo->exec("UPDATE server_nodes SET config_template = ''");

    // 2. Reset clients traffic to 0 bytes and set status to active
    $pdo->exec("UPDATE clients SET traffic_used_bytes = 0, status = 'active'");

    $nodes = $pdo->query("SELECT id, name, driver, host, port, sub_domain, config_template, is_active FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query("SELECT id, username, password, server_id, traffic_limit_bytes, traffic_used_bytes, expire_at, status, node_sublink FROM clients ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Test direct sublink fetch for client 10f575
    $sampleClient = null;
    foreach ($clients as $c) {
        if ($c['username'] === 'usr_10f575') {
            $sampleClient = $c;
            break;
        }
    }
    if (!$sampleClient && !empty($clients)) {
        $sampleClient = $clients[0];
    }

    $subOutput = [];
    if ($sampleClient && !empty($sampleClient['node_sublink'])) {
        $ch = curl_init($sampleClient['node_sublink']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'v2rayNG/1.8.5');
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw) {
            $dec = base64_decode(trim($raw), true) ?: $raw;
            $subOutput = array_filter(array_map('trim', explode("\n", $dec)));
        }
    }

    echo json_encode([
        'nodes' => $nodes,
        'clients' => $clients,
        'sample_sub_count' => count($subOutput),
        'sample_sub_links' => array_values($subOutput)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

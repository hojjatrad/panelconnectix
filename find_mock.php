<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$pdo = Database::getConnection();

// Check server_nodes config_template
$rows = $pdo->query("SELECT id, name, driver, config_template, sub_domain FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "SERVER #{$r['id']} ({$r['name']} / {$r['driver']}):\n";
    echo "Subdomain: {$r['sub_domain']}\n";
    echo "Config template:\n{$r['config_template']}\n";
    echo "----------------------------------------\n";
}

// Check client 09c06fbd5ba83c1177ab1a0b
$cl = $pdo->query("SELECT * FROM clients WHERE sub_token = '09c06fbd5ba83c1177ab1a0b' OR username = 'usr_10f575'")->fetch(PDO::FETCH_ASSOC);
if ($cl) {
    echo "\nCLIENT {$cl['username']}:\n";
    echo "Server ID: {$cl['server_id']}\n";
    echo "Node sublink: {$cl['node_sublink']}\n";
    echo "Traffic Limit: " . round($cl['traffic_limit_bytes'] / 1073741824, 2) . " GB\n";
    echo "Traffic Used: " . round($cl['traffic_used_bytes'] / 1073741824, 2) . " GB\n";
}

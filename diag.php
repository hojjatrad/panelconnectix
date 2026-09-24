<?php
/**
 * Standalone Zero-Dependency Server Diagnostic Tool
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/drivers/DriverFactory.php';
require_once __DIR__ . '/controllers/ServerController.php';

try {
    $pdo = Database::getConnection();
    $nodes = $pdo->query("SELECT id, name, driver, api_url, api_username, is_active, health_status, server_group, sub_domain, config_template FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
    $results = [];

    foreach ($nodes as $n) {
        $full = $pdo->query("SELECT * FROM server_nodes WHERE id = " . (int)$n['id'])->fetch(PDO::FETCH_ASSOC);

        // Auto-detect driver
        $detectedDriver = ServerController::detectDriverType($full['api_url'] ?? '', $full['api_username'] ?? '', $full['api_password'] ?? '', $full['api_token'] ?? '', $full['name'] ?? '');

        // If driver in DB differs from auto-detected, update DB
        if (!empty($detectedDriver) && $detectedDriver !== $full['driver'] && $full['driver'] !== 'mock') {
            $pdo->prepare("UPDATE server_nodes SET driver = ? WHERE id = ?")->execute([$detectedDriver, $full['id']]);
            $full['driver'] = $detectedDriver;
            $n['driver'] = $detectedDriver;
        }

        $driverInst = DriverFactory::create($full);
        $auth = $driverInst->authenticate();
        $err = method_exists($driverInst, 'getLastError') ? $driverInst->getLastError() : null;
        $inbounds = method_exists($driverInst, 'getInbounds') ? $driverInst->getInbounds() : [];
        $detailedInbounds = method_exists($driverInst, 'getDetailedInbounds') ? $driverInst->getDetailedInbounds() : [];

        $sampleLink = null;
        if ($auth) {
            $testUser = 'diag_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $cRes = $driverInst->createUser([
                'username' => $testUser,
                'uuid' => Helpers::generateUUID(),
                'traffic_limit_bytes' => 1073741824,
                'expire_timestamp' => time() + 86400
            ]);

            if ($cRes['success']) {
                $sub = $cRes['sublink'] ?? null;
                $sampleLink = [
                    'sublink' => $sub,
                    'links' => $cRes['links'] ?? [],
                    'vless_link' => $cRes['vless_link'] ?? null
                ];
                $driverInst->deleteUser($testUser);
            } else {
                $sampleLink = ['error' => $cRes['error'] ?? 'failed'];
            }
        }

        $results[] = [
            'node' => $n,
            'detected_driver' => $detectedDriver,
            'auth' => $auth,
            'error' => $err,
            'inbounds' => $inbounds,
            'detailed_inbounds' => $detailedInbounds,
            'sample' => $sampleLink
        ];
    }

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$key = $_GET['key'] ?? '';
if ($key !== 'gh_hook_sec_vpbotn_2026') {
    die(json_encode(['error' => 'forbidden']));
}

$pdo = Database::getConnection();

// Check current clients in DB
$currentSample = $pdo->query("SELECT id, username, traffic_limit_bytes, traffic_used_bytes, status, expire_at FROM clients LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Search zip backups for any client with traffic_used_bytes > 0
$zipFiles = glob('/tmp/connectix_backups/*.zip') ?: [];
rsort($zipFiles); // newest first

$foundBackupsWithTraffic = [];
$totalChecked = 0;

foreach ($zipFiles as $zf) {
    $totalChecked++;
    if ($totalChecked > 100) break;
    $zip = new ZipArchive();
    if ($zip->open($zf) === true) {
        $content = $zip->getFromIndex(0);
        $zip->close();
        if (preg_match_all("/INSERT INTO `clients`.*?VALUES\s*\((.*?)\);/s", $content, $m)) {
            $hasNonZeroTraffic = false;
            $sampleRow = null;
            foreach ($m[0] as $stmt) {
                // Check if traffic_used_bytes (typically column 10) is not '0'
                if (preg_match("/VALUES\s*\([^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,[^,]+,\s*'([1-9][0-9]*)'/", $stmt, $tm)) {
                    $hasNonZeroTraffic = true;
                    $sampleRow = $stmt;
                    break;
                }
            }
            if ($hasNonZeroTraffic) {
                $foundBackupsWithTraffic[] = [
                    'file' => basename($zf),
                    'sample' => $sampleRow
                ];
                if (count($foundBackupsWithTraffic) >= 3) break;
            }
        }
    }
}

// Also check Pasargad node /api/users to see what Pasargad actually reports for used_traffic
require_once __DIR__ . '/drivers/DriverFactory.php';
$srv = $pdo->query("SELECT * FROM server_nodes WHERE id = 10000")->fetch();
$nodeUsersSample = [];
if ($srv) {
    try {
        $driver = DriverFactory::create($srv);
        $driver->authenticate();
        $raw = $driver->request('/api/users?limit=5');
        $nodeUsersSample = $raw['data'] ?? [];
    } catch (Throwable $e) {
        $nodeUsersSample = ['error' => $e->getMessage()];
    }
}

echo json_encode([
    'current_db_sample' => $currentSample,
    'found_backups_with_traffic' => $foundBackupsWithTraffic,
    'total_checked_zips' => $totalChecked,
    'node_users_raw_sample' => $nodeUsersSample,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

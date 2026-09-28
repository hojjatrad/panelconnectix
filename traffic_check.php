<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$key = $_GET['key'] ?? '';
if ($key !== 'gh_hook_sec_vpbotn_2026') die('forbidden');

$targetZip = '/tmp/connectix_backups/connectix_backup_2026-09-28_12-00-42.zip';
if (!file_exists($targetZip)) {
    die(json_encode(['error' => 'zip not found']));
}

$pdo = Database::getConnection();

$zip = new ZipArchive();
$updated = 0;
$details = [];

if ($zip->open($targetZip) === true) {
    $sql = $zip->getFromIndex(0);
    $zip->close();

    preg_match('/INSERT INTO `clients` \((.*?)\) VALUES/s', $sql, $colsM);
    $cols = array_map(function($c) { return trim($c, " `\t\n\r"); }, explode(',', $colsM[1] ?? ''));
    $usedIdx = array_search('traffic_used_bytes', $cols);
    $userIdx = array_search('username', $cols);
    $statusIdx = array_search('status', $cols);
    $expireIdx = array_search('expire_at', $cols);
    $idIdx = array_search('id', $cols);

    preg_match_all("/INSERT INTO `clients`.*?VALUES\s*\((.*?)\);/s", $sql, $matches);
    
    $stUpdate = $pdo->prepare("UPDATE clients SET traffic_used_bytes = ?, status = ?, expire_at = ? WHERE id = ?");

    $pdo->beginTransaction();
    foreach ($matches[1] as $valStr) {
        $vals = str_getcsv($valStr, ',', "'");
        $cId = (int)($vals[$idIdx] ?? 0);
        $uName = $vals[$userIdx] ?? '';
        $used = (int)($vals[$usedIdx] ?? 0);
        $status = $vals[$statusIdx] ?? 'active';
        $expire = !empty($vals[$expireIdx]) && $vals[$expireIdx] !== 'NULL' ? $vals[$expireIdx] : null;

        $stUpdate->execute([$used, $status, $expire, $cId]);
        $updated++;
        if ($used > 0) {
            $details[$uName] = [
                'id' => $cId,
                'used_bytes' => $used,
                'used_formatted' => round($used / (1024*1024*1024), 2) . ' GB',
                'status' => $status
            ];
        }
    }
    $pdo->commit();
}

// Read back from DB to verify
$rows = $pdo->query("SELECT id, username, traffic_limit_bytes, traffic_used_bytes, status, expire_at FROM clients WHERE traffic_used_bytes > 0 ORDER BY traffic_used_bytes DESC")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'total_updated' => $updated,
    'clients_with_traffic_count' => count($rows),
    'sample_clients_with_traffic' => array_slice($rows, 0, 15),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

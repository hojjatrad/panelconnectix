<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$key = $_GET['key'] ?? '';
if ($key !== 'gh_hook_sec_vpbotn_2026') die('forbidden');

$targetZip = '/tmp/connectix_backups/connectix_backup_2026-09-28_12-00-42.zip';
if (!file_exists($targetZip)) {
    $targetZip = '/tmp/connectix_backups/connectix_backup_2026-09-28_11-52-37.zip';
}

$zip = new ZipArchive();
$usersWithTraffic = [];
if ($zip->open($targetZip) === true) {
    $sql = $zip->getFromIndex(0);
    $zip->close();
    
    // Extract column list and values
    preg_match('/INSERT INTO `clients` \((.*?)\) VALUES/s', $sql, $colsM);
    $cols = array_map(function($c) { return trim($c, " `\t\n\r"); }, explode(',', $colsM[1] ?? ''));
    $usedIdx = array_search('traffic_used_bytes', $cols);
    $userIdx = array_search('username', $cols);
    $idIdx = array_search('id', $cols);
    
    preg_match_all("/INSERT INTO `clients`.*?VALUES\s*\((.*?)\);/s", $sql, $matches);
    foreach ($matches[1] as $valStr) {
        // Parse CSV values taking quotes into account
        $vals = str_getcsv($valStr, ',', "'");
        $uName = $vals[$userIdx] ?? '';
        $used = (int)($vals[$usedIdx] ?? 0);
        $cId = (int)($vals[$idIdx] ?? 0);
        if ($used > 0) {
            $usersWithTraffic[$uName] = [
                'id' => $cId,
                'used_bytes' => $used,
                'used_formatted' => round($used / (1024*1024*1024), 2) . ' GB'
            ];
        }
    }
}

echo json_encode([
    'zip' => basename($targetZip),
    'cols' => $cols,
    'usedIdx' => $usedIdx,
    'total_clients' => count($matches[1] ?? []),
    'clients_with_traffic_count' => count($usersWithTraffic),
    'clients_with_traffic' => $usersWithTraffic,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

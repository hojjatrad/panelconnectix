<?php
// ST2 Health Check every 30s - ZERO COST
require_once __DIR__ . '/core/Database.php';
try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    $servers = $pdo->query("SELECT * FROM server_nodes")->fetchAll();
    $online=0; $offline=0;
    foreach ($servers as $s) {
        $ok = false;
        $latency = null;
        for ($i=0; $i<2; $i++) {
            $start = microtime(true);
            $ch = curl_init($s['api_url'].'/panel/api/inbounds/list');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code>=200 && $code<300) { 
                $ok=true; 
                $latency = (int)((microtime(true)-$start)*1000);
                break; 
            }
        }
        $status = $ok ? 'online' : 'offline';
        $pdo->prepare("UPDATE server_nodes SET health_status=?, latency_ms=?, last_check=NOW() WHERE id=?")
            ->execute([$status, $latency, $s['id']]);
        if ($ok) $online++; else $offline++;
    }
    echo date('Y-m-d H:i:s')." HealthCheck: $online online, $offline offline\n";
} catch (Exception $e) {
    echo "HealthCheck error: ".$e->getMessage()."\n";
}
?>

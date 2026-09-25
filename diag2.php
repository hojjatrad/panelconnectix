<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$out = [
    'time' => date('Y-m-d H:i:s'),
    'dir' => __DIR__,
];

$checkFiles = [
    'core/Helpers.php' => ['isPanelSubUrl' => 'isPanelSubUrl', 'mci_reality' => 'mci_reality'],
    'controllers/ApiController.php' => ['mci_reality_de' => 'mci_reality_de', 'mock_pbk' => 'mock_pbk'],
    'controllers/ApiControllerV2.php' => ['mci_reality_de' => 'mci_reality_de', 'mock_pbk' => 'mock_pbk'],
    'controllers/SublinkController.php' => ['mci_reality_de' => 'mci_reality_de'],
    'controllers/SublinkControllerV2.php' => ['mci_reality_de' => 'mci_reality_de'],
    'drivers/MockDriver.php' => ['15' => '15 * 1024'],
    'index.php' => ['ApiControllerV2' => 'ApiControllerV2'],
    'router.php' => ['ApiControllerV2' => 'ApiControllerV2'],
];

$out['self_updater'] = (function () {
    $p = __DIR__ . '/quick_update.php';
    if (!file_exists($p)) return ['exists' => false];
    $c = file_get_contents($p);
    return [
        'exists' => true,
        'sha1' => sha1($c),
        'size' => strlen($c),
        'mtime' => date('Y-m-d H:i:s', filemtime($p)),
        'has_v4_marker' => str_contains($c, 'Updater v4'),
        'has_fixed_loop' => str_contains($c, 'as $sub => $item'),
        'has_getsubpathname_bug' => str_contains($c, 'getSubPathname'),
        'old_backups' => array_map('basename', glob(__DIR__ . '/*.old.*') ?: []),
    ];
})();

$out['files'] = [];
foreach ($checkFiles as $rel => $needles) {
    $p = __DIR__ . '/' . $rel;
    if (!file_exists($p)) { $out['files'][$rel] = ['exists' => false]; continue; }
    $c = file_get_contents($p);
    $row = [
        'exists' => true,
        'sha1' => sha1($c),
        'size' => strlen($c),
        'mtime' => date('Y-m-d H:i:s', filemtime($p)),
    ];
    foreach ($needles as $label => $needle) {
        $row['has_' . $label] = str_contains($c, $needle);
    }
    $out['files'][$rel] = $row;
}

// OpCache state
$out['opcache'] = function_exists('opcache_get_status') ? opcache_get_status(false) : ['enabled' => false];
if (is_array($out['opcache']) && isset($out['opcache']['opcache_enabled'])) {
    $out['opcache'] = [
        'enabled' => $out['opcache']['opcache_enabled'],
        'memory' => $out['opcache']['memory_usage'] ?? null,
        'revalidate_freq' => $out['opcache']['revalidate_freq'] ?? null,
    ];
}

// .user.ini
$out['user_ini'] = file_exists(__DIR__ . '/.user.ini') ? file_get_contents(__DIR__ . '/.user.ini') : null;

// Scan ALL php files for legacy mock markers
$stale = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
foreach ($rii as $f) {
    if ($f->isDir()) continue;
    if ($f->getExtension() !== 'php') continue;
    $rel = str_replace(__DIR__, '', $f->getPathname());
    // These files only reference the markers as search patterns (guard definitions)
    // montago-shop occurrences elsewhere are legacy-rewrite guards (healing code), not generators
    if (in_array($rel, ['/diag2.php', '/core/Helpers.php', '/quick_update.php'], true)) continue;
    $c = @file_get_contents($f->getPathname());
    if ($c && (str_contains($c, 'mci_reality') || str_contains($c, 'mock_pbk'))) {
        $stale[] = $rel;
    }
}
$out['stale_mock_files'] = $stale;

// GitHub connectivity probe FROM THIS HOST
$probe = [];
try {
    $ch = curl_init('https://api.github.com/repos/hojjatrad/panelconnectix/commits/main');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['User-Agent: Connectix-Diag'],
    ]);
    $r = curl_exec($ch);
    $probe['api_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $probe['api_error'] = curl_error($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    $probe['api_sha'] = $d['sha'] ?? null;
} catch (Throwable $e) { $probe['api_error'] = $e->getMessage(); }
try {
    $ch = curl_init('https://codeload.github.com/hojjatrad/panelconnectix/zip/refs/heads/main?t=' . time());
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['User-Agent: Connectix-Diag'],
    ]);
    $z = curl_exec($ch);
    $probe['zip_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $probe['zip_size'] = strlen((string)$z);
    curl_close($ch);
    if (is_string($z) && strlen($z) > 5000) {
        $tmp = sys_get_temp_dir() . '/cx_probe_' . uniqid() . '.zip';
        file_put_contents($tmp, $z);
        if (class_exists('ZipArchive')) {
            $za = new ZipArchive();
            if ($za->open($tmp) === true) {
                $names = $za->namelist();
                $root = $names[0] ?? '';
                $probe['zip_root_folder'] = explode('/', $root)[0] ?? null;
                $probe['zip_has_v5_updater'] = in_array($root . 'quick_update.php', $names, false)
                    ? str_contains($za->getFromName($root . 'quick_update.php'), 'Self-Healing Updater v5')
                    : null;
                $za->close();
            }
        }
        @unlink($tmp);
    }
} catch (Throwable $e) { $probe['zip_error'] = $e->getMessage(); }
$out['github_probe'] = $probe;

// Live test: V2 extractServerList for a real client (wrapped)
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    $cl = $pdo->query("SELECT * FROM clients ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cl) {
        require_once __DIR__ . '/controllers/ApiControllerV2.php';
        $list = ApiControllerV2::extractServerList($cl, $pdo);
        $out['v2_test'] = [
            'client' => $cl['username'],
            'count' => count($list),
            'names' => array_column($list, 'name'),
            'mock_markers' => array_filter(array_column($list, 'config_uri'), fn($l) => str_contains($l, 'mock_pbk') || str_contains($l, 'mock_public_key') || str_contains($l, 'montago-shop')),
        ];
    }
} catch (Throwable $e) {
    $out['v2_test_error'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

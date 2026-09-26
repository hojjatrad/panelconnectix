<?php
/**
 * Connectix Panel — SQLite → MySQL migration script (cPanel-ready).
 *
 * Usage (on the host, via SSH or the cPanel terminal):
 *   php scripts/migrate-to-mysql.php
 *     → reads scripts/mysql-credentials.json (git-ignored):
 *         {"host":"localhost","port":3306,"database":"your_db","user":"your_user","pass":"..."}
 *   or:
 *   php scripts/migrate-to-mysql.php HOST PORT DATABASE USER PASS
 *
 * What it does:
 *   1. Creates the full schema in MySQL from schema.sql (INSERT seeds are skipped)
 *   2. Applies the panel's extended-table migrations (ensureExtendedTablesExist)
 *   3. Copies every row of every SQLite table into MySQL (INSERT IGNORE, batched)
 *   4. Prints a per-table report + the exact config.php changes for cutover
 *
 * It NEVER deletes or modifies the source SQLite database.
 * Rerunning is safe (INSERT IGNORE + CREATE TABLE IF NOT EXISTS).
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php'; // loads APP_* + DB_* constants (source = SQLITE_PATH)

$credFile = __DIR__ . '/mysql-credentials.json';
if (isset($argv[1])) {
    $mysql = [
        'host' => $argv[1],
        'port' => (int)($argv[2] ?? 3306),
        'database' => $argv[3],
        'user' => $argv[4],
        'pass' => $argv[5] ?? '',
    ];
} else {
    if (!is_file($credFile)) {
        exit("No credentials. Create {$credFile} or pass: HOST PORT DATABASE USER PASS\n");
    }
    $mysql = json_decode((string)file_get_contents($credFile), true);
    if (!is_array($mysql) || empty($mysql['database']) || empty($mysql['user'])) {
        exit("Invalid {$credFile}\n");
    }
}

echo "== Connectix SQLite → MySQL migration ==\n";
echo "Target: {$mysql['user']}@{$mysql['host']}:{$mysql['port']}/{$mysql['database']}\n\n";

// ---------- 1. Source (SQLite) ----------
if (defined('DB_DRIVER') && DB_DRIVER !== 'sqlite') {
    exit("Source DB_DRIVER must be 'sqlite' in config.php for this script. Aborting.\n");
}
@mkdir(dirname(SQLITE_PATH), 0777, true);
$src = new PDO('sqlite:' . SQLITE_PATH);
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "[1/4] Source SQLite opened: " . SQLITE_PATH . " (" . filesize(SQLITE_PATH) . " bytes)\n";

// ---------- 2. Target (MySQL) ----------
try {
    $dst = new PDO(
        "mysql:host={$mysql['host']};port={$mysql['port']};dbname={$mysql['database']};charset=utf8mb4",
        $mysql['user'],
        $mysql['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    exit("FATAL: cannot connect to MySQL: " . $e->getMessage() . "\n");
}
$dst->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "[2/4] MySQL connected.\n";

// ---------- 3. Schema ----------
echo "[3/4] Applying schema.sql (seeds skipped)...\n";
$schemaSql = (string)file_get_contents(__DIR__ . '/../schema.sql');
// Drop lines that seed demo data (keep DDL only)
$lines = preg_split('/\n/', $schemaSql);
$ddl = '';
foreach ($lines as $line) {
    $t = trim($line);
    if (str_starts_with($t, 'INSERT')) {
        continue;
    }
    $ddl .= $line . "\n";
}
$statements = array_filter(array_map('trim', explode(';', $ddl)));
$schemaOk = 0;
$schemaFail = 0;
foreach ($statements as $stmt) {
    if ($stmt === '') continue;
    try {
        $dst->exec($stmt);
        $schemaOk++;
    } catch (Throwable $e) {
        $schemaFail++;
        echo "  schema stmt failed: " . substr($e->getMessage(), 0, 160) . "\n";
    }
}
echo "  schema statements: {$schemaOk} ok, {$schemaFail} failed\n";

require __DIR__ . '/../core/Database.php';
Database::ensureExtendedTablesExist($dst);
echo "  extended tables ensured.\n";

// ---------- 4. Data copy ----------
echo "[4/4] Copying data...\n";
$srcTables = $src->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$report = [];
$grandTotal = 0;

foreach ($srcTables as $table) {
    $table = str_replace('`', '', $table);
    $cols = $src->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (empty($colNames)) continue;

    // Does the target table exist?
    $exists = $dst->query("SHOW TABLES LIKE '" . addslashes($table) . "'")->fetchColumn();
    if (!$exists) {
        $report[$table] = ['rows' => 0, 'status' => 'skipped (no target table)'];
        continue;
    }
    // Intersect with target columns (defensive)
    $dstCols = array_column($dst->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $useCols = array_values(array_intersect($colNames, $dstCols));
    if (empty($useCols)) {
        $report[$table] = ['rows' => 0, 'status' => 'skipped (no common columns)'];
        continue;
    }

    $colList = '`' . implode('`,`', $useCols) . '`';
    $placeholders = '(' . implode(',', array_fill(0, count($useCols), '?')) . ')';
    $ins = $dst->prepare("INSERT IGNORE INTO `{$table}` ({$colList}) VALUES {$placeholders}");

    $stmt = $src->query("SELECT * FROM `{$table}`");
    $copied = 0;
    $skippedRows = 0;
    $batch = [];
    $dst->beginTransaction();
    try {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($useCols as $c) {
                $v = $row[$c] ?? null;
                // SQLite may hand back int/float/string/null — normalize
                $vals[] = is_int($v) || is_float($v) ? $v : (is_string($v) ? $v : null);
            }
            $batch[] = $vals;
            if (count($batch) >= 500) {
                foreach ($batch as $b) {
                    $r = $ins->execute($b);
                    $copied += $r ? $ins->rowCount() : 0;
                }
                $batch = [];
            }
        }
        foreach ($batch as $b) {
            $ins->execute($b);
            $copied += $ins->rowCount();
        }
        $dst->commit();
    } catch (Throwable $e) {
        $dst->rollBack();
        $report[$table] = ['rows' => $copied, 'status' => 'ERROR: ' . substr($e->getMessage(), 0, 140)];
        continue;
    }
    $report[$table] = ['rows' => $copied, 'status' => 'ok'];
    $grandTotal += $copied;
    echo "  {$table}: {$copied} row(s) copied\n";
}

// ---------- Report ----------
echo "\n== MIGRATION REPORT ==\n";
foreach ($report as $t => $r) {
    printf("  %-28s %6d  %s\n", $t, $r['rows'], $r['status']);
}
echo "  TOTAL rows copied: {$grandTotal}\n\n";

$failed = array_filter($report, fn($r) => str_starts_with($r['status'], 'ERROR'));
if (!empty($failed)) {
    echo "⚠️  Some tables failed — review above before cutover.\n";
}

echo "
== NEXT STEPS (manual cutover, in this order) ==
1. Verify data:  mysql -u{$mysql['user']} -p {$mysql['database']} -e 'SELECT COUNT(*) FROM clients; SELECT COUNT(*) FROM users;'
2. Edit ~/contax/config.php:
     define('DB_DRIVER', 'mysql');
     define('DB_HOST', '{$mysql['host']}');
     define('DB_PORT', '{$mysql['port']}');
     define('DB_NAME', '{$mysql['database']}');
     define('DB_USER', '{$mysql['user']}');
     define('DB_PASS', '<password>');
3. Reload the panel once:  open https://vpbotn.ir/contax/login
4. Keep the SQLite file (data/panel.sqlite) as-is for a week as a fallback,
   then let the normal daily Telegram backups take over.
";

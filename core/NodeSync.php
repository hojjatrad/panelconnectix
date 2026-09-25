<?php
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

/**
 * Node Client Sync — «همگام‌سازی کلاینت‌های سرور»
 *
 * Mirrors the live user list of every node (Marzban / Pasargad / X-UI) into
 * the panel's `clients` table so that EVERY client — panel-managed AND
 * node-direct — is visible and manageable in «مدیریت کلاینت‌ها» with the
 * same tooling (copy sub/configs, renew, delete, QR inspector).
 *
 * Safety rules:
 *  - Only ADDS users that do not exist yet for that (server_id, username).
 *  - Only UPDATES rows flagged `node_sync = 1` (live usage/limit/expire/status).
 *  - NEVER deletes panel rows, and NEVER touches panel-managed clients
 *    (`node_sync = 0`) — the panel remains their source of truth.
 *  - Imported clients get a generated panel password + sub token, so the
 *    dedicated app can log in with username/password (appLogin matches by
 *    username) and the panel sub link proxies the node subscription.
 */
class NodeSync {

    /**
     * Sync one server's live users into the clients table.
     * Returns ['added' => int, 'updated' => int, 'skipped' => int, 'errors' => string[]]
     */
    public static function syncServer(PDO $pdo, array $server): array {
        $stats = [
            'server_id' => (int)($server['id'] ?? 0),
            'server' => (string)($server['name'] ?? ''),
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (empty($server['driver']) || $server['driver'] === 'mock') {
            return $stats; // mock/demo servers have no real users
        }

        try {
            $driver = DriverFactory::create($server);
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            return $stats;
        }

        if (!$driver->authenticate()) {
            $stats['errors'][] = (string)($driver->getLastError() ?? 'اتصال به سرور برقرار نشد');
            return $stats;
        }

        $users = $driver->listUsers();
        if (empty($users)) {
            return $stats;
        }

        $adminId = (int)($pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);

        $stFind = $pdo->prepare("SELECT id, node_sync FROM clients WHERE server_id = ? AND username = ?");
        $stIns  = $pdo->prepare(
            "INSERT INTO clients
                (reseller_id, server_id, plan_id, username, password, uuid, sub_token,
                 traffic_limit_bytes, traffic_used_bytes, expire_at, status, node_sublink,
                 node_sync, custom_note, created_at)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'مستقیم سرور', CURRENT_TIMESTAMP)"
        );
        $stUpd = $pdo->prepare(
            "UPDATE clients
                SET traffic_limit_bytes = ?, traffic_used_bytes = ?, expire_at = ?, status = ?, node_sublink = ?
              WHERE id = ?"
        );

        foreach ($users as $u) {
            $username = trim((string)($u['username'] ?? ''));
            if ($username === '' || strlen($username) > 190) continue;

            $expireAt = !empty($u['expire_at']) ? (string)$u['expire_at'] : null;
            $status   = (string)($u['status'] ?? 'active');
            $limit    = (int)($u['traffic_limit_bytes'] ?? 0);
            $used     = (int)($u['traffic_used_bytes'] ?? 0);
            $nodeSub  = (string)($u['subscription_url'] ?? '');

            $stFind->execute([(int)($server['id'] ?? 0), $username]);
            $row = $stFind->fetch(PDO::FETCH_ASSOC);

            try {
                if ($row) {
                    if (empty($row['node_sync'])) {
                        // Panel-managed client — panel is source of truth, do not touch
                        $stats['skipped']++;
                        continue;
                    }
                    $stUpd->execute([$limit, $used, $expireAt, $status, $nodeSub, (int)$row['id']]);
                    $stats['updated']++;
                } else {
                    $stIns->execute([
                        $adminId,
                        (int)($server['id'] ?? 0),
                        $username,
                        self::generatePassword(),
                        self::generateUuid(),
                        Helpers::generateToken(24),
                        $limit,
                        $used,
                        $expireAt,
                        $status,
                        $nodeSub,
                    ]);
                    $stats['added']++;
                }
            } catch (Throwable $e) {
                $stats['errors'][] = "{$username}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Sync every active, non-mock server. Aggregates per-server stats.
     */
    public static function syncAll(PDO $pdo): array {
        $result = ['servers' => 0, 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $servers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 AND (driver IS NULL OR driver != 'mock')")->fetchAll();
        foreach ($servers as $s) {
            $st = self::syncServer($pdo, $s);
            $result['servers']++;
            $result['added']   += $st['added'];
            $result['updated'] += $st['updated'];
            $result['skipped'] += $st['skipped'];
            foreach ($st['errors'] as $err) {
                $result['errors'][] = "{$st['server']}: {$err}";
            }
        }
        return $result;
    }

    /** 10-char printable password for the dedicated app (username/password login). */
    private static function generatePassword(): string {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /** RFC-4122 v4 UUID. */
    private static function generateUuid(): string {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}

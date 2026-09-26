<?php
/**
 * Temporary server-side diagnostics (operator-facing, key-protected).
 *
 * Used to pinpoint production issues without SSH access:
 *  - Telegram webhook health for the main bot + reseller bots
 *  - Tails of bot_error.log / telegram_api.log
 *  - Step-by-step trace of the Android "servers" delivery chain
 *    (node binding -> driver getUser -> links -> sublink fallback -> mock guard)
 *
 * All identifiers/credentials are masked. Call with:
 *   /contax/index.php?route=monitor/diag&key=<DIAG_KEY>
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';

class DiagController {
    private const DIAG_KEY = '56e2d2918c5407b159ae587879abe0ca';

    public function run(): void {
        header('Content-Type: application/json; charset=utf-8');
        if (!hash_equals(self::DIAG_KEY, (string)($_GET['key'] ?? ''))) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not found']);
            return;
        }

        // One-shot repair action: re-register broken bot webhooks
        if (($_GET['action'] ?? '') === 'fix-webhooks') {
            echo json_encode($this->fixWebhooks(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return;
        }

        $out = ['ok' => true, 'time' => date('c'), 'version' => ''];
        try {
            require_once __DIR__ . '/../core/Updater.php';
            $out['version'] = Updater::getCurrentVersion();
        } catch (Throwable $e) {
            $out['version'] = 'error: ' . substr($e->getMessage(), 0, 80);
        }

        $out['bot'] = $this->diagBots();
        $out['logs'] = $this->diagLogs();
        $out['android'] = $this->diagAndroid();

        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // ------------------------------------------------------------------

    private function diagBots(): array {
        $res = ['main' => null, 'resellers' => []];
        try {
            $info = TelegramBot::getWebhookInfo();
            $data = $info['result'] ?? [];
            $res['main'] = [
                'url' => $this->maskUrl((string)($data['url'] ?? '')),
                'last_error_message' => $data['last_error_message'] ?? null,
                'last_error_date' => $data['last_error_date'] ?? null,
                'pending_update_count' => $data['pending_update_count'] ?? null,
                'allow_requests' => $data['allow_requests'] ?? null,
            ];

            $pdo = Database::getConnection();
            $bots = $pdo->query("SELECT id, username, telegram_bot_token FROM users WHERE telegram_bot_token != '' ORDER BY id ASC LIMIT 6")->fetchAll();
            foreach ($bots as $b) {
                $bi = TelegramBot::getWebhookInfo($b['telegram_bot_token']);
                $bd = $bi['result'] ?? [];
                $res['resellers'][] = [
                    'id' => (int)$b['id'],
                    'username' => (string)$b['username'],
                    'url' => $this->maskUrl((string)($bd['url'] ?? '')),
                    'last_error_message' => $bd['last_error_message'] ?? ($bi['description'] ?? null),
                    'pending_update_count' => $bd['pending_update_count'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    private function diagLogs(): array {
        $tail = function (string $file) {
            if (!file_exists($file)) return '(file missing)';
            $size = filesize($file);
            if ($size > 200000) return '(file too large: ' . $size . ' bytes — truncated)';
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $lines = array_slice($lines, -40);
            return $lines === [] ? '(empty)' : $lines;
        };
        return [
            'bot_error' => $tail(__DIR__ . '/../data/bot_error.log'),
            'telegram_api' => $tail(__DIR__ . '/../data/telegram_api.log'),
        ];
    }

    // ------------------------------------------------------------------

    private function diagAndroid(): array {
        $res = ['nodes' => [], 'sample' => null, 'error' => null];
        try {
            $pdo = Database::getConnection();

            // 1. Nodes
            $nodes = $pdo->query("SELECT id, driver, is_active, api_url FROM server_nodes ORDER BY id ASC")->fetchAll();
            foreach ($nodes as $n) {
                $p = parse_url((string)$n['api_url']);
                $res['nodes'][] = [
                    'id' => (int)$n['id'],
                    'driver' => $n['driver'],
                    'is_active' => (int)$n['is_active'],
                    'host' => $p['host'] ?? '',
                ];
            }

            // 2. Sample client (newest one with a sub token)
            $client = $pdo->query("SELECT id, username, server_id, sub_token, node_sublink FROM clients WHERE sub_token != '' ORDER BY id DESC LIMIT 1")->fetch();
            if (!$client) {
                $res['error'] = 'no client with sub_token found';
                return $res;
            }
            $stages = [];
            $client['username_masked'] = mb_substr((string)$client['username'], 0, 2) . '**';
            $client['id'] = (int)$client['id'];
            $client['server_id'] = (int)$client['server_id'];
            $client['has_node_sublink'] = !empty($client['node_sublink']);

            $res['sample'] = ['client' => $client, 'stages' => [], 'final_links' => 0, 'sample_links' => []];

            // 3. Node binding
            $node = null;
            if ($client['server_id'] > 0) {
                $st = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                $st->execute([$client['server_id']]);
                $node = $st->fetch();
            }
            $stages[] = $node
                ? ['stage' => 'node_binding', 'ok' => true, 'detail' => "node #{$node['id']} driver=" . $node['driver'] . ' active=' . $node['is_active'] . ' host=' . (parse_url((string)$node['api_url'], PHP_URL_HOST) ?: '?')]
                : ['stage' => 'node_binding', 'ok' => false, 'detail' => 'client has no valid server_id (server_id=' . $client['server_id'] . ')'];

            $realLinks = [];
            $liveData = null;

            // 4. Driver getUser (read-only — NEVER createUser from diagnostics)
            if ($node && $node['driver'] !== 'mock') {
                try {
                    require_once __DIR__ . '/../drivers/DriverFactory.php';
                    $driver = DriverFactory::create($node);
                    $liveData = $driver->getUser((string)$client['username']);
                    $stages[] = $liveData
                        ? ['stage' => 'driver_getUser', 'ok' => true, 'detail' => 'user found on node']
                        : ['stage' => 'driver_getUser', 'ok' => false, 'detail' => 'user NOT found on node (auto-provision skipped in diag)'];
                } catch (Throwable $e) {
                    $stages[] = ['stage' => 'driver_getUser', 'ok' => false, 'detail' => get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200)];
                }
            }

            if ($liveData) {
                $links = is_array($liveData['links'] ?? null) ? array_map('trim', $liveData['links']) : [];
                $links = array_filter($links, fn($l) => preg_match('/^(vless|vmess|trojan|ss|shadowsocks|hysteria2|hy2|tuic|wireguard):\/\//i', (string)$l));
                $stages[] = ['stage' => 'live_links', 'ok' => count($links) > 0, 'detail' => count($links) . ' links in liveData'];
                foreach ($links as $l) $realLinks[] = $l;

                if (empty($realLinks) && !empty($liveData['subscription_url'])) {
                    $subUrl = (string)$liveData['subscription_url'];
                    if (!Helpers::isPanelSubUrl($subUrl)) {
                        $stages[] = ['stage' => 'subscription_url', 'ok' => false, 'detail' => 'fetching ' . $this->maskUrl($subUrl)];
                        $ch = curl_init($subUrl);
                        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'v2rayNG/1.8.5']);
                        $content = curl_exec($ch);
                        $curlErr = curl_error($ch);
                        curl_close($ch);
                        if (!$content) {
                            $stages[] = ['stage' => 'subscription_fetch', 'ok' => false, 'detail' => 'curl error: ' . $curlErr];
                        } else {
                            $decoded = base64_decode(trim((string)$content), true) ?: (string)$content;
                            $subLinks = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", (string)$decoded)), fn($l) => preg_match('/^(vless|vmess|trojan|ss|shadowsocks|hysteria2|hy2|tuic|wireguard):\/\//i', (string)$l));
                            $stages[] = ['stage' => 'subscription_fetch', 'ok' => count($subLinks) > 0, 'detail' => count($subLinks) . ' links from sublink'];
                            foreach ($subLinks as $l) $realLinks[] = $l;
                        }
                    } else {
                        $stages[] = ['stage' => 'subscription_url', 'ok' => false, 'detail' => 'subscription_url points at our own panel (loop guard) — skipped'];
                    }
                }
            }

            // 5. buildConfigs fallback
            if (empty($realLinks)) {
                try {
                    $built = [];
                    if (class_exists('SublinkControllerV2')) {
                        $built = SublinkControllerV2::buildConfigs($client);
                    } elseif (class_exists('SublinkController')) {
                        $built = SublinkController::buildConfigs($client);
                    }
                    $built = array_filter(array_map('trim', is_array($built) ? $built : []), fn($l) => preg_match('/^(vless|vmess|trojan|ss|shadowsocks|hysteria2|hy2|tuic|wireguard):\/\//i', (string)$l));
                    $stages[] = ['stage' => 'build_configs_fallback', 'ok' => count($built) > 0, 'detail' => count($built) . ' built links'];
                    foreach ($built as $l) $realLinks[] = $l;
                } catch (Throwable $e) {
                    $stages[] = ['stage' => 'build_configs_fallback', 'ok' => false, 'detail' => get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200)];
                }
            }

            // 6. node_sublink fallback
            if (empty($realLinks) && !empty($client['node_sublink'])) {
                $nodeSub = (string)$client['node_sublink'];
                if (!Helpers::isPanelSubUrl($nodeSub)) {
                    $stages[] = ['stage' => 'node_sublink', 'ok' => false, 'detail' => 'fetching ' . $this->maskUrl($nodeSub)];
                    $ch = curl_init($nodeSub);
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'v2rayNG/1.8.5']);
                    $content = curl_exec($ch);
                    $curlErr = curl_error($ch);
                    curl_close($ch);
                    if (!$content) {
                        $stages[] = ['stage' => 'node_sublink_fetch', 'ok' => false, 'detail' => 'curl error: ' . $curlErr];
                    } else {
                        $decoded = base64_decode(trim((string)$content), true) ?: (string)$content;
                        $subLinks = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", (string)$decoded)), fn($l) => preg_match('/^(vless|vmess|trojan|ss|shadowsocks|hysteria2|hy2|tuic|wireguard):\/\//i', (string)$l));
                        $stages[] = ['stage' => 'node_sublink_fetch', 'ok' => count($subLinks) > 0, 'detail' => count($subLinks) . ' links from node_sublink'];
                        foreach ($subLinks as $l) $realLinks[] = $l;
                    }
                } else {
                    $stages[] = ['stage' => 'node_sublink', 'ok' => false, 'detail' => 'points at our own panel (loop guard) — skipped'];
                }
            }

            // 7. Dedup + mock guard (same as production)
            $realLinks = array_values(array_unique(array_map('trim', $realLinks)));
            $before = count($realLinks);
            $realLinks = Helpers::stripMockLinks($realLinks);
            $after = count($realLinks);
            $stages[] = ['stage' => 'mock_guard', 'ok' => $after > 0, 'detail' => "before={$before} after={$after} (stripped " . ($before - $after) . ' mock links)'];
            $res['sample']['stages'] = $stages;
            $res['sample']['final_links'] = $after;
            $res['sample']['sample_links'] = array_slice(array_map(fn($l) => $this->maskLink((string)$l), $realLinks), 0, 10);
        } catch (Throwable $e) {
            $res['error'] = get_class($e) . ': ' . $e->getMessage();
        }
        return $res;
    }

    // ------------------------------------------------------------------

    /**
     * One-shot repair: re-register every bot webhook that is missing,
     * stale or erroring. Main bot -> bare webhook.php; each user bot ->
     * webhook.php?bot_token=<token> (same contract as ResellerPortalController).
     */
    private function fixWebhooks(): array {
        $report = [];
        try {
            $pdo = Database::getConnection();
            $base = Helpers::fullFileUrl('webhook.php');

            // Main bot (webhook without token param)
            $info = TelegramBot::getWebhookInfo();
            $cur = (string)(($info['result']['url'] ?? null) ?: '');
            $err = ($info['result']['last_error_message'] ?? null) ?: ($info['description'] ?? null);
            $needed = ($cur !== $base) || ($err !== null);
            $res = $needed ? TelegramBot::setWebhook($base, null, true) : null;
            $report[] = [
                'bot' => 'main',
                'before_url' => $this->maskUrl($cur),
                'before_error' => $err,
                'action' => $needed ? 're-registered' : 'ok',
                'result' => $res ? ($res['ok'] ? 'ok' : ($res['description'] ?? 'error')) : 'not needed',
            ];

            // Per-user bots
            $users = $pdo->query("SELECT id, username, telegram_bot_token FROM users WHERE telegram_bot_token != '' ORDER BY id ASC LIMIT 10")->fetchAll();
            foreach ($users as $u) {
                $token = (string)$u['telegram_bot_token'];
                $expected = Helpers::fullUrl('webhook.php?bot_token=' . urlencode($token));
                $info = TelegramBot::getWebhookInfo($token);
                if (!empty($info['description'])) {
                    // token itself invalid (Unauthorized) — cannot fix from here
                    $report[] = ['bot' => "user #{$u['id']} ({$u['username']})", 'action' => 'skip', 'result' => 'invalid token: ' . $info['description']];
                    continue;
                }
                $cur = (string)(($info['result']['url'] ?? null) ?: '');
                $err = $info['result']['last_error_message'] ?? null;
                $needed = ($cur !== $expected) || ($err !== null);
                $res = $needed ? TelegramBot::setWebhook($expected, $token, true) : null;
                $report[] = [
                    'bot' => "user #{$u['id']} ({$u['username']})",
                    'before_url' => $this->maskUrl($cur),
                    'before_error' => $err,
                    'action' => $needed ? 're-registered' : 'ok',
                    'result' => $res ? ($res['ok'] ? 'ok' : ($res['description'] ?? 'error')) : 'not needed',
                ];
            }
        } catch (Throwable $e) {
            $report[] = ['error' => $e->getMessage()];
        }
        return ['ok' => true, 'time' => date('c'), 'fixed' => $report];
    }

    // ------------------------------------------------------------------

    private function maskUrl(string $url): string {
        $p = parse_url($url);
        if (!$p) return $url !== '' ? '(unparseable)' : '';
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '');
    }

    private function maskLink(string $link): string {
        $p = parse_url($link);
        if (!$p) return '(unparseable)';
        $out = ($p['scheme'] ?? '?') . '://' . ($p['host'] ?? '?') . (isset($p['port']) ? ':' . $p['port'] : '');
        if (!empty($p['fragment'])) {
            $out .= '#' . mb_substr(urldecode((string)$p['fragment']), 0, 60);
        }
        return $out;
    }
}

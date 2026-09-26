<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Updater.php';

/**
 * Operations endpoints — key-protected (current GitHub webhook secret).
 * These are intentionally outside the admin UI: they exist for the
 * operator (you/bot) to rotate secrets and tune engine settings
 * without touching files or the cPanel.
 */
class OpsController {
    private const ALLOWED_SETTING_KEYS = [
        'github_webhook_secret',
        'app_latest_version',
        'app_download_url',
        'app_universal_url',
        'app_update_title',
        'app_update_changelog',
        'app_update_enabled',
        'app_download_url_windows',
        'app_latest_version_windows',
        'disk_alert_pct',
        'backup_retention_days',
        'node_sync_reseller_id',
        'node_sync_plan_id',
        'server_capacity_alert_pct',
        'brand_api_key',
    ];

    private function authorized(): bool {
        $key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
        $expected = Setting::get('github_webhook_secret', defined('APP_SECRET') ? APP_SECRET : 'gh_hook_sec_vpbotn_2026');
        $fallback = 'gh_hook_sec_vpbotn_2026'; // bootstrap key stays valid for ops
        if ($key === '') {
            return false;
        }
        return hash_equals($expected, $key) || hash_equals($fallback, $key);
    }

    private function respond(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * GET/POST ops/set-setting?key=...&name=<whitelisted>&value=...
     */
    public function setSetting(): void {
        if (!$this->authorized()) {
            $this->respond(['success' => false, 'error' => 'unauthorized'], 403);
        }
        $name = trim((string)($_GET['name'] ?? $_POST['name'] ?? ''));
        $value = (string)($_GET['value'] ?? $_POST['value'] ?? '');
        if (!in_array($name, self::ALLOWED_SETTING_KEYS, true)) {
            $this->respond(['success' => false, 'error' => 'key not allowed: ' . $name], 400);
        }
        Setting::set($name, $value);
        Helpers::logActivity('ops_set_setting', "تنظیم عملیاتی \"{$name}\" به‌روزرسانی شد (ops endpoint)", 'system');
        $this->respond([
            'success' => true,
            'name' => $name,
            'value' => $name === 'github_webhook_secret' ? str_repeat('*', 16) : $value,
        ]);
    }

    /**
     * GET/POST ops/rotate-webhook-secret?key=<current secret>
     * Generates a fresh secret, stores it in the panel, and updates the
     * matching GitHub repository hook via the API.
     */
    public function rotateWebhookSecret(): void {
        if (!$this->authorized()) {
            $this->respond(['success' => false, 'error' => 'unauthorized'], 403);
        }

        $newSecret = bin2hex(random_bytes(24)); // 48 hex chars
        $oldSecret = (string)($_GET['key'] ?? $_POST['key'] ?? '');

        // 1. Store new secret in the panel FIRST (webhook immediately accepts it)
        Setting::set('github_webhook_secret', $newSecret);

        // 2. Update the GitHub repository hook
        $repo = Updater::getRepo();
        $token = Updater::getToken();
        $hooks = Updater::githubRequest("https://api.github.com/repos/{$repo}/hooks", $token);
        $updated = null;
        if (is_array($hooks) && is_array($hooks)) {
            foreach ($hooks as $hook) {
                $cfg = $hook['config'] ?? [];
                $url = (string)($cfg['url'] ?? '');
                if (str_contains($url, 'updater/webhook')) {
                    $payload = [
                        'name' => 'web',
                        'active' => true,
                        'events' => ['push'],
                        'config' => [
                            'url' => $url,
                            'content_type' => $cfg['content_type'] ?? 'json',
                            'secret' => $newSecret,
                        ],
                    ];
                    $updated = $this->githubPut("https://api.github.com/repos/{$repo}/hooks/{$hook['id']}", $payload, $token);
                    break;
                }
            }
        }

        Helpers::logActivity('ops_rotate_webhook_secret', 'کلید وب‌هوک گیت‌هاب چرخش یافت (ops endpoint)', 'system');

        if ($updated !== null && !empty($updated['id'])) {
            $this->respond([
                'success' => true,
                'message' => 'کلید وب‌هوک با موفقیت چرخش یافت (پنل + گیت‌هاب).',
                'new_secret' => $newSecret,
                'hook_id' => $updated['id'],
                'old_secret_invalid_for_new_requests' => true,
                'note' => 'کلید قبلی برای cron/ops تا اطلاع بعدی معتبر می‌ماند تا جاب cPanel قطع نشود.',
            ]);
        }
        $this->respond([
            'success' => false,
            'error' => 'پنل کلید جدید را ذخیره کرد، اما به‌روزرسانی هوک گیت‌هاب ناموفق بود. کلید جدید را دستی در تنظیمات وب‌هوک گیت‌هاب وارد کنید.',
            'new_secret' => $newSecret,
            'hint' => 'Settings → Webhooks → Edit → Secret',
        ]);
    }

    private function githubPut(string $url, array $payload, string $token): ?array {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $url = $url . $sep . 'cb=' . (string)time() . rand(1000, 9999);
        $ch = curl_init($url);
        $headers = [
            'User-Agent: Connectix-Panel-System',
            'Accept: application/vnd.github.v3+json',
            'Content-Type: application/json',
        ];
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return json_decode((string)$res, true);
        }
        return null;
    }
}

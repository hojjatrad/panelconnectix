<?php
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Updater.php';

/**
 * App APK Mirror
 *
 * The in-app updater of the Android app downloads the APK from the PANEL host
 * ("$baseUrl/Connectix-ARM64-v8a.apk") — a fast URL that works inside Iran,
 * where GitHub downloads are blocked/unreliable (that is why the fallback
 * GitHub URL fails with "خطا در ارتباط").
 *
 * This class guarantees the APK files exist on the panel host, mirroring the
 * release assets from GitHub server-side (the host CAN reach GitHub).
 *
 *  - Runs from the app-release cron cycle (every ~5 min) — no-op when files
 *    are already present with the exact expected size.
 *  - Can be forced manually from Settings → Metadata (app management).
 */
class AppApkMirror {

    /** APK assets to keep mirrored in the panel web root */
    public const APK_NAMES = [
        'Connectix-ARM64-v8a.apk',   // primary — used by the in-app updater
        'Connectix-Universal.apk',   // universal fallback
        'Connectix-ARM32-v7a.apk',   // 32-bit devices
        'Connectix-Android.apk',     // full build (legacy name)
    ];

    /** Panel web root (this file lives in <root>/core/) */
    public static function rootDir(): string {
        return dirname(__DIR__);
    }

    /**
     * Mirror all release APKs to the panel root.
     *
     * @param array|null $release Optional pre-fetched GitHub release object
     *                            (avoids a duplicate API call).
     * @param bool       $force   Re-download even if the local file looks right.
     * @return array ['ok'=>bool, 'files'=>[name=>state], 'changed'=>bool, 'error'=>?string]
     */
    public static function mirror(?array $release = null, bool $force = false): array {
        $res = ['ok' => true, 'files' => [], 'changed' => false, 'error' => null];

        if ($release === null) {
            $repo  = Updater::getRepo();
            $token = Updater::getToken();
            $release = Updater::githubRequest(
                "https://api.github.com/repos/{$repo}/releases/tags/v3.0.0",
                $token
            );
        }

        if (!is_array($release) || empty($release['assets'])) {
            $res['ok'] = false;
            $res['error'] = 'release_not_found';
            return $res;
        }

        $assets = [];
        foreach ($release['assets'] as $a) {
            if (is_array($a) && !empty($a['name'])) $assets[$a['name']] = $a;
        }

        $root = self::rootDir();
        @set_time_limit(600);

        foreach (self::APK_NAMES as $name) {
            $asset = $assets[$name] ?? null;
            $local = $root . '/' . $name;
            $expectedSize = (int)($asset['size'] ?? 0);

            // Already mirrored? (exact size match = same release build)
            if (!$force && is_file($local) && $expectedSize > 0 && filesize($local) === $expectedSize) {
                $res['files'][$name] = 'ok';
                continue;
            }
            if (!$asset || empty($asset['browser_download_url'])) {
                $res['files'][$name] = is_file($local) ? 'ok' : 'asset_missing';
                continue;
            }

            $tmp = $local . '.part';
            $fp = @fopen($tmp, 'wb');
            if (!$fp) {
                $res['files'][$name] = 'write_denied';
                $res['ok'] = false;
                continue;
            }

            $ch = curl_init($asset['browser_download_url']);
            $headers = ['User-Agent: Connectix-Panel-AppApkMirror/1.0'];
            $token = Updater::getToken();
            if (!empty($token)) $headers[] = "Authorization: token {$token}";
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 900,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $ok = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($ok === false || $http !== 200 || !is_file($tmp) || filesize($tmp) < 1024) {
                @unlink($tmp);
                $res['files'][$name] = 'download_failed(' . ($http ?: 'curl:' . $err) . ')';
                $res['ok'] = false;
                continue;
            }

            if (filesize($tmp) !== $expectedSize) {
                // Partial/corrupt transfer — retry once
                @unlink($tmp);
                $retry = self::downloadOne($asset['browser_download_url'], $local, $expectedSize);
                $res['files'][$name] = $retry ? 'downloaded' : 'size_mismatch';
                $res['changed'] = $retry ? true : $res['changed'];
                if (!$retry) $res['ok'] = false;
                continue;
            }

            if (!@rename($tmp, $local)) {
                @copy($tmp, $local);
                @unlink($tmp);
            }
            @chmod($local, 0644);
            $res['files'][$name] = 'downloaded';
            $res['changed'] = true;
        }

        Setting::set('app_apk_mirror_state', json_encode([
            'at' => date('Y-m-d H:i:s'),
            'files' => $res['files'],
            'ok' => $res['ok'],
        ]));
        return $res;
    }

    private static function downloadOne(string $url, string $target, int $expectedSize): bool {
        $tmp = $target . '.part';
        $fp = @fopen($tmp, 'wb');
        if (!$fp) return false;
        $ch = curl_init($url);
        $headers = ['User-Agent: Connectix-Panel-AppApkMirror/1.0'];
        $token = Updater::getToken();
        if (!empty($token)) $headers[] = "Authorization: token {$token}";
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $ok = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $http !== 200 || filesize($tmp) !== $expectedSize) {
            @unlink($tmp);
            return false;
        }
        @rename($tmp, $target);
        @chmod($target, 0644);
        return true;
    }

    /** Local mirror status for the admin UI (no network access) */
    public static function status(): array {
        $root = self::rootDir();
        $files = [];
        foreach (self::APK_NAMES as $name) {
            $p = $root . '/' . $name;
            $files[$name] = is_file($p) ? ['present' => true, 'size' => filesize($p)] : ['present' => false, 'size' => 0];
        }
        return [
            'files' => $files,
            'last_run' => json_decode(Setting::get('app_apk_mirror_state', '{}'), true) ?: [],
        ];
    }
}

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
     * @param array|null  $release         Optional pre-fetched GitHub release
     *                                     object (avoids a duplicate API call).
     * @param bool        $force           Re-download even if the local file
     *                                     looks right.
     * @param string      $manifestVersion Manifest version (e.g. "3.3.1").
     *                                     A changed version forces re-download —
     *                                     CI rebuilds can produce identical
     *                                     file SIZES with different content.
     * @param string      $manifestCode    Manifest build number (e.g. "11").
     * @return array ['ok'=>bool, 'files'=>[name=>state], 'changed'=>bool, 'error'=>?string]
     */
    public static function mirror(?array $release = null, bool $force = false, string $manifestVersion = '', string $manifestCode = ''): array {
        $res = ['ok' => true, 'files' => [], 'changed' => false, 'error' => null];

        if ($release === null) {
            $repo  = Updater::getRepo();
            $token = Updater::getToken();
            // v3.7.0 ULTRA: Try latest release first, then v3.7.0, then v3.0.0 fallback
            $release = Updater::githubRequest(
                "https://api.github.com/repos/{$repo}/releases/latest",
                $token
            );
            if (!is_array($release) || empty($release['assets'])) {
                $release = Updater::githubRequest(
                    "https://api.github.com/repos/{$repo}/releases/tags/v3.7.0",
                    $token
                );
            }
            if (!is_array($release) || empty($release['assets'])) {
                $release = Updater::githubRequest(
                    "https://api.github.com/repos/{$repo}/releases/tags/v3.0.0",
                    $token
                );
            }
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

        // Build changed since last mirror? (legacy state without a recorded
        // version is treated as stale too — forces a one-time bootstrap)
        $state = json_decode(Setting::get('app_apk_mirror_state', '{}'), true) ?: [];
        if (!$force && $manifestVersion !== '') {
            if ((string)($state['version'] ?? '') !== $manifestVersion) {
                $force = true;
            }
        }

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

            // FIX 2026-10-02: GitHub browser_download_url is PUBLIC and must NOT
            // have ?cb= query param (breaks GitHub's asset handler -> returns HTML)
            // and must NOT have Authorization token header (github.com rejects it).
            // Correct: use clean URL, no token, follow redirect to release-assets.
            $assetUrl = $asset['browser_download_url'];
            $ch = curl_init($assetUrl);
            $headers = [
                'User-Agent: Connectix-Panel-AppApkMirror/1.0',
                'Accept: application/octet-stream',
            ];
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 900,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => 'Connectix-Panel-AppApkMirror/1.0',
            ]);
            $ok = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            fclose($fp);

            // Debug: if file is tiny (<10KB) it's HTML error page, not APK
            $tmpSize = is_file($tmp) ? filesize($tmp) : 0;
            if ($ok === false || $http !== 200 || !is_file($tmp) || $tmpSize < 1024) {
                @unlink($tmp);
                $res['files'][$name] = 'download_failed(' . ($http ?: 'curl:' . $err) . ') size=' . $tmpSize . ' url=' . substr($finalUrl ?? $assetUrl, 0, 80);
                $res['ok'] = false;
                continue;
            }
            // If file is <1MB but expected >10MB, it's likely HTML error page
            if ($tmpSize < 1024 * 1024 && $expectedSize > 5 * 1024 * 1024) {
                $content = @file_get_contents($tmp, false, null, 0, 500);
                if ($content && (str_contains($content, '<html') || str_contains($content, 'Not Found'))) {
                    @unlink($tmp);
                    $res['files'][$name] = 'download_failed(html_page_not_apk)';
                    $res['ok'] = false;
                    continue;
                }
            }

            if ($expectedSize > 0 && $tmpSize !== $expectedSize) {
                // Size mismatch — could be new build with same name but different size
                // Accept if >1MB and looks like APK (ZIP header), otherwise retry
                $fh = @fopen($tmp, 'rb');
                $header = $fh ? @fread($fh, 4) : '';
                if ($fh) @fclose($fh);
                $isApk = $header && $header[0] === "\x50" && $header[1] === "\x4B";
                if (!$isApk || $tmpSize < 1024 * 1024) {
                    @unlink($tmp);
                    $retry = self::downloadOne($asset['browser_download_url'], $local, $expectedSize);
                    $res['files'][$name] = $retry ? 'downloaded' : 'size_mismatch_exp' . $expectedSize . '_got' . $tmpSize;
                    $res['changed'] = $retry ? true : $res['changed'];
                    if (!$retry) $res['ok'] = false;
                    continue;
                }
                // Accept if APK header valid even if size differs (GitHub size may be stale)
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
            // Keep the last known manifest identity when this run had none
            // (e.g. a manual force-mirror without manifest context).
            'version' => $manifestVersion !== '' ? $manifestVersion : (string)($state['version'] ?? ''),
            'code' => $manifestCode !== '' ? $manifestCode : (string)($state['code'] ?? ''),
        ]));
        return $res;
    }

    private static function downloadOne(string $url, string $target, int $expectedSize): bool {
        $tmp = $target . '.part';
        $fp = @fopen($tmp, 'wb');
        if (!$fp) return false;
        // FIX: no ?cb= param, no token header for public browser_download_url
        $ch = curl_init($url);
        $headers = [
            'User-Agent: Connectix-Panel-AppApkMirror/1.0',
            'Accept: application/octet-stream',
        ];
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Connectix-Panel-AppApkMirror/1.0',
        ]);
        $ok = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        $sz = is_file($tmp) ? filesize($tmp) : 0;
        if ($ok === false || $http !== 200 || $sz < 1024 * 1024) {
            @unlink($tmp);
            return false;
        }
        // If expectedSize given, check header but accept if APK valid
        if ($expectedSize > 0 && $sz !== $expectedSize) {
            $fh = @fopen($tmp, 'rb');
            $header = $fh ? @fread($fh, 4) : '';
            if ($fh) @fclose($fh);
            $isApk = $header && strlen($header) >= 2 && $header[0] === "\x50" && $header[1] === "\x4B";
            if (!$isApk) {
                @unlink($tmp);
                return false;
            }
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

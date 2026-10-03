<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';

class Updater {
    public const CURRENT_VERSION = '6.8.25'; // FIX JSON parse error - bulletproof updater ajax-apply display_errors=0 + ob_end_clean

    public static function getCurrentVersion(): string {
        $dbVer = Setting::get('current_version', '');
        // Sanitize: if DB contains commit-xxxx, replace with proper version
        if (!empty($dbVer) && str_starts_with($dbVer, 'commit-')) {
            Setting::set('current_version', self::CURRENT_VERSION);
            return self::CURRENT_VERSION;
        }
        if (empty($dbVer) || version_compare(self::CURRENT_VERSION, $dbVer, '>')) {
            Setting::set('current_version', self::CURRENT_VERSION);
            return self::CURRENT_VERSION;
        }
        return $dbVer;
    }

    public static function ensureDatabaseSchema(): void {
        try {
            $pdo = Database::getConnection();
            Database::ensureExtendedTablesExist($pdo);
            self::ensureConnectixDriverFixed($pdo);
            self::ensureCustomerNamesFixed($pdo);
        } catch (Throwable $e) {}
    }

    public static function getRepo(): string {
        return Setting::get('github_repo', 'hojjatrad/panelconnectix');
    }

    public static function getBranch(): string {
        return Setting::get('github_branch', 'main');
    }

    public static function getToken(): string {
        return Setting::get('github_token', '');
    }

    /**
     * Sanitize version string - never return commit-xxxx as version
     */
    private static function sanitizeVersion(string $ver): string {
        $ver = trim($ver);
        if (str_starts_with($ver, 'commit-')) {
            return self::CURRENT_VERSION;
        }
        // If version looks like commit hash (7-40 hex chars)
        if (preg_match('/^[0-9a-f]{7,40}$/i', $ver)) {
            return self::CURRENT_VERSION;
        }
        // Remove leading v
        $ver = ltrim($ver, 'vV');
        // If empty after sanitization, return current
        if (empty($ver) || $ver === 'commit') {
            return self::CURRENT_VERSION;
        }
        return $ver;
    }

    /**
     * Check GitHub for latest release or latest commit
     */
    public static function checkForUpdates(bool $forceRefresh = false): array {
        if ($forceRefresh) {
            Setting::set('update_check_cache', '');
            Setting::set('update_check_time', '0');
        }

        $cached = Setting::get('update_check_cache');
        $cacheTime = (int)Setting::get('update_check_time', '0');

        if (!$forceRefresh && !empty($cached) && (time() - $cacheTime < 180)) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                // IMPORTANT v6.8.3: If cached data contains commit-xxxx, invalidate it immediately
                $lv = $data['latest_version'] ?? '';
                $cv = $data['current_version'] ?? '';
                if (str_starts_with((string)$lv, 'commit-') || str_starts_with((string)$cv, 'commit-')) {
                    Setting::set('update_check_cache', '');
                    Setting::set('update_check_time', '0');
                } else {
                    // Sanitize cached versions too
                    $data['latest_version'] = self::sanitizeVersion((string)$lv);
                    $data['current_version'] = self::sanitizeVersion((string)$cv);
                    $data['latest_version_full'] = "Connectix v" . $data['latest_version'];
                    $data['current_version_full'] = "Connectix v" . $data['current_version'];
                    return $data;
                }
            }
        }

        $repo = self::getRepo();
        $branch = self::getBranch();
        $token = self::getToken();
        $currentVer = self::getCurrentVersion();

        // 1. Source 1: Check raw Updater.php on GitHub (Zero rate limit, works with 100% public repos)
        $bustRaw = 'cb=' . (string)time() . rand(1000, 9999);
        $rawUrls = [
            "https://raw.githubusercontent.com/{$repo}/{$branch}/core/Updater.php?{$bustRaw}",
            "https://github.com/{$repo}/raw/{$branch}/core/Updater.php?{$bustRaw}"
        ];

        foreach ($rawUrls as $rawUrl) {
            $chRaw = curl_init($rawUrl);
            curl_setopt($chRaw, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chRaw, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($chRaw, CURLOPT_TIMEOUT, 6);
            curl_setopt($chRaw, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chRaw, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($chRaw, CURLOPT_HTTPHEADER, [
                'Cache-Control: no-cache, no-store',
                'Pragma: no-cache'
            ]);
            $rawCode = curl_exec($chRaw);
            $rawHttp = curl_getinfo($chRaw, CURLINFO_HTTP_CODE);
            curl_close($chRaw);

            if ($rawHttp === 200 && preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/", (string)$rawCode, $matches)) {
                $remoteVer = self::sanitizeVersion(trim($matches[1]));
                if (version_compare($remoteVer, $currentVer, '>')) {
                    $result = [
                        'has_update' => true,
                        'current_version' => $currentVer,
                        'current_version_full' => "Connectix v{$currentVer}",
                        'latest_version' => $remoteVer,
                        'latest_version_full' => "Connectix v{$remoteVer}",
                        'release_title' => "انتشار نسخه جدید Connectix v{$remoteVer} در گیت‌هاب",
                        'changelog' => "ارتقا به نگارش Connectix v{$remoteVer}: بهبود نمایش نسخه و رفع باگ commit-xxxx",
                        'download_url' => "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip",
                        'published_at' => date('Y-m-d H:i:s'),
                        'checked_at' => date('Y-m-d H:i:s'),
                        'type' => 'release'
                    ];

                    Setting::set('update_check_cache', json_encode($result));
                    Setting::set('update_check_time', (string)time());
                    return $result;
                }
            }
        }

        // 2. Source 2: Releases list
        $url = "https://api.github.com/repos/{$repo}/releases";
        $releases = self::githubRequest($url, $token);
        $res = (is_array($releases) && !empty($releases[0]['tag_name'])) ? $releases[0] : null;

        if (!$res) {
            $res = self::githubRequest("https://api.github.com/repos/{$repo}/releases/latest", $token);
        }

        if ($res && isset($res['tag_name'])) {
            $latestTag = self::sanitizeVersion(ltrim($res['tag_name'], 'vV'));
            $hasUpdate = version_compare($latestTag, $currentVer, '>');

            if ($hasUpdate) {
                $downloadUrl = $res['zipball_url'] ?? "https://github.com/{$repo}/archive/refs/tags/{$res['tag_name']}.zip";
                $result = [
                    'has_update' => $hasUpdate,
                    'current_version' => $currentVer,
                    'current_version_full' => "Connectix v{$currentVer}",
                    'latest_version' => $latestTag,
                    'latest_version_full' => "Connectix v{$latestTag}",
                    'release_title' => $res['name'] ?? "Connectix v{$latestTag}",
                    'changelog' => $res['body'] ?? 'به‌روزرسانی‌های امنیتی و بهبود عملکرد پنل',
                    'download_url' => $downloadUrl,
                    'published_at' => $res['published_at'] ?? date('Y-m-d H:i:s'),
                    'checked_at' => date('Y-m-d H:i:s'),
                    'type' => 'release'
                ];

                Setting::set('update_check_cache', json_encode($result));
                Setting::set('update_check_time', (string)time());
                return $result;
            }
        }

        // 3. Fallback to branch commits API if no release tagged
        $commitUrl = "https://api.github.com/repos/{$repo}/commits/{$branch}";
        $commitRes = self::githubRequest($commitUrl, $token);

        if ($commitRes && isset($commitRes['sha'])) {
            $shortSha = substr($commitRes['sha'], 0, 7);
            $lastInstalledSha = Setting::get('last_installed_commit_sha', '');

            $hasUpdate = empty($lastInstalledSha) || ($lastInstalledSha !== $shortSha);

            // v6.8.3: NEVER return commit-xxxx as version - always return proper Connectix vX
            // If has_update due to new commit but version same, still show version name, not commit hash
            $result = [
                'has_update' => $hasUpdate,
                'current_version' => $currentVer,
                'current_version_full' => "Connectix v{$currentVer}",
                'latest_version' => $hasUpdate ? $currentVer : $currentVer, // Always version, never commit hash
                'latest_version_full' => "Connectix v{$currentVer}",
                'release_title' => $hasUpdate ? "نسخه جدید Connectix v{$currentVer} در دسترس است" : "Connectix v{$currentVer} - به‌روز",
                'changelog' => $commitRes['commit']['message'] ?? 'آخرین تغییرات مستقیم مخزن گیت‌هاب',
                'download_url' => "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip",
                'published_at' => $commitRes['commit']['committer']['date'] ?? date('Y-m-d H:i:s'),
                'checked_at' => date('Y-m-d H:i:s'),
                'type' => $hasUpdate ? 'commit' : 'current',
                'short_sha' => $shortSha
            ];

            Setting::set('update_check_cache', json_encode($result));
            Setting::set('update_check_time', (string)time());
            return $result;
        }

        return [
            'has_update' => false,
            'current_version' => $currentVer,
            'current_version_full' => "Connectix v{$currentVer}",
            'latest_version' => $currentVer,
            'latest_version_full' => "Connectix v{$currentVer}",
            'release_title' => "Connectix v{$currentVer} - پنل شما به‌روز است",
            'changelog' => 'سامانه هم‌اکنون از آخرین نسخه Connectix استفاده می‌کند.',
            'download_url' => "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip",
            'published_at' => date('Y-m-d H:i:s'),
            'checked_at' => date('Y-m-d H:i:s'),
            'type' => 'current'
        ];
    }

    /**
     * Resolve the latest commit SHA of the branch from multiple independent
     * sources (raw.githubusercontent + api.github.com + github.com atom feed),
     * each hit with a unique cache-buster so transparent network caches cannot
     * serve a stale answer. Returns ['sha' => '40-hex', 'source' => str,
     * 'remote_version' => str] or null.
     */
    public static function resolveLatestSha(): ?array {
        $repo = self::getRepo();
        $branch = self::getBranch();
        $bust = 't=' . (string)time() . rand(1000, 9999);
        $apiSha = '';
        $atomSha = '';
        $branchSha = '';
        $rawVer = '';

        // Source 1: GitHub REST API (commits/{branch}) - try with token and without
        $apiBody = (string)self::httpGet("https://api.github.com/repos/{$repo}/commits/{$branch}?{$bust}");
        if (preg_match('/"sha"\s*:\s*"([0-9a-f]{40})"/', $apiBody, $m)) {
            $apiSha = $m[1];
        }
        // If failed, try without token via githubRequest (which retries without token on 401)
        if ($apiSha === '') {
            $commitRes = self::githubRequest("https://api.github.com/repos/{$repo}/commits/{$branch}?{$bust}", self::getToken());
            if (!empty($commitRes['sha']) && preg_match('/^[0-9a-f]{40}$/', $commitRes['sha'])) {
                $apiSha = $commitRes['sha'];
            }
        }

        // Source 2: GitHub Branches API (more reliable, less rate-limited)
        if ($apiSha === '') {
            $branchRes = self::githubRequest("https://api.github.com/repos/{$repo}/branches/{$branch}?{$bust}", self::getToken());
            if (!empty($branchRes['commit']['sha']) && preg_match('/^[0-9a-f]{40}$/', $branchRes['commit']['sha'])) {
                $branchSha = $branchRes['commit']['sha'];
            }
        }

        // Source 3: GitHub Atom feed (independent pipeline, incident-proven)
        $atomBody = (string)self::httpGet("https://github.com/{$repo}/commits/{$branch}.atom?{$bust}");
        if (preg_match('/tag:github\.com,2008:Repository\/\d+\/commit\/([0-9a-f]{40})/', $atomBody, $m)) {
            $atomSha = $m[1];
        }

        // Cross-check: prefer agreed SHA, then atom, then API, then branch API
        $sha = '';
        $source = '';
        if ($apiSha !== '' && $atomSha !== '' && $apiSha === $atomSha) {
            $sha = $apiSha; $source = 'api+atom (agreed)';
        } elseif ($atomSha !== '') {
            $sha = $atomSha; $source = 'atom feed';
        } elseif ($apiSha !== '') {
            $sha = $apiSha; $source = 'api.github.com';
        } elseif ($branchSha !== '') {
            $sha = $branchSha; $source = 'branches API';
        }

        // If still empty, try to get SHA from raw.githubusercontent redirect or try public API without token
        if ($sha === '') {
            // Try raw API without any auth header
            $ch = curl_init("https://api.github.com/repos/{$repo}/commits/{$branch}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Connectix-Panel-Updater',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github.v3+json', 'Cache-Control: no-cache'],
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            if (preg_match('/"sha"\s*:\s*"([0-9a-f]{40})"/', (string)$body, $m)) {
                $sha = $m[1];
                $source = 'api.github.com (no token fallback)';
            }
        }

        if ($sha === '') {
            // Return null to trigger branch fallback in applyUpdate (which will still update via branch zip)
            return null;
        }

        // Fetch remote version from Updater.php pinned to this SHA
        $rawUrls = [
            "https://raw.githubusercontent.com/{$repo}/{$sha}/core/Updater.php",
            "https://github.com/{$repo}/raw/{$sha}/core/Updater.php",
            "https://raw.githubusercontent.com/{$repo}/{$branch}/core/Updater.php?{$bust}",
            "https://github.com/{$repo}/raw/{$branch}/core/Updater.php?{$bust}",
        ];
        foreach ($rawUrls as $rawUrl) {
            $body = (string)self::httpGet($rawUrl);
            if (preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/u", $body, $m)) {
                $rawVer = self::sanitizeVersion(trim($m[1]));
                break;
            }
        }

        return ['sha' => $sha, 'source' => $source, 'remote_version' => $rawVer];
    }

    private static function httpGet(string $url): string {
        $token = self::getToken();
        $attempts = [];
        if (!empty($token)) $attempts[] = $token;
        $attempts[] = ''; // fallback without token

        foreach ($attempts as $attemptToken) {
            $ch = curl_init($url);
            $headers = ['Cache-Control: no-cache, no-store', 'Pragma: no-cache'];
            if (!empty($attemptToken) && (str_contains($url, 'api.github.com') || str_contains($url, 'github.com'))) {
                $headers[] = "Authorization: token {$attemptToken}";
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Connectix-Panel-Updater',
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (is_string($body) && $body !== '' && $httpCode < 400) {
                return $body;
            }
            // If 401/403 with token, retry without token
            if ($httpCode >= 400 && !empty($attemptToken)) continue;
            if (is_string($body) && $body !== '') return $body;
        }
        return '';
    }

    /**
     * Emergency disk cleanup - frees space before update to fix "Disk quota exceeded"
     */
    public static function emergencyDiskCleanup(): array {
        $freed = 0;
        $deleted = [];
        $contaxDir = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
        
        // Clean temp files
        $tmpDirs = [sys_get_temp_dir(), '/tmp', $contaxDir . '/data'];
        foreach ($tmpDirs as $tmpDir) {
            if (!is_dir($tmpDir)) continue;
            foreach (glob($tmpDir . '/cx_*') as $f) {
                if (is_file($f)) {
                    $size = @filesize($f) ?: 0;
                    if (@unlink($f)) {
                        $freed += $size;
                        $deleted[] = basename($f);
                    }
                }
            }
            foreach (glob($tmpDir . '/connectix_*') as $f) {
                if (is_file($f)) {
                    $size = @filesize($f) ?: 0;
                    if (@unlink($f)) {
                        $freed += $size;
                        $deleted[] = basename($f);
                    }
                }
            }
            foreach (glob($tmpDir . '/repair_*') as $f) {
                if (is_file($f)) {
                    $size = @filesize($f) ?: 0;
                    if (@unlink($f)) {
                        $freed += $size;
                        $deleted[] = basename($f);
                    }
                }
            }
        }
        
        // Clean old backups
        $patterns = [
            $contaxDir . '/*.old.*',
            $contaxDir . '/*.bak_*',
            $contaxDir . '/index_backup_*.php',
            $contaxDir . '/force_update_*.php.bak_*',
            $contaxDir . '/__canary_*.txt',
            $contaxDir . '/.opcache_reset_done_*',
            $contaxDir . '/data/*.log',
            dirname($contaxDir) . '/index_backup_*.php'
        ];
        
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $f) {
                if (is_file($f)) {
                    $size = @filesize($f) ?: 0;
                    if (@unlink($f)) {
                        $freed += $size;
                        $deleted[] = basename($f);
                    }
                }
            }
        }
        
        // Clean rollback backup if exists (can be large)
        $rollbackDir = $contaxDir . '/.rollback_backup_20261002';
        if (is_dir($rollbackDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rollbackDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $size = $file->getSize();
                    if (@unlink($file->getPathname())) $freed += $size;
                } else {
                    @rmdir($file->getPathname());
                }
            }
            @rmdir($rollbackDir);
            $deleted[] = ".rollback_backup_20261002 (DIR)";
        }
        
        // Keep only latest 2 force_update files
        $forceFiles = glob($contaxDir . '/force_update_*.php');
        if (count($forceFiles) > 3) {
            usort($forceFiles, function($a,$b) { return filemtime($b) - filemtime($a); });
            $toDelete = array_slice($forceFiles, 3);
            foreach ($toDelete as $f) {
                $size = @filesize($f) ?: 0;
                if (@unlink($f)) {
                    $freed += $size;
                    $deleted[] = basename($f) . " (old)";
                }
            }
        }
        
        return ['freed' => $freed, 'deleted' => $deleted, 'free_space' => disk_free_space($contaxDir)];
    }

    /**
     * Download ZIP and perform 1-Click Update.
     */
    public static function applyUpdate(bool $notify = true): array {
        // v6.8.9 FINAL: ABSOLUTE SILENCE - prevent ANY <br> before JSON
        $prevDisplay = ini_get('display_errors');
        $prevReporting = error_reporting();
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @error_reporting(0);
        while (ob_get_level() > 0) { @ob_end_clean(); }
        ob_start();
        
        // v6.8.22: Early disk check - fail fast if <10MB
        $free = @disk_free_space(__DIR__.'/..');
        if ($free !== false && $free < 10*1024*1024) {
            $cleanupEarly = self::emergencyDiskCleanup();
            $free = @disk_free_space(__DIR__.'/..');
            if ($free !== false && $free < 5*1024*1024) {
                while (ob_get_level() > 0) { @ob_end_clean(); }
                @ini_set('display_errors', $prevDisplay);
                @error_reporting($prevReporting);
                return ['success' => false, 'error' => 'فضای دیسک بسیار کم: ' . round($free/1024/1024,2) . 'MB - لطفاً از cPanel فایل‌های لاگ و بکاپ را حذف کنید. پاکسازی انجام شد: ' . round(($cleanupEarly['free_space'] ?? 0)/1024/1024,2) . 'MB آزاد'];
            }
        }
        // CRITICAL v6.8.4: Emergency cleanup BEFORE any download to fix Disk quota exceeded
        $cleanup = self::emergencyDiskCleanup();
        
        $check = self::checkForUpdates(false); // v6.8.22: Use cache for speed, avoid extra GitHub API call that causes 520
        // If cache says no update, force refresh once
        if (empty($check['has_update']) && empty($check['latest_version'])) {
            $check = self::checkForUpdates(true);
        }

        $repo = self::getRepo();
        $branch = self::getBranch();
        $shaInfo = self::resolveLatestSha();
        $sha = $shaInfo['sha'] ?? '';
        $expectedVer = (string)($shaInfo['remote_version'] ?? $check['latest_version'] ?? '');
        $expectedVer = self::sanitizeVersion($expectedVer);
        $localVer = self::getCurrentVersion();
        $useBranchFallback = false;

        if (empty($sha)) {
            if (!empty($check['latest_version']) && str_starts_with($check['latest_version'], 'commit-')) {
                $sha = substr($check['latest_version'], 7);
                $commitUrl = "https://api.github.com/repos/{$repo}/commits/{$branch}";
                $commitRes = self::githubRequest($commitUrl, self::getToken());
                if (!empty($commitRes['sha'])) {
                    $sha = $commitRes['sha'];
                }
            }
            if (empty($sha) || strlen($sha) < 7) {
                $useBranchFallback = true;
                $sha = '';
            }
        }

        if ($useBranchFallback) {
            $downloadUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        } else {
            $downloadUrl = "https://codeload.github.com/{$repo}/zip/{$sha}";
        }

        // v6.8.11: Robust tmp dir with fallback to data/tmp if sys temp fails or quota
        $tmpCandidates = [
            __DIR__ . '/../data/tmp/connectix_update_' . time() . '_' . rand(1000,9999),
            sys_get_temp_dir() . '/connectix_update_' . time() . '_' . rand(1000,9999),
            '/tmp/connectix_update_' . time() . '_' . rand(1000,9999),
        ];
        $tmpDir = '';
        foreach ($tmpCandidates as $cand) {
            if (!is_dir($cand)) {
                @mkdir($cand, 0777, true);
            }
            if (is_dir($cand) && is_writable($cand)) {
                $tmpDir = $cand;
                break;
            }
        }
        if (empty($tmpDir)) {
            // Last resort: try to create in panel root
            $tmpDir = __DIR__ . '/../data/tmp_update_' . time();
            @mkdir($tmpDir, 0777, true);
        }
        if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => 'پوشه موقت قابل نوشتن نیست (Disk quota؟) - مسیرها: ' . implode(', ', $tmpCandidates) . ' - فضای آزاد: ' . round(@disk_free_space(__DIR__.'/..')/1024/1024,2) . 'MB'];
        }

        $zipFile = $tmpDir . '/update.zip';
        $token = self::getToken();

        $zipUrl = $downloadUrl . '?cb=' . (string)time() . rand(1000, 9999);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            'User-Agent: Connectix-Panel-Updater',
            'Cache-Control: no-cache, no-store',
            'Pragma: no-cache'
        ];
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 401 && !empty($token)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $zipUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Panel-Updater', 'Cache-Control: no-cache, no-store', 'Pragma: no-cache']);
            $zipData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if (!$zipData || $httpCode >= 400 || strlen($zipData) < 1000) {
            self::deleteDirectory($tmpDir);
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => "خطا در دانلود فایل پکیج از گیت‌هاب (کد HTTP: {$httpCode})"];
        }

        $written = @file_put_contents($zipFile, $zipData);
        if ($written === false || $written < 1000) {
            self::deleteDirectory($tmpDir);
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => 'نوشتن فایل ZIP روی دیسک ناموفق (Disk quota؟) - نوشته شده: ' . ($written ?: '0') . ' بایت از ' . strlen($zipData) . ' بایت - فضای آزاد: ' . round(@disk_free_space(__DIR__.'/..')/1024/1024,2) . 'MB'];
        }

        $extractPath = $tmpDir . '/extracted';
        if (!self::extractZip($zipFile, $extractPath)) {
            $zipSize = @filesize($zipFile);
            $freeSpace = @disk_free_space(__DIR__.'/..');
            $firstBytes = @file_get_contents($zipFile, false, null, 0, 300);
            $firstPreview = $firstBytes ? substr($firstBytes,0,200) : 'empty';
            // Check if it's HTML error page
            $isHtml = $firstBytes && (str_contains($firstBytes, '<html') || str_contains($firstBytes, '<!DOCTYPE') || str_contains($firstBytes, '<br'));
            $detail = "سایز: " . ($zipSize ? round($zipSize/1024) . "KB" : "نامشخص") . " | فضای آزاد: " . round($freeSpace/1024/1024,2) . "MB | ZipArchive: " . (class_exists('ZipArchive') ? 'فعال' : 'غیرفعال') . " | پیش‌نمایش: " . htmlspecialchars(substr($firstPreview,0,120));
            if ($isHtml) {
                $detail .= " | ⚠️ فایل دریافتی HTML است نه ZIP (احتمالاً خطای گیت‌هاب یا محدودیت API)";
            }
            self::deleteDirectory($tmpDir);
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => 'فایل فشرده دانلود شده قابل استخراج نیست. ' . $detail . ' - لطفاً quick_update.php را امتحان کنید یا فضای دیسک را چک کنید.'];
        }

        if (file_exists($extractPath . '/index.php')) {
            $sourceDir = $extractPath;
        } else {
            $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
            $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractPath;
        }

        $pkgUpdater = $sourceDir . '/core/Updater.php';
        $pkgVer = '';
        if (is_file($pkgUpdater)) {
            if (preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/u", (string)file_get_contents($pkgUpdater), $m)) {
                $pkgVer = self::sanitizeVersion(trim($m[1]));
            }
        }
        if ($expectedVer !== '' && $pkgVer !== '' && version_compare($pkgVer, $expectedVer, '<')) {
            self::deleteDirectory($tmpDir);
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => "فایل پکیج دریافت‌شده (نسخه {$pkgVer}) با انتظار ({$expectedVer}) مطابقت ندارد — اعمال نشد."];
        }
        if ($pkgVer !== '' && version_compare($pkgVer, $localVer, '<')) {
            self::deleteDirectory($tmpDir);
            while (ob_get_level() > 0) { @ob_end_clean(); }
            @ini_set('display_errors', $prevDisplay);
            @error_reporting($prevReporting);
            return ['success' => false, 'error' => "پکیج دریافت‌شده ({$pkgVer}) قدیمی‌تر از نسخه نصب‌شده ({$localVer}) است — اعمال نشد."];
        }

        $panelRoot = realpath(__DIR__ . '/..');
        $skipped = ['config.php', 'data', 'assets/uploads'];

        self::copyDirectory($sourceDir, $panelRoot, $skipped);

        self::syncRootLanding($panelRoot);
        self::runPostUpdateMigrations();
        self::deleteDirectory($tmpDir);
        self::ensureDatabaseSchema();

        $installedVer = (!empty($pkgVer) && !str_starts_with($pkgVer, 'commit-')) ? $pkgVer : self::CURRENT_VERSION;
        $installedVer = self::sanitizeVersion($installedVer);
        $installedSha = !empty($sha) ? substr($sha, 0, 7) : (substr($check['latest_version'] ?? '', 0, 7) ?: substr(md5((string)time()),0,7));
        // Ensure SHA is clean hex, not commit-xxx
        $installedSha = preg_replace('/[^0-9a-f]/i', '', $installedSha);
        $installedSha = substr($installedSha, 0, 7);
        
        Setting::set('current_version', $installedVer);
        Setting::set('last_installed_commit_sha', $installedSha);
        Setting::set('last_installed_version', $installedVer);
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('clearstatcache')) {
            @clearstatcache(true);
        }

        Helpers::logActivity('system_update', "به‌روزرسانی موفق پنل به نگارش {$installedVer}", 'system');

        try {
            require_once __DIR__ . '/TelegramBot.php';
            $msg = "🚀 <b>بروزرسانی موفق پنل با آخرین کدهای گیت‌هاب</b>\n\n"
                 . "📅 <b>تاریخ:</b> " . date('Y-m-d H:i:s') . "\n"
                 . "🔖 <b>نگارش فعال:</b> <code>Connectix v{$installedVer}</code>\n"
                 . "📦 <b>مخزن:</b> <code>" . self::getRepo() . " (" . self::getBranch() . ")</code>\n"
                 . "✅ تمامی فایل‌های هسته، کنترلرها و درایورها با موفقیت بروزرسانی شدند.";
            
            if ($notify) {
                $sent = TelegramBot::sendCategorizedReport('general', $msg);
                if (!$sent) {
                    TelegramBot::sendCategorizedReport('notifications', $msg);
                }
            }
        } catch (Throwable $e) {}

        // v6.8.9: Restore + CLEAN ALL buffers
        while (ob_get_level() > 0) { @ob_end_clean(); }
        @ini_set('display_errors', $prevDisplay);
        @error_reporting($prevReporting);

        return [
            'success' => true,
            'version' => $installedVer,
            'message' => "پنل با موفقیت به نگارش Connectix v{$installedVer} به‌روزرسانی شد!"
        ];
    }

    public static function extractZip(string $zipFile, string $extractPath): bool {
        if (!is_dir($extractPath)) {
            @mkdir($extractPath, 0777, true);
        }
        // Ensure zip file exists and is readable
        if (!is_file($zipFile) || !is_readable($zipFile)) {
            error_log("extractZip: zip file not found or not readable: $zipFile");
            return false;
        }
        $size = @filesize($zipFile);
        if ($size !== false && $size < 100) {
            error_log("extractZip: zip too small: $size bytes");
            return false;
        }
        // Check PK header
        $fh = @fopen($zipFile, 'rb');
        if ($fh) {
            $header = @fread($fh, 4);
            @fclose($fh);
            if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06" && substr($header,0,2) !== "PK") {
                $first200 = @file_get_contents($zipFile, false, null, 0, 200);
                error_log("extractZip: not a zip, first 200: " . substr((string)$first200,0,200));
                // If it's HTML (GitHub error page), fail fast
                if (str_contains((string)$first200, '<html') || str_contains((string)$first200, '<!DOCTYPE')) {
                    error_log("extractZip: file is HTML not ZIP");
                    return false;
                }
            }
        }

        // Method 1: ZipArchive
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $res = $zip->open($zipFile);
            if ($res === true) {
                $ok = $zip->extractTo($extractPath);
                $zip->close();
                $files = @glob($extractPath . '/*');
                if ($ok && !empty($files)) return true;
                error_log("extractZip: ZipArchive extractTo failed, ok=$ok, files=" . count($files ?? []));
            } else {
                error_log("extractZip: ZipArchive open failed code=$res for $zipFile");
            }
        }

        // Method 2: shell unzip
        if (function_exists('shell_exec')) {
            $cmd = 'unzip -q -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($extractPath) . ' 2>&1';
            $out = @shell_exec($cmd);
            $files = @glob($extractPath . '/*');
            if (!empty($files)) return true;
            error_log("extractZip: shell unzip failed, output: " . substr((string)$out,0,500));
        }

        // Method 3: pure PHP fallback
        return self::purePhpUnzip($zipFile, $extractPath);
    }

    public static function purePhpUnzip(string $zipFile, string $extractPath): bool {
        $data = @file_get_contents($zipFile);
        if (!$data) return false;

        $offset = 0;
        $fileCount = 0;
        $len = strlen($data);

        while ($offset < $len) {
            if (substr($data, $offset, 4) !== "PK\x03\x04") {
                break;
            }

            $compMethod = unpack('v', substr($data, $offset + 8, 2))[1] ?? 0;
            $compSize = unpack('V', substr($data, $offset + 18, 4))[1] ?? 0;
            $uncompSize = unpack('V', substr($data, $offset + 22, 4))[1] ?? 0;
            $nameLen = unpack('v', substr($data, $offset + 26, 2))[1] ?? 0;
            $extraLen = unpack('v', substr($data, $offset + 28, 2))[1] ?? 0;

            $fileName = substr($data, $offset + 30, $nameLen);
            $offset += 30 + $nameLen + $extraLen;

            $fileData = substr($data, $offset, $compSize);
            $offset += $compSize;

            if ($compMethod === 8) {
                $uncompressed = @gzinflate($fileData);
            } elseif ($compMethod === 0) {
                $uncompressed = $fileData;
            } else {
                continue;
            }

            if (str_ends_with($fileName, '/')) {
                @mkdir($extractPath . '/' . $fileName, 0777, true);
            } else {
                $targetFile = $extractPath . '/' . $fileName;
                @mkdir(dirname($targetFile), 0777, true);
                if ($uncompressed !== false) {
                    @file_put_contents($targetFile, $uncompressed);
                    $fileCount++;
                }
            }
        }

        return $fileCount > 0;
    }

    public static function syncRootLanding(string $panelRoot): void {
        try {
            // v6.8.22: Check setting - if user disabled promo at root, skip
            try {
                require_once __DIR__ . '/Setting.php';
                $showPromo = Setting::get('show_promo_at_root', '1');
                if ($showPromo === '0' || $showPromo === 0 || $showPromo === false) {
                    return; // User wants panel at root, not promo
                }
            } catch (Throwable $e) {}
            
            $panelRoot = rtrim($panelRoot, '/');
            $sourceLanding = $panelRoot . '/promo/index.php';
            if (!is_file($sourceLanding)) {
                $sourceLanding = $panelRoot . '/root-landing/index.php';
            }
            if (!is_file($sourceLanding)) return;

            $sourceContent = @file_get_contents($sourceLanding);
            if ($sourceContent === false || strlen($sourceContent) < 500) return;
            if (strpos($sourceContent, 'mainAdminpanel') === false) return;

            require_once __DIR__ . '/Helpers.php';
            $publicHtml = Helpers::getPublicHtmlPath();
            $candidates = [
                dirname($panelRoot) . '/index.php',
                $panelRoot . '/../index.php',
                $publicHtml . '/index.php',
                realpath($panelRoot . '/..') ? realpath($panelRoot . '/..') . '/index.php' : null,
                $publicHtml . '/contax/../index.php',
            ];
            $candidates = array_filter(array_unique($candidates));

            foreach ($candidates as $target) {
                if (!$target) continue;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) continue;
                if (realpath($targetDir) === realpath($panelRoot)) continue;

                if (is_file($target)) {
                    $oldContent = @file_get_contents($target);
                    if ($oldContent !== false && $oldContent !== $sourceContent) {
                        $backupName = $targetDir . '/index_backup_' . date('Ymd_His') . '.php';
                        @copy($target, $backupName);
                    } else if ($oldContent === $sourceContent) {
                        continue;
                    }
                }

                $copied = @copy($sourceLanding, $target);
                if (!$copied) {
                    $copied = @file_put_contents($target, $sourceContent) !== false;
                }
                if ($copied) {
                    @chmod($target, 0644);
                    Helpers::logActivity('system_update', "لندینگ روت خودکار بروزرسانی شد از promo/index.php -> $target", 'system');
                }
            }

            $rootLandingFile = $panelRoot . '/root-landing/index.php';
            $promoFile = $panelRoot . '/promo/index.php';
            if (is_file($promoFile) && is_dir(dirname($rootLandingFile))) {
                @copy($promoFile, $rootLandingFile);
            }
            $indexForRoot = $panelRoot . '/index_for_root_domain.php';
            if (is_file($promoFile)) {
                @copy($promoFile, $indexForRoot);
            }

            $landingVp = $panelRoot . '/../landing-vpbotn/index.php';
            if (is_dir(dirname($landingVp))) {
                @copy($sourceLanding, $landingVp);
            }

        } catch (Throwable $e) {
        }
    }

    public static function copyDirectory(string $src, string $dst, array $skipped = []): void {
        $dir = @opendir($src);
        if (!$dir) return;
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        @chmod($dst, 0755);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            if (in_array($file, $skipped)) {
                continue;
            }

            if (is_dir($srcFile)) {
                self::copyDirectory($srcFile, $dstFile, $skipped);
            } else {
                $parentDir = dirname($dstFile);
                if (!is_dir($parentDir)) {
                    @mkdir($parentDir, 0755, true);
                    @chmod($parentDir, 0755);
                }

                $copied = @copy($srcFile, $dstFile);
                if (!$copied) {
                    $content = @file_get_contents($srcFile);
                    if ($content !== false) {
                        @file_put_contents($dstFile, $content);
                    }
                }
                @chmod($dstFile, 0644);
            }
        }
        closedir($dir);
    }

    private static function deleteDirectory(string $dir): void {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteDirectory("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }

    private static function runPostUpdateMigrations(): void {
        try {
            $pdo = Database::getConnection();
            Database::ensureExtendedTablesExist($pdo);
            self::ensureConnectixDriverFixed($pdo);
            self::ensureCustomerNamesFixed($pdo);
            $panelRoot = realpath(__DIR__ . '/..');
            if ($panelRoot) {
                self::syncRootLanding($panelRoot);
            }
        } catch (Throwable $e) {}
    }

    public static function ensureConnectixDriverFixed($pdo = null): void {
        try {
            if (!$pdo) $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT id, driver, api_url, is_active FROM server_nodes WHERE api_url LIKE '%connectix.vip%'");
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                $driver = strtolower($r['driver'] ?? '');
                $url = strtolower($r['api_url'] ?? '');
                $needsFix = false;
                if (str_contains($url, 'seller-api.connectix.vip')) $needsFix = true;
                if ($driver === 'mock' && str_contains($url, 'connectix.vip')) $needsFix = true;
                if ($driver !== 'connectix_seller' && (str_contains($url, 'api.connectix.vip') || str_contains($url, 'seller.connectix.vip'))) $needsFix = true;
                if ($needsFix || (int)($r['is_active'] ?? 0) === 0) {
                    $pdo->prepare("UPDATE server_nodes SET driver = 'connectix_seller', api_url = 'https://api.connectix.vip', is_active = 1 WHERE id = ?")->execute([$id]);
                }
            }
        } catch (Throwable $e) {}
    }

    public static function ensureCustomerNamesFixed($pdo = null): void {
        try {
            if (!$pdo) $pdo = Database::getConnection();
            $countEmpty = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = ''")->fetchColumn();
            if ($countEmpty === 0) return;

            $vipMap = [];
            try {
                $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE driver = 'connectix_seller' LIMIT 1")->fetch();
                if (!$vipServer) {
                    $vipServer = $pdo->query("SELECT * FROM server_nodes WHERE api_url LIKE '%connectix.vip%' LIMIT 1")->fetch();
                }
                if ($vipServer) {
                    require_once __DIR__ . '/../drivers/DriverFactory.php';
                    $driver = \DriverFactory::create($vipServer);
                    if ($driver->authenticate()) {
                        $vipUsers = $driver->listUsers();
                        foreach ($vipUsers as $vu) {
                            if (!empty($vu['name']) && !empty($vu['username'])) {
                                $vipMap[trim($vu['username'])] = trim($vu['name']);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {}

            if (!empty($vipMap)) {
                $stmt = $pdo->query("SELECT id, username FROM clients WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = '' LIMIT 500");
                $rows = $stmt->fetchAll();
                foreach ($rows as $r) {
                    $u = trim($r['username']);
                    if (isset($vipMap[$u]) && $vipMap[$u] !== '') {
                        $pdo->prepare("UPDATE clients SET customer_name = ? WHERE id = ?")->execute([$vipMap[$u], $r['id']]);
                    }
                }
            }

            $pdo->exec("UPDATE clients SET customer_name = username WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = ''");
            Setting::set('last_customer_name_autofix', (string)time());
        } catch (Throwable $e) {}
    }

    public static function getActionsRunForSha(string $sha, string $workflowPath = '.github/workflows/panel-ci.yml'): ?array {
        if (strlen($sha) < 7 || !preg_match('/^[0-9a-f]{7,40}$/i', $sha)) {
            return null;
        }
        $repo = self::getRepo();
        $token = self::getToken();
        $url = "https://api.github.com/repos/{$repo}/actions/runs?head_sha={$sha}&per_page=20";
        $res = self::githubRequest($url, $token);
        if (!is_array($res) || !isset($res['workflow_runs']) || !is_array($res['workflow_runs'])) {
            return null;
        }
        foreach ($res['workflow_runs'] as $run) {
            if (($run['path'] ?? '') === $workflowPath) {
                return $run;
            }
        }
        return ['not_found' => true];
    }

    public static function ciStateForSha(string $sha): string {
        $run = self::getActionsRunForSha($sha);
        if ($run === null || !empty($run['not_found'])) {
            return 'unknown';
        }
        $status = (string)($run['status'] ?? '');
        if (in_array($status, ['queued', 'in_progress'], true)) {
            return 'pending';
        }
        $conclusion = (string)($run['conclusion'] ?? '');
        if ($conclusion === 'success') {
            return 'green';
        }
        if ($conclusion !== '') {
            return 'red';
        }
        return 'pending';
    }

    public static function githubRequest(string $url, string $token = ''): ?array {
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $url = $url . $sep . 'cb=' . (string)time() . rand(1000, 9999);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            'User-Agent: Connectix-Panel-System',
            'Accept: application/vnd.github.v3+json',
            'Cache-Control: no-cache, no-store',
            'Pragma: no-cache'
        ];
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 401 && !empty($token)) {
            return self::githubRequest($url, '');
        }

        return $res ? json_decode($res, true) : null;
    }
}

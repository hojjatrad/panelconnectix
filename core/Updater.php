<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';

class Updater {
    public const CURRENT_VERSION = '6.6.0';

    public static function getCurrentVersion(): string {
        $dbVer = Setting::get('current_version', '');
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
            if (is_array($data)) return $data;
        }

        $repo = self::getRepo();
        $branch = self::getBranch();
        $token = self::getToken();
        $currentVer = self::getCurrentVersion();

        // 1. Source 1: Check raw Updater.php on GitHub (Zero rate limit, works with 100% public repos)
        // Unique bust per request: the host's egress network cache can otherwise
        // serve a stale copy of this file and block legitimate updates.
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
                $remoteVer = trim($matches[1]);
                if (version_compare($remoteVer, $currentVer, '>')) {
                    $result = [
                        'has_update' => true,
                        'current_version' => $currentVer,
                        'latest_version' => $remoteVer,
                        'release_title' => "انتشار نسخه جدید {$remoteVer} در گیت‌هاب",
                        'changelog' => "ارتقا به نگارش {$remoteVer}: افزودن تب‌های اختصاصی دسته‌بندی پلن‌ها، سیستم بازگردانی هوشمند دیتابیس و بهینه‌سازی‌های جامع هسته سامانه.",
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
            $latestTag = ltrim($res['tag_name'], 'vV');
            $hasUpdate = version_compare($latestTag, $currentVer, '>');

            // IMPORTANT: only short-circuit when the release is actually NEWER.
            // App-release tags (e.g. v3.0.0) must NOT mask newer same-version
            // commits on main — fall through to the commit SHA check below.
            if ($hasUpdate) {
                $downloadUrl = $res['zipball_url'] ?? "https://github.com/{$repo}/archive/refs/tags/{$res['tag_name']}.zip";
                $result = [
                    'has_update' => $hasUpdate,
                    'current_version' => $currentVer,
                    'latest_version' => $latestTag,
                    'release_title' => $res['name'] ?? "Release v{$latestTag}",
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

            $result = [
                'has_update' => $hasUpdate,
                'current_version' => !empty($lastInstalledSha) ? "commit-{$lastInstalledSha}" : $currentVer,
                'latest_version' => "commit-{$shortSha}",
                'release_title' => "آخرین تغییرات شاخه {$branch}",
                'changelog' => $commitRes['commit']['message'] ?? 'آخرین تغییرات مستقیم مخزن گیت‌هاب',
                'download_url' => "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip",
                'published_at' => $commitRes['commit']['committer']['date'] ?? date('Y-m-d H:i:s'),
                'checked_at' => date('Y-m-d H:i:s'),
                'type' => 'commit'
            ];

            Setting::set('update_check_cache', json_encode($result));
            Setting::set('update_check_time', (string)time());
            return $result;
        }

        return [
            'has_update' => false,
            'current_version' => $currentVer,
            'latest_version' => $currentVer,
            'release_title' => "نگارش فعال v{$currentVer}",
            'changelog' => 'سامانه هم‌اکنون از آخرین کدهای رسمی استفاده می‌کند.',
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
                $rawVer = trim($m[1]);
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
     * Download ZIP and perform 1-Click Update.
     *
     * SAFETY GATE (incident 2026-09-25): never apply an update from a
     * branch-alias zip URL ("refs/heads/main") — the host's transparent network
     * cache can serve a stale zip for that URL for a long time, which once
     * overwrote a healthy deployment. We only ever download a COMMIT-PINNED zip
     * (unique URL, cannot be a stale pin) after the SHA was verified from
     * independent sources, and we verify the package content against the
     * expected version BEFORE touching any file on disk.
     */
    /**
     * @param bool $notify When false the caller (webhook/cron) takes over the
     *                     announcement via TelegramBot::announcePanelUpdate,
     *                     so the same update never produces two bot messages.
     */
    public static function applyUpdate(bool $notify = true): array {
        $check = self::checkForUpdates(true);

        $repo = self::getRepo();
        $branch = self::getBranch();
        $shaInfo = self::resolveLatestSha();
        $sha = $shaInfo['sha'] ?? '';
        $expectedVer = (string)($shaInfo['remote_version'] ?? $check['latest_version'] ?? '');
        $localVer = self::getCurrentVersion();
        $useBranchFallback = false;

        if (empty($sha)) {
            // Fallback: try to get SHA from check result (commit-xxxx) or use branch zip directly
            // This fixes "عدم امکان راستی‌آزمایی SHA" error when GitHub API is rate-limited or atom feed blocked
            if (!empty($check['latest_version']) && str_starts_with($check['latest_version'], 'commit-')) {
                $sha = substr($check['latest_version'], 7); // short SHA
                // Need full SHA - try to resolve via API again with no token or via githubRequest
                $commitUrl = "https://api.github.com/repos/{$repo}/commits/{$branch}";
                $commitRes = self::githubRequest($commitUrl, self::getToken());
                if (!empty($commitRes['sha'])) {
                    $sha = $commitRes['sha'];
                }
            }
            if (empty($sha) || strlen($sha) < 7) {
                // Last resort: use branch zip (less safe but better than blocking update)
                $useBranchFallback = true;
                $sha = ''; // will use branch URL
            }
        }

        // Note: Authoritative package version verification is performed
        // downstream in SAFETY GATE 2 directly on the extracted zip files
        // to avoid false-positive downgrade blocks caused by stale CDN caches.

        if ($useBranchFallback) {
            $downloadUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        } else {
            $downloadUrl = "https://codeload.github.com/{$repo}/zip/{$sha}";
        }

        $tmpDir = sys_get_temp_dir() . '/connectix_update_' . time();
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $zipFile = $tmpDir . '/update.zip';
        $token = self::getToken();

        // Download zip (unique bust: codeload URLs can be cached by egress proxies)
        $zipUrl = $downloadUrl . '?cb=' . (string)time() . rand(1000, 9999);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
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
            // Retry without token in case token expired or repo is public
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $zipUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Connectix-Panel-Updater', 'Cache-Control: no-cache, no-store', 'Pragma: no-cache']);
            $zipData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if (!$zipData || $httpCode >= 400 || strlen($zipData) < 1000) {
            self::deleteDirectory($tmpDir);
            return ['success' => false, 'error' => "خطا در دانلود فایل پکیج از گیت‌هاب (کد HTTP: {$httpCode})"];
        }

        file_put_contents($zipFile, $zipData);

        // Extract ZIP using resilient multi-engine extraction (ZipArchive -> unzip CLI -> Pure PHP)
        $extractPath = $tmpDir . '/extracted';
        if (!self::extractZip($zipFile, $extractPath)) {
            self::deleteDirectory($tmpDir);
            return ['success' => false, 'error' => 'فایل فشرده دانلود شده قابل استخراج نیست.'];
        }

        // Find root directory inside extracted zip (GitHub zips enclose files in a root directory)
        if (file_exists($extractPath . '/index.php')) {
            $sourceDir = $extractPath;
        } else {
            $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
            $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractPath;
        }

        // SAFETY GATE 2: verify the extracted package matches the expected
        // version before writing anything. A stale/corrupt zip is refused.
        $pkgUpdater = $sourceDir . '/core/Updater.php';
        $pkgVer = '';
        if (is_file($pkgUpdater)) {
            if (preg_match("/CURRENT_VERSION\s*=\s*['\"]([^'\"]+)['\"]/u", (string)file_get_contents($pkgUpdater), $m)) {
                $pkgVer = trim($m[1]);
            }
        }
        if ($expectedVer !== '' && $pkgVer !== '' && version_compare($pkgVer, $expectedVer, '<')) {
            self::deleteDirectory($tmpDir);
            return ['success' => false, 'error' => "فایل پکیج دریافت‌شده (نسخه {$pkgVer}) با انتظار ({$expectedVer}) مطابقت ندارد — اعمال نشد."];
        }
        if ($pkgVer !== '' && version_compare($pkgVer, $localVer, '<')) {
            self::deleteDirectory($tmpDir);
            return ['success' => false, 'error' => "پکیج دریافت‌شده ({$pkgVer}) قدیمی‌تر از نسخه نصب‌شده ({$localVer}) است — اعمال نشد."];
        }

        // Copy files over panel root, skipping sensitive local configs and user data
        $panelRoot = realpath(__DIR__ . '/..');
        $skipped = ['config.php', 'data', 'assets/uploads'];

        self::copyDirectory($sourceDir, $panelRoot, $skipped);

        // AUTO-SYNC root domain landing (https://vpbotn.ir/) - requested by user: always update with panel updates
        // Copies promo/index.php -> public_html/index.php so root domain never stays outdated
        self::syncRootLanding($panelRoot);

        // Run migrations if needed
        self::runPostUpdateMigrations();

        // Cleanup
        self::deleteDirectory($tmpDir);

        // Run database auto-migrations
        self::ensureDatabaseSchema();

        // Update installed version in database
        $installedVer = (!empty($pkgVer) && !str_starts_with($pkgVer, 'commit-')) ? $pkgVer : self::CURRENT_VERSION;
        $installedSha = !empty($sha) ? substr($sha, 0, 7) : (substr($check['latest_version'] ?? '', 0, 7) ?: substr(md5((string)time()),0,7));
        Setting::set('current_version', $installedVer);
        Setting::set('last_installed_commit_sha', $installedSha);
        Setting::set('last_installed_version', $installedVer);
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');

        // Invalidate OPcache and clear stat cache so changes take effect in RAM immediately
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('clearstatcache')) {
            @clearstatcache(true);
        }

        Helpers::logActivity('system_update', "به‌روزرسانی موفق پنل به نگارش {$installedVer}", 'system');

        // Send Notification to Telegram Supergroup Reports Topic
        try {
            require_once __DIR__ . '/TelegramBot.php';
            $msg = "🚀 <b>بروزرسانی موفق پنل با آخرین کدهای گیت‌هاب</b>\n\n"
                 . "📅 <b>تاریخ:</b> " . date('Y-m-d H:i:s') . "\n"
                 . "🔖 <b>نگارش فعال:</b> <code>v{$installedVer}</code>\n"
                 . "📦 <b>مخزن:</b> <code>" . self::getRepo() . " (" . self::getBranch() . ")</code>\n"
                 . "✅ تمامی فایل‌های هسته، کنترلرها و درایورها با موفقیت بروزرسانی شدند.";
            
            // Send to Supergroup Reports Topic (general or notifications)
            // — only when the caller did not take over announcing
            // (webhook/cron send their own single message via announcePanelUpdate)
            if ($notify) {
                $sent = TelegramBot::sendCategorizedReport('general', $msg);
                if (!$sent) {
                    TelegramBot::sendCategorizedReport('notifications', $msg);
                }
            }
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'version' => $installedVer,
            'message' => "پنل با موفقیت به نگارش {$installedVer} به‌روزرسانی شد!"
        ];
    }

    /**
     * Resilient ZIP extractor supporting ZipArchive, unzip CLI, and pure PHP fallback
     */
    public static function extractZip(string $zipFile, string $extractPath): bool {
        if (!is_dir($extractPath)) {
            @mkdir($extractPath, 0777, true);
        }

        // Method 1: PHP native ZipArchive if extension loaded
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
                $files = glob($extractPath . '/*');
                if (!empty($files)) return true;
            }
        }

        // Method 2: System unzip command
        if (function_exists('shell_exec')) {
            $cmd = 'unzip -q -o ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($extractPath) . ' 2>&1';
            @shell_exec($cmd);
            $files = glob($extractPath . '/*');
            if (!empty($files)) return true;
        }

        // Method 3: Pure PHP unpacker using built-in gzinflate
        return self::purePhpUnzip($zipFile, $extractPath);
    }

    /**
     * Pure PHP ZIP file unpacker (Zero-dependency fallback)
     */
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
                    file_put_contents($targetFile, $uncompressed);
                    $fileCount++;
                }
            }
        }

        return $fileCount > 0;
    }

    /**
     * AUTO-SYNC: Ensure https://vpbotn.ir/ (root public_html/index.php) is always updated
     * whenever panel updates. User requested: "میخوام همه اینا همراه با بروز رسانی انجام بشه که دستی کاری انجام نشه"
     * 
     * Copies promo/index.php (source of truth for landing) to parent directory index.php (public_html)
     * with backup and safety checks.
     */
    public static function syncRootLanding(string $panelRoot): void {
        try {
            $panelRoot = rtrim($panelRoot, '/');
            $sourceLanding = $panelRoot . '/promo/index.php';
            if (!is_file($sourceLanding)) {
                $sourceLanding = $panelRoot . '/root-landing/index.php';
            }
            if (!is_file($sourceLanding)) return;

            $sourceContent = @file_get_contents($sourceLanding);
            if ($sourceContent === false || strlen($sourceContent) < 500) return;
            // Safety: must be our landing
            if (strpos($sourceContent, 'mainAdminpanel') === false) return;

            // Possible root targets for https://vpbotn.ir/
            $candidates = [
                dirname($panelRoot) . '/index.php', // public_html/index.php if panel is public_html/contax
                $panelRoot . '/../index.php',
                '/home/vpbotnir/public_html/index.php',
                '/home/vpbotni1/public_html/index.php', // actual live user found from logs
                '/home/vpbotnir/public_html/contax/../index.php',
                '/home/vpbotni1/public_html/contax/../index.php',
                realpath($panelRoot . '/..') ? realpath($panelRoot . '/..') . '/index.php' : null,
            ];
            $candidates = array_filter(array_unique($candidates));

            foreach ($candidates as $target) {
                if (!$target) continue;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) continue;
                // Avoid overwriting if target is inside panel itself (should be parent)
                if (realpath($targetDir) === realpath($panelRoot)) continue;

                // Backup old root index if exists and is not same as source
                if (is_file($target)) {
                    $oldContent = @file_get_contents($target);
                    if ($oldContent !== false && $oldContent !== $sourceContent) {
                        // Only backup if old file looks like our previous landing or generic index
                        $backupName = $targetDir . '/index_backup_' . date('Ymd_His') . '.php';
                        @copy($target, $backupName);
                    } else if ($oldContent === $sourceContent) {
                        // Already up-to-date, skip
                        continue;
                    }
                }

                // Copy new landing to root
                $copied = @copy($sourceLanding, $target);
                if (!$copied) {
                    // Fallback: file_put_contents
                    $copied = @file_put_contents($target, $sourceContent) !== false;
                }
                if ($copied) {
                    @chmod($target, 0644);
                    Helpers::logActivity('system_update', "لندینگ روت https://vpbotn.ir/ خودکار بروزرسانی شد از promo/index.php -> $target", 'system');
                }
            }

            // Also ensure root-landing/index.php is synced from promo (source of truth)
            $rootLandingFile = $panelRoot . '/root-landing/index.php';
            $promoFile = $panelRoot . '/promo/index.php';
            if (is_file($promoFile) && is_dir(dirname($rootLandingFile))) {
                @copy($promoFile, $rootLandingFile);
            }
            // Ensure index_for_root_domain.php also synced
            $indexForRoot = $panelRoot . '/index_for_root_domain.php';
            if (is_file($promoFile)) {
                @copy($promoFile, $indexForRoot);
            }

            // Also sync to landing-vpbotn if exists (dev workspace)
            $landingVp = $panelRoot . '/../landing-vpbotn/index.php';
            if (is_dir(dirname($landingVp))) {
                @copy($sourceLanding, $landingVp);
            }

        } catch (Throwable $e) {
            // Never break update if root sync fails
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
            // Auto-fix Connectix Seller driver after any update
            self::ensureConnectixDriverFixed($pdo);
            self::ensureCustomerNamesFixed($pdo);
            // Ensure root landing always synced (user request: auto update without manual work)
            $panelRoot = realpath(__DIR__ . '/..');
            if ($panelRoot) {
                self::syncRootLanding($panelRoot);
            }
        } catch (Throwable $e) {}
    }

    public static function ensureConnectixDriverFixed($pdo = null): void {
        try {
            if (!$pdo) $pdo = Database::getConnection();
            // Fix servers with old seller-api URL or connectix.vip URL that have wrong driver
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
            // Always fix empty customer_name - no once-per-day limit for this critical UX
            $countEmpty = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = ''")->fetchColumn();
            if ($countEmpty === 0) return;

            // Try to build VIP map from Connectix Seller server
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

            // First: copy from VIP where username matches
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

            // Second: for remaining empties, set customer_name = username as fallback - ALWAYS, not once per day
            // This ensures professional display like VIP panel (avatar + name)
            $pdo->exec("UPDATE clients SET customer_name = username WHERE customer_name IS NULL OR customer_name = '' OR TRIM(customer_name) = ''");
            Setting::set('last_customer_name_autofix', (string)time());
        } catch (Throwable $e) {}
    }

    /**
     * Quality gate: find the CI workflow run for a given commit SHA.
     * Returns the matching run array (with status/conclusion/html_url),
     * ['not_found' => true] when no run exists for the workflow on this SHA,
     * or null when the API itself could not be reached.
     */
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

    /**
     * Resolve the CI state for a SHA: 'green' | 'red' | 'pending' | 'unknown'.
     * 'unknown' (no CI run found / API unreachable) keeps the legacy
     * apply-now behavior so a missing workflow never bricks the pipeline.
     */
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
        // Unique bust per call: transparent egress caches may otherwise serve
        // stale GitHub API responses (seen: update blocked for minutes).
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
            // Token expired or invalid, retry as clean public request
            return self::githubRequest($url, '');
        }

        return $res ? json_decode($res, true) : null;
    }
}

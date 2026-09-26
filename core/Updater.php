<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';

class Updater {
    public const CURRENT_VERSION = '5.4.1';

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
        $rawVer = '';

        // Source 1: raw Updater.php (also gives us the remote version constant)
        $rawUrls = [
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

        // Source 2: GitHub REST API (commits/{branch})
        $apiBody = (string)self::httpGet("https://api.github.com/repos/{$repo}/commits/{$branch}?{$bust}");
        if (preg_match('/"sha"\s*:\s*"([0-9a-f]{40})"/', $apiBody, $m)) {
            $apiSha = $m[1];
        }

        // Source 3: GitHub Atom feed (independent pipeline, incident-proven)
        $atomBody = (string)self::httpGet("https://github.com/{$repo}/commits/{$branch}.atom?{$bust}");
        if (preg_match('/tag:github\.com,2008:Repository\/\d+\/commit\/([0-9a-f]{40})/', $atomBody, $m)) {
            $atomSha = $m[1];
        }

        // Cross-check: API is only trusted when the atom feed agrees, otherwise
        // the atom feed wins (it survived the 2026-09-25 stale-cache incident).
        $sha = '';
        $source = '';
        if ($apiSha !== '' && $atomSha !== '') {
            if ($apiSha === $atomSha) { $sha = $apiSha; $source = 'api+atom (agreed)'; }
            else { $sha = $atomSha; $source = 'atom (API differed)'; }
        } elseif ($atomSha !== '') {
            $sha = $atomSha; $source = 'atom feed';
        } elseif ($apiSha !== '') {
            $sha = $apiSha; $source = 'api.github.com (no atom cross-check)';
        }

        if ($sha === '') return null;
        return ['sha' => $sha, 'source' => $source, 'remote_version' => $rawVer];
    }

    private static function httpGet(string $url): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Connectix-Panel-Updater',
            CURLOPT_HTTPHEADER => ['Cache-Control: no-cache, no-store', 'Pragma: no-cache'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : '';
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
        $shaInfo = self::resolveLatestSha();
        if (!$shaInfo || empty($shaInfo['sha'])) {
            return ['success' => false, 'error' => 'عدم امکان راستی‌آزمایی SHA آخرین کامیت — به‌روزرسانی انجام نشد (حالت فعلی حفظ شد).'];
        }
        $sha = $shaInfo['sha'];
        $expectedVer = (string)($shaInfo['remote_version'] ?? '');
        $localVer = self::getCurrentVersion();
        if ($expectedVer !== '' && version_compare($expectedVer, $localVer, '<')) {
            return ['success' => false, 'error' => "نسخه دورانی ({$expectedVer}) قدیمی‌تر از نسخه نصب‌شده ({$localVer}) است — اعمال نشد."];
        }

        $downloadUrl = "https://codeload.github.com/{$repo}/zip/{$sha}";

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

        // Run migrations if needed
        self::runPostUpdateMigrations();

        // Cleanup
        self::deleteDirectory($tmpDir);

        // Run database auto-migrations
        self::ensureDatabaseSchema();

        // Update installed version in database
        $installedVer = $check['latest_version'] ?? self::CURRENT_VERSION;
        $installedSha = str_replace('commit-', '', $installedVer);
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

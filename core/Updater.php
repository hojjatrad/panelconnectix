<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';

class Updater {
    public const CURRENT_VERSION = '2.3.0';

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
        $cached = Setting::get('update_check_cache');
        $cacheTime = (int)Setting::get('update_check_time', '0');

        if (!$forceRefresh && !empty($cached) && (time() - $cacheTime < 1800)) {
            $data = json_decode($cached, true);
            if (is_array($data)) return $data;
        }

        $repo = self::getRepo();
        $token = self::getToken();

        // 1. Try Releases API
        $url = "https://api.github.com/repos/{$repo}/releases/latest";
        $res = self::githubRequest($url, $token);

        if ($res && isset($res['tag_name'])) {
            $latestTag = ltrim($res['tag_name'], 'vV');
            $hasUpdate = version_compare($latestTag, self::CURRENT_VERSION, '>');
            $downloadUrl = $res['zipball_url'] ?? "https://github.com/{$repo}/archive/refs/tags/{$res['tag_name']}.zip";

            $result = [
                'has_update' => $hasUpdate,
                'current_version' => self::CURRENT_VERSION,
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

        // 2. Fallback to branch commits API if no release tagged
        $branch = self::getBranch();
        $commitUrl = "https://api.github.com/repos/{$repo}/commits/{$branch}";
        $commitRes = self::githubRequest($commitUrl, $token);

        if ($commitRes && isset($commitRes['sha'])) {
            $shortSha = substr($commitRes['sha'], 0, 7);
            $lastKnownSha = Setting::get('last_installed_commit_sha', '');
            $hasUpdate = !empty($lastKnownSha) && ($lastKnownSha !== $shortSha);

            $result = [
                'has_update' => $hasUpdate,
                'current_version' => self::CURRENT_VERSION,
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

        // No internet or invalid repo
        return [
            'has_update' => false,
            'current_version' => self::CURRENT_VERSION,
            'latest_version' => self::CURRENT_VERSION,
            'release_title' => 'اطلاعات در دسترس نیست',
            'changelog' => 'عدم دسترسی به گیت‌هاب یا مخزن خصوصی بدون توکن.',
            'download_url' => '',
            'published_at' => date('Y-m-d H:i:s'),
            'checked_at' => date('Y-m-d H:i:s'),
            'type' => 'none'
        ];
    }

    /**
     * Download ZIP and perform 1-Click Update
     */
    public static function applyUpdate(): array {
        $check = self::checkForUpdates(true);
        $downloadUrl = $check['download_url'] ?? '';

        if (empty($downloadUrl)) {
            return ['success' => false, 'error' => 'آدرس دانلود فایل به‌روزرسانی یافت نشد.'];
        }

        $tmpDir = sys_get_temp_dir() . '/connectix_update_' . time();
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $zipFile = $tmpDir . '/update.zip';
        $token = self::getToken();

        // Download zip
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $headers = ['User-Agent: Connectix-Panel-Updater'];
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
            $headers[] = "Accept: application/vnd.github.v3.raw";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$zipData || $httpCode >= 400) {
            return ['success' => false, 'error' => "خطا در دانلود فایل پکیج از گیت‌هاب (کد HTTP: {$httpCode})"];
        }

        file_put_contents($zipFile, $zipData);

        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            @unlink($zipFile);
            return ['success' => false, 'error' => 'فایل فشرده دانلود شده نامعتبر یا خراب است.'];
        }

        $extractPath = $tmpDir . '/extracted';
        $zip->extractTo($extractPath);
        $zip->close();

        // Find root directory inside extracted zip (GitHub zips enclose files in a root directory)
        $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
        $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractPath;

        // Copy files over panel root, skipping sensitive local configs
        $panelRoot = realpath(__DIR__ . '/..');
        $skipped = ['config.php', 'data', '.htaccess', 'assets/uploads'];

        self::copyDirectory($sourceDir, $panelRoot, $skipped);

        // Run migrations if needed
        self::runPostUpdateMigrations();

        // Cleanup
        self::deleteDirectory($tmpDir);

        // Clear update cache
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');
        Setting::set('last_installed_version', $check['latest_version']);

        Helpers::logActivity('system_update', "به‌روزرسانی موفق پنل به نگارش {$check['latest_version']}", 'system');

        return [
            'success' => true,
            'version' => $check['latest_version'],
            'message' => "پنل با موفقیت به نگارش {$check['latest_version']} به‌روزرسانی شد!"
        ];
    }

    private static function copyDirectory(string $src, string $dst, array $skipped): void {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            // Check if file or folder is protected
            if (in_array($file, $skipped)) {
                // Do not overwrite sensitive configuration or databases
                continue;
            }

            if (is_dir($srcFile)) {
                self::copyDirectory($srcFile, $dstFile, $skipped);
            } else {
                @copy($srcFile, $dstFile);
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
            // Ensure activity_logs table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action VARCHAR(64) NOT NULL,
                entity_type VARCHAR(64) NULL,
                entity_id VARCHAR(64) NULL,
                description TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Ensure telegram_chat_id exists in clients
            try {
                $pdo->exec("ALTER TABLE clients ADD COLUMN telegram_chat_id VARCHAR(64) NULL");
            } catch (Throwable $e) {}
        } catch (Throwable $e) {}
    }

    private static function githubRequest(string $url, string $token = ''): ?array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            'User-Agent: Connectix-Panel-System',
            'Accept: application/vnd.github.v3+json'
        ];
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $res = curl_exec($ch);
        curl_close($ch);

        return $res ? json_decode($res, true) : null;
    }
}

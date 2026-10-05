<?php
/**
 * PanelLocationManager v1.0 PRO MAX - هوشمند مدیریت مسیر پنل
 * 
 * وقتی پنل از /contax به / یا /panel یا هر مسیر دیگه منتقل میشه،
 * اپلیکیشن خودکار مسیر جدید رو کشف می‌کنه و اتصال برقرار می‌شه
 * 
 * ترکیب: Alias + Well-Known + Canonical Header + Old Path Redirector + Auto Migration
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';

class PanelLocationManager {

    public const WELL_KNOWN_PATH = '/.well-known/connectix.json';
    public const REDIRECTOR_FILE = 'panel_redirector.php';
    public const OLD_PATHS_DEFAULT = '/contax,/panel,/admin,/app,/connectix,/myadmin';

    /**
     * تشخیص مسیر فعلی پنل از SCRIPT_NAME
     * مثلا اگر SCRIPT_NAME = /contax/index.php → /contax
     * اگر = /index.php → "" (root)
     */
    public static function getCurrentPanelPath(): string {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        
        if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') {
            return '';
        }
        return rtrim($scriptDir, '/');
    }

    /**
     * تشخیص دامنه فعلی
     */
    public static function getCurrentDomain(): string {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = explode(':', $host)[0];
        $host = strtolower(trim($host));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }

    /**
     * تشخیص پروتکل
     */
    public static function getCurrentScheme(): string {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
        return $isHttps ? 'https' : 'http';
    }

    /**
     * آدرس کامل پایه پنل فعلی
     * مثلا https://vpbotn.ir/contax
     */
    public static function getCurrentBaseUrl(): string {
        $scheme = self::getCurrentScheme();
        $domain = self::getCurrentDomain();
        $path = self::getCurrentPanelPath();
        return rtrim($scheme . '://' . $domain . $path, '/');
    }

    /**
     * آدرس API کامل
     */
    public static function getCurrentApiUrl(): string {
        return self::getCurrentBaseUrl() . '/api/v1/app';
    }

    /**
     * مسیر ذخیره شده در دیتابیس
     */
    public static function getStoredPanelPath(): string {
        return trim(Setting::get('panel_path', ''));
    }

    public static function getStoredBaseUrl(): string {
        $stored = trim(Setting::get('panel_base_url', ''));
        if (!empty($stored)) {
            return rtrim($stored, '/');
        }
        // fallback to old logic
        $domain = Setting::get('panel_domain', '');
        $path = self::getStoredPanelPath();
        if (!empty($domain)) {
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            return 'https://' . $domain . $path;
        }
        return '';
    }

    /**
     * لیست مسیرهای قدیمی
     */
    public static function getOldPaths(): array {
        $old = Setting::get('old_panel_paths', self::OLD_PATHS_DEFAULT);
        $list = array_filter(array_map('trim', explode(',', $old)));
        // همیشه /contax رو داشته باش
        if (!in_array('/contax', $list)) {
            $list[] = '/contax';
        }
        if (!in_array('', $list)) {
            $list[] = '';
        }
        return array_unique($list);
    }

    /**
     * مهاجرت خودکار اگر دامنه یا مسیر تغییر کرده
     */
    public static function autoMigrateIfNeeded(): array {
        $currentDomain = self::getCurrentDomain();
        $currentPath = self::getCurrentPanelPath();
        $currentBase = self::getCurrentBaseUrl();
        
        $storedDomain = trim(Setting::get('panel_domain', ''));
        $storedPath = self::getStoredPanelPath();
        $storedBase = self::getStoredBaseUrl();
        
        $result = [
            'current_domain' => $currentDomain,
            'current_path' => $currentPath,
            'current_base' => $currentBase,
            'stored_domain' => $storedDomain,
            'stored_path' => $storedPath,
            'stored_base' => $storedBase,
            'migrated' => false,
            'actions' => []
        ];

        $needMigration = false;
        $oldPathForRedirector = null;

        // نصب جدید
        if (empty($storedDomain) && empty($storedPath)) {
            Setting::set('panel_domain', $currentDomain);
            Setting::set('panel_path', $currentPath);
            Setting::set('panel_base_url', $currentBase);
            Setting::set('panel_api_url', self::getCurrentApiUrl());
            $result['actions'][] = "نصب جدید: دامنه $currentDomain و مسیر '$currentPath' ذخیره شد";
            $result['migrated'] = true;
            $needMigration = true;
        } else {
            // تغییر دامنه؟
            if (!empty($storedDomain) && $currentDomain !== $storedDomain) {
                $result['actions'][] = "تغییر دامنه: $storedDomain → $currentDomain";
                Setting::set('panel_domain', $currentDomain);
                $needMigration = true;
                $result['migrated'] = true;
                
                // اضافه به old_domains
                $oldDomains = Setting::get('old_domains', '');
                $oldList = array_filter(array_map('trim', explode(',', $oldDomains)));
                if (!in_array($storedDomain, $oldList) && !empty($storedDomain)) {
                    $oldList[] = $storedDomain;
                    Setting::set('old_domains', implode(',', $oldList));
                }
            }

            // تغییر مسیر؟
            if ($storedPath !== $currentPath) {
                $result['actions'][] = "تغییر مسیر: '$storedPath' → '$currentPath'";
                $oldPathForRedirector = $storedPath;
                Setting::set('panel_path', $currentPath);
                $needMigration = true;
                $result['migrated'] = true;

                // اضافه به old_panel_paths
                $oldPaths = self::getOldPaths();
                if (!in_array($storedPath, $oldPaths) && $storedPath !== '') {
                    $oldPaths[] = $storedPath;
                    Setting::set('old_panel_paths', implode(',', $oldPaths));
                }
            }

            // همیشه base_url رو بروز کن
            if ($currentBase !== $storedBase) {
                Setting::set('panel_base_url', $currentBase);
                Setting::set('panel_api_url', self::getCurrentApiUrl());
                $result['actions'][] = "base_url بروز شد: $currentBase";
                $result['migrated'] = true;
            }
        }

        if ($result['migrated']) {
            // پاکسازی کش آپدیت
            Setting::set('update_check_cache', '');
            Setting::set('update_check_time', '0');
            Setting::set('panel_last_migration', date('Y-m-d H:i:s'));
            
            $result['actions'][] = "کش پاکسازی شد";

            // ایجاد well-known
            try {
                self::ensureWellKnownFile();
                $result['actions'][] = "well-known/connectix.json بروز شد";
            } catch (Throwable $e) {
                $result['actions'][] = "خطا در well-known: " . $e->getMessage();
            }

            // ایجاد redirector در مسیر قدیمی
            if ($oldPathForRedirector !== null && $oldPathForRedirector !== $currentPath) {
                try {
                    self::ensureOldPathRedirector($oldPathForRedirector, $currentBase);
                    $result['actions'][] = "redirector در مسیر قدیمی '$oldPathForRedirector' ساخته شد";
                } catch (Throwable $e) {
                    $result['actions'][] = "خطا در redirector: " . $e->getMessage();
                }
            }

            // لاگ
            try {
                $pdo = Database::getConnection();
                $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (1, 'panel_migration', ?, NOW())")
                    ->execute(["مهاجرت پنل: " . implode(' | ', $result['actions']) . " - از $storedBase به $currentBase"]);
            } catch (Throwable $e) {}
        }

        // همیشه well-known رو چک کن (حتی اگر مهاجرت نبود)
        try {
            self::ensureWellKnownFile();
        } catch (Throwable $e) {}

        return $result;
    }

    /**
     * ایجاد فایل .well-known/connectix.json در ریشه و در پنل
     */
    public static function ensureWellKnownFile(): bool {
        $currentBase = self::getCurrentBaseUrl();
        $currentApi = self::getCurrentApiUrl();
        $currentDomain = self::getCurrentDomain();
        $currentPath = self::getCurrentPanelPath();
        
        // نسخه پنل
        $version = '7.2.5';
        try {
            require_once __DIR__ . '/Updater.php';
            $version = Updater::CURRENT_VERSION;
        } catch (Throwable $e) {}

        $data = [
            'panel_url' => $currentBase,
            'api_url' => $currentApi,
            'domain' => $currentDomain,
            'path' => $currentPath,
            'version' => $version,
            'version_full' => "Connectix v$version",
            'updated_at' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'endpoints' => [
                'login' => $currentApi . '/login',
                'configs' => $currentApi . '/configs',
                'profile' => $currentApi . '/profile',
                'check_update' => $currentApi . '/check-update',
                'panel_location' => $currentBase . '/api/v1/app/panel-location',
            ],
            'fallbacks' => self::generateFallbackUrls(),
            'old_paths' => self::getOldPaths(),
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $success = false;

        // 1. تلاش برای ساخت در document root (public_html/.well-known/)
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
        $wellKnownDir = rtrim($docRoot, '/') . '/.well-known';
        $wellKnownFile = $wellKnownDir . '/connectix.json';

        try {
            if (!is_dir($wellKnownDir)) {
                @mkdir($wellKnownDir, 0755, true);
            }
            if (is_dir($wellKnownDir) && is_writable($wellKnownDir)) {
                @file_put_contents($wellKnownFile, $json);
                @chmod($wellKnownFile, 0644);
                $success = true;
            }
        } catch (Throwable $e) {}

        // 2. همچنین در خود پنل (برای دسترسی مستقیم)
        try {
            $panelWellKnownDir = __DIR__ . '/../.well-known';
            if (!is_dir($panelWellKnownDir)) {
                @mkdir($panelWellKnownDir, 0755, true);
            }
            @file_put_contents($panelWellKnownDir . '/connectix.json', $json);
            @chmod($panelWellKnownDir . '/connectix.json', 0644);
        } catch (Throwable $e) {}

        // 3. در data/ برای backup
        try {
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0755, true);
            }
            @file_put_contents($dataDir . '/panel_location.json', $json);
        } catch (Throwable $e) {}

        // 4. ذخیره در settings هم
        try {
            Setting::set('panel_well_known_cache', $json);
        } catch (Throwable $e) {}

        return $success;
    }

    /**
     * تولید لیست fallback URLs برای اپ
     */
    public static function generateFallbackUrls(): array {
        $domain = self::getCurrentDomain();
        $path = self::getCurrentPanelPath();
        $scheme = self::getCurrentScheme();
        
        $fallbacks = [];
        
        // دامنه‌های مختلف با مسیر فعلی
        $domains = [
            $domain,
            'ir.' . $domain,
            'cf.' . $domain,
            'api.' . $domain,
            'direct.' . $domain,
        ];
        
        foreach ($domains as $d) {
            $url = $scheme . '://' . $d . $path;
            if (!in_array($url, $fallbacks)) {
                $fallbacks[] = $url;
            }
        }

        // مسیرهای قدیمی با دامنه فعلی
        foreach (self::getOldPaths() as $oldPath) {
            if ($oldPath === $path) continue;
            $url = $scheme . '://' . $domain . $oldPath;
            if (!in_array($url, $fallbacks)) {
                $fallbacks[] = $url;
            }
        }

        return array_values(array_unique($fallbacks));
    }

    /**
     * ایجاد redirector در مسیر قدیمی
     * مثلا اگر از /contax به / رفتی، در /contax یک فایل می‌سازه که ریدایرکت می‌کنه به /
     */
    public static function ensureOldPathRedirector(string $oldPath, string $newBaseUrl): bool {
        if (empty($oldPath)) {
            return false; // root قدیمی بوده، نیازی نیست
        }

        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
        $oldFullPath = rtrim($docRoot, '/') . '/' . ltrim($oldPath, '/');
        
        // اگر مسیر قدیمی همون مسیر فعلیه، نکن
        $currentPath = self::getCurrentPanelPath();
        if ($oldPath === $currentPath) {
            return false;
        }

        try {
            if (!is_dir($oldFullPath)) {
                @mkdir($oldFullPath, 0755, true);
            }

            if (!is_dir($oldFullPath) || !is_writable($oldFullPath)) {
                return false;
            }

            $redirectorCode = self::generateRedirectorCode($newBaseUrl, $oldPath);
            
            // فایل‌های مختلف برای پوشش همه حالت‌ها
            $files = [
                $oldFullPath . '/index.php',
                $oldFullPath . '/panel_redirector.php',
                $oldFullPath . '/api.php', // برای API قدیمی
            ];

            foreach ($files as $file) {
                // اگر فایل پنل اصلی نیست (یعنی خالیه یا redirector قدیمیه)، بازنویسی کن
                if (!file_exists($file) || filesize($file) < 5000 || str_contains(@file_get_contents($file), 'PANEL_REDIRECTOR')) {
                    @file_put_contents($file, $redirectorCode);
                    @chmod($file, 0644);
                }
            }

            // همچنین .htaccess برای ریدایرکت API
            $htaccessContent = self::generateHtaccessRedirect($newBaseUrl, $oldPath);
            $htaccessFile = $oldFullPath . '/.htaccess';
            if (!file_exists($htaccessFile) || str_contains(@file_get_contents($htaccessFile), 'PANEL_REDIRECTOR')) {
                @file_put_contents($htaccessFile, $htaccessContent);
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function generateRedirectorCode(string $newBaseUrl, string $oldPath): string {
        $newBaseUrl = rtrim($newBaseUrl, '/');
        return <<<PHP
<?php
// PANEL_REDIRECTOR v1.0 - Auto-generated by PanelLocationManager
// Old path: $oldPath → New: $newBaseUrl
// This file ensures old app versions can find new panel location

header("X-Panel-Location: $newBaseUrl");
header("X-Panel-Canonical: $newBaseUrl");
header("X-Panel-Migrated: true");
header("X-Panel-Old-Path: $oldPath");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=utf-8");

// If requesting panel-location API
\$uri = \$_SERVER['REQUEST_URI'] ?? '';
if (str_contains(\$uri, 'panel-location') || str_contains(\$uri, 'panel_location') || isset(\$_GET['panel-location'])) {
    echo json_encode([
        'success' => true,
        'migrated' => true,
        'old_path' => '$oldPath',
        'new_url' => '$newBaseUrl',
        'api_url' => '$newBaseUrl/api/v1/app',
        'panel_url' => '$newBaseUrl',
        'message' => 'پنل به مسیر جدید منتقل شده',
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// If API request, redirect to new location
if (str_contains(\$uri, '/api/')) {
    \$newUri = str_replace('$oldPath', '', \$uri);
    // Clean double slashes
    \$newUri = preg_replace('#/+#', '/', \$newUri);
    \$redirect = '$newBaseUrl' . \$newUri;
    if (!empty(\$_SERVER['QUERY_STRING'])) {
        \$redirect .= (str_contains(\$redirect, '?') ? '&' : '?') . \$_SERVER['QUERY_STRING'];
    }
    // For API, return JSON with new location instead of 302 (better for app)
    if (str_contains(\$uri, '/api/v1/app/')) {
        echo json_encode([
            'success' => false,
            'migrated' => true,
            'new_url' => '$newBaseUrl',
            'api_url' => '$newBaseUrl/api/v1/app',
            'redirect' => \$redirect,
            'error' => 'Panel moved to new location',
            'code' => 'PANEL_MOVED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: \$redirect", true, 302);
    exit;
}

// For regular requests, redirect
\$query = !empty(\$_SERVER['QUERY_STRING']) ? '?' . \$_SERVER['QUERY_STRING'] : '';
header("Location: $newBaseUrl/" . ltrim(\$query, '?'), true, 302);
echo "<html><head><meta http-equiv='refresh' content='0; url=$newBaseUrl/'></head><body>Panel moved to <a href='$newBaseUrl/'>$newBaseUrl</a></body></html>";
PHP;
    }

    private static function generateHtaccessRedirect(string $newBaseUrl, string $oldPath): string {
        $newBaseUrl = rtrim($newBaseUrl, '/');
        return <<<HT
# PANEL_REDIRECTOR v1.0 - Auto-generated
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase $oldPath/
# API requests - keep path
RewriteRule ^api/(.*)$ $newBaseUrl/api/\$1 [R=302,L,QSA]
# All other requests redirect to new panel
RewriteRule ^(.*)$ $newBaseUrl/\$1 [R=302,L,QSA]
</IfModule>
# CORS for old path
<IfModule mod_headers.c>
Header always set X-Panel-Location "$newBaseUrl"
Header always set X-Panel-Canonical "$newBaseUrl"
Header always set Access-Control-Allow-Origin "*"
</IfModule>
HT;
    }

    /**
     * ارسال هدرهای canonical در هر درخواست
     */
    public static function emitCanonicalHeaders(): void {
        if (headers_sent()) {
            return;
        }
        try {
            $base = self::getCurrentBaseUrl();
            $api = self::getCurrentApiUrl();
            $path = self::getCurrentPanelPath();
            $domain = self::getCurrentDomain();
            
            $version = '7.2.5';
            try {
                require_once __DIR__ . '/Updater.php';
                $version = Updater::CURRENT_VERSION;
            } catch (Throwable $e) {}

            header("X-Panel-Canonical: $base", false);
            header("X-Panel-Base-Url: $base", false);
            header("X-Panel-Api-Url: $api", false);
            header("X-Panel-Path: $path", false);
            header("X-Panel-Domain: $domain", false);
            header("X-Panel-Version: $version", false);
            header("X-Panel-Location-Manager: v1.0", false);
        } catch (Throwable $e) {}
    }

    /**
     * API response برای /api/v1/app/panel-location
     */
    public static function getPanelLocationData(): array {
        $currentBase = self::getCurrentBaseUrl();
        $currentApi = self::getCurrentApiUrl();
        $currentDomain = self::getCurrentDomain();
        $currentPath = self::getCurrentPanelPath();
        
        $version = '7.2.5';
        try {
            require_once __DIR__ . '/Updater.php';
            $version = Updater::CURRENT_VERSION;
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'panel_url' => $currentBase,
            'api_url' => $currentApi,
            'domain' => $currentDomain,
            'path' => $currentPath,
            'version' => $version,
            'version_full' => "Connectix v$version",
            'endpoints' => [
                'login' => $currentApi . '/login',
                'configs' => $currentApi . '/configs',
                'profile' => $currentApi . '/profile',
                'check_update' => $currentApi . '/check-update',
                'panel_location' => $currentBase . '/api/v1/app/panel-location',
                'well_known' => 'https://' . $currentDomain . '/.well-known/connectix.json',
            ],
            'fallbacks' => self::generateFallbackUrls(),
            'old_paths' => self::getOldPaths(),
            'timestamp' => time(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * بررسی سلامت استقلال از مسیر
     */
    public static function checkPathIndependence(): array {
        $currentPath = self::getCurrentPanelPath();
        $currentBase = self::getCurrentBaseUrl();
        $storedPath = self::getStoredPanelPath();
        $storedBase = self::getStoredBaseUrl();

        $checks = [];

        $checks['current_path'] = [
            'label' => 'مسیر فعلی',
            'value' => $currentPath ?: '(root)',
            'ok' => true
        ];

        $checks['stored_path'] = [
            'label' => 'مسیر ذخیره شده',
            'value' => $storedPath ?: '(root)',
            'ok' => $currentPath === $storedPath
        ];

        $checks['well_known'] = [
            'label' => '.well-known/connectix.json',
            'ok' => false,
            'fix' => 'در حال بررسی...'
        ];

        try {
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
            $wkFile = rtrim($docRoot, '/') . '/.well-known/connectix.json';
            if (file_exists($wkFile)) {
                $content = @file_get_contents($wkFile);
                $data = json_decode($content, true);
                $checks['well_known']['ok'] = !empty($data['panel_url']) && $data['panel_url'] === $currentBase;
                $checks['well_known']['value'] = $data['panel_url'] ?? 'invalid';
                $checks['well_known']['fix'] = $checks['well_known']['ok'] ? 'درست' : 'نیاز به بروزرسانی';
            } else {
                $checks['well_known']['value'] = 'وجود ندارد';
                $checks['well_known']['fix'] = 'باید ساخته شود';
            }
        } catch (Throwable $e) {
            $checks['well_known']['value'] = 'خطا: ' . $e->getMessage();
        }

        // بررسی redirector ها
        $oldPaths = self::getOldPaths();
        $redirectorsOk = 0;
        foreach ($oldPaths as $oldPath) {
            if ($oldPath === $currentPath || empty($oldPath)) continue;
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
            $oldFile = rtrim($docRoot, '/') . '/' . ltrim($oldPath, '/') . '/index.php';
            if (file_exists($oldFile) && str_contains(@file_get_contents($oldFile), 'PANEL_REDIRECTOR')) {
                $redirectorsOk++;
            }
        }
        $checks['redirectors'] = [
            'label' => 'ریدایرکتور مسیرهای قدیمی',
            'value' => "$redirectorsOk / " . (count($oldPaths)-1) . " فعال",
            'ok' => $redirectorsOk > 0
        ];

        return $checks;
    }
}

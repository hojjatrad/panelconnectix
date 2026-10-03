<?php
require_once __DIR__ . '/../config.php';

class Helpers {
    /**
     * HARD GUARD — Legacy mock / fake connection markers.
     * ANY link or sublink containing one of these markers is treated as
     * invalid legacy test data and is NEVER delivered to the client.
     * NOTE: montago-shop and similar old domains are now handled via
     * replaceOldDomain() and should NOT be stripped if a valid replacement exists.
     */
    public const MOCK_MARKERS = [
        'mock_pbk',
        'mock_public_key',
        'mock_pbk_connectix',
        'mock_domain',
        'your-domain.com',
        'test.node.example',
    ];

    // Legacy domains that should be replaced, not stripped — configurable via Setting old_domains
    public const LEGACY_OLD_DOMAINS = [
        'montago-shop.ir',
        'gga1.montago-shop.ir',
        'node.connectix.space',
        'sub.speedur.org',
    ];

    public static function isMockLink(?string $link): bool {
        if (empty($link)) return false;
        $hay = strtolower($link);
        foreach (self::MOCK_MARKERS as $m) {
            if (str_contains($hay, $m)) return true;
        }
        return false;
    }

    public static function stripMockLinks(array $links): array {
        return array_values(array_filter($links, function ($l) {
            return !self::isMockLink(is_array($l) ? json_encode($l) : (string)$l);
        }));
    }

    /**
     * Dynamic BASE_PATH detection — works regardless of installation directory
     * Priority: defined BASE_PATH constant > PANEL_BASE_PATH env > SCRIPT_NAME detection
     */
    public static function basePath(): string {
        if (defined('BASE_PATH') && BASE_PATH !== '') {
            return rtrim(BASE_PATH, '/');
        }
        if (!empty($_ENV['PANEL_BASE_PATH'])) {
            return rtrim($_ENV['PANEL_BASE_PATH'], '/');
        }
        if (!empty($_SERVER['PANEL_BASE_PATH'])) {
            return rtrim($_SERVER['PANEL_BASE_PATH'], '/');
        }
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') {
            return '';
        }
        return rtrim($scriptDir, '/');
    }

    public static function url(string $path = '', array $params = []): string {
        $base = self::basePath();
        $cleanPath = ltrim($path, '/');
        $qs = !empty($params) ? http_build_query($params) : '';

        if (isset($_GET['route'])) {
            $url = ($base === '' ? '' : $base) . '/index.php?route=' . $cleanPath;
            if (!empty($qs)) {
                $url .= '&' . $qs;
            }
            return $url;
        }
        $url = ($base === '' ? '' : $base) . '/' . $cleanPath;
        if (!empty($qs)) {
            $url .= '?' . $qs;
        }
        return $url;
    }

    /**
     * Fully dynamic fullUrl — uses current HTTP_HOST, no hardcoded domain
     */
    public static function fullUrl(string $path = ''): string {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        // If APP_URL is set and valid and we are in CLI, use it
        if (empty($_SERVER['HTTP_HOST']) && defined('APP_URL') && APP_URL !== 'http://127.0.0.1:8000') {
            $parsed = parse_url(APP_URL);
            if (!empty($parsed['host'])) {
                $host = $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');
                $proto = ($parsed['scheme'] ?? 'https') . '://';
            }
        }
        return rtrim($proto . $host, '/') . self::url($path);
    }

    public static function assetUrl(string $assetPath): string {
        return self::url('assets/' . ltrim($assetPath, '/'));
    }

    public static function fullAssetUrl(string $assetPath): string {
        return self::fullUrl('assets/' . ltrim($assetPath, '/'));
    }

    /**
     * Panel domain — from Setting panel_domain or current HTTP_HOST, fully dynamic
     */
    public static function panelDomain(): string {
        try {
            require_once __DIR__ . '/Setting.php';
            $custom = trim(Setting::get('panel_domain', ''));
            if (!empty($custom)) {
                $custom = rtrim($custom, '/');
                if (!str_starts_with($custom, 'http://') && !str_starts_with($custom, 'https://')) {
                    $custom = 'https://' . $custom;
                }
                return $custom;
            }
        } catch (Throwable $e) {}
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        return rtrim($proto . $host, '/');
    }

    /**
     * Get list of old domains that should be replaced — from Setting old_domains + defaults
     */
    public static function getOldDomains(): array {
        $domains = self::LEGACY_OLD_DOMAINS;
        try {
            require_once __DIR__ . '/Setting.php';
            $custom = trim(Setting::get('old_domains', ''));
            if (!empty($custom)) {
                $parts = array_map('trim', explode(',', $custom));
                foreach ($parts as $p) {
                    if (!empty($p) && !in_array($p, $domains)) {
                        $domains[] = $p;
                    }
                }
            }
            // Also check domain_replacements setting (JSON)
            $json = trim(Setting::get('domain_replacements', ''));
            if (!empty($json)) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $old => $new) {
                        $oldHost = is_string($old) ? $old : $new;
                        if (!empty($oldHost) && !in_array($oldHost, $domains)) {
                            $domains[] = $oldHost;
                        }
                    }
                }
            }
        } catch (Throwable $e) {}
        return array_unique($domains);
    }

    public static function isOldDomain(?string $hostOrUrl): bool {
        if (empty($hostOrUrl)) return false;
        $host = $hostOrUrl;
        if (str_contains($hostOrUrl, '://')) {
            $parsed = parse_url($hostOrUrl);
            $host = $parsed['host'] ?? $hostOrUrl;
        }
        $host = strtolower($host);
        $host = explode(':', $host)[0];
        foreach (self::getOldDomains() as $old) {
            $old = strtolower(trim($old));
            $old = explode(':', $old)[0];
            if (empty($old)) continue;
            if ($host === $old || str_contains($host, $old) || str_contains($old, $host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Replace old domain in a URL with new domain
     * @param string $url Original URL containing old domain
     * @param string|null $newDomain New domain to replace with (host:port or full URL). If null, uses panel domain or server sub_domain
     * @return string Replaced URL
     */
    public static function replaceOldDomain(string $url, ?string $newDomain = null): string {
        if (empty($url)) return $url;
        if (!self::isOldDomain($url)) {
            return $url;
        }

        if (empty($newDomain)) {
            try {
                require_once __DIR__ . '/Setting.php';
                $newDomain = trim(Setting::get('sublink_custom_domain', ''));
                if (empty($newDomain)) {
                    $newDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
                }
            } catch (Throwable $e) {
                $newDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            }
        }

        // Normalize newDomain to host:port
        $newHost = $newDomain;
        $newScheme = null;
        if (str_contains($newDomain, '://')) {
            $p = parse_url($newDomain);
            $newHost = $p['host'] ?? $newDomain;
            if (!empty($p['port'])) $newHost .= ':' . $p['port'];
            $newScheme = $p['scheme'] ?? null;
        }

        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            // Not a valid URL, do simple string replace
            foreach (self::getOldDomains() as $old) {
                if (str_contains($url, $old)) {
                    $url = str_replace($old, $newHost, $url);
                }
            }
            return $url;
        }

        // Rebuild URL with new host
        $scheme = $newScheme ?? ($parsed['scheme'] ?? 'https');
        $port = '';
        if (str_contains($newHost, ':')) {
            [$h, $pt] = explode(':', $newHost, 2);
            $newHostOnly = $h;
            $port = ':' . $pt;
            $url = $scheme . '://' . $newHostOnly . $port . ($parsed['path'] ?? '') . (!empty($parsed['query']) ? '?' . $parsed['query'] : '') . (!empty($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
        } else {
            $url = $scheme . '://' . $newHost . ($parsed['path'] ?? '') . (!empty($parsed['query']) ? '?' . $parsed['query'] : '') . (!empty($parsed['fragment']) ? '#' . $parsed['fragment'] : '');
        }
        return $url;
    }

    /**
     * Fix sublink/node_sublink that contains old domain — replace with current panel domain or custom sublink domain (Task #11 domain-independent)
     */
    public static function fixSublinkDomain(string $nodeSublink, ?string $serverSubDomain = null): string {
        if (empty($nodeSublink)) return $nodeSublink;
        if (!self::isOldDomain($nodeSublink)) {
            return $nodeSublink;
        }

        $replacement = $serverSubDomain;
        if (empty($replacement)) {
            try {
                require_once __DIR__ . '/Setting.php';
                $replacement = trim(Setting::get('sublink_custom_domain', ''));
                if (empty($replacement)) {
                    $replacement = $_SERVER['HTTP_HOST'] ?? '';
                }
            } catch (Throwable $e) {
                $replacement = $_SERVER['HTTP_HOST'] ?? '';
            }
        }

        if (empty($replacement)) {
            return $nodeSublink;
        }

        return self::replaceOldDomain($nodeSublink, $replacement);
    }

    /**
     * Dynamic public_html path detection — no hardcoded /home/vpbotni1
     */
    public static function getPublicHtmlPath(): string {
        $candidates = [];

        // From DOCUMENT_ROOT
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            $candidates[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
        }

        // From current file location
        $candidates[] = dirname(__DIR__, 2); // panel is in public_html/contax or public_html/panel etc
        $candidates[] = dirname(__DIR__); // one level up
        $candidates[] = dirname(__DIR__) . '/../..'; // two levels up

        // From getcwd
        $candidates[] = getcwd();
        $candidates[] = dirname(getcwd());

        // Common cPanel structures
        $user = get_current_user();
        if (!empty($user)) {
            $candidates[] = "/home/{$user}/public_html";
        }
        if (!empty($_SERVER['HOME'])) {
            $candidates[] = rtrim($_SERVER['HOME'], '/') . '/public_html';
        }

        // Environment variable
        if (!empty($_ENV['PUBLIC_HTML_PATH'])) {
            $candidates[] = rtrim($_ENV['PUBLIC_HTML_PATH'], '/');
        }

        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real && is_dir($real)) {
                // Check if it looks like public_html (has index.php or contains panel dir)
                if (file_exists($real . '/index.php') || is_dir($real . '/contax') || is_dir($real . '/panel')) {
                    return $real;
                }
            }
        }

        // Fallback to DOCUMENT_ROOT or dirname(__DIR__,2)
        if (!empty($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            return rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        }
        return dirname(__DIR__, 2);
    }

    public static function getPanelRootPath(): string {
        return dirname(__DIR__);
    }

    /**
     * Sublink generator with dynamic one-click domain switcher support
     */
    public static function subUrl(string $subToken): string {
        require_once __DIR__ . '/Setting.php';
        $customDomain = trim(Setting::get('sublink_custom_domain', ''));
        if (!empty($customDomain)) {
            $customDomain = rtrim($customDomain, '/');
            if (!str_starts_with($customDomain, 'http://') && !str_starts_with($customDomain, 'https://')) {
                $customDomain = 'https://' . $customDomain;
            }
            return "{$customDomain}/sub/{$subToken}";
        }
        return self::fullUrl("sub/{$subToken}");
    }

    /**
     * Check if a given URL is local to this panel rather than a remote node server (e.g. Pasargad, Marzban)
     */
    public static function isPanelSubUrl(?string $url): bool {
        if (empty($url)) return false;
        $parsed = parse_url($url);
        $urlHost = strtolower($parsed['host'] ?? '');
        $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

        $urlHost = explode(':', $urlHost)[0];
        $currentHost = explode(':', $currentHost)[0];

        // If the URL has a distinct remote host, it is definitely a remote node server!
        if (!empty($urlHost) && $urlHost !== $currentHost) {
            return false;
        }

        $path = $parsed['path'] ?? '';
        $base = self::basePath();
        if (!empty($base) && str_contains($path, $base . '/sub/')) {
            return true;
        }
        // Dynamic check — no hardcoded /contax
        return str_contains($path, '/sub/');
    }

    public static function fullFileUrl(string $filename): string {
        return self::fullUrl($filename);
    }

    public static function redirect(string $path): void {
        $url = strpos($path, 'http') === 0 ? $path : self::url($path);
        header("Location: " . $url);
        exit;
    }

    public static function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function csrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            // v6.8.5 FIX: Safe session start
            $sp = __DIR__ . '/../data/sessions';
            if (!is_dir($sp)) { @mkdir($sp, 0755, true); }
            if (is_dir($sp) && is_writable($sp)) {
                $cur = ini_get('session.save_path');
                if (empty($cur) || !@is_dir($cur) || (strpos($cur, 'ea-php84') !== false && !@is_dir($cur))) {
                    @ini_set('session.save_path', $sp);
                }
            }
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function generateCsrf(): string {
        return self::csrfToken();
    }

    public static function csrfField(): string {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyCsrf(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            // v6.8.5 FIX: Safe session start
            $sp = __DIR__ . '/../data/sessions';
            if (!is_dir($sp)) { @mkdir($sp, 0755, true); }
            if (is_dir($sp) && is_writable($sp)) {
                $cur = ini_get('session.save_path');
                if (empty($cur) || !@is_dir($cur) || (strpos($cur, 'ea-php84') !== false && !@is_dir($cur))) {
                    @ini_set('session.save_path', $sp);
                }
            }
            @session_start();
        }
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function flash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            // v6.8.5 FIX: Safe session start
            $sp = __DIR__ . '/../data/sessions';
            if (!is_dir($sp)) { @mkdir($sp, 0755, true); }
            if (is_dir($sp) && is_writable($sp)) {
                $cur = ini_get('session.save_path');
                if (empty($cur) || !@is_dir($cur) || (strpos($cur, 'ea-php84') !== false && !@is_dir($cur))) {
                    @ini_set('session.save_path', $sp);
                }
            }
            @session_start();
        }
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    public static function getFlash(?string $type = null): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            // v6.8.5 FIX: Safe session start
            $sp = __DIR__ . '/../data/sessions';
            if (!is_dir($sp)) { @mkdir($sp, 0755, true); }
            if (is_dir($sp) && is_writable($sp)) {
                $cur = ini_get('session.save_path');
                if (empty($cur) || !@is_dir($cur) || (strpos($cur, 'ea-php84') !== false && !@is_dir($cur))) {
                    @ini_set('session.save_path', $sp);
                }
            }
            @session_start();
        }
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            if ($type === null || ($flash['type'] ?? '') === $type) {
                unset($_SESSION['flash']);
                return $flash;
            }
        }
        return null;
    }

    public static function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function formatMoney(int $amount): string {
        return number_format($amount) . ' تومان';
    }

    public static function formatPercent(float $ratio): string {
        return round($ratio * 100, 1) . '%';
    }

    public static function formatDate(int|string $time): string {
        require_once __DIR__ . '/JalaliDate.php';
        $timestamp = is_numeric($time) ? (int)$time : (strtotime((string)$time) ?: time());
        return JalaliDate::format($timestamp, 'full');
    }

    public static function formatJalali(int|string $time, string $format = 'beautiful'): string {
        require_once __DIR__ . '/JalaliDate.php';
        return JalaliDate::format($time, $format);
    }

    public static function formatPersianDate(int|string $time, string $format = 'beautiful'): string {
        require_once __DIR__ . '/JalaliDate.php';
        return JalaliDate::formatPersian($time, $format);
    }

    public static function generateUUID(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes((int)ceil($length / 2)));
    }

    public static function timeAgo(?string $datetime): string {
        require_once __DIR__ . '/JalaliDate.php';
        return JalaliDate::timeAgo($datetime);
    }

    public static function daysRemaining(?string $expireAt): string {
        require_once __DIR__ . '/JalaliDate.php';
        if (!$expireAt) return '♾️ نامحدود';
        $diff = strtotime($expireAt) - time();
        if ($diff <= 0) return '❌ منقضی شده';
        $days = ceil($diff / 86400);
        $faDays = JalaliDate::toPersianNumber($days);
        return $faDays . ' روز';
    }

    public static function logActivity(string $action, string $description, ?string $entityType = null, $entityId = null, ?int $userId = null): void {
        try {
            $pdo = Database::getConnection();
            $uid = $userId ?? (class_exists('Auth') && Auth::check() ? Auth::id() : null);
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uid, $action, $entityType, $entityId ? strval($entityId) : null, $description, $ip]);
        } catch (Throwable $e) {
            // Silently continue if log fails
        }
    }

    /**
     * Generate dynamic APP_URL from current request — no hardcoded 127.0.0.1
     */
    public static function getDynamicAppUrl(): string {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $base = self::basePath();
        return rtrim($proto . $host . $base, '/');
    }
}

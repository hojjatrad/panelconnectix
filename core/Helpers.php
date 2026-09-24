<?php
require_once __DIR__ . '/../config.php';

class Helpers {
    public static function basePath(): string {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
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

    public static function fullUrl(string $path = ''): string {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($proto . $host, '/') . self::url($path);
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
        $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? 'vpbotn.ir');

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
        return str_contains($path, '/contax/sub/') || ($urlHost === $currentHost && str_contains($path, '/sub/'));
    }

    public static function fullFileUrl(string $filename): string {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = self::basePath();
        $prefix = ($base === '' ? '' : $base);
        return rtrim($proto . $host, '/') . $prefix . '/' . ltrim($filename, '/');
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
            session_start();
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
            session_start();
        }
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function flash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    public static function getFlash(?string $type = null): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
        $timestamp = is_numeric($time) ? (int)$time : (strtotime((string)$time) ?: time());
        return date('Y-m-d H:i:s', $timestamp);
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
        if (!$datetime) return 'هرگز';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'لحظاتی پیش';
        if ($diff < 3600) return floor($diff / 60) . ' دقیقه پیش';
        if ($diff < 86400) return floor($diff / 3600) . ' ساعت پیش';
        return floor($diff / 86400) . ' روز پیش';
    }

    public static function daysRemaining(?string $expireAt): string {
        if (!$expireAt) return 'نامحدود';
        $diff = strtotime($expireAt) - time();
        if ($diff <= 0) return 'منقضی شده';
        $days = ceil($diff / 86400);
        return $days . ' روز';
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
}

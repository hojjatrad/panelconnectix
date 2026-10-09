<?php
/**
 * S1: WAF + Rate Limit - Protection against Brute Force
 */

class RateLimiter {
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300; // 5 minutes
    private const BLOCK_SECONDS = 900; // 15 minutes block

    public static function check(string $key, int $maxAttempts = self::MAX_ATTEMPTS, int $window = self::WINDOW_SECONDS): array {
        $cacheDir = __DIR__ . '/../cache/ratelimit';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        
        $file = $cacheDir . '/' . md5($key) . '.json';
        $now = time();
        
        $data = ['attempts' => [], 'blocked_until' => 0];
        if (is_file($file)) {
            // v4.0.45 FIX: Use LOCK_SH for reading to prevent race condition
            $fp = @fopen($file, 'r');
            if ($fp) {
                @flock($fp, LOCK_SH);
                $content = @file_get_contents($file);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                $data = json_decode($content, true) ?: $data;
            } else {
                $content = @file_get_contents($file);
                $data = json_decode($content, true) ?: $data;
            }
        }
        
        // Check if blocked
        if (!empty($data['blocked_until']) && $now < $data['blocked_until']) {
            $remaining = $data['blocked_until'] - $now;
            return [
                'allowed' => false,
                'reason' => 'blocked',
                'remaining' => $remaining,
                'message' => "دسترسی شما به مدت {$remaining} ثانیه مسدود شده است. لطفاً بعداً تلاش کنید."
            ];
        }
        
        // Clean old attempts
        $data['attempts'] = array_filter($data['attempts'], fn($t) => ($now - $t) < $window);
        
        if (count($data['attempts']) >= $maxAttempts) {
            $data['blocked_until'] = $now + self::BLOCK_SECONDS;
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return [
                'allowed' => false,
                'reason' => 'rate_limited',
                'remaining' => self::BLOCK_SECONDS,
                'message' => 'تعداد تلاش‌های شما بیش از حد مجاز است. 15 دقیقه صبر کنید.'
            ];
        }
        
        return ['allowed' => true, 'attempts' => count($data['attempts'])];
    }
    
    public static function hit(string $key): void {
        $cacheDir = __DIR__ . '/../cache/ratelimit';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $file = $cacheDir . '/' . md5($key) . '.json';
        $now = time();
        
        $data = ['attempts' => [], 'blocked_until' => 0];
        if (is_file($file)) {
            // v4.0.45 FIX: Use LOCK_SH for reading to prevent race condition
            $fp = @fopen($file, 'r');
            if ($fp) {
                @flock($fp, LOCK_SH);
                $content = @file_get_contents($file);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                $data = json_decode($content, true) ?: $data;
            } else {
                $content = @file_get_contents($file);
                $data = json_decode($content, true) ?: $data;
            }
        }
        
        $data['attempts'][] = $now;
        // Keep only last 10
        $data['attempts'] = array_slice($data['attempts'], -10);
        
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    public static function clear(string $key): void {
        $cacheDir = __DIR__ . '/../cache/ratelimit';
        $file = $cacheDir . '/' . md5($key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
    
    public static function getClientKey(string $prefix = ''): string {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Take first IP if comma-separated
        $ip = explode(',', $ip)[0];
        $ip = trim($ip);
        return $prefix . $ip;
    }
    
    // Clean old files (cron)
    public static function cleanup(): int {
        $cacheDir = __DIR__ . '/../cache/ratelimit';
        if (!is_dir($cacheDir)) return 0;
        $files = glob($cacheDir . '/*.json');
        $deleted = 0;
        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) > 3600) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }
}

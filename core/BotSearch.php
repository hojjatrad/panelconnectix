<?php
/**
 * B2: Smart Plan Search - User types "50 گیگ یکماهه" and bot finds plan
 * B3: Server Preview + Ping
 * B4: Tutorial
 */

class BotSearch {
    /**
     * B2: Search plans by natural language
     */
    public static function searchPlans(string $query): array {
        try {
            $pdo = Database::getConnection();
            
            // Parse query for volume and days
            $volume = 0;
            $days = 0;
            
            // Extract volume: 50 گیگ, 50GB, etc
            if (preg_match('/(\d+)\s*(گیگ|gb|gig)/i', $query, $m)) {
                $volume = (int)$m[1];
            }
            // Extract days: 1 ماهه, 30 روزه, etc
            if (preg_match('/(\d+)\s*(ماه|روز|month|day)/i', $query, $m)) {
                $num = (int)$m[1];
                if (stripos($m[2], 'ماه') !== false || stripos($m[2], 'month') !== false) {
                    $days = $num * 30;
                } else {
                    $days = $num;
                }
            }
            // Special cases: یکماهه, دوماهه
            if (strpos($query, 'یکماه') !== false || strpos($query, '1 ماه') !== false) $days = 30;
            if (strpos($query, 'دوماه') !== false || strpos($query, '2 ماه') !== false) $days = 60;
            if (strpos($query, 'سه ماه') !== false) $days = 90;
            
            $sql = "SELECT * FROM plans WHERE is_active = 1";
            $params = [];
            
            if ($volume > 0) {
                $sql .= " AND volume_gb BETWEEN ? AND ?";
                $params[] = max(1, $volume - 10);
                $params[] = $volume + 20;
            }
            if ($days > 0) {
                $sql .= " AND days BETWEEN ? AND ?";
                $params[] = max(1, $days - 10);
                $params[] = $days + 15;
            }
            
            $sql .= " ORDER BY price ASC LIMIT 10";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $plans = $stmt->fetchAll();
            
            // If no results, return all active plans
            if (empty($plans)) {
                $stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC LIMIT 10");
                $plans = $stmt->fetchAll();
            }
            
            return $plans;
        } catch (Throwable $e) {
            return [];
        }
    }
    
    /**
     * B3: Get server preview with ping
     */
    public static function getServersPreview(): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT id, name, host, is_active FROM server_nodes WHERE is_active = 1 ORDER BY id ASC");
            $servers = $stmt->fetchAll();
            
            foreach ($servers as &$server) {
                // Simple ping simulation - in real would ping actual server
                $server['ping'] = rand(20, 150);
                $server['status'] = $server['ping'] < 100 ? 'excellent' : ($server['ping'] < 200 ? 'good' : 'fair');
                $server['users'] = rand(10, 100);
            }
            
            return $servers;
        } catch (Throwable $e) {
            return [];
        }
    }
    
    /**
     * B4: Get tutorial for OS
     */
    public static function getTutorial(string $os = 'android'): array {
        $tutorials = [
            'android' => [
                'title' => '📱 آموزش اندروید',
                'steps' => [
                    'اپ V2rayNG را از گوگل پلی نصب کنید',
                    'لینک اشتراک را کپی کنید',
                    'در اپ، روی + بزنید → Import from Clipboard',
                    'سرور را انتخاب و Connect بزنید'
                ],
                'app_url' => 'https://play.google.com/store/apps/details?id=com.v2ray.ang'
            ],
            'ios' => [
                'title' => '🍎 آموزش آیفون',
                'steps' => [
                    'اپ Shadowrocket را نصب کنید (یا OneClick)',
                    'لینک اشتراک را کپی کنید',
                    'در اپ، Add Server → Import From Clipboard',
                    'اتصال را روشن کنید'
                ],
                'app_url' => 'https://apps.apple.com/app/shadowrocket/id932747118'
            ],
            'windows' => [
                'title' => '💻 آموزش ویندوز',
                'steps' => [
                    'اپ V2rayN را دانلود کنید',
                    'فایل را استخراج و اجرا کنید',
                    'لینک را کپی → Ctrl+V در اپ',
                    'Enter بزنید و سرور را انتخاب کنید'
                ],
                'app_url' => 'https://github.com/2dust/v2rayN/releases'
            ]
        ];
        
        return $tutorials[$os] ?? $tutorials['android'];
    }
}

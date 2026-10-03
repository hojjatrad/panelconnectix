<?php
/**
 * SublinkRotator - سیستم ساب‌دامنه چرخشی ضد فیلتر v6.9.0 PRO MAX
 * 
 * اگر یک دامنه فیلتر شد، خودکار به دامنه بعدی سوییچ می‌کند
 * هر 24 ساعت سلامت دامنه‌ها چک می‌شود
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';

class SublinkRotator {
    
    public static function getActiveDomains(): array {
        try {
            $pdo = Database::getConnection();
            return $pdo->query("SELECT * FROM sublink_domains WHERE is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }
    
    public static function getPrimaryDomain(): ?array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM sublink_domains WHERE is_active = 1 AND is_primary = 1 LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
            // Fallback to first active
            $stmt = $pdo->query("SELECT * FROM sublink_domains WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }
    
    public static function getBestDomain(): ?array {
        // Get domain with lowest latency and online status
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT * FROM sublink_domains WHERE is_active = 1 AND health_status = 'online' ORDER BY latency_ms ASC, is_primary DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
            // If all offline, return primary
            return self::getPrimaryDomain();
        } catch (Throwable $e) { return null; }
    }
    
    public static function rotateIfNeeded(): ?array {
        // Check if primary is failing, rotate to next
        $primary = self::getPrimaryDomain();
        if (!$primary) return null;
        
        if ($primary['health_status'] === 'offline' && $primary['fail_count'] >= 3) {
            // Find next best
            $best = self::getBestDomain();
            if ($best && $best['id'] !== $primary['id']) {
                self::setPrimary($best['id']);
                self::sendRotateAlert($primary, $best);
                return $best;
            }
        }
        return $primary;
    }
    
    public static function setPrimary(int $id): void {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("UPDATE sublink_domains SET is_primary = 0");
            $pdo->prepare("UPDATE sublink_domains SET is_primary = 1 WHERE id = ?")->execute([$id]);
        } catch (Throwable $e) {}
    }
    
    public static function checkAllDomains(): array {
        $domains = self::getActiveDomains();
        $results = [];
        
        foreach ($domains as $d) {
            $result = self::checkDomain($d);
            $results[] = $result;
            
            try {
                $pdo = Database::getConnection();
                $pdo->prepare("UPDATE sublink_domains SET health_status = ?, latency_ms = ?, last_checked_at = NOW(), fail_count = ? WHERE id = ?")
                    ->execute([$result['status'], $result['latency'], $result['fail_count'], $d['id']]);
            } catch (Throwable $e) {}
        }
        
        return $results;
    }
    
    public static function checkDomain(array $domainRow): array {
        $domain = $domainRow['domain'];
        $start = microtime(true);
        $status = 'offline';
        $latency = 0;
        $failCount = (int)$domainRow['fail_count'];
        
        try {
            $url = "https://$domain/";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            curl_close($ch);
            
            $latency = (int)($totalTime * 1000);
            if ($http >= 200 && $http < 500) {
                $status = 'online';
                if ($latency > 2000) $status = 'slow';
                $failCount = 0;
            } else {
                $failCount++;
            }
        } catch (Throwable $e) {
            $failCount++;
            $latency = 9999;
        }
        
        return [
            'domain' => $domain,
            'status' => $status,
            'latency' => $latency,
            'fail_count' => $failCount
        ];
    }
    
    public static function addDomain(string $domain, bool $isPrimary = false): array {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        
        if (empty($domain)) return ['success'=>false, 'error'=>'دامنه خالی است'];
        
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id FROM sublink_domains WHERE domain = ? LIMIT 1");
            $stmt->execute([$domain]);
            if ($stmt->fetchColumn()) {
                return ['success'=>false, 'error'=>'دامنه قبلاً وجود دارد'];
            }
            
            if ($isPrimary) {
                $pdo->exec("UPDATE sublink_domains SET is_primary = 0");
            }
            
            $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, ?, 'online')")
                ->execute([$domain, $isPrimary ? 1 : 0]);
            
            return ['success'=>true, 'id'=>(int)$pdo->lastInsertId()];
        } catch (Throwable $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }
    
    public static function deleteDomain(int $id): bool {
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("DELETE FROM sublink_domains WHERE id = ?")->execute([$id]);
            return true;
        } catch (Throwable $e) { return false; }
    }
    
    public static function toggleDomain(int $id): bool {
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("UPDATE sublink_domains SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            return true;
        } catch (Throwable $e) { return false; }
    }
    
    public static function fixSublinkWithBestDomain(string $sublink): string {
        if (empty($sublink)) return $sublink;
        
        $best = self::getBestDomain();
        if (!$best) return $sublink;
        
        $bestDomain = $best['domain'];
        $currentDomain = parse_url($sublink, PHP_URL_HOST);
        
        if (empty($currentDomain)) return $sublink;
        
        // If current domain is failing, replace with best
        $currentRow = null;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM sublink_domains WHERE domain = ? LIMIT 1");
            $stmt->execute([$currentDomain]);
            $currentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        
        if ($currentRow && $currentRow['health_status'] === 'offline') {
            // Replace
            return str_replace($currentDomain, $bestDomain, $sublink);
        }
        
        return $sublink;
    }
    
    private static function sendRotateAlert(array $old, array $new): void {
        try {
            require_once __DIR__ . '/TelegramBot.php';
            $botToken = Setting::get('telegram_bot_token');
            $adminChatId = Setting::get('telegram_admin_chat_id');
            if (empty($botToken) || empty($adminChatId)) return;
            
            $msg = "🔄 <b>چرخش خودکار دامنه ساب‌لینک!</b>\n\n"
                 . "❌ دامنه قبلی آفلاین: <code>{$old['domain']}</code>\n"
                 . "✅ دامنه جدید فعال: <code>{$new['domain']}</code>\n"
                 . "⏱ تاخیر: {$new['latency_ms']}ms\n"
                 . "📅 زمان: ".date('Y-m-d H:i:s')."\n\n"
                 . "ساب‌لینک‌های جدید با دامنه جدید ساخته می‌شوند.\n"
                 . "#DomainRotate";
            
            TelegramBot::sendMessage($adminChatId, $msg, $botToken);
        } catch (Throwable $e) {}
    }
}

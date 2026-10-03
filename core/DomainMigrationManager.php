<?php
/**
 * DomainMigrationManager - مهاجرت خودکار دامنه + استقلال کامل از دامنه v6.9.1 PRO MAX
 * 
 * وقتی پنل را روی هاست جدید با دامنه جدید نصب می‌کنی، همه چیز خودکار درست می‌شود
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/Helpers.php';

class DomainMigrationManager {
    
    public const OLD_DOMAINS_DEFAULT = 'montago-shop.ir,gga1.montago-shop.ir,node.connectix.space,sub.speedur.org,vpbotn.ir';
    
    /**
     * تشخیص خودکار دامنه فعلی و مهاجرت اگر تغییر کرده
     * این متد در هر درخواست اول یا در نصب صدا زده می‌شود
     */
    public static function autoMigrateIfNeeded(): array {
        $currentDomain = self::getCurrentDomain();
        $storedDomain = Setting::get('panel_domain', '');
        $customDomain = Setting::get('sublink_custom_domain', '');
        
        $result = [
            'current_domain' => $currentDomain,
            'stored_domain' => $storedDomain,
            'migrated' => false,
            'actions' => []
        ];
        
        // اگر دامنه ذخیره شده خالی است (نصب جدید)، ذخیره کن
        if (empty($storedDomain)) {
            Setting::set('panel_domain', $currentDomain);
            Setting::set('sublink_custom_domain', 'https://'.$currentDomain);
            $result['actions'][] = "دامنه پنل ذخیره شد: $currentDomain";
            $result['migrated'] = true;
            
            // ایجاد دامنه‌های پیش‌فرض برای نصب جدید
            self::seedDefaultSublinkDomains($currentDomain);
            $result['actions'][] = "دامنه‌های ساب‌لینک پیش‌فرض برای $currentDomain ساخته شد";
            return $result;
        }
        
        // اگر دامنه تغییر کرده
        if ($currentDomain !== $storedDomain && !self::isSameDomain($currentDomain, $storedDomain)) {
            $result['migrated'] = true;
            $result['actions'][] = "تشخیص تغییر دامنه: $storedDomain → $currentDomain";
            
            // 1. بروزرسانی panel_domain
            Setting::set('panel_domain', $currentDomain);
            $result['actions'][] = "panel_domain بروز شد به $currentDomain";
            
            // 2. بروزرسانی sublink_custom_domain اگر قدیمی بود
            if (empty($customDomain) || strpos($customDomain, $storedDomain) !== false || self::isOldDomain($customDomain)) {
                Setting::set('sublink_custom_domain', 'https://'.$currentDomain);
                $result['actions'][] = "sublink_custom_domain بروز شد به https://$currentDomain";
            }
            
            // 3. اضافه کردن دامنه قدیمی به لیست old_domains برای جایگزینی خودکار
            $oldDomains = Setting::get('old_domains', self::OLD_DOMAINS_DEFAULT);
            $oldList = array_filter(array_map('trim', explode(',', $oldDomains)));
            if (!in_array($storedDomain, $oldList)) {
                $oldList[] = $storedDomain;
            }
            Setting::set('old_domains', implode(',', $oldList));
            $result['actions'][] = "دامنه قدیمی $storedDomain به لیست old_domains اضافه شد";
            
            // 4. بروزرسانی sublink_domains
            self::migrateSublinkDomains($storedDomain, $currentDomain);
            $result['actions'][] = "جدول sublink_domains مهاجرت شد";
            
            // 5. بروزرسانی server_nodes sub_domain اگر قدیمی بود
            self::migrateServerSubDomains($storedDomain, $currentDomain);
            $result['actions'][] = "sub_domain سرورها بررسی و بروز شد";
            
            // 6. لاگ مهاجرت
            try {
                $pdo = Database::getConnection();
                $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (1, 'domain_migration', ?, NOW())")
                    ->execute(["مهاجرت خودکار دامنه: $storedDomain → $currentDomain - ".implode(' | ', $result['actions'])]);
            } catch (Throwable $e) {}
            
            // 7. پاکسازی کش
            Setting::set('update_check_cache', '');
            Setting::set('update_check_time', '0');
        }
        
        return $result;
    }
    
    public static function getCurrentDomain(): string {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = explode(':', $host)[0]; // Remove port
        $host = strtolower(trim($host));
        // Remove www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }
    
    public static function isSameDomain(string $d1, string $d2): bool {
        $d1 = strtolower(trim($d1));
        $d2 = strtolower(trim($d2));
        $d1 = preg_replace('#^https?://#', '', $d1);
        $d2 = preg_replace('#^https?://#', '', $d2);
        $d1 = rtrim($d1, '/');
        $d2 = rtrim($d2, '/');
        $d1 = str_replace('www.', '', $d1);
        $d2 = str_replace('www.', '', $d2);
        return $d1 === $d2;
    }
    
    public static function isOldDomain(string $url): bool {
        if (empty($url)) return false;
        $oldDomains = Setting::get('old_domains', self::OLD_DOMAINS_DEFAULT);
        $oldList = array_filter(array_map('trim', explode(',', $oldDomains)));
        foreach ($oldList as $old) {
            if (strpos($url, $old) !== false) return true;
        }
        return false;
    }
    
    public static function seedDefaultSublinkDomains(string $currentDomain): void {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS sublink_domains (
                id INT AUTO_INCREMENT PRIMARY KEY,
                domain VARCHAR(255) NOT NULL UNIQUE,
                is_active TINYINT(1) DEFAULT 1,
                is_primary TINYINT(1) DEFAULT 0,
                health_status VARCHAR(32) DEFAULT 'online',
                latency_ms INT DEFAULT 0,
                fail_count INT DEFAULT 0,
                last_checked_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $count = (int)$pdo->query("SELECT COUNT(*) FROM sublink_domains")->fetchColumn();
            if ($count === 0) {
                // برای دامنه جدید، 2 دامنه پیش‌فرض بساز
                $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, 1, 'online')")
                    ->execute([$currentDomain]);
                $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, 0, 'online')")
                    ->execute(['direct.'.$currentDomain]);
                // اگر دامنه direct هم باشد، www را هم اضافه کن
                if (!str_starts_with($currentDomain, 'direct.')) {
                    try {
                        $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, 0, 'online')")
                            ->execute(['www.'.$currentDomain]);
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }
    
    public static function migrateSublinkDomains(string $oldDomain, string $newDomain): void {
        try {
            $pdo = Database::getConnection();
            // اگر دامنه جدید وجود ندارد، اضافه کن و اصلی کن
            $stmt = $pdo->prepare("SELECT id FROM sublink_domains WHERE domain = ? LIMIT 1");
            $stmt->execute([$newDomain]);
            if (!$stmt->fetchColumn()) {
                $pdo->exec("UPDATE sublink_domains SET is_primary = 0");
                $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, 1, 'online')")
                    ->execute([$newDomain]);
                // direct جدید
                try {
                    $pdo->prepare("INSERT INTO sublink_domains (domain, is_active, is_primary, health_status) VALUES (?, 1, 0, 'online')")
                        ->execute(['direct.'.$newDomain]);
                } catch (Throwable $e) {}
            } else {
                // اگر وجود دارد، اصلی کن
                $pdo->exec("UPDATE sublink_domains SET is_primary = 0");
                $pdo->prepare("UPDATE sublink_domains SET is_primary = 1, is_active = 1 WHERE domain = ?")->execute([$newDomain]);
            }
        } catch (Throwable $e) {}
    }
    
    public static function migrateServerSubDomains(string $oldDomain, string $newDomain): void {
        try {
            $pdo = Database::getConnection();
            $servers = $pdo->query("SELECT id, sub_domain FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($servers as $s) {
                $sub = $s['sub_domain'] ?? '';
                if (empty($sub)) continue;
                if (strpos($sub, $oldDomain) !== false || self::isOldDomain($sub)) {
                    $newSub = str_replace($oldDomain, $newDomain, $sub);
                    // اگر هنوز قدیمی است، از دامنه جدید استفاده کن
                    if (self::isOldDomain($newSub)) {
                        $newSub = 'https://direct.'.$newDomain;
                    }
                    $pdo->prepare("UPDATE server_nodes SET sub_domain = ? WHERE id = ?")->execute([$newSub, $s['id']]);
                }
            }
        } catch (Throwable $e) {}
    }
    
    /**
     * بررسی کامل استقلال از دامنه - آیا همه چیز درست کار می‌کند؟
     */
    public static function checkDomainIndependence(): array {
        $current = self::getCurrentDomain();
        $checks = [];
        
        // 1. panel_domain
        $stored = Setting::get('panel_domain','');
        $checks['panel_domain'] = [
            'label' => 'دامنه پنل',
            'current' => $current,
            'stored' => $stored,
            'ok' => $stored === $current,
            'fix' => $stored !== $current ? "بروزرسانی به $current" : "درست"
        ];
        
        // 2. sublink_custom_domain
        $custom = Setting::get('sublink_custom_domain','');
        $checks['sublink_custom_domain'] = [
            'label' => 'دامنه ساب‌لینک',
            'current' => $custom,
            'ok' => !empty($custom) && (strpos($custom, $current) !== false || strpos($custom, 'direct.'.$current) !== false),
            'fix' => empty($custom) ? "تنظیم به https://$current" : (strpos($custom, $current) === false ? "بروزرسانی به https://$current" : "درست")
        ];
        
        // 3. sublink_domains table
        try {
            $pdo = Database::getConnection();
            $domains = $pdo->query("SELECT * FROM sublink_domains WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
            $hasCurrent = false;
            foreach ($domains as $d) {
                if (strpos($d['domain'], $current) !== false) $hasCurrent = true;
            }
            $checks['sublink_domains'] = [
                'label' => 'دامنه‌های چرخشی',
                'count' => count($domains),
                'has_current' => $hasCurrent,
                'ok' => $hasCurrent && count($domains) >= 2,
                'fix' => !$hasCurrent ? "افزودن $current" : (count($domains) < 2 ? "افزودن direct.$current" : "درست")
            ];
        } catch (Throwable $e) {
            $checks['sublink_domains'] = ['label'=>'دامنه‌های چرخشی','ok'=>false,'fix'=>'جدول وجود ندارد'];
        }
        
        // 4. server_nodes sub_domain
        try {
            $pdo = Database::getConnection();
            $servers = $pdo->query("SELECT id, name, sub_domain FROM server_nodes")->fetchAll(PDO::FETCH_ASSOC);
            $oldCount = 0;
            foreach ($servers as $s) {
                if (!empty($s['sub_domain']) && self::isOldDomain($s['sub_domain'])) $oldCount++;
            }
            $checks['server_subdomains'] = [
                'label' => 'ساب‌دامنه سرورها',
                'total' => count($servers),
                'old' => $oldCount,
                'ok' => $oldCount === 0,
                'fix' => $oldCount > 0 ? "$oldCount سرور دامنه قدیمی دارد" : "درست"
            ];
        } catch (Throwable $e) {
            $checks['server_subdomains'] = ['label'=>'ساب‌دامنه سرورها','ok'=>false];
        }
        
        // 5. .htaccess
        $htPath = dirname(__DIR__) . '/.htaccess';
        $hasBypass = false;
        $hasDirectRule = false;
        if (file_exists($htPath)) {
            $ht = file_get_contents($htPath);
            $hasBypass = strpos($ht, 'cf-cache-status') !== false;
            $hasDirectRule = strpos($ht, 'direct.') !== false || strpos($ht, 'HTTP_HOST') !== false;
        }
        $checks['htaccess'] = [
            'label' => '.htaccess',
            'bypass' => $hasBypass,
            'direct_rule' => $hasDirectRule,
            'ok' => $hasBypass && $hasDirectRule,
            'fix' => !$hasBypass ? "افزودن هدر BYPASS" : (!$hasDirectRule ? "افزودن قانون direct" : "درست")
        ];
        
        return $checks;
    }
    
    /**
     * مهاجرت کامل دستی - برای صفحه تنظیمات
     */
    public static function fullMigration(string $newDomain): array {
        $newDomain = trim($newDomain);
        $newDomain = preg_replace('#^https?://#', '', $newDomain);
        $newDomain = rtrim($newDomain, '/');
        $newDomain = str_replace('www.', '', $newDomain);
        
        $oldDomain = Setting::get('panel_domain', self::getCurrentDomain());
        
        $actions = [];
        
        Setting::set('panel_domain', $newDomain);
        $actions[] = "panel_domain: $oldDomain → $newDomain";
        
        Setting::set('sublink_custom_domain', 'https://'.$newDomain);
        $actions[] = "sublink_custom_domain → https://$newDomain";
        
        $oldDomains = Setting::get('old_domains', self::OLD_DOMAINS_DEFAULT);
        $oldList = array_filter(array_map('trim', explode(',', $oldDomains)));
        if (!in_array($oldDomain, $oldList)) $oldList[] = $oldDomain;
        Setting::set('old_domains', implode(',', $oldList));
        $actions[] = "old_domains بروز شد";
        
        self::migrateSublinkDomains($oldDomain, $newDomain);
        $actions[] = "sublink_domains مهاجرت شد";
        
        self::migrateServerSubDomains($oldDomain, $newDomain);
        $actions[] = "server_nodes sub_domain بروز شد";
        
        // پاکسازی کش
        Setting::set('update_check_cache', '');
        Setting::set('update_check_time', '0');
        $actions[] = "کش پاک شد";
        
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (1, 'domain_migration_manual', ?, NOW())")
                ->execute(["مهاجرت دستی: $oldDomain → $newDomain"]);
        } catch (Throwable $e) {}
        
        return ['success'=>true, 'old'=>$oldDomain, 'new'=>$newDomain, 'actions'=>$actions];
    }
}

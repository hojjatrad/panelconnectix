<?php
/**
 * P2: License API - Sell API access to other panels
 * Free to implement, generates monthly income
 */

class LicenseAPI {
    /**
     * Generate license key for reseller
     */
    public static function generateLicense(int $resellerId, string $plan = 'basic', int $days = 30): array {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_licenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reseller_id INT NOT NULL,
                license_key VARCHAR(64) UNIQUE NOT NULL,
                plan VARCHAR(20) DEFAULT 'basic',
                is_active TINYINT(1) DEFAULT 1,
                requests_limit INT DEFAULT 10000,
                requests_used INT DEFAULT 0,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reseller (reseller_id),
                INDEX idx_key (license_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $licenseKey = 'CX-' . strtoupper(bin2hex(random_bytes(16)));
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            $stmt = $pdo->prepare("INSERT INTO api_licenses (reseller_id, license_key, plan, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$resellerId, $licenseKey, $plan, $expiresAt]);
            
            return ['success' => true, 'license_key' => $licenseKey, 'expires_at' => $expiresAt];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Validate license key
     */
    public static function validateLicense(string $licenseKey): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM api_licenses WHERE license_key = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
            $stmt->execute([$licenseKey]);
            $license = $stmt->fetch();
            
            if (!$license) {
                return ['valid' => false, 'message' => 'لایسنس نامعتبر یا منقضی'];
            }
            
            if ($license['requests_used'] >= $license['requests_limit']) {
                return ['valid' => false, 'message' => 'سقف درخواست‌ها تمام شده'];
            }
            
            // Increment usage
            $pdo->prepare("UPDATE api_licenses SET requests_used = requests_used + 1 WHERE id = ?")->execute([$license['id']]);
            
            return ['valid' => true, 'license' => $license];
        } catch (Throwable $e) {
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get all licenses for reseller
     */
    public static function getResellerLicenses(int $resellerId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM api_licenses WHERE reseller_id = ? ORDER BY id DESC");
            $stmt->execute([$resellerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

// M1: Retargeting Pixel
class MarketingPixel {
    public static function getPixelCode(): string {
        $fbPixel = Setting::get('fb_pixel_id', '');
        $googleTag = Setting::get('google_tag_id', '');
        
        $code = '';
        if ($fbPixel) {
            $code .= "
<!-- Facebook Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '$fbPixel'); fbq('track', 'PageView');
</script>
";
        }
        
        if ($googleTag) {
            $code .= "
<!-- Google Tag -->
<script async src='https://www.googletagmanager.com/gtag/js?id=$googleTag'></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','$googleTag');</script>
";
        }
        
        return $code;
    }
    
    public static function trackEvent(string $event, array $data = []): string {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return "<script>if(typeof fbq !== 'undefined') fbq('track', '$event', $json); if(typeof gtag !== 'undefined') gtag('event', '$event', $json);</script>";
    }
}

// M4: Influencer Panel
class InfluencerPanel {
    public static function createInfluencer(int $userId, string $code, int $commission = 25): array {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS influencers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                influencer_code VARCHAR(32) UNIQUE NOT NULL,
                commission_percent INT DEFAULT 25,
                total_clicks INT DEFAULT 0,
                total_sales INT DEFAULT 0,
                total_commission INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_code (influencer_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $stmt = $pdo->prepare("INSERT INTO influencers (user_id, influencer_code, commission_percent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE commission_percent = ?");
            $stmt->execute([$userId, $code, $commission, $commission]);
            
            return ['success' => true, 'code' => $code];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public static function trackClick(string $code): void {
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("UPDATE influencers SET total_clicks = total_clicks + 1 WHERE influencer_code = ?")->execute([$code]);
        } catch (Throwable $e) {}
    }
    
    public static function getStats(string $code): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM influencers WHERE influencer_code = ? LIMIT 1");
            $stmt->execute([$code]);
            return $stmt->fetch() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

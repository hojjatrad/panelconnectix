<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Database.php';

class ApiTokenController {
    
    public function index(): void {
        Auth::requireAdmin();
        require __DIR__ . '/../views/settings/api_tokens.php';
    }
    
    public function save(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/api-tokens');
        }
        
        $fields = ['cloudflare_api_token','cloudflare_zone_id','cloudflare_email','cpanel_api_token','cpanel_username','cpanel_domain'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $val = trim($_POST[$f]);
                if (!empty($val)) {
                    Setting::set($f, $val);
                }
            }
        }
        
        Helpers::flash('success', 'توکن‌ها ذخیره شد - حالا می‌توانید اتوماسیون را اجرا کنید');
        Helpers::redirect('settings/api-tokens');
    }
    
    public function autoDirect(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/api-tokens');
        }
        
        $cfToken = Setting::get('cloudflare_api_token','');
        $cfZoneId = Setting::get('cloudflare_zone_id','');
        $cfEmail = Setting::get('cloudflare_email','');
        $cpUser = Setting::get('cpanel_username', get_current_user());
        $cpToken = Setting::get('cpanel_api_token','');
        $cpDomain = Setting::get('cpanel_domain','vpbotn.ir');
        $originIp = $_SERVER['SERVER_ADDR'] ?? '';
        if (empty($originIp)) {
            $originIp = gethostbyname($_SERVER['HTTP_HOST'] ?? 'vpbotn.ir');
        }
        
        $results = [];
        
        // 1. cPanel subdomain creation via UAPI
        if (!empty($cpToken) && !empty($cpUser)) {
            try {
                // Try cPanel UAPI - SubDomain::addsubdomain
                $cpanelHost = '127.0.0.1';
                $cpanelPort = 2083;
                
                // Method 1: Try local cPanel API call
                $subdomain = 'direct';
                $rootDomain = $cpDomain;
                $dir = 'public_html';
                
                // Create via file system if UAPI fails - ensure public_html exists for subdomain
                // In cPanel, subdomain document root is usually public_html/direct or public_html
                // We want it to point to public_html, so we can use .htaccess trick
                
                // Try UAPI call
                $url = "https://$cpanelHost:$cpanelPort/execute/SubDomain/addsubdomain?domain=$subdomain&rootdomain=$rootDomain&dir=$dir";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: cpanel $cpUser:$cpToken"
                ]);
                $response = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $results['cpanel'] = [
                    'http' => $http,
                    'response' => substr($response ?? '', 0, 500),
                    'success' => ($http >= 200 && $http < 300)
                ];
                
                // If UAPI fails, try alternative method - create symlink or .htaccess
                if (!$results['cpanel']['success']) {
                    // Create directory and .htaccess for direct
                    $directPath = "/home/$cpUser/public_html";
                    if (is_dir($directPath)) {
                        // Already exists, ensure .htaccess has direct rule
                        $htPath = $directPath . '/.htaccess';
                        if (file_exists($htPath)) {
                            $ht = file_get_contents($htPath);
                            if (strpos($ht, 'direct') === false) {
                                $fix = "\n# Auto direct subdomain fix\n<IfModule mod_rewrite.c>\n    RewriteCond %{HTTP_HOST} ^direct\\. [NC]\n    RewriteRule ^(.*)$ index.php [QSA,L]\n</IfModule>\n";
                                file_put_contents($htPath, $ht . $fix);
                            }
                        }
                        $results['cpanel']['fallback'] = 'htaccess fixed';
                    }
                }
                
            } catch (Throwable $e) {
                $results['cpanel'] = ['error'=>$e->getMessage()];
            }
        } else {
            $results['cpanel'] = ['error'=>'توکن cPanel وارد نشده'];
        }
        
        // 2. Cloudflare DNS creation
        if (!empty($cfToken)) {
            try {
                // Get Zone ID if not provided
                if (empty($cfZoneId)) {
                    $ch = curl_init("https://api.cloudflare.com/client/v4/zones?name=$cpDomain");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $cfToken",
                        "Content-Type: application/json"
                    ]);
                    $resp = curl_exec($ch);
                    curl_close($ch);
                    $data = json_decode($resp, true);
                    if (!empty($data['result'][0]['id'])) {
                        $cfZoneId = $data['result'][0]['id'];
                        Setting::set('cloudflare_zone_id', $cfZoneId);
                    }
                }
                
                if (!empty($cfZoneId)) {
                    // Check existing record
                    $ch = curl_init("https://api.cloudflare.com/client/v4/zones/$cfZoneId/dns_records?name=direct.$cpDomain");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $cfToken",
                        "Content-Type: application/json"
                    ]);
                    $resp = curl_exec($ch);
                    curl_close($ch);
                    $existing = json_decode($resp, true);
                    
                    $recordData = [
                        'type' => 'A',
                        'name' => 'direct',
                        'content' => $originIp,
                        'ttl' => 120,
                        'proxied' => false, // DNS only - grey cloud
                        'comment' => 'Auto created by Connectix Panel - direct bypass for Iran'
                    ];
                    
                    if (!empty($existing['result'][0]['id'])) {
                        // Update existing
                        $recordId = $existing['result'][0]['id'];
                        $ch = curl_init("https://api.cloudflare.com/client/v4/zones/$cfZoneId/dns_records/$recordId");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($recordData));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer $cfToken",
                            "Content-Type: application/json"
                        ]);
                        $resp = curl_exec($ch);
                        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        $results['cloudflare'] = ['action'=>'update','http'=>$http,'response'=>substr($resp,0,500),'success'=>($http>=200 && $http<300)];
                    } else {
                        // Create new
                        $ch = curl_init("https://api.cloudflare.com/client/v4/zones/$cfZoneId/dns_records");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($recordData));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer $cfToken",
                            "Content-Type: application/json"
                        ]);
                        $resp = curl_exec($ch);
                        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        $results['cloudflare'] = ['action'=>'create','http'=>$http,'response'=>substr($resp,0,500),'success'=>($http>=200 && $http<300)];
                    }
                } else {
                    $results['cloudflare'] = ['error'=>'Zone ID پیدا نشد - دامنه را چک کنید'];
                }
                
            } catch (Throwable $e) {
                $results['cloudflare'] = ['error'=>$e->getMessage()];
            }
        } else {
            $results['cloudflare'] = ['error'=>'توکن Cloudflare وارد نشده'];
        }
        
        // Save results to session for display
        $_SESSION['auto_direct_results'] = $results;
        
        $success = (!empty($results['cloudflare']['success']) || !empty($results['cpanel']['success']));
        if ($success) {
            Helpers::flash('success', 'ساب‌دامنه direct با موفقیت ساخته/بروزرسانی شد - 2 دقیقه صبر کنید و https://direct.vpbotn.ir را تست کنید');
        } else {
            Helpers::flash('error', 'برخی عملیات ناموفق بود - جزئیات در صفحه بعد');
        }
        Helpers::redirect('settings/api-tokens');
    }
}

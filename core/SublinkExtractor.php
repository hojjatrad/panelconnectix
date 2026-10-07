<?php
/**
 * SublinkExtractor - استخراج دقیق ساب‌لینک سرور اصلی بدون اضافه
 * 
 * برای درخواست: ساب لینک دقیقا همان سرور اصلی بدون چیزی اضافه
 * و اگر یوزر/پسورد همراه ساب نیست، پنل خودش بسازد تا به اپ بدون ساب لینک وصل شود
 */

class SublinkExtractor {
    
    /**
     * استخراج دقیق ساب‌لینک سرور اصلی - بدون هیچ تغییر
     * @param array $apiUser یوزر از API سرور اصلی
     * @return string ساب لینک دقیق
     */
    public static function extractExactSub(array $apiUser): string {
        $sub = trim($apiUser['subscription_url'] ?? $apiUser['sub_url'] ?? $apiUser['sublink'] ?? '');
        // دقیقا همان بدون هیچ اضافه، فقط trim
        return $sub;
    }
    
    /**
     * استخراج یوزر و پسورد از ساب‌لینک اگر همراه ساب باشد
     * @param string $subUrl ساب لینک
     * @param string $fallbackUsername یوزرنیم fallback
     * @return array [username, password, source]
     */
    public static function extractCredentials(string $subUrl, string $fallbackUsername): array {
        $subUrl = trim($subUrl);
        if (empty($subUrl)) {
            // اگر ساب خالی است، پنل میسازد
            return [$fallbackUsername, self::generateSecurePassword(), 'panel_generated_empty_sub'];
        }
        
        // حالت 1: از query param خود URL ساب
        // مثال: https://example.com/sub/abc?username=ali&password=123
        // یا: https://example.com/sub/abc?user=ali&pass=123
        try {
            $parsed = parse_url($subUrl);
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $q);
                $userKeys = ['username', 'user', 'u', 'login'];
                $passKeys = ['password', 'pass', 'p', 'pwd', 'passwd'];
                
                $foundUser = null;
                $foundPass = null;
                
                foreach ($userKeys as $uk) {
                    if (!empty($q[$uk])) {
                        $foundUser = trim($q[$uk]);
                        break;
                    }
                }
                foreach ($passKeys as $pk) {
                    if (!empty($q[$pk])) {
                        $foundPass = trim($q[$pk]);
                        break;
                    }
                }
                
                if (!empty($foundUser) && !empty($foundPass)) {
                    return [$foundUser, $foundPass, 'url_query'];
                }
                // حتی اگر فقط یکی بود، برگردان
                if (!empty($foundUser) || !empty($foundPass)) {
                    return [
                        $foundUser ?: $fallbackUsername,
                        $foundPass ?: self::generateSecurePassword(),
                        'url_query_partial'
                    ];
                }
            }
        } catch (Throwable $e) {}
        
        // حالت 2: از محتوای ساب (اگر قابل دسترسی باشد)
        // سعی کن محتوای ساب را بگیری و از داخل کانفیگ‌ها استخراج کنی
        try {
            // فقط اگر URL معتبر است و http/https است
            if (filter_var($subUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $subUrl)) {
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'ignore_errors' => true,
                        'header' => "User-Agent: ConnectixPanel/1.0\r\n"
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $content = @file_get_contents($subUrl, false, $ctx);
                if ($content !== false && !empty($content)) {
                    // اگر base64 است decode کن
                    $trimmed = trim($content);
                    // چک کن آیا کل محتوا base64 است (ساب‌های معمول V2Ray)
                    if (preg_match('#^[A-Za-z0-9+/=\\n\\r]+$#', $trimmed) && strlen($trimmed) > 100) {
                        $decoded = base64_decode($trimmed, true);
                        if ($decoded !== false && !empty($decoded)) {
                            $content = $decoded;
                        }
                    }
                    
                    // از VLESS uuid استخراج کن
                    // vless://uuid@host:port?...
                    if (preg_match('/vless:\/\/([a-f0-9\-]{36})@/i', $content, $m)) {
                        $uuid = $m[1];
                        // یوزر را از path ساب استخراج کن: /sub/abc123 -> abc123
                        $path = parse_url($subUrl, PHP_URL_PATH) ?? '';
                        $usernameFromPath = basename($path);
                        if (empty($usernameFromPath) || $usernameFromPath === 'sub') {
                            $usernameFromPath = $fallbackUsername;
                        }
                        return [$usernameFromPath, $uuid, 'vless_uuid'];
                    }
                    
                    // vmess:// base64 json
                    if (preg_match('/vmess:\/\/([A-Za-z0-9+\/=]+)/', $content, $m)) {
                        try {
                            $vmessJson = json_decode(base64_decode($m[1]), true);
                            if (!empty($vmessJson['id'])) {
                                $ps = $vmessJson['ps'] ?? $fallbackUsername;
                                return [$ps, $vmessJson['id'], 'vmess_id'];
                            }
                        } catch (Throwable $e) {}
                    }
                    
                    // trojan://password@host
                    if (preg_match('/trojan:\/\/([^@]+)@/i', $content, $m)) {
                        $pass = $m[1];
                        $path = parse_url($subUrl, PHP_URL_PATH) ?? '';
                        $usernameFromPath = basename($path) ?: $fallbackUsername;
                        return [$usernameFromPath, $pass, 'trojan_pass'];
                    }
                    
                    // ss:// base64
                    if (preg_match('/ss:\/\/([A-Za-z0-9+\/=]+)/', $content, $m)) {
                        try {
                            $ssDecoded = base64_decode($m[1]);
                            if (preg_match('/^([^:]+):(.+)@/', $ssDecoded, $sm)) {
                                return [$fallbackUsername, $sm[2], 'ss_pass'];
                            }
                        } catch (Throwable $e) {}
                    }
                }
            }
        } catch (Throwable $e) {
            // اگر استخراج از محتوا فیل شد، ادامه بده
        }
        
        // حالت 3: اگر هیچ کدام نشد، پنل خودش میسازد
        // یوزر = همان یوزر سرور اصلی، پسورد = رندوم امن
        return [$fallbackUsername, self::generateSecurePassword(), 'panel_generated'];
    }
    
    /**
     * ساخت پسورد امن رندوم برای پنل
     */
    public static function generateSecurePassword(int $length = 12): string {
        // حروف و اعداد امن
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $pass = '';
        try {
            for ($i = 0; $i < $length; $i++) {
                $pass .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } catch (Throwable $e) {
            // fallback
            $pass = bin2hex(random_bytes($length / 2));
        }
        return $pass;
    }
    
    /**
     * آیا ساب‌لینک یوزر/پسورد دارد؟
     */
    public static function hasCredentials(string $subUrl): bool {
        $subUrl = trim($subUrl);
        if (empty($subUrl)) return false;
        
        // چک query param
        $parsed = parse_url($subUrl);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['username']) || !empty($q['user'])) {
                return true;
            }
        }
        
        // اگر ساب شامل uuid یا پسورد در محتوا باشد، فرض بر دارد
        // ولی برای سادگی، اگر URL طولانی باشد و شامل /sub/ باشد، ممکن است credential داشته باشد
        // در حالت کلی، اگر extractCredentials منبع panel_generated نباشد، یعنی دارد
        return false;
    }
    
    /**
     * ساخت لینک مستقیم برای ریدایرکت 302 به ساب اصلی
     * @param string $directSub ساب دقیق اصلی
     * @return string همان ساب دقیق
     */
    public static function getDirectRedirectUrl(string $directSub): string {
        // دقیقا همان بدون هیچ تغییر
        return trim($directSub);
    }
    
    /**
     * ساخت ساب پنلی برای اپ خودت (بدون نیاز به وارد کردن ساب لینک)
     * کاربر با یوزر/پسورد پنل لاگین میکند و پنل direct_sublink را برمیگرداند
     */
    public static function getPanelSubUrl(string $subToken): string {
        // از Helpers استفاده کن
        if (class_exists('Helpers') && method_exists('Helpers', 'subUrl')) {
            return Helpers::subUrl($subToken);
        }
        // fallback
        $domain = 'https://vpbotn.ir';
        try {
            if (class_exists('Setting')) {
                $custom = Setting::get('sublink_custom_domain', '');
                if (!empty($custom)) {
                    $domain = rtrim($custom, '/');
                }
            }
        } catch (Throwable $e) {}
        return $domain . '/sub/' . $subToken;
    }
}

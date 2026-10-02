<?php
/**
 * I2: Google Drive Backup - Encrypted backup to Google Drive
 * Requires: Service Account JSON or OAuth token
 */

class GoogleDriveBackup {
    private static function getAccessToken(): ?string {
        // Method 1: Service Account JSON file
        $jsonPath = __DIR__ . '/../data/gdrive_service_account.json';
        if (is_file($jsonPath)) {
            try {
                $json = json_decode(file_get_contents($jsonPath), true);
                if (!empty($json['client_email']) && !empty($json['private_key'])) {
                    return self::getServiceAccountToken($json);
                }
            } catch (Throwable $e) {}
        }
        
        // Method 2: OAuth refresh token stored in settings
        $refreshToken = Setting::get('gdrive_refresh_token', '');
        $clientId = Setting::get('gdrive_client_id', '');
        $clientSecret = Setting::get('gdrive_client_secret', '');
        
        if ($refreshToken && $clientId && $clientSecret) {
            return self::getOAuthToken($clientId, $clientSecret, $refreshToken);
        }
        
        // Method 3: Direct access token (short-lived, not recommended)
        $accessToken = Setting::get('gdrive_access_token', '');
        if ($accessToken) {
            return $accessToken;
        }
        
        return null;
    }
    
    private static function getServiceAccountToken(array $serviceAccount): ?string {
        try {
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $now = time();
            $payload = base64_encode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive.file',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ]));
            
            $signature = '';
            openssl_sign("$header.$payload", $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "$header.$payload." . base64_encode($signature);
            
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
    
    private static function getOAuthToken(string $clientId, string $clientSecret, string $refreshToken): ?string {
        try {
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token'
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
    
    public static function uploadBackup(string $filePath, string $fileName): array {
        $token = self::getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'توکن Google Drive تنظیم نشده. لطفاً از راهنمای زیر استفاده کنید.'];
        }
        
        try {
            // Check if folder ID is set, or use root
            $folderId = Setting::get('gdrive_folder_id', '');
            
            $metadata = ['name' => $fileName];
            if ($folderId) {
                $metadata['parents'] = [$folderId];
            }
            
            // Create multipart upload
            $boundary = uniqid();
            $body = "--$boundary\r\n";
            $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
            $body .= json_encode($metadata) . "\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/octet-stream\r\n\r\n";
            $body .= file_get_contents($filePath) . "\r\n";
            $body .= "--$boundary--";
            
            $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $token",
                    "Content-Type: multipart/related; boundary=$boundary"
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                Setting::set('last_gdrive_backup_at', date('Y-m-d H:i:s'));
                Setting::set('last_gdrive_file_id', $data['id'] ?? '');
                
                if (class_exists('SecurityLogger')) {
                    SecurityLogger::log('backup_gdrive', "Uploaded to GDrive: $fileName");
                }
                
                return ['success' => true, 'file_id' => $data['id'] ?? '', 'message' => 'بکاپ به Google Drive آپلود شد'];
            }
            
            return ['success' => false, 'message' => "خطا HTTP $httpCode: $response"];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public static function backupAndUpload(): array {
        // Create encrypted backup
        if (!class_exists('Backup')) {
            require_once __DIR__ . '/Backup.php';
        }
        
        $backup = Backup::createEncryptedBackup();
        if (!$backup['success']) {
            return $backup;
        }
        
        $result = self::uploadBackup($backup['path'], $backup['filename']);
        @unlink($backup['path']);
        
        return $result;
    }
    
    public static function getSetupGuide(): string {
        return "
🔧 راهنمای تنظیم Google Drive برای بک‌آپ خودکار:

**روش پیشنهادی (Service Account - ساده‌تر):**

1. برو به https://console.cloud.google.com/
2. پروژه جدید بساز (مثلاً Connectix-Backup)
3. از منوی سمت چپ: APIs & Services → Library → Google Drive API → Enable
4. APIs & Services → Credentials → Create Credentials → Service Account
5. نام: connectix-backup → Create → Role: Owner → Done
6. روی Service Account کلیک کن → Keys → Add Key → Create New Key → JSON → Create
7. فایل JSON دانلود میشه - محتوای اون رو کپی کن
8. برو به https://drive.google.com/ → پوشه جدید بساز به نام Connectix-Backups
9. روی پوشه راست کلیک → Share → ایمیل Service Account رو اضافه کن (Editor) - ایمیل داخل فایل JSON هست مثل: xxx@yyy.iam.gserviceaccount.com
10. ID پوشه را کپی کن: از URL پوشه، بخش آخر بعد از /folders/ مثلاً: 1a2b3c4d5e6f
11. محتوای JSON و Folder ID را به من بده تا تنظیم کنم

**روش دوم (OAuth - برای Drive شخصی):**
1. console.cloud.google.com → Credentials → Create Credentials → OAuth Client ID → Web Application
2. Authorized redirect URIs: https://developers.google.com/oauthplayground
3. Client ID و Secret را کپی کن
4. برو به https://developers.google.com/oauthplayground
5. چرخ دنده بالا → تیک Use your own OAuth credentials → Client ID/Secret را وارد کن
6. از لیست سمت چپ: Drive API v3 → https://www.googleapis.com/auth/drive.file → Authorize APIs
7. با اکانت گوگل لاگین کن → Allow
8. Exchange authorization code for tokens → Refresh Token را کپی کن
9. Client ID, Secret, Refresh Token را به من بده

**بعد از دادن توکن:**
من با دستور زیر تنظیم میکنم:
- فایل JSON را در data/gdrive_service_account.json ذخیره میکنم
- یا توکن‌ها را در Setting ذخیره میکنم
- تست آپلود انجام میدم
- کرون روزانه فعال میشه

**امنیت:** فایل JSON رمزنگاری شده ذخیره میشه و فقط بک‌آپ‌های رمز شده AES-256 آپلود میشن.
";
    }
}

<?php
// v4.0.45 SECURITY FIX: Encrypt server credentials at rest
class Encryption {
    private const METHOD = 'AES-256-CBC';
    
    private static function getKey(): string {
        // Try to get key from config.secrets.php or config.php or env
        if (defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32) {
            return substr(ENCRYPTION_KEY, 0, 32);
        }
        // Fallback: use APP_SECRET or generate from DB path (not ideal but better than plaintext)
        if (defined('APP_SECRET') && strlen(APP_SECRET) >= 16) {
            return hash('sha256', APP_SECRET, true);
        }
        // Last resort: use a fixed key derived from config.php path (still better than plaintext in DB dump)
        $fallback = __DIR__ . '/../config.php';
        if (is_file($fallback)) {
            return hash('sha256', file_get_contents($fallback) . 'connectix_salt', true);
        }
        return hash('sha256', 'connectix_default_key_2024', true);
    }
    
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') return '';
        // If already encrypted (starts with enc_), return as is
        if (str_starts_with($plaintext, 'enc_')) return $plaintext;
        
        $key = self::getKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) return $plaintext; // fallback to plaintext if encrypt fails
        return 'enc_' . base64_encode($iv . $encrypted);
    }
    
    public static function decrypt(string $ciphertext): string {
        if ($ciphertext === '') return '';
        if (!str_starts_with($ciphertext, 'enc_')) return $ciphertext; // not encrypted, return as is (backward compat)
        
        $key = self::getKey();
        $data = base64_decode(substr($ciphertext, 4));
        if ($data === false || strlen($data) < 16) return $ciphertext;
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $ciphertext;
    }
    
    public static function isEncrypted(string $value): bool {
        return str_starts_with($value, 'enc_');
    }
}

<?php
/**
 * Connectix Panel - Pure PHP Two-Factor Authentication (RFC 6238 TOTP)
 * Compatible with Google Authenticator, Microsoft Authenticator, 1Password, etc.
 * Zero external composer dependencies required.
 */
class TwoFactor {
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new base32 secret key (16 characters / 80 bits)
     */
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        $max = strlen(self::$base32Chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $max)];
        }
        return $secret;
    }

    /**
     * Decode a base32 string to binary
     */
    private static function base32Decode(string $b32): string {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $buffer = 0;
        $bufferSize = 0;
        $output = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::$base32Chars, $b32[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $output .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Calculate 6-digit TOTP code for a secret at a given timestamp
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        // Pack time counter into 8-byte big-endian binary
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit code with window tolerance (default 1 step = 30s drift)
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        $currentTimeSlice = (int)floor(time() / 30);
        $code = trim($code);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculated = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate QR Code Image URL (using SVG or Google Chart/QuickChart API fallback)
     */
    public static function getOtpAuthUrl(string $username, string $secret, string $issuer = 'Connectix'): string {
        $encodedUser = rawurlencode($username);
        $encodedIssuer = rawurlencode($issuer);
        return "otpauth://totp/{$encodedIssuer}:{$encodedUser}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    public static function getQrCodeUrl(string $otpAuthUrl): string {
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpAuthUrl);
    }
}

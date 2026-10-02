<?php
/**
 * B10: Multilingual Support - FA/EN/AR
 */

class Multilingual {
    private static array $translations = [
        'fa' => [
            'welcome' => 'به Connectix خوش آمدید',
            'buy' => 'خرید اشتراک',
            'my_subs' => 'اشتراک‌های من',
            'wallet' => 'کیف پول',
            'support' => 'پشتیبانی',
            'settings' => 'تنظیمات',
            'renew' => 'تمدید',
            'help' => 'آموزش',
            'referral' => 'دعوت دوستان',
            'wheel' => 'گردونه شانس',
            'search' => 'جستجو',
            'status_online' => 'آنلاین',
            'status_offline' => 'آفلاین',
        ],
        'en' => [
            'welcome' => 'Welcome to Connectix',
            'buy' => 'Buy Subscription',
            'my_subs' => 'My Subscriptions',
            'wallet' => 'Wallet',
            'support' => 'Support',
            'settings' => 'Settings',
            'renew' => 'Renew',
            'help' => 'Help',
            'referral' => 'Invite Friends',
            'wheel' => 'Wheel of Fortune',
            'search' => 'Search',
            'status_online' => 'Online',
            'status_offline' => 'Offline',
        ],
        'ar' => [
            'welcome' => 'مرحبا بكم في Connectix',
            'buy' => 'شراء اشتراك',
            'my_subs' => 'اشتراكاتي',
            'wallet' => 'المحفظة',
            'support' => 'الدعم',
            'settings' => 'الإعدادات',
            'renew' => 'تجديد',
            'help' => 'مساعدة',
            'referral' => 'دعوة الأصدقاء',
            'wheel' => 'عجلة الحظ',
            'search' => 'بحث',
            'status_online' => 'متصل',
            'status_offline' => 'غير متصل',
        ]
    ];
    
    public static function get(string $key, string $lang = 'fa'): string {
        return self::$translations[$lang][$key] ?? self::$translations['fa'][$key] ?? $key;
    }
    
    public static function detectLang(string $text): string {
        // Simple detection based on characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            // Contains Arabic/Persian
            if (strpos($text, 'ی') !== false || strpos($text, 'ک') !== false || strpos($text, 'پ') !== false) {
                return 'fa';
            }
            return 'ar';
        }
        return 'en';
    }
    
    public static function getAll(string $lang = 'fa'): array {
        return self::$translations[$lang] ?? self::$translations['fa'];
    }
}

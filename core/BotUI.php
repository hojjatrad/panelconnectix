<?php
/**
 * B1: New Glass Menu - Improved Bot UI
 * B9: Support Button
 * B6: Subscription Management
 */

class BotUI {
    public static function getMainMenu(string $brand = 'Connectix'): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🛒 خرید اشتراک', 'callback_data' => 'buy'],
                    ['text' => '📊 اشتراک‌های من', 'callback_data' => 'my_subs']
                ],
                [
                    ['text' => '💰 کیف پول', 'callback_data' => 'wallet'],
                    ['text' => '🎁 دعوت دوستان', 'callback_data' => 'referral']
                ],
                [
                    ['text' => '🔄 تمدید', 'callback_data' => 'renew'],
                    ['text' => '📱 آموزش اتصال', 'callback_data' => 'help']
                ],
                [
                    ['text' => '🎡 گردونه شانس', 'callback_data' => 'wheel'],
                    ['text' => '💬 پشتیبانی', 'callback_data' => 'support']
                ],
                [
                    ['text' => '⚙️ تنظیمات', 'callback_data' => 'settings']
                ]
            ]
        ];
    }
    
    public static function getBuyMenu(): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📱 یکماهه', 'callback_data' => 'buy_monthly'],
                    ['text' => '📅 سه‌ماهه', 'callback_data' => 'buy_3month']
                ],
                [
                    ['text' => '🗓️ شش‌ماهه', 'callback_data' => 'buy_6month'],
                    ['text' => '📆 یکساله', 'callback_data' => 'buy_yearly']
                ],
                [
                    ['text' => '🔍 جستجوی پلن', 'callback_data' => 'search_plan'],
                    ['text' => '💎 ویژه', 'callback_data' => 'buy_vip']
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'main_menu']
                ]
            ]
        ];
    }
    
    public static function getSupportMenu(string $supportUsername = ''): array {
        $buttons = [
            [['text' => '📩 ارسال پیام به پشتیبانی', 'callback_data' => 'contact_support']],
            [['text' => '❓ سوالات متداول', 'callback_data' => 'faq']],
            [['text' => '⬅️ بازگشت', 'callback_data' => 'main_menu']]
        ];
        
        if (!empty($supportUsername)) {
            $url = str_starts_with($supportUsername, 'http') ? $supportUsername : "https://t.me/" . ltrim($supportUsername, '@');
            $buttons[0][] = ['text' => '👤 پشتیبانی مستقیم', 'url' => $url];
        }
        
        return ['inline_keyboard' => $buttons];
    }
    
    public static function getSubscriptionMenu(int $clientId): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 تمدید', 'callback_data' => "renew_{$clientId}"],
                    ['text' => '📊 وضعیت', 'callback_data' => "status_{$clientId}"]
                ],
                [
                    ['text' => '🔗 لینک جدید', 'callback_data' => "newlink_{$clientId}"],
                    ['text' => '🌐 تغییر سرور', 'callback_data' => "changeserver_{$clientId}"]
                ],
                [
                    ['text' => '📱 آموزش اتصال', 'callback_data' => "help_{$clientId}"],
                    ['text' => '🗑️ حذف', 'callback_data' => "delete_{$clientId}"]
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'my_subs']
                ]
            ]
        ];
    }
    
    public static function getWelcomeMessage(string $name = 'کاربر', string $brand = 'Connectix'): string {
        return "👋 سلام <b>{$name}</b> عزیز!\n\n"
             . "🌟 به <b>{$brand}</b> خوش آمدید\n"
             . "🚀 سریع‌ترین و پایدارترین VPN ضد فیلتر\n\n"
             . "📦 <b>امکانات:</b>\n"
             . "• ⚡ سرورهای پرسرعت 10Gbps\n"
             . "• 📱 اپ اختصاصی اندروید و iOS\n"
             . "• 🔄 تحویل آنی + پشتیبانی 24/7\n"
             . "• 🎁 پورسانت 20% دعوت دوستان\n\n"
             . "👇 از منوی زیر انتخاب کنید:";
    }
    
    public static function getHelpMessage(): string {
        return "📱 <b>آموزش اتصال</b>\n\n"
             . "<b>اندروید:</b>\n"
             . "1. اپ V2rayNG را نصب کنید\n"
             . "2. لینک اشتراک را کپی کنید\n"
             . "3. در اپ، + → Import from Clipboard\n\n"
             . "<b>آیفون:</b>\n"
             . "1. اپ Shadowrocket یا OneClick را نصب کنید\n"
             . "2. لینک را کپی و در اپ اضافه کنید\n\n"
             . "<b>ویندوز:</b>\n"
             . "1. اپ V2rayN را دانلود کنید\n"
             . "2. لینک را Import کنید\n\n"
             . "💬 سوالی دارید؟ پشتیبانی در خدمت شماست!";
    }
}

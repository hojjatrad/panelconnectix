<?php
/**
 * v4.0.53 BotKeyboardService - All keyboards extracted
 * Maintains same logic as original but with caching and toggle enforcement
 */
class BotKeyboardService {
    public static function getMainMenuInlineKeyboard(?PDO $pdo = null, ?string $fromId = null): array {
        $pdo = $pdo ?: Database::getConnection();
        
        // v4.0.53 Use ApcuCache for settings (30s TTL)
        $getSetting = function($key, $default = '1') use ($pdo) {
            if (class_exists('ApcuCache')) {
                $cached = ApcuCache::get("setting_$key", null);
                if ($cached !== null) return $cached;
            }
            try {
                $val = Setting::get($key, $default);
                if (class_exists('ApcuCache')) ApcuCache::set("setting_$key", $val, 30);
                return $val;
            } catch (Throwable $e) {
                return $default;
            }
        };

        // v4.0.52 FIX: Enforce toggle settings - check if button enabled
        $btnEnabled = function($btn) use ($getSetting) {
            return $getSetting("btn_{$btn}_enabled", '1') == '1';
        };

        $keyboard = ['inline_keyboard' => []];

        // Row 1: Buy + My Accounts
        $row1 = [];
        if ($btnEnabled('buy')) $row1[] = ['text' => $getSetting('btn_buy_text', '🛒 خرید اشتراک'), 'callback_data' => 'buy_sub'];
        if ($btnEnabled('my_accounts')) $row1[] = ['text' => $getSetting('btn_my_accounts_text', '👤 اشتراک‌های من'), 'callback_data' => 'my_accounts'];
        if (!empty($row1)) $keyboard['inline_keyboard'][] = $row1;

        // Row 2: Free Trial + Wallet
        $row2 = [];
        if ($btnEnabled('trial')) $row2[] = ['text' => $getSetting('btn_trial_text', '🎁 تست رایگان'), 'callback_data' => 'free_trial'];
        if ($btnEnabled('wallet')) $row2[] = ['text' => $getSetting('btn_wallet_text', '💰 کیف پول'), 'callback_data' => 'wallet_menu'];
        if (!empty($row2)) $keyboard['inline_keyboard'][] = $row2;

        // Row 3: Download Apps + Support
        $row3 = [];
        if ($btnEnabled('apps')) $row3[] = ['text' => $getSetting('btn_apps_text', '📱 دانلود اپ'), 'callback_data' => 'download_apps'];
        if ($btnEnabled('support')) $row3[] = ['text' => $getSetting('btn_support_text', '💬 پشتیبانی'), 'callback_data' => 'support'];
        if (!empty($row3)) $keyboard['inline_keyboard'][] = $row3;

        // Row 4: Monitoring + Financial (v4.0.52 FIX - was missing toggle enforcement)
        $row4 = [];
        if ($btnEnabled('monitoring')) $row4[] = ['text' => $getSetting('btn_monitoring_text', '📊 وضعیت سرورها LIVE'), 'callback_data' => 'monitoring'];
        if ($btnEnabled('financial')) $row4[] = ['text' => $getSetting('btn_financial_text', '💹 گزارش مالی'), 'callback_data' => 'financial'];
        if (!empty($row4)) $keyboard['inline_keyboard'][] = $row4;

        // Row 5: Points + Usage
        $row5 = [];
        if ($btnEnabled('points')) $row5[] = ['text' => $getSetting('btn_points_text', '🏆 امتیاز و جایزه'), 'callback_data' => 'points'];
        if ($btnEnabled('usage')) $row5[] = ['text' => $getSetting('btn_usage_text', '📈 مصرف و تاریخچه'), 'callback_data' => 'usage'];
        if (!empty($row5)) $keyboard['inline_keyboard'][] = $row5;

        // Row 6: Referral + Wheel
        $row6 = [];
        if ($btnEnabled('referral')) $row6[] = ['text' => $getSetting('btn_referral_text', '👥 دعوت دوستان'), 'callback_data' => 'referral_info'];
        if ($btnEnabled('wheel')) $row6[] = ['text' => $getSetting('btn_wheel_text', '🎡 گردونه شانس'), 'callback_data' => 'lucky_wheel'];
        if (!empty($row6)) $keyboard['inline_keyboard'][] = $row6;

        // Row 7: Guide + Panel
        $row7 = [];
        if ($btnEnabled('guide')) $row7[] = ['text' => $getSetting('btn_guide_text', '📚 راهنما'), 'callback_data' => 'guide'];
        if ($btnEnabled('panel')) $row7[] = ['text' => $getSetting('btn_panel_text', '🔑 اطلاعات پنل'), 'callback_data' => 'panel_info'];
        if (!empty($row7)) $keyboard['inline_keyboard'][] = $row7;

        // Row 8: Reseller
        if ($btnEnabled('reseller')) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $getSetting('btn_reseller_text', '🤝 درخواست نمایندگی'), 'callback_data' => 'reseller_apply']
            ];
        }

        return $keyboard;
    }

    public static function getMainMenuReplyKeyboard(): array {
        $getSetting = function($key, $default = '1') {
            try {
                return Setting::get($key, $default);
            } catch (Throwable $e) {
                return $default;
            }
        };

        $btnEnabled = function($btn) use ($getSetting) {
            return $getSetting("btn_{$btn}_enabled", '1') == '1';
        };

        $keyboard = ['keyboard' => [], 'resize_keyboard' => true, 'is_persistent' => true];

        // Row 1
        $row1 = [];
        if ($btnEnabled('buy')) $row1[] = ['text' => $getSetting('btn_buy_text', '🛒 خرید اشتراک')];
        if ($btnEnabled('my_accounts')) $row1[] = ['text' => $getSetting('btn_my_accounts_text', '👤 اشتراک‌های من')];
        if (!empty($row1)) $keyboard['keyboard'][] = $row1;

        // Row 2
        $row2 = [];
        if ($btnEnabled('trial')) $row2[] = ['text' => $getSetting('btn_trial_text', '🎁 تست رایگان')];
        if ($btnEnabled('wallet')) $row2[] = ['text' => $getSetting('btn_wallet_text', '💰 کیف پول')];
        if (!empty($row2)) $keyboard['keyboard'][] = $row2;

        // Row 3
        $row3 = [];
        if ($btnEnabled('apps')) $row3[] = ['text' => $getSetting('btn_apps_text', '📱 دانلود اپ')];
        if ($btnEnabled('support')) $row3[] = ['text' => $getSetting('btn_support_text', '💬 پشتیبانی')];
        if (!empty($row3)) $keyboard['keyboard'][] = $row3;

        // Row 4: Monitoring + Financial (v4.0.52 FIX)
        $row4 = [];
        if ($btnEnabled('monitoring')) $row4[] = ['text' => $getSetting('btn_monitoring_text', '📊 وضعیت سرورها LIVE')];
        if ($btnEnabled('financial')) $row4[] = ['text' => $getSetting('btn_financial_text', '💹 گزارش مالی')];
        if (!empty($row4)) $keyboard['keyboard'][] = $row4;

        // Row 5: Points + Usage
        $row5 = [];
        if ($btnEnabled('points')) $row5[] = ['text' => $getSetting('btn_points_text', '🏆 امتیاز و جایزه')];
        if ($btnEnabled('usage')) $row5[] = ['text' => $getSetting('btn_usage_text', '📈 مصرف و تاریخچه')];
        if (!empty($row5)) $keyboard['keyboard'][] = $row5;

        return $keyboard;
    }
}

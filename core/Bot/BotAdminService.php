<?php
/**
 * v4.0.53 BotAdminService - manage, updateSettings, botUsers, broadcast, etc.
 */
class BotAdminService {
    public static function updateSettings(array $postData): void {
        // v4.0.52 FIX: Ensure all button toggles are saved including monitoring/financial/points/usage
        $buttonKeys = [
            'btn_buy_enabled', 'btn_my_accounts_enabled', 'btn_trial_enabled', 'btn_wallet_enabled',
            'btn_apps_enabled', 'btn_support_enabled', 'btn_monitoring_enabled', 'btn_financial_enabled',
            'btn_points_enabled', 'btn_usage_enabled', 'btn_referral_enabled', 'btn_wheel_enabled',
            'btn_guide_enabled', 'btn_panel_enabled', 'btn_reseller_enabled',
            'btn_buy_text', 'btn_my_accounts_text', 'btn_trial_text', 'btn_wallet_text',
            'btn_apps_text', 'btn_support_text', 'btn_monitoring_text', 'btn_financial_text',
            'btn_points_text', 'btn_usage_text', 'btn_referral_text', 'btn_wheel_text',
            'btn_guide_text', 'btn_panel_text', 'btn_reseller_text',
        ];

        foreach ($buttonKeys as $key) {
            if (isset($postData[$key])) {
                Setting::set($key, $postData[$key]);
                if (class_exists('ApcuCache')) ApcuCache::delete("setting_$key");
            } else if (str_ends_with($key, '_enabled')) {
                // Checkbox not sent = disabled
                Setting::set($key, '0');
                if (class_exists('ApcuCache')) ApcuCache::delete("setting_$key");
            }
        }

        // Other settings
        $otherKeys = ['bot_force_join_channel', 'bot_pay_card_enabled', 'bot_pay_usdt_enabled', 'bot_pay_ton_enabled', 'bot_pay_wallet_enabled'];
        foreach ($otherKeys as $key) {
            if (isset($postData[$key])) {
                Setting::set($key, $postData[$key]);
                if (class_exists('ApcuCache')) ApcuCache::delete("setting_$key");
            }
        }

        // Clear bot plans cache
        if (class_exists('ApcuCache')) {
            ApcuCache::delete('bot_plans_1_0');
            ApcuCache::delete('bot_plans_1_1');
        }
        if (class_exists('Cache')) {
            Cache::clear();
        }
    }
}

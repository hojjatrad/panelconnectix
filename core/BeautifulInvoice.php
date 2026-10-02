<?php
require_once __DIR__ . '/JalaliDate.php';
require_once __DIR__ . '/Helpers.php';

class BeautifulInvoice
{
    /**
     * Generate beautiful pre-invoice with Jalali dates and copyable fields
     */
    public static function renderPreInvoice(array $order, int $userWallet = 0): array
    {
        $priceFa = number_format($order['amount']) . ' تومان';
        $isRenew = ($order['order_type'] === 'renew');
        $titlePrefix = $isRenew ? '🔄 پیش‌فاکتور تمدید اشتراک' : '🛒 پیش‌فاکتور خرید اشتراک جدید';
        
        $trafficVal = (float)($order['traffic_gb'] ?? $order['custom_traffic_gb'] ?? 0);
        if ($trafficVal > 0 && $trafficVal < 1) {
            $trafficText = JalaliDate::toPersianNumber(round($trafficVal * 1024)) . ' مگابایت';
        } elseif ($trafficVal >= 1) {
            $trafficText = ($trafficVal == (int)$trafficVal ? JalaliDate::toPersianNumber((int)$trafficVal) : JalaliDate::toPersianNumber($trafficVal)) . ' گیگابایت';
        } else {
            $trafficText = '♾️ نامحدود';
        }

        $ipLimit = (int)($order['ip_limit'] ?? 0);
        $userLimitText = $ipLimit > 0 ? JalaliDate::toPersianNumber($ipLimit) . ' کاربره' : '♾️ نامحدود';

        $durationDays = (int)($order['duration_days'] ?? $order['custom_duration_days'] ?? 30);
        $durationText = JalaliDate::toPersianNumber($durationDays) . ' روزه';

        $orderDate = JalaliDate::invoiceDate($order['created_at'] ?? time());
        $expirePreview = date('Y-m-d H:i:s', time() + $durationDays * 86400);
        $expireJalali = JalaliDate::format($expirePreview, 'date');

        // Beautiful invoice
        $msg = "┏━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ {$titlePrefix} ┃\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━━━━┛\n\n";

        $msg .= "📦 <b>پلن انتخابی:</b>\n";
        $msg .= "   └─ {$order['display_title']}\n\n";

        $msg .= "📊 <b>مشخصات پلن:</b>\n";
        $msg .= "   ├─ 💾 حجم: <b>{$trafficText}</b>\n";
        $msg .= "   ├─ 👥 اتصال: <b>{$userLimitText}</b>\n";
        $msg .= "   └─ ⏳ مدت: <b>{$durationText}</b>\n\n";

        if (!empty($order['coupon_code'])) {
            $discount = number_format($order['discount_amount'] ?? 0);
            $msg .= "🎟 <b>تخفیف اعمال شده:</b>\n";
            $msg .= "   ├─ کد: <code>{$order['coupon_code']}</code>\n";
            $msg .= "   └─ مبلغ تخفیف: {$discount} تومان\n\n";
        }

        $msg .= "💰 <b>صورتحساب:</b>\n";
        $msg .= "   ├─ مبلغ نهایی: <b>{$priceFa}</b>\n";
        $msg .= "   ├─ کد رهگیری: <code>{$order['order_code']}</code>\n";
        $msg .= "   ├─ تاریخ صدور: {$orderDate}\n";
        $msg .= "   └─ انقضای اشتراک: {$expireJalali}\n\n";

        $msg .= "💡 <i>برای کپی هر کد، روی آن ضربه بزنید</i>\n";
        $msg .= "👇 روش پرداخت را انتخاب کنید:";

        return [
            'text' => $msg,
            'wallet' => $userWallet,
            'amount' => (int)$order['amount']
        ];
    }

    /**
     * Beautiful success invoice after payment - with copyable username/password/config
     */
    public static function renderSuccessInvoice(array $prov, array $order = []): string
    {
        $trafficGb = $prov['traffic_gb'] ?? 0;
        $expireAt = $prov['expire_at'] ?? '';
        $serverName = $prov['server_name'] ?? 'نامشخص';
        $username = $prov['username'] ?? '';
        $password = $prov['password'] ?? '';
        $primarySub = $prov['primary_sub'] ?? $prov['node_sublink'] ?? $prov['sub_url'] ?? '';
        $subUrl = $prov['sub_url'] ?? $primarySub;
        $vlessLink = $prov['vless_link'] ?? '';

        $ipLimit = (int)($prov['ip_limit'] ?? 0);
        $userLimitText = $ipLimit > 0 ? JalaliDate::toPersianNumber($ipLimit) . ' کاربره' : 'نامحدود';

        $expireJalali = JalaliDate::expireDate($expireAt);
        $nowJalali = JalaliDate::invoiceDate(time());
        $trafficText = $trafficGb ? JalaliDate::toPersianNumber($trafficGb) . ' گیگ' : 'نامحدود';

        $msg = "┏━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 🎉 اشتراک فعال شد! ┃\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━━━━┛\n\n";

        $msg .= "✅ <b>سفارش شما با موفقیت تایید و اشتراک فعال گردید!</b>\n\n";

        $msg .= "👤 <b>اطلاعات ورود (کپی با یک ضربه):</b>\n";
        $msg .= "   ├─ نام کاربری: <code>{$username}</code>\n";
        $msg .= "   └─ رمز عبور: <code>{$password}</code>\n\n";

        $msg .= "📊 <b>مشخصات اشتراک:</b>\n";
        $msg .= "   ├─ 💾 حجم: <b>{$trafficText}</b>\n";
        $msg .= "   ├─ 👥 سقف اتصال: <b>{$userLimitText}</b>\n";
        $msg .= "   ├─ 🌐 سرور: {$serverName}\n";
        $msg .= "   ├─ {$expireJalali}\n";
        $msg .= "   └─ {$nowJalali}\n\n";

        $msg .= "🔗 <b>لینک‌های اتصال (کپی با یک ضربه):</b>\n";
        $msg .= "   ├─ ساب‌لینک اصلی:\n<code>{$primarySub}</code>\n\n";

        if (!empty($vlessLink)) {
            $msg .= "   ├─ کانکشن مستقیم VLESS:\n<code>{$vlessLink}</code>\n\n";
        }

        if (!empty($subUrl) && $subUrl !== $primarySub) {
            $msg .= "   └─ صفحه وضعیت:\n<code>{$subUrl}</code>\n\n";
        } else {
            $msg .= "\n";
        }

        $msg .= "📱 <b>راهنمای اتصال:</b>\n";
        $msg .= "   • اندروید: v2rayNG - لینک را وارد کنید\n";
        $msg .= "   • آیفون: <a href=\"https://vpbotn.ir/contax/ios-guide\">راهنمای تصویری iOS</a>\n";
        $msg .= "   • یا QR کد بالا را اسکن کنید\n\n";

        $msg .= "💡 <i>برای کپی، روی هر کد ضربه بزنید</i>\n";
        $msg .= "🎧 پشتیبانی: @YourSupport";

        return $msg;
    }

    /**
     * Beautiful renewal invoice
     */
    public static function renderRenewInvoice(array $client, array $order, string $newExpire): string
    {
        $username = $client['username'] ?? '';
        $password = $client['password'] ?? '';
        $primarySub = $order['primary_sub'] ?? '';
        $trafficGb = $order['traffic_gb'] ?? 0;
        
        $nowJalali = JalaliDate::invoiceDate(time());
        $newExpireJalali = JalaliDate::format($newExpire, 'full');

        $msg = "┏━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 🔄 تمدید موفق! ┃\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━━━━┛\n\n";

        $msg .= "✅ <b>اشتراک شما با موفقیت تمدید شد!</b>\n\n";

        $msg .= "👤 <b>اطلاعات (کپی با یک ضربه):</b>\n";
        $msg .= "   ├─ نام کاربری: <code>{$username}</code>\n";
        $msg .= "   └─ رمز عبور: <code>{$password}</code>\n\n";

        $msg .= "📊 <b>جزئیات تمدید:</b>\n";
        $msg .= "   ├─ 💾 حجم افزوده: <b>" . JalaliDate::toPersianNumber($trafficGb) . " گیگ</b>\n";
        $msg .= "   ├─ 📅 انقضای جدید: {$newExpireJalali}\n";
        $msg .= "   └─ {$nowJalali}\n\n";

        $msg .= "🔗 <b>لینک اتصال (کپی):</b>\n<code>{$primarySub}</code>\n\n";
        $msg .= "💡 روی لینک ضربه بزنید تا کپی شود";

        return $msg;
    }

    /**
     * Beautiful account detail with Jalali and copyable fields
     */
    public static function renderAccountDetail(array $client, string $primarySub): string
    {
        $username = $client['username'] ?? '';
        $password = $client['password'] ?? '123456';
        $trafficLimit = (int)($client['traffic_limit_bytes'] ?? 0);
        $trafficUsed = (int)($client['traffic_used_bytes'] ?? 0);
        $trafficRemaining = $trafficLimit > 0 ? $trafficLimit - $trafficUsed : 0;
        
        $trafficText = $trafficLimit > 0 ? Helpers::formatBytes($trafficLimit) : 'نامحدود';
        $usedText = Helpers::formatBytes($trafficUsed);
        $remainingText = $trafficLimit > 0 ? Helpers::formatBytes($trafficRemaining) : 'نامحدود';

        $expireAt = $client['expire_at'] ?? '';
        $expireJalali = JalaliDate::expireDate($expireAt);
        $createdJalali = JalaliDate::format($client['created_at'] ?? time(), 'date');
        $serverName = $client['server_name'] ?? 'نامشخص';

        $msg = "┏━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 👤 جزئیات حساب ┃\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━━━━┛\n\n";

        $msg .= "🔐 <b>اطلاعات ورود (کپی با یک ضربه):</b>\n";
        $msg .= "   ├─ نام کاربری: <code>{$username}</code>\n";
        $msg .= "   └─ رمز عبور: <code>{$password}</code>\n\n";

        $msg .= "📊 <b>وضعیت اشتراک:</b>\n";
        $msg .= "   ├─ 🌐 سرور: {$serverName}\n";
        $msg .= "   ├─ 💾 حجم کل: {$trafficText}\n";
        $msg .= "   ├─ 📥 مصرف شده: {$usedText}\n";
        $msg .= "   ├─ 📤 باقی‌مانده: {$remainingText}\n";
        $msg .= "   ├─ {$expireJalali}\n";
        $msg .= "   └─ 📅 تاریخ ساخت: {$createdJalali}\n\n";

        $msg .= "🔗 <b>لینک اتصال (کپی با یک ضربه):</b>\n<code>{$primarySub}</code>\n\n";

        $msg .= "💡 <i>برای کپی هر مقدار، روی آن ضربه بزنید</i>";

        return $msg;
    }

    /**
     * Beautiful configs message with copyable configs
     */
    public static function renderConfigs(array $client, array $configs, string $primarySub): string
    {
        $username = $client['username'] ?? '';
        $nowJalali = JalaliDate::invoiceDate(time());

        $msg = "┏━━━━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 📥 کانفیگ‌های اختصاصی ┃\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━━━━┛\n\n";

        $msg .= "👤 حساب: <code>{$username}</code>\n";
        $msg .= "{$nowJalali}\n\n";

        $msg .= "🔗 <b>ساب‌لینک اصلی (کپی):</b>\n<code>{$primarySub}</code>\n\n";

        if (!empty($configs['vless_reality'])) {
            $msg .= "⚡️ <b>VLESS Reality (کپی):</b>\n<code>{$configs['vless_reality']}</code>\n\n";
        }
        if (!empty($configs['vless_ws'])) {
            $msg .= "🛡 <b>WebSocket (کپی):</b>\n<code>{$configs['vless_ws']}</code>\n\n";
        }
        if (!empty($configs['trojan'])) {
            $msg .= "🔒 <b>Trojan (کپی):</b>\n<code>{$configs['trojan']}</code>\n\n";
        }

        $count = 0;
        foreach ($configs as $k => $v) {
            if (str_starts_with($k, 'node_link_') || str_starts_with($k, 'sub_link_')) {
                $count++;
                $msg .= "🚀 <b>کانکشن {$count} (کپی):</b>\n<code>{$v}</code>\n\n";
            }
        }

        $msg .= "📱 <b>راهنما:</b> روی هر کانفیگ ضربه بزنید تا کپی شود\n";
        $msg .= "🔍 آیفون: <a href=\"https://vpbotn.ir/contax/ios-guide\">آموزش تصویری iOS</a>";

        return $msg;
    }
}

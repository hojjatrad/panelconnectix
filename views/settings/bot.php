<?php
$pageTitle = 'مدیریت و فعال‌سازی ربات تلگرام';
require __DIR__ . '/../layout/header.php';

$botActive = ($settings['telegram_bot_active'] ?? '1') === '1';
$botToken = $settings['telegram_bot_token'] ?? '';
$botUsername = $settings['telegram_bot_username'] ?? '';
$adminId = $settings['telegram_admin_id'] ?? '';

$forceJoinChannel = $settings['bot_force_join_channel'] ?? '';
$logSupergroup = $settings['bot_log_channel'] ?? '';
$topicSales = $settings['bot_topic_sales'] ?? '';
$topicBackup = $settings['bot_topic_backup'] ?? '';
$topicServers = $settings['bot_topic_servers'] ?? '';
$topicUsers = $settings['bot_topic_users'] ?? '';
$topicCrypto = $settings['bot_topic_crypto'] ?? '';

$btnBuyText = $settings['btn_buy_text'] ?? '🛒 خرید اشتراک';
$btnRenewText = $settings['btn_renew_text'] ?? '🔄 تمدید اشتراک';
$btnMyAccText = $settings['btn_my_accounts_text'] ?? '👤 حساب‌های من';
$btnTrialText = $settings['btn_trial_text'] ?? '🎁 تست رایگان';
$btnRefText = $settings['btn_referral_text'] ?? '🤝 کسب درآمد';
$btnAppsText = $settings['btn_apps_text'] ?? '📱 دانلود و آموزش';
$btnSupportText = $settings['btn_support_text'] ?? '☎️ پشتیبانی';
$btnResellerText = $settings['btn_reseller_text'] ?? '💼 اخذ نمایندگی';

$cardNumber = $settings['card_number'] ?? '';
$cardHolder = $settings['card_holder'] ?? '';
$cardBank = $settings['card_bank_name'] ?? '';
$supportTg = $settings['support_telegram'] ?? '';

$paymentGw = $settings['payment_gateway'] ?? 'card';
$zarinMerchant = $settings['zarinpal_merchant'] ?? '';
$nextpayKey = $settings['nextpay_apikey'] ?? '';
$nowpaymentsKey = $settings['nowpayments_apikey'] ?? '';

$cryptoUsdtWallet = $settings['crypto_usdt_trc20_address'] ?? '';
$cryptoUsdtRate = $settings['crypto_usdt_rate'] ?? '98000';
$cryptoTonWallet = $settings['crypto_ton_wallet_address'] ?? '';
$cryptoTonRate = $settings['crypto_ton_rate'] ?? '380000';
$trialEnabled = ($settings['trial_enabled'] ?? '1') === '1';
$trialHours = $settings['trial_duration_hours'] ?? '24';
$trialGb = $settings['trial_traffic_gb'] ?? '1';
$trialMb = $settings['trial_traffic_mb'] ?? '0';
$referralEnabled = ($settings['referral_enabled'] ?? '1') === '1';
$referralPercent = $settings['referral_commission_percent'] ?? '10';

$webhookUrl = Helpers::fullUrl('webhook.php');
$isWebhookSet = !empty($webhookInfo['result']['url'] ?? '');
?>

<div class="space-y-6">

    <!-- Top Status Bar & Quick Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-3xl shadow-lg shadow-cyan-600/10">
                <i class="fa-brands fa-telegram"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-bold text-white">ربات تلگرام و فروشگاه خودکار</h1>
                    <?php if ($botActive && !empty($botToken)): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">روشن و فعال</span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">نیاز به توکن یا غیرفعال</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-400 mt-1">خرید دسته‌بندی‌شده، تمدید، استعلام، عضویت اجباری، موضوعات انجمن (Topics) و کدهای تخفیف</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap">
            <form method="POST" action="<?= Helpers::url('settings/bot/set-webhook') ?>">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-bolt"></i>
                    <span>ثبت خودکار وبهوک</span>
                </button>
            </form>

            <form method="POST" action="<?= Helpers::url('settings/bot/test-message') ?>">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>تست ارسال به مدیر</span>
                </button>
            </form>

            <form method="POST" action="<?= Helpers::url('settings/bot/delete-webhook') ?>" onsubmit="return confirm('آیا از حذف وبهوک تلگرام اطمینان دارید؟');">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-3 py-2 bg-slate-800 hover:bg-rose-900/50 hover:text-rose-300 text-slate-300 rounded-xl text-xs font-medium transition border border-slate-700" title="حذف وبهوک">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Webhook Info Box -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg <?= $isWebhookSet ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400' ?> flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid <?= $isWebhookSet ? 'fa-link' : 'fa-link-slash' ?>"></i>
            </div>
            <div class="overflow-hidden">
                <div class="text-[11px] text-slate-400">وضعیت اتصال وبهوک تلگرام</div>
                <div class="text-xs font-bold text-white truncate" title="<?= htmlspecialchars($webhookInfo['result']['url'] ?? 'ثبت نشده') ?>">
                    <?= $isWebhookSet ? 'متصل به سرور' : 'تنظیم نشده (دکمه ثبت را بزنید)' ?>
                </div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-400">آپدیت‌های در صف تلگرام</div>
                <div class="text-xs font-bold text-white">
                    <?= intval($webhookInfo['result']['pending_update_count'] ?? 0) ?> پیام در انتظار
                </div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-400">سفارشات نیازمند بررسی</div>
                <div class="text-xs font-bold text-purple-300">
                    <?php 
                    $pendingCount = count(array_filter($orders, fn($o) => $o['payment_status'] === 'pending_approval'));
                    echo $pendingCount . ' سفارش در انتظار تایید';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Settings Form & Orders List -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Form Settings (5 Columns) -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2 pb-3 border-b border-slate-800">
                <i class="fa-solid fa-sliders text-purple-400"></i>
                <span>پیکربندی ربات، کانال و انجمن</span>
            </h2>

            <form method="POST" action="<?= Helpers::url('settings/bot') ?>" class="space-y-4">
                <?= Helpers::csrfField() ?>

                <!-- Tab Navigation Buttons -->
                <div class="grid grid-cols-4 gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs">
                    <button type="button" onclick="switchBotTab('core')" id="tabBtn-core" class="py-2 px-1 rounded-lg font-bold text-center transition bg-purple-600 text-white shadow text-[11px] truncate">
                        🤖 پایه
                    </button>
                    <button type="button" onclick="switchBotTab('payment')" id="tabBtn-payment" class="py-2 px-1 rounded-lg font-medium text-center transition text-slate-400 hover:text-white text-[11px] truncate">
                        💳 مالی
                    </button>
                    <button type="button" onclick="switchBotTab('growth')" id="tabBtn-growth" class="py-2 px-1 rounded-lg font-medium text-center transition text-slate-400 hover:text-white text-[11px] truncate">
                        🎁 تست
                    </button>
                    <button type="button" onclick="switchBotTab('ui')" id="tabBtn-ui" class="py-2 px-1 rounded-lg font-medium text-center transition text-slate-400 hover:text-white text-[11px] truncate">
                        ⌨️ دکمه‌ها
                    </button>
                </div>

                <!-- TAB 1: Core Configuration -->
                <div id="tabContent-core" class="space-y-4">
                    <!-- Bot Status Toggle -->
                    <div class="flex items-center justify-between p-3 bg-slate-950/60 rounded-xl border border-slate-800/80">
                        <div>
                            <div class="text-xs font-bold text-white">فعال بودن ربات تلگرام</div>
                            <div class="text-[11px] text-slate-400">پاسخگویی به خریداران در تلگرام</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="telegram_bot_active" value="1" class="sr-only peer" <?= $botActive ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>

                    <!-- Bot Token -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">توکن ربات تلگرام (Bot Token)</label>
                        <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($botToken) ?>" placeholder="مثال: 7123456789:AAHk..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-left" dir="ltr">
                        <p class="text-[10px] text-slate-500 mt-1">از طریق @BotFather در تلگرام دریافت می‌شود.</p>
                    </div>

                    <!-- Bot Username -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">یوزرنیم ربات تلگرام</label>
                        <div class="relative">
                            <input type="text" name="telegram_bot_username" value="<?= htmlspecialchars($botUsername) ?>" placeholder="MyVpnBot" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-left pl-8" dir="ltr">
                            <span class="absolute left-3 top-2.5 text-xs text-slate-500">@</span>
                        </div>
                    </div>

                    <!-- Admin Telegram Chat ID -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">شناسه عددی تلگرام مدیر (Admin Chat ID)</label>
                        <input type="text" name="telegram_admin_id" value="<?= htmlspecialchars($adminId) ?>" placeholder="مثال: 123456789" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-left" dir="ltr">
                        <p class="text-[10px] text-slate-500 mt-1">ارسال مستقیم فیش‌ها، فایل‌های بکاپ و اعلانات فوری به پیوی مدیر (ربات @userinfobot).</p>
                    </div>

                    <!-- Bot Welcome Text -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">متن پیام شروع ربات (Welcome Message)</label>
                        <textarea name="bot_welcome_text" rows="2" placeholder="به ربات رسمی خوش آمدید..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-purple-500"><?= htmlspecialchars($settings['bot_welcome_text'] ?? '') ?></textarea>
                    </div>

                    <!-- Force Join Channel -->
                    <div class="pt-3 border-t border-slate-800/80 space-y-2">
                        <div class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-bullhorn"></i>
                            <span>عضویت اجباری در کانال (Force Join)</span>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">آیدی عمومی یا عددی کانال تلگرام</label>
                            <input type="text" name="bot_force_join_channel" value="<?= htmlspecialchars($forceJoinChannel) ?>" placeholder="@MyChannel یا -1001234567890" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-amber-500 font-mono text-left" dir="ltr">
                            <p class="text-[10px] text-slate-500 mt-0.5">در صورت تنظیم، کاربر قبل از دسترسی موظف به عضویت است. ربات باید ادمین کانال باشد.</p>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Payments & Gateways -->
                <div id="tabContent-payment" class="space-y-4 hidden">
                    <!-- Card to Card Section -->
                    <div class="space-y-3">
                        <div class="text-xs font-bold text-purple-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>اطلاعات کارت جهت واریز مشتری</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">شماره کارت ۱۶ رقمی</label>
                            <input type="text" name="card_number" value="<?= htmlspecialchars($cardNumber) ?>" placeholder="۶۰۳۷-xxxx-xxxx-xxxx" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-center tracking-widest" dir="ltr">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">نام دارنده حساب</label>
                                <input type="text" name="card_holder" value="<?= htmlspecialchars($cardHolder) ?>" placeholder="نام صاحب کارت" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1">نام بانک</label>
                                <input type="text" name="card_bank_name" value="<?= htmlspecialchars($cardBank) ?>" placeholder="بانک ملی" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-purple-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">شماره شبا (اختیاری)</label>
                            <input type="text" name="card_sheba" value="<?= htmlspecialchars($settings['card_sheba'] ?? '') ?>" placeholder="IR..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-left" dir="ltr">
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">آیدی پشتیبانی تلگرام</label>
                            <input type="text" name="support_telegram" value="<?= htmlspecialchars($supportTg) ?>" placeholder="@Support_ID" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-purple-500 font-mono text-left" dir="ltr">
                        </div>
                    </div>

                    <!-- Online Payment Gateway Section -->
                    <div class="pt-3 border-t border-slate-800/80 space-y-3">
                        <div class="text-xs font-bold text-cyan-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-globe"></i>
                            <span>درگاه پرداخت آنلاین</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">روش پرداخت</label>
                            <select name="payment_gateway" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-500">
                                <option value="card" <?= $paymentGw === 'card' ? 'selected' : '' ?>>کارت به کارت دستی (تایید فیش)</option>
                                <option value="zarinpal" <?= $paymentGw === 'zarinpal' ? 'selected' : '' ?>>زرین‌پال (درگاه ریالی)</option>
                                <option value="nextpay" <?= $paymentGw === 'nextpay' ? 'selected' : '' ?>>نکست‌پی (NextPay)</option>
                                <option value="nowpayments" <?= $paymentGw === 'nowpayments' ? 'selected' : '' ?>>NOWPayments (درگاه کریپتو)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">مرچنت زرین‌پال</label>
                            <input type="text" name="zarinpal_merchant" value="<?= htmlspecialchars($zarinMerchant) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-left" dir="ltr">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">API Key نکست‌پی</label>
                                <input type="text" name="nextpay_apikey" value="<?= htmlspecialchars($nextpayKey) ?>" placeholder="api_key_..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white font-mono text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">API Key درگاه NOWPayments</label>
                                <input type="text" name="nowpayments_apikey" value="<?= htmlspecialchars($nowpaymentsKey) ?>" placeholder="NOWPayments Key..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white font-mono text-left" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <!-- Cryptocurrency USDT TRC20 & TON Section -->
                    <div class="pt-3 border-t border-slate-800/80 space-y-3">
                        <div class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-coins"></i>
                            <span>پرداخت کریپتو مستقیم (USDT TRC-20 و شبکه TON)</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">آدرس ولت تتر (TRC-20)</label>
                            <input type="text" name="crypto_usdt_trc20_address" value="<?= htmlspecialchars($cryptoUsdtWallet) ?>" placeholder="TLa5xxxx..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono text-left" dir="ltr">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">نرخ هر تتر (تومان)</label>
                                <input type="number" name="crypto_usdt_rate" value="<?= htmlspecialchars($cryptoUsdtRate) ?>" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white font-mono text-center">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">نرخ هر TON (تومان)</label>
                                <input type="number" name="crypto_ton_rate" value="<?= htmlspecialchars($cryptoTonRate) ?>" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white font-mono text-center">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">آدرس ولت TON (Tonkeeper / Wallet)</label>
                            <input type="text" name="crypto_ton_wallet_address" value="<?= htmlspecialchars($cryptoTonWallet) ?>" placeholder="EQD4FPq-PRDieyQKkKZjmNu49pKypDryHyRMvzkBhzsJw6-h" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-left" dir="ltr">
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Growth, Free Trial & Referral -->
                <div id="tabContent-growth" class="space-y-4 hidden">
                    <!-- Free Trial Section -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold text-amber-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-gift"></i>
                                <span>تست رایگان خودکار (Free Trial)</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="trial_enabled" value="1" class="sr-only peer" <?= $trialEnabled ? 'checked' : '' ?>>
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[10px] font-medium text-slate-400 mb-1">مدت زمان (ساعت)</label>
                                <input type="number" name="trial_duration_hours" value="<?= htmlspecialchars($trialHours) ?>" min="1" max="720" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 text-xs text-white focus:outline-none focus:border-amber-500 font-mono text-center">
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-slate-400 mb-1">حجم به مگابایت (MB)</label>
                                <input type="number" name="trial_traffic_mb" value="<?= htmlspecialchars($trialMb) ?>" min="0" placeholder="مثلاً 500" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 text-xs text-white focus:outline-none focus:border-amber-500 font-mono text-center">
                                <span class="text-[9px] text-amber-400 block text-center mt-0.5">اولویت اصلی</span>
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-slate-400 mb-1">حجم به گیگ (GB)</label>
                                <input type="number" name="trial_traffic_gb" value="<?= htmlspecialchars($trialGb) ?>" min="1" max="50" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-2 py-2 text-xs text-white focus:outline-none focus:border-amber-500 font-mono text-center">
                                <span class="text-[9px] text-slate-500 block text-center mt-0.5">در صورت ۰ بودن MB</span>
                            </div>
                        </div>
                    </div>

                    <!-- Referral Program Section -->
                    <div class="pt-3 border-t border-slate-800/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold text-cyan-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-users-rays"></i>
                                <span>سامانه زیرمجموعه‌گیری و بازاریابی (Referral)</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="referral_enabled" value="1" class="sr-only peer" <?= $referralEnabled ? 'checked' : '' ?>>
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">درصد پورسانت معرف از هر خرید</label>
                            <div class="relative">
                                <input type="number" name="referral_commission_percent" value="<?= htmlspecialchars($referralPercent) ?>" min="0" max="50" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-center">
                                <span class="absolute left-3 top-2 text-xs text-slate-400 font-bold">%</span>
                            </div>
                            <span class="text-[10px] text-slate-500 mt-0.5 block">به ازای هر خرید توسط زیرمجموعه، این درصد به عنوان شارژ نقدی به کیف‌پول معرف اضافه می‌شود.</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: UI Customization & Supergroup Topics -->
                <div id="tabContent-ui" class="space-y-4 hidden">
                    <!-- Supergroup Forum Topics Section -->
                    <div class="space-y-3">
                        <div class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-layer-group"></i>
                            <span>سوپرگروه لاگ و تاپیک‌های انجمن (Forum Topics)</span>
                        </div>
                        <p class="text-[10px] text-slate-400">تفکیک گزارش خریدها، بکاپ‌ها، سلامت سرورها و تراکنش‌ها در تاپیک‌های اختصاصی تلگرام.</p>

                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">شناسه عددی سوپرگروه لاگ (Supergroup ID)</label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="botLogChannelInput" name="bot_log_channel" value="<?= htmlspecialchars($logSupergroup) ?>" placeholder="-1001234567890" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-emerald-500 font-mono text-left" dir="ltr">
                                <button type="button" onclick="autoCreateForumTopics()" id="btnAutoTopics" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[11px] font-bold transition flex items-center gap-1.5 shrink-0 shadow">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    <span>ساخت خودکار تاپیک‌ها</span>
                                </button>
                            </div>
                            <span id="topicAutoStatus" class="text-[10px] text-slate-500 mt-1 block"></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-[11px]">
                            <?php 
                            $topicsConfig = [
                                'nightly' => ['title' => '🌙 گزارش شبانه (Nightly)', 'id' => $settings['bot_topic_nightly'] ?? ''],
                                'backup_reseller' => ['title' => '🤖 بکاپ ربات نماینده (Reseller Backup)', 'id' => $settings['bot_topic_backup_reseller'] ?? ''],
                                'backup_all' => ['title' => '💾 بکاپ تمام ربات (Full Backup)', 'id' => $settings['bot_topic_backup_all'] ?? ''],
                                'notifications' => ['title' => '📢 گزارش اطلاع‌رسانی‌ها (Broadcasts)', 'id' => $settings['bot_topic_notifications'] ?? ''],
                                'services' => ['title' => '🛍 گزارش خرید خدمات (Services)', 'id' => $settings['bot_topic_services'] ?? ''],
                                'sales' => ['title' => '🛒 گزارش‌های خرید (Orders)', 'id' => $settings['bot_topic_sales'] ?? ''],
                                'finance' => ['title' => '💳 گزارش‌های مالی (Financial)', 'id' => $settings['bot_topic_finance'] ?? ''],
                                'trials' => ['title' => '🎁 گزارشات اکانت تست (Trials)', 'id' => $settings['bot_topic_trials'] ?? ''],
                                'general' => ['title' => '📊 سایر گزارشات (General)', 'id' => $settings['bot_topic_general'] ?? ''],
                                'commissions' => ['title' => '🤝 گزارشات پورسانت (Commissions)', 'id' => $settings['bot_topic_commissions'] ?? ''],
                                'errors' => ['title' => '⚠️ گزارش خطاها (Errors)', 'id' => $settings['bot_topic_errors'] ?? ''],
                            ];
                            foreach ($topicsConfig as $tKey => $tData):
                            ?>
                                <div>
                                    <label class="block text-[10px] text-slate-400 mb-1"><?= $tData['title'] ?></label>
                                    <input type="text" id="topic_<?= $tKey ?>" name="bot_topic_<?= $tKey ?>" value="<?= htmlspecialchars($tData['id']) ?>" placeholder="شناسه عددی تاپیک" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white font-mono text-center" dir="ltr">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Custom Button Labels & Feature Toggles Section -->
                    <div class="pt-3 border-t border-slate-800/80 space-y-3">
                        <div class="text-xs font-bold text-sky-400 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-toggle-on text-emerald-400"></i>
                                <span>فعال‌سازی و شخصی‌سازی دکمه‌های ربات</span>
                            </span>
                            <span class="text-[10px] text-slate-400 font-normal">امکان خاموش/روشن کردن کلیدها</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <?php
                            $botButtonsConfig = [
                                'buy' => ['title' => '🛒 خرید اشتراک', 'text' => $settings['btn_buy_text'] ?? '🛒 خرید اشتراک', 'enabled' => ($settings['btn_buy_enabled'] ?? '1') === '1'],
                                'renew' => ['title' => '🔄 تمدید اشتراک', 'text' => $settings['btn_renew_text'] ?? '🔄 تمدید اشتراک', 'enabled' => ($settings['btn_renew_enabled'] ?? '1') === '1'],
                                'my_accounts' => ['title' => '👤 حساب‌های من', 'text' => $settings['btn_my_accounts_text'] ?? '👤 حساب‌های من', 'enabled' => ($settings['btn_my_accounts_enabled'] ?? '1') === '1'],
                                'trial' => ['title' => '🎁 تست رایگان', 'text' => $settings['btn_trial_text'] ?? '🎁 تست رایگان', 'enabled' => ($settings['btn_trial_enabled'] ?? '1') === '1'],
                                'wheel' => ['title' => '🎰 گردونه شانس و هدیه', 'text' => $settings['btn_wheel_text'] ?? '🎰 گردونه شانس و هدیه', 'enabled' => ($settings['btn_wheel_enabled'] ?? '1') === '1'],
                                'referral' => ['title' => '🤝 کسب درآمد', 'text' => $settings['btn_referral_text'] ?? '🤝 کسب درآمد', 'enabled' => ($settings['btn_referral_enabled'] ?? '1') === '1'],
                                'apps' => ['title' => '📱 دانلود و آموزش', 'text' => $settings['btn_apps_text'] ?? '📱 دانلود و آموزش', 'enabled' => ($settings['btn_apps_enabled'] ?? '1') === '1'],
                                'support' => ['title' => '☎️ پشتیبانی', 'text' => $settings['btn_support_text'] ?? '☎️ پشتیبانی', 'enabled' => ($settings['btn_support_enabled'] ?? '1') === '1'],
                                'reseller' => ['title' => '💼 اخذ نمایندگی', 'text' => $settings['btn_reseller_text'] ?? '💼 اخذ نمایندگی', 'enabled' => ($settings['btn_reseller_enabled'] ?? '1') === '1'],
                                'panel_login' => ['title' => '🔐 ورود به پنل وب', 'text' => $settings['btn_panel_login_text'] ?? '🔐 ورود به پنل وب', 'enabled' => ($settings['btn_panel_login_enabled'] ?? '1') === '1'],
                                'webapp' => ['title' => '🚀 مینی‌اپ تلگرام (Mini App)', 'text' => $settings['btn_webapp_text'] ?? '🚀 مینی‌اپ اختصاصی کانکتیکس (Mini App)', 'enabled' => ($settings['btn_webapp_enabled'] ?? '1') === '1'],
                            ];
                            foreach ($botButtonsConfig as $bKey => $b):
                            ?>
                                <div class="p-2.5 bg-slate-950/80 rounded-xl border border-slate-800/80 flex flex-col gap-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-slate-300"><?= $b['title'] ?></span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="btn_<?= $bKey ?>_enabled" value="1" <?= $b['enabled'] ? 'checked' : '' ?> class="sr-only peer">
                                            <div class="w-8 h-4 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                                        </label>
                                    </div>
                                    <input type="text" name="btn_<?= $bKey ?>_text" value="<?= htmlspecialchars($b['text']) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white text-center">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-purple-600/30 transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>ذخیره کلیه تنظیمات ربات</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Orders Table (7 Columns) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-cart-shopping text-cyan-400"></i>
                    <span>سفارش‌های دریافتی از ربات تلگرام</span>
                </h2>
                <span class="text-xs text-slate-400"><?= count($orders) ?> سفارش اخیر</span>
            </div>

            <?php if (empty($orders)): ?>
                <div class="p-12 text-center text-slate-500 space-y-2 my-auto">
                    <i class="fa-solid fa-inbox text-3xl opacity-40"></i>
                    <p class="text-xs">هنوز سفارشی از ربات تلگرام ثبت نشده است.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-800">
                                <th class="pb-3 pr-2">کد سفارش</th>
                                <th class="pb-3">کاربر تلگرام</th>
                                <th class="pb-3">پلن</th>
                                <th class="pb-3">مبلغ</th>
                                <th class="pb-3">وضعیت</th>
                                <th class="pb-3 text-center">اقدام</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($orders as $o): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-3 pr-2 font-mono text-purple-300 font-bold"><?= htmlspecialchars($o['order_code']) ?></td>
                                    <td class="py-3">
                                        <div class="font-bold text-white"><?= htmlspecialchars($o['user_tg_name'] ?: 'کاربر') ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            <?= !empty($o['user_tg_username']) ? '@' . htmlspecialchars($o['user_tg_username']) : 'ID: ' . $o['user_tg_id'] ?>
                                        </div>
                                    </td>
                                    <td class="py-3 text-slate-300"><?= htmlspecialchars($o['plan_title'] ?? 'پلن حذف شده') ?></td>
                                    <td class="py-3 font-mono">
                                        <span class="font-bold text-slate-200"><?= number_format($o['amount']) ?> ت</span>
                                        <?php if (!empty($o['coupon_code'])): ?>
                                            <div class="text-[10px] text-amber-400 font-mono">🎟 <?= htmlspecialchars($o['coupon_code']) ?> (-<?= number_format($o['discount_amount'] ?? 0) ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($o['payment_status'] === 'paid'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">تایید و فعال شده</span>
                                        <?php elseif ($o['payment_status'] === 'pending_approval'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/20 font-bold animate-pulse">فیش واریز شد (نیاز به تایید)</span>
                                        <?php elseif ($o['payment_status'] === 'rejected'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-500/10 text-rose-400 border border-rose-500/20">رد شده</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-400">در انتظار واریز</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center">
                                        <?php if ($o['payment_status'] === 'pending_approval'): ?>
                                            <div class="flex items-center justify-center gap-1.5">
                                                <form method="POST" action="<?= Helpers::url('settings/bot/approve') ?>">
                                                    <?= Helpers::csrfField() ?>
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition shadow" title="تایید و ساخت خودکار اکانت">
                                                        <i class="fa-solid fa-check"></i> تایید
                                                    </button>
                                                </form>

                                                <form method="POST" action="<?= Helpers::url('settings/bot/reject') ?>">
                                                    <?= Helpers::csrfField() ?>
                                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                    <button type="submit" class="px-2 py-1 bg-rose-900/50 hover:bg-rose-800 text-rose-300 rounded-lg text-[10px] font-bold transition border border-rose-800/50" title="رد سفارش">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($o['payment_status'] === 'paid' && !empty($o['client_username'])): ?>
                                            <span class="text-[10px] text-emerald-400 font-mono">کاربر: <?= htmlspecialchars($o['client_username']) ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[11px]">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Cron Automation Hint -->
            <div class="mt-auto pt-4 border-t border-slate-800/80">
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-[11px] text-slate-400 space-y-1">
                    <div class="font-bold text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-clock text-purple-400"></i>
                        <span>دستور Cron Job سی‌پنل جهت استعلام حجم، فیل‌اور، بکاپ و تمدید خودکار:</span>
                    </div>
                    <code class="block font-mono text-[10px] text-purple-300 select-all bg-slate-900 p-1.5 rounded border border-slate-800 text-left" dir="ltr">
                        */10 * * * * curl -s "<?= Helpers::fullUrl('cron/sync.php?key=' . APP_SECRET) ?>" >/dev/null 2>&1
                    </code>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function switchBotTab(tab) {
    const tabs = ['core', 'payment', 'growth', 'ui'];
    tabs.forEach(t => {
        const pane = document.getElementById('tabContent-' + t);
        const btn = document.getElementById('tabBtn-' + t);
        if (pane && btn) {
            if (t === tab) {
                pane.classList.remove('hidden');
                btn.className = 'py-2 px-1 rounded-lg font-bold text-center transition bg-purple-600 text-white shadow text-[11px] truncate';
            } else {
                pane.classList.add('hidden');
                btn.className = 'py-2 px-1 rounded-lg font-medium text-center transition text-slate-400 hover:text-white text-[11px] truncate';
            }
        }
    });
}

function autoCreateForumTopics() {
    const btn = document.getElementById('btnAutoTopics');
    const status = document.getElementById('topicAutoStatus');
    const logChannel = document.getElementById('botLogChannelInput').value.trim();

    if (!logChannel) {
        alert('لطفاً ابتدا شناسه عددی سوپرگروه لاگ (مثلاً -1001234567890) را وارد نمایید.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>در حال ساخت ۱۱ تاپیک...</span>';
    status.className = 'text-[10px] text-amber-400 mt-1 block';
    status.innerText = 'در حال ارتباط با API تلگرام و ایجاد ۱۱ تاپیک اختصاصی...';

    const fd = new FormData();
    fd.append('log_channel', logChannel);
    fd.append('csrf_token', '<?= Helpers::csrfToken() ?>');

    fetch('<?= Helpers::url('settings/bot/auto-create-topics') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('کد خطای سرور: ' + r.status + ' (' + r.statusText + ')');
        }
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> <span>ساخت خودکار تاپیک‌ها</span>';
        
        if (data.results) {
            for (let k in data.results) {
                if (data.results[k] && data.results[k].thread_id) {
                    let inp = document.getElementById('topic_' + k);
                    if (inp) inp.value = data.results[k].thread_id;
                }
            }
        }

        if (data.success) {
            status.className = 'text-[10px] text-emerald-400 mt-1 block';
            status.innerText = '✅ ' + data.message;
        } else {
            status.className = 'text-[10px] text-rose-400 mt-1 block';
            status.innerText = '⚠️ ' + data.message;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> <span>ساخت خودکار تاپیک‌ها</span>';
        status.className = 'text-[10px] text-rose-400 mt-1 block';
        status.innerText = 'خطا در ارتباط با سرور: ' + err.message;
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

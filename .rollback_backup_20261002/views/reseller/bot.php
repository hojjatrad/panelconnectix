<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-brands fa-telegram text-cyan-400"></i>
                <span>ربات تلگرام و سیستم فروش خودکار اختصاصی من</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">با اتصال ربات تلگرام خود، مشتریان شما مستقیماً با برند شما خرید، تمدید و استعلام سرویس انجام می‌دهند.</p>
        </div>

        <?php if (!empty($reseller['telegram_bot_username'])): ?>
            <a href="https://t.me/<?= htmlspecialchars($reseller['telegram_bot_username']) ?>" target="_blank" class="px-4 py-2 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white text-xs font-bold rounded-xl transition shadow flex items-center gap-2 w-fit">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>مشاهده ربات در تلگرام (@<?= htmlspecialchars($reseller['telegram_bot_username']) ?>)</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Bot Form Card -->
        <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
            <h3 class="text-sm font-bold text-white border-b border-slate-800 pb-3 flex items-center gap-2">
                <i class="fa-solid fa-gear text-purple-400"></i>
                <span>تنظیمات توکن و مشخصات ربات تلگرام</span>
            </h3>

            <form action="<?= Helpers::url('reseller/bot') ?>" method="POST" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1.5">توکن ربات تلگرام شما (Bot Token):</label>
                    <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($reseller['telegram_bot_token'] ?? '') ?>" 
                           dir="ltr" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-cyan-500 focus:outline-none">
                    <span class="text-[11px] text-slate-400 mt-1 block">توکن اختصاصی دریافتی از <code class="text-cyan-400">@BotFather</code> در تلگرام</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">آیدی کاربری ربات (بدون @):</label>
                        <input type="text" name="telegram_bot_username" value="<?= htmlspecialchars($reseller['telegram_bot_username'] ?? '') ?>" 
                               dir="ltr" placeholder="MyBrandVPN_Bot"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-cyan-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">عددی شناسه چت تلگرام شما (Admin Chat ID):</label>
                        <input type="text" name="telegram_admin_chat_id" value="<?= htmlspecialchars($reseller['telegram_admin_chat_id'] ?? '') ?>" 
                               dir="ltr" placeholder="987654321" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-cyan-500 focus:outline-none">
                        <span class="text-[10px] text-slate-500 mt-1 block">رسیدهای پرداختی مشتریان مستقیماً به این شناسه ارسال می‌شود.</span>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1.5">کانال تلگرام جهت عضویت اجباری (Force Join):</label>
                    <input type="text" name="telegram_channel" value="<?= htmlspecialchars($reseller['telegram_channel'] ?? '') ?>" 
                           dir="ltr" placeholder="@MyBrandChannel یا -100..."
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-cyan-500 focus:outline-none">
                    <span class="text-[10px] text-slate-500 mt-1 block">در صورت تکمیل، مشتریان قبل از خرید در ربات شما موظف به عضویت در کانال شما خواهند بود. ربات باید ادمین کانال باشد.</span>
                </div>

                <!-- Webhook URL Display -->
                <div class="p-3.5 bg-slate-950/60 rounded-xl border border-slate-800 space-y-1.5">
                    <span class="text-[11px] font-bold text-slate-400 block">آدرس وب‌هوک خودکار اختصاصی شما:</span>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="<?= htmlspecialchars($webhookUrl) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-slate-400 font-mono text-[11px] select-all" dir="ltr">
                    </div>
                    <span class="text-[10px] text-slate-500 block">به‌محض زدن دکمه ذخیره، وب‌هوک به صورت خودکار توسط سیستم روی تلگرام ثبت و فعال می‌گردد.</span>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-purple-900/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>ذخیره تنظیمات و اتصال ربات تلگرام</span>
                </button>
            </form>
        </div>

        <!-- Guide & Status Card -->
        <div class="space-y-4">
            <!-- Webhook Live Status -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
                <h4 class="text-xs font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-satellite-dish text-emerald-400"></i>
                    <span>وضعیت زنده اتصال به تلگرام</span>
                </h4>

                <?php if (!empty($webhookInfo['ok'])): ?>
                    <div class="p-3 bg-emerald-950/40 border border-emerald-800/60 rounded-xl text-emerald-300 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>ربات فعال و وب‌هوک متصل است</span>
                        </div>
                        <div class="text-[10px] text-emerald-400/80 font-mono">
                            Pending updates: <?= $webhookInfo['result']['pending_update_count'] ?? 0 ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-amber-950/40 border border-amber-800/60 rounded-xl text-amber-300 text-xs">
                        <span class="font-semibold">هنوز توکن ثبت یا متصل نشده است.</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bot Setup Instructions -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3 text-xs text-slate-300 leading-relaxed">
                <h4 class="font-bold text-white border-b border-slate-800 pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-circle-question text-cyan-400"></i>
                    <span>راهنمای ۳ مرحله‌ای ساخت ربات:</span>
                </h4>
                <ol class="list-decimal list-inside space-y-2 text-slate-400 text-[11px]">
                    <li>در تلگرام به ربات <a href="https://t.me/BotFather" target="_blank" class="text-cyan-400 underline font-mono">@BotFather</a> پیام داده و دستور <code class="text-white">/newbot</code> را بفرستید.</li>
                    <li>یک نام برای برند خود و یک آیدی برای ربات انتخاب کنید. توکن نهایی را کپی کرده و در کادر روبه‌رو جای‌گذاری نمایید.</li>
                    <li>جهت دریافت شناسه عددی تلگرام خود، به ربات <a href="https://t.me/userinfobot" target="_blank" class="text-cyan-400 underline font-mono">@userinfobot</a> پیام دهید و عدد Id را در فیلد Chat ID وارد فرمایید.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

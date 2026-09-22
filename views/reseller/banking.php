<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-emerald-400"></i>
                <span>تنظیمات حساب بانکی و درگاه پرداخت اختصاصی من</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">مشخصات بانکی شما در ربات تلگرام و صفحه پرداخت به مشتریانتان نمایش داده می‌شود و واریزی‌ها مستقیماً به حساب شما واریز خواهد شد.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Banking Settings Form -->
        <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
            <h3 class="text-sm font-bold text-white border-b border-slate-800 pb-3 flex items-center gap-2">
                <i class="fa-solid fa-building-columns text-cyan-400"></i>
                <span>اطلاعات کارت‌به‌کارت و حساب بانکی</span>
            </h3>

            <form action="<?= Helpers::url('reseller/banking') ?>" method="POST" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">شماره کارت بانکی ۱۶ رقمی:</label>
                        <input type="text" name="card_number" value="<?= htmlspecialchars($banking['card_number'] ?? '') ?>" 
                               dir="ltr" placeholder="۶۰۳۷-۹۹۷۵-xxxx-xxxx" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-sm tracking-widest focus:border-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">نام و نام خانوادگی صاحب حساب:</label>
                        <input type="text" name="card_holder" value="<?= htmlspecialchars($banking['card_holder'] ?? '') ?>" 
                               placeholder="مثال: علی حسینی" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1.5">شماره شبا (اختیاری جهت مبالغ بالاتر):</label>
                    <input type="text" name="card_shaba" value="<?= htmlspecialchars($banking['card_shaba'] ?? '') ?>" 
                           dir="ltr" placeholder="IR000000000000000000000000"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="pt-4 border-t border-slate-800">
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-globe text-amber-400"></i>
                        <span>درگاه پرداخت آنلاین زرین‌پال (اختیاری)</span>
                    </h3>

                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">مرچنت‌کد اختصاصی زرین‌پال (Merchant ID):</label>
                        <input type="text" name="zarinpal_merchant" value="<?= htmlspecialchars($banking['zarinpal_merchant'] ?? '') ?>" 
                               dir="ltr" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-amber-500 focus:outline-none">
                        <span class="text-[10px] text-slate-500 mt-1 block">در صورت وارد کردن مرچنت‌کد، دکمه پرداخت آنلاین مستقیم زرین‌پال برای مشتریان شما در ربات تلگرام فعال می‌شود.</span>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-emerald-900/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>ذخیره اطلاعات بانکی</span>
                </button>
            </form>
        </div>

        <!-- Preview Card -->
        <div class="space-y-4">
            <div class="bg-gradient-to-br from-slate-900 to-emerald-950 border border-emerald-900/40 rounded-2xl p-5 shadow-lg space-y-4 relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>

                <div class="flex items-center justify-between text-slate-400 text-xs">
                    <span class="font-bold text-emerald-400"><i class="fa-solid fa-shield-halved ml-1"></i> کارت تجاری نماینده</span>
                    <i class="fa-brands fa-cc-mastercard text-2xl text-slate-300"></i>
                </div>

                <div class="py-2 text-center">
                    <span class="text-[11px] text-slate-400 block mb-1">شماره کارت نمایشی در ربات تلگرام:</span>
                    <span class="text-sm font-mono font-bold text-white tracking-widest block bg-slate-900/70 py-2 rounded-lg border border-slate-800">
                        <?= !empty($banking['card_number']) ? htmlspecialchars($banking['card_number']) : '---- ---- ---- ----' ?>
                    </span>
                </div>

                <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-800/80">
                    <div>
                        <span class="text-[10px] text-slate-400 block">صاحب حساب:</span>
                        <span class="font-semibold text-slate-200"><?= !empty($banking['card_holder']) ? htmlspecialchars($banking['card_holder']) : 'تنظیم نشده' ?></span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 font-semibold">تأیید مستقیم</span>
                </div>
            </div>

            <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-2xl text-xs text-slate-400 leading-relaxed space-y-2">
                <span class="font-bold text-slate-200 block"><i class="fa-solid fa-circle-info text-cyan-400 ml-1"></i> روند تراکنش‌ها:</span>
                <p>مشتری کل مبلغ فاکتور را مستقیماً به کارت شما واریز می‌کند و عکس رسید را در ربات می‌فرستد. شما رسید را بررسی کرده و با تایید آن، هزینه عمده بسته از کیف پول شما کسر و اشتراک تحویل می‌گردد.</p>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

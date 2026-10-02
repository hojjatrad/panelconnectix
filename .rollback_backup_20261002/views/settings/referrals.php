<?php
require __DIR__ . '/../layout/header.php';
$refCode = $stats['referral_code'] ?? '';
$refUrl = Helpers::fullUrl("?ref=" . $refCode);
$botUsername = Setting::get('telegram_bot_username', '');
$botRefUrl = !empty($botUsername) ? "https://t.me/" . ltrim($botUsername, '@') . "?start=ref_" . $refCode : $refUrl;
?>

<div class="space-y-6">

    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center text-3xl shadow-lg">
                <i class="fa-solid fa-gift"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white">سیستم معرف و بازاریابی پورسانتی (Affiliate Program)</h1>
                <p class="text-xs text-slate-400 mt-1">با معرفی دوستان و همکاران، در هر بار خرید و شارژ کیف پول آن‌ها پورسانت نقدی دریافت کنید.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-purple-600/20 text-purple-300 border border-purple-500/30 text-xs font-bold font-mono">
                پورسانت شما: <?= $stats['commission_percent'] ?>% از هر خرید
            </span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">کاربران دعوت‌شده</span>
                <span class="text-lg font-black text-white font-mono"><?= $stats['total_invited'] ?> نفر</span>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">کل پورسانت واریزی</span>
                <span class="text-lg font-black text-emerald-400 font-mono"><?= Helpers::formatMoney($stats['total_commission']) ?></span>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">واریز خودکار</span>
                <span class="text-xs font-bold text-cyan-300">آنی به کیف پول پنل</span>
            </div>
        </div>
    </div>

    <!-- Referral Link Box -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 space-y-4">
        <h3 class="font-bold text-sm text-white flex items-center gap-2 pb-2 border-b border-slate-800">
            <i class="fa-solid fa-share-nodes text-purple-400"></i>
            <span>لینک‌های اختصاصی دعوت شما</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <!-- Telegram Bot Link -->
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                <span class="font-bold text-sky-400 flex items-center gap-1.5">
                    <i class="fa-brands fa-telegram"></i>
                    <span>لینک دعوت ربات تلگرام:</span>
                </span>
                <div class="flex items-center gap-2">
                    <input type="text" readonly id="botRefInput" value="<?= htmlspecialchars($botRefUrl) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg p-2 font-mono text-[11px] text-slate-300 select-all" dir="ltr">
                    <button onclick="copyToClipboard(document.getElementById('botRefInput').value, this)" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold transition">
                        کپی
                    </button>
                </div>
            </div>

            <!-- Web Link -->
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                <span class="font-bold text-purple-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-globe"></i>
                    <span>کد معرف اختصاصی شما:</span>
                </span>
                <div class="flex items-center gap-2">
                    <input type="text" readonly id="refCodeInput" value="<?= htmlspecialchars($refCode) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg p-2 font-mono text-xs font-bold text-amber-300 text-center tracking-widest select-all" dir="ltr">
                    <button onclick="copyToClipboard(document.getElementById('refCodeInput').value, this)" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold transition">
                        کپی کد
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Conversions Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 space-y-4">
        <h3 class="font-bold text-sm text-white flex items-center gap-2 pb-2 border-b border-slate-800">
            <i class="fa-solid fa-list-check text-emerald-400"></i>
            <span>تراکنش‌های اخیر کسب درآمد از معرف</span>
        </h3>

        <div class="overflow-x-auto text-xs">
            <table class="w-full text-right">
                <thead class="bg-slate-950/60 text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="p-3 font-semibold">شناسه</th>
                        <th class="p-3 font-semibold">کاربر دعوت‌شده</th>
                        <th class="p-3 font-semibold">مبلغ پورسانت واریزی</th>
                        <th class="p-3 font-semibold">تاریخ</th>
                        <th class="p-3 font-semibold">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($stats['recent'])): ?>
                        <tr><td colspan="5" class="p-6 text-center text-slate-500">هنوز خریدی توسط زیرمجموعه‌های شما ثبت نشده است. لینک خود را به اشتراک بگذارید!</td></tr>
                    <?php else: ?>
                        <?php foreach ($stats['recent'] as $r): ?>
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono text-slate-400">#<?= $r['id'] ?></td>
                                <td class="p-3 font-bold text-white"><?= htmlspecialchars($r['referred_username'] ?? ('کاربر #' . $r['referred_id'])) ?></td>
                                <td class="p-3 font-mono font-bold text-emerald-400">+<?= Helpers::formatMoney($r['commission_amount']) ?></td>
                                <td class="p-3 text-slate-400 font-mono text-[11px]"><?= $r['created_at'] ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">واریز شده</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerText;
        btn.innerText = 'کپی شد!';
        btn.classList.add('bg-emerald-600');
        setTimeout(() => {
            btn.innerText = orig;
            btn.classList.remove('bg-emerald-600');
        }, 2000);
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

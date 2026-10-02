<?php
$pageTitle = 'سرویس AI — نمایندگان';
require __DIR__ . '/../layout/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-indigo-400"></i>
                <span>سرویس هوش مصنوعی نمایندگان (شارژ با تاریخ انقضا)</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">هر نماینده‌ای که سرویس دارد، تیکت‌هایش بلافاصله توسط AI پاسخ داده می‌شود. با پایان تاریخ، غیرفعال می‌شود و اعلان می‌افتد.</p>
        </div>
        <a href="<?= Helpers::url('settings/ai') ?>" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-right"></i> تنظیمات AI
        </a>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 flex items-center justify-between">
        <span class="text-xs text-slate-400">هزینه پیش‌فرض ماهیانه (برای دکمه‌های زیر):</span>
        <span class="text-sm font-bold text-emerald-400 font-mono"><?= Helpers::formatMoney((int)\AiService::cfg('ai_monthly_price')) ?> تومان</span>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-right">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-950/50 text-[10px] text-slate-500">
                    <th class="px-4 py-3 font-bold">نماینده</th>
                    <th class="px-4 py-3 font-bold w-28">وضعیت سرویس</th>
                    <th class="px-4 py-3 font-bold w-36">انقضا</th>
                    <th class="px-4 py-3 font-bold w-28">مجموع دریافتی</th>
                    <th class="px-4 py-3 font-bold w-72">فعال‌سازی / تمدید</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resellers as $r): $s = $subs[(int)$r['id']] ?? null;
                    $isActive = $s && $s['status'] === 'active' && $s['expires_at'] > $now;
                    $daysLeft = $isActive ? (int)ceil((strtotime((string)$s['expires_at']) - time()) / 86400) : 0;
                ?>
                <tr class="border-b border-slate-800/60 hover:bg-slate-950/40 transition">
                    <td class="px-4 py-3">
                        <div class="text-xs font-bold text-white"><?= htmlspecialchars($r['brand_name'] ?: $r['full_name'] ?: $r['username']) ?></div>
                        <div class="text-[10px] text-slate-500 font-mono">@<?= htmlspecialchars($r['username']) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($isActive): ?>
                            <span class="text-[10px] px-2 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">● فعال (<?= $daysLeft ?> روز مانده)</span>
                        <?php elseif ($s && $s['status'] === 'revoked'): ?>
                            <span class="text-[10px] px-2 py-1 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">کنسل شده</span>
                        <?php else: ?>
                            <span class="text-[10px] px-2 py-1 rounded-full bg-slate-800 text-slate-500"><?= $s ? 'انقضای گذشته' : 'ندارد' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-[11px] text-slate-400 font-mono"><?= $s && $s['expires_at'] ? substr((string)$s['expires_at'], 0, 16) : '—' ?></td>
                    <td class="px-4 py-3 text-[11px] text-emerald-400 font-mono"><?= $s && (int)$s['price_paid'] > 0 ? Helpers::formatMoney((int)$s['price_paid']) : '—' ?></td>
                    <td class="px-4 py-3">
                        <form method="POST" action="<?= $isActive ? Helpers::url('settings/ai/resellers/renew') : Helpers::url('settings/ai/resellers/activate') ?>" class="flex items-center gap-1.5 flex-wrap">
                            <?= Helpers::csrfField() ?>
                            <input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
                            <input type="number" name="days" value="30" min="1" max="365" class="w-16 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-[11px] text-white font-mono focus:border-indigo-500 focus:outline-none" title="تعداد روز">
                            <span class="text-[10px] text-slate-500">روز</span>
                            <input type="number" name="price" value="<?= (int)\AiService::cfg('ai_monthly_price') ?>" min="0" step="100000" class="w-28 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-[11px] text-white font-mono focus:border-indigo-500 focus:outline-none" title="مبلغ دریافتی (تومان)">
                            <?php if (!$isActive): ?>
                                <input type="text" name="note" placeholder="یادداشت (اختیاری)" class="w-32 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-[10px] text-white focus:border-indigo-500 focus:outline-none">
                            <?php endif; ?>
                            <button type="submit" class="px-3 py-1.5 <?= $isActive ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-emerald-600 hover:bg-emerald-700' ?> text-white rounded-lg text-[10px] font-bold flex items-center gap-1">
                                <i class="fa-solid <?= $isActive ? 'fa-rotate-right' : 'fa-toggle-on' ?>"></i>
                                <?= $isActive ? 'تمدید' : 'فعال‌سازی' ?>
                            </button>
                            <?php if ($s): ?>
                                <button type="submit" formaction="<?= Helpers::url('settings/ai/resellers/revoke') ?>" formnovalidate
                                        onclick="return confirm('غیرفعال‌سازی سرویس AI این نماینده؟ (تمدیدها هم باطل می‌شود)');"
                                        class="px-2.5 py-1.5 bg-slate-800 hover:bg-rose-900/50 text-rose-300 rounded-lg text-[10px] border border-slate-700"><i class="fa-solid fa-ban"></i></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($resellers)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-xs text-slate-500">نماینده‌ای ثبت نشده است.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-[11px] text-slate-500 leading-relaxed">
        <i class="fa-solid fa-circle-info text-slate-600"></i>
        نحوه کار: نماینده از صفحه «دستیار هوشمند» در پنل خودش درخواست می‌دهد (تیکت «درخواست خرید سرویس هوش مصنوعی» ساخته می‌شود) و اعلان به سوپرگروه می‌افتد. شما پس از دریافت پرداخت، در همین صفحه سرویس را با روز دلخواه (مثلاً ۳۰) و مبلغ فعال می‌کنید.
        با رسیدن به تاریخ انقضا، سرویس <b class="text-slate-300">خودکار</b> خاموش می‌شود و اعلان انقضا در سوپرگروه ثبت می‌شود.
    </p>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

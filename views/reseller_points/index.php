<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="relative overflow-hidden bg-gradient-to-br from-amber-900/20 via-slate-900 to-yellow-900/20 border border-amber-500/20 rounded-[1.5rem] p-6">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-amber-600/10 rounded-full blur-[60px]"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
            <div>
                <h1 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-500 flex items-center justify-center text-white"><i class="fa-solid fa-trophy"></i></span>
                    سیستم امتیاز و وفاداری نمایندگان
                    <span class="text-[10px] bg-amber-500/20 text-amber-300 px-2.5 py-1 rounded-full border border-amber-500/30">LOYALTY ULTRA v7.1</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">هر فروش = امتیاز - سطح بالاتر = تخفیف بیشتر + جایزه</p>
            </div>
            <div class="flex gap-2 text-[11px]">
                <div class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-center"><div class="text-amber-300 font-black">🥉 برنز</div><div class="text-slate-400">0-99 امتیاز - 5% تخفیف</div></div>
                <div class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-center"><div class="text-slate-300 font-black">🥈 نقره</div><div class="text-slate-400">100-499 - 10%</div></div>
                <div class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-center"><div class="text-amber-400 font-black">🥇 طلا</div><div class="text-slate-400">500-1999 - 15%</div></div>
                <div class="px-3 py-2 bg-cyan-900/30 border border-cyan-500/30 rounded-xl text-center"><div class="text-cyan-300 font-black">💎 الماس</div><div class="text-slate-400">2000+ - 20%</div></div>
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-black text-sm text-white">🏆 جدول امتیازات</h3>
            <span class="text-[11px] text-slate-400"><?= count($allResellers) ?> نماینده</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr><th class="p-3 text-right">رتبه</th><th class="p-3 text-right">نماینده</th><th class="p-3 text-center">امتیاز</th><th class="p-3 text-center">سطح</th><th class="p-3 text-center">فروش کل</th><th class="p-3 text-center">کلاینت</th><th class="p-3 text-center">موجودی</th><th class="p-3 text-center">عملیات</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allResellers as $idx=>$r):
                        $lvl = ResellerPointsController::calculateLevel((int)$r['points']);
                        $badgeColor = $lvl['level']==='diamond'?'bg-cyan-500/20 text-cyan-300 border-cyan-500/30':($lvl['level']==='gold'?'bg-amber-500/20 text-amber-300 border-amber-500/30':($lvl['level']==='silver'?'bg-slate-500/20 text-slate-300 border-slate-500/30':'bg-orange-500/20 text-orange-300 border-orange-500/30'));
                    ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30 <?= $idx<3?'bg-amber-950/10':'' ?>">
                        <td class="p-3 font-black text-white"><?= $idx+1 ?><?= $idx==0?' 🥇':($idx==1?' 🥈':($idx==2?' 🥉':'')) ?></td>
                        <td class="p-3"><div class="font-bold text-white"><?= htmlspecialchars($r['full_name'] ?: $r['username']) ?></div><div class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($r['username']) ?></div></td>
                        <td class="p-3 text-center font-mono font-black text-amber-300"><?= number_format((int)$r['points']) ?></td>
                        <td class="p-3 text-center"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $badgeColor ?>"><?= $lvl['badge'] ?> <?= $lvl['title'] ?></span></td>
                        <td class="p-3 text-center font-mono text-emerald-300"><?= Helpers::formatMoney((int)($r['total_sales'] ?? 0)) ?></td>
                        <td class="p-3 text-center font-mono text-slate-300"><?= $r['total_clients'] ?? 0 ?></td>
                        <td class="p-3 text-center font-mono text-violet-300"><?= Helpers::formatMoney((int)$r['wallet_balance']) ?></td>
                        <td class="p-3 text-center">
                            <form method="POST" action="<?= Helpers::url('resellers/points/adjust') ?>" class="flex gap-1 justify-center m-0">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="reseller_id" value="<?= $r['id'] ?>">
                                <input type="number" name="points" value="10" class="w-16 bg-slate-800 border border-slate-700 rounded-lg px-2 py-1 text-white text-[11px]">
                                <button type="submit" name="action" value="add" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px]">+</button>
                                <button type="submit" name="action" value="remove" class="px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-[10px]">-</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold text-white text-sm mb-3">📖 چطور کار می‌کند؟</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px] text-slate-400 leading-relaxed">
            <div class="bg-slate-800/50 rounded-xl p-3"><div class="font-bold text-white mb-1">1. امتیاز خودکار</div>هر 10,000 تومان فروش = 1 امتیاز (سقف 50 امتیاز هر فروش). هر کلاینت جدید = 5 امتیاز اضافی. خودکار محاسبه می‌شود.</div>
            <div class="bg-slate-800/50 rounded-xl p-3"><div class="font-bold text-white mb-1">2. سطح و تخفیف</div>برنز 5% → نقره 10% → طلا 15% → الماس 20% تخفیف روی هر پلن. سطح بالاتر = سود بیشتر.</div>
            <div class="bg-slate-800/50 rounded-xl p-3"><div class="font-bold text-white mb-1">3. انگیزه فروش</div>نمایندگان برای رسیدن به الماس بیشتر می‌فروشند. جدول امتیازات عمومی است و رقابت ایجاد می‌کند.</div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

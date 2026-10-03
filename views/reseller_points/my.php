<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="relative overflow-hidden bg-gradient-to-br from-violet-900/20 via-slate-900 to-amber-900/20 border border-violet-500/20 rounded-[1.5rem] p-6 text-center">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-violet-600/10 rounded-full blur-[60px]"></div>
        <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-amber-600/10 rounded-full blur-[60px]"></div>
        <div class="relative z-10">
            <div class="w-20 h-20 rounded-[1.5rem] bg-gradient-to-br from-amber-500 to-yellow-500 mx-auto flex items-center justify-center text-3xl shadow-xl mb-4"><?= $levelInfo['badge'] ?></div>
            <h1 class="text-2xl font-black text-white">سطح <?= $levelInfo['title'] ?> - <?= number_format((int)$my['points']) ?> امتیاز</h1>
            <p class="text-sm text-slate-400 mt-2">تخفیف فعلی شما: <span class="text-emerald-400 font-black"><?= $levelInfo['discount'] ?>%</span> روی هر پلن</p>
            
            <div class="mt-6 max-w-md mx-auto">
                <div class="flex justify-between text-[11px] text-slate-400 mb-2 font-mono">
                    <span><?= $my['points'] ?> امتیاز</span>
                    <span><?= $isMax ? 'حداکثر' : $nextLevel.' امتیاز تا سطح بعدی' ?></span>
                </div>
                <div class="w-full h-3 bg-slate-800 rounded-full overflow-hidden border border-slate-700">
                    <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full transition-all duration-1000" style="width: <?= $progress ?>%"></div>
                </div>
                <div class="text-[11px] text-slate-500 mt-2"><?= $progress ?>% تا سطح بعدی</div>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-3 max-w-md mx-auto">
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-3"><div class="text-[10px] text-slate-400">امتیاز</div><div class="font-black text-amber-300 text-lg"><?= number_format((int)$my['points']) ?></div></div>
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-3"><div class="text-[10px] text-slate-400">فروش کل</div><div class="font-black text-emerald-300 text-sm"><?= Helpers::formatMoney((int)$my['total_sales']) ?></div></div>
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-3"><div class="text-[10px] text-slate-400">کلاینت</div><div class="font-black text-white text-lg"><?= (int)$my['total_clients'] ?></div></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-3 text-center text-[11px]">
        <div class="p-3 rounded-xl border <?= $levelInfo['level']==='bronze'?'bg-orange-500/20 border-orange-500/30 text-orange-300':'bg-slate-800 border-slate-700 text-slate-400' ?>"><div class="text-lg">🥉</div><div class="font-bold">برنز</div><div>5% تخفیف</div></div>
        <div class="p-3 rounded-xl border <?= $levelInfo['level']==='silver'?'bg-slate-500/20 border-slate-500/30 text-slate-300':'bg-slate-800 border-slate-700 text-slate-400' ?>"><div class="text-lg">🥈</div><div class="font-bold">نقره</div><div>10% تخفیف</div></div>
        <div class="p-3 rounded-xl border <?= $levelInfo['level']==='gold'?'bg-amber-500/20 border-amber-500/30 text-amber-300':'bg-slate-800 border-slate-700 text-slate-400' ?>"><div class="text-lg">🥇</div><div class="font-bold">طلا</div><div>15% تخفیف</div></div>
        <div class="p-3 rounded-xl border <?= $levelInfo['level']==='diamond'?'bg-cyan-500/20 border-cyan-500/30 text-cyan-300':'bg-slate-800 border-slate-700 text-slate-400' ?>"><div class="text-lg">💎</div><div class="font-bold">الماس</div><div>20% تخفیف</div></div>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800"><h3 class="font-black text-sm text-white">🏆 جدول برترین‌ها</h3></div>
        <div class="divide-y divide-slate-800/50">
            <?php foreach ($leaderboard as $idx=>$lb): $isMe = $lb['reseller_id']==Auth::id(); ?>
            <div class="p-3 flex items-center justify-between <?= $isMe?'bg-violet-950/30 border border-violet-500/20 rounded-xl m-2':'' ?>">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-xl bg-slate-800 flex items-center justify-center font-black text-[11px] text-white"><?= $idx+1 ?></span>
                    <div>
                        <div class="font-bold text-white text-xs"><?= htmlspecialchars($lb['full_name'] ?: $lb['username']) ?> <?= $isMe?'(شما)':'' ?></div>
                        <div class="text-[10px] text-slate-400"><?= number_format((int)$lb['points']) ?> امتیاز</div>
                    </div>
                </div>
                <span class="text-[11px] font-mono text-amber-300"><?= $lb['level'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="glass rounded-2xl p-5">
        <h3 class="font-bold text-white text-sm mb-3">💡 چطور امتیاز بگیرم؟</h3>
        <ul class="space-y-2 text-xs text-slate-400 leading-relaxed">
            <li>• هر 10,000 تومان فروش = 1 امتیاز (تا 50 امتیاز هر فروش)</li>
            <li>• هر کلاینت جدید = 5 امتیاز اضافی</li>
            <li>• سطح بالاتر = تخفیف بیشتر روی خرید پلن (الماس 20%)</li>
            <li>• هر روز 10 امتیاز جایزه ورود روزانه (به زودی)</li>
        </ul>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

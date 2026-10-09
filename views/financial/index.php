<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header ULTRA -->
    <div class="relative overflow-hidden bg-gradient-to-br from-emerald-900/20 via-slate-900 to-teal-900/20 border border-emerald-500/20 rounded-[1.5rem] p-6">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-emerald-600/10 rounded-full blur-[60px]"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
            <div>
                <h1 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-600 flex items-center justify-center text-white"><i class="fa-solid fa-chart-pie"></i></span>
                    گزارش مالی پیشرفته
                    <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2.5 py-1 rounded-full border border-emerald-500/30">PRO v4.0.46</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">درآمد، سود خالص، هزینه سرورها، پرفروش‌ترین پلن‌ها - همه در یک نگاه</p>
            </div>
            <div class="flex gap-2">
                <a href="<?= Helpers::url('financial/export') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold border border-slate-700 flex items-center gap-2"><i class="fa-solid fa-file-csv"></i> خروجی Excel</a>
                <a href="<?= Helpers::url('monitoring') ?>" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold flex items-center gap-2"><i class="fa-solid fa-heart-pulse"></i> مانیتورینگ</a>
            </div>
        </div>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="glass-card rounded-2xl p-5 border-emerald-800/30">
            <div class="text-[11px] text-slate-400 mb-1">کل درآمد</div>
            <div class="text-xl font-black text-emerald-400 font-mono"><?= Helpers::formatMoney($totalIncome) ?></div>
            <div class="text-[10px] text-slate-500 mt-1">مجموع واریزی‌ها</div>
        </div>
        <div class="glass-card rounded-2xl p-5 border-rose-800/30">
            <div class="text-[11px] text-slate-400 mb-1">کل هزینه (خرید پلن)</div>
            <div class="text-xl font-black text-rose-400 font-mono"><?= Helpers::formatMoney($totalSpent) ?></div>
            <div class="text-[10px] text-slate-500 mt-1">مجموع خریدها</div>
        </div>
        <div class="glass-card rounded-2xl p-5 border-violet-800/30 bg-gradient-to-br from-violet-950/20 to-indigo-950/20">
            <div class="text-[11px] text-violet-300 mb-1">سود تخمینی (45%)</div>
            <div class="text-xl font-black text-violet-300 font-mono"><?= Helpers::formatMoney($estimatedProfit) ?></div>
            <div class="text-[10px] text-slate-500 mt-1">سود خالص شما</div>
        </div>
        <div class="glass-card rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">موجودی نمایندگان</div>
            <div class="text-xl font-black text-white font-mono"><?= Helpers::formatMoney($walletTotal) ?></div>
            <div class="text-[10px] text-slate-500 mt-1"><?= $resellerCount ?> نماینده فعال</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 glass-card rounded-2xl p-5">
            <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-line text-emerald-400"></i> درآمد 12 ماه اخیر</h3>
            <div class="h-64"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-column text-cyan-400"></i> درآمد 30 روز اخیر</h3>
            <div class="h-64"><canvas id="dailyChart"></canvas></div>
        </div>
    </div>

    <!-- Servers Profitability -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-server text-violet-400"></i> سودآوری سرورها</h3>
                <span class="text-[10px] text-slate-400">بر اساس کلاینت فعال</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-800/50 text-slate-400">
                        <tr><th class="p-3 text-right">سرور</th><th class="p-3 text-center">فعال</th><th class="p-3 text-center">کل</th><th class="p-3 text-center">درایور</th><th class="p-3 text-center">وضعیت</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $s): ?>
                        <tr class="border-t border-slate-800/50 hover:bg-slate-800/30">
                            <td class="p-3 font-bold text-white"><?= htmlspecialchars($s['name']) ?></td>
                            <td class="p-3 text-center font-mono text-emerald-400"><?= $s['active_clients'] ?></td>
                            <td class="p-3 text-center font-mono text-slate-300"><?= $s['total_clients'] ?></td>
                            <td class="p-3 text-center"><span class="px-2 py-0.5 bg-slate-800 rounded-full text-[10px]"><?= htmlspecialchars($s['driver']) ?></span></td>
                            <td class="p-3 text-center"><span class="w-2 h-2 bg-emerald-400 rounded-full inline-block animate-pulse"></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-800">
                <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-crown text-amber-400"></i> پرفروش‌ترین پلن‌ها</h3>
            </div>
            <div class="p-4 space-y-3">
                <?php foreach ($topPlans as $tp): ?>
                <div class="flex items-center justify-between p-3 bg-slate-800/40 border border-slate-700/50 rounded-xl">
                    <div>
                        <div class="font-bold text-white text-xs"><?= htmlspecialchars($tp['title']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($tp['server_group']) ?> • <?= $tp['sold'] ?> فروش</div>
                    </div>
                    <div class="text-emerald-300 font-mono font-bold text-xs"><?= Helpers::formatMoney((int)($tp['revenue'] ?? 0)) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topPlans)): ?>
                <div class="text-center py-6 text-slate-400 text-xs">داده‌ای وجود ندارد</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const monthly = <?= json_encode($monthly) ?>;
    const daily = <?= json_encode($daily) ?>;
    
    // Monthly chart
    const ctx1 = document.getElementById('monthlyChart');
    if(ctx1){
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: monthly.map(m=>m.ym),
                datasets: [
                    { label:'درآمد', data: monthly.map(m=>m.income), backgroundColor:'rgba(16,185,129,0.7)', borderRadius:8 },
                    { label:'هزینه', data: monthly.map(m=>m.spent), backgroundColor:'rgba(244,63,94,0.5)', borderRadius:8 }
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{ labels:{ color:'#94a3b8', font:{family:'Vazirmatn', size:11} } } },
                scales:{ x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} }, y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} } }
            }
        });
    }
    // Daily chart
    const ctx2 = document.getElementById('dailyChart');
    if(ctx2){
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: daily.map(d=>d.d),
                datasets: [{ label:'درآمد روزانه', data: daily.map(d=>d.income), borderColor:'#22d3ee', backgroundColor:'rgba(34,211,238,0.15)', fill:true, tension:0.4, borderWidth:2 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ display:false }, y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} } } }
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

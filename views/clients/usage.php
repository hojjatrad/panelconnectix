<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-black text-white flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center text-white"><i class="fa-solid fa-chart-area"></i></span>
            تاریخچه مصرف: <?= htmlspecialchars($client['username']) ?>
            <span class="text-[10px] bg-violet-500/20 text-violet-300 px-2.5 py-1 rounded-full border border-violet-500/30">v4.0.45</span>
        </h1>
        <a href="<?= Helpers::url('clients') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold border border-slate-700">بازگشت</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="glass rounded-2xl p-5 text-center">
            <div class="text-[11px] text-slate-400">مصرف فعلی</div>
            <div class="text-xl font-black text-violet-400 font-mono"><?= Helpers::formatBytes((int)$client['traffic_used_bytes']) ?></div>
            <div class="text-[10px] text-slate-500 mt-1">از <?= Helpers::formatBytes((int)$client['traffic_limit_bytes']) ?></div>
        </div>
        <div class="glass rounded-2xl p-5 text-center">
            <div class="text-[11px] text-slate-400">باقیمانده</div>
            <div class="text-xl font-black text-emerald-400 font-mono"><?= Helpers::formatBytes(max(0, (int)$client['traffic_limit_bytes'] - (int)$client['traffic_used_bytes'])) ?></div>
            <div class="text-[10px] text-slate-500 mt-1"><?= round(((int)$client['traffic_used_bytes']/(int)max(1,$client['traffic_limit_bytes']))*100,1) ?>% مصرف</div>
        </div>
        <div class="glass rounded-2xl p-5 text-center">
            <div class="text-[11px] text-slate-400">انقضا</div>
            <div class="text-sm font-bold text-amber-300"><?= Helpers::daysRemaining($client['expire_at']) ?></div>
            <div class="text-[10px] text-slate-500 mt-1 font-mono"><?= htmlspecialchars($client['expire_at'] ?? '-') ?></div>
        </div>
    </div>

    <div class="glass rounded-2xl p-5">
        <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-line text-cyan-400"></i> نمودار مصرف 30 روز اخیر</h3>
        <div class="h-72"><canvas id="usageChart"></canvas></div>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800"><h3 class="font-bold text-white text-sm">📋 لاگ روزانه</h3></div>
        <div class="overflow-x-auto max-h-[400px] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400 sticky top-0"><tr><th class="p-3 text-right">تاریخ</th><th class="p-3 text-center">مصرف</th><th class="p-3 text-center">درصد</th></tr></thead>
                <tbody>
                    <?php foreach ($usageLogs as $log): 
                        $pct = (int)$client['traffic_limit_bytes']>0 ? round(($log['used_bytes']/(int)$client['traffic_limit_bytes'])*100,1) : 0;
                    ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($log['recorded_at']) ?></td>
                        <td class="p-3 text-center font-mono text-white"><?= Helpers::formatBytes((int)$log['used_bytes']) ?></td>
                        <td class="p-3 text-center"><div class="w-20 h-1.5 bg-slate-800 rounded-full mx-auto overflow-hidden"><div class="h-full bg-violet-500" style="width: <?= min(100,$pct) ?>%"></div></div><span class="text-[10px] font-mono text-slate-400 mt-1 block"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const logs = <?= json_encode($usageLogs) ?>;
    const ctx = document.getElementById('usageChart');
    if(!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: logs.map(l=>l.recorded_at.split(' ')[0]),
            datasets: [{
                label: 'مصرف (GB)',
                data: logs.map(l=> (l.used_bytes/1073741824).toFixed(2)),
                borderColor: '#8b5cf6',
                backgroundColor: (c)=>{ const g=c.chart.ctx.createLinearGradient(0,0,0,250); g.addColorStop(0,'rgba(139,92,246,0.3)'); g.addColorStop(1,'rgba(139,92,246,0)'); return g; },
                fill:true, tension:0.4, borderWidth:2.5, pointRadius:3, pointBackgroundColor:'#8b5cf6'
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{ x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} }, y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} } }
        }
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

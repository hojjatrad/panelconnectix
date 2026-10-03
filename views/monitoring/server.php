<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-black text-white flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-600 flex items-center justify-center text-white"><i class="fa-solid fa-chart-line"></i></span>
            مانیتورینگ سرور: <?= htmlspecialchars($server['name']) ?>
            <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2.5 py-1 rounded-full border border-emerald-500/30">ULTRA v7.1 • CPU/RAM</span>
        </h1>
        <a href="<?= Helpers::url('monitoring') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold border border-slate-700">بازگشت</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="glass rounded-2xl p-5 text-center">
            <div class="text-3xl font-black text-emerald-400"><?= $stats['uptime_percent'] ?>%</div>
            <div class="text-xs text-slate-400 mt-1">آپتایم 24 ساعت</div>
            <div class="text-[11px] text-slate-500 mt-2"><?= $stats['online_checks'] ?> / <?= $stats['total_checks'] ?> آنلاین</div>
            <div class="w-full h-1.5 bg-slate-800 rounded-full mt-3 overflow-hidden"><div class="h-full bg-emerald-500" style="width: <?= $stats['uptime_percent'] ?>%"></div></div>
        </div>
        <div class="glass rounded-2xl p-5 text-center">
            <div class="text-3xl font-black text-cyan-400"><?= $stats['avg_latency'] ?>ms</div>
            <div class="text-xs text-slate-400 mt-1">میانگین تاخیر</div>
            <div class="text-[11px] text-slate-500 mt-2">Min: <?= $stats['min_latency'] ?>ms / Max: <?= $stats['max_latency'] ?>ms</div>
        </div>
        <div class="glass rounded-2xl p-5 text-center border-violet-800/20">
            <div class="text-3xl font-black text-violet-400"><?= $stats7d['uptime_percent'] ?>%</div>
            <div class="text-xs text-slate-400 mt-1">آپتایم 7 روز</div>
            <div class="text-[11px] text-slate-500 mt-2">هفته گذشته</div>
        </div>
        <?php
        $latestSys = null;
        try { $latestSys = \ServerMonitor::getLatestSystemStats((int)$server['id']); } catch (Throwable $e) {}
        ?>
        <div class="glass rounded-2xl p-5 text-center border-amber-800/20">
            <div class="flex justify-center gap-3 mb-2">
                <div class="text-center"><div class="text-lg font-black text-amber-400"><?= $latestSys ? round((float)$latestSys['cpu_percent']) : '—' ?>%</div><div class="text-[10px] text-slate-400">CPU</div></div>
                <div class="text-center"><div class="text-lg font-black text-cyan-400"><?= $latestSys ? round((float)$latestSys['ram_percent']) : '—' ?>%</div><div class="text-[10px] text-slate-400">RAM</div></div>
            </div>
            <div class="text-xs text-slate-400">کاربران آنلاین</div>
            <div class="text-lg font-black text-white"><?= $latestSys ? (int)$latestSys['online_users'] : '—' ?></div>
            <div class="text-[10px] text-slate-500 mt-1">آخرین چک: <?= $latestSys ? htmlspecialchars($latestSys['checked_at']) : '-' ?></div>
        </div>
    </div>

    <!-- CPU/RAM Chart ULTRA -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="glass rounded-2xl p-5">
            <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-microchip text-amber-400"></i> نمودار CPU و RAM (24 ساعت)</h3>
            <div class="h-64"><canvas id="sysChart"></canvas></div>
        </div>
        <div class="glass rounded-2xl p-5">
            <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-wave-square text-emerald-400"></i> نمودار تاخیر و آپتایم</h3>
            <div class="h-64"><canvas id="latencyChart"></canvas></div>
        </div>
    </div>

    <div class="glass rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-white font-black text-sm">📋 لاگ‌های اخیر (100 مورد)</h3>
            <span class="text-[11px] text-slate-400">آخرین بررسی‌ها</span>
        </div>
        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400 sticky top-0">
                    <tr><th class="p-3 text-right">زمان</th><th class="p-3 text-center">وضعیت</th><th class="p-3 text-center">تاخیر</th><th class="p-3 text-right">خطا</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($log['checked_at']) ?></td>
                        <td class="p-3 text-center"><?php $cls = $log['status']==='online'?'bg-emerald-900/30 text-emerald-300':($log['status']==='offline'?'bg-rose-900/30 text-rose-300':'bg-amber-900/30 text-amber-300'); ?><span class="px-2 py-1 rounded-full text-[10px] <?= $cls ?>"><?= htmlspecialchars($log['status']) ?></span></td>
                        <td class="p-3 text-center font-mono"><?= $log['latency_ms'] ?>ms</td>
                        <td class="p-3 text-rose-300 max-w-xs truncate"><?= htmlspecialchars($log['error_message'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const sysLogs = <?= json_encode(array_map(fn($l)=>['t'=>substr($l['checked_at'],11,5),'cpu'=>(float)($l['cpu_percent']??0),'ram'=>(float)($l['ram_percent']??0)], \ServerMonitor::getSystemStats((int)$server['id'],24))) ?>;
    const latencyLogs = <?= json_encode(array_map(fn($l)=>['t'=>substr($l['checked_at'],11,5),'lat'=>(int)$l['latency_ms'],'status'=>$l['status']], $logs)) ?>;

    // System chart
    const ctx1 = document.getElementById('sysChart');
    if(ctx1 && sysLogs.length){
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: sysLogs.map(l=>l.t),
                datasets: [
                    { label:'CPU %', data: sysLogs.map(l=>l.cpu), borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.1)', fill:true, tension:0.4, borderWidth:2 },
                    { label:'RAM %', data: sysLogs.map(l=>l.ram), borderColor:'#06b6d4', backgroundColor:'rgba(6,182,214,0.1)', fill:true, tension:0.4, borderWidth:2 }
                ]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ labels:{ color:'#94a3b8', font:{size:11} } } }, scales:{ x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} }, y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}}, min:0, max:100 } } }
        });
    } else if(ctx1){
        ctx1.parentElement.innerHTML = '<div class="h-64 flex items-center justify-center text-slate-500 text-xs">هنوز داده CPU/RAM وجود ندارد - بعد از اولین کرون مانیتورینگ نمایش داده می‌شود</div>';
    }

    // Latency chart
    const ctx2 = document.getElementById('latencyChart');
    if(ctx2 && latencyLogs.length){
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: latencyLogs.map(l=>l.t).reverse(),
                datasets: [{ label:'تاخیر ms', data: latencyLogs.map(l=>l.lat).reverse(), borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.1)', fill:true, tension:0.4, borderWidth:2, pointRadius:2 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} }, y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#64748b', font:{size:10}} } } }
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

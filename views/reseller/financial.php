<?php
$pageTitle = 'گزارش مالی اختصاصی - v4.0.46';
require __DIR__ . '/../layout/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-emerald-400"></i>
                <span>گزارش مالی ULTRA - درآمد و سود</span>
                <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full text-[10px]">RESYNC v4.0.46</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">نمای کامل تراکنش‌ها، درآمد، هزینه و سود تخمینی شما</p>
        </div>
        <a href="<?= Helpers::url('reseller/invoice?month='.date('Y-m')) ?>" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold"><i class="fa-solid fa-file-invoice"></i> صورت‌حساب ماهانه</a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-emerald-600/20 to-emerald-900/20 border border-emerald-500/30 rounded-2xl p-5">
            <div class="text-[11px] text-emerald-300 mb-1">کل درآمد</div>
            <div class="text-xl font-bold text-white"><?= number_format($totalIncome) ?> <span class="text-xs">تومان</span></div>
            <div class="text-[10px] text-emerald-400 mt-1"><i class="fa-solid fa-arrow-trend-up"></i> مجموع واریزی‌ها</div>
        </div>
        <div class="bg-gradient-to-br from-amber-600/20 to-amber-900/20 border border-amber-500/30 rounded-2xl p-5">
            <div class="text-[11px] text-amber-300 mb-1">کل هزینه</div>
            <div class="text-xl font-bold text-white"><?= number_format($totalSpent) ?> <span class="text-xs">تومان</span></div>
            <div class="text-[10px] text-amber-400 mt-1"><i class="fa-solid fa-arrow-trend-down"></i> مجموع خریدها</div>
        </div>
        <div class="bg-gradient-to-br from-purple-600/20 to-purple-900/20 border border-purple-500/30 rounded-2xl p-5">
            <div class="text-[11px] text-purple-300 mb-1">سود تخمینی 45%</div>
            <div class="text-xl font-bold text-white"><?= number_format($profit) ?> <span class="text-xs">تومان</span></div>
            <div class="text-[10px] text-purple-400 mt-1">برآورد سود خالص شما</div>
        </div>
        <div class="bg-gradient-to-br from-cyan-600/20 to-cyan-900/20 border border-cyan-500/30 rounded-2xl p-5">
            <div class="text-[11px] text-cyan-300 mb-1">کلاینت‌ها</div>
            <div class="text-xl font-bold text-white"><?= $activeClients ?> / <?= $totalClients ?></div>
            <div class="text-[10px] text-cyan-400 mt-1">فعال / کل</div>
        </div>
    </div>

    <!-- Chart -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-bar text-purple-400"></i> نمودار مالی 6 ماه اخیر</h3>
        <canvas id="financialChart" height="80"></canvas>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-list text-amber-400"></i> آخرین تراکنش‌ها</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-[11px] text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="text-right py-2">تاریخ</th>
                        <th class="text-right py-2">نوع</th>
                        <th class="text-right py-2">مبلغ</th>
                        <th class="text-right py-2">توضیحات</th>
                        <th class="text-right py-2">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php foreach ($transactions as $tx): ?>
                    <tr class="hover:bg-slate-800/30">
                        <td class="py-2.5 text-slate-300"><?= htmlspecialchars($tx['created_at']) ?></td>
                        <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] <?= $tx['amount']>0?'bg-emerald-500/20 text-emerald-400':'bg-amber-500/20 text-amber-400' ?>"><?= $tx['amount']>0?'واریز':'خرید' ?></span></td>
                        <td class="py-2.5 font-bold <?= $tx['amount']>0?'text-emerald-400':'text-amber-400' ?>"><?= number_format(abs($tx['amount'])) ?> تومان</td>
                        <td class="py-2.5 text-slate-400 max-w-[200px] truncate"><?= htmlspecialchars($tx['description']??'') ?></td>
                        <td class="py-2.5"><span class="px-2 py-0.5 bg-slate-800 text-slate-300 rounded-full text-[10px]"><?= htmlspecialchars($tx['status']??'completed') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">تراکنشی یافت نشد</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const monthlyData = <?= json_encode($monthly) ?>;
if (monthlyData.length>0 && typeof Chart!=='undefined') {
    const ctx = document.getElementById('financialChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(r=>r.ym),
            datasets: [
                { label: 'درآمد', data: monthlyData.map(r=>parseInt(r.income)), backgroundColor: 'rgba(16,185,129,0.6)', borderColor: 'rgb(16,185,129)', borderWidth:1 },
                { label: 'هزینه', data: monthlyData.map(r=>parseInt(r.spent)), backgroundColor: 'rgba(245,158,11,0.6)', borderColor: 'rgb(245,158,11)', borderWidth:1 }
            ]
        },
        options: { responsive:true, plugins:{legend:{labels:{color:'#cbd5e1', font:{family:'Vazirmatn'}}}} , scales:{x:{ticks:{color:'#94a3b8'}}, y:{ticks:{color:'#94a3b8'}}} }
    });
}
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>

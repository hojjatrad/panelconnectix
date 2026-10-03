<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white flex items-center gap-3">
            <i class="fa-solid fa-chart-line text-emerald-400"></i>
            لاگ‌های سرور: <?= htmlspecialchars($server['name']) ?>
        </h1>
        <a href="<?= Helpers::url('monitoring') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs">بازگشت</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-3xl font-bold text-emerald-400"><?= $stats['uptime_percent'] ?>%</div>
            <div class="text-xs text-slate-400 mt-1">آپتایم 24 ساعت</div>
            <div class="text-[11px] text-slate-500 mt-2"><?= $stats['online_checks'] ?> / <?= $stats['total_checks'] ?> چک آنلاین</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-3xl font-bold text-cyan-400"><?= $stats['avg_latency'] ?>ms</div>
            <div class="text-xs text-slate-400 mt-1">میانگین تاخیر</div>
            <div class="text-[11px] text-slate-500 mt-2">Min: <?= $stats['min_latency'] ?>ms / Max: <?= $stats['max_latency'] ?>ms</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-3xl font-bold text-purple-400"><?= $stats7d['uptime_percent'] ?>%</div>
            <div class="text-xs text-slate-400 mt-1">آپتایم 7 روز</div>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-white font-bold text-sm">📋 لاگ‌های اخیر (100 مورد)</h3>
        </div>
        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400 sticky top-0">
                    <tr>
                        <th class="p-3 text-right">زمان</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-center">تاخیر</th>
                        <th class="p-3 text-right">خطا</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr class="border-t border-slate-800/50">
                        <td class="p-3 font-mono text-slate-400"><?= htmlspecialchars($log['checked_at']) ?></td>
                        <td class="p-3 text-center">
                            <?php
                            $cls = $log['status'] === 'online' ? 'bg-emerald-900/30 text-emerald-300' : ($log['status'] === 'offline' ? 'bg-rose-900/30 text-rose-300' : 'bg-amber-900/30 text-amber-300');
                            ?>
                            <span class="px-2 py-1 rounded-full text-[10px] <?= $cls ?>"><?= htmlspecialchars($log['status']) ?></span>
                        </td>
                        <td class="p-3 text-center font-mono"><?= $log['latency_ms'] ?>ms</td>
                        <td class="p-3 text-rose-300 max-w-xs truncate"><?= htmlspecialchars($log['error_message'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

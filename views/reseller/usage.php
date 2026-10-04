<?php
$pageTitle = 'مصرف و تاریخچه کلاینت‌ها - ULTRA v7.2';
require __DIR__ . '/../layout/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-area text-cyan-400"></i>
                <span>مصرف و تاریخچه - ULTRA</span>
                <span class="px-2 py-0.5 bg-cyan-500/20 text-cyan-400 rounded-full text-[10px]">RESYNC v7.2</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">نمودار مصرف، تاریخچه اتصال و ترافیک کلاینت‌های شما</p>
        </div>
        <a href="<?= Helpers::url('clients') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs"><i class="fa-solid fa-users"></i> لیست کلاینت‌ها</a>
    </div>

    <!-- Clients Usage Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-users-gear text-purple-400"></i> مصرف کلاینت‌ها (Top 50)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-[11px] text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="text-right py-2">کاربر</th>
                        <th class="text-right py-2">پلن</th>
                        <th class="text-right py-2">سرور</th>
                        <th class="text-right py-2">مصرف / کل</th>
                        <th class="text-right py-2">درصد</th>
                        <th class="text-right py-2">انقضا</th>
                        <th class="text-right py-2">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php foreach ($clients as $c): 
                        $used = (int)$c['traffic_used_bytes'];
                        $limit = (int)$c['traffic_limit_bytes'];
                        $pct = $limit>0 ? round($used/$limit*100) : 0;
                    ?>
                    <tr class="hover:bg-slate-800/30">
                        <td class="py-2.5 font-bold text-white"><?= htmlspecialchars($c['username']) ?></td>
                        <td class="py-2.5 text-slate-400"><?= htmlspecialchars($c['plan_title']??'سفارشی') ?></td>
                        <td class="py-2.5 text-slate-400"><?= htmlspecialchars($c['server_name']??'-') ?></td>
                        <td class="py-2.5 text-slate-300"><?= Helpers::formatBytes($used) ?> / <?= Helpers::formatBytes($limit) ?></td>
                        <td class="py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-slate-800 rounded-full h-1.5"><div class="h-1.5 rounded-full <?= $pct>90?'bg-red-500':($pct>70?'bg-amber-500':'bg-emerald-500') ?>" style="width: <?= min(100,$pct) ?>%"></div></div>
                                <span class="text-[11px] text-white"><?= $pct ?>%</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-slate-400"><?= htmlspecialchars($c['expire_at']??'نامحدود') ?></td>
                        <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] <?= $c['status']=='active'?'bg-emerald-500/20 text-emerald-400':'bg-red-500/20 text-red-400' ?>"><?= $c['status']=='active'?'فعال':'منقضی' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clients)): ?>
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">کلاینتی یافت نشد</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Usage Logs -->
    <?php if (!empty($usageLogs)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-clock-rotate-left text-amber-400"></i> لاگ‌های مصرف اخیر</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-[11px] text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="text-right py-2">کاربر</th>
                        <th class="text-right py-2">مصرف روز</th>
                        <th class="text-right py-2">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php foreach (array_slice($usageLogs,0,50) as $log): ?>
                    <tr class="hover:bg-slate-800/30">
                        <td class="py-2 text-white"><?= htmlspecialchars($log['username']??'') ?></td>
                        <td class="py-2 text-slate-300"><?= Helpers::formatBytes((int)($log['bytes_used']??0)) ?></td>
                        <td class="py-2 text-slate-400"><?= htmlspecialchars($log['created_at']??'') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i class="fa-solid fa-heart-pulse"></i></span>
                    مانیتورینگ زنده سرورها + صف همگام‌سازی
                    <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-1 rounded-full border border-emerald-500/30">PRO MAX v6.9.0</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">بررسی هر 5 دقیقه + آلارم تلگرام + صف ضد 520 + دامنه چرخشی</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="<?= Helpers::url('monitoring/check-now') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-rotate"></i> بررسی همه سرورها الان
                    </button>
                </form>
                <form method="POST" action="<?= Helpers::url('monitoring/check-domains') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs font-bold">🌐 چک دامنه‌ها</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $online = 0; $offline = 0; $slow = 0;
        foreach ($servers as $s) {
            $st = $s['health_status'] ?? 'online';
            if ($st === 'online') $online++;
            elseif ($st === 'offline') $offline++;
            else $slow++;
        }
        ?>
        <div class="bg-slate-900 border border-emerald-800/30 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-emerald-400"><?= $online ?></div>
            <div class="text-[11px] text-slate-400">آنلاین</div>
        </div>
        <div class="bg-slate-900 border border-rose-800/30 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-rose-400"><?= $offline ?></div>
            <div class="text-[11px] text-slate-400">آفلاین</div>
        </div>
        <div class="bg-slate-900 border border-amber-800/30 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-amber-400"><?= $slow ?></div>
            <div class="text-[11px] text-slate-400">کند</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold text-white"><?= count($servers) ?></div>
            <div class="text-[11px] text-slate-400">کل سرورها</div>
        </div>
    </div>

    <!-- Servers Health Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-white font-bold text-sm flex items-center gap-2"><i class="fa-solid fa-server text-cyan-400"></i> وضعیت سرورها (24 ساعت اخیر)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right">سرور</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-center">تاخیر</th>
                        <th class="p-3 text-center">آپتایم 24h</th>
                        <th class="p-3 text-center">چک‌ها</th>
                        <th class="p-3 text-right">آخرین خطا</th>
                        <th class="p-3 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $s):
                        $st = $s['health_status'] ?? 'online';
                        $stat = $stats[$s['id']] ?? ['uptime_percent'=>0,'avg_latency'=>0,'total_checks'=>0];
                        $statusCls = $st === 'online' ? 'bg-emerald-900/30 text-emerald-300 border-emerald-800/30' : ($st === 'offline' ? 'bg-rose-900/30 text-rose-300 border-rose-800/30' : 'bg-amber-900/30 text-amber-300 border-amber-800/30');
                        $statusLabel = $st === 'online' ? '✅ آنلاین' : ($st === 'offline' ? '❌ آفلاین' : '⚠️ کند');
                    ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-3 text-white font-bold"><?= htmlspecialchars($s['name']) ?><div class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($s['driver']) ?></div></td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded-full text-[10px] border font-bold <?= $statusCls ?>"><?= $statusLabel ?></span></td>
                        <td class="p-3 text-center font-mono text-slate-300"><?= $s['latency_ms'] ?? 0 ?>ms</td>
                        <td class="p-3 text-center">
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-16 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: <?= $stat['uptime_percent'] ?>%"></div>
                                </div>
                                <span class="font-mono text-[11px] text-white"><?= $stat['uptime_percent'] ?>%</span>
                            </div>
                        </td>
                        <td class="p-3 text-center font-mono text-slate-400"><?= $stat['total_checks'] ?></td>
                        <td class="p-3 text-rose-300 max-w-[150px] truncate text-[11px]" title="<?= htmlspecialchars($s['error_message'] ?? '') ?>"><?= htmlspecialchars(mb_substr($s['error_message'] ?? '-',0,40)) ?></td>
                        <td class="p-3 text-center">
                            <div class="flex gap-1 justify-center">
                                <a href="<?= Helpers::url('monitoring/server/'.$s['id']) ?>" class="w-7 h-7 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg flex items-center justify-center" title="لاگ‌ها"><i class="fa-solid fa-chart-line text-[10px]"></i></a>
                                <form method="POST" action="<?= Helpers::url('servers/'.$s['id'].'/full-sync') ?>" class="m-0 inline">
                                    <?= Helpers::csrfField() ?>
                                    <button type="submit" class="w-7 h-7 bg-purple-900/30 hover:bg-purple-800/50 text-purple-300 rounded-lg flex items-center justify-center" title="همگام‌سازی صف"><i class="fa-solid fa-rotate text-[10px]"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sync Queue -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-white font-bold text-sm flex items-center gap-2"><i class="fa-solid fa-list-check text-purple-400"></i> صف همگام‌سازی (ضد 520)</h3>
            <span class="text-[11px] text-slate-400">آخرین 10 صف</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right">ID</th>
                        <th class="p-3 text-right">سرور</th>
                        <th class="p-3 text-center">نوع</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-center">پیشرفت</th>
                        <th class="p-3 text-right">نتیجه</th>
                        <th class="p-3 text-right">زمان</th>
                        <th class="p-3 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($queue)): ?>
                        <tr><td colspan="8" class="p-6 text-center text-slate-400">صفی وجود ندارد - همگام‌سازی سرورها به صورت خودکار به صف می‌رود</td></tr>
                    <?php else: foreach ($queue as $q):
                        $statusCls = $q['status'] === 'completed' ? 'bg-emerald-900/30 text-emerald-300' : ($q['status'] === 'failed' ? 'bg-rose-900/30 text-rose-300' : ($q['status'] === 'running' ? 'bg-blue-900/30 text-blue-300' : 'bg-amber-900/30 text-amber-300'));
                    ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30" id="queue-row-<?= $q['id'] ?>">
                        <td class="p-3 font-mono">#<?= $q['id'] ?></td>
                        <td class="p-3 text-white"><?= htmlspecialchars($q['server_name'] ?? $q['server_id']) ?></td>
                        <td class="p-3 text-center"><span class="px-2 py-0.5 bg-slate-800 rounded-full text-[10px]"><?= htmlspecialchars($q['type']) ?></span></td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $statusCls ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                        <td class="p-3 text-center">
                            <div class="w-20 mx-auto">
                                <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 transition-all" style="width: <?= $q['progress'] ?>%" id="progress-<?= $q['id'] ?>"></div>
                                </div>
                                <div class="text-[10px] font-mono text-slate-400 mt-1"><?= $q['progress'] ?>%</div>
                            </div>
                        </td>
                        <td class="p-3 text-slate-300 max-w-[150px] truncate text-[11px]"><?= htmlspecialchars(mb_substr($q['result'] ?? '-',0,60)) ?></td>
                        <td class="p-3 font-mono text-[11px] text-slate-400"><?= htmlspecialchars($q['created_at']) ?></td>
                        <td class="p-3 text-center">
                            <?php if ($q['status'] === 'pending'): ?>
                                <form method="POST" action="<?= Helpers::url('monitoring/queue/'.$q['id'].'/process') ?>" class="m-0 inline">
                                    <?= Helpers::csrfField() ?>
                                    <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px]">اجرا</button>
                                </form>
                            <?php else: ?>
                                <button onclick="checkQueueStatus(<?= $q['id'] ?>)" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-[10px]">بروزرسانی</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-blue-950/20 border-t border-blue-800/20 text-[11px] text-blue-300">
            💡 <b>چطور کار می‌کند:</b> وقتی دکمه <code>ایمپورت کامل</code> را می‌زنید، کار به صف می‌رود تا ارور 520 ندهد. پردازش در پس‌زمینه انجام می‌شود و نتیجه به تلگرام ارسال می‌گردد. پیشرفت را اینجا می‌بینید.
        </div>
    </div>

    <!-- Sublink Domains -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-white font-bold text-sm flex items-center gap-2"><i class="fa-solid fa-globe text-cyan-400"></i> دامنه‌های ساب‌لینک چرخشی (ضد فیلتر)</h3>
            <a href="<?= Helpers::url('settings/sublink-domains') ?>" class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-[11px]">مدیریت دامنه‌ها</a>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php foreach ($domains as $d):
                    $cls = $d['health_status'] === 'online' ? 'border-emerald-800/30 bg-emerald-950/10' : ($d['health_status'] === 'offline' ? 'border-rose-800/30 bg-rose-950/10' : 'border-amber-800/30 bg-amber-950/10');
                ?>
                <div class="border rounded-xl p-3 <?= $cls ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono font-bold text-white text-xs"><?= htmlspecialchars($d['domain']) ?></span>
                        <?php if ($d['is_primary']): ?><span class="text-[9px] bg-cyan-500/20 text-cyan-300 px-1.5 py-0.5 rounded-full border border-cyan-500/30">اصلی</span><?php endif; ?>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="text-slate-400"><?= $d['health_status'] === 'online' ? '✅ آنلاین' : ($d['health_status'] === 'offline' ? '❌ آفلاین' : '⚠️ کند') ?></span>
                        <span class="font-mono text-slate-300"><?= $d['latency_ms'] ?>ms</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function checkQueueStatus(id) {
    fetch('<?= Helpers::url('servers/queue/') ?>' + id + '/status')
        .then(r => r.json())
        .then(data => {
            if (data.progress !== undefined) {
                document.getElementById('progress-' + id).style.width = data.progress + '%';
                // Update row
                location.reload();
            }
        });
}

// Auto refresh running queues every 5s
setInterval(() => {
    document.querySelectorAll('[id^=\"queue-row-\"]').forEach(row => {
        const id = row.id.replace('queue-row-', '');
        const statusEl = row.querySelector('span');
        if (statusEl && statusEl.textContent.includes('running')) {
            checkQueueStatus(id);
        }
    });
}, 5000);
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

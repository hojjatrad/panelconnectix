<?php
$pageTitle = 'وضعیت سرورها LIVE - ULTRA v7.2';
require __DIR__ . '/../layout/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between bg-slate-900 border border-slate-800 p-5 rounded-2xl">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-server text-emerald-400"></i>
                <span>وضعیت سرورها LIVE - مانیتورینگ ریل‌تایم</span>
                <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 rounded-full text-[10px]">ULTRA v7.2 RESYNC</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">مشاهده لحظه‌ای سلامت، تاخیر، CPU و RAM تمام سرورهای فعال - فقط خواندنی برای ریسلر</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= Helpers::url('reseller/monitoring') ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs"><i class="fa-solid fa-rotate"></i> بروزرسانی</a>
            <a href="<?= Helpers::url('dashboard') ?>" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs">داشبورد</a>
        </div>
    </div>

    <!-- Domains Rotator -->
    <?php if (!empty($domains)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2"><i class="fa-solid fa-globe text-cyan-400"></i> دامنه‌های چرخشی ضد فیلتر</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($domains as $d): ?>
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-white"><?= htmlspecialchars($d['domain']) ?></div>
                    <div class="text-[10px] text-slate-400"><?= $d['latency_ms'] ?>ms - <?= $d['health_status'] ?></div>
                </div>
                <div class="w-2 h-2 rounded-full <?= $d['health_status']==='online'?'bg-emerald-500 animate-pulse':'bg-red-500' ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Servers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($serverStats as $srv): 
            $isOnline = ($srv['health_status']??'online')==='online';
            $cpu = $srv['latest']['cpu_percent'] ?? $srv['cpu'] ?? 0;
            $ram = $srv['latest']['ram_percent'] ?? $srv['ram'] ?? 0;
        ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 hover:border-slate-700 transition">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-xl <?= $isOnline?'bg-emerald-500/20 text-emerald-400':'bg-red-500/20 text-red-400' ?> flex items-center justify-center">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white"><?= htmlspecialchars($srv['name']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($srv['driver']) ?> - <?= htmlspecialchars($srv['location']??'IR') ?></div>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isOnline?'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30':'bg-red-500/20 text-red-400 border border-red-500/30' ?>"><?= $isOnline?'🟢 آنلاین':'🔴 آفلاین' ?></span>
            </div>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between"><span class="text-slate-400">تاخیر:</span><span class="text-white font-bold"><?= $srv['latency_ms']??0 ?>ms</span></div>
                <div class="flex justify-between"><span class="text-slate-400">آپتایم 24ساعت:</span><span class="text-emerald-400 font-bold"><?= round($srv['uptime_percent']??100,1) ?>%</span></div>
                <div>
                    <div class="flex justify-between mb-1"><span class="text-slate-400">CPU</span><span class="text-white"><?= $cpu ?>%</span></div>
                    <div class="w-full bg-slate-800 rounded-full h-1.5"><div class="h-1.5 rounded-full <?= $cpu>80?'bg-red-500':($cpu>50?'bg-amber-500':'bg-emerald-500') ?>" style="width: <?= min(100,$cpu) ?>%"></div></div>
                </div>
                <div>
                    <div class="flex justify-between mb-1"><span class="text-slate-400">RAM</span><span class="text-white"><?= $ram ?>%</span></div>
                    <div class="w-full bg-slate-800 rounded-full h-1.5"><div class="h-1.5 rounded-full <?= $ram>80?'bg-red-500':($ram>50?'bg-amber-500':'bg-cyan-500') ?>" style="width: <?= min(100,$ram) ?>%"></div></div>
                </div>
                <div class="pt-2 border-t border-slate-800/50 flex justify-between text-[10px] text-slate-500">
                    <span>آخرین چک: <?= htmlspecialchars($srv['last_checked_at']??'همین الان') ?></span>
                    <span><?= htmlspecialchars($srv['ip']??'') ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($serverStats)): ?>
        <div class="col-span-full text-center py-10 text-slate-400 text-sm">هیچ سرور فعالی یافت نشد</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

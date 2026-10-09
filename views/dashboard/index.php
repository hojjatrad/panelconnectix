<?php require __DIR__ . '/../layout/header.php'; ?>

<!-- v4.0.45 PRO MAX - Glassmorphism Dashboard -->
<style>
@keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-6px); } }
@keyframes glow { 0%,100% { box-shadow: 0 0 20px rgba(168,85,247,0.2); } 50% { box-shadow: 0 0 35px rgba(168,85,247,0.4); } }
.glass-card { backdrop-filter: blur(12px); background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9)); border: 1px solid rgba(255,255,255,0.08); }
.glass-card:hover { border-color: rgba(168,85,247,0.3); transform: translateY(-2px); transition: all 0.3s ease; }
.stat-icon { animation: float 3s ease-in-out infinite; }
.pulse-dot { animation: glow 2s ease-in-out infinite; }
</style>

<!-- Welcome Banner ULTRA -->
<div class="relative overflow-hidden bg-gradient-to-br from-violet-900/30 via-slate-900 to-indigo-900/30 border border-violet-500/20 rounded-[1.5rem] p-6 shadow-2xl">
    <div class="absolute -top-20 -right-20 w-72 h-72 bg-violet-600/20 rounded-full blur-[80px] pointer-events-none"></div>
    <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-cyan-600/20 rounded-full blur-[80px] pointer-events-none"></div>
    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center text-white shadow-xl shadow-violet-900/40 text-xl">
                <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            <div>
                <?php $panelVer = class_exists('Updater') ? Updater::getCurrentVersion() : '4.0.45'; ?>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-violet-500/20 text-violet-300 border border-violet-500/30 font-mono">v<?= htmlspecialchars($panelVer) ?> PRO ULTRA</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                    <span class="text-[11px] text-emerald-300">زنده و متصل</span>
                </div>
                <h2 class="text-xl md:text-2xl font-black text-white">سلام، <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?> 👋</h2>
                <p class="text-xs text-slate-400 mt-1">به پنل فوق حرفه‌ای کانکتیکس خوش آمدی - همه چیز تحت کنترلته</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= Helpers::url('monitoring') ?>" class="px-4 py-2.5 bg-slate-800/80 hover:bg-emerald-900/30 text-emerald-300 border border-slate-700 hover:border-emerald-500/30 text-xs font-bold rounded-xl transition-all flex items-center gap-2">
                <i class="fa-solid fa-heart-pulse"></i> مانیتورینگ زنده
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
            </a>
            <a href="<?= Helpers::url('clients/create') ?>" class="px-5 py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-black rounded-xl shadow-lg shadow-violet-900/30 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-plus"></i> کاربر جدید
            </a>
        </div>
    </div>
</div>

<!-- Reseller Tier Progress ULTRA -->
<div class="bg-gradient-to-r from-violet-950/40 via-slate-900 to-indigo-950/40 border border-violet-500/20 rounded-2xl p-5 flex flex-col md:flex-row items-center justify-between gap-4 glass-card">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-600/40 to-indigo-600/40 border border-violet-500/30 flex items-center justify-center text-2xl"><?= $tierInfo['badge'] ?></div>
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400">سطح شراکت:</span>
                <span class="px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r from-violet-600/20 to-indigo-600/20 text-violet-300 border border-violet-500/30">سطح <?= $tierInfo['title'] ?> • <?= $tierInfo['discount'] ?>% تخفیف</span>
            </div>
            <p class="text-xs text-slate-400 mt-1.5"><?= htmlspecialchars($tierInfo['next_target'] ?? 'تخفیف حداکثری فعال است 🚀') ?></p>
            <?php if ($tierInfo['tier'] !== 'diamond'): 
                $currentClients = (int)($tierInfo['client_count'] ?? 0);
                $nextTargetNum = match($tierInfo['tier']) { 'bronze'=>10, 'silver'=>25, 'gold'=>50, default=>50 };
                $tierProgress = min(100, round(($currentClients / $nextTargetNum) * 100));
            ?>
            <div class="mt-3 w-full max-w-sm">
                <div class="flex justify-between text-[11px] text-slate-400 mb-1.5 font-mono">
                    <span>ارتقای خودکار</span>
                    <span class="text-violet-300 font-bold"><?= $currentClients ?> / <?= $nextTargetNum ?> (<?= $tierProgress ?>%)</span>
                </div>
                <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
                    <div class="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-cyan-400 rounded-full transition-all duration-700" style="width: <?= $tierProgress ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex gap-3">
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 text-center min-w-[110px]">
            <span class="text-[10px] text-slate-400 block">تخفیف هر پلن</span>
            <span class="text-emerald-400 font-black text-lg"><?= $tierInfo['discount'] ?>%</span>
        </div>
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 text-center min-w-[110px]">
            <span class="text-[10px] text-slate-400 block"><?= Auth::isAdmin() ? 'سود تخمینی' : 'زیرمجموعه' ?></span>
            <span class="text-amber-300 font-black text-sm"><?= Auth::isAdmin() ? Helpers::formatMoney($netProfit) : ($subResellerCount . ' نفر') ?></span>
        </div>
    </div>
</div>

<!-- Stats Grid ULTRA - 6 cards with glassmorphism -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
    <?php
    $cards = [
        ['label'=>'کل مشتریان','value'=>number_format($totalClients),'sub'=>'ثبت‌شده','icon'=>'fa-users','color'=>'blue','bg'=>'from-blue-600/20 to-cyan-600/20','text'=>'text-blue-400'],
        ['label'=>'آنلاین لحظه‌ای','value'=>number_format($onlineClients),'sub'=>'10 دقیقه اخیر','icon'=>'fa-signal','color'=>'emerald','bg'=>'from-emerald-600/20 to-teal-600/20','text'=>'text-emerald-400','pulse'=>true],
        ['label'=>'فعال','value'=>number_format($activeClients),'sub'=>'دارای اعتبار','icon'=>'fa-circle-check','color'=>'violet','bg'=>'from-violet-600/20 to-purple-600/20','text'=>'text-violet-400'],
        ['label'=>'راکد','value'=>number_format($idleClients),'sub'=>'عدم اتصال','icon'=>'fa-clock','color'=>'amber','bg'=>'from-amber-600/20 to-orange-600/20','text'=>'text-amber-400'],
        ['label'=>'هرگز متصل نشده','value'=>number_format($neverConnected),'sub'=>'استفاده صفر','icon'=>'fa-user-slash','color'=>'indigo','bg'=>'from-indigo-600/20 to-violet-600/20','text'=>'text-indigo-300'],
        ['label'=>'منقضی','value'=>number_format($expiredClients),'sub'=>'پایان اعتبار','icon'=>'fa-circle-xmark','color'=>'rose','bg'=>'from-rose-600/20 to-red-600/20','text'=>'text-rose-400'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="glass-card rounded-2xl p-4 hover:shadow-xl transition-all duration-300 group">
        <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-bold text-slate-400 group-hover:text-white transition"><?= $c['label'] ?></span>
            <span class="w-9 h-9 rounded-xl bg-gradient-to-br <?= $c['bg'] ?> border border-white/10 flex items-center justify-center <?= $c['text'] ?> stat-icon">
                <i class="fa-solid <?= $c['icon'] ?> text-sm"></i>
                <?php if (!empty($c['pulse'])): ?><span class="absolute w-2 h-2 bg-emerald-400 rounded-full -top-1 -right-1 animate-ping"></span><?php endif; ?>
            </span>
        </div>
        <div class="text-2xl font-black text-white tracking-tight"><?= $c['value'] ?></div>
        <div class="text-[10px] text-slate-500 mt-1"><?= $c['sub'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main Grid: Chart + Financial + Server Health -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Chart Sales -->
    <div class="lg:col-span-2 glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-chart-line text-violet-400"></i> نمودار رشد فروش</h3>
                <p class="text-[11px] text-slate-400 mt-1">6 ماه اخیر - درآمد واقعی از دیتابیس</p>
            </div>
            <span class="text-[10px] px-2.5 py-1 rounded-full bg-violet-500/10 text-violet-300 border border-violet-500/20 font-bold">زنده</span>
        </div>
        <div class="h-64"><canvas id="growthChart"></canvas></div>
        <?php if (!empty($financialMonthly)): ?>
        <div class="mt-4 grid grid-cols-3 gap-2 text-[11px]">
            <?php foreach (array_slice($financialMonthly, -3) as $fm): ?>
            <div class="bg-slate-800/50 rounded-xl p-2.5 border border-slate-700/50 text-center">
                <div class="text-slate-400"><?= htmlspecialchars($fm['ym']) ?></div>
                <div class="font-bold text-emerald-300 font-mono"><?= Helpers::formatMoney((int)$fm['income']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Financial + Quick Actions -->
    <div class="space-y-5">
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-black text-sm text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-wallet text-emerald-400"></i> کیف پول و مالی</h3>
            <div class="space-y-3">
                <div class="bg-gradient-to-br from-emerald-950/40 to-teal-950/40 border border-emerald-800/30 rounded-xl p-4">
                    <span class="text-[11px] text-emerald-300/70 block mb-1">موجودی فعلی</span>
                    <span class="text-2xl font-black text-emerald-400 font-mono"><?= Helpers::formatMoney($walletBalance) ?></span>
                    <div class="mt-2 flex gap-2">
                        <a href="<?= Helpers::url('billing') ?>" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-[11px] font-bold text-center transition">شارژ +</a>
                        <a href="<?= Helpers::url('billing') ?>" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-[11px] font-bold text-center border border-slate-700 transition">صورت‌حساب</a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-slate-800/60 rounded-xl p-3 border border-slate-700/50">
                        <span class="text-[10px] text-slate-400 block">کل خرید</span>
                        <span class="font-bold text-violet-300 text-sm"><?= Helpers::formatMoney($totalSpent) ?></span>
                    </div>
                    <div class="bg-slate-800/60 rounded-xl p-3 border border-slate-700/50">
                        <span class="text-[10px] text-slate-400 block">تراکنش</span>
                        <span class="font-bold text-white text-sm"><?= number_format($totalTransactions) ?></span>
                    </div>
                </div>
                <div class="bg-slate-800/40 rounded-xl p-3 border border-slate-700/30 text-[11px] text-slate-300 flex justify-between">
                    <span>پلن‌ها: <?= $totalPlans ?> (<?= $freePlans ?> رایگان)</span>
                    <span class="text-violet-300 font-bold"><?= $premiumPlans ?> تجاری</span>
                </div>
            </div>
        </div>

        <!-- Expiring Soon -->
        <?php if (!empty($expiringSoon)): ?>
        <div class="glass-card rounded-2xl p-4 border-amber-800/20">
            <h4 class="font-bold text-xs text-amber-300 mb-3 flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation"></i> در حال انقضا (3 روز)</h4>
            <div class="space-y-2">
                <?php foreach ($expiringSoon as $es): ?>
                <div class="flex items-center justify-between bg-amber-950/20 border border-amber-800/20 rounded-xl p-2.5">
                    <div>
                        <div class="font-mono font-bold text-white text-xs"><?= htmlspecialchars($es['username']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($es['plan_title'] ?? '') ?></div>
                    </div>
                    <div class="text-[10px] font-mono text-amber-300"><?= htmlspecialchars($es['expire_at']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= Helpers::url('clients?filter=expiring') ?>" class="mt-3 block text-center py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 rounded-xl text-[11px] font-bold border border-amber-500/20 transition">مشاهده همه</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 72h Sparkline + Top Resellers + Server Health -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?php
    if (!function_exists('sparkline_svg')) {
        function sparkline_svg(array $rows, string $key, string $color): string {
            $vals = array_map(fn($r) => (float)($r[$key] ?? 0), $rows);
            $n = count($vals); if ($n===0) return '';
            $max = max($vals); $min = min($vals); $span = max(1.0, $max-$min);
            $W=600; $H=80; $pad=4; $pts=[];
            for ($i=0;$i<$n;$i++) { $x=$pad+($W-2*$pad)*($n===1?0:$i/($n-1)); $y=$H-$pad-($H-2*$pad)*(($vals[$i]-$min)/$span); $pts[]=round($x,1).','.round($y,1); }
            $area = $pts[0].' '.implode(' ', $pts).' '.round($W-$pad,1).','.($H-$pad).' '.$pad.','.($H-$pad);
            return '<svg viewBox="0 0 '.$W.' '.$H.'" preserveAspectRatio="none" class="w-full h-20"><polygon points="'.$area.'" fill="'.$color.'18" /><polyline points="'.implode(' ', $pts).'" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
        }
    }
    if (!empty($metrics72h) && count($metrics72h)>=2):
        $firstTs = date('d/m H:i', strtotime((string)$metrics72h[0]['ts']));
        $lastTs = date('d/m H:i', strtotime((string)end($metrics72h)['ts']));
        $gbNow = number_format((float)end($metrics72h)['traffic_used_total']/1073741824,1);
        $actNow = (int)end($metrics72h)['active_clients'];
        $nodesNow = (int)end($metrics72h)['online_nodes'].'/'.(int)end($metrics72h)['total_nodes'];
    ?>
    <div class="lg:col-span-2 glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-wave-square text-cyan-400"></i> روند 72 ساعت</h3>
            <span class="text-[10px] font-mono text-slate-500" dir="ltr"><?= $firstTs ?> ← <?= $lastTs ?></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div><div class="flex justify-between mb-1"><span class="text-xs text-slate-400">مصرف کل</span><span class="text-sm font-black text-cyan-300"><?= $gbNow ?> GB</span></div><?= sparkline_svg($metrics72h,'traffic_used_total','#22D3EE') ?></div>
            <div><div class="flex justify-between mb-1"><span class="text-xs text-slate-400">فعال</span><span class="text-sm font-black text-emerald-300"><?= number_format($actNow) ?></span></div><?= sparkline_svg($metrics72h,'active_clients','#34D399') ?></div>
            <div><div class="flex justify-between mb-1"><span class="text-xs text-slate-400">نود برخط</span><span class="text-sm font-black text-violet-300"><?= $nodesNow ?></span></div><?= sparkline_svg($metrics72h,'online_nodes','#A78BFA') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Server Health Live ULTRA -->
    <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-server text-emerald-400"></i> سلامت سرورها</h3>
            <a href="<?= Helpers::url('monitoring') ?>" class="text-[11px] text-violet-400 hover:text-violet-300 font-bold">LIVE →</a>
        </div>
        <div class="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
            <?php foreach (($serverHealth ?? $activeNodes) as $node):
                $health = $node['health_status'] ?? 'online';
                $uptime = $node['uptime_percent'] ?? 100;
                $lat = $node['latency_ms'] ?? $node['avg_latency'] ?? 0;
                $cls = $health==='online'?'border-emerald-800/30 bg-emerald-950/20':($health==='offline'?'border-rose-800/30 bg-rose-950/20':'border-amber-800/30 bg-amber-950/20');
                $dot = $health==='online'?'bg-emerald-400':($health==='offline'?'bg-rose-400':'bg-amber-400');
            ?>
            <div class="p-3 rounded-xl border <?= $cls ?> flex items-center justify-between group hover:scale-[1.02] transition-all">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full <?= $dot ?> <?= $health==='online'?'animate-pulse':'' ?>"></span>
                    <div>
                        <div class="font-bold text-white text-xs"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($node['driver'] ?? '') ?> • <?= $lat ?>ms • <?= $uptime ?>% uptime</div>
                    </div>
                </div>
                <div class="w-12 h-1.5 bg-slate-800 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width: <?= $uptime ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($sublinkDomains)): ?>
        <div class="mt-4 pt-4 border-t border-slate-800/60">
            <h4 class="text-[11px] font-bold text-cyan-300 mb-2 flex items-center gap-1"><i class="fa-solid fa-globe"></i> دامنه چرخشی</h4>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach (array_slice($sublinkDomains,0,4) as $d): ?>
                <span class="px-2 py-1 rounded-full text-[10px] font-mono border <?= ($d['health_status']==='online'?'bg-emerald-900/20 text-emerald-300 border-emerald-800/30':'bg-rose-900/20 text-rose-300 border-rose-800/30') ?>"><?= htmlspecialchars($d['domain']) ?> • <?= $d['latency_ms'] ?>ms</span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top Resellers + Recent Clients + Queue -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <?php if (!empty($topResellers)): ?>
    <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-crown text-amber-400"></i> نمایندگان برتر</h3>
            <a href="<?= Helpers::url('resellers') ?>" class="text-[11px] text-violet-400 font-bold">مدیریت →</a>
        </div>
        <div class="space-y-2.5">
            <?php foreach ($topResellers as $rank=>$tr): ?>
            <div class="flex items-center justify-between p-2.5 bg-slate-800/40 border border-slate-700/50 rounded-xl hover:bg-slate-800/60 transition">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-xl bg-gradient-to-br from-violet-600/20 to-indigo-600/20 border border-violet-500/20 text-violet-300 text-[11px] font-black flex items-center justify-center"><?= $rank+1 ?></span>
                    <div>
                        <div class="font-bold text-white text-xs"><?= htmlspecialchars($tr['full_name'] ?: $tr['username']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= number_format((int)($tr['active_clients'] ?? 0)) ?> فعال • <?= Helpers::formatMoney((int)($tr['week_sales'] ?? 0)) ?> هفته</div>
                    </div>
                </div>
                <span class="text-[11px] font-mono text-emerald-300"><?= Helpers::formatMoney((int)($tr['wallet_balance'] ?? 0)) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= empty($topResellers) ? 'lg:col-span-2' : '' ?> glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-users text-cyan-400"></i> آخرین کلاینت‌ها</h3>
            <a href="<?= Helpers::url('clients') ?>" class="text-[11px] text-violet-400 font-bold">همه →</a>
        </div>
        <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1">
            <?php foreach ($recentClients as $rc):
                $pct = $rc['traffic_limit_bytes']>0 ? round(($rc['traffic_used_bytes']/$rc['traffic_limit_bytes'])*100) : 0;
            ?>
            <div class="flex items-center justify-between p-2.5 bg-slate-800/30 border border-slate-800 rounded-xl hover:bg-slate-800/50 transition group">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-violet-600/20 to-indigo-600/20 flex items-center justify-center text-violet-300 font-bold text-[11px]"><?= strtoupper(substr($rc['username'],0,2)) ?></div>
                    <div>
                        <div class="font-mono font-bold text-white text-xs"><?= htmlspecialchars($rc['username']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($rc['plan_title'] ?? 'عادی') ?> • <?= Helpers::formatBytes($rc['traffic_used_bytes']) ?> / <?= Helpers::formatBytes($rc['traffic_limit_bytes']) ?></div>
                        <div class="w-20 h-1 bg-slate-800 rounded-full mt-1 overflow-hidden"><div class="h-full bg-violet-500" style="width: <?= min(100,$pct) ?>%"></div></div>
                    </div>
                </div>
                <button onclick="copyToClipboard('<?= Helpers::fullUrl('sub/'.$rc['sub_token']) ?>', this)" class="w-8 h-8 bg-slate-800 hover:bg-violet-600 text-slate-400 hover:text-white rounded-xl flex items-center justify-center transition"><i class="fa-solid fa-copy text-[11px]"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sync Queue Live -->
    <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-list-check text-purple-400"></i> صف همگام‌سازی</h3>
            <a href="<?= Helpers::url('monitoring') ?>" class="text-[11px] text-violet-400 font-bold">LIVE →</a>
        </div>
        <div class="space-y-2.5 max-h-[300px] overflow-y-auto pr-1">
            <?php if (empty($syncQueueRecent)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">صفی وجود ندارد - همه چیز به‌روز است ✅</div>
            <?php else: foreach ($syncQueueRecent as $q):
                $cls = $q['status']==='completed'?'bg-emerald-900/20 text-emerald-300 border-emerald-800/30':($q['status']==='failed'?'bg-rose-900/20 text-rose-300 border-rose-800/30':($q['status']==='running'?'bg-blue-900/20 text-blue-300 border-blue-800/30':'bg-amber-900/20 text-amber-300 border-amber-800/30'));
            ?>
            <div class="p-3 rounded-xl border <?= $cls ?>">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-xs">#<?= $q['id'] ?> <?= htmlspecialchars($q['server_name'] ?? '') ?></span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700"><?= htmlspecialchars($q['status']) ?></span>
                </div>
                <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden"><div class="h-full bg-violet-500 transition-all" style="width: <?= $q['progress'] ?>%"></div></div>
                <div class="text-[10px] font-mono mt-1 opacity-70"><?= $q['progress'] ?>% • <?= htmlspecialchars($q['created_at']) ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const ctx = document.getElementById('growthChart');
    if(!ctx) return;
    const months = <?= json_encode($chartMonths) ?>;
    const realData = <?= json_encode($chartData) ?>;
    const monthlyIncome = <?= json_encode(array_column($financialMonthly,'income')) ?>;
    const labels = <?= !empty($financialMonthly) ? json_encode(array_column($financialMonthly,'ym')) : 'months' ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : months,
            datasets: [{
                label: 'فروش',
                data: monthlyIncome.length ? monthlyIncome : realData,
                borderColor: '#a855f7',
                backgroundColor: (context)=>{
                    const bg = context.chart.ctx.createLinearGradient(0,0,0,200);
                    bg.addColorStop(0,'rgba(168,85,247,0.3)');
                    bg.addColorStop(1,'rgba(168,85,247,0)');
                    return bg;
                },
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#a855f7',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { backgroundColor:'#1e293b', titleColor:'#fff', bodyColor:'#cbd5e1', borderColor:'#334155', borderWidth:1, padding:10, displayColors:false } },
            scales: {
                x: { grid: { color:'rgba(255,255,255,0.04)' }, ticks: { color:'#64748b', font:{ family:'Vazirmatn', size:10 } } },
                y: { grid: { color:'rgba(255,255,255,0.04)' }, ticks: { color:'#64748b', font:{ family:'Vazirmatn', size:10 } } }
            }
        }
    });
});
function copyToClipboard(text, btn){
    navigator.clipboard.writeText(text).then(()=>{
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i>';
        setTimeout(()=>btn.innerHTML=orig,1500);
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

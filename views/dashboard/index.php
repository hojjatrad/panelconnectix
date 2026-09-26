<?php
require __DIR__ . '/../layout/header.php';
?>

<!-- Welcome Banner -->
<div class="relative overflow-hidden bg-gradient-to-r from-purple-900/40 via-slate-900 to-slate-900 border border-purple-800/30 rounded-2xl p-6 shadow-xl">
    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <?php 
            $panelVer = class_exists('Updater') ? Updater::getCurrentVersion() : '2.4.4'; 
            ?>
            <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30 mb-2 font-mono">
                نگارش پنل: v<?= htmlspecialchars($panelVer) ?> | سازگار با مرزبان، پاسارگاد و ۳x-ui
            </span>
            <h2 class="text-xl md:text-2xl font-black text-white">سلام، <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?> خوش آمدید!</h2>
            <p class="text-xs md:text-sm text-slate-400 mt-1"><?= htmlspecialchars($user['welcome_message'] ?? 'مدیریت و مانیتورینگ متمرکز سرورها و مشتریان') ?></p>
        </div>
        <div class="flex items-center gap-3">
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= Helpers::url('servers/sync') ?>" class="px-3.5 py-2.5 bg-slate-800 hover:bg-purple-900/40 text-purple-300 text-xs md:text-sm font-medium rounded-xl border border-slate-700 transition-all flex items-center gap-2" title="همگام‌سازی لحظه‌ای مصرف کلاینت‌ها از نودها">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span class="hidden sm:inline">همگام‌سازی نودها</span>
            </a>
            <?php endif; ?>
            <a href="<?= Helpers::url('clients/create') ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs md:text-sm font-bold rounded-xl transition-all shadow-lg shadow-purple-900/30 flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i>
                <span>ثبت مشتری جدید</span>
            </a>
            <a href="<?= Helpers::url('billing') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs md:text-sm font-medium rounded-xl border border-slate-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-wallet text-emerald-400"></i>
                <span>شارژ موجودی</span>
            </a>
        </div>
    </div>
</div>

<!-- Reseller Tier & Business Growth Banner -->
<div class="bg-gradient-to-r from-purple-950/60 via-slate-900 to-indigo-950/60 border border-purple-500/30 p-5 rounded-2xl shadow-sm mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-2xl bg-purple-600/30 border border-purple-500/40 flex items-center justify-center text-2xl shadow-inner">
            <?= $tierInfo['badge'] ?>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400">سطح شراکت و نمایندگی:</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                    سطح <?= $tierInfo['title'] ?> (<?= $tierInfo['discount'] ?>٪ تخفیف اختصاصی)
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($tierInfo['next_target'] ?? 'تخفیف حداکثری فعال است.') ?></p>
            <?php if ($tierInfo['tier'] !== 'diamond'): ?>
                <?php
                $currentClients = (int)($tierInfo['client_count'] ?? 0);
                $nextTargetNum = match($tierInfo['tier']) {
                    'bronze' => 10,
                    'silver' => 25,
                    'gold' => 50,
                    default => 50
                };
                $tierProgress = min(100, round(($currentClients / $nextTargetNum) * 100));
                ?>
                <div class="mt-2 w-full max-w-sm">
                    <div class="flex justify-between text-[10px] text-slate-400 mb-1 font-mono">
                        <span class="font-sans">مسیر ارتقای خودکار:</span>
                        <span class="text-purple-300 font-bold"><?= $currentClients ?> / <?= $nextTargetNum ?> کلاینت (<?= $tierProgress ?>%)</span>
                    </div>
                    <div class="w-full bg-slate-950/80 rounded-full h-1.5 overflow-hidden border border-slate-800">
                        <div class="h-full bg-gradient-to-r from-purple-500 to-emerald-400 rounded-full transition-all duration-500" style="width: <?= $tierProgress ?>%"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex items-center gap-3 text-xs font-mono">
        <div class="bg-slate-900/90 border border-slate-800 p-2.5 rounded-xl text-center min-w-[120px]">
            <span class="text-[10px] text-slate-400 block font-sans">تخفیف روی هر پلن</span>
            <span class="text-emerald-400 font-bold text-sm"><?= $tierInfo['discount'] ?>%</span>
        </div>
        <div class="bg-slate-900/90 border border-slate-800 p-2.5 rounded-xl text-center min-w-[120px]">
            <span class="text-[10px] text-slate-400 block font-sans"><?= Auth::isAdmin() ? 'تخمین سود خالص' : 'ساب‌نمایندگان شما' ?></span>
            <span class="text-amber-300 font-bold text-sm"><?= Auth::isAdmin() ? Helpers::formatMoney($netProfit) : ($subResellerCount . ' بازاریاب') ?></span>
        </div>
    </div>
</div>

<!-- 1. Client Metrics Grid (Connectix Style) -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
    <!-- Total Clients -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">کل مشتریان</span>
            <span class="p-2 rounded-lg bg-blue-500/10 text-blue-400"><i class="fa-solid fa-users"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-white"><?= number_format($totalClients) ?></span>
            <span class="text-[10px] text-slate-400 block mt-1">تعداد ثبت‌شده</span>
        </div>
    </div>

    <!-- Online Now -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">آنلاین در لحظه</span>
            <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400"><i class="fa-solid fa-signal"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-emerald-400"><?= number_format($onlineClients) ?></span>
            <span class="text-[10px] text-emerald-500/80 block mt-1">متصل در ۱۰ دقیقه اخیر</span>
        </div>
    </div>

    <!-- Active Clients -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">سرویس‌های فعال</span>
            <span class="p-2 rounded-lg bg-purple-500/10 text-purple-400"><i class="fa-solid fa-circle-check"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-purple-400"><?= number_format($activeClients) ?></span>
            <span class="text-[10px] text-slate-400 block mt-1">دارای زمان و حجم</span>
        </div>
    </div>

    <!-- Idle Clients -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">کلاینت‌های راکد</span>
            <span class="p-2 rounded-lg bg-amber-500/10 text-amber-400"><i class="fa-solid fa-clock-rotate-left"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-amber-400"><?= number_format($idleClients) ?></span>
            <span class="text-[10px] text-slate-400 block mt-1">عدم اتصال اخیر</span>
        </div>
    </div>

    <!-- Never Connected -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">هرگز متصل نشده</span>
            <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400"><i class="fa-solid fa-user-slash"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-indigo-300"><?= number_format($neverConnected) ?></span>
            <span class="text-[10px] text-slate-400 block mt-1">استفاده صفر</span>
        </div>
    </div>

    <!-- Expired Clients -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex flex-col justify-between hover:border-slate-700 transition-all shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-xs font-semibold">منقضی‌شده</span>
            <span class="p-2 rounded-lg bg-rose-500/10 text-rose-400"><i class="fa-solid fa-circle-xmark"></i></span>
        </div>
        <div>
            <span class="text-2xl font-black text-rose-400"><?= number_format($expiredClients) ?></span>
            <span class="text-[10px] text-slate-400 block mt-1">پایان حجم یا زمان</span>
        </div>
    </div>
</div>

<!-- 2. Charts & Financial Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Chart: Sales & Growth -->
    <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-sm text-white">نمودار رشد فروش و اکانت‌ها (۱۲ ماهه)</h3>
                <p class="text-xs text-slate-400 mt-0.5">آمار مقایسه‌ای صدور سرویس در طول سال</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-800 text-purple-400 border border-slate-700">سال جاری</span>
        </div>
        <div class="h-64 relative">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Financial Summary Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between shadow-sm">
        <div>
            <h3 class="font-bold text-sm text-white mb-1">وضعیت مالی و سهمیه</h3>
            <p class="text-xs text-slate-400 mb-4">تراز مالی حساب نمایندگی</p>

            <div class="space-y-3.5">
                <div class="bg-slate-800/50 p-3.5 rounded-xl border border-slate-700/50">
                    <span class="text-xs text-slate-400 block mb-1">موجودی کیف پول</span>
                    <span class="text-xl font-black text-emerald-400"><?= Helpers::formatMoney($walletBalance) ?></span>
                </div>

                <div class="bg-slate-800/50 p-3.5 rounded-xl border border-slate-700/50">
                    <span class="text-xs text-slate-400 block mb-1">مجموع خریدها تا کنون</span>
                    <span class="text-xl font-bold text-purple-300"><?= Helpers::formatMoney($totalSpent) ?></span>
                </div>

                <div class="flex justify-between items-center text-xs text-slate-300 px-1">
                    <span>تعداد تراکنش‌ها:</span>
                    <span class="font-bold text-white"><?= number_format($totalTransactions) ?> تراکنش</span>
                </div>

                <div class="flex justify-between items-center text-xs text-slate-300 px-1">
                    <span>پلن‌های موجود:</span>
                    <span class="font-bold text-white"><?= $totalPlans ?> پلن (<?= $freePlans ?> تستی / <?= $premiumPlans ?> تجاری)</span>
                </div>
            </div>
        </div>

        <a href="<?= Helpers::url('billing') ?>" class="mt-4 w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-purple-300 font-semibold text-xs rounded-xl border border-slate-700 transition-all text-center block">
            مشاهده صورت‌حساب‌ها و فاکتورها
        </a>
    </div>
</div>

<!-- 2a. 72h Trend (sparklines) -->
<?php
if (!function_exists('sparkline_svg')) {
    function sparkline_svg(array $rows, string $key, string $color): string {
        $vals = array_map(fn($r) => (float)($r[$key] ?? 0), $rows);
        $n = count($vals);
        if ($n === 0) return '';
        $max = max($vals);
        $min = min($vals);
        $span = max(1.0, $max - $min);
        $W = 600; $H = 80; $pad = 4;
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $pad + ($W - 2 * $pad) * ($n === 1 ? 0 : $i / ($n - 1));
            $y = $H - $pad - ($H - 2 * $pad) * (($vals[$i] - $min) / $span);
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        $area = $pts[0] . ' ' . implode(' ', $pts) . ' ' . round($W - $pad, 1) . ',' . ($H - $pad) . ' ' . $pad . ',' . ($H - $pad);
        return '<svg viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" class="w-full h-20">'
            . '<polygon points="' . $area . '" fill="' . $color . '18" />'
            . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
    }
}
if (!empty($metrics72h) && count($metrics72h) >= 2) {
    $firstTs = date('d/m H:i', strtotime((string)$metrics72h[0]['ts']));
    $lastTs = date('d/m H:i', strtotime((string)end($metrics72h)['ts']));
    $gbNow = number_format((float)end($metrics72h)['traffic_used_total'] / 1073741824, 1);
    $actNow = (int)end($metrics72h)['active_clients'];
    $nodesNow = (int)end($metrics72h)['online_nodes'] . '/' . (int)end($metrics72h)['total_nodes'];
    ?>
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-bold text-sm text-white">روند ۷۲ ساعت اخیر</h3>
            <p class="text-xs text-slate-400 mt-0.5">برآورد زنده از موتور همگام‌سازی (هر ۳۰ دقیقه)</p>
        </div>
        <span class="text-[11px] font-mono text-slate-500" dir="ltr"><?= $firstTs ?> ← <?= $lastTs ?></span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <div class="flex justify-between items-end mb-1">
                <span class="text-xs text-slate-400">مصرف تراکم کل</span>
                <span class="text-sm font-black text-cyan-300" dir="ltr"><?= $gbNow ?> GB</span>
            </div>
            <?= sparkline_svg($metrics72h, 'traffic_used_total', '#22D3EE') ?>
        </div>
        <div>
            <div class="flex justify-between items-end mb-1">
                <span class="text-xs text-slate-400">کلاینت‌های فعال</span>
                <span class="text-sm font-black text-emerald-300" dir="ltr"><?= number_format($actNow) ?></span>
            </div>
            <?= sparkline_svg($metrics72h, 'active_clients', '#34D399') ?>
        </div>
        <div>
            <div class="flex justify-between items-end mb-1">
                <span class="text-xs text-slate-400">نودهای برخط</span>
                <span class="text-sm font-black text-purple-300" dir="ltr"><?= $nodesNow ?></span>
            </div>
            <?= sparkline_svg($metrics72h, 'online_nodes', '#A78BFA') ?>
        </div>
    </div>
</div>
<?php } ?>

<!-- 2b. Top Resellers (admin) -->
<?php if (!empty($topResellers)): ?>
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-bold text-sm text-white">نمایندگان برتر</h3>
            <p class="text-xs text-slate-400 mt-0.5">بر اساس کلاینت‌های فعال و خریدهای ۷ روز اخیر</p>
        </div>
        <a href="<?= Helpers::url('resellers') ?>" class="text-xs text-purple-400 hover:underline">مدیریت نمایندگان</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="text-slate-400 border-b border-slate-800/80 pb-2">
                    <th class="py-2 font-semibold">نماینده</th>
                    <th class="py-2 font-semibold">کلاینت فعال</th>
                    <th class="py-2 font-semibold">خرید ۷ روز اخیر</th>
                    <th class="py-2 font-semibold">موجودی کیف‌پول</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php foreach ($topResellers as $rank => $tr): ?>
                <tr>
                    <td class="py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-purple-500/15 text-purple-300 text-[10px] font-black flex items-center justify-center"><?= $rank + 1 ?></span>
                            <span class="font-bold text-white"><?= htmlspecialchars((string)($tr['full_name'] ?: $tr['username'])) ?></span>
                        </div>
                    </td>
                    <td class="py-2.5 font-bold text-emerald-400"><?= number_format((int)$tr['active_clients']) ?></td>
                    <td class="py-2.5 font-semibold text-purple-300"><?= Helpers::formatMoney((int)$tr['week_sales']) ?></td>
                    <td class="py-2.5 text-slate-300"><?= Helpers::formatMoney((int)$tr['wallet_balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 3. Server Nodes Status & Recent Clients -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Server Nodes Live List -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm text-white">سرورهای متصل (Nodes)</h3>
            <?php if (Auth::isAdmin()): ?>
                <a href="<?= Helpers::url('servers') ?>" class="text-xs text-purple-400 hover:underline">مدیریت</a>
            <?php endif; ?>
        </div>

        <div class="space-y-3">
            <?php foreach ($activeNodes as $node): ?>
                <div class="p-3 bg-slate-800/40 border border-slate-800 rounded-xl flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span class="text-xs font-bold text-white"><?= htmlspecialchars($node['name']) ?></span>
                        </div>
                        <span class="text-[10px] text-slate-400 block mt-1 font-mono"><?= htmlspecialchars($node['driver']) ?> | <?= htmlspecialchars($node['server_group']) ?></span>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-800 text-emerald-400 border border-slate-700">برخط</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Clients Table -->
    <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm text-white">آخرین کلاینت‌های ایجاد شده</h3>
            <a href="<?= Helpers::url('clients') ?>" class="text-xs text-purple-400 hover:underline">مشاهده همه</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-800/80 pb-2">
                        <th class="py-2.5 font-semibold">نام کاربری</th>
                        <th class="py-2.5 font-semibold">پلن</th>
                        <th class="py-2.5 font-semibold">مصرف / سقف</th>
                        <th class="py-2.5 font-semibold">وضعیت</th>
                        <th class="py-2.5 font-semibold">لینک ساب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($recentClients)): ?>
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">هیچ کلاینتی یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentClients as $rc): 
                            $pct = $rc['traffic_limit_bytes'] > 0 ? round(($rc['traffic_used_bytes'] / $rc['traffic_limit_bytes']) * 100) : 0;
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3 font-mono font-medium text-white"><?= htmlspecialchars($rc['username']) ?></td>
                                <td class="py-3 text-slate-300"><?= htmlspecialchars($rc['plan_title'] ?? 'عادی') ?></td>
                                <td class="py-3">
                                    <div class="w-24 bg-slate-800 rounded-full h-1.5 mb-1 overflow-hidden">
                                        <div class="bg-purple-500 h-1.5 rounded-full" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-400"><?= Helpers::formatBytes($rc['traffic_used_bytes']) ?> از <?= Helpers::formatBytes($rc['traffic_limit_bytes']) ?></span>
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $rc['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($rc['status'] === 'expired' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-800 text-slate-400') ?>">
                                        <?= $rc['status'] === 'active' ? 'فعال' : ($rc['status'] === 'expired' ? 'منقضی' : 'تست') ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <button onclick="copyToClipboard('<?= Helpers::fullUrl('sub/' . $rc['sub_token']) ?>', this)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 rounded-lg text-purple-300 text-[11px] transition-all flex items-center gap-1">
                                        <i class="fa-solid fa-copy"></i>
                                        <span>کپی ساب</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Initialize Chart.js Growth Chart
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('growthChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartMonths) ?>,
                datasets: [{
                    label: 'تعداد کلاینت‌های فعال',
                    data: <?= json_encode($chartData) ?>,
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.12)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#a855f7',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8', font: { family: 'Vazirmatn', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8', font: { family: 'Vazirmatn', size: 10 } }
                    }
                }
            }
        });
    });
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

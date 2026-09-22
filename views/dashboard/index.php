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

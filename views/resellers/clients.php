<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-users text-cyan-400"></i>
            <span>کلاینت‌ها و مشتریان نماینده: <span class="font-mono text-purple-300"><?= htmlspecialchars($reseller['username']) ?></span> (<?= htmlspecialchars($reseller['brand_name'] ?? $reseller['full_name']) ?>)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده کلاینت‌های ایجاد شده، ترافیک مصرفی، تاریخ انقضا و وضعیت سرویس‌ها</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-right"></i>
            <span>بازگشت به لیست نمایندگان</span>
        </a>
    </div>
</div>

<!-- Stats bar for this reseller -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">مجموع کلاینت‌ها</span>
        <span class="text-xl font-bold text-white font-mono mt-1 block"><?= count($clients) ?></span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">موجودی کیف پول نماینده</span>
        <span class="text-xl font-bold text-emerald-400 font-mono mt-1 block"><?= Helpers::formatMoney($reseller['wallet_balance']) ?></span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">سقف اعتبار بدهی</span>
        <span class="text-xl font-bold text-purple-400 font-mono mt-1 block"><?= Helpers::formatMoney($reseller['credit_limit'] ?? 0) ?></span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">درصد تخفیف همکاری</span>
        <span class="text-xl font-bold text-cyan-400 font-mono mt-1 block"><?= $reseller['discount_percent'] ?>%</span>
    </div>
</div>

<!-- Clients Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <?php if (empty($clients)): ?>
        <div class="p-12 text-center text-slate-400 space-y-2">
            <i class="fa-solid fa-user-slash text-4xl text-slate-600 block"></i>
            <p class="text-sm">این نماینده تاکنون کلاینتی ایجاد نکرده است.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 font-semibold">ردیف</th>
                        <th class="p-3.5 font-semibold">نام کاربری کلاینت</th>
                        <th class="p-3.5 font-semibold">سرور / نود</th>
                        <th class="p-3.5 font-semibold">پلن فعال</th>
                        <th class="p-3.5 font-semibold">مصرف حجم</th>
                        <th class="p-3.5 font-semibold">تاریخ انقضا</th>
                        <th class="p-3.5 font-semibold">وضعیت</th>
                        <th class="p-3.5 font-semibold text-center">ساب‌لینک</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php foreach ($clients as $idx => $c): 
                        $usedGb = round($c['traffic_used_bytes'] / (1024*1024*1024), 2);
                        $totalGb = round($c['traffic_limit_bytes'] / (1024*1024*1024), 2);
                        $percent = ($totalGb > 0) ? min(100, round(($usedGb / $totalGb) * 100)) : 0;
                    ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="p-3.5">
                                <span class="font-bold text-white font-mono"><?= htmlspecialchars($c['username']) ?></span>
                            </td>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($c['server_name'] ?? 'نامشخص') ?></td>
                            <td class="p-3.5 text-purple-300 font-medium"><?= htmlspecialchars($c['plan_title'] ?? 'شخصی') ?></td>
                            <td class="p-3.5">
                                <div class="space-y-1 w-32">
                                    <div class="flex justify-between text-[10px] text-slate-400 font-mono">
                                        <span><?= $usedGb ?> GB</span>
                                        <span><?= $totalGb ?> GB</span>
                                    </div>
                                    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-full rounded-full <?= $percent > 90 ? 'bg-rose-500' : ($percent > 70 ? 'bg-amber-500' : 'bg-purple-500') ?>" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3.5 font-mono text-slate-400"><?= $c['expire_at'] ?: 'نامحدود' ?></td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $c['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                    <?= $c['status'] === 'active' ? 'فعال' : 'منقضی' ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-center">
                                <button onclick="copyToClipboard('<?= Helpers::fullUrl('sub/' . $c['sub_token']) ?>', this)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px] border border-slate-700 transition">
                                    <i class="fa-solid fa-copy"></i> کپی لینک
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

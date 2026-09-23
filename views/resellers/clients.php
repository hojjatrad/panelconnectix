<?php
require __DIR__ . '/../layout/header.php';
$hasReseller = !empty($reseller);
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-users text-cyan-400"></i>
            <?php if ($hasReseller): ?>
                <span>کلاینت‌ها و مشتریان نماینده: <span class="font-mono text-purple-300"><?= htmlspecialchars($reseller['username']) ?></span> (<?= htmlspecialchars($reseller['brand_name'] ?? $reseller['full_name']) ?>)</span>
            <?php else: ?>
                <span>نظارت بر کلاینت‌ها و مشتریان کلیه نمایندگان</span>
            <?php endif; ?>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده کلاینت‌های ایجاد شده، ترافیک مصرفی، تاریخ انقضا و وضعیت سرویس‌ها</p>
    </div>

    <div class="flex items-center gap-2">
        <form method="GET" action="<?= Helpers::url('resellers/clients') ?>" class="m-0 flex items-center gap-2">
            <select name="id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white">
                <option value="0">🌐 کلیه نمایندگان (<?= count($clients) ?> کلاینت)</option>
                <?php if (!empty($allResellers)): ?>
                    <?php foreach ($allResellers as $ar): ?>
                        <option value="<?= $ar['id'] ?>" <?= ($resellerId === (int)$ar['id']) ? 'selected' : '' ?>>
                            👤 <?= htmlspecialchars($ar['username']) ?> (<?= htmlspecialchars($ar['brand_name'] ?: $ar['full_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </form>

        <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-right"></i>
            <span>فهرست نمایندگان</span>
        </a>
    </div>
</div>

<?php if ($hasReseller): ?>
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
<?php else: ?>
<!-- Overall stats bar -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">تعداد کل کلاینت‌های نمایندگان</span>
        <span class="text-xl font-bold text-white font-mono mt-1 block"><?= count($clients) ?> کلاینت</span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">تعداد نمایندگان فعال</span>
        <span class="text-xl font-bold text-cyan-400 font-mono mt-1 block"><?= count($allResellers ?? []) ?> نماینده</span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">کلاینت‌های فعال در حال حاضر</span>
        <span class="text-xl font-bold text-emerald-400 font-mono mt-1 block"><?= count(array_filter($clients, fn($c) => $c['status'] === 'active')) ?> کلاینت</span>
    </div>
</div>
<?php endif; ?>

<!-- Clients Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <?php if (empty($clients)): ?>
        <div class="p-12 text-center text-slate-400 space-y-2">
            <i class="fa-solid fa-user-slash text-4xl text-slate-600 block"></i>
            <p class="text-sm">کلاینتی در این بخش یافت نشد.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 font-semibold">ردیف</th>
                        <th class="p-3.5 font-semibold">نام کاربری کلاینت</th>
                        <?php if (!$hasReseller): ?>
                            <th class="p-3.5 font-semibold">نماینده مسئول</th>
                        <?php endif; ?>
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
                        $usedStr = Helpers::formatBytes($c['traffic_used_bytes']);
                        $totalStr = Helpers::formatBytes($c['traffic_limit_bytes']);
                        $percent = ($c['traffic_limit_bytes'] > 0) ? min(100, round(($c['traffic_used_bytes'] / $c['traffic_limit_bytes']) * 100)) : 0;
                    ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="p-3.5">
                                <span class="font-bold text-white font-mono"><?= htmlspecialchars($c['username']) ?></span>
                            </td>
                            <?php if (!$hasReseller): ?>
                                <td class="p-3.5">
                                    <span class="font-bold text-indigo-300 block"><?= htmlspecialchars($c['reseller_username'] ?? 'مدیر') ?></span>
                                    <span class="text-[10px] text-slate-500"><?= htmlspecialchars($c['reseller_brand'] ?? '') ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($c['server_name'] ?? 'نامشخص') ?></td>
                            <td class="p-3.5 text-purple-300 font-medium"><?= htmlspecialchars($c['plan_title'] ?? 'شخصی') ?></td>
                            <td class="p-3.5">
                                <div class="space-y-1 w-32">
                                    <div class="flex justify-between text-[10px] text-slate-400 font-mono">
                                        <span><?= $usedStr ?></span>
                                        <span><?= $totalStr ?></span>
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

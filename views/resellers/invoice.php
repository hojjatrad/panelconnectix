<?php
$pageTitle = 'فاکتور و صورت‌حساب ماهانه نماینده';
require __DIR__ . '/../layout/header.php';

$isAdmin = Auth::isAdmin();
$curYear = (int)date('Y');
$curMonth = (int)date('m');

// Generate past 12 months for selector
$monthsList = [];
for ($i = 0; $i < 12; $i++) {
    $ts = strtotime("-{$i} month", strtotime(date('Y-m-01')));
    $val = date('Y-m', $ts);
    $monthsList[] = [
        'val' => $val,
        'label' => date('F Y', $ts) . ' (' . $val . ')'
    ];
}

$exportUrl = $isAdmin 
    ? Helpers::url('resellers/invoice-export', ['id' => $reseller['id'], 'month' => $month])
    : Helpers::url('reseller/invoice-export', ['month' => $month]);
$invoiceNumber = 'INV-' . str_pad((string)$reseller['id'], 3, '0', STR_PAD_LEFT) . '-' . str_replace('-', '', $month);
$totalGbAllocated = round($totalTrafficBytes / (1024 * 1024 * 1024), 2);
$totalGbUsed = round($totalTrafficUsedBytes / (1024 * 1024 * 1024), 2);
?>

<style>
@media print {
    body {
        background: #ffffff !important;
        color: #0f172a !important;
        font-size: 11px !important;
    }
    aside, header, nav, .no-print, .menu-section, footer, #toast-container {
        display: none !important;
    }
    main {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .print-sheet {
        border: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
        color: #0f172a !important;
        padding: 0 !important;
    }
    .print-table {
        border-color: #cbd5e1 !important;
    }
    .print-table th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
    }
    .print-table td {
        border: 1px solid #e2e8f0 !important;
        color: #1e293b !important;
    }
    .print-badge {
        border: 1px solid #94a3b8 !important;
        color: #0f172a !important;
        background: transparent !important;
    }
}
</style>

<!-- Controls & Filter Bar (Hidden when printing) -->
<div class="no-print flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-file-invoice-dollar text-emerald-400"></i>
            <span>فاکتور و ریز صورت‌حساب ماهانه: <span class="font-mono text-cyan-300"><?= htmlspecialchars($reseller['username']) ?></span></span>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono"><?= htmlspecialchars($month) ?></span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">تفکیک پلن‌های فروخته شده، مشتریان زیرمجموعه و محاسبه کل مبالغ بر مبنای نرخ همکاری</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <form method="GET" action="<?= $isAdmin ? Helpers::url('resellers/invoice') : Helpers::url('reseller/invoice') ?>" class="m-0 flex items-center gap-2">
            <?php if ($isAdmin): ?>
                <select name="id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                    <?php foreach ($allResellers as $ar): ?>
                        <option value="<?= $ar['id'] ?>" <?= ($reseller['id'] === (int)$ar['id']) ? 'selected' : '' ?>>
                            👤 <?= htmlspecialchars($ar['username']) ?> (<?= htmlspecialchars($ar['brand_name'] ?: $ar['full_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="month" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono">
                <?php foreach ($monthsList as $mItem): ?>
                    <option value="<?= $mItem['val'] ?>" <?= ($month === $mItem['val']) ? 'selected' : '' ?>>
                        📅 <?= $mItem['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <a href="<?= $exportUrl ?>" class="px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-semibold rounded-xl border border-emerald-500/30 transition flex items-center gap-1.5" title="دانلود ریز صورت‌حساب در قالب فایل اکسل / CSV">
            <i class="fa-solid fa-file-csv text-emerald-400"></i>
            <span>خروجی اکسل (CSV)</span>
        </a>

        <button onclick="window.print()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-1.5" title="چاپ یا ذخیره به عنوان PDF">
            <i class="fa-solid fa-print"></i>
            <span>چاپ / PDF فاکتور</span>
        </button>

        <?php if ($isAdmin): ?>
            <a href="<?= Helpers::url('resellers') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-right"></i>
                <span>بازگشت به نمایندگان</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Main Invoice Sheet (Printable Document) -->
<div class="print-sheet bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 shadow-xl text-xs space-y-6">

    <!-- Invoice Header & Brand -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-cyan-500 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                <i class="fa-solid fa-file-invoice text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-white tracking-wide">صورت‌حساب ماهانه نماینده</h1>
                <p class="text-xs text-slate-400 mt-0.5">پلتفرم مدیریت شبکه و زیرساخت کانکتیکس (Connectix Panel)</p>
            </div>
        </div>

        <div class="text-left sm:text-left font-mono">
            <div class="text-xs text-slate-400">شماره فاکتور:</div>
            <div class="text-base font-bold text-emerald-400 tracking-wider"><?= $invoiceNumber ?></div>
            <div class="text-[11px] text-slate-400 mt-1">دوره صورت‌حساب: <span class="text-white font-semibold"><?= $month ?></span></div>
            <div class="text-[11px] text-slate-500">تاریخ صدور: <?= date('Y-m-d') ?></div>
        </div>
    </div>

    <!-- Parties Info (Seller & Buyer / Reseller) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Reseller (Buyer) Card -->
        <div class="bg-slate-950/60 border border-slate-800 rounded-2xl p-4 space-y-2">
            <div class="text-xs font-bold text-cyan-400 flex items-center gap-1.5 pb-2 border-b border-slate-800/80">
                <i class="fa-solid fa-user-tie"></i>
                <span>مشخصات نماینده همکار (مشتری)</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span class="text-slate-500 text-[11px] block">نام و نام خانوادگی:</span>
                    <span class="text-white font-semibold"><?= htmlspecialchars($reseller['full_name'] ?: 'ثبت نشده') ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">نام کاربری:</span>
                    <span class="font-mono text-cyan-300 font-bold"><?= htmlspecialchars($reseller['username']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">برند تجاری / تگ:</span>
                    <span class="text-slate-300 font-medium"><?= htmlspecialchars($reseller['brand_name'] ?: 'پیش‌فرض') ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">درصد تخفیف همکاری:</span>
                    <span class="text-emerald-400 font-mono font-bold"><?= (int)$reseller['discount_percent'] ?>%</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">سقف اعتبار بدهی:</span>
                    <span class="text-purple-300 font-mono"><?= Helpers::formatMoney((int)$reseller['credit_limit']) ?></span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">موجودی فعلی کیف‌پول:</span>
                    <span class="font-mono font-bold <?= $reseller['wallet_balance'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= Helpers::formatMoney((int)$reseller['wallet_balance']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Issuer Details -->
        <div class="bg-slate-950/60 border border-slate-800 rounded-2xl p-4 space-y-2">
            <div class="text-xs font-bold text-emerald-400 flex items-center gap-1.5 pb-2 border-b border-slate-800/80">
                <i class="fa-solid fa-shield-halved"></i>
                <span>صادرکننده صورت‌حساب</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span class="text-slate-500 text-[11px] block">واحد صدور:</span>
                    <span class="text-white font-semibold">مدیریت مالی سرور و شبکه</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">نوع فاکتور:</span>
                    <span class="text-slate-300">صورت‌حساب رسمی ماهانه همکار</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">واحد پول:</span>
                    <span class="text-amber-400 font-bold">تومان ایران (IRT)</span>
                </div>
                <div>
                    <span class="text-slate-500 text-[11px] block">وضعیت حساب:</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $reseller['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' ?>">
                        <?= $reseller['status'] === 'active' ? 'حساب فعال' : 'معلق' ?>
                    </span>
                </div>
                <div class="col-span-2 pt-1 border-t border-slate-800/60 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>مجموع واریزی‌های ثبت‌شده این ماه:</span>
                    <span class="text-emerald-400 font-mono font-bold"><?= Helpers::formatMoney($totalDeposited) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl">
            <span class="text-slate-500 text-[11px] block">سفارش‌ها / کلاینت‌ها</span>
            <span class="text-xl font-bold text-white font-mono mt-0.5 block"><?= count($clients) ?> اکانت</span>
        </div>
        <div class="bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl">
            <span class="text-slate-500 text-[11px] block">ترافیک اختصاصی کل</span>
            <span class="text-xl font-bold text-cyan-400 font-mono mt-0.5 block"><?= $totalGbAllocated ?> GB</span>
        </div>
        <div class="bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl">
            <span class="text-slate-500 text-[11px] block">مجموع مبلغ ناخالص</span>
            <span class="text-xl font-bold text-slate-300 font-mono mt-0.5 block"><?= Helpers::formatMoney($totalGross) ?></span>
        </div>
        <div class="bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl">
            <span class="text-slate-500 text-[11px] block">تخفیف همکاری اعمال‌شده</span>
            <span class="text-xl font-bold text-amber-400 font-mono mt-0.5 block"><?= Helpers::formatMoney($totalDiscount) ?></span>
        </div>
        <div class="bg-emerald-950/30 border border-emerald-500/30 p-3.5 rounded-2xl col-span-2 md:col-span-1">
            <span class="text-emerald-400 text-[11px] font-bold block">مبلغ نهایی صورت‌حساب</span>
            <span class="text-xl font-black text-emerald-400 font-mono mt-0.5 block"><?= Helpers::formatMoney($totalNet) ?></span>
        </div>
    </div>

    <!-- SECTION 1: Plan-by-Plan Aggregation Breakdown -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-purple-400"></i>
                <span>تفکیک و آمار پلن‌های خریداری شده در دوره (Plan Breakdown)</span>
            </h3>
            <span class="text-[11px] text-slate-400"><?= count($planBreakdown) ?> پلن مختلف</span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-800">
            <table class="print-table w-full text-right text-xs">
                <thead class="bg-slate-800/80 text-slate-300 font-semibold border-b border-slate-800">
                    <tr>
                        <th class="p-3">عنوان پلن</th>
                        <th class="p-3 text-center">مشخصات (حجم / مدت)</th>
                        <th class="p-3 text-center">تعداد فروخته‌شده</th>
                        <th class="p-3 text-center">نرخ واحد نماینده (تومان)</th>
                        <th class="p-3 text-left">مجموع ردیف (تومان)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-slate-950/40">
                    <?php if (empty($planBreakdown)): ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500">
                                هیچ سرویسی در دوره ماهانه انتخابی برای این نماینده ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($planBreakdown as $pb): ?>
                            <tr class="hover:bg-slate-800/20 transition">
                                <td class="p-3 font-bold text-white"><?= htmlspecialchars($pb['title']) ?></td>
                                <td class="p-3 text-center font-mono text-slate-300">
                                    <?= $pb['traffic_gb'] > 0 ? $pb['traffic_gb'] . ' GB' : 'نامحدود' ?> / <?= $pb['duration_days'] > 0 ? $pb['duration_days'] . ' روز' : 'دائمی' ?>
                                </td>
                                <td class="p-3 text-center font-mono font-bold text-cyan-300"><?= $pb['count'] ?> عدد</td>
                                <td class="p-3 text-center font-mono text-slate-300"><?= number_format($pb['unit_price']) ?> تومان</td>
                                <td class="p-3 text-left font-mono font-bold text-emerald-400"><?= number_format($pb['total_amount']) ?> تومان</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-slate-800/50 font-bold border-t border-slate-700">
                            <td class="p-3 text-white" colspan="2">مجموع کل پلن‌ها</td>
                            <td class="p-3 text-center font-mono text-cyan-300"><?= count($clients) ?></td>
                            <td class="p-3 text-center text-slate-400">—</td>
                            <td class="p-3 text-left font-mono text-emerald-400"><?= Helpers::formatMoney($totalNet) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 2: Itemized Customer Rows (ریز صورت‌حساب مشتریان) -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-400"></i>
                <span>ریز فهرست مشتریان و اکانت‌های دوره (Itemized Customer Roster)</span>
            </h3>
            <span class="text-[11px] text-slate-400"><?= count($clients) ?> سطر صورت‌حساب</span>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-800">
            <table class="print-table w-full text-right text-xs">
                <thead class="bg-slate-800/80 text-slate-300 font-semibold border-b border-slate-800">
                    <tr>
                        <th class="p-2.5 text-center w-12">#</th>
                        <th class="p-2.5">نام و نام خانوادگی خریدار</th>
                        <th class="p-2.5">نام کاربری اکانت</th>
                        <th class="p-2.5">پلن سرویس</th>
                        <th class="p-2.5">سرور / نود</th>
                        <th class="p-2.5 text-center">حجم مجاز</th>
                        <th class="p-2.5 text-center">تاریخ صدور</th>
                        <th class="p-2.5 text-center">تاریخ انقضا</th>
                        <th class="p-2.5 text-center">وضعیت</th>
                        <th class="p-2.5 text-left">مبلغ صورت‌حساب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-slate-950/40 font-mono text-[11px]">
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-500 font-sans">
                                هیچ مشتری‌ای در دوره زمانی <span class="font-mono text-slate-400"><?= htmlspecialchars($month) ?></span> برای این نماینده ثبت نگردیده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = 1; foreach ($clients as $c): 
                            $cLimitGb = round(($c['traffic_limit_bytes'] ?? 0) / (1024 * 1024 * 1024), 1);
                            $cUsedGb = round(($c['traffic_used_bytes'] ?? 0) / (1024 * 1024 * 1024), 2);
                            $isExpired = ($c['status'] === 'expired') || (!empty($c['expire_at']) && strtotime($c['expire_at']) < time());
                        ?>
                            <tr class="hover:bg-slate-800/20 transition">
                                <td class="p-2.5 text-center text-slate-500"><?= $rowNum++ ?></td>
                                <td class="p-2.5 font-sans font-bold text-cyan-300">
                                    <?php if (!empty($c['customer_name'])): ?>
                                        <div class="flex items-center gap-1">
                                            <i class="fa-solid fa-user text-[10px] text-cyan-400/80"></i>
                                            <span><?= htmlspecialchars($c['customer_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-500 font-normal">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5 font-bold text-white"><?= htmlspecialchars($c['username']) ?></td>
                                <td class="p-2.5 font-sans text-slate-300"><?= htmlspecialchars($c['plan_title'] ?? 'سفارشی') ?></td>
                                <td class="p-2.5 font-sans text-slate-400"><?= htmlspecialchars($c['server_name'] ?? '—') ?></td>
                                <td class="p-2.5 text-center text-slate-200"><?= $cLimitGb > 0 ? $cLimitGb . ' GB' : 'نامحدود' ?></td>
                                <td class="p-2.5 text-center text-slate-400"><?= substr($c['created_at'], 0, 10) ?></td>
                                <td class="p-2.5 text-center text-slate-400"><?= !empty($c['expire_at']) ? substr($c['expire_at'], 0, 10) : '∞' ?></td>
                                <td class="p-2.5 text-center">
                                    <?php if ($c['status'] === 'active' && !$isExpired): ?>
                                        <span class="print-badge px-2 py-0.5 rounded-full text-[10px] bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">فعال</span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="print-badge px-2 py-0.5 rounded-full text-[10px] bg-rose-500/15 text-rose-400 border border-rose-500/30">منقضی</span>
                                    <?php elseif ($c['status'] === 'limited'): ?>
                                        <span class="print-badge px-2 py-0.5 rounded-full text-[10px] bg-amber-500/15 text-amber-400 border border-amber-500/30">پایان حجم</span>
                                    <?php else: ?>
                                        <span class="print-badge px-2 py-0.5 rounded-full text-[10px] bg-slate-800 text-slate-400 border border-slate-700"><?= htmlspecialchars($c['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5 text-left font-bold text-emerald-400 font-mono">
                                    <?= number_format($c['calculated_unit_price'] ?? 0) ?> ت
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Official Signature / Seal Block for Print & Formal Records -->
    <div class="border-t border-slate-800 pt-6 mt-8 grid grid-cols-2 gap-8 text-xs">
        <div class="space-y-2">
            <span class="text-slate-400 font-semibold block">توضیحات و شرایط صورت‌حساب:</span>
            <p class="text-slate-500 text-[11px] leading-relaxed">
                کلیه مبالغ فوق بر اساس نرخ مصوب پنل همکاری با احتساب <?= (int)$reseller['discount_percent'] ?>٪ تخفیف نماینده محاسبه گردیده است. در صورت وجود هرگونه مغایرت ظرف ۴۸ ساعت به پشتیبانی مدیریت اطلاع داده شود.
            </p>
        </div>
        <div class="flex flex-col items-end justify-between space-y-4">
            <div class="text-left font-mono">
                <span class="text-slate-400 text-[11px] block">مهر و امضای امور مالی پنل:</span>
                <div class="w-36 h-16 border-2 border-dashed border-slate-700 rounded-xl mt-2 flex items-center justify-center text-[10px] text-slate-500">
                    محل امضا و تایید
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

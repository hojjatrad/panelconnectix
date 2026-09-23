<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-tags text-indigo-400"></i>
                <span>کاتالوگ، دسته‌بندی و قیمت‌گذاری اختصاصی محصولات من</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">
                درصد تخفیف همکاری شما: <b class="text-purple-400 font-bold"><?= $discount ?>٪</b> | شما می‌توانید عنوان، دسته‌بندی و قیمت دلخواه فروش خود به مشتری را تعیین کنید.
            </p>
        </div>
    </div>

    <!-- Plans Table Form -->
    <form action="<?= Helpers::url('reseller/plans') ?>" method="POST" class="space-y-4">
        <?= Helpers::csrfField() ?>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                        <tr>
                            <th class="p-3.5 font-semibold">وضعیت نمایش</th>
                            <th class="p-3.5 font-semibold">مشخصات پایه پلن</th>
                            <th class="p-3.5 font-semibold">دسته‌بندی اختصاصی</th>
                            <th class="p-3.5 font-semibold">عنوان اختصاصی نمایشی</th>
                            <th class="p-3.5 font-semibold">قیمت خرید عمده شما</th>
                            <th class="p-3.5 font-semibold">قیمت فروش شما به مشتری</th>
                            <th class="p-3.5 font-semibold text-center">سود خالص شما</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70">
                        <?php foreach ($plans as $p): ?>
                            <?php 
                            $wholesaleCost = (int)round($p['base_price'] * (1 - ($discount / 100)));
                            $retailPrice = !empty($p['retail_price']) ? (int)$p['retail_price'] : (int)$p['base_price'];
                            $profit = max(0, $retailPrice - $wholesaleCost);
                            $displayTitle = !empty($p['custom_title']) ? $p['custom_title'] : $p['title'];
                            $displayCategory = !empty($p['custom_category']) ? $p['custom_category'] : 'پیش‌فرض';
                            $isActive = isset($p['reseller_active']) ? ((int)$p['reseller_active'] === 1) : true;
                            ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-3.5 text-center">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="plans[<?= $p['id'] ?>][is_active]" value="1" <?= $isActive ? 'checked' : '' ?> class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </td>

                                <td class="p-3.5">
                                    <span class="font-bold text-white block"><?= htmlspecialchars($p['title']) ?></span>
                                    <span class="text-[11px] text-slate-400">
                                        <?php
                                        $tr = (float)$p['traffic_gb'];
                                        $trTxt = ($tr > 0 && $tr < 1) ? round($tr * 1024) . ' مگابایت' : (($tr == (int)$tr ? (int)$tr : $tr) . ' گیگابایت');
                                        ?>
                                        <?= $trTxt ?> | <?= $p['duration_days'] ?> روزه
                                    </span>
                                </td>

                                <td class="p-3.5">
                                    <input type="text" name="plans[<?= $p['id'] ?>][custom_category]" value="<?= htmlspecialchars($displayCategory) ?>" 
                                           placeholder="مثال: اقتصادی یا VIP"
                                           class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs text-slate-200 w-32 focus:border-indigo-500 focus:outline-none">
                                </td>

                                <td class="p-3.5">
                                    <input type="text" name="plans[<?= $p['id'] ?>][custom_title]" value="<?= htmlspecialchars($displayTitle) ?>" 
                                           class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs text-white w-48 focus:border-indigo-500 focus:outline-none">
                                </td>

                                <td class="p-3.5">
                                    <span class="font-mono font-bold text-slate-300"><?= number_format($wholesaleCost) ?></span>
                                    <span class="text-[10px] text-slate-400">تومان</span>
                                </td>

                                <td class="p-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <input type="number" step="1000" name="plans[<?= $p['id'] ?>][retail_price]" value="<?= $retailPrice ?>" 
                                               oninput="calcProfit(this, <?= $wholesaleCost ?>, 'profit_<?= $p['id'] ?>')"
                                               class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs font-mono font-bold text-emerald-400 w-32 focus:border-indigo-500 focus:outline-none text-left" dir="ltr">
                                        <span class="text-[10px] text-slate-400">تومان</span>
                                    </div>
                                </td>

                                <td class="p-3.5 text-center">
                                    <span id="profit_<?= $p['id'] ?>" class="font-mono font-extrabold text-xs text-cyan-400">
                                        +<?= number_format($profit) ?> تومان
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-slate-800/40 border-t border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-400">
                    <i class="fa-solid fa-circle-info text-cyan-400 ml-1"></i> قیمت‌ها به صورت زنده در ربات تلگرام و فاکتورهای مشتری شما اعمال خواهند شد.
                </span>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-indigo-900/30 flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>ذخیره تمامی تغییرات قیمت و کاتالوگ</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function calcProfit(input, wholesaleCost, profitElId) {
    let val = parseInt(input.value) || 0;
    let profit = Math.max(0, val - wholesaleCost);
    let el = document.getElementById(profitElId);
    if (el) {
        el.innerText = '+' + profit.toLocaleString('fa-IR') + ' تومان';
    }
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

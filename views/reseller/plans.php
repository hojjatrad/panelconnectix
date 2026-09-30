<?php
require __DIR__ . '/../layout/header.php';
$perms = $resellerPerms ?? ['allow_custom_plans'=>1,'allow_price_edit'=>1,'max_custom_plans'=>10,'allowed_servers'=>null];
$allowCustom = (int)($perms['allow_custom_plans'] ?? 1) === 1;
$allowPrice = (int)($perms['allow_price_edit'] ?? 1) === 1;
$maxCustom = (int)($perms['max_custom_plans'] ?? 10);
$customCount = count($customPlans ?? []);
?>
<div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-gradient-to-br from-slate-900 via-indigo-950/20 to-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center"><i class="fa-solid fa-store text-white"></i></span>
                    <span>فروشگاه من — مدیریت پلن‌های اختصاصی</span>
                    <span class="px-2.5 py-1 bg-emerald-600/20 text-emerald-300 border border-emerald-700/40 rounded-full text-[10px]">FULL CONTROL v5.6.3</span>
                </h2>
                <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                    تخفیف شما: <b class="text-violet-400 font-bold text-sm"><?= $discount ?>٪</b> — 
                    <?php if ($allowCustom && $allowPrice): ?>
                        هم پلن‌های اصلی را با قیمت دلخواه بفروشید و هم <b class="text-emerald-300">پلن اختصاصی خودتان</b> را بسازید (سقف <?= $maxCustom ?> عدد، <?= $customCount ?> ساخته شده).
                    <?php elseif ($allowCustom): ?>
                        <b class="text-amber-300">فقط ساخت پلن اختصاصی فعال است</b> — قیمت پلن‌های پایه توسط مدیریت قفل شده.
                    <?php elseif ($allowPrice): ?>
                        <b class="text-cyan-300">فقط ویرایش قیمت پلن‌های پایه فعال است</b> — ساخت پلن اختصاصی توسط مدیریت غیرفعال شده.
                    <?php else: ?>
                        <b class="text-rose-300">⛔ هر دو دسترسی توسط مدیریت غیرفعال شده</b> — برای فعال‌سازی با مدیریت تماس بگیرید.
                    <?php endif; ?>
                    <?php if (!empty($servers) && count($servers) < count($allServers ?? $servers)): ?>
                        <br><span class="text-[11px] text-amber-300"><i class="fa-solid fa-server ml-1"></i> سرورهای مجاز شما محدود شده: <?= implode('، ', array_map(fn($s)=>$s['name'], $servers)) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (!$allowCustom): ?>
                    <div class="mt-2 p-2.5 bg-rose-950/30 border border-rose-800/40 rounded-xl text-[11px] text-rose-300"><i class="fa-solid fa-lock ml-1"></i> ساخت پلن اختصاصی توسط ادمین غیرفعال شده. فقط قیمت‌گذاری پایه (اگر فعال باشد) قابل استفاده است.</div>
                <?php endif; ?>
                <?php if (!$allowPrice): ?>
                    <div class="mt-2 p-2.5 bg-amber-950/30 border border-amber-800/40 rounded-xl text-[11px] text-amber-300"><i class="fa-solid fa-lock ml-1"></i> ویرایش قیمت پلن‌های پایه توسط ادمین غیرفعال شده.</div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-2.5 text-center">
                    <div class="text-[10px] text-slate-400">پلن پایه</div>
                    <div class="text-lg font-black text-white"><?= $stats['base_count'] ?? count($plans) ?></div>
                </div>
                <div class="bg-emerald-950/30 border border-emerald-800/40 rounded-xl px-4 py-2.5 text-center">
                    <div class="text-[10px] text-emerald-400">اختصاصی من</div>
                    <div class="text-lg font-black text-emerald-300"><?= $stats['custom_count'] ?? 0 ?> <span class="text-[10px] font-normal">/ <?= $maxCustom ?> — فعال <?= $stats['active_custom'] ?? 0 ?></span></div>
                </div>
                <?php if ($allowCustom): ?>
                    <button onclick="openCreateCustomModal()" class="px-5 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-emerald-900/30 flex items-center gap-2 <?= $customCount >= $maxCustom ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $customCount >= $maxCustom ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-plus"></i><span>ساخت پلن اختصاصی</span>
                    </button>
                <?php else: ?>
                    <span class="px-4 py-3 bg-slate-800 border border-slate-700 text-slate-400 rounded-xl text-xs"><i class="fa-solid fa-lock ml-1"></i> ساخت غیرفعال</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-2 bg-slate-900/60 border border-slate-800 rounded-xl p-1.5 w-fit">
        <button id="tabBase" onclick="switchTab('base')" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-lg text-xs flex items-center gap-2"><i class="fa-solid fa-layer-group"></i> پلن‌های پایه (شخصی‌سازی قیمت)</button>
        <button id="tabCustom" onclick="switchTab('custom')" class="px-5 py-2.5 bg-transparent text-slate-400 hover:text-white font-bold rounded-lg text-xs flex items-center gap-2"><i class="fa-solid fa-star text-amber-400"></i> پلن‌های اختصاصی من (<?= $stats['custom_count'] ?? 0 ?>)</button>
    </div>

    <!-- Base Plans Section -->
    <div id="sectionBase" class="space-y-4">
        <form action="<?= Helpers::url('reseller/plans') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 bg-gradient-to-r from-indigo-950/40 to-slate-900 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-tags text-indigo-400"></i> کاتالوگ پایه — قیمت‌گذاری اختصاصی شما</h3>
                    <span class="text-[10px] text-slate-400">قیمت‌ها زنده در ربات اعمال می‌شود</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3.5 font-semibold">نمایش</th>
                                <th class="p-3.5 font-semibold">پلن پایه</th>
                                <th class="p-3.5 font-semibold">دسته من</th>
                                <th class="p-3.5 font-semibold">عنوان من</th>
                                <th class="p-3.5 font-semibold">خرید شما</th>
                                <th class="p-3.5 font-semibold">فروش شما</th>
                                <th class="p-3.5 font-semibold text-center">سود</th>
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
                                            <?= $trTxt ?> | <?= $p['duration_days'] ?> روزه | <?= (int)($p['ip_limit'] ?? 0) >0 ? (int)$p['ip_limit'].' کاربره' : 'نامحدود' ?>
                                        </span>
                                    </td>
                                    <td class="p-3.5">
                                        <input type="text" name="plans[<?= $p['id'] ?>][custom_category]" value="<?= htmlspecialchars($displayCategory) ?>" placeholder="اقتصادی / VIP" class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs text-slate-200 w-28 focus:border-indigo-500 focus:outline-none <?= $allowPrice ? '' : 'opacity-50' ?>" <?= $allowPrice ? '' : 'readonly' ?>>
                                    </td>
                                    <td class="p-3.5">
                                        <input type="text" name="plans[<?= $p['id'] ?>][custom_title]" value="<?= htmlspecialchars($displayTitle) ?>" class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs text-white w-44 focus:border-indigo-500 focus:outline-none <?= $allowPrice ? '' : 'opacity-50' ?>" <?= $allowPrice ? '' : 'readonly' ?>>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="font-mono font-bold text-slate-300"><?= number_format($wholesaleCost) ?></span><span class="text-[10px] text-slate-400"> ت</span>
                                    </td>
                                    <td class="p-3.5">
                                        <div class="flex items-center gap-1">
                                            <input type="number" step="1000" name="plans[<?= $p['id'] ?>][retail_price]" value="<?= $retailPrice ?>" oninput="calcProfit(this, <?= $wholesaleCost ?>, 'profit_<?= $p['id'] ?>')" class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-xs font-mono font-bold text-emerald-400 w-28 focus:border-indigo-500 focus:outline-none text-left <?= $allowPrice ? '' : 'opacity-50' ?>" dir="ltr" <?= $allowPrice ? '' : 'readonly' ?>>
                                            <span class="text-[10px] text-slate-400">ت</span>
                                        </div>
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <span id="profit_<?= $p['id'] ?>" class="font-mono font-extrabold text-xs text-cyan-400">+<?= number_format($profit) ?> ت</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 bg-slate-800/40 border-t border-slate-800 flex items-center justify-between">
                    <span class="text-xs text-slate-400">
                        <?php if ($allowPrice): ?>
                            <i class="fa-solid fa-circle-info text-cyan-400 ml-1"></i> تغییرات بلافاصله در ربات تلگرام شما اعمال می‌شود.
                        <?php else: ?>
                            <i class="fa-solid fa-lock text-rose-400 ml-1"></i> ویرایش قیمت توسط مدیریت غیرفعال شده.
                        <?php endif; ?>
                    </span>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold rounded-xl text-xs shadow-md flex items-center gap-2 <?= $allowPrice ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= $allowPrice ? '' : 'disabled' ?>><i class="fa-solid fa-check"></i><span>ذخیره قیمت‌های پایه</span></button>
                </div>
            </div>
        </form>
    </div>

    <!-- Custom Plans Section -->
    <div id="sectionCustom" class="space-y-4 hidden">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 bg-gradient-to-r from-emerald-950/40 to-slate-900 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-star text-amber-400"></i> پلن‌های اختصاصی من — محصولاتی که خودت می‌سازی</h3>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] bg-emerald-600/20 text-emerald-300 border border-emerald-700/30 px-2.5 py-1 rounded-full">⭐ در ربات با ستاره نمایش داده می‌شود</span>
                    <button onclick="openCreateCustomModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-[11px] flex items-center gap-1.5"><i class="fa-solid fa-plus"></i> ساخت جدید</button>
                </div>
            </div>

            <?php if (empty($customPlans)): ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800 flex items-center justify-center text-2xl text-slate-500 mb-4"><i class="fa-solid fa-box-open"></i></div>
                    <div class="text-sm font-bold text-white">هنوز پلن اختصاصی نساختی</div>
                    <div class="text-xs text-slate-400 mt-1 max-w-md mx-auto leading-relaxed">مثلاً می‌توانی پلن 15GB - 45 روزه - 4 کاربره با قیمت 95,000 تومان بسازی. این پلن فقط در ربات تو نمایش داده می‌شود و سود کامل برای توست.</div>
                    <button onclick="openCreateCustomModal()" class="mt-4 px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-xl text-xs">+ ساخت اولین پلن اختصاصی</button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3.5">وضعیت</th>
                                <th class="p-3.5">عنوان اختصاصی ⭐</th>
                                <th class="p-3.5">دسته</th>
                                <th class="p-3.5">حجم / روز / اتصال</th>
                                <th class="p-3.5">سرور</th>
                                <th class="p-3.5">قیمت تمام شده</th>
                                <th class="p-3.5">قیمت فروش</th>
                                <th class="p-3.5">سود</th>
                                <th class="p-3.5 text-center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/70">
                            <?php foreach ($customPlans as $cp): 
                                $profit = max(0, (int)$cp['retail_price'] - (int)($cp['base_cost'] ?? 0));
                                $isActive = (int)($cp['is_active'] ?? 1) === 1;
                            ?>
                                <tr class="hover:bg-slate-800/30 transition-colors <?= $isActive ? '' : 'opacity-50' ?>">
                                    <td class="p-3.5"><span class="px-2 py-1 rounded-full text-[10px] font-bold <?= $isActive ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-700/30' : 'bg-slate-700 text-slate-400' ?>"><?= $isActive ? 'فعال' : 'غیرفعال' ?></span></td>
                                    <td class="p-3.5"><span class="font-bold text-white"><?= htmlspecialchars($cp['custom_title']) ?></span><?php if (!empty($cp['description'])): ?><div class="text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars(mb_substr($cp['description'],0,60)) ?></div><?php endif; ?></td>
                                    <td class="p-3.5"><span class="px-2 py-1 bg-indigo-600/20 text-indigo-300 border border-indigo-700/30 rounded-full text-[10px]"><?= htmlspecialchars($cp['custom_category']) ?></span></td>
                                    <td class="p-3.5"><span class="font-mono text-white"><?= (float)$cp['traffic_gb'] ?>GB / <?= (int)$cp['duration_days'] ?>روز / <?= (int)($cp['ip_limit'] ?? 4) ?>نفره</span></td>
                                    <td class="p-3.5 text-slate-300"><?php 
                                        $srvName = 'خودکار';
                                        foreach (($servers ?? []) as $sv) { if ((int)$sv['id'] === (int)($cp['server_id'] ?? 0)) { $srvName = $sv['name']; break; } }
                                        echo htmlspecialchars($srvName);
                                    ?></td>
                                    <td class="p-3.5 font-mono text-slate-400"><?= number_format((int)($cp['base_cost'] ?? 0)) ?> ت</td>
                                    <td class="p-3.5 font-mono font-bold text-emerald-400"><?= number_format((int)$cp['retail_price']) ?> ت</td>
                                    <td class="p-3.5 font-mono font-bold text-cyan-400">+<?= number_format($profit) ?> ت</td>
                                    <td class="p-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button onclick='openEditCustomModal(<?= json_encode($cp, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="w-7 h-7 bg-slate-800 hover:bg-amber-900/40 text-amber-300 rounded-lg border border-slate-700 flex items-center justify-center"><i class="fa-solid fa-pen text-[10px]"></i></button>
                                            <form action="<?= Helpers::url('reseller/custom-plans/delete') ?>" method="POST" onsubmit="return confirm('حذف پلن اختصاصی؟')" class="inline">
                                                <?= Helpers::csrfField() ?>
                                                <input type="hidden" name="custom_id" value="<?= $cp['id'] ?>">
                                                <button type="submit" class="w-7 h-7 bg-slate-800 hover:bg-red-900/40 text-red-300 rounded-lg border border-slate-700 flex items-center justify-center"><i class="fa-solid fa-trash text-[10px]"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- How it works -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5">
            <h4 class="text-xs font-bold text-white mb-3 flex items-center gap-2"><i class="fa-solid fa-lightbulb text-amber-400"></i> این بخش چطور کار می‌کند؟ (حرفه‌ای)</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px] text-slate-300 leading-relaxed">
                <div class="bg-slate-800/40 rounded-xl p-3 border border-slate-700/50"><b class="text-emerald-300">۱. ساخت محصول اختصاصی:</b> حجم دلخواه (مثلاً 20GB)، مدت (30-90 روز)، سقف اتصال (پیشفرض ۴ نفره VIP)، قیمت فروش خودت، دسته‌بندی (اقتصادی/VIP) و سرور مقصد را انتخاب می‌کنی.</div>
                <div class="bg-slate-800/40 rounded-xl p-3 border border-slate-700/50"><b class="text-cyan-300">۲. نمایش در ربات:</b> پلن اختصاصی با ⭐ در ربات تلگرام تو ظاهر می‌شود. مشتری همان قیمت تو را می‌بیند، سفارش می‌دهد، تو تایید می‌کنی و سود کامل (فروش - هزینه تمام شده) برای توست.</div>
                <div class="bg-slate-800/40 rounded-xl p-3 border border-slate-700/50"><b class="text-violet-300">۳. مدیریت حرفه‌ای:</b> می‌توانی پلن را ویرایش، غیرفعال یا حذف کنی، قیمت تمام شده (خرید عمده از مدیر) را جدا ثبت کنی تا سود دقیق حساب شود.</div>
            </div>
        </div>
    </div>
</div>

<!-- Create Custom Plan Modal -->
<div id="createCustomModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <button type="button" onclick="closeCreateCustomModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-star text-amber-400"></i> ساخت پلن اختصاصی جدید — محصول خودت</h3>
        <form action="<?= Helpers::url('reseller/custom-plans/create') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1 font-semibold">عنوان پلن * (نمایشی در ربات)</label><input type="text" name="custom_title" required placeholder="مثلا: 15 گیگ اقتصادی 45 روزه" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white focus:border-emerald-500 focus:outline-none"></div>
                <div><label class="block text-slate-300 mb-1">دسته‌بندی</label><select name="custom_category" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="اقتصادی">اقتصادی</option><option value="VIP">VIP</option><option value="ایران اکسس">ایران اکسس</option><option value="نامحدود">نامحدود</option><option value="پیش‌فرض">پیش‌فرض</option></select></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-slate-300 mb-1">حجم GB *</label><input type="number" step="0.1" name="traffic_gb" required value="10" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center font-mono"></div>
                <div><label class="block text-slate-300 mb-1">مدت روز *</label><input type="number" name="duration_days" required value="30" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center font-mono"></div>
                <div><label class="block text-slate-300 mb-1">سقف اتصال (VIP: 4)</label><input type="number" name="ip_limit" value="4" min="1" max="10" class="w-full bg-slate-800 border border-emerald-700/50 rounded-xl p-2.5 text-emerald-300 text-center font-mono font-bold"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">قیمت فروش شما به مشتری * (تومان)</label><input type="number" step="1000" name="retail_price" required placeholder="95000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-emerald-400 font-mono font-bold text-left" dir="ltr"></div>
                <div><label class="block text-slate-300 mb-1">قیمت تمام شده شما (خرید از مدیر) (تومان)</label><input type="number" step="1000" name="base_cost" placeholder="مثلا 50000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-slate-300 font-mono text-left" dir="ltr"><span class="text-[10px] text-slate-500">برای محاسبه سود دقیق</span></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">سرور مقصد (اختیاری)</label><select name="server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="">خودکار (بهترین)</option><?php foreach (($servers ?? []) as $sv): ?><option value="<?= $sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?> (<?= $sv['driver'] ?>)</option><?php endforeach; ?></select></div>
                <div><label class="block text-slate-300 mb-1">توضیح کوتاه (اختیاری)</label><input type="text" name="description" placeholder="مناسب استریم و..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeCreateCustomModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl">انصراف</button>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold rounded-xl flex items-center gap-2"><i class="fa-solid fa-plus"></i> ساخت پلن اختصاصی</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Custom Plan Modal -->
<div id="editCustomModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <button type="button" onclick="closeEditCustomModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-pen text-amber-400"></i> ویرایش پلن اختصاصی</h3>
        <form action="<?= Helpers::url('reseller/custom-plans/update') ?>" method="POST" class="space-y-4 text-xs" id="editCustomForm">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="custom_id" id="edit_custom_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">عنوان</label><input type="text" name="custom_title" id="edit_custom_title" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></div>
                <div><label class="block text-slate-300 mb-1">دسته</label><select name="custom_category" id="edit_custom_category" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="اقتصادی">اقتصادی</option><option value="VIP">VIP</option><option value="ایران اکسس">ایران اکسس</option><option value="نامحدود">نامحدود</option><option value="پیش‌فرض">پیش‌فرض</option></select></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-slate-300 mb-1">حجم GB</label><input type="number" step="0.1" name="traffic_gb" id="edit_traffic_gb" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center font-mono"></div>
                <div><label class="block text-slate-300 mb-1">مدت روز</label><input type="number" name="duration_days" id="edit_duration_days" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center font-mono"></div>
                <div><label class="block text-slate-300 mb-1">سقف اتصال</label><input type="number" name="ip_limit" id="edit_ip_limit" class="w-full bg-slate-800 border border-emerald-700/50 rounded-xl p-2.5 text-emerald-300 text-center font-mono"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">قیمت فروش</label><input type="number" step="1000" name="retail_price" id="edit_retail_price" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-emerald-400 font-mono text-left" dir="ltr"></div>
                <div><label class="block text-slate-300 mb-1">قیمت تمام شده</label><input type="number" step="1000" name="base_cost" id="edit_base_cost" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-slate-300 font-mono text-left" dir="ltr"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">سرور</label><select name="server_id" id="edit_server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="">خودکار</option><?php foreach (($servers ?? []) as $sv): ?><option value="<?= $sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?></option><?php endforeach; ?></select></div>
                <div class="flex items-center gap-3 pt-6">
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded"><span class="text-slate-300">فعال در ربات</span></label>
                </div>
            </div>
            <div><label class="block text-slate-300 mb-1">توضیح</label><input type="text" name="description" id="edit_description" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeEditCustomModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl">انصراف</button>
                <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcProfit(input, wholesaleCost, profitElId) {
    let val = parseInt(input.value) || 0;
    let profit = Math.max(0, val - wholesaleCost);
    let el = document.getElementById(profitElId);
    if (el) el.innerText = '+' + profit.toLocaleString('fa-IR') + ' ت';
}
function switchTab(tab) {
    const baseSec = document.getElementById('sectionBase');
    const customSec = document.getElementById('sectionCustom');
    const baseTab = document.getElementById('tabBase');
    const customTab = document.getElementById('tabCustom');
    if (tab === 'base') {
        baseSec.classList.remove('hidden'); customSec.classList.add('hidden');
        baseTab.className = 'px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-lg text-xs flex items-center gap-2';
        customTab.className = 'px-5 py-2.5 bg-transparent text-slate-400 hover:text-white font-bold rounded-lg text-xs flex items-center gap-2';
    } else {
        baseSec.classList.add('hidden'); customSec.classList.remove('hidden');
        customTab.className = 'px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-lg text-xs flex items-center gap-2';
        baseTab.className = 'px-5 py-2.5 bg-transparent text-slate-400 hover:text-white font-bold rounded-lg text-xs flex items-center gap-2';
    }
}
function openCreateCustomModal() { const m=document.getElementById('createCustomModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeCreateCustomModal() { const m=document.getElementById('createCustomModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openEditCustomModal(cp) {
    document.getElementById('edit_custom_id').value = cp.id;
    document.getElementById('edit_custom_title').value = cp.custom_title || '';
    document.getElementById('edit_custom_category').value = cp.custom_category || 'اقتصادی';
    document.getElementById('edit_traffic_gb').value = cp.traffic_gb || 0;
    document.getElementById('edit_duration_days').value = cp.duration_days || 30;
    document.getElementById('edit_ip_limit').value = cp.ip_limit || 4;
    document.getElementById('edit_retail_price').value = cp.retail_price || 0;
    document.getElementById('edit_base_cost').value = cp.base_cost || 0;
    document.getElementById('edit_server_id').value = cp.server_id || '';
    document.getElementById('edit_description').value = cp.description || '';
    document.getElementById('edit_is_active').checked = ((cp.is_active ?? 1) == 1);
    const m=document.getElementById('editCustomModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeEditCustomModal() { const m=document.getElementById('editCustomModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
// Auto open custom tab if hash
if (location.hash === '#custom') switchTab('custom');
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

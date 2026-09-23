<?php
require __DIR__ . '/../layout/header.php';

// Prepare Categories Filter List
$allCategories = $allCategories ?? [];
$filterCategories = [];
foreach ($allCategories as $cat) {
    $cnt = count(array_filter($plans, function($p) use ($cat) {
        if (!empty($p['category_id']) && (int)$p['category_id'] === (int)$cat['id']) return true;
        if (!empty($p['category']) && $p['category'] === $cat['name']) return true;
        if (!empty($p['server_group']) && $p['server_group'] === $cat['slug']) return true;
        return false;
    }));
    $filterCategories[] = [
        'id' => $cat['id'],
        'key' => 'cat_' . $cat['id'],
        'name' => $cat['name'],
        'slug' => $cat['slug'],
        'icon' => $cat['icon'] ?: 'fa-cubes',
        'badge_color' => $cat['badge_color'] ?: 'purple',
        'type' => $cat['type'] ?? 'both',
        'count' => $cnt
    ];
}

// Add any legacy or custom categories in existing plans not present in categories table
$existingNames = array_column($allCategories, 'name');
$existingSlugs = array_column($allCategories, 'slug');
$customPlanCats = [];
foreach ($plans as $p) {
    $cName = $p['category'] ?? '';
    if (!empty($cName) && !in_array($cName, $existingNames) && !in_array($cName, $existingSlugs) && !in_array($cName, $customPlanCats)) {
        $customPlanCats[] = $cName;
    }
}
foreach ($customPlanCats as $idx => $custCat) {
    $cnt = count(array_filter($plans, fn($p) => ($p['category'] ?? '') === $custCat));
    $filterCategories[] = [
        'id' => 0,
        'key' => 'cust_' . $idx,
        'name' => $custCat,
        'slug' => '',
        'icon' => 'fa-tag',
        'badge_color' => 'slate',
        'type' => 'custom',
        'count' => $cnt
    ];
}
?>

<div class="space-y-6">
    <!-- Top Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-box-open text-amber-400"></i>
                <span>تعرفه‌ها، پلن‌ها و دسته‌بندی محصولات</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">مدیریت قیمت پایه، قیمت همکاری، دسته‌بندی دوره‌ها، خوشه‌های سرور و وضعیت ربات تلگرام</p>
        </div>

        <?php if (Auth::isAdmin()): ?>
            <div class="flex items-center gap-2">
                <a href="<?= Helpers::url('categories') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-purple-300 border border-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>مدیریت دسته‌بندی و خوشه‌ها</span>
                </a>
                <button onclick="openNewPlanModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>تعریف پلن جدید</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Category Filter Tabs Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs">
        <span class="text-slate-400 text-xs shrink-0 font-medium">دسته‌بندی‌ها:</span>
        <button onclick="filterCategory('all', '', '', '')" id="pill-all" class="cat-pill shrink-0 px-3 py-1.5 rounded-xl font-bold bg-purple-600 text-white border border-purple-500 shadow-sm transition flex items-center gap-1.5">
            <i class="fa-solid fa-layer-group text-[11px]"></i>
            <span>همه پلن‌ها (<?= count($plans) ?>)</span>
        </button>

        <?php foreach ($filterCategories as $fc): 
            $badgeDot = match($fc['badge_color']) {
                'amber' => 'text-amber-400',
                'blue' => 'text-blue-400',
                'emerald', 'green' => 'text-emerald-400',
                'cyan' => 'text-cyan-400',
                'rose', 'red' => 'text-rose-400',
                default => 'text-purple-400'
            };
        ?>
            <button onclick="filterCategory('<?= $fc['key'] ?>', '<?= $fc['id'] ?>', '<?= htmlspecialchars($fc['slug']) ?>', '<?= htmlspecialchars(addslashes($fc['name'])) ?>')" 
                    id="pill-<?= $fc['key'] ?>" 
                    class="cat-pill shrink-0 px-3 py-1.5 rounded-xl font-medium bg-slate-900 text-slate-300 border border-slate-800 hover:bg-slate-800 transition flex items-center gap-1.5">
                <i class="fa-solid <?= htmlspecialchars($fc['icon']) ?> <?= $badgeDot ?> text-[11px]"></i>
                <span><?= htmlspecialchars($fc['name']) ?></span>
                <span class="text-[10px] bg-slate-800/80 px-1.5 py-0.2 rounded-md font-mono text-slate-400"><?= $fc['count'] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($plans as $p): 
            $catName = $p['cluster_name'] ?: ($p['category'] ?: 'عمومی');
            $showInBot = (int)($p['show_in_bot'] ?? 1);
            $cardIcon = $p['cluster_icon'] ?: 'fa-cubes';
        ?>
            <div class="plan-card bg-slate-900/80 border <?= $p['is_free'] ? 'border-indigo-800/60' : 'border-slate-800' ?> rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden group" 
                 data-category-id="<?= $p['category_id'] ?? '' ?>" 
                 data-category-name="<?= htmlspecialchars($p['category'] ?? '') ?>"
                 data-server-group="<?= htmlspecialchars($p['server_group'] ?? '') ?>">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fa-solid <?= htmlspecialchars($cardIcon) ?>"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($p['title']) ?></h3>
                                <span class="inline-block mt-0.5 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 text-cyan-300 border border-slate-700">
                                    دسته: <?= htmlspecialchars($catName) ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <?php if ($p['is_free']): ?>
                                <span class="bg-indigo-500/20 text-indigo-300 text-[10px] font-bold px-2 py-0.5 rounded border border-indigo-500/30">
                                    تست رایگان
                                </span>
                            <?php endif; ?>
                            
                            <!-- Bot Visibility Badge -->
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded border <?= $showInBot ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-800 text-slate-500 border-slate-700' ?>" title="وضعیت نمایش در ربات تلگرام">
                                <i class="fa-brands fa-telegram"></i>
                                <?= $showInBot ? 'در ربات فعال' : 'مخفی در ربات' ?>
                            </span>
                        </div>
                    </div>

                    <div class="my-4 space-y-2 text-xs bg-slate-800/30 p-3 rounded-xl border border-slate-800">
                        <div class="flex justify-between text-slate-400">
                            <span>حجم ترافیک:</span>
                            <span class="font-bold text-white font-mono"><?= $p['traffic_gb'] ?> گیگابایت</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>مدت اعتبار:</span>
                            <span class="font-bold text-white font-mono"><?= $p['duration_days'] ?> روز</span>
                        </div>
                        <?php if (!empty($p['start_on_first_use'])): ?>
                        <div class="flex justify-between text-indigo-300 bg-indigo-950/40 p-1.5 rounded-lg border border-indigo-800/40 text-[11px]">
                            <span>شروع محاسبه زمان:</span>
                            <span class="font-bold flex items-center gap-1">
                                <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                از اولین اتصال
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-slate-400">
                            <span>سقف اتصال همزمان:</span>
                            <span class="font-bold text-purple-300 font-mono"><?= ($p['ip_limit'] ?? 2) > 0 ? ($p['ip_limit'] ?? 2) . ' دستگاه' : 'نامحدود' ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>خوشه / سرور:</span>
                            <?php if (!empty($p['server_name'])): ?>
                                <span class="font-bold text-purple-300 text-[11px] flex items-center gap-1 font-mono" title="<?= htmlspecialchars($p['server_subdomain'] ?? '') ?>">
                                    <i class="fa-solid fa-server text-[9px] text-purple-400"></i>
                                    <?= htmlspecialchars($p['server_name']) ?>
                                </span>
                            <?php elseif (!empty($p['cluster_name'])): ?>
                                <span class="font-bold text-cyan-300 text-[11px] flex items-center gap-1 font-mono">
                                    <i class="fa-solid <?= htmlspecialchars($p['cluster_icon'] ?: 'fa-globe') ?> text-[9px]"></i>
                                    <?= htmlspecialchars($p['cluster_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-cyan-400 text-[11px] flex items-center gap-1 font-mono">
                                    <i class="fa-solid fa-network-wired text-[9px]"></i>
                                    کلاستر (<?= strtoupper($p['server_group']) ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pt-2 space-y-1 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">قیمت فروش عادی:</span>
                            <span class="font-bold text-emerald-400 text-sm font-mono"><?= number_format($p['base_price']) ?> تومان</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-slate-400">قیمت همکار / نماینده:</span>
                            <span class="font-medium text-amber-300 font-mono"><?= number_format($p['reseller_price']) ?> تومان</span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-slate-800/80 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <form action="<?= Helpers::url('plans/toggle') ?>" method="POST" class="inline">
                            <?= Helpers::csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $p['is_active'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-rose-500/20' ?>">
                                <?= $p['is_active'] ? 'فعال' : 'غیرفعال' ?>
                            </button>
                        </form>

                        <!-- Bot Visibility Toggle -->
                        <form action="<?= Helpers::url('plans/toggle-bot') ?>" method="POST" class="inline" title="تغییر وضعیت نمایش در ربات">
                            <?= Helpers::csrfField() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="p-1.5 rounded-lg text-xs transition <?= $showInBot ? 'text-cyan-400 hover:bg-cyan-500/10' : 'text-slate-500 hover:bg-slate-800' ?>">
                                <i class="fa-brands fa-telegram text-base"></i>
                            </button>
                        </form>
                    </div>

                    <?php if (Auth::isAdmin()): ?>
                        <div class="flex items-center gap-1.5">
                            <button onclick='openEditPlanModal(<?= json_encode($p) ?>)' class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-lg text-xs font-bold border border-slate-700 transition flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>ویرایش</span>
                            </button>
                            <form action="<?= Helpers::url('plans/delete') ?>" method="POST" onsubmit="return confirm('آیا از حذف این پلن اطمینان دارید؟');" class="inline">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-400 transition" title="حذف پلن">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (Auth::isAdmin()): ?>
<!-- Modal: Create Plan -->
<div id="newPlanModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-purple-500/40 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeNewPlanModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-box-open text-purple-400"></i>
            <span>تعریف پلن تعرفه جدید</span>
        </h3>

        <form action="<?= Helpers::url('plans/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">عنوان یا نام نمایشی پلن *</label>
                    <input type="text" name="title" required placeholder="مثلاً: یک‌ماهه ۵۰ گیگابایت VIP" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>

                <div class="col-span-2 md:col-span-1">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-300 font-semibold">دسته‌بندی پلن *</label>
                        <a href="<?= Helpers::url('categories') ?>" target="_blank" class="text-[10px] text-purple-400 hover:text-purple-300 flex items-center gap-1 font-bold">
                            <i class="fa-solid fa-plus-circle"></i> مدیریت دسته‌ها
                        </a>
                    </div>
                    <select name="category_id" id="create_category_id" onchange="syncCategoryName(this, 'create_category_text')" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-medium">
                        <option value="">-- انتخاب دسته‌بندی --</option>
                        <?php if (!empty($planCategories)): ?>
                            <optgroup label="دسته‌بندی‌های پلن و تعرفه">
                                <?php foreach ($planCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php 
                        $otherCats = array_filter($allCategories, fn($c) => !in_array($c, $planCategories));
                        if (!empty($otherCats)): 
                        ?>
                            <optgroup label="خوشه‌ها و سایر دسته‌ها">
                                <?php foreach ($otherCats as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <option value="0">سفارشی / نام دلخواه...</option>
                    </select>
                    <input type="text" name="category" id="create_category_text" value="" placeholder="عنوان دسته‌بندی" class="w-full mt-1.5 bg-slate-950/60 border border-slate-800 rounded-lg px-2.5 py-1 text-slate-300 text-[11px]">
                </div>

                <div class="col-span-2 md:col-span-1">
                    <label class="block text-slate-300 mb-1 font-semibold">خوشه سرور / لوکیشن (Cluster) *</label>
                    <select name="server_group" id="create_server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <?php if (!empty($serverCategories)): ?>
                            <?php foreach ($serverCategories as $sc): ?>
                                <option value="<?= htmlspecialchars($sc['slug']) ?>" <?= $sc['slug'] === 'default' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sc['name']) ?> (<?= htmlspecialchars($sc['slug']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="default" selected>پیش‌فرض بین‌الملل (Default)</option>
                            <option value="vip">سرورهای VIP و پرسرعت</option>
                            <option value="economic">سرورهای اقتصادی (Economic)</option>
                            <option value="iran_access">ایران اکسس (ملی و نامحدود)</option>
                            <option value="gaming">مخصوص بازی و گیمینگ (Gaming)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">سرور / نود اختصاصی صدور کانفیگ</label>
                    <select name="server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                        <option value="">⚡️ انتخاب خودکار از کلاستر سرور (Auto Load Balance)</option>
                        <?php if (!empty($servers)): ?>
                            <?php foreach ($servers as $s): ?>
                                <option value="<?= $s['id'] ?>">🖥 <?= htmlspecialchars($s['name']) ?> (هسته: <?= strtoupper($s['driver']) ?> - دسته: <?= $s['server_group'] ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">در صورت انتخاب نود، کانفیگ مشتریان این پلن ۱۰۰٪ روی همان سرور ایجاد خواهد شد.</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">حجم (گیگابایت) *</label>
                    <input type="number" name="traffic_gb" required min="1" value="50" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">مدت اعتبار (روز) *</label>
                    <input type="number" name="duration_days" required min="1" value="30" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سقف اتصال همزمان (کاربر/IP)</label>
                    <input type="number" name="ip_limit" required min="0" value="2" placeholder="0 = نامحدود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">قیمت پایه فروش عادی (تومان)</label>
                    <input type="number" name="base_price" value="120000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">قیمت همکاری نماینده (تومان)</label>
                <input type="number" name="reseller_price" value="95000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="pt-2 space-y-2 border-t border-slate-800">
                <div class="flex items-center gap-2 p-2 bg-indigo-950/40 border border-indigo-800/40 rounded-xl">
                    <input type="checkbox" name="start_on_first_use" id="new_start_on_first_use" value="1" class="rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-0">
                    <label for="new_start_on_first_use" class="text-indigo-200 font-semibold text-xs cursor-pointer">
                        🕒 فعال‌سازی از اولین اتصال (مهلت زمانی پس از اتصال اول مشتری آغاز شود)
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="show_in_bot" id="new_show_in_bot" value="1" checked class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                    <label for="new_show_in_bot" class="text-slate-300 font-medium">نمایش برای خرید مستقیم در ربات تلگرام</label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_free" id="new_is_free" value="1" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                    <label for="new_is_free" class="text-slate-300">پلن تست رایگان (بدون هزینه)</label>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره پلن
            </button>
        </form>
    </div>
</div>

<!-- Modal: Edit Plan -->
<div id="editPlanModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-amber-500/40 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeEditPlanModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-amber-400"></i>
            <span>ویرایش پلن تعرفه</span>
        </h3>

        <form action="<?= Helpers::url('plans/update') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="id" id="edit_id" value="">

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">عنوان پلن *</label>
                    <input type="text" name="title" id="edit_title" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>

                <div class="col-span-2 md:col-span-1">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-300 font-semibold">دسته‌بندی پلن *</label>
                        <a href="<?= Helpers::url('categories') ?>" target="_blank" class="text-[10px] text-purple-400 hover:text-purple-300 flex items-center gap-1 font-bold">
                            <i class="fa-solid fa-plus-circle"></i> مدیریت دسته‌ها
                        </a>
                    </div>
                    <select name="category_id" id="edit_category_id" onchange="syncCategoryName(this, 'edit_category_text')" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-medium">
                        <option value="">-- انتخاب دسته‌بندی --</option>
                        <?php if (!empty($planCategories)): ?>
                            <optgroup label="دسته‌بندی‌های پلن و تعرفه">
                                <?php foreach ($planCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php 
                        $otherCats = array_filter($allCategories, fn($c) => !in_array($c, $planCategories));
                        if (!empty($otherCats)): 
                        ?>
                            <optgroup label="خوشه‌ها و سایر دسته‌ها">
                                <?php foreach ($otherCats as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-slug="<?= htmlspecialchars($cat['slug']) ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <option value="0">سفارشی / نام دلخواه...</option>
                    </select>
                    <input type="text" name="category" id="edit_category_text" placeholder="عنوان دسته‌بندی" class="w-full mt-1.5 bg-slate-950/60 border border-slate-800 rounded-lg px-2.5 py-1 text-slate-300 text-[11px]">
                </div>

                <div class="col-span-2 md:col-span-1">
                    <label class="block text-slate-300 mb-1 font-semibold">خوشه سرور / لوکیشن (Cluster) *</label>
                    <select name="server_group" id="edit_server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <?php if (!empty($serverCategories)): ?>
                            <?php foreach ($serverCategories as $sc): ?>
                                <option value="<?= htmlspecialchars($sc['slug']) ?>">
                                    <?= htmlspecialchars($sc['name']) ?> (<?= htmlspecialchars($sc['slug']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="default">پیش‌فرض بین‌الملل (Default)</option>
                            <option value="vip">سرورهای VIP و پرسرعت</option>
                            <option value="economic">سرورهای اقتصادی (Economic)</option>
                            <option value="iran_access">ایران اکسس (ملی و نامحدود)</option>
                            <option value="gaming">مخصوص بازی و گیمینگ (Gaming)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">سرور / نود اختصاصی صدور کانفیگ</label>
                    <select name="server_id" id="edit_server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                        <option value="">⚡️ انتخاب خودکار از کلاستر سرور (Auto Load Balance)</option>
                        <?php if (!empty($servers)): ?>
                            <?php foreach ($servers as $s): ?>
                                <option value="<?= $s['id'] ?>">🖥 <?= htmlspecialchars($s['name']) ?> (هسته: <?= strtoupper($s['driver']) ?> - دسته: <?= $s['server_group'] ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">در صورت انتخاب نود، کانفیگ مشتریان این پلن ۱۰۰٪ روی همان سرور ایجاد خواهد شد.</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">حجم (گیگابایت) *</label>
                    <input type="number" name="traffic_gb" id="edit_traffic_gb" required min="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">مدت اعتبار (روز) *</label>
                    <input type="number" name="duration_days" id="edit_duration_days" required min="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سقف اتصال همزمان (کاربر/IP)</label>
                    <input type="number" name="ip_limit" id="edit_ip_limit" required min="0" value="2" placeholder="0 = نامحدود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">قیمت پایه فروش عادی (تومان)</label>
                    <input type="number" name="base_price" id="edit_base_price" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">قیمت همکاری نماینده (تومان)</label>
                <input type="number" name="reseller_price" id="edit_reseller_price" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="pt-2 space-y-2 border-t border-slate-800">
                <div class="flex items-center gap-2 p-2 bg-indigo-950/40 border border-indigo-800/40 rounded-xl">
                    <input type="checkbox" name="start_on_first_use" id="edit_start_on_first_use" value="1" class="rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-0">
                    <label for="edit_start_on_first_use" class="text-indigo-200 font-semibold text-xs cursor-pointer">
                        🕒 فعال‌سازی از اولین اتصال (مهلت زمانی پس از اتصال اول مشتری آغاز شود)
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="show_in_bot" id="edit_show_in_bot" value="1" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                    <label for="edit_show_in_bot" class="text-slate-300 font-medium">نمایش برای خرید مستقیم در ربات تلگرام</label>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_free" id="edit_is_free" value="1" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                    <label for="edit_is_free" class="text-slate-300">پلن تست رایگان (بدون هزینه)</label>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره تغییرات پلن
            </button>
        </form>
    </div>
</div>

<script>
    function syncCategoryName(selectEl, textInputId) {
        const textInput = document.getElementById(textInputId);
        if (!textInput) return;
        const opt = selectEl.options[selectEl.selectedIndex];
        if (opt && opt.value !== "0" && opt.value !== "") {
            const catName = opt.getAttribute('data-name');
            if (catName) {
                textInput.value = catName;
            }
        }
    }

    function openNewPlanModal() {
        const modal = document.getElementById('newPlanModal');
        const catSelect = document.getElementById('create_category_id');
        const catText = document.getElementById('create_category_text');
        if (catSelect && catSelect.options.length > 1) {
            // Select first real option
            catSelect.selectedIndex = 1;
            syncCategoryName(catSelect, 'create_category_text');
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeNewPlanModal() {
        const modal = document.getElementById('newPlanModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function openEditPlanModal(p) {
        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_title').value = p.title;

        // Set category select and text
        const catSelect = document.getElementById('edit_category_id');
        const catText = document.getElementById('edit_category_text');
        catText.value = p.category || '';

        if (catSelect) {
            let matched = false;
            if (p.category_id && catSelect.querySelector(`option[value="${p.category_id}"]`)) {
                catSelect.value = p.category_id;
                matched = true;
            } else {
                for (let i = 0; i < catSelect.options.length; i++) {
                    const opt = catSelect.options[i];
                    if ((p.category && opt.getAttribute('data-name') === p.category) ||
                        (p.server_group && opt.getAttribute('data-slug') === p.server_group)) {
                        catSelect.selectedIndex = i;
                        matched = true;
                        break;
                    }
                }
            }
            if (!matched) {
                catSelect.value = "0"; // Custom
            }
        }

        // Set server group select
        const grpSelect = document.getElementById('edit_server_group');
        if (grpSelect) {
            grpSelect.value = p.server_group || 'default';
        }

        document.getElementById('edit_server_id').value = p.server_id || '';
        document.getElementById('edit_traffic_gb').value = p.traffic_gb;
        document.getElementById('edit_duration_days').value = p.duration_days;
        document.getElementById('edit_ip_limit').value = p.ip_limit ?? 2;
        document.getElementById('edit_base_price').value = p.base_price;
        document.getElementById('edit_reseller_price').value = p.reseller_price;
        document.getElementById('edit_show_in_bot').checked = (parseInt(p.show_in_bot ?? 1) === 1);
        document.getElementById('edit_is_free').checked = (parseInt(p.is_free ?? 0) === 1);
        document.getElementById('edit_start_on_first_use').checked = (parseInt(p.start_on_first_use ?? 0) === 1);

        const modal = document.getElementById('editPlanModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeEditPlanModal() {
        const modal = document.getElementById('editPlanModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function filterCategory(filterKey, catId, catSlug, catName) {
        document.querySelectorAll('.cat-pill').forEach(btn => {
            btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-500');
            btn.classList.add('bg-slate-900', 'text-slate-300', 'border-slate-800');
        });

        const activeBtn = document.getElementById('pill-' + filterKey);
        if (activeBtn) {
            activeBtn.classList.remove('bg-slate-900', 'text-slate-300', 'border-slate-800');
            activeBtn.classList.add('bg-purple-600', 'text-white', 'border-purple-500');
        }

        document.querySelectorAll('.plan-card').forEach(card => {
            if (filterKey === 'all') {
                card.style.display = '';
                return;
            }

            const cardCatId = card.getAttribute('data-category-id');
            const cardCatName = card.getAttribute('data-category-name');
            const cardServerGroup = card.getAttribute('data-server-group');

            let match = false;
            if (catId && catId !== '0' && cardCatId && String(catId) === String(cardCatId)) {
                match = true;
            } else if (catName && cardCatName === catName) {
                match = true;
            } else if (catSlug && cardServerGroup === catSlug) {
                match = true;
            }

            card.style.display = match ? '' : 'none';
        });
    }
</script>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/footer.php';
?>

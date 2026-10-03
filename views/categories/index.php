<?php
require __DIR__ . '/../layout/header.php';

$totalCategories = count($categories);
$activeCategories = count(array_filter($categories, fn($c) => (int)$c['is_active'] === 1));
$totalServersInCats = array_sum(array_column($categories, 'server_count'));
$totalPlansInCats = array_sum(array_column($categories, 'plan_count'));

// Build parent tree for display
$byParent = [];
foreach ($categories as $cat) {
    $pid = $cat['parent_id'] ?? null;
    $key = $pid ? (int)$pid : 0;
    $byParent[$key][] = $cat;
}
function renderCategoryOptions($cats, $byParent, $level = 0, $excludeId = null, $selectedId = null) {
    $html = '';
    foreach ($cats as $cat) {
        if ($excludeId && (int)$cat['id'] === (int)$excludeId) continue;
        $indent = str_repeat('— ', $level);
        $sel = $selectedId && (int)$selectedId === (int)$cat['id'] ? 'selected' : '';
        $html .= '<option value="'.$cat['id'].'" '.$sel.'>'.$indent.htmlspecialchars($cat['name']).' ('.$cat['slug'].')</option>';
        if (!empty($byParent[$cat['id']])) {
            $html .= renderCategoryOptions($byParent[$cat['id']], $byParent, $level+1, $excludeId, $selectedId);
        }
    }
    return $html;
}
?>

<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 border border-slate-800 p-5 rounded-2xl backdrop-blur-xl">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-500/30 flex items-center justify-center text-2xl shadow-lg shadow-purple-950/40">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h1 class="text-lg font-black text-white">مدیریت دسته‌بندی‌های تو در تو (Nested Categories)</h1>
                <p class="text-xs text-slate-400 mt-0.5">تعریف خوشه‌ها به صورت درختی: سرور → نوع (اقتصادی/ویژه) → مدت (۱ماهه/۲ماهه) - قابل اعمال در ربات</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <div class="text-[10px] bg-slate-800 border border-slate-700 px-3 py-1.5 rounded-xl text-slate-300">
                <i class="fa-solid fa-diagram-project text-purple-400 ml-1"></i>
                ساختار: سرور ویژه → اقتصادی/ویژه → ۱ماهه/۲ماهه → پلن‌ها
            </div>
            <?php
            $catSeedDisabled = \Setting::get('categories_auto_seed_disabled','0') === '1';
            if ($catSeedDisabled): ?>
                <span class="px-3 py-2 bg-emerald-950/50 text-emerald-300 border border-emerald-800/50 text-[11px] font-bold rounded-xl flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved"></i> ایمپورت خودکار دسته خاموش - برنمی‌گردند
                </span>
                <a href="<?= Helpers::url('categories/enable-auto-seed') ?>" onclick="return confirm('ایمپورت خودکار دسته‌ها فعال شود؟')" class="px-3 py-2 bg-amber-900/30 hover:bg-amber-900/50 text-amber-300 border border-amber-800/50 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-power-off"></i> فعال‌سازی ایمپورت
                </a>
            <?php else: ?>
                <span class="px-3 py-2 bg-amber-950/30 text-amber-300 border border-amber-800/30 text-[11px] font-bold rounded-xl flex items-center gap-1.5">
                    <i class="fa-solid fa-arrows-rotate"></i> ایمپورت خودکار روشن
                </span>
                <a href="<?= Helpers::url('categories/disable-auto-seed') ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                    <i class="fa-solid fa-ban"></i> خاموش کردن ایمپورت
                </a>
            <?php endif; ?>
            <form action="<?= Helpers::url('categories/merge_duplicates') ?>" method="POST" class="inline">
                <?= Helpers::csrfField() ?>
                <button type="submit" onclick="return confirm('آیا از ادغام دسته‌بندی‌های تکراری (مثل 1 ماهه و یک ماهه) اطمینان دارید؟')" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-amber-900/30 flex items-center gap-2">
                    <i class="fa-solid fa-code-merge"></i>
                    <span>ادغام تکراری‌ها</span>
                </button>
            </form>
            <form action="<?= Helpers::url('categories/fix_all') ?>" method="POST" class="inline">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-emerald-900/30 flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Fix All</span>
                </button>
            </form>
            <a href="<?= Helpers::url('categories/purge-all') ?>" onclick="return confirm('⚠️ تمام دسته‌ها به جز پیش‌فرض پاک شوند؟ این کار ایمپورت خودکار را خاموش می‌کند.')" class="px-3 py-2.5 bg-rose-950/40 hover:bg-rose-900/50 text-rose-300 border border-rose-800/50 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-trash-can"></i> پاکسازی همه (ضد بازگشت)
            </a>
            <button onclick="openCreateCatModal()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/30 flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>افزودن دسته جدید</span>
            </button>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-folder-tree"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">کل دسته‌بندی‌ها</span>
                <span class="text-lg font-black text-white font-mono"><?= $totalCategories ?> دسته</span>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">دسته‌های پلن</span>
                <span class="text-lg font-black text-amber-300 font-mono">
                    <?= count(array_filter($categories, fn($c) => in_array($c['type'] ?? '', ['plan', 'plans', 'both']))) ?> دسته
                </span>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-server"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">خوشه‌های سرور</span>
                <span class="text-lg font-black text-cyan-300 font-mono">
                    <?= count(array_filter($categories, fn($c) => in_array($c['type'] ?? '', ['server', 'servers', 'both']))) ?> خوشه
                </span>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">دسته‌های فعال</span>
                <span class="text-lg font-black text-emerald-400 font-mono"><?= $activeCategories ?> فعال</span>
            </div>
        </div>
    </div>

    <!-- Category Type Navigation Tabs - FIXED WRAP -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
        <button type="button" onclick="switchCategoryFilter('all')" id="tabBtn-cat-all" class="cat-filter-tab px-4 py-2 rounded-xl text-xs font-bold transition bg-purple-600 text-white shadow-md flex items-center gap-2">
            <i class="fa-solid fa-layer-group"></i>
            <span>همه (<?= $totalCategories ?>)</span>
        </button>
        <button type="button" onclick="switchCategoryFilter('plans')" id="tabBtn-cat-plans" class="cat-filter-tab px-4 py-2 rounded-xl text-xs font-medium transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-cubes text-amber-400"></i>
            <span>پلن‌ها</span>
        </button>
        <button type="button" onclick="switchCategoryFilter('servers')" id="tabBtn-cat-servers" class="cat-filter-tab px-4 py-2 rounded-xl text-xs font-medium transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-server text-cyan-400"></i>
            <span>سرورها</span>
        </button>
        <button type="button" onclick="switchCategoryFilter('nested')" id="tabBtn-cat-nested" class="cat-filter-tab px-4 py-2 rounded-xl text-xs font-medium transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-diagram-project text-emerald-400"></i>
            <span>فقط والددار (تو در تو)</span>
        </button>
    </div>

    <!-- Categories Tree Table with Bulk Delete -->
    <form id="bulkDeleteForm" action="<?= Helpers::url('categories/bulk-delete') ?>" method="POST" class="hidden">
        <?= Helpers::csrfField() ?>
        <div id="bulkIdsContainer"></div>
    </form>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800 flex flex-wrap items-center justify-between gap-2">
            <div class="font-bold text-sm text-white flex items-center gap-2">
                <i class="fa-solid fa-list text-purple-400"></i>
                <span>فهرست دسته‌ها (نمایش درختی)</span>
                <span class="text-[11px] text-slate-400 font-normal" id="selectedCount"></span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="toggleSelectAll()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[11px] font-bold rounded-lg transition flex items-center gap-1.5">
                    <i class="fa-solid fa-check-double"></i> انتخاب همه
                </button>
                <button onclick="bulkDeleteSelected()" class="px-3 py-1.5 bg-rose-900/40 hover:bg-rose-900/60 text-rose-300 border border-rose-800/50 text-[11px] font-bold rounded-lg transition flex items-center gap-1.5">
                    <i class="fa-solid fa-trash-can"></i> حذف انتخاب شده‌ها (ضد بازگشت)
                </button>
                <div class="flex items-center gap-2 text-[10px] text-slate-400 mr-2">
                    <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-slate-600 inline-block"></span> L1</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-purple-500 inline-block"></span> L2</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-cyan-500 inline-block"></span> L3</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-950/60 text-slate-400 border-b border-slate-800/80">
                    <tr>
                        <th class="p-3.5 font-semibold"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" class="rounded border-slate-600 bg-slate-800"></th>
                        <th class="p-3.5 font-semibold">ساختار درختی</th>
                        <th class="p-3.5 font-semibold">عنوان و نشانگر</th>
                        <th class="p-3.5 font-semibold">Slug / والد</th>
                        <th class="p-3.5 font-semibold">نوع</th>
                        <th class="p-3.5 font-semibold">ربات</th>
                        <th class="p-3.5 font-semibold">اعضا</th>
                        <th class="p-3.5 font-semibold">وضعیت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-500">
                                هیچ دسته‌بندی یافت نشد.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        // Recursive render
                        $renderRows = function($parentId, $level, $idxRef) use (&$renderRows, $byParent) {
                            $cats = $byParent[$parentId] ?? [];
                            foreach ($cats as $c) {
                                $idxRef++;
                                $badgeColor = match($c['badge_color'] ?? 'purple') {
                                    'amber' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                    'blue' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                    'emerald', 'green' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                    'cyan' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
                                    'rose', 'red' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                    default => 'bg-purple-500/10 text-purple-300 border-purple-500/30'
                                };
                                $levelColor = match($level) {
                                    0 => 'border-slate-600',
                                    1 => 'border-purple-500',
                                    2 => 'border-cyan-500',
                                    default => 'border-amber-500'
                                };
                                $levelIndent = str_repeat('<span class="inline-block w-4 h-0.5 bg-slate-700 ml-1"></span>', $level);
                                if ($level > 0) $levelIndent .= '<span class="inline-block w-3 h-3 border-l border-b '.$levelColor.' ml-1 mr-1 rounded-bl"></span>';
                        ?>
                            <tr class="cat-row hover:bg-slate-800/30 transition-colors" data-type="<?= htmlspecialchars($c['type'] ?? 'both') ?>" data-level="<?= $level ?>" data-parent="<?= $c['parent_id'] ?? 0 ?>">
                                <td class="p-3.5 text-center">
                                    <?php if ((int)$c['id'] > 1): ?>
                                        <input type="checkbox" class="cat-checkbox rounded border-slate-600 bg-slate-800 w-4 h-4" value="<?= $c['id'] ?>" onchange="updateSelectedCount()">
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-600">🔒</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-slate-500 font-mono">
                                    <div class="flex items-center gap-1">
                                        <?= $levelIndent ?>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700">L<?= $level ?></span>
                                        <span class="text-slate-600">#<?= $c['id'] ?></span>
                                    </div>
                                </td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl border flex items-center justify-center <?= $badgeColor ?>">
                                            <i class="fa-solid <?= htmlspecialchars($c['icon'] ?: 'fa-server') ?>"></i>
                                        </div>
                                        <div>
                                            <span class="font-bold text-white block"><?= htmlspecialchars($c['name']) ?></span>
                                            <?php if (!empty($c['bot_label'])): ?>
                                                <span class="text-[10px] text-cyan-300 block">ربات: <?= htmlspecialchars($c['bot_label']) ?> <?= htmlspecialchars($c['bot_icon'] ?? '') ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($c['description'])): ?>
                                                <span class="text-[10px] text-slate-400 block mt-0.5 line-clamp-1"><?= htmlspecialchars($c['description']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($c['children_count'] > 0): ?>
                                                <span class="text-[9px] bg-purple-500/20 text-purple-300 px-1.5 py-0.5 rounded mt-1 inline-block"><?= $c['children_count'] ?> زیرشاخه</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-[11px]">
                                    <code class="text-cyan-300 block"><?= htmlspecialchars($c['slug']) ?></code>
                                    <?php if (!empty($c['parent_name'])): ?>
                                        <span class="text-[10px] text-slate-400">والد: <?= htmlspecialchars($c['parent_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-500">ریشه</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= match($c['type']) {
                                            'server', 'servers' => 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20',
                                            'plan', 'plans' => 'bg-amber-500/10 text-amber-300 border border-amber-500/20',
                                            default => 'bg-purple-500/10 text-purple-300 border border-purple-500/20'
                                        } ?>">
                                        <?= match($c['type']) {
                                            'server', 'servers' => 'سرور',
                                            'plan', 'plans' => 'پلن',
                                            default => 'مشترک'
                                        } ?>
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <?php if (!empty($c['bot_label'])): ?>
                                        <span class="text-[11px] bg-slate-800 border border-slate-700 px-2 py-1 rounded-lg"><?= htmlspecialchars($c['bot_icon'] ?? '') ?> <?= htmlspecialchars($c['bot_label']) ?></span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 whitespace-nowrap font-mono text-slate-300 text-[11px]">
                                    <div class="flex flex-col gap-1">
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-800 border border-slate-700"><?= (int)$c['server_count'] ?> سرور</span>
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-800 border border-slate-700"><?= (int)$c['plan_count'] ?> پلن</span>
                                    </div>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= (int)$c['is_active'] === 1 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                        <?= (int)$c['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                    <div class="text-[10px] text-slate-500 mt-1">ترتیب: <?= (int)$c['sort_order'] ?></div>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick='openEditCatModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                                class="w-8 h-8 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 transition flex items-center justify-center" 
                                                title="ویرایش">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button onclick="openCreateCatModal(<?= $c['id'] ?>)" 
                                                class="w-8 h-8 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 transition flex items-center justify-center" 
                                                title="افزودن زیرشاخه">
                                            <i class="fa-solid fa-plus text-xs"></i>
                                        </button>
                                        <?php if ((int)$c['id'] > 1): ?>
                                            <button onclick="confirmDeleteCat(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                                                    class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition flex items-center justify-center" 
                                                    title="حذف">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
                                // Recursively render children
                                $renderRows($c['id'], $level+1, $idxRef);
                            }
                        };
                        $idxRef = 0;
                        $renderRows(0, 0, $idxRef);
                        ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Create Category Modal -->
<div id="createCatModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl relative my-8">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-purple-400"></i>
                <span id="createModalTitle">افزودن دسته‌بندی جدید (تو در تو)</span>
            </h3>
            <button onclick="closeCreateCatModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="<?= Helpers::url('categories/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div class="bg-purple-500/10 border border-purple-500/20 rounded-xl p-3 text-[11px] text-purple-200">
                <i class="fa-solid fa-lightbulb text-amber-400 ml-1"></i>
                برای ساختار درختی: ابتدا دسته والد را بسازید (مثلاً «سرور ویژه») سپس زیرشاخه «اقتصادی» و «ویژه» و زیر آن‌ها «۱ ماهه»، «۲ ماهه» را به عنوان فرزند ایجاد کنید. این ساختار در ربات اعمال می‌شود.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام نمایشی دسته *</label>
                    <input type="text" name="name" required placeholder="مثلاً: اقتصادی یا ۱ ماهه" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">شناسه انگلیسی (Slug)</label>
                    <input type="text" name="slug" dir="ltr" placeholder="economic (اختیاری)" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">دسته والد (برای تو در تو) - خالی = ریشه</label>
                    <select name="parent_id" id="createParentId" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="">🌳 بدون والد (سطح ریشه)</option>
                        <?php echo renderCategoryOptions($byParent[0] ?? [], $byParent, 0); ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه کاربرد</label>
                    <select name="type" id="createCatType" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="both" selected>🌐 مشترک</option>
                        <option value="plans">📦 پلن‌ها</option>
                        <option value="servers">🖥 سرورها</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون FA</label>
                    <input type="text" name="icon" id="createCatIcon" value="fa-server" dir="ltr" placeholder="fa-tag" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رنگ</label>
                    <select name="badge_color" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="purple" selected>بنفش</option>
                        <option value="amber">طلایی</option>
                        <option value="blue">آبی</option>
                        <option value="emerald">سبز</option>
                        <option value="cyan">فیروزه‌ای</option>
                        <option value="rose">قرمز</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">برچسب در ربات (bot_label)</label>
                    <input type="text" name="bot_label" placeholder="مثلاً: اقتصادی یا ۱ماهه" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون ربات (bot_icon)</label>
                    <input type="text" name="bot_icon" placeholder="💰 یا ⭐ یا 📅" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">ترتیب</label>
                    <input type="number" name="sort_order" value="10" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات</label>
                <textarea name="description" rows="2" placeholder="توضیحات..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg transition">ذخیره دسته‌بندی تو در تو</button>
                <button type="button" onclick="closeCreateCatModal()" class="px-5 py-3 bg-slate-800 text-slate-300 font-bold rounded-xl hover:bg-slate-700 transition">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCatModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl relative my-8">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-purple-400"></i>
                <span>ویرایش دسته‌بندی تو در تو</span>
            </h3>
            <button onclick="closeEditCatModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="<?= Helpers::url('categories/update') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="id" id="editCatId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام نمایشی *</label>
                    <input type="text" name="name" id="editCatName" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">Slug</label>
                    <input type="text" name="slug" id="editCatSlug" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">دسته والد</label>
                    <select name="parent_id" id="editCatParent" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="">🌳 بدون والد (ریشه)</option>
                        <?php echo renderCategoryOptions($byParent[0] ?? [], $byParent, 0); ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه کاربرد</label>
                    <select name="type" id="editCatType" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="both">مشترک</option>
                        <option value="servers">فقط سرور</option>
                        <option value="plans">فقط پلن</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون FA</label>
                    <input type="text" name="icon" id="editCatIcon" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رنگ</label>
                    <select name="badge_color" id="editCatColor" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="purple">بنفش</option>
                        <option value="amber">طلایی</option>
                        <option value="blue">آبی</option>
                        <option value="emerald">سبز</option>
                        <option value="cyan">فیروزه‌ای</option>
                        <option value="rose">قرمز</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">برچسب ربات</label>
                    <input type="text" name="bot_label" id="editCatBotLabel" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون ربات</label>
                    <input type="text" name="bot_icon" id="editCatBotIcon" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">ترتیب</label>
                    <input type="number" name="sort_order" id="editCatSort" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 items-center">
                <div class="col-span-2">
                    <label class="block text-slate-300 mb-1 font-semibold">توضیحات</label>
                    <textarea name="description" id="editCatDesc" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
                </div>
                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" id="editCatActive" value="1" class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500 bg-slate-800 border-slate-700">
                        <span class="text-slate-200 font-bold">فعال باشد</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg transition">به‌روزرسانی</button>
                <button type="button" onclick="closeEditCatModal()" class="px-5 py-3 bg-slate-800 text-slate-300 font-bold rounded-xl hover:bg-slate-700 transition">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<form id="deleteCatForm" action="<?= Helpers::url('categories/delete') ?>" method="POST" class="hidden">
    <?= Helpers::csrfField() ?>
    <input type="hidden" name="id" id="deleteCatId">
</form>

<script>
let currentCatFilter = 'all';

function switchCategoryFilter(type) {
    currentCatFilter = type;
    const tabs = ['all', 'plans', 'servers', 'nested'];
    tabs.forEach(t => {
        const btn = document.getElementById('tabBtn-cat-' + t);
        if (btn) {
            if (t === type) {
                btn.className = 'cat-filter-tab px-4 py-2 rounded-xl text-xs font-bold transition bg-purple-600 text-white shadow-md flex items-center gap-2';
            } else {
                btn.className = 'cat-filter-tab px-4 py-2 rounded-xl text-xs font-medium transition bg-slate-900 text-slate-400 hover:text-white border border-slate-800 flex items-center gap-2';
            }
        }
    });

    const rows = document.querySelectorAll('.cat-row');
    rows.forEach(row => {
        const rowType = row.getAttribute('data-type') || 'both';
        const level = parseInt(row.getAttribute('data-level') || '0');
        const parent = row.getAttribute('data-parent') || '0';
        if (type === 'all') {
            row.style.display = '';
        } else if (type === 'plans') {
            row.style.display = (rowType === 'plans' || rowType === 'plan' || rowType === 'both') ? '' : 'none';
        } else if (type === 'servers') {
            row.style.display = (rowType === 'servers' || rowType === 'server' || rowType === 'both') ? '' : 'none';
        } else if (type === 'nested') {
            row.style.display = (level > 0 || parent !== '0') ? '' : 'none';
        }
    });
}

function openCreateCatModal(parentId = null) {
    const typeSelect = document.getElementById('createCatType');
    const iconInput = document.getElementById('createCatIcon');
    const parentSelect = document.getElementById('createParentId');
    const title = document.getElementById('createModalTitle');
    if (parentId) {
        if (parentSelect) parentSelect.value = parentId;
        if (title) title.textContent = 'افزودن زیرشاخه (والد #' + parentId + ')';
    } else {
        if (parentSelect) parentSelect.value = '';
        if (title) title.textContent = 'افزودن دسته‌بندی جدید (تو در تو)';
    }
    if (currentCatFilter === 'plans') {
        if (typeSelect) typeSelect.value = 'plans';
        if (iconInput) iconInput.value = 'fa-cubes';
    } else if (currentCatFilter === 'servers') {
        if (typeSelect) typeSelect.value = 'servers';
        if (iconInput) iconInput.value = 'fa-server';
    } else {
        if (typeSelect) typeSelect.value = 'both';
    }
    document.getElementById('createCatModal').classList.remove('hidden');
}
function closeCreateCatModal() {
    document.getElementById('createCatModal').classList.add('hidden');
}

function openEditCatModal(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatSlug').value = cat.slug;
    document.getElementById('editCatType').value = cat.type || 'both';
    document.getElementById('editCatIcon').value = cat.icon || 'fa-server';
    document.getElementById('editCatColor').value = cat.badge_color || 'purple';
    document.getElementById('editCatSort').value = cat.sort_order || 0;
    document.getElementById('editCatDesc').value = cat.description || '';
    document.getElementById('editCatActive').checked = (parseInt(cat.is_active) === 1);
    document.getElementById('editCatParent').value = cat.parent_id || '';
    document.getElementById('editCatBotLabel').value = cat.bot_label || '';
    document.getElementById('editCatBotIcon').value = cat.bot_icon || '';
    // Exclude self from parent options
    const parentSelect = document.getElementById('editCatParent');
    for (let opt of parentSelect.options) {
        opt.disabled = (parseInt(opt.value) === parseInt(cat.id));
    }
    document.getElementById('editCatModal').classList.remove('hidden');
}
function closeEditCatModal() {
    document.getElementById('editCatModal').classList.add('hidden');
}

function confirmDeleteCat(id, name) {
    if (confirm(`آیا از حذف دسته‌بندی «${name}» اطمینان دارید؟\nزیرشاخه‌های آن به ریشه منتقل می‌شوند و سرورها/پلن‌های عضو به پیش‌فرض می‌روند.\n\n⚠️ ایمپورت خودکار دسته‌ها خاموش می‌شود تا دوباره برنگردد.`)) {
        document.getElementById('deleteCatId').value = id;
        document.getElementById('deleteCatForm').submit();
    }
}

function toggleSelectAll() {
    const allCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.cat-checkbox');
    const isChecked = allCheckbox ? allCheckbox.checked : false;
    // If called from button, toggle based on current state
    let shouldCheck = isChecked;
    if (event && event.target && event.target.tagName === 'BUTTON') {
        const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
        shouldCheck = anyUnchecked;
        if (allCheckbox) allCheckbox.checked = shouldCheck;
    }
    checkboxes.forEach(cb => cb.checked = shouldCheck);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.cat-checkbox:checked');
    const count = checkboxes.length;
    const el = document.getElementById('selectedCount');
    if (el) {
        el.textContent = count > 0 ? `(${count} انتخاب شده)` : '';
    }
}

function bulkDeleteSelected() {
    const checkboxes = document.querySelectorAll('.cat-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('هیچ دسته‌ای انتخاب نشده است.');
        return;
    }
    if (!confirm(`⚠️ آیا از حذف ${checkboxes.length} دسته‌بندی انتخاب شده اطمینان دارید؟\n\nزیرشاخه‌ها به ریشه منتقل می‌شوند و پلن‌ها/سرورها به پیش‌فرض می‌روند.\n\n🚫 ایمپورت خودکار دسته‌ها خاموش می‌شود تا دیگر برنگردند.`)) {
        return;
    }
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkDeleteForm').submit();
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

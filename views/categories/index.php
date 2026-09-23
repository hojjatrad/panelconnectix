<?php
require __DIR__ . '/../layout/header.php';

$totalCategories = count($categories);
$activeCategories = count(array_filter($categories, fn($c) => (int)$c['is_active'] === 1));
$totalServersInCats = array_sum(array_column($categories, 'server_count'));
$totalPlansInCats = array_sum(array_column($categories, 'plan_count'));
?>

<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 border border-slate-800 p-5 rounded-2xl backdrop-blur-xl">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-500/30 flex items-center justify-center text-2xl shadow-lg shadow-purple-950/40">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h1 class="text-lg font-black text-white">مدیریت دسته‌بندی‌های پویا (Clusters & Groups)</h1>
                <p class="text-xs text-slate-400 mt-0.5">تعریف لوکیشن‌ها و خوشه‌ها برای تفکیک سرورها، پنل‌ها و پلن‌های فروش</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="openCreateCatModal()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/30 flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>افزودن دسته‌بندی جدید</span>
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
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">دسته‌های فعال</span>
                <span class="text-lg font-black text-emerald-400 font-mono"><?= $activeCategories ?> فعال</span>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-server"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">سرورهای متصل</span>
                <span class="text-lg font-black text-cyan-300 font-mono"><?= $totalServersInCats ?> سرور</span>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800 p-4 rounded-2xl flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-cubes"></i>
            </div>
            <div>
                <span class="text-[11px] text-slate-400 block">پلن‌های متصل</span>
                <span class="text-lg font-black text-amber-300 font-mono"><?= $totalPlansInCats ?> پلن</span>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div class="font-bold text-sm text-white flex items-center gap-2">
                <i class="fa-solid fa-list text-purple-400"></i>
                <span>فهرست دسته‌ها و خوشه‌های تعریف‌شده</span>
            </div>
            <span class="text-xs text-slate-500">هر سرور یا پلن می‌تواند به یکی از این دسته‌ها اختصاص داده شود</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-950/60 text-slate-400 border-b border-slate-800/80">
                    <tr>
                        <th class="p-3.5 font-semibold">ردیف</th>
                        <th class="p-3.5 font-semibold">عنوان و نشانگر</th>
                        <th class="p-3.5 font-semibold">شناسه یکتا (Slug)</th>
                        <th class="p-3.5 font-semibold">دامنه کاربرد</th>
                        <th class="p-3.5 font-semibold">سرورهای عضو</th>
                        <th class="p-3.5 font-semibold">پلن‌های عضو</th>
                        <th class="p-3.5 font-semibold">ترتیب</th>
                        <th class="p-3.5 font-semibold">وضعیت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-500">
                                هیچ دسته‌بندی یافت نشد. می‌توانید با دکمه بالا اولین دسته‌بندی را تعریف کنید.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $idx => $c): 
                            $badgeColor = match($c['badge_color'] ?? 'purple') {
                                'amber' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                'blue' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
                                'emerald', 'green' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'cyan' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
                                'rose', 'red' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                default => 'bg-purple-500/10 text-purple-300 border-purple-500/30'
                            };
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-3.5 text-slate-500 font-mono"><?= $idx + 1 ?></td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl border flex items-center justify-center <?= $badgeColor ?>">
                                            <i class="fa-solid <?= htmlspecialchars($c['icon'] ?: 'fa-server') ?>"></i>
                                        </div>
                                        <div>
                                            <span class="font-bold text-white block"><?= htmlspecialchars($c['name']) ?></span>
                                            <?php if (!empty($c['description'])): ?>
                                                <span class="text-[10px] text-slate-400 block mt-0.5 line-clamp-1"><?= htmlspecialchars($c['description']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-cyan-300 text-[11px] whitespace-nowrap">
                                    <code><?= htmlspecialchars($c['slug']) ?></code>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                                        <?= match($c['type']) {
                                            'server' => 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20',
                                            'plan' => 'bg-amber-500/10 text-amber-300 border border-amber-500/20',
                                            default => 'bg-purple-500/10 text-purple-300 border border-purple-500/20'
                                        } ?>">
                                        <?= match($c['type']) {
                                            'server' => 'فقط سرورها',
                                            'plan' => 'فقط پلن‌ها',
                                            default => 'سرورها و پلن‌ها'
                                        } ?>
                                    </span>
                                </td>
                                <td class="p-3.5 whitespace-nowrap font-mono text-slate-300 font-bold">
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-800 border border-slate-700">
                                        <?= (int)$c['server_count'] ?> سرور
                                    </span>
                                </td>
                                <td class="p-3.5 whitespace-nowrap font-mono text-slate-300 font-bold">
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-800 border border-slate-700">
                                        <?= (int)$c['plan_count'] ?> پلن
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono text-slate-400 text-center whitespace-nowrap">
                                    <?= (int)$c['sort_order'] ?>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= (int)$c['is_active'] === 1 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                        <?= (int)$c['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick='openEditCatModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                                class="w-8 h-8 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 transition flex items-center justify-center" 
                                                title="ویرایش دسته‌بندی">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <?php if ((int)$c['id'] > 1): ?>
                                            <button onclick="confirmDeleteCat(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')" 
                                                    class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition flex items-center justify-center" 
                                                    title="حذف دسته‌بندی">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Create Category Modal -->
<div id="createCatModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-purple-400"></i>
                <span>افزودن دسته‌بندی جدید</span>
            </h3>
            <button onclick="closeCreateCatModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="<?= Helpers::url('categories/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام نمایشی دسته *</label>
                    <input type="text" name="name" required placeholder="مثلاً: آلمان VIP یا اقتصادی" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">شناسه انگلیسی (Slug)</label>
                    <input type="text" name="slug" dir="ltr" placeholder="germany_vip (اختیاری)" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه کاربرد</label>
                    <select name="type" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="both" selected>سرورها و پلن‌ها</option>
                        <option value="server">فقط سرورها</option>
                        <option value="plan">فقط پلن‌ها</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون FontAwesome</label>
                    <input type="text" name="icon" value="fa-server" dir="ltr" placeholder="fa-globe" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رنگ نشانگر</label>
                    <select name="badge_color" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="purple" selected>بنفش (استاندارد)</option>
                        <option value="amber">طلایی / زرد</option>
                        <option value="blue">آبی</option>
                        <option value="emerald">سبز</option>
                        <option value="cyan">فیروزه‌ای</option>
                        <option value="rose">قرمز</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">اولویت ترتیب نمایش (عددی)</label>
                <input type="number" name="sort_order" value="10" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات کوتاه یا راهنما</label>
                <textarea name="description" rows="2" placeholder="توضیحاتی درباره این خوشه سرور یا نوع پلن برای اطلاع مدیر و مشتریان..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg transition">ذخیره دسته‌بندی</button>
                <button type="button" onclick="closeCreateCatModal()" class="px-5 py-3 bg-slate-800 text-slate-300 font-bold rounded-xl hover:bg-slate-700 transition">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCatModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-purple-400"></i>
                <span>ویرایش دسته‌بندی</span>
            </h3>
            <button onclick="closeEditCatModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="<?= Helpers::url('categories/update') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="id" id="editCatId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام نمایشی دسته *</label>
                    <input type="text" name="name" id="editCatName" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">شناسه انگلیسی (Slug)</label>
                    <input type="text" name="slug" id="editCatSlug" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه کاربرد</label>
                    <select name="type" id="editCatType" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="both">سرورها و پلن‌ها</option>
                        <option value="server">فقط سرورها</option>
                        <option value="plan">فقط پلن‌ها</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آیکون FontAwesome</label>
                    <input type="text" name="icon" id="editCatIcon" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رنگ نشانگر</label>
                    <select name="badge_color" id="editCatColor" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="purple">بنفش</option>
                        <option value="amber">طلایی / زرد</option>
                        <option value="blue">آبی</option>
                        <option value="emerald">سبز</option>
                        <option value="cyan">فیروزه‌ای</option>
                        <option value="rose">قرمز</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">اولویت ترتیب نمایش</label>
                    <input type="number" name="sort_order" id="editCatSort" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div class="pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" id="editCatActive" value="1" class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500 bg-slate-800 border-slate-700">
                        <span class="text-slate-200 font-bold">دسته‌بندی فعال باشد</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات دسته</label>
                <textarea name="description" id="editCatDesc" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg transition">به‌روزرسانی دسته‌بندی</button>
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
function openCreateCatModal() {
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
    document.getElementById('editCatModal').classList.remove('hidden');
}
function closeEditCatModal() {
    document.getElementById('editCatModal').classList.add('hidden');
}

function confirmDeleteCat(id, name) {
    if (confirm(`آیا از حذف دسته‌بندی «${name}» اطمینان دارید؟\nدر صورت حذف، کلیه سرورها و پلن‌های عضو این دسته به صورت خودکار به دسته پیش‌فرض منتقل می‌شوند.`)) {
        document.getElementById('deleteCatId').value = id;
        document.getElementById('deleteCatForm').submit();
    }
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

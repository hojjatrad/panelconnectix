<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-box-open text-amber-400"></i>
            <span>تعرفه‌ها و پلن‌های سرویس</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده قیمت پایه، قیمت همکاری نمایندگان و حجم ترافیک</p>
    </div>

    <?php if (Auth::isAdmin()): ?>
        <button onclick="openNewPlanModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>تعریف پلن جدید</span>
        </button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($plans as $p): ?>
        <div class="bg-slate-900/80 border <?= $p['is_free'] ? 'border-indigo-800/60' : 'border-slate-800' ?> rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden">
            <?php if ($p['is_free']): ?>
                <div class="absolute top-3 left-3 bg-indigo-500/20 text-indigo-300 text-[10px] font-bold px-2 py-0.5 rounded border border-indigo-500/30">
                    پلن تست رایگان
                </div>
            <?php endif; ?>

            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-cloud"></i>
                    </span>
                    <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($p['title']) ?></h3>
                </div>

                <div class="my-4 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>حجم ترافیک:</span>
                        <span class="font-bold text-white"><?= $p['traffic_gb'] ?> گیگابایت</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>مدت اعتبار:</span>
                        <span class="font-bold text-white"><?= $p['duration_days'] ?> روز</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>گروه سرور:</span>
                        <span class="font-bold text-cyan-400 uppercase font-mono text-[11px]"><?= $p['server_group'] ?></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 space-y-1">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400">قیمت فروش عادی:</span>
                        <span class="text-slate-300 font-medium"><?= $p['is_free'] ? 'رایگان' : Helpers::formatMoney($p['base_price']) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400">قیمت همکاری نماینده:</span>
                        <span class="font-bold text-emerald-400 text-sm"><?= $p['is_free'] ? 'رایگان' : Helpers::formatMoney($p['reseller_price']) ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-800 flex items-center justify-between">
                <span class="text-[10px] px-2 py-0.5 rounded <?= $p['is_active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                    <?= $p['is_active'] ? 'فعال جهت سفارش' : 'غیرفعال' ?>
                </span>

                <a href="<?= Helpers::url('clients/create') ?>" class="text-xs text-purple-400 hover:text-purple-300 font-semibold flex items-center gap-1">
                    <span>ثبت با این پلن</span>
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal to add new Plan (Admin only) -->
<?php if (Auth::isAdmin()): ?>
<div id="newPlanModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="closeNewPlanModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4">تعریف پلن تعرفه جدید</h3>

        <form action="<?= Helpers::url('plans/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">عنوان پلن *</label>
                <input type="text" name="title" required placeholder="مثلاً: یک‌ماهه ۶۰ گیگ" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">حجم (گیگابایت) *</label>
                    <input type="number" name="traffic_gb" required min="1" value="50" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">مدت اعتبار (روز) *</label>
                    <input type="number" name="duration_days" required min="1" value="30" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">قیمت پایه (تومان)</label>
                    <input type="number" name="base_price" value="120000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">قیمت نماینده (تومان)</label>
                    <input type="number" name="reseller_price" value="95000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">گروه سرور مجاز</label>
                <select name="server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                    <option value="default">عادی (Default)</option>
                    <option value="economic">اقتصادی (Economic)</option>
                    <option value="iran_access">ایران اکسس (Iran Access)</option>
                    <option value="vip">تجاری VIP (Business Class)</option>
                </select>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_free" id="is_free" value="1" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                <label for="is_free" class="text-slate-300">پلن رایگان / تستی (بدون کسر از موجودی)</label>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره پلن
            </button>
        </form>
    </div>
</div>

<script>
    function openNewPlanModal() {
        const modal = document.getElementById('newPlanModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeNewPlanModal() {
        const modal = document.getElementById('newPlanModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
</script>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/footer.php';
?>

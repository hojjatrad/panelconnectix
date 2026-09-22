<?php
require __DIR__ . '/../layout/header.php';

// Unique categories
$categories = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'] ?? '۱ ماهه', $plans))));
if (empty($categories)) $categories = ['۱ ماهه', '۲ ماهه', '۳ ماهه', '۶ ماهه'];
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-box-open text-amber-400"></i>
                <span>تعرفه‌ها، پلن‌ها و دسته‌بندی محصولات</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">مدیریت قیمت پایه، قیمت همکاری، دسته‌بندی دوره‌ها و وضعیت نمایش اختصاصی در ربات تلگرام</p>
        </div>

        <?php if (Auth::isAdmin()): ?>
            <button onclick="openNewPlanModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>تعریف پلن جدید</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
        <span class="text-slate-400 text-xs shrink-0 font-medium">دسته‌بندی‌ها:</span>
        <button onclick="filterCategory('all')" id="pill-all" class="cat-pill px-3 py-1.5 rounded-xl font-bold bg-purple-600 text-white border border-purple-500 shadow-sm transition">
            همه پلن‌ها (<?= count($plans) ?>)
        </button>
        <?php foreach ($categories as $cat): 
            $catCount = count(array_filter($plans, fn($p) => ($p['category'] ?? '۱ ماهه') === $cat));
        ?>
            <button onclick="filterCategory('<?= htmlspecialchars($cat) ?>')" id="pill-<?= md5($cat) ?>" class="cat-pill px-3 py-1.5 rounded-xl font-medium bg-slate-900 text-slate-300 border border-slate-800 hover:bg-slate-800 transition">
                <?= htmlspecialchars($cat) ?> (<?= $catCount ?>)
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($plans as $p): 
            $cat = $p['category'] ?? '۱ ماهه';
            $showInBot = (int)($p['show_in_bot'] ?? 1);
        ?>
            <div class="plan-card bg-slate-900/80 border <?= $p['is_free'] ? 'border-indigo-800/60' : 'border-slate-800' ?> rounded-2xl p-5 flex flex-col justify-between shadow-sm relative overflow-hidden group" data-category="<?= htmlspecialchars($cat) ?>">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fa-solid fa-cloud"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($p['title']) ?></h3>
                                <span class="inline-block mt-0.5 text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 text-cyan-300 border border-slate-700">
                                    دسته: <?= htmlspecialchars($cat) ?>
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
                        <div class="flex justify-between text-slate-400">
                            <span>سقف اتصال همزمان:</span>
                            <span class="font-bold text-purple-300 font-mono"><?= ($p['ip_limit'] ?? 2) > 0 ? ($p['ip_limit'] ?? 2) . ' دستگاه' : 'نامحدود' ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>نود اختصاصی / کلاستر:</span>
                            <?php if (!empty($p['server_name'])): ?>
                                <span class="font-bold text-purple-300 text-[11px] flex items-center gap-1 font-mono" title="<?= htmlspecialchars($p['server_subdomain'] ?? '') ?>">
                                    <i class="fa-solid fa-server text-[9px] text-purple-400"></i>
                                    <?= htmlspecialchars($p['server_name']) ?> (<?= strtoupper($p['server_driver'] ?? '') ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-cyan-400 text-[11px] flex items-center gap-1 font-mono">
                                    <i class="fa-solid fa-network-wired text-[9px]"></i>
                                    کلاستر هوشمند (<?= strtoupper($p['server_group']) ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pt-2 space-y-1 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">قیمت فروش عادی:</span>
                            <span class="text-slate-300 font-medium font-mono"><?= $p['is_free'] ? 'رایگان' : Helpers::formatMoney($p['base_price']) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">قیمت همکاری نماینده:</span>
                            <span class="font-bold text-emerald-400 text-sm font-mono"><?= $p['is_free'] ? 'رایگان' : Helpers::formatMoney($p['reseller_price']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800/80 space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-[10px] px-2 py-0.5 rounded font-bold <?= $p['is_active'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                            <?= $p['is_active'] ? '✓ فعال در سیستم' : '✗ غیرفعال' ?>
                        </span>

                        <a href="<?= Helpers::url('clients/create?plan_id=' . $p['id']) ?>" class="text-xs text-purple-400 hover:text-purple-300 font-bold flex items-center gap-1">
                            <span>صدور کلاینت</span>
                            <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        </a>
                    </div>

                    <?php if (Auth::isAdmin()): ?>
                        <div class="flex items-center gap-1.5 pt-1 border-t border-slate-800/60">
                            <!-- Edit Button -->
                            <button onclick='openEditPlanModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)' class="flex-1 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs font-bold rounded-lg border border-slate-700 transition flex items-center justify-center gap-1">
                                <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                <span>ویرایش</span>
                            </button>

                            <!-- Toggle Bot Button -->
                            <form action="<?= Helpers::url('plans/toggle-bot') ?>" method="POST" class="m-0 flex-1">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-300 text-xs font-bold rounded-lg border border-slate-700 transition flex items-center justify-center gap-1" title="تغییر وضعیت نمایش این پلن در ربات تلگرام">
                                    <i class="fa-brands fa-telegram text-[11px]"></i>
                                    <span><?= $showInBot ? 'مخفی در بات' : 'نمایش در بات' ?></span>
                                </button>
                            </form>

                            <!-- Toggle Active Button -->
                            <form action="<?= Helpers::url('plans/toggle') ?>" method="POST" class="m-0">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="p-1.5 bg-slate-800 hover:bg-slate-700 <?= $p['is_active'] ? 'text-rose-400' : 'text-emerald-400' ?> text-xs font-bold rounded-lg border border-slate-700 transition" title="<?= $p['is_active'] ? 'غیرفعال‌سازی پلن' : 'فعال‌سازی پلن' ?>">
                                    <i class="fa-solid <?= $p['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: New Plan -->
<?php if (Auth::isAdmin()): ?>
<div id="newPlanModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
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
                    <label class="block text-slate-300 mb-1 font-semibold">عنوان پلن *</label>
                    <input type="text" name="title" required placeholder="مثلاً: یک‌ماهه ۵۰ گیگابایت VIP" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دسته‌بندی محصول *</label>
                    <input type="text" name="category" list="cat-suggestions" value="۱ ماهه" required placeholder="مثلاً: ۱ ماهه یا اقتصادی" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-medium">
                    <datalist id="cat-suggestions">
                        <option value="۱ ماهه">
                        <option value="۲ ماهه">
                        <option value="۳ ماهه">
                        <option value="۶ ماهه">
                        <option value="اقتصادی">
                        <option value="VIP تجاری">
                        <option value="ایران اکسس (ملی)">
                    </datalist>
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

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دسته‌بندی محصول *</label>
                    <input type="text" name="category" id="edit_category" list="cat-suggestions" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-medium">
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">گروه سرور مجاز</label>
                    <select name="server_group" id="edit_server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="default">عادی (Default)</option>
                        <option value="economic">اقتصادی (Economic)</option>
                        <option value="iran_access">ایران اکسس (Iran Access)</option>
                        <option value="vip">تجاری VIP (Business Class)</option>
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

    function openEditPlanModal(p) {
        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_title').value = p.title;
        document.getElementById('edit_category').value = p.category || '۱ ماهه';
        document.getElementById('edit_server_group').value = p.server_group || 'default';
        document.getElementById('edit_server_id').value = p.server_id || '';
        document.getElementById('edit_traffic_gb').value = p.traffic_gb;
        document.getElementById('edit_duration_days').value = p.duration_days;
        document.getElementById('edit_ip_limit').value = p.ip_limit ?? 2;
        document.getElementById('edit_base_price').value = p.base_price;
        document.getElementById('edit_reseller_price').value = p.reseller_price;
        document.getElementById('edit_show_in_bot').checked = (parseInt(p.show_in_bot ?? 1) === 1);
        document.getElementById('edit_is_free').checked = (parseInt(p.is_free ?? 0) === 1);

        const modal = document.getElementById('editPlanModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeEditPlanModal() {
        const modal = document.getElementById('editPlanModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function filterCategory(cat) {
        document.querySelectorAll('.cat-pill').forEach(btn => {
            btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-500');
            btn.classList.add('bg-slate-900', 'text-slate-300', 'border-slate-800');
        });

        if (cat === 'all') {
            document.getElementById('pill-all').classList.add('bg-purple-600', 'text-white', 'border-purple-500');
            document.querySelectorAll('.plan-card').forEach(c => c.style.display = '');
        } else {
            const activeBtn = event ? event.currentTarget : null;
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-900', 'text-slate-300', 'border-slate-800');
                activeBtn.classList.add('bg-purple-600', 'text-white', 'border-purple-500');
            }
            document.querySelectorAll('.plan-card').forEach(c => {
                if (c.getAttribute('data-category') === cat) {
                    c.style.display = '';
                } else {
                    c.style.display = 'none';
                }
            });
        }
    }
</script>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/footer.php';
?>

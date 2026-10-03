<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center"><i class="fa-solid fa-box-archive"></i></span>
                    بکاپ‌های حرفه‌ای سرورها
                    <span class="text-[10px] bg-cyan-500/20 text-cyan-300 px-2 py-1 rounded-full border border-cyan-500/30">PRO v6.8.35</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">بکاپ کامل با ساب‌لینک دقیق + بازگردانی هوشمند + بکاپ خودکار قبل حذف</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= Helpers::url('backups/export-excel?server_id='.(($_GET['server_id'] ?? 0))) ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-file-excel"></i> خروجی اکسل
                </a>
                <form method="POST" action="<?= Helpers::url('backups/auto-all') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-down"></i> بکاپ همه سرورها
                    </button>
                </form>
                <a href="<?= Helpers::url('servers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs">سرورها</a>
            </div>
        </div>
    </div>

    <!-- Create Backup Form -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-800 bg-gradient-to-r from-emerald-950/20 to-slate-900">
            <h3 class="text-white font-bold flex items-center gap-2 text-sm"><i class="fa-solid fa-plus text-emerald-400"></i> ایجاد بکاپ جدید</h3>
        </div>
        <div class="p-5">
            <form method="POST" action="<?= Helpers::url('backups/create') ?>" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <?= Helpers::csrfField() ?>
                <div class="md:col-span-3">
                    <label class="block text-[11px] text-slate-400 mb-1.5 font-semibold">سرور</label>
                    <select name="server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs focus:border-cyan-500 outline-none">
                        <option value="0">🌐 همه سرورها (کامل)</option>
                        <?php foreach ($servers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (isset($_GET['server_id']) && $_GET['server_id']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[11px] text-slate-400 mb-1.5 font-semibold">نوع بکاپ</label>
                    <select name="type" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                        <option value="full">📦 کامل (دسته+پلن+کلاینت)</option>
                        <option value="clients">👥 فقط کلاینت‌ها + ساب‌لینک دقیق</option>
                        <option value="plans">📋 فقط پلن‌ها</option>
                        <option value="categories">📂 فقط دسته‌بندی‌ها</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-[11px] text-slate-400 mb-1.5 font-semibold">یادداشت</label>
                    <input type="text" name="note" placeholder="مثلاً قبل از حذف یا آپدیت..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs focus:border-cyan-500 outline-none">
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> ساخت بکاپ
                    </button>
                </div>
            </form>
            <div class="mt-4 p-3 bg-blue-950/20 border border-blue-800/30 rounded-xl text-[11px] text-blue-300 leading-relaxed">
                💡 <b>پیشنهاد حرفه‌ای:</b> سیستم قبل از هر عملیات خطرناک (حذف، همگام‌سازی) بکاپ خودکار می‌گیرد. بکاپ شامل <b>ساب‌لینک دقیق</b>، ترافیک، انقضا و پسورد اصلی است.
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex flex-wrap gap-2">
        <a href="<?= Helpers::url('backups') ?>" class="px-3 py-1.5 rounded-xl text-[11px] font-bold <?= empty($_GET['server_id']) ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">همه سرورها</a>
        <?php foreach ($servers as $s): ?>
            <a href="<?= Helpers::url('backups?server_id='.$s['id']) ?>" class="px-3 py-1.5 rounded-xl text-[11px] <?= (isset($_GET['server_id']) && $_GET['server_id']==$s['id']) ? 'bg-cyan-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>"><?= htmlspecialchars($s['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Backups List -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-white font-bold text-sm">📋 لیست بکاپ‌ها (<?= count($backups) ?>)</h3>
            <span class="text-[11px] text-slate-400">آخرین 100 بکاپ</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right font-semibold">ID</th>
                        <th class="p-3 text-right font-semibold">سرور</th>
                        <th class="p-3 text-right font-semibold">نوع</th>
                        <th class="p-3 text-center font-semibold">محتوا</th>
                        <th class="p-3 text-center font-semibold">حجم</th>
                        <th class="p-3 text-center font-semibold">حالت</th>
                        <th class="p-3 text-right font-semibold">یادداشت</th>
                        <th class="p-3 text-right font-semibold">تاریخ</th>
                        <th class="p-3 text-center font-semibold">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="9" class="p-10 text-center">
                            <div class="w-12 h-12 rounded-xl bg-slate-800 text-slate-500 mx-auto flex items-center justify-center mb-3"><i class="fa-solid fa-box-open"></i></div>
                            <div class="text-slate-400 text-sm">هنوز بکاپی وجود ندارد</div>
                            <div class="text-slate-500 text-[11px] mt-1">اولین بکاپ را از فرم بالا بسازید</div>
                        </td></tr>
                    <?php else: foreach ($backups as $b): ?>
                        <tr class="border-t border-slate-800/50 hover:bg-slate-800/30 transition">
                            <td class="p-3 font-mono text-slate-400">#<?= $b['id'] ?></td>
                            <td class="p-3 text-white font-semibold"><?= htmlspecialchars($b['server_name'] ?? 'همه') ?></td>
                            <td class="p-3">
                                <?php
                                $typeMap = [
                                    'full'=>['label'=>'📦 کامل','cls'=>'bg-purple-900/30 text-purple-300 border-purple-800/30'],
                                    'clients'=>['label'=>'👥 کلاینت','cls'=>'bg-blue-900/30 text-blue-300 border-blue-800/30'],
                                    'plans'=>['label'=>'📋 پلن','cls'=>'bg-emerald-900/30 text-emerald-300 border-emerald-800/30'],
                                    'categories'=>['label'=>'📂 دسته','cls'=>'bg-amber-900/30 text-amber-300 border-amber-800/30'],
                                    'auto_delete'=>['label'=>'⚠️ قبل حذف','cls'=>'bg-rose-900/30 text-rose-300 border-rose-800/30'],
                                ];
                                $t = $typeMap[$b['type']] ?? ['label'=>$b['type'],'cls'=>'bg-slate-800 text-slate-300'];
                                ?>
                                <span class="px-2 py-1 rounded-full text-[10px] border font-bold <?= $t['cls'] ?>"><?= $t['label'] ?></span>
                            </td>
                            <td class="p-3 text-center font-mono text-[11px]">
                                <span class="text-blue-400"><?= $b['clients_count'] ?>👥</span>
                                <span class="text-emerald-400 mx-1"><?= $b['plans_count'] ?>📋</span>
                                <span class="text-amber-400"><?= $b['categories_count'] ?>📂</span>
                            </td>
                            <td class="p-3 text-center font-mono text-slate-400"><?= round($b['file_size']/1024,1) ?>KB</td>
                            <td class="p-3 text-center"><?= $b['is_auto'] ? '<span class="px-2 py-0.5 bg-amber-900/30 text-amber-300 rounded-full text-[10px] border border-amber-800/30">🤖 خودکار</span>' : '<span class="px-2 py-0.5 bg-slate-800 text-slate-400 rounded-full text-[10px]">دستی</span>' ?></td>
                            <td class="p-3 text-slate-300 max-w-[140px] truncate text-[11px]" title="<?= htmlspecialchars($b['note'] ?? '') ?>"><?= htmlspecialchars($b['note'] ?? '-') ?></td>
                            <td class="p-3 text-slate-400 font-mono text-[11px]"><?= htmlspecialchars($b['created_at']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="<?= Helpers::url('backups/'.$b['id'].'/preview') ?>" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition" title="پیش‌نمایش"><i class="fa-solid fa-eye text-[10px]"></i></a>
                                    <a href="<?= Helpers::url('backups/'.$b['id'].'/download') ?>" class="w-7 h-7 flex items-center justify-center bg-cyan-900/30 hover:bg-cyan-800/50 text-cyan-300 rounded-lg transition" title="دانلود"><i class="fa-solid fa-download text-[10px]"></i></a>
                                    <form method="POST" action="<?= Helpers::url('backups/'.$b['id'].'/send-telegram') ?>" class="m-0 inline">
                                        <?= Helpers::csrfField() ?>
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-blue-900/30 hover:bg-blue-800/50 text-blue-300 rounded-lg transition" title="تلگرام"><i class="fa-brands fa-telegram text-[10px]"></i></button>
                                    </form>
                                    <button onclick="openRestoreModal(<?= $b['id'] ?>, '<?= htmlspecialchars($b['server_name']) ?>')" class="w-7 h-7 flex items-center justify-center bg-emerald-900/30 hover:bg-emerald-800/50 text-emerald-300 rounded-lg transition" title="بازگردانی"><i class="fa-solid fa-rotate-left text-[10px]"></i></button>
                                    <form method="POST" action="<?= Helpers::url('backups/'.$b['id'].'/delete') ?>" class="m-0" onsubmit="return confirm('حذف بکاپ #<?= $b['id'] ?>؟')">
                                        <?= Helpers::csrfField() ?>
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-rose-900/30 hover:bg-rose-800/50 text-rose-300 rounded-lg transition" title="حذف"><i class="fa-solid fa-trash text-[10px]"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-400"></i> امنیت بکاپ</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400">
                <li>• ZIP + JSON با SHA256</li>
                <li>• ساب‌لینک دقیق + پسورد اصلی</li>
                <li>• نگهداری 20 آخر هر سرور</li>
                <li>• ارسال به تلگرام</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation text-amber-400"></i> بکاپ خودکار قبل حذف</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400">
                <li>• قبل حذف تکی/گروهی کلاینت</li>
                <li>• قبل همگام‌سازی کامل</li>
                <li>• هنگام افزودن سرور</li>
                <li>• با یادداشت زمان و دلیل</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-rotate-left text-cyan-400"></i> بازگردانی هوشمند</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400">
                <li>• <b>Merge:</b> فقط جدیدها</li>
                <li>• <b>Overwrite:</b> جایگزینی کامل</li>
                <li>• انتخابی: دسته/پلن/کلاینت</li>
                <li>• پیش‌نمایش قبل بازگردانی</li>
            </ul>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <h3 class="text-white font-bold mb-4 text-sm">♻️ بازگردانی بکاپ <span id="restoreServerName" class="text-cyan-400"></span></h3>
        <form id="restoreForm" method="POST" action="">
            <?= Helpers::csrfField() ?>
            <div class="space-y-2.5 mb-5">
                <label class="flex items-center gap-2.5 cursor-pointer p-3 bg-slate-800 hover:bg-slate-700/80 rounded-xl transition border border-slate-700">
                    <input type="checkbox" name="restore_categories" value="1" checked class="rounded text-cyan-600">
                    <span class="text-xs text-white font-semibold">📂 دسته‌بندی‌ها</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer p-3 bg-slate-800 hover:bg-slate-700/80 rounded-xl transition border border-slate-700">
                    <input type="checkbox" name="restore_plans" value="1" checked class="rounded text-emerald-600">
                    <span class="text-xs text-white font-semibold">📋 پلن‌ها</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer p-3 bg-slate-800 hover:bg-slate-700/80 rounded-xl transition border border-slate-700">
                    <input type="checkbox" name="restore_clients" value="1" checked class="rounded text-blue-600">
                    <span class="text-xs text-white font-semibold">👥 کلاینت‌ها + ساب‌لینک دقیق</span>
                </label>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <label class="flex items-center gap-2 cursor-pointer p-2.5 bg-emerald-950/30 border border-emerald-800/30 rounded-xl hover:bg-emerald-900/20 transition">
                        <input type="radio" name="mode" value="merge" checked class="text-emerald-600">
                        <span class="text-[11px] text-emerald-300 font-bold">ادغام (فقط جدید)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-2.5 bg-amber-950/30 border border-amber-800/30 rounded-xl hover:bg-amber-900/20 transition">
                        <input type="radio" name="mode" value="overwrite" class="text-amber-600">
                        <span class="text-[11px] text-amber-300 font-bold">جایگزینی کامل</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs">بازگردانی</button>
                <button type="button" onclick="closeRestoreModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRestoreModal(id, serverName) {
    document.getElementById('restoreServerName').textContent = serverName;
    document.getElementById('restoreForm').action = '<?= Helpers::url('backups/') ?>' + id + '/restore';
    document.getElementById('restoreModal').classList.remove('hidden');
    document.getElementById('restoreModal').classList.add('flex');
}
function closeRestoreModal() {
    document.getElementById('restoreModal').classList.add('hidden');
    document.getElementById('restoreModal').classList.remove('flex');
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

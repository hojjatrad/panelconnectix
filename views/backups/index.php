<?php
require __DIR__ . '/../layout/header.php';
?>
<div class="p-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-white flex items-center gap-3">
            <i class="fa-solid fa-box-archive text-cyan-400"></i>
            بکاپ‌های حرفه‌ای سرورها
            <span class="text-xs bg-cyan-900/40 text-cyan-300 px-2 py-1 rounded-lg border border-cyan-800/50">v6.8.28 PRO</span>
        </h1>
        <div class="flex gap-2">
            <a href="<?= Helpers::url('backups/export-excel?server_id='.(($_GET['server_id'] ?? 0))) ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold flex items-center gap-2">
                <i class="fa-solid fa-file-excel"></i> خروجی اکسل کلاینت‌ها
            </a>
            <form method="POST" action="<?= Helpers::url('backups/auto-all') ?>" class="m-0">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-down"></i> بکاپ خودکار همه سرورها
                </button>
            </form>
            <a href="<?= Helpers::url('servers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">سرورها</a>
        </div>
    </div>

    <!-- Create Backup Form -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-plus text-emerald-400"></i> ایجاد بکاپ جدید</h3>
        <form method="POST" action="<?= Helpers::url('backups/create') ?>" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <?= Helpers::csrfField() ?>
            <div>
                <label class="block text-xs text-slate-400 mb-1">سرور</label>
                <select name="server_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm">
                    <option value="0">🌐 همه سرورها (بکاپ کامل)</option>
                    <?php foreach ($servers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (isset($_GET['server_id']) && $_GET['server_id']==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">نوع بکاپ</label>
                <select name="type" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm">
                    <option value="full">📦 کامل (دسته+پلن+کلاینت)</option>
                    <option value="clients">👥 فقط کلاینت‌ها + ساب‌لینک دقیق</option>
                    <option value="plans">📋 فقط پلن‌ها</option>
                    <option value="categories">📂 فقط دسته‌بندی‌ها</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">یادداشت</label>
                <input type="text" name="note" placeholder="مثلاً قبل از حذف یا آپدیت..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> ساخت بکاپ
                </button>
            </div>
        </form>
        <div class="mt-3 p-3 bg-blue-950/20 border border-blue-800/30 rounded-xl text-xs text-blue-300">
            💡 <b>پیشنهاد حرفه‌ای:</b> سیستم به صورت خودکار قبل از هر عملیات خطرناک (حذف کلاینت، همگام‌سازی کامل، حذف دسته‌جمعی) بکاپ می‌گیرد. بکاپ‌ها شامل <b>ساب‌لینک دقیق سرور اصلی</b>، ترافیک مصرفی، تاریخ انقضا و پسورد اصلی هستند و قابل بازگردانی با دو حالت <code>ادغام (Merge)</code> و <code>جایگزینی کامل (Overwrite)</code> هستند.
        </div>
    </div>

    <!-- Filter -->
    <div class="flex gap-2 mb-4">
        <a href="<?= Helpers::url('backups') ?>" class="px-3 py-1.5 rounded-lg text-xs <?= empty($_GET['server_id']) ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300' ?>">همه</a>
        <?php foreach ($servers as $s): ?>
            <a href="<?= Helpers::url('backups?server_id='.$s['id']) ?>" class="px-3 py-1.5 rounded-lg text-xs <?= (isset($_GET['server_id']) && $_GET['server_id']==$s['id']) ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300' ?>"><?= htmlspecialchars($s['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Backups List -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50 text-slate-400 text-xs">
                    <tr>
                        <th class="p-3 text-right">شناسه</th>
                        <th class="p-3 text-right">سرور</th>
                        <th class="p-3 text-right">نوع</th>
                        <th class="p-3 text-center">محتوا</th>
                        <th class="p-3 text-center">حجم</th>
                        <th class="p-3 text-center">خودکار؟</th>
                        <th class="p-3 text-right">یادداشت</th>
                        <th class="p-3 text-right">تاریخ</th>
                        <th class="p-3 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="9" class="p-8 text-center text-slate-400">هنوز بکاپی وجود ندارد. اولین بکاپ را بسازید.</td></tr>
                    <?php else: foreach ($backups as $b): ?>
                        <tr class="border-t border-slate-800 hover:bg-slate-800/30">
                            <td class="p-3 font-mono text-slate-300">#<?= $b['id'] ?></td>
                            <td class="p-3 text-white"><?= htmlspecialchars($b['server_name'] ?? 'همه') ?></td>
                            <td class="p-3">
                                <?php
                                $typeLabel = ['full'=>'📦 کامل','clients'=>'👥 کلاینت','plans'=>'📋 پلن','categories'=>'📂 دسته','auto_delete'=>'⚠️ قبل حذف'][$b['type']] ?? $b['type'];
                                $typeColor = ['full'=>'bg-purple-900/40 text-purple-300 border-purple-800/50','clients'=>'bg-blue-900/40 text-blue-300 border-blue-800/50','plans'=>'bg-emerald-900/40 text-emerald-300 border-emerald-800/50','categories'=>'bg-amber-900/40 text-amber-300 border-amber-800/50','auto_delete'=>'bg-rose-900/40 text-rose-300 border-rose-800/50'][$b['type']] ?? 'bg-slate-800 text-slate-300';
                                ?>
                                <span class="px-2 py-1 rounded-lg text-[11px] border <?= $typeColor ?>"><?= $typeLabel ?></span>
                            </td>
                            <td class="p-3 text-center font-mono text-xs">
                                <span class="text-blue-400"><?= $b['clients_count'] ?>👥</span>
                                <span class="text-emerald-400"><?= $b['plans_count'] ?>📋</span>
                                <span class="text-amber-400"><?= $b['categories_count'] ?>📂</span>
                            </td>
                            <td class="p-3 text-center font-mono text-slate-400"><?= round($b['file_size']/1024,1) ?>KB</td>
                            <td class="p-3 text-center"><?= $b['is_auto'] ? '<span class="text-amber-400">🤖 خودکار</span>' : '<span class="text-slate-400">دستی</span>' ?></td>
                            <td class="p-3 text-slate-300 text-xs max-w-[150px] truncate" title="<?= htmlspecialchars($b['note'] ?? '') ?>"><?= htmlspecialchars($b['note'] ?? '-') ?></td>
                            <td class="p-3 text-slate-400 font-mono text-xs"><?= htmlspecialchars($b['created_at']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="<?= Helpers::url('backups/'.$b['id'].'/preview') ?>" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg" title="پیش‌نمایش"><i class="fa-solid fa-eye text-[11px]"></i></a>
                                    <a href="<?= Helpers::url('backups/'.$b['id'].'/download') ?>" class="w-7 h-7 flex items-center justify-center bg-cyan-900/40 hover:bg-cyan-800/60 text-cyan-300 rounded-lg" title="دانلود"><i class="fa-solid fa-download text-[11px]"></i></a>
                                    <form method="POST" action="<?= Helpers::url('backups/'.$b['id'].'/send-telegram') ?>" class="m-0 inline">
                                        <?= Helpers::csrfField() ?>
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-blue-900/40 hover:bg-blue-800/60 text-blue-300 rounded-lg" title="ارسال به تلگرام"><i class="fa-brands fa-telegram text-[11px]"></i></button>
                                    </form>
                                    <button onclick="openRestoreModal(<?= $b['id'] ?>, '<?= htmlspecialchars($b['server_name']) ?>')" class="w-7 h-7 flex items-center justify-center bg-emerald-900/40 hover:bg-emerald-800/60 text-emerald-300 rounded-lg" title="بازگردانی"><i class="fa-solid fa-rotate-left text-[11px]"></i></button>
                                    <form method="POST" action="<?= Helpers::url('backups/'.$b['id'].'/delete') ?>" class="m-0" onsubmit="return confirm('آیا از حذف این بکاپ اطمینان دارید؟')">
                                        <?= Helpers::csrfField() ?>
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-rose-900/40 hover:bg-rose-800/60 text-rose-300 rounded-lg" title="حذف"><i class="fa-solid fa-trash text-[11px]"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <h4 class="font-bold text-white mb-2">🔒 امنیت بکاپ</h4>
            <ul class="space-y-1 text-slate-400 list-disc list-inside">
                <li>فایل‌ها ZIP + JSON با checksum SHA256</li>
                <li>شامل ساب‌لینک دقیق + پسورد اصلی + ترافیک</li>
                <li>نگهداری چرخشی آخرین 20 بکاپ هر سرور</li>
                <li>قابل ارسال به تلگرام / گوگل درایو</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <h4 class="font-bold text-white mb-2">⚠️ بکاپ خودکار قبل حذف</h4>
            <ul class="space-y-1 text-slate-400 list-disc list-inside">
                <li>قبل حذف تکی یا دسته‌جمعی کلاینت</li>
                <li>قبل همگام‌سازی کامل سرور</li>
                <li>هنگام افزودن سرور جدید</li>
                <li>با یادداشت زمان و دلیل</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
            <h4 class="font-bold text-white mb-2">♻️ بازگردانی هوشمند</h4>
            <ul class="space-y-1 text-slate-400 list-disc list-inside">
                <li><b>Merge:</b> فقط موارد جدید را اضافه کن</li>
                <li><b>Overwrite:</b> حذف و جایگزینی کامل</li>
                <li>انتخابی: فقط دسته یا پلن یا کلاینت</li>
                <li>پیش‌نمایش قبل از بازگردانی</li>
            </ul>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6">
        <h3 class="text-white font-bold mb-4">♻️ بازگردانی بکاپ <span id="restoreServerName" class="text-cyan-400"></span></h3>
        <form id="restoreForm" method="POST" action="">
            <?= Helpers::csrfField() ?>
            <div class="space-y-3 mb-4">
                <label class="flex items-center gap-2 cursor-pointer p-2 bg-slate-800 rounded-xl">
                    <input type="checkbox" name="restore_categories" value="1" checked class="rounded">
                    <span class="text-sm text-white">📂 دسته‌بندی‌ها</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 bg-slate-800 rounded-xl">
                    <input type="checkbox" name="restore_plans" value="1" checked class="rounded">
                    <span class="text-sm text-white">📋 پلن‌ها</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer p-2 bg-slate-800 rounded-xl">
                    <input type="checkbox" name="restore_clients" value="1" checked class="rounded">
                    <span class="text-sm text-white">👥 کلاینت‌ها + ساب‌لینک دقیق</span>
                </label>
                <div class="grid grid-cols-2 gap-2 mt-3">
                    <label class="flex items-center gap-2 cursor-pointer p-2 bg-emerald-950/30 border border-emerald-800/30 rounded-xl">
                        <input type="radio" name="mode" value="merge" checked>
                        <span class="text-xs text-emerald-300">ادغام (فقط جدید)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer p-2 bg-amber-950/30 border border-amber-800/30 rounded-xl">
                        <input type="radio" name="mode" value="overwrite">
                        <span class="text-xs text-amber-300">جایگزینی کامل</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm">بازگردانی</button>
                <button type="button" onclick="closeRestoreModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">انصراف</button>
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

<?php
require __DIR__ . '/../layout/footer.php';
?>

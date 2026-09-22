<?php
$pageTitle = 'مدیریت نرم‌افزارهای اتصال و آموزش‌ها';
require __DIR__ . '/../layout/header.php';

$platforms = [
    'android' => ['title' => 'اندروید (Android)', 'icon' => 'fa-brands fa-android', 'color' => 'text-emerald-400'],
    'ios' => ['title' => 'آیفون و آیپد (iOS)', 'icon' => 'fa-brands fa-apple', 'color' => 'text-slate-200'],
    'windows' => ['title' => 'ویندوز (Windows)', 'icon' => 'fa-brands fa-windows', 'color' => 'text-cyan-400'],
    'macos' => ['title' => 'مک‌بوک (macOS)', 'icon' => 'fa-brands fa-apple', 'color' => 'text-purple-300'],
    'linux' => ['title' => 'لینوکس (Linux)', 'icon' => 'fa-brands fa-linux', 'color' => 'text-amber-400']
];
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-mobile-screen-button text-cyan-400"></i>
                <span>مدیریت نرم‌افزارهای اتصال و ویدیوهای آموزشی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">تنظیم لینک‌های دانلود مستقیم و آموزش‌های ویدیویی برای نمایش مرتب در منوی ربات تلگرام</p>
        </div>

        <button onclick="openNewGuideModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>افزودن نرم‌افزار جدید</span>
        </button>
    </div>

    <!-- Grouped by platform -->
    <?php foreach ($platforms as $platKey => $platInfo): 
        $platGuides = array_filter($guides, fn($g) => $g['platform'] === $platKey);
    ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <i class="<?= $platInfo['icon'] ?> text-lg <?= $platInfo['color'] ?>"></i>
                    <h3 class="text-sm font-bold text-white"><?= $platInfo['title'] ?></h3>
                </div>
                <span class="text-xs text-slate-400 font-mono"><?= count($platGuides) ?> نرم‌افزار</span>
            </div>

            <?php if (empty($platGuides)): ?>
                <div class="p-6 text-center text-slate-500 text-xs">
                    هنوز نرم‌افزاری برای این سیستم‌عامل ثبت نشده است.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($platGuides as $g): ?>
                        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-4 flex flex-col justify-between space-y-3">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-white text-xs flex items-center gap-1.5">
                                        <i class="fa-solid fa-circle-check text-cyan-400 text-[10px]"></i>
                                        <span><?= htmlspecialchars($g['app_name']) ?></span>
                                    </h4>
                                    <span class="text-[9px] px-2 py-0.5 rounded font-bold <?= $g['is_active'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                        <?= $g['is_active'] ? 'فعال' : 'غیرفعال' ?>
                                    </span>
                                </div>
                                <?php if (!empty($g['description'])): ?>
                                    <p class="text-[11px] text-slate-400 mt-2 leading-relaxed"><?= htmlspecialchars($g['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="pt-2 border-t border-slate-800/80 space-y-2">
                                <div class="flex items-center gap-2 text-[10px]">
                                    <a href="<?= htmlspecialchars($g['download_url']) ?>" target="_blank" class="text-cyan-400 hover:underline flex items-center gap-1">
                                        <i class="fa-solid fa-download"></i> لینک دانلود
                                    </a>
                                    <?php if (!empty($g['guide_url'])): ?>
                                        <span class="text-slate-600">|</span>
                                        <a href="<?= htmlspecialchars($g['guide_url']) ?>" target="_blank" class="text-amber-400 hover:underline flex items-center gap-1">
                                            <i class="fa-solid fa-video"></i> ویدیو آموزشی
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-1.5 pt-1">
                                    <button onclick='openEditGuideModal(<?= json_encode($g, JSON_UNESCAPED_UNICODE) ?>)' class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs font-bold rounded-lg border border-slate-700 transition">
                                        <i class="fa-solid fa-pen text-[10px]"></i> ویرایش
                                    </button>
                                    <form action="<?= Helpers::url('settings/app-guides/toggle') ?>" method="POST" class="m-0">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-700 transition">
                                            <?= $g['is_active'] ? 'غیرفعال' : 'فعال' ?>
                                        </button>
                                    </form>
                                    <form action="<?= Helpers::url('settings/app-guides/delete') ?>" method="POST" class="m-0" onsubmit="return confirm('آیا از حذف این برنامه اطمینان دارید؟')">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="p-1 bg-slate-800 hover:bg-rose-900/40 text-rose-400 text-xs rounded-lg border border-slate-700 transition">
                                            <i class="fa-solid fa-trash text-[10px]"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: New Guide -->
<div id="newGuideModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeNewGuideModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-mobile-screen text-cyan-400"></i>
            <span>افزودن نرم‌افزار یا آموزش جدید</span>
        </h3>

        <form action="<?= Helpers::url('settings/app-guides/store') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سیستم‌عامل *</label>
                    <select name="platform" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="android">اندروید (Android)</option>
                        <option value="ios">آیفون و آیپد (iOS)</option>
                        <option value="windows">ویندوز (Windows)</option>
                        <option value="macos">مک‌بوک (macOS)</option>
                        <option value="linux">لینوکس (Linux)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نام نرم‌افزار *</label>
                <input type="text" name="app_name" required placeholder="مثلاً: v2rayNG (پیشنهادی)" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">لینک دانلود مستقیم / استور *</label>
                <input type="url" name="download_url" required placeholder="https://..." dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-left">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">لینک ویدیو یا پست آموزشی (اختیاری)</label>
                <input type="url" name="guide_url" placeholder="https://t.me/... یا لینک یوتیوب" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-left">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات کوتاه راهنما</label>
                <textarea name="description" rows="2" placeholder="توضیحاتی درباره نحوه اتصال و ویژگی‌های این نرم‌افزار..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره نرم‌افزار
            </button>
        </form>
    </div>
</div>

<!-- Modal: Edit Guide -->
<div id="editGuideModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-amber-500/40 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeEditGuideModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-amber-400"></i>
            <span>ویرایش نرم‌افزار و آموزش</span>
        </h3>

        <form action="<?= Helpers::url('settings/app-guides/update') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="id" id="edit_app_id" value="">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سیستم‌عامل *</label>
                    <select name="platform" id="edit_app_platform" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="android">اندروید (Android)</option>
                        <option value="ios">آیفون و آیپد (iOS)</option>
                        <option value="windows">ویندوز (Windows)</option>
                        <option value="macos">مک‌بوک (macOS)</option>
                        <option value="linux">لینوکس (Linux)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">ترتیب نمایش</label>
                    <input type="number" name="sort_order" id="edit_app_sort" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نام نرم‌افزار *</label>
                <input type="text" name="app_name" id="edit_app_name" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">لینک دانلود مستقیم / استور *</label>
                <input type="url" name="download_url" id="edit_app_download" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-left">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">لینک ویدیو یا آموزش</label>
                <input type="url" name="guide_url" id="edit_app_guide" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-left">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات کوتاه</label>
                <textarea name="description" id="edit_app_desc" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره تغییرات
            </button>
        </form>
    </div>
</div>

<script>
    function openNewGuideModal() {
        document.getElementById('newGuideModal').classList.remove('hidden');
        document.getElementById('newGuideModal').classList.add('flex');
    }
    function closeNewGuideModal() {
        document.getElementById('newGuideModal').classList.remove('flex');
        document.getElementById('newGuideModal').classList.add('hidden');
    }

    function openEditGuideModal(g) {
        document.getElementById('edit_app_id').value = g.id;
        document.getElementById('edit_app_platform').value = g.platform;
        document.getElementById('edit_app_name').value = g.app_name;
        document.getElementById('edit_app_download').value = g.download_url;
        document.getElementById('edit_app_guide').value = g.guide_url || '';
        document.getElementById('edit_app_desc').value = g.description || '';
        document.getElementById('edit_app_sort').value = g.sort_order || 0;

        document.getElementById('editGuideModal').classList.remove('hidden');
        document.getElementById('editGuideModal').classList.add('flex');
    }
    function closeEditGuideModal() {
        document.getElementById('editGuideModal').classList.remove('flex');
        document.getElementById('editGuideModal').classList.add('hidden');
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

<?php
$pageTitle = 'پایگاه دانش هوش مصنوعی';
require __DIR__ . '/../layout/header.php';
$categories = ['فنی', 'پولی', 'نماینده', 'گزارش خطا', 'سایر'];
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-book-open text-teal-400"></i>
                <span>پایگاه دانش (RAG)</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">دستیار فقط با این مستندات جواب می‌دهد. هرچه دقیق‌تر و تازه‌تر بنویسید، پاسخ‌ها بهتر می‌شود.</p>
        </div>
        <a href="<?= Helpers::url('settings/ai') ?>" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-right"></i> بازگشت به تنظیمات AI
        </a>
    </div>

    <!-- Search test -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="<?= Helpers::url('settings/ai/knowledge') ?>" class="flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="تست جستجو: مثلاً «سرعت کم است چه کار کنم؟»"
                   class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-teal-900/50 text-teal-300 rounded-xl text-xs font-bold border border-slate-700 transition">جستجو</button>
            <?php if (!empty($_GET['q'])): ?><a href="<?= Helpers::url('settings/ai/knowledge') ?>" class="px-3 py-2 bg-slate-800 text-slate-400 rounded-xl text-xs border border-slate-700">پاک کردن</a><?php endif; ?>
        </form>
        <?php if ($searchHits !== null): ?>
            <div class="mt-3 space-y-2">
                <?php if (empty($searchHits)): ?>
                    <div class="text-[11px] text-rose-300">موردی با این جستجو پیدا نشد — احتمالاً مستند جدید لازم است.</div>
                <?php else: ?>
                    <div class="text-[11px] text-slate-500 mb-1">مستنداتی که AI برای این سؤال خواهد دید:</div>
                    <?php foreach ($searchHits as $h): ?>
                        <div class="flex items-center gap-2 bg-slate-950/60 border border-slate-800 rounded-lg px-3 py-2">
                            <i class="fa-solid fa-file-lines text-teal-400 text-xs"></i>
                            <span class="text-xs text-white font-bold flex-1"><?= htmlspecialchars($h['title']) ?></span>
                            <span class="text-[10px] text-slate-500 font-mono">score: <?= $h['score'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($editing): ?>
    <!-- Edit form -->
    <form action="<?= Helpers::url('settings/ai/knowledge/update') ?>" method="POST" class="bg-slate-900/80 border border-teal-800/50 rounded-2xl p-5 shadow-sm space-y-3">
        <?= Helpers::csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <h3 class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-solid fa-pen text-teal-400"></i> ویرایش سند #<?= (int)$editing['id'] ?></h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <label class="block text-[11px] text-slate-400 mb-1">عنوان</label>
                <input type="text" name="title" value="<?= htmlspecialchars($editing['title']) ?>" required class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">دسته</label>
                <select name="category" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
                    <?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= $editing['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-[11px] text-slate-400 mb-1">کلیدواژه‌ها (با ویرایش جدا کنید — برای جستجو مهم است)</label>
            <input type="text" name="keywords" value="<?= htmlspecialchars((string)$editing['keywords']) ?>" placeholder="سرعت,کند,dl,ul" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] text-slate-400 mb-1">متن مستند (پاسخ پیشنهادی کامل)</label>
            <textarea name="content" rows="6" required class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white leading-relaxed focus:border-teal-500 focus:outline-none"><?= htmlspecialchars((string)$editing['content']) ?></textarea>
        </div>
        <label class="flex items-center gap-2 text-xs text-slate-300">
            <input type="checkbox" name="is_active" value="1" <?= $editing['is_active'] ? 'checked' : '' ?> class="w-4 h-4 accent-teal-500"> فعال
        </label>
        <div class="flex justify-end gap-2">
            <a href="<?= Helpers::url('settings/ai/knowledge') ?>" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs border border-slate-700">انصراف</a>
            <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold">ذخیره</button>
        </div>
    </form>
    <?php else: ?>
    <!-- New form -->
    <form action="<?= Helpers::url('settings/ai/knowledge/store') ?>" method="POST" class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
        <?= Helpers::csrfField() ?>
        <h3 class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-solid fa-plus text-teal-400"></i> مستند جدید</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
                <label class="block text-[11px] text-slate-400 mb-1">عنوان</label>
                <input type="text" name="title" required placeholder="مثلاً: روش پرداخت و فعال‌سازی سرویس" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">دسته</label>
                <select name="category" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
                    <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-[11px] text-slate-400 mb-1">کلیدواژه‌ها (با ویرایش جدا کنید)</label>
            <input type="text" name="keywords" placeholder="پرداخت,خرید,شارژ,کارت" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-teal-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] text-slate-400 mb-1">متن مستند</label>
            <textarea name="content" rows="5" required placeholder="پاسخ کامل و مرحله‌به‌مرحله که می‌خواهید AI بدهد..." class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white leading-relaxed focus:border-teal-500 focus:outline-none"></textarea>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold">افزودن مستند</button>
        </div>
    </form>
    <?php endif; ?>

    <!-- List -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-right">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-950/50 text-[10px] text-slate-500">
                    <th class="px-4 py-3 font-bold">مستند</th>
                    <th class="px-4 py-3 font-bold w-24">دسته</th>
                    <th class="px-4 py-3 font-bold w-20">وضعیت</th>
                    <th class="px-4 py-3 font-bold w-40"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                <tr class="border-b border-slate-800/60 hover:bg-slate-950/40 transition">
                    <td class="px-4 py-3">
                        <div class="text-xs font-bold text-white"><?= htmlspecialchars($d['title']) ?></div>
                        <div class="text-[10px] text-slate-500 mt-0.5 line-clamp-1"><?= htmlspecialchars(mb_substr((string)$d['content'], 0, 90)) ?>...</div>
                    </td>
                    <td class="px-4 py-3"><span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300"><?= htmlspecialchars((string)$d['category']) ?></span></td>
                    <td class="px-4 py-3">
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $d['is_active'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                            <?= $d['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5 justify-end">
                            <a href="<?= Helpers::url('settings/ai/knowledge?edit=' . (int)$d['id']) ?>" class="px-2.5 py-1.5 bg-slate-800 hover:bg-teal-900/50 text-teal-300 rounded-lg text-[10px] font-bold border border-slate-700"><i class="fa-solid fa-pen"></i></a>
                            <form method="POST" action="<?= Helpers::url('settings/ai/knowledge/toggle') ?>" class="m-0">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 rounded-lg text-[10px] border border-slate-700" title="تغییر وضعیت"><i class="fa-solid fa-rotate"></i></button>
                            </form>
                            <form method="POST" action="<?= Helpers::url('settings/ai/knowledge/delete') ?>" class="m-0" onsubmit="return confirm('حذف این مستند؟');">
                                <?= Helpers::csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 bg-slate-800 hover:bg-rose-900/50 text-rose-300 rounded-lg text-[10px] border border-slate-700"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

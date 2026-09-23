<?php
$title = 'ساخت گروهی اکانت (Bulk Create)';
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-purple-400"></i>
                <span>ساخت گروهی اکانت اشتراک</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">تولید دسته‌ای اکانت‌ها با پیش‌وند و پلن مشخص جهت فروش عمده، فیزیکی یا توزیع سریع</p>
        </div>
        <a href="<?= Helpers::url('clients') ?>" class="px-3.5 py-2 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 text-xs font-bold transition flex items-center gap-1.5 border border-slate-700">
            <i class="fa-solid fa-arrow-right"></i>
            <span>بازگشت به لیست کلاینت‌ها</span>
        </a>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <form method="POST" action="<?= Helpers::url('clients/bulk-store') ?>" class="space-y-4">
            <?= Helpers::csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-300 mb-1.5 font-bold text-xs">تعداد اکانت جهت صدور همزمان:</label>
                    <select name="count" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white text-xs font-bold">
                        <option value="5">۵ اکانت</option>
                        <option value="10" selected>۱۰ اکانت</option>
                        <option value="20">۲۰ اکانت</option>
                        <option value="50">۵۰ اکانت</option>
                        <option value="100">۱۰۰ اکانت (حداکثر)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-bold text-xs">پیش‌وند نام کاربری (Prefix):</label>
                    <input type="text" name="prefix" value="vip_" placeholder="مثال: vip_ یا eco_" dir="ltr" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white text-xs font-mono">
                    <span class="text-[10px] text-slate-500 mt-1 block">به انتهای این پیش‌وند یک کد یکتا متشکل از ۵ کاراکتر تصادفی اضافه خواهد شد.</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-300 mb-1.5 font-bold text-xs">انتخاب پلن سرویس:</label>
                    <select name="plan_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white text-xs">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB - <?= $p['duration_days'] ?> روزه) - <?= number_format($p['base_price']) ?> تومان
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-bold text-xs">انتخاب نود سرور میزبان:</label>
                    <select name="server_id" required class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white text-xs">
                        <?php foreach ($servers as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['server_group']) ?> - <?= htmlspecialchars($s['driver']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (Auth::isAdmin() && !empty($resellers)): ?>
            <div>
                <label class="block text-slate-300 mb-1.5 font-bold text-xs">تخصیص به نماینده (اختیاری):</label>
                <select name="reseller_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white text-xs">
                    <option value="">مدیر کل (بدون نماینده)</option>
                    <?php foreach ($resellers as $res): ?>
                        <option value="<?= $res['id'] ?>"><?= htmlspecialchars($res['username']) ?> (شناسه: <?= $res['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-800/80">
                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold rounded-xl text-xs shadow-lg shadow-purple-600/30 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>شروع تولید و صدور گروهی اکانت‌ها</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

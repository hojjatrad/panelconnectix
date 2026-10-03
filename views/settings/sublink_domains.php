<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center"><i class="fa-solid fa-globe"></i></span>
                    دامنه‌های ساب‌لینک چرخشی (ضد فیلتر)
                    <span class="text-[10px] bg-cyan-500/20 text-cyan-300 px-2 py-1 rounded-full border border-cyan-500/30">PRO MAX</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">اگر یک دامنه فیلتر شد، خودکار به دامنه بعدی سوییچ می‌کند. هر 24 ساعت سلامت چک می‌شود.</p>
            </div>
            <form method="POST" action="<?= Helpers::url('settings/sublink-domains/check') ?>" class="m-0">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs font-bold">🔄 بررسی سلامت همه</button>
            </form>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-4 text-sm">➕ افزودن دامنه جدید</h3>
        <form method="POST" action="<?= Helpers::url('settings/sublink-domains/add') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <?= Helpers::csrfField() ?>
            <div class="md:col-span-2">
                <label class="block text-[11px] text-slate-400 mb-1.5">دامنه (بدون https)</label>
                <input type="text" name="domain" placeholder="direct2.vpbotn.ir یا mydomain.com" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs font-mono focus:border-cyan-500 outline-none">
            </div>
            <div class="flex items-center gap-2 pt-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_primary" value="1" class="rounded">
                    <span class="text-xs text-white">دامنه اصلی</span>
                </label>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs">افزودن دامنه</button>
            </div>
        </form>
        <div class="mt-3 p-3 bg-blue-950/20 border border-blue-800/30 rounded-xl text-[11px] text-blue-300">
            💡 <b>پیشنهاد حرفه‌ای:</b> 3 دامنه بسازید: <code>direct.vpbotn.ir</code> (اصلی) + <code>direct2.vpbotn.ir</code> + <code>direct3.vpbotn.ir</code> - همه با ابر خاکستری. اگر یکی فیلتر شد، سیستم خودکار به بعدی سوییچ می‌کند و به تلگرام اطلاع می‌دهد.
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right">دامنه</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-center">تاخیر</th>
                        <th class="p-3 text-center">Fail</th>
                        <th class="p-3 text-center">فعال؟</th>
                        <th class="p-3 text-center">اصلی؟</th>
                        <th class="p-3 text-right">آخرین چک</th>
                        <th class="p-3 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($domains)): ?>
                        <tr><td colspan="8" class="p-8 text-center text-slate-400">دامنه‌ای وجود ندارد</td></tr>
                    <?php else: foreach ($domains as $d):
                        $healthCls = $d['health_status'] === 'online' ? 'bg-emerald-900/30 text-emerald-300 border-emerald-800/30' : ($d['health_status'] === 'offline' ? 'bg-rose-900/30 text-rose-300 border-rose-800/30' : 'bg-amber-900/30 text-amber-300 border-amber-800/30');
                    ?>
                    <tr class="border-t border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-3 font-mono font-bold text-white"><?= htmlspecialchars($d['domain']) ?></td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded-full text-[10px] border font-bold <?= $healthCls ?>"><?= htmlspecialchars($d['health_status']) ?></span></td>
                        <td class="p-3 text-center font-mono text-slate-300"><?= $d['latency_ms'] ?>ms</td>
                        <td class="p-3 text-center font-mono text-rose-400"><?= $d['fail_count'] ?></td>
                        <td class="p-3 text-center"><?= $d['is_active'] ? '✅' : '❌' ?></td>
                        <td class="p-3 text-center"><?= $d['is_primary'] ? '<span class="px-2 py-0.5 bg-cyan-500/20 text-cyan-300 rounded-full text-[9px] border border-cyan-500/30">اصلی</span>' : '' ?></td>
                        <td class="p-3 font-mono text-[11px] text-slate-400"><?= htmlspecialchars($d['last_checked_at'] ?? '-') ?></td>
                        <td class="p-3">
                            <div class="flex gap-1 justify-center">
                                <form method="POST" action="<?= Helpers::url('settings/sublink-domains/'.$d['id'].'/primary') ?>" class="m-0 inline">
                                    <?= Helpers::csrfField() ?>
                                    <button type="submit" class="w-7 h-7 bg-cyan-900/30 hover:bg-cyan-800/50 text-cyan-300 rounded-lg flex items-center justify-center" title="اصلی کن"><i class="fa-solid fa-star text-[10px]"></i></button>
                                </form>
                                <form method="POST" action="<?= Helpers::url('settings/sublink-domains/'.$d['id'].'/toggle') ?>" class="m-0 inline">
                                    <?= Helpers::csrfField() ?>
                                    <button type="submit" class="w-7 h-7 bg-amber-900/30 hover:bg-amber-800/50 text-amber-300 rounded-lg flex items-center justify-center" title="فعال/غیرفعال"><i class="fa-solid fa-power-off text-[10px]"></i></button>
                                </form>
                                <form method="POST" action="<?= Helpers::url('settings/sublink-domains/'.$d['id'].'/delete') ?>" class="m-0 inline" onsubmit="return confirm('حذف دامنه <?= htmlspecialchars($d['domain']) ?>؟')">
                                    <?= Helpers::csrfField() ?>
                                    <button type="submit" class="w-7 h-7 bg-rose-900/30 hover:bg-rose-800/50 text-rose-300 rounded-lg flex items-center justify-center" title="حذف"><i class="fa-solid fa-trash text-[10px]"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3 text-sm">📖 چطور کار می‌کند؟</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-[11px] text-slate-400 leading-relaxed">
            <div class="bg-slate-800/50 rounded-xl p-3">
                <div class="font-bold text-white mb-1">1. بررسی سلامت هر 24 ساعت</div>
                Cron هر روز دامنه‌ها را پینگ می‌کند و latency و وضعیت را ثبت می‌کند.
            </div>
            <div class="bg-slate-800/50 rounded-xl p-3">
                <div class="font-bold text-white mb-1">2. چرخش خودکار اگر آفلاین</div>
                اگر دامنه اصلی 3 بار پشت سر هم آفلاین بود، خودکار به بهترین دامنه آنلاین سوییچ می‌کند و به تلگرام اطلاع می‌دهد.
            </div>
            <div class="bg-slate-800/50 rounded-xl p-3">
                <div class="font-bold text-white mb-1">3. ساب‌لینک‌ها با بهترین دامنه</div>
                ساب‌لینک جدید با دامنه‌ای که کمترین تاخیر را دارد ساخته می‌شود. اگر دامنه فعلی آفلاین باشد، خودکار جایگزین می‌شود.
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

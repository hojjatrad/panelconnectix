<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="glass rounded-[2rem] p-8 text-center relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-violet-600/20 rounded-full blur-[60px]"></div>
        <div class="absolute -bottom-20 -left-20 w-72 h-72 bg-cyan-600/20 rounded-full blur-[60px]"></div>
        <div class="relative z-10">
            <div class="w-20 h-20 rounded-[1.5rem] bg-gradient-to-br from-violet-600 to-indigo-600 mx-auto flex items-center justify-center text-white text-3xl shadow-xl mb-4">🚀</div>
            <h1 class="text-2xl font-black text-white">به Connectix ULTRA v7.0 خوش آمدی!</h1>
            <p class="text-sm text-slate-400 mt-2">راه‌اندازی پنل در 4 مرحله ساده - کمتر از 2 دقیقه</p>
            <div class="mt-6 flex justify-center gap-2">
                <?php for($i=0;$i<4;$i++): $active = $i <= ($progress['step'] ?? 0); ?>
                <div class="h-2 rounded-full transition-all duration-500 <?= $active ? 'w-12 bg-violet-500' : 'w-6 bg-slate-700' ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="glass rounded-2xl p-5 border <?= $hasBot ? 'border-emerald-800/30 bg-emerald-950/10' : 'border-amber-800/30 bg-amber-950/10' ?>">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-xl <?= $hasBot ? 'bg-emerald-600' : 'bg-amber-600' ?> flex items-center justify-center text-white"><i class="fa-brands fa-telegram"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">1. ربات تلگرام</h3>
                    <p class="text-[11px] text-slate-400"><?= $hasBot ? '✅ متصل است' : '⚠️ هنوز متصل نشده' ?></p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed mb-3">ربات تلگرام را متصل کن تا فروش خودکار و اعلان‌ها فعال شود.</p>
            <a href="<?= Helpers::url('settings/bot') ?>" class="block text-center py-2.5 <?= $hasBot ? 'bg-slate-800 text-slate-300' : 'bg-violet-600 hover:bg-violet-700 text-white' ?> rounded-xl text-xs font-bold transition"><?= $hasBot ? 'مدیریت ربات' : 'اتصال ربات →' ?></a>
        </div>

        <div class="glass rounded-2xl p-5 border <?= $serversCount>0 ? 'border-emerald-800/30 bg-emerald-950/10' : 'border-amber-800/30 bg-amber-950/10' ?>">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-xl <?= $serversCount>0 ? 'bg-emerald-600' : 'bg-amber-600' ?> flex items-center justify-center text-white"><i class="fa-solid fa-server"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">2. سرورها (<?= $serversCount ?>)</h3>
                    <p class="text-[11px] text-slate-400"><?= $serversCount>0 ? '✅ '.$serversCount.' سرور فعال' : '⚠️ سروری وجود ندارد' ?></p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed mb-3">اولین سرور را اضافه کن - سیستم خودکار دسته و پلن را تشخیص می‌دهد.</p>
            <a href="<?= Helpers::url('servers') ?>" class="block text-center py-2.5 <?= $serversCount>0 ? 'bg-slate-800 text-slate-300' : 'bg-violet-600 hover:bg-violet-700 text-white' ?> rounded-xl text-xs font-bold transition"><?= $serversCount>0 ? 'مدیریت سرورها' : 'افزودن سرور →' ?></a>
        </div>

        <div class="glass rounded-2xl p-5 border <?= $plansCount>0 ? 'border-emerald-800/30 bg-emerald-950/10' : 'border-amber-800/30 bg-amber-950/10' ?>">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-xl <?= $plansCount>0 ? 'bg-emerald-600' : 'bg-amber-600' ?> flex items-center justify-center text-white"><i class="fa-solid fa-box"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">3. پلن‌ها (<?= $plansCount ?>)</h3>
                    <p class="text-[11px] text-slate-400"><?= $plansCount>0 ? '✅ '.$plansCount.' پلن فعال' : '⚠️ پلنی وجود ندارد' ?></p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed mb-3">پلن‌ها را بررسی کن - اگر خالی است، از سرور ایمپورت کن.</p>
            <a href="<?= Helpers::url('plans') ?>" class="block text-center py-2.5 <?= $plansCount>0 ? 'bg-slate-800 text-slate-300' : 'bg-violet-600 hover:bg-violet-700 text-white' ?> rounded-xl text-xs font-bold transition"><?= $plansCount>0 ? 'مدیریت پلن‌ها' : 'مدیریت پلن‌ها →' ?></a>
        </div>

        <div class="glass rounded-2xl p-5 border <?= $hasBackup ? 'border-emerald-800/30 bg-emerald-950/10' : 'border-slate-800 bg-slate-900/50' ?>">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-xl <?= $hasBackup ? 'bg-emerald-600' : 'bg-slate-700' ?> flex items-center justify-center text-white"><i class="fa-solid fa-shield-halved"></i></span>
                <div>
                    <h3 class="font-bold text-white text-sm">4. بکاپ و امنیت</h3>
                    <p class="text-[11px] text-slate-400"><?= $hasBackup ? '✅ بکاپ وجود دارد' : '💡 پیشنهاد: بکاپ بگیر' ?></p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed mb-3">اولین بکاپ کامل را بگیر تا خیالت راحت باشد.</p>
            <a href="<?= Helpers::url('backups') ?>" class="block text-center py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition border border-slate-700">بکاپ‌ها →</a>
        </div>
    </div>

    <div class="glass rounded-2xl p-5 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="font-bold text-white text-sm">همه چیز آماده است؟</h3>
            <p class="text-[11px] text-slate-400 mt-1">اگر مراحل بالا را انجام دادی، پنل شما 100% آماده فروش است.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="<?= Helpers::url('onboarding/skip') ?>" class="m-0"><?= Helpers::csrfField() ?><button type="submit" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold border border-slate-700 transition">رد کردن</button></form>
            <form method="POST" action="<?= Helpers::url('onboarding/complete') ?>" class="m-0"><?= Helpers::csrfField() ?><button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white rounded-xl text-xs font-black shadow-lg shadow-violet-900/30 transition flex items-center gap-2"><i class="fa-solid fa-check"></i> اتمام راه‌اندازی 🎉</button></form>
        </div>
    </div>

    <div class="text-center text-[11px] text-slate-500">
        <p>💡 نکته: می‌توانی همیشه از منوی تنظیمات به این صفحه برگردی: <code>/onboarding</code></p>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

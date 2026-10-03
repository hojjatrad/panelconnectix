<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center"><i class="fa-solid fa-right-left"></i></span>
                    مهاجرت خودکار دامنه - استقلال کامل
                    <span class="text-[10px] bg-violet-500/20 text-violet-300 px-2 py-1 rounded-full border border-violet-500/30">AUTO MIGRATION v6.9.1</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">وقتی پنل را روی هاست جدید با دامنه جدید نصب می‌کنی، همه چیز خودکار درست می‌شود</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="<?= Helpers::url('settings/domain-migration/check') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold">🔍 بررسی استقلال دامنه</button>
                </form>
            </div>
        </div>
    </div>

    <?php
    $currentDomain = DomainMigrationManager::getCurrentDomain();
    $storedDomain = Setting::get('panel_domain','');
    $customDomain = Setting::get('sublink_custom_domain','');
    $oldDomains = Setting::get('old_domains','');
    ?>

    <!-- Current Status -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-emerald-800/30 rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">دامنه فعلی (تشخیص خودکار)</div>
            <div class="text-lg font-mono font-bold text-emerald-400"><?= htmlspecialchars($currentDomain) ?></div>
            <div class="text-[11px] text-slate-500 mt-1">از $_SERVER['HTTP_HOST']</div>
        </div>
        <div class="bg-slate-900 border <?= $storedDomain === $currentDomain ? 'border-emerald-800/30' : 'border-amber-800/30' ?> rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">دامنه ذخیره شده در دیتابیس</div>
            <div class="text-lg font-mono font-bold <?= $storedDomain === $currentDomain ? 'text-emerald-400' : 'text-amber-400' ?>"><?= htmlspecialchars($storedDomain ?: 'خالی (نصب جدید)') ?></div>
            <div class="text-[11px] mt-1 <?= $storedDomain === $currentDomain ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $storedDomain === $currentDomain ? '✅ مطابق' : '⚠️ متفاوت - نیاز به مهاجرت' ?></div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">دامنه ساب‌لینک</div>
            <div class="text-sm font-mono font-bold text-white truncate"><?= htmlspecialchars($customDomain ?: 'خالی') ?></div>
            <div class="text-[11px] text-slate-500 mt-1">برای ساخت sub/xxx</div>
        </div>
    </div>

    <!-- Auto Migration Result -->
    <?php if (!empty($migrationResult)): ?>
    <div class="bg-slate-900 border <?= $migrationResult['migrated'] ? 'border-emerald-800/30' : 'border-slate-800' ?> rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
            <i class="fa-solid fa-wand-magic-sparkles text-violet-400"></i>
            نتیجه بررسی خودکار مهاجرت
        </h3>
        <div class="space-y-2">
            <?php foreach ($migrationResult['actions'] as $act): ?>
                <div class="p-2 bg-slate-800 rounded-lg text-xs text-slate-300">• <?= htmlspecialchars($act) ?></div>
            <?php endforeach; ?>
            <?php if (empty($migrationResult['actions'])): ?>
                <div class="p-3 bg-emerald-950/20 border border-emerald-800/30 rounded-xl text-xs text-emerald-300">✅ همه چیز درست است - دامنه تغییر نکرده و نیازی به مهاجرت نیست</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Independence Check -->
    <?php if (!empty($checks)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-white font-bold text-sm">🔍 بررسی استقلال از دامنه (Domain Independence Check)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right">بخش</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-right">جزئیات</th>
                        <th class="p-3 text-right">اقدام</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $key => $chk): ?>
                    <tr class="border-t border-slate-800/50">
                        <td class="p-3 text-white font-bold"><?= htmlspecialchars($chk['label']) ?></td>
                        <td class="p-3 text-center"><?= $chk['ok'] ? '<span class="px-2 py-1 bg-emerald-900/30 text-emerald-300 rounded-full text-[10px] border border-emerald-800/30">✅ درست</span>' : '<span class="px-2 py-1 bg-amber-900/30 text-amber-300 rounded-full text-[10px] border border-amber-800/30">⚠️ نیاز به فیکس</span>' ?></td>
                        <td class="p-3 text-slate-400 text-[11px]">
                            <?php if ($key === 'panel_domain'): ?>
                                فعلی: <?= htmlspecialchars($chk['current']) ?> / ذخیره: <?= htmlspecialchars($chk['stored']) ?>
                            <?php elseif ($key === 'sublink_custom_domain'): ?>
                                <?= htmlspecialchars($chk['current'] ?? 'خالی') ?>
                            <?php elseif ($key === 'sublink_domains'): ?>
                                <?= $chk['count'] ?? 0 ?> دامنه، دارد فعلی: <?= ($chk['has_current'] ?? false) ? 'بله' : 'خیر' ?>
                            <?php elseif ($key === 'server_subdomains'): ?>
                                <?= $chk['total'] ?? 0 ?> سرور، <?= $chk['old'] ?? 0 ?> قدیمی
                            <?php else: ?>
                                BYPASS: <?= ($chk['bypass'] ?? false) ? '✅' : '❌' ?> / Direct Rule: <?= ($chk['direct_rule'] ?? false) ? '✅' : '❌' ?>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-slate-300 text-[11px]"><?= htmlspecialchars($chk['fix'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Manual Migration -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-4 text-sm flex items-center gap-2"><i class="fa-solid fa-right-left text-violet-400"></i> مهاجرت دستی به دامنه جدید</h3>
        <p class="text-xs text-slate-400 mb-4">اگر می‌خواهی دامنه را به صورت دستی تغییر دهی (مثلاً از vpbotn.ir به mynewdomain.com):</p>
        <form method="POST" action="<?= Helpers::url('settings/domain-migration/migrate') ?>" class="flex gap-3">
            <?= Helpers::csrfField() ?>
            <input type="text" name="new_domain" placeholder="mynewdomain.com" required class="flex-1 bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm font-mono focus:border-violet-500 outline-none">
            <button type="submit" onclick="return confirm('آیا از مهاجرت به دامنه جدید اطمینان داری؟ این عملیات panel_domain و sublink_custom_domain و sublink_domains و server_nodes را بروز می‌کند.')" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-bold text-xs">🚀 مهاجرت خودکار</button>
        </form>
    </div>

    <!-- How it works -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-rocket text-violet-400"></i> نصب جدید روی هاست جدید</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• وقتی پنل را روی هاست جدید با دامنه جدید نصب می‌کنی</li>
                <li>• در اولین بازدید، <code>panel_domain</code> خودکار ذخیره می‌شود</li>
                <li>• دامنه‌های <code>direct.newdomain.com</code> و <code>newdomain.com</code> خودکار ساخته می‌شوند</li>
                <li>• <code>sublink_custom_domain</code> خودکار به دامنه جدید تنظیم می‌شود</li>
                <li>• همه چیز بدون دخالت دستی کار می‌کند ✅</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-repeat text-cyan-400"></i> تغییر دامنه (Migration)</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• اگر دامنه از <code>old.com</code> به <code>new.com</code> تغییر کند</li>
                <li>• سیستم خودکار تشخیص می‌دهد (هر 1 ساعت)</li>
                <li>• <code>old.com</code> به لیست <code>old_domains</code> اضافه می‌شود</li>
                <li>• <code>sublink_domains</code> با <code>new.com</code> و <code>direct.new.com</code> بروز می‌شود</li>
                <li>• <code>server_nodes.sub_domain</code> اگر قدیمی بود، بروز می‌شود</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-400"></i> استقلال کامل (Domain Independent)</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• هیچ دامنه‌ای هاردکد نیست، همه از <code>HTTP_HOST</code> یا تنظیمات خوانده می‌شود</li>
                <li>• ساب‌لینک‌ها با بهترین دامنه چرخشی ساخته می‌شوند</li>
                <li>• اگر دامنه قدیمی در ساب‌لینک بود، خودکار با دامنه جدید جایگزین می‌شود</li>
                <li>• بکاپ‌ها شامل دامنه نیستند، قابل بازگردانی روی هر دامنه</li>
                <li>• <code>.htaccess</code> با قانون <code>direct.*</code> حتی بدون ساخت ساب‌دامنه کار می‌کند</li>
            </ul>
        </div>
    </div>

    <div class="bg-violet-950/20 border border-violet-800/30 rounded-2xl p-4">
        <h4 class="font-bold text-violet-300 mb-2 text-xs">💡 پیشنهاد حرفه‌ای من برای مهاجرت بدون دردسر:</h4>
        <ol class="list-decimal list-inside space-y-1.5 text-[11px] text-slate-300 leading-relaxed">
            <li>پنل را روی هاست جدید نصب کن (فایل‌ها + دیتابیس)</li>
            <li>اولین بار با دامنه جدید وارد شو - همه چیز خودکار ساخته می‌شود (نیازی به تنظیم دستی نیست)</li>
            <li>برو <code>/settings/api-tokens</code> و توکن‌های Cloudflare و cPanel جدید را وارد کن</li>
            <li>دکمه <code>ساخت خودکار direct.newdomain.com</code> را بزن - DNS و ساب‌دامنه خودکار ساخته می‌شود</li>
            <li>برو <code>/backups</code> و یک بکاپ کامل بگیر - این بکاپ روی هر دامنه قابل بازگردانی است</li>
            <li>تمام! ساب‌لینک‌ها با دامنه جدید کار می‌کنند، نیازی به تغییر دستی نیست</li>
        </ol>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

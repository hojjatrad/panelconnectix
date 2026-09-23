<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-palette text-fuchsia-400"></i>
            <span>شخصی‌سازی برند و هویت بصری (White-Label Metadata)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">تغییر نام برند، رنگ سازمانی، آپلود لوگو، تنظیم پیام خوش‌آمدگویی و راه‌های ارتباطی</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Settings Form -->
        <div class="md:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
            <form action="<?= Helpers::url('settings/metadata') ?>" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">نام برند / اپلیکیشن شما *</label>
                    <input type="text" name="brand_name" value="<?= htmlspecialchars($meta['brand_name'] ?? 'Connectix VPN') ?>" required 
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>

                <!-- Theme Color Selector (Connectix 5 Color Palettes) -->
                <div>
                    <label class="block text-slate-300 mb-2 font-semibold">تم و پالت رنگی پنل و ساب‌لینک:</label>
                    <div class="grid grid-cols-5 gap-3">
                        <label class="flex flex-col items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="theme_color" value="violet" <?= ($meta['theme_color'] ?? 'violet') === 'violet' ? 'checked' : '' ?> class="peer sr-only">
                            <span class="w-8 h-8 rounded-full bg-purple-600 ring-2 ring-transparent peer-checked:ring-white peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900 transition-all shadow-md"></span>
                            <span class="text-[10px] text-slate-400">بنفش (اصلی)</span>
                        </label>

                        <label class="flex flex-col items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="theme_color" value="blue" <?= ($meta['theme_color'] ?? '') === 'blue' ? 'checked' : '' ?> class="peer sr-only">
                            <span class="w-8 h-8 rounded-full bg-blue-600 ring-2 ring-transparent peer-checked:ring-white peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900 transition-all shadow-md"></span>
                            <span class="text-[10px] text-slate-400">آبی</span>
                        </label>

                        <label class="flex flex-col items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="theme_color" value="green" <?= ($meta['theme_color'] ?? '') === 'green' ? 'checked' : '' ?> class="peer sr-only">
                            <span class="w-8 h-8 rounded-full bg-emerald-600 ring-2 ring-transparent peer-checked:ring-white peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900 transition-all shadow-md"></span>
                            <span class="text-[10px] text-slate-400">سبز نئونی</span>
                        </label>

                        <label class="flex flex-col items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="theme_color" value="orange" <?= ($meta['theme_color'] ?? '') === 'orange' ? 'checked' : '' ?> class="peer sr-only">
                            <span class="w-8 h-8 rounded-full bg-amber-600 ring-2 ring-transparent peer-checked:ring-white peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900 transition-all shadow-md"></span>
                            <span class="text-[10px] text-slate-400">نارنجی</span>
                        </label>

                        <label class="flex flex-col items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="theme_color" value="black" <?= ($meta['theme_color'] ?? '') === 'black' ? 'checked' : '' ?> class="peer sr-only">
                            <span class="w-8 h-8 rounded-full bg-zinc-800 ring-2 ring-transparent peer-checked:ring-white peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900 transition-all shadow-md"></span>
                            <span class="text-[10px] text-slate-400">مشکی مات</span>
                        </label>
                    </div>
                </div>

                <!-- Logo URL and Upload -->
                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">لوگوی برند (ابعاد استاندارد ۳۰۰×۱۵۰ پیکسل):</label>
                    <input type="url" name="logo_url" value="<?= htmlspecialchars($meta['logo_url'] ?? '') ?>" placeholder="https://example.com/logo.png" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono mb-2">
                    <div class="text-[11px] text-slate-400 mb-1">یا آپلود مستقیم فایل لوگو:</div>
                    <input type="file" name="logo_file" accept="image/*" class="w-full text-slate-400 file:ml-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-purple-300 hover:file:bg-slate-700">
                </div>

                <!-- Contacts -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-300 mb-1.5 font-semibold">آیدی تلگرام پشتیبانی</label>
                        <input type="text" name="telegram_support" value="<?= htmlspecialchars($meta['telegram_support'] ?? '') ?>" dir="ltr" placeholder="@NovinVPN_Support"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-300 mb-1.5 font-semibold">شماره یا واتساپ پشتیبانی</label>
                        <input type="text" name="whatsapp_support" value="<?= htmlspecialchars($meta['whatsapp_support'] ?? '') ?>" dir="ltr" placeholder="+989120000000"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">پیام خوش‌آمدگویی در صفحه اشتراک مشتری</label>
                    <textarea name="welcome_message" rows="3" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><?= htmlspecialchars($meta['welcome_message'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">لینک مستقیم صفحه تمدید / سایت فروشگاه</label>
                    <input type="url" name="renewal_url" value="<?= htmlspecialchars($meta['renewal_url'] ?? '') ?>" dir="ltr" placeholder="https://myshop.com"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>

                <?php if (Auth::isAdmin()): ?>
                <div class="p-3.5 bg-slate-800/60 rounded-xl border border-indigo-500/30 space-y-2">
                    <label class="block text-indigo-300 font-bold text-xs flex items-center gap-2">
                        <i class="fa-solid fa-arrows-rotate text-indigo-400"></i>
                        <span>دامنه اختصاصی ساب‌لینک ضد فیلتر (Sublink Domain Switcher)</span>
                    </label>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        در صورت فیلتر شدن دامنه اصلی پنل، کافیست دامنه جدید خود (مثلاً <code class="text-cyan-400 font-mono">sub.newdomain.com</code>) را در اینجا وارد نمایید تا کلیه لینک‌های اتصال و ساب‌لینک کاربران به دامنه جدید هدایت شوند.
                    </p>
                    <input type="text" name="sublink_custom_domain" value="<?= htmlspecialchars(Setting::get('sublink_custom_domain', '')) ?>" dir="ltr" placeholder="sub.newdomain.com"
                           class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs">
                </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/30 mt-2">
                    ذخیره تنظیمات هویت بصری
                </button>
            </form>
        </div>

        <!-- Live Preview Card & Backup -->
        <div class="space-y-4">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col items-center justify-center text-center">
                <span class="text-xs text-slate-400 mb-4 font-semibold">پیش‌نمایش کارت مشتری شما</span>
                <div class="w-full bg-slate-950 border border-slate-800 rounded-2xl p-5 shadow-inner">
                    <?php if (!empty($meta['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($meta['logo_url']) ?>" alt="Logo Preview" class="h-10 mx-auto mb-3 object-contain rounded">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-xl bg-purple-600 mx-auto flex items-center justify-center text-white mb-3 shadow-lg">
                            <i class="fa-solid fa-shield-halved text-xl"></i>
                        </div>
                    <?php endif; ?>

                    <h4 class="font-bold text-white text-sm"><?= htmlspecialchars($meta['brand_name'] ?? 'Connectix VPN') ?></h4>
                    <p class="text-[11px] text-slate-400 mt-2 line-clamp-3"><?= htmlspecialchars($meta['welcome_message'] ?? 'سرویس امن و بدون محدودیت') ?></p>

                    <div class="mt-4 pt-3 border-t border-slate-800 flex justify-center gap-4 text-slate-400 text-sm">
                        <span title="تلگرام"><i class="fa-brands fa-telegram text-sky-400"></i></span>
                        <span title="واتساپ"><i class="fa-brands fa-whatsapp text-emerald-400"></i></span>
                        <span title="پشتیبانی آنلاین"><i class="fa-solid fa-headset text-purple-400"></i></span>
                    </div>
                </div>
            </div>

            <?php if (Auth::isAdmin()): ?>
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">پشتیبان‌گیری از کل اطلاعات</h4>
                        <p class="text-[11px] text-slate-400">دانلود فایل SQL از تمامی کلاینت‌ها، سفارشات و تنظیمات</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                    <a href="<?= Helpers::url('settings/backup') ?>" class="block w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-purple-300 font-bold rounded-xl text-xs text-center border border-slate-700 transition">
                        <i class="fa-solid fa-download ml-1.5"></i>
                        دانلود فایل SQL
                    </a>
                    <a href="<?= Helpers::url('settings/backup-telegram') ?>" onclick="return confirm('آیا مایلید نسخه پشتیبان دیتابیس مستقیماً به چت تلگرام ادمین ارسال شود؟');" class="block w-full py-2.5 bg-cyan-950/60 hover:bg-cyan-900/60 text-cyan-300 font-bold rounded-xl text-xs text-center border border-cyan-800/60 transition">
                        <i class="fa-brands fa-telegram ml-1.5"></i>
                        ارسال به تلگرام ادمین
                    </a>
                </div>
            </div>

            <!-- Database Restore Card -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">بازگردانی پایگاه داده (Restore Database)</h4>
                        <p class="text-[11px] text-slate-400">آپلود و بازیابی فایل پشتیبان SQL با بررسی و امنیت کامل</p>
                    </div>
                </div>

                <form action="<?= Helpers::url('settings/restore') ?>" method="POST" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('⚠️ هشدار مهم:\nبا بازگردانی فایل، اطلاعات و ساختار جداول با نسخه بکاپ هماهنگ خواهند شد.\nآیا از ادامه عملیات بازگردانی اطمینان دارید؟');">
                    <?= Helpers::csrfField() ?>
                    <div>
                        <input type="file" name="backup_file" required accept=".sql" class="w-full text-xs text-slate-400 file:mr-0 file:ml-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-amber-300 hover:file:bg-slate-700 bg-slate-950/80 p-1.5 rounded-xl border border-slate-800">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs text-center transition flex items-center justify-center gap-2 shadow-lg shadow-amber-900/30">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span>بارگذاری و بازگردانی فایل پشتیبان</span>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

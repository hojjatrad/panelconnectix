<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-palette text-fuchsia-400"></i>
                <span>شخصی‌سازی برندینگ و وایت‌لیبل (White-Label) من</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">نام برند، لوگو و راه‌های ارتباطی خود را تنظیم فرمایید تا اپلیکیشن، صفحه وب ساب‌لینک مشتریان و ربات تلگرام کاملاً با هویت اختصاصی شما نمایش داده شود.</p>
        </div>
    </div>

    <!-- Unified Auto-Update Notice (panel + app, same for main & reseller panels) -->
    <?php require_once __DIR__ . '/../../core/Updater.php'; $panelVersion = Updater::CURRENT_VERSION; ?>
    <div class="bg-emerald-950/40 border border-emerald-900/60 rounded-2xl p-4 flex flex-col md:flex-row md:items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-rotate text-lg"></i>
        </div>
        <div class="text-xs space-y-1">
            <div class="font-bold text-emerald-300 flex items-center gap-2">
                <span>پنل و اپلیکیشن همیشه به‌صورت خودکار به‌روز می‌مانند</span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 font-mono text-[10px] text-emerald-400" dir="ltr">Panel v<?= htmlspecialchars($panelVersion) ?></span>
            </div>
            <p class="text-slate-400 leading-relaxed">
                هر بار که نسخه جدیدی از پنل یا اپلیکیشن منتشر شود، پنل شما (و نسخه‌های پنل نماینده‌های دیگر) بدون دخالت شما به‌روز می‌شود و
                کاربرانی که با برند شما اشتراک دارند، بلافاصله در اپ خود هشدار آپدیت درون‌برنامه‌ای می‌بینند و بدون حذف و نصب مجدد، روی نسخه فعلی ارتقا می‌یابند.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Branding Form -->
        <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
            <h3 class="text-sm font-bold text-white border-b border-slate-800 pb-3 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-cyan-400"></i>
                <span>تنظیمات بصری و هویت تجاری</span>
            </h3>

            <form action="<?= Helpers::url('reseller/branding') ?>" method="POST" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">نام تجاری برند شما:</label>
                        <input type="text" name="brand_name" value="<?= htmlspecialchars($branding['brand_name'] ?? '') ?>" 
                               placeholder="مثال: رویال وی‌پی‌ان" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-fuchsia-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">آدرس اینترنتی تصویر لوگو (Logo URL):</label>
                        <input type="url" name="logo_url" value="<?= htmlspecialchars($branding['logo_url'] ?? '') ?>" 
                               placeholder="https://example.com/logo.png" dir="ltr"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-fuchsia-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">تم رنگی دلخواه صفحه ساب‌لینک:</label>
                        <select name="theme_color" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-fuchsia-500 focus:outline-none">
                            <option value="violet" <?= ($branding['theme_color'] ?? '') === 'violet' ? 'selected' : '' ?>>بنفش رویال (پیش‌فرض)</option>
                            <option value="cyan" <?= ($branding['theme_color'] ?? '') === 'cyan' ? 'selected' : '' ?>>آبی فیروزه‌ای نئونی</option>
                            <option value="emerald" <?= ($branding['theme_color'] ?? '') === 'emerald' ? 'selected' : '' ?>>سبز زمردی</option>
                            <option value="amber" <?= ($branding['theme_color'] ?? '') === 'amber' ? 'selected' : '' ?>>طلایی کهربایی</option>
                            <option value="rose" <?= ($branding['theme_color'] ?? '') === 'rose' ? 'selected' : '' ?>>سرخ یاقوتی</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-semibold mb-1.5">آیدی تلگرام پشتیبانی (بدون @):</label>
                        <input type="text" name="support_username" value="<?= htmlspecialchars($branding['support_username'] ?? '') ?>" 
                               placeholder="MySupport_ID" dir="ltr"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-fuchsia-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1.5">پیام خوش‌آمدگویی یا توضیحات اختصاصی:</label>
                    <textarea name="welcome_message" rows="3" placeholder="سرویس اینترنت آزاد و پرسرعت با پشتیبانی ۲۴ ساعته..."
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-fuchsia-500 focus:outline-none"><?= htmlspecialchars($branding['welcome_message'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1.5">دامنه یا زیردامنه اختصاصی شما (اختیاری):</label>
                    <input type="text" name="custom_domain" value="<?= htmlspecialchars($branding['custom_domain'] ?? '') ?>" 
                           placeholder="vpn.mybrand.com" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white font-mono text-xs focus:border-fuchsia-500 focus:outline-none">
                    <span class="text-[10px] text-slate-500 mt-1 block">می‌توانید رکورد CNAME زیردامنه خود را به هاست متصل کنید تا مشتریان با آدرس سایت شما وارد شوند.</span>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-fuchsia-600 to-purple-600 hover:from-fuchsia-700 hover:to-purple-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-fuchsia-900/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>ذخیره تغییرات وایت‌لیبل</span>
                </button>
            </form>
        </div>

        <!-- Live Preview -->
        <div class="space-y-4">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 text-center space-y-4 shadow-xl relative overflow-hidden">
                <span class="text-[10px] font-bold text-fuchsia-400 bg-fuchsia-950/60 px-2.5 py-1 rounded-full border border-fuchsia-800/40 inline-block">پیش‌نمایش زنده در صفحه مشتری</span>
                
                <div class="w-16 h-16 rounded-2xl bg-fuchsia-600 mx-auto flex items-center justify-center text-white shadow-lg overflow-hidden">
                    <?php if (!empty($branding['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-shield-halved text-3xl"></i>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 class="font-extrabold text-white text-base"><?= htmlspecialchars($branding['brand_name'] ?: 'برند شما') ?></h4>
                    <p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars($branding['welcome_message'] ?: 'سرویس امن و بدون محدودیت') ?></p>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-center gap-2 text-xs text-cyan-400">
                    <i class="fa-brands fa-telegram"></i>
                    <span class="font-mono">@<?= htmlspecialchars($branding['support_username'] ?: 'Support') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

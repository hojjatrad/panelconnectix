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

                <div class="p-3.5 bg-slate-800/60 rounded-xl border border-emerald-500/30 space-y-2">
                    <label class="block text-emerald-300 font-bold text-xs flex items-center gap-2">
                        <i class="fa-solid fa-mobile-screen-button text-emerald-400"></i>
                        <span>انتشار به‌روزرسانی اپلیکیشن اندروید (In-App Update)</span>
                    </label>
<?php
require_once __DIR__ . '/../../core/AppReleasePublisher.php';
$appPub = AppReleasePublisher::status();
$appAutoMode = (($appPub['source'] ?? 'auto') !== 'admin');
?>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        بیلد هر نسخه‌ی جدید اپلیکیشن به‌صورت خودکار توسط گیت‌هاب ساخته و در ریلیس منتشر می‌شود و این پنل
                        <b>خودکار نسخه‌ی جدید را به کاربران اعلام می‌کند</b> (دیالوگ آپدیت با نوار پیشرفت + نصب مستقیم روی نسخه‌ی فعلی، بدون نیاز به حذف و نصب مجدد).
                        برای کنترل کامل، حالت «دستی» را فعال کنید.
                    </p>

                    <div class="flex flex-wrap items-center gap-4 bg-slate-950/60 border border-slate-800 rounded-xl p-3">
                        <span class="text-[11px] font-bold text-slate-300">حالت انتشار:</span>
                        <label class="flex items-center gap-1.5 text-[11px] text-slate-300 cursor-pointer">
                            <input type="radio" name="app_publish_mode" value="auto" <?= $appAutoMode ? 'checked' : '' ?> onchange="toggleAppPublishMode('auto')" class="accent-emerald-500">
                            خودکار از گیت‌هاب (پیشنهادی)
                        </label>
                        <label class="flex items-center gap-1.5 text-[11px] text-slate-300 cursor-pointer">
                            <input type="radio" name="app_publish_mode" value="manual" <?= !$appAutoMode ? 'checked' : '' ?> onchange="toggleAppPublishMode('manual')" class="accent-emerald-500">
                            دستی (کنترل کامل مدیر)
                        </label>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-2.5">
                            <div class="text-slate-500 mb-0.5">نسخه در گیت‌هاب</div>
                            <div class="font-mono font-bold text-emerald-300" dir="ltr"><?= htmlspecialchars((string)($appPub['remote_version'] ?? 'در انتظار بررسی...')) ?></div>
                        </div>
                        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-2.5">
                            <div class="text-slate-500 mb-0.5">نسخه منتشرشده</div>
                            <div class="font-mono font-bold text-white" dir="ltr"><?= htmlspecialchars((string)($appPub['published_version'] !== '' ? $appPub['published_version'] : '—')) ?></div>
                        </div>
                        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-2.5">
                            <div class="text-slate-500 mb-0.5">وضعیت</div>
                            <div class="font-bold <?= $appPub['enabled'] ? 'text-emerald-300' : 'text-rose-300' ?>"><?= $appPub['enabled'] ? ($appAutoMode ? 'خودکار' : 'دستی') : 'غیرفعال' ?></div>
                        </div>
                        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-2.5">
                            <div class="text-slate-500 mb-0.5">آخرین بررسی</div>
                            <div class="text-slate-300" dir="ltr"><?= htmlspecialchars((string)$appPub['checked_at']) ?></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">نسخه‌ی جدید <span class="text-slate-600">(حالت خودکار: فقط‌نمایش)</span></label>
                            <input type="text" id="appVerInput" name="app_latest_version" value="<?= htmlspecialchars(Setting::get('app_latest_version', '')) ?>" dir="ltr" placeholder="3.2.0" <?= $appAutoMode ? 'readonly' : '' ?>
                                   class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs <?= $appAutoMode ? 'opacity-60' : '' ?>">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">عنوان دیالوگ آپدیت <span class="text-slate-600">(حالت خودکار: فقط‌نمایش)</span></label>
                            <input type="text" id="appTitleInput" name="app_update_title" value="<?= htmlspecialchars(Setting::get('app_update_title', '')) ?>" dir="rtl" placeholder="Connectix v3.2.0" <?= $appAutoMode ? 'readonly' : '' ?>
                                   class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white text-xs <?= $appAutoMode ? 'opacity-60' : '' ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">لینک APK (ARM64 — اختیاری)</label>
                            <input type="text" name="app_download_url" value="<?= htmlspecialchars(Setting::get('app_download_url', '')) ?>" dir="ltr" placeholder="خالی = فایل روی هاست پنل / گیت‌هاب"
                                   class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-[11px]">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">لینک APK (Universal — اختیاری)</label>
                            <input type="text" name="app_universal_url" value="<?= htmlspecialchars(Setting::get('app_universal_url', '')) ?>" dir="ltr" placeholder="خالی = فایل روی هاست پنل / گیت‌هاب"
                                   class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-[11px]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">تغییرات این نسخه (Changelog) <span class="text-slate-600">(حالت خودکار: فقط‌نمایش)</span></label>
                        <textarea id="appChangelogInput" name="app_update_changelog" rows="3" dir="rtl" placeholder="• قابلیت جدید اول&#10;• رفع باگ دوم" <?= $appAutoMode ? 'readonly' : '' ?>
                                  class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white text-xs <?= $appAutoMode ? 'opacity-60' : '' ?>"><?= htmlspecialchars(Setting::get('app_update_changelog', '')) ?></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">آپلود APK جدید (ARM64)</label>
                            <input type="file" name="apk_file" accept=".apk"
                                   class="w-full text-[11px] text-slate-400 file:ml-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-slate-700 file:text-emerald-300 hover:file:bg-slate-600 bg-slate-900 p-1.5 rounded-xl border border-slate-700">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">آپلود APK جدید (Universal)</label>
                            <input type="file" name="apk_universal_file" accept=".apk"
                                   class="w-full text-[11px] text-slate-400 file:ml-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-slate-700 file:text-emerald-300 hover:file:bg-slate-600 bg-slate-900 p-1.5 rounded-xl border border-slate-700">
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 leading-relaxed">
                        <?= is_file(__DIR__ . '/../../Connectix-ARM64-v8a.apk')
                            ? '✅ APK فعلی روی هاست: ' . round(filesize(__DIR__ . '/../../Connectix-ARM64-v8a.apk') / 1048576, 1) . ' MB — ' . date('Y/m-d H:i', filemtime(__DIR__ . '/../../Connectix-ARM64-v8a.apk'))
                            : '⚠️ فعلاً APK ای روی هاست پنل نیست؛ به‌زودی به‌صورت خودکار (هر ۵ دقیقه) روی هاست دانلود می‌شود، یا دکمه «همگام‌سازی فوری APKها» را پایین همین صفحه بزنید تا همین حالا نصب شود.'
                        ?>
                    </div>

                    <div class="border-t border-slate-800 pt-3">
                        <div class="text-[11px] font-bold text-slate-300 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-gear text-emerald-400"></i>
                            <span>مدیریت اپلیکیشن (مشترک بین همه کاربرها — اگر نماینده‌ای خودش تنظیم نکند، این مقادیر نمایش داده می‌شوند)</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">آیدی تلگرام پشتیبانی (Default Support ID)</label>
                                <input type="text" name="app_support_id" value="<?= htmlspecialchars(Setting::get('app_support_id', '')) ?>" dir="ltr" placeholder="Support_ID"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs">
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">لینک پشتیبانی (اختیاری)</label>
                                <input type="text" name="app_support_link" value="<?= htmlspecialchars(Setting::get('app_support_link', '')) ?>" dir="ltr" placeholder="https://t.me/Support_ID"
                                       class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-[11px]">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-[11px] text-slate-400 mb-1">اطلاعیه‌ی نمایش‌داده‌شده در اپلیکیشن (اختیاری)</label>
                            <textarea name="app_announcement" rows="2" dir="rtl" placeholder="مثلاً: سرورهای جدید اضافه شد؛ برای پشتیبانی با ما در ارتباط باشید."
                                      class="w-full bg-slate-900 border border-slate-700 rounded-xl p-2.5 text-white text-xs"><?= htmlspecialchars(Setting::get('app_announcement', '')) ?></textarea>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer select-none">
                        <input type="checkbox" name="app_update_enabled" value="1" <?= Setting::get('app_update_enabled', '1') !== '0' ? 'checked' : '' ?> class="w-4 h-4 accent-emerald-500">
                        <span>فعال‌سازی نمایش هشدار آپدیت در اپلیکیشن</span>
                    </label>

                    <script>
                    function toggleAppPublishMode(mode) {
                        var auto = (mode === 'auto');
                        ['appVerInput', 'appTitleInput', 'appChangelogInput'].forEach(function (id) {
                            var el = document.getElementById(id);
                            if (!el) return;
                            el.readOnly = auto;
                            el.classList.toggle('opacity-60', auto);
                        });
                    }
                    </script>
                </div>
                <?php endif; ?>

                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/30 mt-2">
                    ذخیره تنظیمات هویت بصری
                </button>
            </form>
        </div>

        <?php if (Auth::isAdmin()): ?>
        <!-- APK Mirror Card (in-app update downloads from this host) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
            <?php require_once __DIR__ . '/../../core/AppApkMirror.php'; $apkMirrorSt = AppApkMirror::status(); ?>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-download"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-white">آینه‌سازی APK روی هاست پنل (دانلود داخل برنامه)</h4>
                    <p class="text-[11px] text-slate-400">نصب‌کننده‌ی داخل برنامه، APK را مستقیماً از همین هاست دانلود می‌کند (سریع و در داخل ایران). هر ۵ دقیقه به‌صورت خودکار با ریلیس گیت‌هاب همگام می‌شود.</p>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                <?php foreach ($apkMirrorSt['files'] as $fname => $finfo): ?>
                    <div class="bg-slate-950/60 border <?= $finfo['present'] ? 'border-emerald-800/40' : 'border-rose-800/40' ?> rounded-xl p-2.5">
                        <div class="text-slate-500 mb-0.5 truncate" title="<?= htmlspecialchars($fname) ?>"><?= htmlspecialchars(basename($fname)) ?></div>
                        <div class="font-bold <?= $finfo['present'] ? 'text-emerald-300' : 'text-rose-300' ?>">
                            <?= $finfo['present'] ? round($finfo['size'] / 1048576, 1) . ' MB' : 'روی هاست نیست' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php $mirrorLast = $apkMirrorSt['last_run'] ?? []; if (!empty($mirrorLast['at'])): ?>
                <div class="text-[10px] text-slate-500">آخرین همگام‌سازی: <span dir="ltr"><?= htmlspecialchars((string)$mirrorLast['at']) ?></span></div>
            <?php endif; ?>
            <form method="post" action="<?= Helpers::url('app/apk-mirror') ?>" class="m-0"
                  onsubmit="return confirm('فایل‌های APK با ریلیس فعلی گیت‌هاب مقایسه و در صورت نیاز دوباره روی هاست دانلود می‌شوند (چند صد مگابایت). ادامه می‌دهید؟');">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="w-full py-2.5 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 font-bold rounded-xl text-xs border border-emerald-500/30 transition">
                    <i class="fa-solid fa-arrows-rotate ml-1.5"></i> همگام‌سازی فوری APKها با گیت‌هاب
                </button>
            </form>
        </div>
        <?php endif; ?>

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

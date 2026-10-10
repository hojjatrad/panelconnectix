<?php
$pageTitle = 'تنظیمات سراسری اپ - مدیریت مرکزی از پنل وب';
require __DIR__ . '/../layout/header.php';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

$defaultPanelUrl = $configs['default_panel_url']['config_value'] ?? 'https://vpbotn.ir';
$hideManualPanel = ($configs['hide_manual_panel_url']['config_value'] ?? '1') === '1';
$hideManualApiKey = ($configs['hide_manual_api_key']['config_value'] ?? '1') === '1';
$forceManaged = ($configs['force_managed_mode']['config_value'] ?? '0') === '1';
$autoFetchServers = ($configs['auto_fetch_servers']['config_value'] ?? '1') === '1';
$defaultApiKey = $configs['default_api_key']['config_value'] ?? '';
$appSettingsJson = $configs['app_settings_json']['config_value'] ?? '{"split_tunneling": true, "auto_reconnect": true}';
?>
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center"><i class="fa-solid fa-mobile-screen-button"></i></span>
                    تنظیمات سراسری اپ - مدیریت مرکزی از پنل وب
                </h1>
                <p class="text-sm text-slate-400 mt-2">از اینجا می‌توانید تمام تنظیمات اپ (حتی اپ مدیر ارشد) را از پنل وب کنترل کنید. اپ به صورت خودکار این تنظیمات را می‌خواند - نیازی به تنظیم دستی مسیر نیست.</p>
                <p class="text-xs text-amber-300 mt-2 bg-amber-500/10 border border-amber-500/20 rounded-lg p-2">💡 پیشنهاد شما: "کلا تنظیمات برای آپ حتی آپ مدیر ارشد از پنل وب تنظیم بشه" - این صفحه دقیقاً همین کار را می‌کند!</p>
            </div>
            <a href="<?= Helpers::url('dashboard') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm">بازگشت به داشبورد</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-2xl font-black text-violet-400"><?= $resellersCount ?></div>
            <div class="text-xs text-slate-400 mt-1">نماینده فعال</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-lg font-bold text-emerald-400"><?= htmlspecialchars($defaultPanelUrl) ?></div>
            <div class="text-xs text-slate-400 mt-1">آدرس پیش‌فرض پنل</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-sm font-bold <?= $hideManualPanel ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $hideManualPanel ? '✅ مخفی' : '⚠️ نمایش' ?></div>
            <div class="text-xs text-slate-400 mt-1">وضعیت فیلد آدرس دستی</div>
        </div>
    </div>

    <form method="POST" action="<?= Helpers::url('settings/app-config/save') ?>" class="space-y-6">
        <?= Helpers::csrfField() ?>

        <!-- Panel URL & API Key -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-globe text-cyan-400"></i> تنظیمات اتصال - از پنل وب به اپ (خودکار)</h3>
            
            <div>
                <label class="text-xs text-slate-300 block mb-2 font-bold">آدرس پیش‌فرض پنل (Default Panel URL) - اپ خودکار این را می‌خواند</label>
                <input type="text" name="default_panel_url" value="<?= htmlspecialchars($defaultPanelUrl) ?>" placeholder="https://vpbotn.ir" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm font-mono">
                <p class="text-[11px] text-slate-500 mt-2">💡 این آدرس به صورت خودکار به تمام اپ‌ها ارسال می‌شود. اپ نیازی نیست دستی آدرس وارد کند - از همین استفاده می‌کند. اگر سرور جدید اضافه کردید، اپ خودکار آن را از همین پنل می‌گیرد.</p>
            </div>

            <div>
                <label class="text-xs text-slate-300 block mb-2 font-bold">کلید API پیش‌فرض برای تمام اپ‌ها (Default API Key) - اختیاری</label>
                <input type="text" name="default_api_key" value="<?= htmlspecialchars($defaultApiKey) ?>" placeholder="مثلا: cx_api_1234567890abcdef یا خالی" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm font-mono">
                <p class="text-[11px] text-slate-500 mt-2">💡 اگر کلید API اینجا تنظیم کنید، تمام اپ‌ها به صورت خودکار با این کلید به پنل وصل می‌شوند - نیازی به وارد کردن دستی نیست. برای نماینده‌ها می‌توانید در صفحه نماینده جداگانه کلید متفاوت بگذارید.</p>
            </div>
        </div>

        <!-- Hide Manual Fields -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-eye-slash text-amber-400"></i> مخفی کردن تنظیمات دستی از اپ - پیشنهاد شما</h3>
            <p class="text-xs text-slate-400">شما گفتید: "در آپ کلا نمیخوام مسیرها به صورت دستی تنظیم بشه و چیزی برای تنظیم مسیرها نباشه" - این بخش دقیقاً همین را کنترل می‌کند.</p>
            
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hover:bg-slate-800 transition">
                    <input type="checkbox" name="hide_manual_panel_url" value="1" <?= $hideManualPanel ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-violet-600 focus:ring-violet-500 mt-0.5">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-white">مخفی کردن فیلد آدرس پنل از اپ (پیشنهاد: فعال)</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، در اپ فیلد "آدرس پنل" نمایش داده نمی‌شود. اپ خودکار از "آدرس پیش‌فرض پنل" که بالا تنظیم کردید استفاده می‌کند. نماینده یا حتی ادمین نیازی نیست دستی آدرس بزند.</div>
                        <div class="text-[11px] text-emerald-300 mt-2">✅ وقتی فعال باشد، اپ کلا مسیرها را از پنل وب می‌خواند - حتی اگر سرور جدید اضافه کنید، خودکار می‌خواند.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hover:bg-slate-800 transition">
                    <input type="checkbox" name="hide_manual_api_key" value="1" <?= $hideManualApiKey ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-amber-600 focus:ring-amber-500 mt-0.5">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-white">مخفی کردن فیلد کلید API از اپ (پیشنهاد: فعال)</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، فیلد کلید API در اپ مخفی می‌شود. کلید API که در پنل وب تنظیم کرده‌اید به صورت خودکار به اپ اعمال می‌شود.</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hover:bg-slate-800 transition">
                    <input type="checkbox" name="auto_fetch_servers" value="1" <?= $autoFetchServers ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-emerald-600 focus:ring-emerald-500 mt-0.5">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-white">دریافت خودکار لیست سرورها از پنل وب (پیشنهاد: فعال)</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، اپ همیشه لیست سرورها را از پنل وب می‌گیرد. وقتی شما سرور جدید اضافه می‌کنید یا سروری را حذف/ویرایش می‌کنید، اپ خودکار لیست جدید را می‌خواند و درست کار می‌کند - نیازی به تنظیم دستی نیست.</div>
                        <div class="text-[11px] text-cyan-300 mt-2">🔄 این دقیقاً چیزی است که خواستید: "حتی اگر سرور جدید اتصال دادم خودش اتومات مسیر سرور را بخونه"</div>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hover:bg-slate-800 transition">
                    <input type="checkbox" name="force_managed_mode" value="1" <?= $forceManaged ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-rose-600 focus:ring-rose-500 mt-0.5">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-white">اجبار حالت مدیریتی برای تمام اپ‌ها حتی مدیر ارشد (اختیاری)</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، حتی اپ مدیر ارشد هم بخش تنظیمات دستی را نمی‌بیند و همه چیز از پنل وب کنترل می‌شود. برای امنیت بیشتر و یکپارچگی.</div>
                        <div class="text-[11px] text-rose-300 mt-2">⚠️ اگر فعال کنید، خودتان هم در اپ نمی‌توانید آدرس پنل را دستی تغییر دهید - همه از وب کنترل می‌شود.</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Advanced JSON -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-code text-indigo-400"></i> تنظیمات پیشرفته اپ (JSON) - برای آینده</h3>
            <p class="text-xs text-slate-500">اینجا می‌توانید تنظیمات پیش‌فرض اپ را به صورت JSON تنظیم کنید. مثلاً split tunneling، auto reconnect و ...</p>
            <textarea name="app_settings_json" rows="4" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-xs font-mono" placeholder='{"split_tunneling": true, "auto_reconnect": true}'><?= htmlspecialchars($appSettingsJson) ?></textarea>
        </div>

        <!-- Info Box -->
        <div class="bg-violet-500/10 border border-violet-500/30 rounded-2xl p-5">
            <h4 class="text-sm font-bold text-violet-300 mb-3">📱 نتیجه برای اپ‌ها:</h4>
            <ul class="text-xs text-violet-200/80 space-y-2 list-disc list-inside leading-relaxed">
                <li><span class="text-white font-bold">بدون تنظیم دستی:</span> کاربر اپ را باز می‌کند، فقط یوزر/پسورد می‌زند (یا حتی آن هم از قبل ذخیره شده)، اپ خودکار آدرس پنل و کلید API را از وب می‌خواند.</li>
                <li><span class="text-white font-bold">سرور خودکار:</span> وقتی شما در پنل وب سرور جدید اضافه می‌کنید، اپ خودکار آن را می‌بیند - نیازی نیست کاربر مسیر جدید وارد کند.</li>
                <li><span class="text-white font-bold">مدیریت مرکزی:</span> تمام تنظیمات اپ از پنل وب کنترل می‌شود - حتی برای مدیر ارشد.</li>
                <li><span class="text-white font-bold">نماینده‌ها:</span> برای هر نماینده می‌توانید جداگانه در <a href="<?= Helpers::url('resellers') ?>" class="text-cyan-300 underline">صفحه نمایندگان</a> → آیکون موبایل، تنظیمات متفاوت بگذارید.</li>
            </ul>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 py-3.5 bg-violet-600 hover:bg-violet-700 text-white font-black rounded-xl text-sm shadow-lg shadow-violet-900/30">💾 ذخیره تنظیمات سراسری اپ</button>
            <a href="<?= Helpers::url('dashboard') ?>" class="px-8 py-3.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-sm border border-slate-700">لغو</a>
        </div>
    </form>

    <!-- Proposals Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-lightbulb text-amber-400"></i> پیشنهادهای تکمیلی برای مدیریت کامل از پنل وب (درخواستی شما)</h3>
        <div class="grid md:grid-cols-2 gap-4 text-xs">
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
                <div class="font-bold text-white mb-2">✅ انجام شده در v4.0.51:</div>
                <ul class="space-y-1 text-slate-400 list-disc list-inside">
                    <li>تنظیمات سراسری اپ از پنل وب</li>
                    <li>مخفی کردن فیلد آدرس پنل و API Key</li>
                    <li>دریافت خودکار سرورها از پنل</li>
                    <li>کلید API خودکار از وب به اپ</li>
                    <li>صفحه تنظیمات نماینده با استایل درست</li>
                </ul>
            </div>
            <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-4">
                <div class="font-bold text-indigo-300 mb-2">🚀 پیشنهادهای آینده (قابل پیاده‌سازی):</div>
                <ul class="space-y-1 text-indigo-200/70 list-disc list-inside">
                    <li>پنل تنظیمات تم و لوگو اپ از وب (برندینگ)</li>
                    <li>مدیریت پیام‌های اطلاعیه از وب به اپ</li>
                    <li>فورس آپدیت اجباری از وب</li>
                    <li>مخفی/نمایش قابلیت‌ها (مثلاً پروکسی، GPS) از وب</li>
                    <li>تنظیمات پیش‌فرض VPN (split tunneling و ...) از وب</li>
                    <li>لاگ و آنالیتیکس اپ در پنل وب</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

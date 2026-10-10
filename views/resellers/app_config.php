<?php
$pageTitle = 'تنظیمات اپ نماینده - ' . htmlspecialchars($reseller['username']);
require __DIR__ . '/../layout/header.php';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center"><i class="fa-solid fa-mobile-screen"></i></span>
                    تنظیمات اپ نماینده - حالت مدیریتی
                </h1>
                <p class="text-sm text-slate-400 mt-2">نماینده: <span class="text-white font-bold"><?= htmlspecialchars($reseller['username']) ?> - <?= htmlspecialchars($reseller['full_name']) ?></span></p>
                <p class="text-xs text-slate-500 mt-1">💡 در این بخش می‌توانید کلید API و حالت مدیریتی اپ را برای نماینده تنظیم کنید. اگر حالت مدیریتی فعال باشد، نماینده بخش تنظیمات پنل را در اپ نمی‌بیند و از تنظیمات شما استفاده می‌کند.</p>
            </div>
            <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm">بازگشت</a>
        </div>
    </div>

    <form method="POST" action="<?= Helpers::url('resellers/app-config/save') ?>" class="space-y-6">
        <?= Helpers::csrfField() ?>
        <input type="hidden" name="reseller_id" value="<?= $resellerId ?>">

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-key text-amber-400"></i> کلید API و آدرس پنل</h3>
            
            <div>
                <label class="text-xs text-slate-300 block mb-2">کلید API (API Key) - اختیاری</label>
                <input type="text" name="api_key" value="<?= htmlspecialchars($appConfig['api_key'] ?? $reseller['reseller_api_key'] ?? '') ?>" placeholder="مثلا: cx_api_1234567890abcdef یا خالی بگذارید" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm font-mono">
                <p class="text-[11px] text-slate-500 mt-2">💡 اگر کلید API وارد کنید، اپ با این کلید به پنل وصل می‌شود. می‌توانید به جای آدرس پنل، کلید API بدهید و یوزر/پسورد بهش وصل بشه. اگر خالی باشد، از آدرس پنل معمولی استفاده می‌شود.</p>
            </div>

            <div>
                <label class="text-xs text-slate-300 block mb-2">آدرس پنل (Panel URL)</label>
                <input type="text" name="panel_url" value="<?= htmlspecialchars($appConfig['panel_url'] ?? 'https://vpbotn.ir') ?>" placeholder="https://vpbotn.ir" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm font-mono">
                <p class="text-[11px] text-slate-500 mt-2">آدرس پنلی که اپ باید بهش وصل بشه. اگر کلید API داده باشید، این آدرس همراه کلید استفاده می‌شود.</p>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
            <h3 class="text-sm font-bold text-white flex items-center gap-2"><i class="fa-solid fa-user-shield text-indigo-400"></i> حالت مدیریتی اپ (مخفی کردن تنظیمات از نماینده)</h3>
            
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="hide_app_config" value="1" <?= (($appConfig['hide_app_config'] ?? $reseller['hide_app_config'] ?? 0) == 1) ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-indigo-600 focus:ring-indigo-500 mt-0.5">
                    <div>
                        <div class="text-sm font-bold text-white">مخفی کردن بخش تنظیمات پنل از نماینده</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، نماینده در اپ بخش "مدیریت حساب‌ها" و "آدرس پنل" را نمی‌بیند. فقط سرورهایی که شما تنظیم کرده‌اید را می‌بیند و به آنها وصل می‌شود. نماینده نیازی نیست کلید API یا آدرس پنل بزند.</div>
                        <div class="text-[11px] text-amber-300 mt-2 bg-amber-500/10 border border-amber-500/20 rounded-lg p-2">⚠️ این دقیقاً چیزی است که شما خواستید: "نماینده از پنل من استفاده می‌کنه نیازی نیست اون کلید بزنه من میزنم سرورها را وصل می‌کنم و برنامه را هم تنظیماتش انجام میدم نماینده نیازی نیست اصلا این بخش از برنامه را ببینه"</div>
                    </div>
                </label>
            </div>

            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="managed_mode" value="1" <?= (($appConfig['managed_mode'] ?? $reseller['app_managed_mode'] ?? 0) == 1) ? 'checked' : '' ?> class="w-5 h-5 rounded bg-slate-700 border-slate-600 text-violet-600 focus:ring-violet-500 mt-0.5">
                    <div>
                        <div class="text-sm font-bold text-white">حالت مدیریتی کامل (Managed Mode)</div>
                        <div class="text-xs text-slate-400 mt-1">اگر فعال باشد، تمام تنظیمات اپ توسط ادمین قفل می‌شود. نماینده فقط می‌تواند وصل/قطع کند و سرور انتخاب کند. نمی‌تواند اکانت اضافه/حذف کند یا تنظیمات پیشرفته را تغییر دهد. هرچی شما ذخیره کرده‌اید، اپ نماینده از همان استفاده می‌کند.</div>
                    </div>
                </label>
            </div>

            <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-4">
                <div class="text-xs font-bold text-indigo-300 mb-2">📱 نتیجه برای نماینده:</div>
                <ul class="text-[11px] text-indigo-200/80 space-y-1 list-disc list-inside">
                    <li>نماینده اپ را باز می‌کند، با یوزر/پسورد خودش لاگین می‌کند (بدون نیاز به وارد کردن آدرس پنل یا کلید API)</li>
                    <li>سرورهایی که شما در پنل برایش تعریف کرده‌اید را می‌بیند</li>
                    <li>بخش "مدیریت حساب‌ها" را نمی‌بیند (اگر مخفی فعال باشد)</li>
                    <li>اتصال با همان تنظیماتی که شما ذخیره کرده‌اید انجام می‌شود</li>
                </ul>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 py-3.5 bg-violet-600 hover:bg-violet-700 text-white font-black rounded-xl text-sm shadow-lg shadow-violet-900/30">💾 ذخیره تنظیمات اپ</button>
            <a href="<?= Helpers::url('resellers') ?>" class="px-8 py-3.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-sm border border-slate-700">لغو</a>
        </div>
    </form>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-white mb-3">📖 راهنما</h3>
        <div class="text-xs text-slate-400 space-y-2 leading-relaxed">
            <p><span class="text-white font-bold">سناریو 1: نماینده از پنل شما استفاده می‌کند</span><br>شما در پنل اصلی سرورها را وصل می‌کنید، تنظیمات را انجام می‌دهید، سپس در این صفحه "مخفی کردن تنظیمات" را فعال می‌کنید. نماینده وقتی اپ را باز می‌کند، فقط یوزر/پسورد خودش را می‌زند (یا حتی آن هم توسط شما از قبل ذخیره شده) و مستقیم وصل می‌شود. نیازی نیست آدرس پنل یا کلید API ببیند.</p>
            <p><span class="text-white font-bold">سناریو 2: اتصال با کلید API</span><br>به جای آدرس پنل، کلید API می‌دهید. مثلاً در اپ به جای <code>https://vpbotn.ir</code>، کلید <code>cx_api_xxx</code> را وارد می‌کنید و یوزر/پسورد را می‌زنید، اپ با کلید API به پنل وصل می‌شود و سرورها را می‌گیرد.</p>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

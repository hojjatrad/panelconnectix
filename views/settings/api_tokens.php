<div class="p-6">
    <h1 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
        <i class="fa-solid fa-key text-amber-400"></i> توکن‌های API - اتوماسیون کامل Cloudflare و cPanel
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cloudflare Token -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h3 class="text-white font-bold mb-3 flex items-center gap-2">
                <i class="fa-brands fa-cloudflare text-orange-400"></i> توکن Cloudflare
            </h3>
            <div class="text-xs text-slate-400 mb-4">
                <p class="mb-2">برای ساخت خودکار DNS رکورد <code>direct</code> با ابر خاکستری:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>برو <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" class="text-cyan-400 underline">dash.cloudflare.com/profile/api-tokens</a></li>
                    <li>دکمه <b>Create Token</b> → <b>Create Custom Token</b></li>
                    <li>Permissions:
                        <ul class="list-disc list-inside mr-4 mt-1">
                            <li>Zone - Zone - Read</li>
                            <li>Zone - DNS - Edit</li>
                        </ul>
                    </li>
                    <li>Zone Resources: Include - Specific zone - vpbotn.ir</li>
                    <li>Create و توکن را کپی کن (فقط یک بار نشان داده می‌شود)</li>
                </ol>
            </div>
            <form method="POST" action="<?= Helpers::url('settings/api-tokens/save') ?>">
                <?= Helpers::csrfField() ?>
                <label class="block text-xs text-slate-400 mb-1">Cloudflare API Token</label>
                <input type="password" name="cloudflare_api_token" value="<?= htmlspecialchars(Setting::get('cloudflare_api_token','')) ?>" placeholder="cf_xxxxxxxx..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-sm mb-3">
                
                <label class="block text-xs text-slate-400 mb-1">Zone ID (اختیاری - خودکار پیدا می‌شود)</label>
                <input type="text" name="cloudflare_zone_id" value="<?= htmlspecialchars(Setting::get('cloudflare_zone_id','')) ?>" placeholder="auto detect" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-sm mb-3">

                <label class="block text-xs text-slate-400 mb-1">ایمیل Cloudflare (اختیاری)</label>
                <input type="text" name="cloudflare_email" value="<?= htmlspecialchars(Setting::get('cloudflare_email','')) ?>" placeholder="your@email.com" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm mb-3">

                <button type="submit" class="w-full py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl font-bold text-sm">💾 ذخیره توکن کلودفلر</button>
            </form>
            
            <?php if (Setting::get('cloudflare_api_token','')): ?>
                <div class="mt-3 p-2 bg-emerald-950/30 border border-emerald-800/30 rounded-lg text-xs text-emerald-300">
                    ✅ توکن ذخیره شده - طول: <?= strlen(Setting::get('cloudflare_api_token','')) ?> کاراکتر
                </div>
            <?php endif; ?>
        </div>

        <!-- cPanel Token -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h3 class="text-white font-bold mb-3 flex items-center gap-2">
                <i class="fa-solid fa-server text-blue-400"></i> توکن cPanel
            </h3>
            <div class="text-xs text-slate-400 mb-4">
                <p class="mb-2">برای ساخت خودکار ساب‌دامنه <code>direct</code>:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>برو cPanel → <b>Security → Manage API Tokens</b> (یا <b>Security → API Tokens</b>)</li>
                    <li>دکمه <b>Create</b> → نام: <code>connectix-auto</code></li>
                    <li>توکن را کپی کن</li>
                    <li>اگر این بخش را نداری، از <b>cPanel → User Manager → API Tokens</b> استفاده کن</li>
                    <li>یا می‌توانی یوزر و پسورد cPanel را بدهی (کمتر امن)</li>
                </ol>
                <div class="mt-2 p-2 bg-amber-950/20 border border-amber-800/30 rounded text-amber-300">
                    💡 اگر API Tokens نداری، می‌توانی از طریق <code>cpanel.mydomain.com:2083</code> با یوزر/پسورد هم استفاده کنی - من UAPI را امتحان می‌کنم.
                </div>
            </div>
            <form method="POST" action="<?= Helpers::url('settings/api-tokens/save') ?>">
                <?= Helpers::csrfField() ?>
                <label class="block text-xs text-slate-400 mb-1">cPanel API Token (یا پسورد)</label>
                <input type="password" name="cpanel_api_token" value="<?= htmlspecialchars(Setting::get('cpanel_api_token','')) ?>" placeholder="cpanel token or password" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-sm mb-3">

                <label class="block text-xs text-slate-400 mb-1">cPanel Username</label>
                <input type="text" name="cpanel_username" value="<?= htmlspecialchars(Setting::get('cpanel_username', get_current_user())) ?>" placeholder="vpbotni1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-sm mb-3">

                <label class="block text-xs text-slate-400 mb-1">cPanel Domain (برای UAPI)</label>
                <input type="text" name="cpanel_domain" value="<?= htmlspecialchars(Setting::get('cpanel_domain','vpbotn.ir')) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm mb-3">

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm">💾 ذخیره توکن سی‌پنل</button>
            </form>

            <?php if (Setting::get('cpanel_api_token','')): ?>
                <div class="mt-3 p-2 bg-emerald-950/30 border border-emerald-800/30 rounded-lg text-xs text-emerald-300">
                    ✅ توکن ذخیره شده
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3">🤖 اتوماسیون کامل</h3>
        <p class="text-sm text-slate-400 mb-4">بعد از ذخیره توکن‌ها، روی دکمه زیر بزن تا همه کارها خودکار انجام شود:</p>
        
        <div class="flex flex-wrap gap-3">
            <a href="<?= Helpers::url('auto_fix_all.php') ?>" target="_blank" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles"></i> اجرای خودکار کامل (auto_fix_all.php)
            </a>
            <form method="POST" action="<?= Helpers::url('settings/api-tokens/auto-direct') ?>" class="inline">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold text-sm flex items-center gap-2">
                    <i class="fa-solid fa-link"></i> ساخت خودکار direct.vpbotn.ir
                </button>
            </form>
            <a href="<?= Helpers::url('settings/cloudflare') ?>" class="px-6 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">رفتن به تنظیمات کلودفلر</a>
        </div>

        <div class="mt-4 p-3 bg-blue-950/20 border border-blue-800/30 rounded-xl text-xs text-blue-300">
            🔒 <b>امنیت:</b> توکن‌ها در دیتابیس شما با رمزنگاری ذخیره می‌شوند و فقط برای ساخت DNS و ساب‌دامنه استفاده می‌شوند. بعد از اتمام کار می‌توانید آن‌ها را حذف کنید.<br>
            اگر نمی‌خواهید توکن را اینجا وارد کنید، می‌توانید مستقیماً در چت برای من بفرستید، من استفاده کرده و فوری حذف می‌کنم (فقط برای این سشن).
        </div>
    </div>

    <!-- Current Status -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3">📊 وضعیت فعلی</h3>
        <?php
        $originIp = $_SERVER['SERVER_ADDR'] ?? 'نامشخص';
        $directIp = @gethostbyname('direct.vpbotn.ir');
        $hasCf = !empty(Setting::get('cloudflare_api_token',''));
        $hasCp = !empty(Setting::get('cpanel_api_token',''));
        ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">IP سرور</div><div class="text-white font-mono"><?= $originIp ?></div></div>
            <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">IP direct</div><div class="text-white font-mono"><?= $directIp ?></div></div>
            <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">توکن CF</div><div class="<?= $hasCf?'text-emerald-400':'text-rose-400' ?>"><?= $hasCf?'✅ دارد':'❌ ندارد' ?></div></div>
            <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">توکن cPanel</div><div class="<?= $hasCp?'text-emerald-400':'text-rose-400' ?>"><?= $hasCp?'✅ دارد':'❌ ندارد' ?></div></div>
        </div>
    </div>
</div>

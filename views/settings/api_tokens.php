<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center"><i class="fa-solid fa-key"></i></span>
                    توکن‌های API - اتوماسیون کامل
                    <span class="text-[10px] bg-amber-500/20 text-amber-300 px-2 py-1 rounded-full border border-amber-500/30">AUTO</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">با وارد کردن توکن Cloudflare و cPanel، ساخت ساب‌دامنه <code>direct</code> و DNS به صورت 100% خودکار انجام می‌شود</p>
            </div>
            <div class="flex gap-2">
                <a href="<?= Helpers::url('settings/cloudflare') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs">🌐 تنظیمات کلودفلر</a>
                <a href="<?= Helpers::url('backups') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs">📦 بکاپ‌ها</a>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <?php
    $originIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['HTTP_HOST'] ?? 'vpbotn.ir');
    $directIp = @gethostbyname('direct.vpbotn.ir');
    $hasCf = !empty(Setting::get('cloudflare_api_token',''));
    $hasCp = !empty(Setting::get('cpanel_api_token',''));
    $directWorks = false;
    try {
        $ch = curl_init('http://direct.vpbotn.ir/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $directWorks = ($http >= 200 && $http < 400);
    } catch (Throwable $e) {}
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <div class="text-[11px] text-slate-400 mb-1">IP سرور (Origin)</div>
            <div class="text-sm font-mono font-bold text-white"><?= htmlspecialchars($originIp) ?></div>
        </div>
        <div class="bg-slate-900 border <?= $directIp === $originIp ? 'border-emerald-800/50' : 'border-amber-800/30' ?> rounded-2xl p-4">
            <div class="text-[11px] text-slate-400 mb-1">IP direct.vpbotn.ir</div>
            <div class="text-sm font-mono font-bold <?= $directIp === $originIp ? 'text-emerald-400' : 'text-amber-400' ?>"><?= htmlspecialchars($directIp) ?></div>
            <div class="text-[10px] mt-1 <?= $directIp === $originIp ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $directIp === $originIp ? '✅ مطابق' : '⚠️ متفاوت' ?></div>
        </div>
        <div class="bg-slate-900 border <?= $hasCf ? 'border-emerald-800/50' : 'border-rose-800/30' ?> rounded-2xl p-4">
            <div class="text-[11px] text-slate-400 mb-1">توکن Cloudflare</div>
            <div class="text-sm font-bold <?= $hasCf ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $hasCf ? '✅ ذخیره شده' : '❌ وارد نشده' ?></div>
        </div>
        <div class="bg-slate-900 border <?= $hasCp ? 'border-emerald-800/50' : 'border-rose-800/30' ?> rounded-2xl p-4">
            <div class="text-[11px] text-slate-400 mb-1">توکن cPanel</div>
            <div class="text-sm font-bold <?= $hasCp ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $hasCp ? '✅ ذخیره شده' : '❌ وارد نشده' ?></div>
        </div>
    </div>

    <?php if ($directWorks): ?>
    <div class="bg-emerald-950/30 border border-emerald-800/30 rounded-2xl p-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i class="fa-solid fa-check"></i></span>
        <div>
            <div class="text-sm font-bold text-emerald-300">direct.vpbotn.ir فعال است و کار می‌کند!</div>
            <div class="text-xs text-emerald-300/70">پنل از طریق <a href="https://direct.vpbotn.ir" target="_blank" class="underline">https://direct.vpbotn.ir</a> بدون ارور 520 در دسترس است</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cloudflare -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-800 bg-gradient-to-r from-orange-950/20 to-slate-900">
                <h3 class="text-white font-bold flex items-center gap-2">
                    <i class="fa-brands fa-cloudflare text-orange-400"></i> Cloudflare API Token
                    <?php if ($hasCf): ?><span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-500/30">فعال</span><?php endif; ?>
                </h3>
                <p class="text-[11px] text-slate-400 mt-1">برای ساخت خودکار DNS رکورد direct با ابر خاکستری</p>
            </div>
            <div class="p-5">
                <div class="bg-slate-800/50 rounded-xl p-3 mb-4 text-[11px] text-slate-300 leading-relaxed">
                    <div class="font-bold text-white mb-2">📍 از کجا بیارم:</div>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>برو به <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" class="text-cyan-400 underline">dash.cloudflare.com/profile/api-tokens</a></li>
                        <li><b>Create Token → Create Custom Token</b></li>
                        <li>Permissions: <code class="bg-slate-700 px-1 rounded">Zone - Zone - Read</code> + <code class="bg-slate-700 px-1 rounded">Zone - DNS - Edit</code></li>
                        <li>Zone Resources: <code>Include - Specific zone - vpbotn.ir</code></li>
                        <li>Create → کپی کن (فقط یک بار نشان داده می‌شود)</li>
                    </ol>
                </div>
                <form method="POST" action="<?= Helpers::url('settings/api-tokens/save') ?>" class="space-y-3">
                    <?= Helpers::csrfField() ?>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1.5 font-semibold">API Token</label>
                        <input type="password" name="cloudflare_api_token" value="<?= htmlspecialchars(Setting::get('cloudflare_api_token','')) ?>" placeholder="xxxx-xxxx..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1.5">Zone ID (خودکار)</label>
                            <input type="text" name="cloudflare_zone_id" value="<?= htmlspecialchars(Setting::get('cloudflare_zone_id','')) ?>" placeholder="auto" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1.5">Email (اختیاری)</label>
                            <input type="text" name="cloudflare_email" value="<?= htmlspecialchars(Setting::get('cloudflare_email','')) ?>" placeholder="email" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره توکن کلودفلر
                    </button>
                </form>
            </div>
        </div>

        <!-- cPanel -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-800 bg-gradient-to-r from-blue-950/20 to-slate-900">
                <h3 class="text-white font-bold flex items-center gap-2">
                    <i class="fa-solid fa-server text-blue-400"></i> cPanel API Token
                    <?php if ($hasCp): ?><span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-500/30">فعال</span><?php endif; ?>
                </h3>
                <p class="text-[11px] text-slate-400 mt-1">برای ساخت خودکار ساب‌دامنه direct</p>
            </div>
            <div class="p-5">
                <div class="bg-slate-800/50 rounded-xl p-3 mb-4 text-[11px] text-slate-300 leading-relaxed">
                    <div class="font-bold text-white mb-2">📍 از کجا بیارم:</div>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>cPanel → <b>Security → Manage API Tokens</b></li>
                        <li>Create → Name: <code>connectix-auto</code> → Create</li>
                        <li>توکن را کپی کن</li>
                        <li>اگر نداری: <b>User Manager → API Tokens</b></li>
                    </ol>
                </div>
                <form method="POST" action="<?= Helpers::url('settings/api-tokens/save') ?>" class="space-y-3">
                    <?= Helpers::csrfField() ?>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1.5 font-semibold">API Token / Password</label>
                        <input type="password" name="cpanel_api_token" value="<?= htmlspecialchars(Setting::get('cpanel_api_token','')) ?>" placeholder="K8QPWHF..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1.5">Username</label>
                            <input type="text" name="cpanel_username" value="<?= htmlspecialchars(Setting::get('cpanel_username', 'vpbotni1')) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1.5">Domain</label>
                            <input type="text" name="cpanel_domain" value="<?= htmlspecialchars(Setting::get('cpanel_domain','vpbotn.ir')) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> ذخیره توکن سی‌پنل
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Automation Actions -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i> اتوماسیون کامل - یک کلیک</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <a href="<?= Helpers::url('auto_fix_all.php') ?>" target="_blank" class="group p-4 bg-emerald-950/20 hover:bg-emerald-900/30 border border-emerald-800/30 hover:border-emerald-700/50 rounded-xl transition">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition"><i class="fa-solid fa-screwdriver-wrench text-xs"></i></span>
                    <span class="font-bold text-emerald-300 text-sm">تعمیر خودکار کامل</span>
                </div>
                <p class="text-[11px] text-slate-400">پاکسازی دیسک + فیکس .htaccess + بکاپ همه سرورها + تنظیم direct</p>
            </a>
            <form method="POST" action="<?= Helpers::url('settings/api-tokens/auto-direct') ?>" class="group p-4 bg-purple-950/20 hover:bg-purple-900/30 border border-purple-800/30 hover:border-purple-700/50 rounded-xl transition text-right">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="w-full text-right">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="w-8 h-8 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center group-hover:scale-110 transition"><i class="fa-solid fa-link text-xs"></i></span>
                        <span class="font-bold text-purple-300 text-sm">ساخت خودکار direct</span>
                    </div>
                    <p class="text-[11px] text-slate-400">ساخت ساب‌دامنه در cPanel + DNS در Cloudflare با ابر خاکستری</p>
                </button>
            </form>
            <a href="<?= Helpers::url('backups') ?>" class="group p-4 bg-cyan-950/20 hover:bg-cyan-900/30 border border-cyan-800/30 hover:border-cyan-700/50 rounded-xl transition">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition"><i class="fa-solid fa-box-archive text-xs"></i></span>
                    <span class="font-bold text-cyan-300 text-sm">بکاپ‌های حرفه‌ای</span>
                </div>
                <p class="text-[11px] text-slate-400">مدیریت بکاپ کامل با ساب‌لینک دقیق + بازگردانی هوشمند</p>
            </a>
        </div>
        <div class="mt-4 p-3 bg-blue-950/20 border border-blue-800/30 rounded-xl text-[11px] text-blue-300 leading-relaxed">
            🔒 <b>امنیت:</b> توکن‌ها در دیتابیس شما رمزنگاری شده ذخیره می‌شوند. بعد از اتمام کار می‌توانید از Cloudflare و cPanel آن‌ها را Delete کنید. اگر اینجا وارد نکنید، می‌توانید در چت بفرستید.
        </div>
    </div>

    <?php if (!empty($_SESSION['auto_direct_results'])): 
        $res = $_SESSION['auto_direct_results'];
        unset($_SESSION['auto_direct_results']);
    ?>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3">📋 نتیجه آخرین عملیات خودکار</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div class="bg-slate-800 rounded-xl p-3">
                <div class="font-bold text-white mb-2">cPanel:</div>
                <pre class="text-[11px] text-slate-300 whitespace-pre-wrap"><?= htmlspecialchars(json_encode($res['cpanel'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
            <div class="bg-slate-800 rounded-xl p-3">
                <div class="font-bold text-white mb-2">Cloudflare:</div>
                <pre class="text-[11px] text-slate-300 whitespace-pre-wrap"><?= htmlspecialchars(json_encode($res['cloudflare'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

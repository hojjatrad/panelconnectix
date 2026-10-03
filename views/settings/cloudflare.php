<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-white flex items-center gap-3">
            <i class="fa-solid fa-cloud text-orange-400"></i>
            رفع ارور 520 کلودفلر + تنظیمات ایران
        </h1>
        <a href="<?= Helpers::url('updater') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">بازگشت</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Status Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h3 class="text-lg font-bold text-white mb-3 flex items-center gap-2"><i class="fa-solid fa-heart-pulse text-emerald-400"></i> وضعیت فعلی</h3>
            <?php
            $free = @disk_free_space(__DIR__.'/../..');
            $freeMB = $free ? round($free/1024/1024,2) : 0;
            $ht = file_exists(__DIR__.'/../../.htaccess') ? file_get_contents(__DIR__.'/../../.htaccess') : '';
            $hasBypass = strpos($ht, 'cf-cache-status') !== false;
            $currentVer = Updater::CURRENT_VERSION ?? 'نامشخص';
            ?>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-400">نسخه پنل:</span><span class="text-white font-mono"><?= htmlspecialchars($currentVer) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">فضای آزاد دیسک:</span><span class="<?= $freeMB < 100 ? 'text-rose-400' : 'text-emerald-400' ?> font-mono"><?= $freeMB ?>MB</span></div>
                <div class="flex justify-between"><span class="text-slate-400">.htaccess بای‌پس:</span><span class="<?= $hasBypass ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $hasBypass ? '✅ فعال' : '⚠️ غیرفعال' ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">دامنه فعلی:</span><span class="text-white font-mono"><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">IP سرور:</span><span class="text-white font-mono"><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'نامشخص') ?></span></div>
            </div>
            <div class="mt-4 flex gap-2">
                <a href="<?= Helpers::url('../repair_cloudflare.php') ?>" target="_blank" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl text-sm font-bold">🔧 اجرای تعمیر خودکار</a>
                <a href="<?= Helpers::url('../quick_update.php') ?>" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold">⬆️ آپدیت سریع</a>
            </div>
        </div>

        <!-- Iran Fix -->
        <div class="bg-gradient-to-br from-amber-950/40 to-orange-950/40 border border-amber-800/30 rounded-2xl p-5">
            <h3 class="text-lg font-bold text-amber-300 mb-3">🇮🇷 کلودفلر در ایران فیلتر است - راه حل</h3>
            <p class="text-sm text-amber-200/80 mb-3">ارور 520 در ایران معمولاً به خاطر فیلتر IP های کلودفلر است. بهترین راه: یک ساب‌دامنه مستقیم بدون کلودفلر بسازید.</p>
            <div class="bg-slate-950/60 rounded-xl p-3 font-mono text-xs text-slate-300 mb-3">
                <div>Type: <span class="text-emerald-400">A</span></div>
                <div>Name: <span class="text-cyan-400">direct</span></div>
                <div>Content: <span class="text-amber-400"><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'IP هاست') ?></span></div>
                <div>Proxy: <span class="text-rose-400 font-bold">DNS only (ابر خاکستری) ⚠️ مهم</span></div>
                <div>TTL: Auto</div>
            </div>
            <p class="text-xs text-slate-400">سپس با <code class="bg-slate-800 px-1.5 py-0.5 rounded">https://direct.<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?></code> وارد شوید. این دامنه کلودفلر را کاملاً دور می‌زند.</p>
            <div class="mt-3 p-2 bg-blue-950/30 border border-blue-800/30 rounded-lg text-xs text-blue-300">
                💡 جایگزین ایرانی: می‌توانید از <b>ArvanCloud</b> به جای کلودفلر استفاده کنید که در ایران اختلال ندارد.
            </div>
        </div>
    </div>

    <!-- Cloudflare Dashboard Fixes -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-lg font-bold text-white mb-4">⚙️ تنظیمات پیشنهادی در داشبورد کلودفلر</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="bg-slate-800/50 rounded-xl p-4">
                <h4 class="font-bold text-white mb-2">1. SSL/TLS</h4>
                <ul class="space-y-1 text-slate-300 text-xs list-disc list-inside">
                    <li>SSL/TLS > Overview > <b class="text-emerald-400">Full</b> (نه Flexible)</li>
                    <li>Edge Certificates > Always Use HTTPS = ON</li>
                    <li>Minimum TLS Version = 1.2</li>
                </ul>
            </div>
            <div class="bg-slate-800/50 rounded-xl p-4">
                <h4 class="font-bold text-white mb-2">2. Speed > Optimization</h4>
                <ul class="space-y-1 text-slate-300 text-xs list-disc list-inside">
                    <li>Rocket Loader = <b class="text-rose-400">OFF</b> (مهم - باعث 520 میشه)</li>
                    <li>Email Obfuscation = OFF</li>
                    <li>Auto Minify: JS/CSS/HTML = OFF (پنل خودش minify داره)</li>
                </ul>
            </div>
            <div class="bg-slate-800/50 rounded-xl p-4">
                <h4 class="font-bold text-white mb-2">3. Caching</h4>
                <ul class="space-y-1 text-slate-300 text-xs list-disc list-inside">
                    <li>Caching Level = Standard</li>
                    <li>Browser Cache TTL = Respect Existing Headers</li>
                    <li>Page Rule: <code>domain.com/updater/*</code> => Bypass</li>
                    <li>Page Rule: <code>domain.com/servers/*</code> => Bypass</li>
                </ul>
            </div>
            <div class="bg-slate-800/50 rounded-xl p-4">
                <h4 class="font-bold text-white mb-2">4. Network & Firewall</h4>
                <ul class="space-y-1 text-slate-300 text-xs list-disc list-inside">
                    <li>Network > gRPC = OFF</li>
                    <li>Network > WebSockets = ON</li>
                    <li>Firewall > Bot Fight Mode = OFF</li>
                    <li>Firewall > Security Level = Medium</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Client Import Debug -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-2xl p-5">
        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-users text-purple-400"></i> دیباگ ایمپورت کلاینت‌ها (وقتی سرور اضافه می‌کنید کلاینت نمیاد)</h3>
        <?php
        try {
            $pdo = Database::getConnection();
            $logs = $pdo->query("SELECT s.name, l.* FROM server_sync_logs l LEFT JOIN server_nodes s ON s.id = l.server_id ORDER BY l.id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
            $servers = $pdo->query("SELECT id, name, driver, api_url, auto_import_clients, auto_import_categories, auto_import_plans FROM server_nodes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $logs=[]; $servers=[]; }
        ?>
        <div class="mb-4">
            <h4 class="font-bold text-slate-300 mb-2">سرورها و تنظیمات ایمپورت:</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-slate-400 border-b border-slate-800"><th class="p-2 text-right">سرور</th><th class="p-2">درایور</th><th class="p-2">پلن</th><th class="p-2">دسته</th><th class="p-2">کلاینت</th><th class="p-2">API</th></tr></thead>
                    <tbody>
                    <?php foreach ($servers as $sv): ?>
                        <tr class="border-b border-slate-800/50">
                            <td class="p-2 text-white"><?= htmlspecialchars($sv['name']) ?></td>
                            <td class="p-2 font-mono text-slate-300"><?= htmlspecialchars($sv['driver']) ?></td>
                            <td class="p-2 text-center"><?= $sv['auto_import_plans'] ? '✅' : '❌' ?></td>
                            <td class="p-2 text-center"><?= $sv['auto_import_categories'] ? '✅' : '❌' ?></td>
                            <td class="p-2 text-center"><?= $sv['auto_import_clients'] ? '✅' : '❌' ?></td>
                            <td class="p-2 font-mono text-[10px] text-slate-400"><?= htmlspecialchars(substr($sv['api_url'],0,35)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h4 class="font-bold text-slate-300 mb-2">آخرین لاگ‌های همگام‌سازی:</h4>
            <?php if ($logs): ?>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-slate-900"><tr class="text-slate-400 border-b border-slate-800"><th class="p-2 text-right">سرور</th><th class="p-2 text-right">عملیات</th><th class="p-2 text-right">جزئیات</th><th class="p-2 text-right">زمان</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $lg): ?>
                        <tr class="border-b border-slate-800/30">
                            <td class="p-2 text-white"><?= htmlspecialchars($lg['name'] ?? $lg['server_id']) ?></td>
                            <td class="p-2"><span class="px-1.5 py-0.5 bg-slate-800 rounded text-[10px]"><?= htmlspecialchars($lg['action']) ?></span></td>
                            <td class="p-2 text-slate-300 max-w-xs truncate" title="<?= htmlspecialchars($lg['details'] ?? '') ?>"><?= htmlspecialchars(mb_substr($lg['details'] ?? '',0,120)) ?></td>
                            <td class="p-2 text-slate-400 font-mono text-[10px]"><?= htmlspecialchars($lg['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-amber-400 text-sm">هنوز لاگی ثبت نشده - یعنی ایمپورت انجام نشده یا جدول خالی است.</p>
            <?php endif; ?>
        </div>

        <div class="mt-4 p-3 bg-purple-950/20 border border-purple-800/30 rounded-xl text-xs text-purple-300">
            <b>چرا کلاینت‌ها نمیان؟</b> 3 دلیل رایج:
            <ol class="list-decimal list-inside mt-1 space-y-1">
                <li>تیک <code>ایمپورت کلاینت‌ها</code> هنگام افزودن سرور خاموش بوده (در v6.8.27 پیش‌فرض روشن شد)</li>
                <li>درایور سرور درست تشخیص داده نشده یا <code>api_url</code> اشتباهه - تست API بزنید</li>
                <li>سرور اصلی لیست خالی برمیگردونه - با <code>repair_cloudflare.php</code> لاگ‌ها را ببینید</li>
            </ol>
            <div class="mt-2">راه حل: در لیست سرورها دکمه <b>ایمپورت کامل</b> را بزنید - این دسته+پلن+کلاینت با ساب‌لینک دقیق را می‌آورد.</div>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <a href="<?= Helpers::url('servers') ?>" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-bold">رفتن به مدیریت سرورها</a>
        <a href="<?= Helpers::url('../repair_cloudflare.php') ?>" target="_blank" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">اجرای تعمیر 520</a>
    </div>
</div>

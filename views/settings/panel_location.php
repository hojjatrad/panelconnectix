<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i class="fa-solid fa-location-crosshairs"></i></span>
                    مدیریت هوشمند مسیر پنل - استقلال از Path
                    <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-1 rounded-full border border-emerald-500/30">PANEL LOCATION v8.0 PRO MAX</span>
                </h1>
                <p class="text-xs text-slate-400 mt-2">وقتی پنل را از /contax به / یا /panel منتقل می‌کنی، اپ خودکار مسیر جدید را کشف می‌کند</p>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="<?= Helpers::url('settings/panel-location/check') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold">🔍 بررسی استقلال مسیر</button>
                </form>
                <form method="POST" action="<?= Helpers::url('settings/panel-location/regenerate') ?>" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold">♻️ بازسازی Well-Known + Redirector</button>
                </form>
            </div>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../core/PanelLocationManager.php';
    $currentDomain = PanelLocationManager::getCurrentDomain();
    $currentPath = PanelLocationManager::getCurrentPanelPath();
    $currentBase = PanelLocationManager::getCurrentBaseUrl();
    $currentApi = PanelLocationManager::getCurrentApiUrl();
    $storedPath = PanelLocationManager::getStoredPanelPath();
    $storedBase = PanelLocationManager::getStoredBaseUrl();
    $oldPaths = PanelLocationManager::getOldPaths();
    $fallbacks = PanelLocationManager::generateFallbackUrls();
    ?>

    <!-- Current Status -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-emerald-800/30 rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">مسیر فعلی (تشخیص خودکار)</div>
            <div class="text-lg font-mono font-bold text-emerald-400"><?= htmlspecialchars($currentPath ?: '/ (root)') ?></div>
            <div class="text-[11px] text-slate-500 mt-1">از SCRIPT_NAME: <?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '') ?></div>
            <div class="mt-3 p-2 bg-slate-800 rounded-lg">
                <div class="text-[10px] text-slate-400">آدرس کامل فعلی:</div>
                <div class="text-xs font-mono text-white break-all"><?= htmlspecialchars($currentBase) ?></div>
            </div>
        </div>
        <div class="bg-slate-900 border <?= $storedPath === $currentPath ? 'border-emerald-800/30' : 'border-amber-800/30' ?> rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">مسیر ذخیره شده در دیتابیس</div>
            <div class="text-lg font-mono font-bold <?= $storedPath === $currentPath ? 'text-emerald-400' : 'text-amber-400' ?>"><?= htmlspecialchars($storedPath ?: '/ (root)') ?></div>
            <div class="text-[11px] mt-1 <?= $storedPath === $currentPath ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $storedPath === $currentPath ? '✅ مطابق' : '⚠️ متفاوت - نیاز به مهاجرت' ?></div>
            <div class="mt-3 p-2 bg-slate-800 rounded-lg">
                <div class="text-[10px] text-slate-400">آدرس ذخیره شده:</div>
                <div class="text-xs font-mono text-white break-all"><?= htmlspecialchars($storedBase ?: 'خالی') ?></div>
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <div class="text-[11px] text-slate-400 mb-1">API Endpoint فعلی</div>
            <div class="text-xs font-mono font-bold text-white break-all"><?= htmlspecialchars($currentApi) ?></div>
            <div class="text-[11px] text-slate-500 mt-2">برای اپلیکیشن</div>
            <div class="mt-3">
                <div class="text-[10px] text-slate-400 mb-1">مسیرهای قدیمی (<?= count($oldPaths) ?>):</div>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($oldPaths as $op): ?>
                        <span class="px-2 py-1 bg-slate-800 text-slate-300 rounded-full text-[10px] font-mono"><?= htmlspecialchars($op ?: '/') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code for App Connection -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-900 border border-violet-800/30 rounded-2xl p-5">
            <h3 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
                <i class="fa-solid fa-qrcode text-violet-400"></i>
                QR کد اتصال اپ - برای وقتی اپ قطع شده
            </h3>
            <p class="text-xs text-slate-400 mb-4">اگر اپ به خاطر تغییر مسیر قطع شده، کاربر می‌تواند این QR را اسکن کند تا آدرس جدید خودکار تنظیم شود</p>
            
            <div class="bg-white p-4 rounded-2xl w-fit mx-auto mb-4">
                <div id="panelQrCode" class="w-48 h-48 flex items-center justify-center"></div>
            </div>
            
            <div class="space-y-2">
                <div class="p-3 bg-slate-800 rounded-xl">
                    <div class="text-[10px] text-slate-400 mb-1">آدرس پنل (برای اسکن):</div>
                    <div class="flex gap-2">
                        <input id="panelUrlInput" type="text" value="<?= htmlspecialchars($currentBase) ?>" readonly class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-xs">
                        <button onclick="copyPanelUrl()" class="px-3 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-xs font-bold">کپی</button>
                    </div>
                </div>
                <div class="p-3 bg-slate-800 rounded-xl">
                    <div class="text-[10px] text-slate-400 mb-1">دیپ‌لینک (connectix://):</div>
                    <div class="text-[11px] font-mono text-violet-300 break-all">connectix://panel?url=<?= urlencode($currentBase) ?></div>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h3 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
                <i class="fa-solid fa-route text-emerald-400"></i>
                مسیرهای Fallback برای اپ
            </h3>
            <p class="text-xs text-slate-400 mb-3">اپ این آدرس‌ها را به ترتیب تست می‌کند تا یکی کار کند</p>
            
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($fallbacks as $idx => $fb): ?>
                <div class="flex items-center gap-2 p-2 bg-slate-800 rounded-lg">
                    <span class="w-6 h-6 rounded-full bg-slate-700 text-slate-300 flex items-center justify-center text-[10px] font-bold"><?= $idx+1 ?></span>
                    <span class="text-xs font-mono text-white flex-1 truncate"><?= htmlspecialchars($fb) ?></span>
                    <span class="text-[10px] px-2 py-1 rounded-full <?= $fb === $currentBase ? 'bg-emerald-900/30 text-emerald-300 border border-emerald-800/30' : 'bg-slate-700 text-slate-400' ?>"><?= $fb === $currentBase ? 'فعلی' : 'fallback' ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 p-3 bg-emerald-950/20 border border-emerald-800/30 rounded-xl">
                <div class="text-[11px] text-emerald-300 font-bold mb-1">✅ استراتژی Resolver هوشمند v8.0:</div>
                <ol class="text-[11px] text-slate-300 space-y-1 list-decimal list-inside">
                    <li>آدرس ذخیره شده قبلی (سریع‌ترین)</li>
                    <li>Well-Known: /.well-known/connectix.json</li>
                    <li>API: /api/v1/app/panel-location</li>
                    <li>Old Path Redirector: /contax → جدید</li>
                    <li>Brute-force: /, /contax, /panel, /admin...</li>
                    <li>Remote Config: Main Server</li>
                    <li>QR اسکن (آخرین راه)</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Auto Migration Result -->
    <?php if (!empty($migrationResult)): ?>
    <div class="bg-slate-900 border <?= $migrationResult['migrated'] ? 'border-emerald-800/30' : 'border-slate-800' ?> rounded-2xl p-5">
        <h3 class="text-white font-bold mb-3 text-sm flex items-center gap-2">
            <i class="fa-solid fa-wand-magic-sparkles text-emerald-400"></i>
            نتیجه بررسی خودکار مهاجرت مسیر
        </h3>
        <div class="space-y-2">
            <?php foreach ($migrationResult['actions'] as $act): ?>
                <div class="p-2 bg-slate-800 rounded-lg text-xs text-slate-300">• <?= htmlspecialchars($act) ?></div>
            <?php endforeach; ?>
            <?php if (empty($migrationResult['actions'])): ?>
                <div class="p-3 bg-emerald-950/20 border border-emerald-800/30 rounded-xl text-xs text-emerald-300">✅ همه چیز درست است - مسیر تغییر نکرده</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Independence Check -->
    <?php if (!empty($checks)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-white font-bold text-sm">🔍 بررسی استقلال از مسیر (Path Independence Check)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800/50 text-slate-400">
                    <tr>
                        <th class="p-3 text-right">بخش</th>
                        <th class="p-3 text-center">وضعیت</th>
                        <th class="p-3 text-right">مقدار</th>
                        <th class="p-3 text-right">اقدام</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $key => $chk): ?>
                    <tr class="border-t border-slate-800/50">
                        <td class="p-3 text-white font-bold"><?= htmlspecialchars($chk['label']) ?></td>
                        <td class="p-3 text-center"><?= $chk['ok'] ? '<span class="px-2 py-1 bg-emerald-900/30 text-emerald-300 rounded-full text-[10px] border border-emerald-800/30">✅ درست</span>' : '<span class="px-2 py-1 bg-amber-900/30 text-amber-300 rounded-full text-[10px] border border-amber-800/30">⚠️ نیاز به فیکس</span>' ?></td>
                        <td class="p-3 text-slate-400 text-[11px] font-mono"><?= htmlspecialchars($chk['value'] ?? '-') ?></td>
                        <td class="p-3 text-slate-300 text-[11px]"><?= htmlspecialchars($chk['fix'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- How it works -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-rocket text-emerald-400"></i> نصب جدید در مسیر جدید</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• پنل را در هر مسیری نصب کن: <code>/</code> یا <code>/panel</code> یا <code>/contax</code></li>
                <li>• در اولین بازدید، مسیر خودکار ذخیره می‌شود</li>
                <li>• فایل <code>/.well-known/connectix.json</code> خودکار ساخته می‌شود</li>
                <li>• ریدایرکتور در مسیرهای قدیمی ساخته می‌شود</li>
                <li>• اپ بدون هیچ تنظیمی کار می‌کند ✅</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-repeat text-cyan-400"></i> تغییر مسیر (Path Migration)</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• اگر از <code>/contax</code> به <code>/</code> منتقل کنی</li>
                <li>• سیستم خودکار تشخیص می‌دهد (هر 1 ساعت + هر درخواست API)</li>
                <li>• مسیر قدیمی به لیست <code>old_panel_paths</code> اضافه می‌شود</li>
                <li>• در مسیر قدیمی فایل ریدایرکتور ساخته می‌شود</li>
                <li>• well-known بروز می‌شود + هدر canonical</li>
            </ul>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
            <h4 class="font-bold text-white mb-2 text-xs flex items-center gap-2"><i class="fa-solid fa-shield-halved text-violet-400"></i> اپ هوشمند v8.0</h4>
            <ul class="space-y-1.5 text-[11px] text-slate-400 leading-relaxed">
                <li>• اپ اول آدرس ذخیره شده را تست می‌کند</li>
                <li>• اگر شکست خورد → well-known را می‌خواند</li>
                <li>• سپس panel-location API + old path redirector</li>
                <li>• سپس brute-force مسیرهای رایج (موازی)</li>
                <li>• آخرش QR اسکن - همیشه کار می‌کند</li>
                <li>• هر پاسخ API هدر canonical دارد → اپ بی‌صدا آپدیت می‌شود</li>
            </ul>
        </div>
    </div>

    <div class="bg-violet-950/20 border border-violet-800/30 rounded-2xl p-4">
        <h4 class="font-bold text-violet-300 mb-2 text-xs">💡 سناریو واقعی شما:</h4>
        <ol class="list-decimal list-inside space-y-1.5 text-[11px] text-slate-300 leading-relaxed">
            <li>پنل قبلا در <code>https://vpbotn.ir/contax</code> بود و اپ به همین آدرس وصل می‌شد</li>
            <li>شما پنل را در مسیر جدید <code>https://vpbotn.ir/</code> یا <code>/panel</code> نصب کردی</li>
            <li>قبلا اپ می‌مرد چون <code>/contax</code> دیگر وجود نداشت ❌</li>
            <li>الان با v8.0: در <code>/contax</code> یک ریدایرکتور خودکار ساخته می‌شود که به مسیر جدید اشاره می‌کند ✅</li>
            <li>اپ وقتی به <code>/contax</code> درخواست می‌زند، پاسخ <code>{\"migrated\":true,\"new_url\":\"https://vpbotn.ir/\"}</code> می‌گیرد</li>
            <li>اپ خودکار آدرس جدید را ذخیره می‌کند و ادامه می‌دهد - کاربر هیچی نمی‌فهمه! ✅</li>
            <li>حتی اگر ریدایرکتور هم نباشه، اپ well-known را چک می‌کند: <code>https://vpbotn.ir/.well-known/connectix.json</code> که همیشه مسیر جدید را دارد</li>
        </ol>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    // Generate QR Code
    document.addEventListener('DOMContentLoaded', function() {
        const panelUrl = document.getElementById('panelUrlInput').value;
        const qrContainer = document.getElementById('panelQrCode');
        
        if (qrContainer && typeof QRCode !== 'undefined') {
            try {
                new QRCode(qrContainer, {
                    text: panelUrl,
                    width: 192,
                    height: 192,
                    colorDark: "#0f172a",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                qrContainer.innerHTML = '<div class="text-xs text-slate-500 p-2">خطا در تولید QR: ' + e.message + '</div>';
            }
        }
    });

    function copyPanelUrl() {
        const input = document.getElementById('panelUrlInput');
        input.select();
        document.execCommand('copy');
        
        // Show feedback
        const btn = event.target;
        const orig = btn.innerText;
        btn.innerText = '✅ کپی شد';
        setTimeout(() => btn.innerText = orig, 2000);
    }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

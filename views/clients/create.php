<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Breadcrumb & Title -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= Helpers::url('clients') ?>" class="text-xs text-purple-400 hover:underline flex items-center gap-1 mb-1">
                <i class="fa-solid fa-arrow-right"></i>
                <span>بازگشت به لیست کلاینت‌ها</span>
            </a>
            <h2 class="text-xl font-bold text-white">صدور کلاینت و سرویس جدید</h2>
        </div>
        <div class="text-left">
            <span class="text-xs text-slate-400 block">موجودی کیف پول:</span>
            <span class="text-base font-bold text-emerald-400"><?= Helpers::formatMoney($user['wallet_balance']) ?></span>
        </div>
    </div>

    <!-- Creation Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <form action="<?= Helpers::url('clients/store') ?>" method="POST" id="createClientForm" class="space-y-5">
            <?= Helpers::csrfField() ?>

            <!-- Username & Password -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">نام کاربری کلاینت (انگلیسی) *</label>
                    <div class="relative">
                        <input type="text" name="username" id="usernameInput" value="<?= htmlspecialchars($generatedUsername) ?>" required dir="ltr"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white font-mono text-xs focus:outline-none focus:ring-1 focus:ring-purple-500">
                        <button type="button" onclick="randomizeUsername()" class="absolute inset-y-0 left-0 px-3 text-slate-400 hover:text-purple-400" title="تولید تصادفی">
                            <i class="fa-solid fa-arrows-rotate text-xs"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">رمز عبور (اختیاری جهت لاگین اپلیکیشن)</label>
                    <input type="text" name="password" value="<?= htmlspecialchars($generatedPassword) ?>" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white font-mono text-xs focus:outline-none focus:ring-1 focus:ring-purple-500">
                </div>
            </div>

            <!-- Customer Name -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-solid fa-user text-cyan-400"></i>
                        <span>نام و نام خانوادگی خریدار / مشتری (اختیاری)</span>
                    </span>
                    <span class="text-[10px] text-slate-400 font-normal">جهت شناسایی مالک سرویس در لیست کلاینت‌ها</span>
                </label>
                <input type="text" name="customer_name" placeholder="مثلاً: علی رضایی یا شرکت البرز"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-xs focus:outline-none focus:ring-1 focus:ring-purple-500">
            </div>

            <!-- Plan Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2">انتخاب تعرفه و پلن *</label>
                <select name="plan_id" id="planSelect" required onchange="calculateCost()"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <option value="" disabled selected>لطفاً یک پلن انتخاب کنید...</option>
                    <?php 
                    $tier = class_exists('Provisioner') 
                        ? Provisioner::getResellerTier($user['id']) 
                        : ['tier' => 'bronze', 'title' => 'برنزی', 'badge' => '🥉', 'discount' => (int)($user['discount_percent'] ?? 0)];
                    $effectiveDiscount = (int)$tier['discount'];
                    foreach ($plans as $p): 
                        $effectivePrice = $p['reseller_price'];
                        if ($effectiveDiscount > 0) {
                            $effectivePrice = $effectivePrice - ($effectivePrice * ($effectiveDiscount / 100));
                        }
                        $trVal = (float)$p['traffic_gb'];
                        $trTxt = ($trVal > 0 && $trVal < 1) ? round($trVal * 1024) . ' مگابایت' : (($trVal == (int)$trVal ? (int)$trVal : $trVal) . ' گیگابایت');
                    ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $effectivePrice ?>" data-group="<?= $p['server_group'] ?>" data-server-id="<?= $p['server_id'] ?? '' ?>" data-ip-limit="<?= $p['ip_limit'] ?? 0 ?>" data-free="<?= $p['is_free'] ?>">
                            <?= htmlspecialchars($p['title']) ?> (<?= $trTxt ?> / <?= $p['duration_days'] ?> روزه) - <?= $p['is_free'] ? 'رایگان (تست)' : Helpers::formatMoney($effectivePrice) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Server Selection - Improved with VIP badge -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2 flex items-center justify-between">
                    <span>انتخاب سرور مقصد (پروتکل و کلاستر) *</span>
                    <span class="text-[10px] text-slate-400">VIP هم نمایش داده می‌شود</span>
                </label>
                <select name="server_id" id="serverSelect" required
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <option value="auto" selected>⚡️ انتخاب خودکار هوشمند (پایدارترین و کم‌ترافیک‌ترین سرور)</option>
                    <?php foreach ($servers as $s):
                        $isVip = in_array(strtolower($s['driver']), ['connectix_seller','connectix','seller']);
                        $badge = $isVip ? '🌟 VIP Connectix (85 کلاینت)' : '';
                        $driverLabel = $isVip ? 'Connectix Seller API' : strtoupper($s['driver']);
                    ?>
                        <option value="<?= $s['id'] ?>" data-group="<?= $s['server_group'] ?>" data-driver="<?= $s['driver'] ?>" <?= $isVip ? 'style="background:#7f1d1d;color:#fca5a5;font-weight:bold;"' : '' ?>>
                            <?= $isVip ? '🌟 ' : '' ?><?= htmlspecialchars($s['name']) ?> (هسته: <?= $driverLabel ?> - دسته: <?= $s['server_group'] ?>) <?= $badge ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-slate-500 mt-1">اگر VIP را نمی‌بینی، <a href="<?= Helpers::url('servers') ?>" class="text-cyan-400 underline">سرورها</a> را چک کن که فعال باشد — سیستم خودکار آن را فعال می‌کند.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2 flex items-center justify-between">
                        <span>سقف اتصال همزمان (تعداد کاربر/IP)</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-900/30 text-emerald-300 border border-emerald-700/30">پیشفرض VIP: 4 نفر</span>
                    </label>
                    <div class="relative">
                        <input type="number" name="ip_limit" id="clientIpLimit" value="4" min="0" max="100" placeholder="4 = پیشفرض حرفه‌ای"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white font-mono text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 text-center font-bold">
                        <div class="absolute inset-y-0 left-0 flex items-center gap-1 pl-2">
                            <button type="button" onclick="setIpLimit(1)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-[10px] text-slate-300">1</button>
                            <button type="button" onclick="setIpLimit(2)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-[10px] text-slate-300">2</button>
                            <button type="button" onclick="setIpLimit(4)" class="px-2 py-1 bg-emerald-800 hover:bg-emerald-700 rounded text-[10px] text-emerald-200 font-bold border border-emerald-700">4</button>
                            <button type="button" onclick="setIpLimit(0)" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-[10px] text-slate-300">∞</button>
                        </div>
                    </div>
                    <span class="text-[10px] text-slate-400 mt-1 block">💡 پیشفرض حرفه‌ای 4 نفره (مثل پنل VIP) — 0 = نامحدود</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">یادداشت برای این مشتری (اختیاری)</label>
                    <input type="text" name="custom_note" placeholder="مثلاً: آقای رضایی - تمدید سالانه"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
                </div>
            </div>

            <!-- First-Connect Activation Option - Default Checked -->
            <div class="p-4 bg-gradient-to-r from-indigo-950/50 to-purple-950/30 border border-indigo-700/40 rounded-xl flex items-start gap-3 shadow-inner">
                <input type="checkbox" name="start_on_first_use" id="start_on_first_use" value="1" checked class="mt-0.5 rounded bg-slate-800 border-slate-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                <div class="flex-1">
                    <label for="start_on_first_use" class="text-xs text-indigo-200 font-bold cursor-pointer select-none flex items-center gap-2">
                        <span>🕒 فعال‌سازی با اولین اتصال</span>
                        <span class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px]">پیشفرض فعال ✅</span>
                    </label>
                    <p class="text-[11px] text-indigo-300/70 mt-1 leading-relaxed">زمان انقضا دقیقاً پس از اولین اتصال مشتری آغاز می‌شود — حرفه‌ای برای فروش (مثل VIP). اگر خاموش باشد، از لحظه ساخت محاسبه می‌شود.</p>
                </div>
            </div>

            <!-- Live Cost Summary Box -->
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 space-y-2 text-xs">
                <div class="flex justify-between items-center text-slate-400">
                    <span>هزینه پلن:</span>
                    <span id="planCostText" class="font-bold text-white">۰ تومان</span>
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>تخفیف نمایندگی شما:</span>
                    <span class="font-bold text-purple-400"><?= $effectiveDiscount ?>% (سطح <?= $tier['title'] ?> <?= $tier['badge'] ?>)</span>
                </div>
                <div class="border-t border-slate-700/60 pt-2 flex justify-between items-center font-bold">
                    <span class="text-slate-300">کسر از کیف پول:</span>
                    <span id="finalCostText" class="text-emerald-400 text-sm">۰ تومان</span>
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/40 flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i>
                <span>ایجاد کاربر و صدور آنی ساب‌لینک</span>
            </button>
        </form>
    </div>
</div>

<script>
    function randomizeUsername() {
        const rand = Math.random().toString(36).substring(2, 8);
        document.getElementById('usernameInput').value = 'user_' + rand;
    }
    function setIpLimit(v){
        const el=document.getElementById('clientIpLimit');
        if(el) el.value=v;
    }
    function calculateCost() {
        const planSelect = document.getElementById('planSelect');
        const selected = planSelect.options[planSelect.selectedIndex];
        if (!selected) return;

        const price = parseInt(selected.getAttribute('data-price') || 0);
        const isFree = selected.getAttribute('data-free') === '1';
        const boundServerId = selected.getAttribute('data-server-id');
        const planIpLimit = selected.getAttribute('data-ip-limit');

        if (boundServerId && document.getElementById('serverSelect')) {
            document.getElementById('serverSelect').value = boundServerId;
        }
        // Only override IP limit if plan has explicit >0, otherwise keep default 4 (VIP style)
        if (planIpLimit && parseInt(planIpLimit) > 0 && document.getElementById('clientIpLimit')) {
            document.getElementById('clientIpLimit').value = planIpLimit;
        } else if (document.getElementById('clientIpLimit') && !document.getElementById('clientIpLimit').value) {
            document.getElementById('clientIpLimit').value = 4;
        }

        if (isFree) {
            document.getElementById('planCostText').innerText = 'رایگان';
            document.getElementById('finalCostText').innerText = '۰ تومان';
        } else {
            document.getElementById('planCostText').innerText = price.toLocaleString('fa-IR') + ' تومان';
            document.getElementById('finalCostText').innerText = price.toLocaleString('fa-IR') + ' تومان';
        }
    }
    // Ensure defaults on load
    document.addEventListener('DOMContentLoaded', function(){
        const cb=document.getElementById('start_on_first_use');
        if(cb) cb.checked=true;
        const ip=document.getElementById('clientIpLimit');
        if(ip && (!ip.value || ip.value=='0')) ip.value=4;
    });
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

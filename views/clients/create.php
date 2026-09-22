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

            <!-- Plan Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2">انتخاب تعرفه و پلن *</label>
                <select name="plan_id" id="planSelect" required onchange="calculateCost()"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <option value="" disabled selected>لطفاً یک پلن انتخاب کنید...</option>
                    <?php 
                    $tier = Provisioner::getResellerTier($user['id']);
                    $effectiveDiscount = (int)$tier['discount'];
                    foreach ($plans as $p): 
                        $effectivePrice = $p['reseller_price'];
                        if ($effectiveDiscount > 0) {
                            $effectivePrice = $effectivePrice - ($effectivePrice * ($effectiveDiscount / 100));
                        }
                    ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $effectivePrice ?>" data-group="<?= $p['server_group'] ?>" data-free="<?= $p['is_free'] ?>">
                            <?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?> گیگابایت / <?= $p['duration_days'] ?> روزه) - <?= $p['is_free'] ? 'رایگان (تست)' : Helpers::formatMoney($effectivePrice) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Server Selection -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2">انتخاب سرور مقصد (پروتکل و کلاستر) *</label>
                <select name="server_id" id="serverSelect" required
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <option value="auto" selected>⚡️ انتخاب خودکار هوشمند (پایدارترین و کم‌ترافیک‌ترین سرور)</option>
                    <?php foreach ($servers as $s): ?>
                        <option value="<?= $s['id'] ?>" data-group="<?= $s['server_group'] ?>">
                            <?= htmlspecialchars($s['name']) ?> (هسته: <?= strtoupper($s['driver']) ?> - دسته: <?= $s['server_group'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">سقف اتصال همزمان (تعداد کاربر/IP)</label>
                    <input type="number" name="ip_limit" id="clientIpLimit" value="2" min="0" placeholder="0 = نامحدود"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white font-mono text-xs focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <span class="text-[10px] text-slate-500 mt-1 block">تعداد دستگاه‌های مجاز برای اتصال همزمان به کانفیگ</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">یادداشت برای این مشتری (اختیاری)</label>
                    <input type="text" name="custom_note" placeholder="مثلاً: آقای رضایی - تمدید سالانه"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
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

    function calculateCost() {
        const planSelect = document.getElementById('planSelect');
        const selected = planSelect.options[planSelect.selectedIndex];
        if (!selected) return;

        const price = parseInt(selected.getAttribute('data-price') || 0);
        const isFree = selected.getAttribute('data-free') === '1';

        if (isFree) {
            document.getElementById('planCostText').innerText = 'رایگان';
            document.getElementById('finalCostText').innerText = '۰ تومان';
        } else {
            document.getElementById('planCostText').innerText = price.toLocaleString('fa-IR') + ' تومان';
            document.getElementById('finalCostText').innerText = price.toLocaleString('fa-IR') + ' تومان';
        }
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

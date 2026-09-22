<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-handshake text-indigo-400"></i>
            <span>مدیریت شبکه نمایندگان فروش (Resellers)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مدیریت اعتبار کیف پول، درصد تخفیف، تعداد مشتریان و مجوزهای دسترسی</p>
    </div>

    <button onclick="openNewResellerModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
        <i class="fa-solid fa-user-plus"></i>
        <span>ثبت نماینده جدید</span>
    </button>
</div>

<!-- Resellers Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                <tr>
                    <th class="p-3.5 font-semibold">شناسه / نام کاربری</th>
                    <th class="p-3.5 font-semibold">برند نماینده</th>
                    <th class="p-3.5 font-semibold">موجودی کیف پول</th>
                    <th class="p-3.5 font-semibold">درصد تخفیف</th>
                    <th class="p-3.5 font-semibold">تعداد مشتریان</th>
                    <th class="p-3.5 font-semibold">وضعیت</th>
                    <th class="p-3.5 font-semibold text-center">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
                <?php foreach ($resellers as $r): ?>
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5">
                            <span class="font-bold text-white font-mono"><?= htmlspecialchars($r['username']) ?></span>
                            <span class="text-[10px] text-slate-400 block mt-0.5"><?= htmlspecialchars($r['full_name'] ?? 'بی‌نام') ?></span>
                        </td>
                        <td class="p-3.5">
                            <span class="font-medium text-slate-200 block"><?= htmlspecialchars($r['brand_name']) ?></span>
                            <?php if (!empty($r['telegram_bot_username'])): ?>
                                <span class="text-[10px] text-cyan-400 font-mono flex items-center gap-1 mt-0.5">
                                    <i class="fa-brands fa-telegram"></i> @<?= htmlspecialchars($r['telegram_bot_username']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-slate-500">ربات متصل نیست</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3.5 font-bold text-emerald-400 font-mono"><?= Helpers::formatMoney($r['wallet_balance']) ?></td>
                        <td class="p-3.5 font-bold text-purple-400"><?= $r['discount_percent'] ?>%</td>
                        <td class="p-3.5 text-slate-300"><?= number_format($r['client_count']) ?> کلاینت</td>
                        <td class="p-3.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $r['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                <?= $r['status'] === 'active' ? 'فعال' : 'مسدود' ?>
                            </span>
                        </td>
                        <td class="p-3.5 text-center">
                            <button onclick="openAdjustModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>')" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-emerald-300 rounded-lg text-xs font-medium border border-slate-700 transition-colors">
                                شارژ / کسر اعتبار
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Adjust Balance -->
<div id="adjustModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeAdjustModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2">تغییر موجودی کیف پول</h3>
        <p class="text-slate-400 mb-4">نماینده: <span id="adjustUsername" class="font-bold text-purple-400 font-mono"></span></p>

        <form action="<?= Helpers::url('resellers/adjust') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="adjustUserId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">مبلغ تغییر (تومان) *</label>
                <input type="number" name="amount" required placeholder="مثبت برای شارژ، منفی برای کسر" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-400 mt-1 block">مثال: ۵۰۰۰۰۰ برای شارژ یا -۵۰۰۰۰ برای کسر</span>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توضیحات تراکنش</label>
                <input type="text" name="description" value="شارژ کیف پول توسط مدیر کل" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md">
                اعمال در کیف پول
            </button>
        </form>
    </div>
</div>

<!-- Modal: Add Reseller -->
<div id="newResellerModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeNewResellerModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4">افزودن نماینده فروش جدید</h3>

        <form action="<?= Helpers::url('resellers/store') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام کاربری *</label>
                    <input type="text" name="username" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رمز عبور *</label>
                    <input type="password" name="password" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام کامل / شرکت</label>
                    <input type="text" name="full_name" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">ایمیل</label>
                    <input type="email" name="email" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">شارژ اولیه (تومان)</label>
                    <input type="number" name="wallet_balance" value="0" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">درصد تخفیف همکاری (%)</label>
                    <input type="number" name="discount_percent" value="10" min="0" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md mt-2">
                ایجاد حساب نماینده
            </button>
        </form>
    </div>
</div>

<script>
    function openAdjustModal(id, username) {
        document.getElementById('adjustUserId').value = id;
        document.getElementById('adjustUsername').innerText = username;
        document.getElementById('adjustModal').classList.remove('hidden');
        document.getElementById('adjustModal').classList.add('flex');
    }
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.remove('flex');
        document.getElementById('adjustModal').classList.add('hidden');
    }
    function openNewResellerModal() {
        document.getElementById('newResellerModal').classList.remove('hidden');
        document.getElementById('newResellerModal').classList.add('flex');
    }
    function closeNewResellerModal() {
        document.getElementById('newResellerModal').classList.remove('flex');
        document.getElementById('newResellerModal').classList.add('hidden');
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

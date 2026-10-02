<?php
require __DIR__ . '/../layout/header.php';
$myBalance = (int)($currentReseller['wallet_balance'] ?? 0);
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-sitemap text-purple-400"></i>
            <span>مدیریت ساب‌نمایندگان و شبکه فروش چند سطحی</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">تعریف بازاریاب‌های زیرمجموعه، تخصیص اعتبار و دریافت خودکار پورسانت از هر خرید آن‌ها</p>
    </div>

    <div class="flex items-center gap-3">
        <div class="bg-slate-950/70 border border-slate-800 px-4 py-2 rounded-xl text-xs text-slate-300">
            <span>موجودی کیف پول شما:</span>
            <strong class="text-emerald-400 font-mono text-sm mr-1"><?= Helpers::formatMoney($myBalance) ?></strong>
        </div>
        <button onclick="openNewSubModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-xl transition shadow flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i>
            <span>تعریف ساب‌نماینده جدید</span>
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-xl shrink-0">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <span class="text-xs text-slate-400 block mb-0.5">تعداد ساب‌نمایندگان فعال</span>
            <span class="text-xl font-black text-white font-mono"><?= count($subResellers) ?></span>
        </div>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-xl shrink-0">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div>
            <span class="text-xs text-slate-400 block mb-0.5">مجموع کلاینت‌های زیرمجموعه</span>
            <span class="text-xl font-black text-white font-mono"><?= array_sum(array_column($subResellers, 'client_count')) ?></span>
        </div>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-600/20 text-amber-400 flex items-center justify-center text-xl shrink-0">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div>
            <span class="text-xs text-slate-400 block mb-0.5">فروش کل زیرمجموعه</span>
            <span class="text-lg font-black text-amber-300 font-mono"><?= Helpers::formatMoney(array_sum(array_column($subResellers, 'total_sales'))) ?></span>
        </div>
    </div>
</div>

<!-- Sub-Resellers Table Card -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
    <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
        <i class="fa-solid fa-list text-slate-400"></i>
        <span>لیست ساب‌نمایندگان و بازاریابان شما</span>
    </h3>

    <?php if (empty($subResellers)): ?>
        <div class="p-8 text-center bg-slate-950/40 border border-slate-800/80 rounded-2xl space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-500 mx-auto flex items-center justify-center text-xl">
                <i class="fa-solid fa-sitemap"></i>
            </div>
            <p class="text-xs text-slate-400">شما هنوز هیچ ساب‌نماینده‌ای تعریف نکرده‌اید. با فشردن دکمه «تعریف ساب‌نماینده جدید»، بازاریاب‌های خود را اضافه نمایید.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 font-medium">
                        <th class="py-3 px-3">شناسه / نام کاربری</th>
                        <th class="py-3 px-3">نام کامل</th>
                        <th class="py-3 px-3">موجودی کیف پول</th>
                        <th class="py-3 px-3">درصد پورسانت شما</th>
                        <th class="py-3 px-3">تعداد کلاینت‌ها</th>
                        <th class="py-3 px-3">کد معرف</th>
                        <th class="py-3 px-3 text-left">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    <?php foreach ($subResellers as $sub): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-3">
                                <span class="font-mono font-bold text-white">@<?= htmlspecialchars($sub['username']) ?></span>
                                <span class="text-[10px] text-slate-500 block font-mono">ID: #<?= $sub['id'] ?></span>
                            </td>
                            <td class="py-3 px-3 font-medium text-slate-200"><?= htmlspecialchars($sub['full_name'] ?: '-') ?></td>
                            <td class="py-3 px-3 font-mono font-bold text-emerald-400"><?= Helpers::formatMoney($sub['wallet_balance']) ?></td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-300 border border-purple-500/20 font-bold font-mono">
                                    <?= $sub['commission_percent'] ?>%
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-cyan-300"><?= $sub['client_count'] ?> کلاینت</td>
                            <td class="py-3 px-3 font-mono text-[11px] text-slate-400"><?= htmlspecialchars($sub['referral_code'] ?: '-') ?></td>
                            <td class="py-3 px-3 text-left">
                                <button onclick="openTransferModal(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')" class="px-2.5 py-1 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white rounded-lg text-xs font-semibold border border-emerald-500/30 transition flex items-center gap-1.5 ml-auto">
                                    <i class="fa-solid fa-money-bill-transfer"></i>
                                    <span>انتقال موجودی</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: New Sub-Reseller -->
<div id="newSubModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl relative space-y-4">
        <button onclick="closeNewSubModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white transition">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-purple-400"></i>
            <span>تعریف ساب‌نماینده و بازاریاب جدید</span>
        </h3>
        <p class="text-xs text-slate-400">برای بازاریاب یا فروشنده خود نام کاربری و رمز تعریف کنید تا بتواند با پنل اختصاصی خود کلاینت ایجاد کند.</p>

        <form action="<?= Helpers::url('reseller/sub-resellers/store') ?>" method="POST" class="space-y-3.5 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نام کامل بازاریاب</label>
                <input type="text" name="full_name" placeholder="مثلاً: علی رضایی" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام کاربری ورود *</label>
                    <input type="text" name="username" required dir="ltr" placeholder="ali_reseller" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">کلمه عبور ورود *</label>
                    <input type="password" name="password" required dir="ltr" placeholder="••••••••" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">اعتبار اولیه (تومان)</label>
                    <input type="number" name="initial_balance" value="0" min="0" step="10000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                    <span class="text-[10px] text-slate-500 mt-0.5 block">از کیف پول شما کسر می‌شود</span>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">درصد پورسانت شما</label>
                    <input type="number" name="commission_percent" value="10" min="0" max="50" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                    <span class="text-[10px] text-slate-500 mt-0.5 block">درصد سود از هر فروش ساب</span>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow mt-2">
                ثبت و ایجاد حساب ساب‌نماینده
            </button>
        </form>
    </div>
</div>

<!-- Modal: Transfer Credit -->
<div id="transferModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl relative space-y-4">
        <button onclick="closeTransferModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white transition">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-money-bill-transfer text-emerald-400"></i>
            <span>انتقال شارژ به ساب‌نماینده</span>
        </h3>

        <form action="<?= Helpers::url('reseller/sub-resellers/transfer') ?>" method="POST" class="space-y-3.5 text-xs">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="sub_id" id="transfer_sub_id">

            <div>
                <label class="block text-slate-400 mb-1">انتقال به حساب:</label>
                <div class="font-mono font-bold text-white p-2.5 bg-slate-800/80 rounded-xl border border-slate-700" id="transfer_sub_name">-</div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">مبلغ انتقال (تومان) *</label>
                <input type="number" name="amount" required min="10000" step="10000" placeholder="مثلاً: 200000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-400 mt-1 block">موجودی فعلی شما: <?= Helpers::formatMoney($myBalance) ?></span>
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow mt-2">
                انتقال آنی به کیف پول
            </button>
        </form>
    </div>
</div>

<script>
function openNewSubModal() {
    document.getElementById('newSubModal').classList.remove('hidden');
    document.getElementById('newSubModal').classList.add('flex');
}
function closeNewSubModal() {
    document.getElementById('newSubModal').classList.remove('flex');
    document.getElementById('newSubModal').classList.add('hidden');
}

function openTransferModal(subId, subName) {
    document.getElementById('transfer_sub_id').value = subId;
    document.getElementById('transfer_sub_name').innerText = '@' + subName;
    document.getElementById('transferModal').classList.remove('hidden');
    document.getElementById('transferModal').classList.add('flex');
}
function closeTransferModal() {
    document.getElementById('transferModal').classList.remove('flex');
    document.getElementById('transferModal').classList.add('hidden');
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

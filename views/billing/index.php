<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Wallet Recharge Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400"><i class="fa-solid fa-wallet text-lg"></i></span>
                <h3 class="font-bold text-base text-white">کیف پول پیش‌پرداخت</h3>
            </div>

            <div class="bg-gradient-to-br from-purple-900/60 to-slate-900 border border-purple-800/40 rounded-2xl p-5 my-4">
                <span class="text-xs text-purple-200 block mb-1">موجودی فعلی حساب:</span>
                <span class="text-2xl font-black text-white"><?= Helpers::formatMoney($user['wallet_balance']) ?></span>
                <?php 
                $tier = class_exists('Provisioner') 
                    ? Provisioner::getResellerTier($user['id']) 
                    : ['tier' => 'bronze', 'title' => 'برنزی', 'badge' => '🥉', 'discount' => (int)($user['discount_percent'] ?? 0)]; 
                ?>
                <span class="text-[10px] text-purple-300 block mt-2">تخفیف همکاری فعال: <?= $tier['discount'] ?>% (سطح <?= $tier['title'] ?> <?= $tier['badge'] ?>)</span>
            </div>

            <form action="<?= Helpers::url('billing/topup') ?>" method="POST" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">مبلغ شارژ (تومان):</label>
                    <input type="number" id="topupAmount" name="amount" min="50000" step="50000" value="200000" required oninput="calculateCrypto()"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-sm">
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">انتخاب درگاه پرداخت:</label>
                    <select name="gateway" id="gatewaySelect" onchange="toggleGatewayDetails()" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="zarinpal">زرین‌پال / شاپرک (کارت‌های بانکی)</option>
                        <option value="crypto">ارز دیجیتال (تتر USDT - شبکه TRC20)</option>
                        <option value="card">کارت به کارت مستقیم</option>
                    </select>
                </div>

                <!-- Crypto TRC20 Details Box -->
                <div id="cryptoDetails" class="hidden bg-slate-800/60 border border-cyan-800/40 rounded-xl p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-slate-400">معادل تتری پرداختی:</span>
                        <span id="cryptoUsdtCalc" class="font-bold font-mono text-cyan-300">-- USDT</span>
                    </div>
                    <div class="text-[10px] text-slate-400">
                        آدرس ولت تتر (TRC-20):
                        <div class="flex items-center gap-1.5 mt-1">
                            <input type="text" readonly id="tetherWalletInput" value="<?= htmlspecialchars($tetherWallet) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-1.5 text-[10px] font-mono text-slate-300" dir="ltr">
                            <button type="button" onclick="copyWallet()" class="px-2 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-[10px] text-white">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1">کد پیگیری تراکنش (TXID / Hash):</label>
                        <input type="text" name="txid" placeholder="مثلاً: 4a3b...c9e1" dir="ltr" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-1.5 text-[10px] font-mono text-white">
                    </div>
                </div>

                <!-- Card to Card Details Box -->
                <div id="cardDetails" class="hidden bg-slate-800/60 border border-purple-800/40 rounded-xl p-3.5 space-y-2">
                    <div class="text-[11px] text-slate-300 flex justify-between">
                        <span>شماره کارت مقصد:</span>
                        <span class="font-mono font-bold text-purple-300" dir="ltr"><?= htmlspecialchars($bankCard) ?></span>
                    </div>
                    <div class="text-[11px] text-slate-300 flex justify-between">
                        <span>به نام:</span>
                        <span class="font-bold text-white"><?= htmlspecialchars($bankCardOwner) ?></span>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/40 mt-2">
                    تأیید پرداخت و شارژ آنی موجودی
                </button>
            </form>
        </div>

        <?php if (Auth::isAdmin()): ?>
            <!-- Admin Gateway Settings Trigger -->
            <div class="mt-6 pt-4 border-t border-slate-800">
                <button onclick="toggleAdminGatewaySettings()" class="w-full py-2 bg-slate-800/60 hover:bg-slate-800 text-slate-300 rounded-xl text-[11px] font-medium border border-slate-700/60 flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-sliders text-xs text-purple-400"></i>
                    <span>تنظیمات درگاه‌ها و حساب‌های مالی (مخصوص مدیر)</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transactions History Table -->
    <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-sm text-white">گردش حساب و تراکنش‌های اخیر</h3>
                <p class="text-xs text-slate-400 mt-0.5">ثبت ریز کسر هزینه‌های صدور سرویس و شارژهای کیف پول</p>
            </div>
            <span class="text-xs text-slate-400 font-mono"><?= count($transactions) ?> تراکنش اخیر</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/40 text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">شناسه / تاریخ</th>
                        <th class="py-2.5 px-3 font-semibold">شرح تراکنش</th>
                        <th class="py-2.5 px-3 font-semibold">مبلغ</th>
                        <th class="py-2.5 px-3 font-semibold">موجودی پس از تراکنش</th>
                        <th class="py-2.5 px-3 font-semibold">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">هیچ تراکنشی ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr class="hover:bg-slate-800/20">
                                <td class="py-3 px-3">
                                    <span class="font-mono text-purple-300 font-bold block"><?= htmlspecialchars($t['reference_id'] ?? ('TX-' . $t['id'])) ?></span>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= $t['created_at'] ?></span>
                                </td>
                                <td class="py-3 px-3 text-slate-200"><?= htmlspecialchars($t['description'] ?? 'تراکنش سیستمی') ?></td>
                                <td class="py-3 px-3 font-mono font-bold <?= $t['amount'] > 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= $t['amount'] > 0 ? '+' : '' ?><?= Helpers::formatMoney($t['amount']) ?>
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-300"><?= Helpers::formatMoney($t['balance_after']) ?></td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $t['status'] === 'completed' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' ?>">
                                        <?= $t['status'] === 'completed' ? 'تکمیل‌شده' : 'در انتظار' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (Auth::isAdmin()): ?>
<!-- Admin Gateway Settings Modal -->
<div id="adminGatewayModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
        <button onclick="toggleAdminGatewaySettings()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-purple-400"></i>
            <span>تنظیمات درگاه‌ها و حساب‌های بانکی</span>
        </h3>

        <form action="<?= Helpers::url('billing/gateways') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">آدرس کیف پول تتر (TRC-20 Wallet Address):</label>
                <input type="text" name="tether_wallet" value="<?= htmlspecialchars($tetherWallet) ?>" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نرخ تبدیل تتر به تومان (USDT Exchange Rate):</label>
                <input type="number" name="usdt_rate" value="<?= $usdtRate ?>" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">شماره کارت بانکی:</label>
                    <input type="text" name="bank_card" value="<?= htmlspecialchars($bankCard) ?>" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام صاحب حساب:</label>
                    <input type="text" name="bank_card_owner" value="<?= htmlspecialchars($bankCardOwner) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره تنظیمات مالی
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const usdtRate = <?= $usdtRate ?>;

    function toggleGatewayDetails() {
        const gw = document.getElementById('gatewaySelect').value;
        const cryptoBox = document.getElementById('cryptoDetails');
        const cardBox = document.getElementById('cardDetails');

        if (gw === 'crypto') {
            cryptoBox.classList.remove('hidden');
            cardBox.classList.add('hidden');
            calculateCrypto();
        } else if (gw === 'card') {
            cardBox.classList.remove('hidden');
            cryptoBox.classList.add('hidden');
        } else {
            cryptoBox.classList.add('hidden');
            cardBox.classList.add('hidden');
        }
    }

    function calculateCrypto() {
        const amount = parseFloat(document.getElementById('topupAmount').value) || 0;
        const usdt = (amount / usdtRate).toFixed(2);
        const el = document.getElementById('cryptoUsdtCalc');
        if (el) el.innerText = usdt + ' USDT';
    }

    function copyWallet() {
        const copyText = document.getElementById('tetherWalletInput');
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert('آدرس ولت تتر در کلیپ‌بورد کپی شد: ' + copyText.value);
    }

    function toggleAdminGatewaySettings() {
        const m = document.getElementById('adminGatewayModal');
        if (m) {
            m.classList.toggle('hidden');
            m.classList.toggle('flex');
        }
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

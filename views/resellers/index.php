<?php
require __DIR__ . '/../layout/header.php';
$pendingCount = $pendingAppsCount ?? 0;
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-handshake text-indigo-400"></i>
            <span>مدیریت شبکه نمایندگان فروش (Resellers)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مدیریت اعتبار کیف پول، سقف بدهی، تخفیف همکاری، نظارت بر کلاینت‌ها و درخواست‌های نمایندگی</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="<?= Helpers::url('resellers/applications') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2 relative">
            <i class="fa-solid fa-user-clock text-amber-400"></i>
            <span>درخواست‌های جدید نمایندگی</span>
            <?php if ($pendingCount > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-slate-950 font-mono animate-pulse">
                    <?= $pendingCount ?>
                </span>
            <?php endif; ?>
        </a>
        <button onclick="openNewResellerModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i>
            <span>ثبت نماینده جدید</span>
        </button>
    </div>
</div>

<!-- Resellers Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                <tr>
                    <th class="p-3.5 font-semibold">شناسه / نام کاربری</th>
                    <th class="p-3.5 font-semibold">برند و ربات نماینده</th>
                    <th class="p-3.5 font-semibold">موجودی کیف پول</th>
                    <th class="p-3.5 font-semibold">سقف بدهی مجاز</th>
                    <th class="p-3.5 font-semibold">وضعیت تراز</th>
                    <th class="p-3.5 font-semibold">تخفیف</th>
                    <th class="p-3.5 font-semibold">مشتریان</th>
                    <th class="p-3.5 font-semibold">وضعیت</th>
                    <th class="p-3.5 font-semibold text-center">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
                <?php foreach ($resellers as $r): 
                    $balance = (int)$r['wallet_balance'];
                    $limit = (int)($r['credit_limit'] ?? 0);
                    $isDebt = ($balance < 0);
                    $tier = Provisioner::getResellerTier($r['id']);
                ?>
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="p-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white font-mono"><?= htmlspecialchars($r['username']) ?></span>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold 
                                    <?= match($tier['tier']) {
                                        'diamond' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
                                        'gold' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                                        'silver' => 'bg-slate-300/10 text-slate-300 border border-slate-400/30',
                                        default => 'bg-amber-800/10 text-amber-500 border border-amber-700/30'
                                    } ?>" title="رتبه پلکانی بر اساس تعداد کاربران فعال">
                                    <span><?= $tier['badge'] ?></span>
                                    <span>سطح <?= $tier['title'] ?></span>
                                </span>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-0.5"><?= htmlspecialchars($r['full_name'] ?? 'بی‌نام') ?></span>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <span class="font-medium text-slate-200 block"><?= htmlspecialchars($r['brand_name']) ?></span>
                            <?php if (!empty($r['telegram_bot_username'])): ?>
                                <a href="https://t.me/<?= htmlspecialchars($r['telegram_bot_username']) ?>" target="_blank" class="text-[10px] text-cyan-400 font-mono flex items-center gap-1 mt-0.5 hover:underline">
                                    <i class="fa-brands fa-telegram"></i> @<?= htmlspecialchars($r['telegram_bot_username']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-[10px] text-slate-500">ربات متصل نیست</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3.5 font-bold <?= $isDebt ? 'text-rose-400' : 'text-emerald-400' ?> font-mono whitespace-nowrap">
                            <?= Helpers::formatMoney($balance) ?>
                        </td>
                        <td class="p-3.5 font-mono text-purple-300 whitespace-nowrap">
                            <?= $limit > 0 ? Helpers::formatMoney($limit) : '<span class="text-slate-500">فقط نقدی</span>' ?>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <?php if ($isDebt): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    بدهکار (<?= Helpers::formatMoney(abs($balance)) ?>)
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    تراز مثبت ✓
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <button onclick="openDiscountModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= (int)$tier['discount'] ?>)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 font-bold border border-purple-500/30 transition text-xs group" title="کلیک برای ویرایش درصد تخفیف (موثر: <?= $tier['discount'] ?>٪)">
                                <span><?= $tier['discount'] ?>%</span>
                                <i class="fa-solid fa-pen text-[9px] text-purple-400 group-hover:scale-125 transition-transform"></i>
                            </button>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <a href="<?= Helpers::url('resellers/clients?id=' . $r['id']) ?>" class="text-cyan-400 hover:underline font-medium flex items-center gap-1">
                                <span><?= number_format($r['client_count']) ?> کلاینت</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $r['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                <?= $r['status'] === 'active' ? 'فعال' : 'مسدود' ?>
                            </span>
                        </td>
                        <td class="p-3.5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="openAdjustModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>')" class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center justify-center text-xs" title="شارژ یا کسر موجودی کیف پول">
                                    <i class="fa-solid fa-wallet"></i>
                                </button>
                                <button onclick="openCreditLimitModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= $limit ?>)" class="w-8 h-8 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 transition flex items-center justify-center text-xs" title="تنظیم سقف بدهی و اعتبار مجاز">
                                    <i class="fa-solid fa-scale-balanced"></i>
                                </button>
                                <a href="<?= Helpers::url('resellers/clients?id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 transition flex items-center justify-center text-xs" title="مشاهده و نظارت بر کاربران این نماینده">
                                    <i class="fa-solid fa-users"></i>
                                </a>
                                <button onclick="copyResellerDetails('<?= htmlspecialchars($r['username']) ?>', '<?= htmlspecialchars($r['brand_name']) ?>')" class="w-8 h-8 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 transition flex items-center justify-center text-xs" title="کپی پیام آماده حاوی آدرس پنل و مشخصات برای ارسال به نماینده">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                            </div>
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

<!-- Modal: Set Credit Limit (سقف اعتبار بدهی) -->
<div id="creditLimitModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-purple-500/40 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeCreditLimitModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-1.5">
            <i class="fa-solid fa-scale-balanced text-purple-400"></i>
            <span>تنظیم سقف اعتبار بدهی (Debt Limit)</span>
        </h3>
        <p class="text-slate-400 mb-4">نماینده: <span id="creditLimitUsername" class="font-bold text-purple-400 font-mono"></span></p>

        <form action="<?= Helpers::url('resellers/set-credit-limit') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="creditLimitUserId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">حداکثر سقف بدهی مجاز (تومان):</label>
                <input type="number" name="credit_limit" id="creditLimitInput" min="0" step="100000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-400 mt-1 block">مقدار ۰ یعنی فقط در صورت داشتن شارژ نقدی مجاز به ایجاد اکانت است. مقادیر بالاتر به نماینده اجازه می‌دهد تا آن سقف بدهکار شود.</span>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-md">
                ذخیره سقف اعتبار
            </button>
        </form>
    </div>
</div>

<!-- Modal: Edit Reseller Discount (%) -->
<div id="discountModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-amber-500/40 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeDiscountModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-1.5">
            <i class="fa-solid fa-percent text-amber-400"></i>
            <span>ویرایش درصد تخفیف نماینده</span>
        </h3>
        <p class="text-slate-400 mb-4">نماینده: <span id="discountUsername" class="font-bold text-amber-400 font-mono"></span></p>

        <form action="<?= Helpers::url('resellers/update-discount') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="discountUserId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">درصد تخفیف همکاری (۰ الی ۱۰۰٪):</label>
                <div class="relative">
                    <input type="number" name="discount_percent" id="discountPercentInput" min="0" max="100" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center text-lg font-bold">
                    <span class="absolute left-3 top-3 text-slate-400 font-bold">%</span>
                </div>
                <span class="text-[10px] text-slate-400 mt-1 block">این درصد به صورت خودکار هنگام صدور اشتراک توسط نماینده یا در خریدهای ربات تلگرام اختصاصی او از قیمت پایه کسر می‌گردد.</span>
            </div>

            <div>
                <label class="block text-slate-400 mb-1.5 font-medium text-[11px]">انتخاب سریع تخفیف‌های متداول:</label>
                <div class="grid grid-cols-4 gap-1.5">
                    <button type="button" onclick="setQuickDiscount(0)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-slate-300 border border-slate-700">۰٪ (عادی)</button>
                    <button type="button" onclick="setQuickDiscount(10)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۱۰٪</button>
                    <button type="button" onclick="setQuickDiscount(15)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۱۵٪</button>
                    <button type="button" onclick="setQuickDiscount(20)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۲۰٪</button>
                    <button type="button" onclick="setQuickDiscount(25)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۲۵٪</button>
                    <button type="button" onclick="setQuickDiscount(30)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۳۰٪</button>
                    <button type="button" onclick="setQuickDiscount(40)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۴۰٪</button>
                    <button type="button" onclick="setQuickDiscount(50)" class="py-1 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-[10px] text-amber-300 border border-slate-700">۵۰٪</button>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow-md">
                ذخیره تغییرات درصد تخفیف
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
                    <input type="number" name="discount_percent" value="15" min="0" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
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
function openCreditLimitModal(id, username, limit) {
    document.getElementById('creditLimitUserId').value = id;
    document.getElementById('creditLimitUsername').innerText = username;
    document.getElementById('creditLimitInput').value = limit || 0;
    document.getElementById('creditLimitModal').classList.remove('hidden');
    document.getElementById('creditLimitModal').classList.add('flex');
}
function closeCreditLimitModal() {
    document.getElementById('creditLimitModal').classList.remove('flex');
    document.getElementById('creditLimitModal').classList.add('hidden');
}

function openDiscountModal(id, username, discount) {
    document.getElementById('discountUserId').value = id;
    document.getElementById('discountUsername').innerText = username;
    document.getElementById('discountPercentInput').value = discount || 0;
    document.getElementById('discountModal').classList.remove('hidden');
    document.getElementById('discountModal').classList.add('flex');
}
function closeDiscountModal() {
    document.getElementById('discountModal').classList.remove('flex');
    document.getElementById('discountModal').classList.add('hidden');
}
function setQuickDiscount(percent) {
    document.getElementById('discountPercentInput').value = percent;
}

function openNewResellerModal() {
    document.getElementById('newResellerModal').classList.remove('hidden');
    document.getElementById('newResellerModal').classList.add('flex');
}
function closeNewResellerModal() {
    document.getElementById('newResellerModal').classList.remove('flex');
    document.getElementById('newResellerModal').classList.add('hidden');
}

function copyResellerDetails(username, brandName) {
    const loginUrl = '<?= Helpers::fullUrl('login') ?>';
    const text = `🌟 اطلاعات پنل نمایندگی شما (${brandName}):\n\n` +
                 `🌐 آدرس ورود به پنل:\n${loginUrl}\n\n` +
                 `👤 نام کاربری:\n${username}\n\n` +
                 `💡 برای اتصال ربات تلگرام اختصاصی و اطلاعات حساب، پس از ورود به بخش «ربات تلگرام و فروش» مراجعه فرمایید.`;
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ پیام آماده با مشخصات کامل پنل کپی شد! می‌توانید آن را مستقیماً در تلگرام یا واتساپ برای نماینده ارسال نمایید.');
    }).catch(err => {
        alert('خطا در کپی: ' + err);
    });
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

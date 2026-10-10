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
        <a href="<?= Helpers::url('resellers/invoice') ?>" class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 text-xs font-semibold rounded-xl border border-cyan-500/30 transition flex items-center gap-1.5" title="مشاهده، چاپ و گزارش فاکتور و ریز صورت‌حساب ماهانه نمایندگان">
            <i class="fa-solid fa-file-invoice-dollar text-cyan-400"></i>
            <span>فاکتور ماهانه</span>
        </a>
        <a href="<?= Helpers::url('resellers/export-financial') ?>" class="px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-semibold rounded-xl border border-emerald-500/30 transition flex items-center gap-1.5" title="دانلود گزارش کامل مالی و ترافیک نمایندگان در قالب اکسل">
            <i class="fa-solid fa-file-excel text-emerald-400"></i>
            <span>خروجی مالی (Excel)</span>
        </a>
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
                    <th class="p-3.5 font-semibold">پلن اختصاصی</th>
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
                    $tier = class_exists('Provisioner') 
                        ? Provisioner::getResellerTier($r['id']) 
                        : ['tier' => 'bronze', 'title' => 'برنزی', 'badge' => '🥉', 'discount' => (int)($r['discount_percent'] ?? 0)];
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
                            <button onclick="openDiscountModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= (int)$r['discount_percent'] ?>, <?= (int)($r['auto_tier_enabled'] ?? 1) ?>)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 font-bold border border-purple-500/30 transition text-xs group" title="کلیک برای ویرایش درصد تخفیف (تخفیف پایه: <?= $r['discount_percent'] ?>٪ / موثر: <?= $tier['discount'] ?>٪)">
                                <span><?= $tier['discount'] ?>%</span>
                                <i class="fa-solid fa-pen text-[9px] text-purple-400 group-hover:scale-125 transition-transform"></i>
                            </button>
                        </td>
                        <td class="p-3.5 whitespace-nowrap">
                            <?php
                                $allowCustom = (int)($r['allow_custom_plans'] ?? 1);
                                $allowPrice = (int)($r['allow_price_edit'] ?? 1);
                                $customCount = (int)($r['custom_plans_count'] ?? 0);
                                $maxCustom = (int)($r['max_custom_plans'] ?? 10);
                            ?>
                            <button onclick="openCustomPermsModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= $allowCustom ?>, <?= $allowPrice ?>, <?= $maxCustom ?>, <?= (int)($r['custom_plan_approval_required'] ?? 0) ?>, '<?= htmlspecialchars($r['allowed_servers'] ?? '', ENT_QUOTES) ?>')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg <?= $allowCustom ? 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-700/50 hover:bg-slate-700 text-slate-400 border border-slate-600' ?> font-bold transition text-xs" title="مدیریت دسترسی پلن اختصاصی - <?= $customCount ?>/<?= $maxCustom ?> ساخته شده">
                                <i class="fa-solid fa-star text-[10px] <?= $allowCustom ? 'text-amber-400' : 'text-slate-500' ?>"></i>
                                <span><?= $allowCustom ? 'فعال' : 'غیرفعال' ?> (<?= $customCount ?>/<?= $maxCustom ?>)</span>
                                <i class="fa-solid fa-pen text-[9px] opacity-60"></i>
                            </button>
                            <?php if ($allowPrice): ?>
                                <span class="inline-flex ml-1 px-1.5 py-0.5 rounded text-[9px] bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">قیمت✓</span>
                            <?php endif; ?>
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
                                <a href="<?= Helpers::url('resellers/app-config?id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-violet-500/10 hover:bg-violet-500/20 text-violet-300 border border-violet-500/30 transition flex items-center justify-center text-xs" title="تنظیمات اپ نماینده - کلید API و حالت مدیریتی (v4.0.49)">
                                    <i class="fa-solid fa-mobile-screen"></i>
                                </a>
                                <a href="<?= Helpers::url('resellers/permissions?reseller_id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 transition flex items-center justify-center text-xs" title="سطح دسترسی و منوها (Permissions)">
                                    <i class="fa-solid fa-user-shield"></i>
                                </a>
                                <button onclick="openAdjustModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>')" class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition flex items-center justify-center text-xs" title="شارژ یا کسر موجودی کیف پول">
                                    <i class="fa-solid fa-wallet"></i>
                                </button>
                                <button onclick="openCreditLimitModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= $limit ?>)" class="w-8 h-8 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 transition flex items-center justify-center text-xs" title="تنظیم سقف بدهی و اعتبار مجاز">
                                    <i class="fa-solid fa-scale-balanced"></i>
                                </button>
                                <a href="<?= Helpers::url('resellers/invoice?id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition flex items-center justify-center text-xs" title="مشاهده، صدور و چاپ فاکتور ماهانه نماینده">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </a>
                                <button onclick="openResetPwdModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', '<?= htmlspecialchars($r['panel_password_display'] ?? '') ?>')" class="w-8 h-8 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 transition flex items-center justify-center text-xs" title="تغییر یا مشاهده کلمه عبور پنل این نماینده">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <a href="<?= Helpers::url('resellers/clients?id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 transition flex items-center justify-center text-xs" title="مشاهده و نظارت بر کاربران این نماینده">
                                    <i class="fa-solid fa-users"></i>
                                </a>
                                <button onclick="copyResellerDetails('<?= htmlspecialchars($r['username']) ?>', '<?= htmlspecialchars($r['brand_name']) ?>', '<?= htmlspecialchars($r['panel_password_display'] ?? '') ?>')" class="w-8 h-8 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 transition flex items-center justify-center text-xs" title="کپی پیام آماده حاوی آدرس پنل و مشخصات برای ارسال به نماینده">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                                <a href="<?= Helpers::url('resellers/backup?id=' . $r['id']) ?>" class="w-8 h-8 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 transition flex items-center justify-center text-xs" title="دانلود و ارسال بکاپ این نماینده به تاپیک تلگرام">
                                    <i class="fa-solid fa-cloud-arrow-down"></i>
                                </a>
                                <button onclick="openDeleteResellerModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['username']) ?>', <?= (int)$r['client_count'] ?>, '<?= Helpers::formatMoney($r['wallet_balance']) ?>')" class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition flex items-center justify-center text-xs" title="حذف حساب این نماینده">
                                    <i class="fa-solid fa-trash"></i>
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

            <!-- Auto Tiering Switch -->
            <div class="p-3 bg-slate-800/60 rounded-xl border border-slate-700/60 flex items-center justify-between">
                <div>
                    <strong class="text-white block font-medium">سیستم ارتقای خودکار پلکانی (Auto-Tiering)</strong>
                    <span class="text-[10px] text-slate-400">در صورت فعال بودن، با افزایش تعداد کاربران نماینده درصد تخفیف او به سطوح نقره‌ای (۲۵٪)، طلایی (۳۵٪) یا الماس (۴۵٪) ارتقا می‌یابد.</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="auto_tier_enabled" id="discountAutoTier" value="1" class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                </label>
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

function openDiscountModal(id, username, discount, autoTier) {
    document.getElementById('discountUserId').value = id;
    document.getElementById('discountUsername').innerText = username;
    document.getElementById('discountPercentInput').value = discount || 0;
    document.getElementById('discountAutoTier').checked = (parseInt(autoTier) === 1);
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

function copyResellerDetails(username, brandName, password) {
    const loginUrl = '<?= Helpers::fullUrl('login') ?>';
    let text = `🌟 اطلاعات پنل نمایندگی شما (${brandName}):\n\n` +
                 `🌐 آدرس ورود به پنل:\n${loginUrl}\n\n` +
                 `👤 نام کاربری:\n${username}\n\n`;
    if (password && password.length > 0) {
        text += `🔑 کلمه عبور:\n${password}\n\n`;
    }
    text += `💡 برای اتصال ربات تلگرام اختصاصی و اطلاعات حساب، پس از ورود به بخش «ربات تلگرام و فروش» مراجعه فرمایید.`;
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ پیام آماده با مشخصات کامل پنل کپی شد! می‌توانید آن را مستقیماً در تلگرام یا واتساپ برای نماینده ارسال نمایید.');
    }).catch(err => {
        alert('خطا در کپی: ' + err);
    });
}

function openResetPwdModal(userId, username, currentPwd) {
    document.getElementById('resetPwdUserId').value = userId;
    document.getElementById('resetPwdUsername').innerText = username;
    var box = document.getElementById('currentPwdBox');
    var val = document.getElementById('currentPwdVal');
    if (currentPwd && currentPwd.length > 0) {
        val.innerText = currentPwd;
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
    document.getElementById('resetPwdInput').value = '';
    document.getElementById('resetPwdModal').classList.remove('hidden');
    document.getElementById('resetPwdModal').classList.add('flex');
}

function closeResetPwdModal() {
    document.getElementById('resetPwdModal').classList.remove('flex');
    document.getElementById('resetPwdModal').classList.add('hidden');
}

function generateRandomPwd() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%';
    let pwd = '';
    for (let i = 0; i < 10; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('resetPwdInput').value = pwd;
}

function openDeleteResellerModal(userId, username, clientCount, balance) {
    document.getElementById('deleteResellerUserId').value = userId;
    document.getElementById('deleteResellerUsername').innerText = username;
    document.getElementById('deleteResellerClients').innerText = clientCount + ' کلاینت';
    document.getElementById('deleteResellerBalance').innerText = balance;
    document.getElementById('deleteResellerModal').classList.remove('hidden');
    document.getElementById('deleteResellerModal').classList.add('flex');
}

function closeDeleteResellerModal() {
    document.getElementById('deleteResellerModal').classList.remove('flex');
    document.getElementById('deleteResellerModal').classList.add('hidden');
}

function openCustomPermsModal(id, username, allowCustom, allowPrice, maxCustom, approvalRequired, allowedServersJson) {
    document.getElementById('customPermsUserId').value = id;
    document.getElementById('customPermsUsername').innerText = username;
    document.getElementById('customPermsAllowCustom').checked = (allowCustom == 1);
    document.getElementById('customPermsAllowPrice').checked = (allowPrice == 1);
    document.getElementById('customPermsMax').value = maxCustom || 10;
    document.getElementById('customPermsApproval').checked = (approvalRequired == 1);
    // allowed servers
    let allowed = [];
    try { allowed = JSON.parse(allowedServersJson || '[]'); } catch(e){ allowed = []; }
    document.querySelectorAll('.customPermsServerCheck').forEach(cb => {
        cb.checked = allowed.length === 0 ? false : allowed.includes(parseInt(cb.value));
    });
    document.getElementById('customPermsModal').classList.remove('hidden');
    document.getElementById('customPermsModal').classList.add('flex');
}
function closeCustomPermsModal() {
    document.getElementById('customPermsModal').classList.remove('flex');
    document.getElementById('customPermsModal').classList.add('hidden');
}
</script>

<!-- Modal: Custom Plan Permissions (Full Control Panel) -->
<div id="customPermsModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-emerald-500/30 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeCustomPermsModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2"><i class="fa-solid fa-star text-amber-400"></i> کنترل پلن اختصاصی نماینده</h3>
        <p class="text-slate-400 mb-4">نماینده: <span id="customPermsUsername" class="font-bold text-emerald-400 font-mono"></span></p>
        <form action="<?= Helpers::url('resellers/update-custom-perms') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="customPermsUserId">
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 p-3 bg-slate-800/60 rounded-xl border border-slate-700 cursor-pointer">
                    <input type="checkbox" name="allow_custom_plans" id="customPermsAllowCustom" value="1" class="rounded">
                    <div><span class="block text-white font-bold">اجازه ساخت پلن اختصاصی</span><span class="text-[10px] text-slate-400">نماینده می‌تواند محصول خودش را بسازد</span></div>
                </label>
                <label class="flex items-center gap-2 p-3 bg-slate-800/60 rounded-xl border border-slate-700 cursor-pointer">
                    <input type="checkbox" name="allow_price_edit" id="customPermsAllowPrice" value="1" class="rounded">
                    <div><span class="block text-white font-bold">اجازه ویرایش قیمت پایه</span><span class="text-[10px] text-slate-400">قیمت فروش پلن‌های اصلی را تغییر دهد</span></div>
                </label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">حداکثر تعداد پلن اختصاصی</label><input type="number" name="max_custom_plans" id="customPermsMax" min="0" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center font-mono"></div>
                <label class="flex items-center gap-2 p-3 bg-slate-800/60 rounded-xl border border-slate-700 cursor-pointer mt-5">
                    <input type="checkbox" name="custom_plan_approval_required" id="customPermsApproval" value="1" class="rounded">
                    <span class="text-white font-bold">نیاز به تایید ادمین</span>
                </label>
            </div>
            <div>
                <label class="block text-slate-300 mb-2 font-semibold">سرورهای مجاز برای ساخت پلن اختصاصی (خالی = همه سرورها)</label>
                <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-3 max-h-32 overflow-y-auto space-y-1.5">
                    <?php
                    try {
                        $allServers = Database::getConnection()->query("SELECT id, name, driver FROM server_nodes WHERE is_active = 1 OR driver='connectix_seller' ORDER BY name ASC")->fetchAll();
                    } catch(Throwable $e){ $allServers = []; }
                    foreach ($allServers as $sv):
                    ?>
                        <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-700/40 p-1 rounded">
                            <input type="checkbox" name="allowed_servers[]" value="<?= $sv['id'] ?>" class="customPermsServerCheck rounded">
                            <span class="text-slate-200"><?= htmlspecialchars($sv['name']) ?></span><span class="text-[10px] text-slate-500">(<?= $sv['driver'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="text-[10px] text-slate-500">اگر هیچکدام را انتخاب نکنید، نماینده می‌تواند از همه سرورها استفاده کند. اگر فقط VIP را انتخاب کنید، فقط از VIP می‌تواند پلن بسازد.</span>
            </div>
            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl">ذخیره دسترسی‌های پلن</button>
        </form>
    </div>
</div>

<!-- Modal: Delete Reseller -->
<div id="deleteResellerModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl relative text-xs space-y-4">
        <button onclick="closeDeleteResellerModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 text-rose-400">
            <div class="w-10 h-10 rounded-2xl bg-rose-500/20 flex items-center justify-center text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white">حذف حساب نماینده فروش</h3>
                <span class="text-slate-400 text-[11px]">این اقدام غیرقابل بازگشت است</span>
            </div>
        </div>

        <p class="text-slate-300">
            آیا از حذف حساب نماینده <strong id="deleteResellerUsername" class="text-white font-mono"></strong> اطمینان دارید؟
        </p>

        <div class="p-3 bg-slate-800/80 rounded-xl space-y-1.5 border border-slate-700/80">
            <div class="flex justify-between text-slate-400">
                <span>تعداد کاربران فعال این نماینده:</span>
                <strong id="deleteResellerClients" class="text-white font-mono">۰</strong>
            </div>
            <div class="flex justify-between text-slate-400">
                <span>تراز مالی فعلی:</span>
                <strong id="deleteResellerBalance" class="text-emerald-400 font-mono">۰ تومان</strong>
            </div>
        </div>

        <form action="<?= Helpers::url('resellers/delete') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="deleteResellerUserId">

            <div>
                <label class="block text-slate-300 mb-2 font-semibold">تکلیف کاربران این نماینده چه شود؟</label>
                <div class="space-y-2">
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-slate-800/50 border border-slate-700 cursor-pointer hover:border-purple-500 transition">
                        <input type="radio" name="client_action" value="reassign" checked class="mt-0.5 text-purple-600 focus:ring-purple-500">
                        <div>
                            <strong class="text-white block font-medium">انتقال کاربران به مدیریت کل (پیشنهادی)</strong>
                            <span class="text-slate-400 text-[11px]">مشتریان این نماینده به حساب مدیر کل منتقل شده و اتصال و اشتراک آن‌ها قطع نمی‌شود.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-slate-800/50 border border-slate-700 cursor-pointer hover:border-rose-500 transition">
                        <input type="radio" name="client_action" value="delete" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                        <div>
                            <strong class="text-rose-300 block font-medium">حذف کامل کاربران از سرورها و دیتابیس</strong>
                            <span class="text-slate-400 text-[11px]">تمامی کانفیگ‌های کاربران این نماینده از روی نودها و پنل پاک خواهند شد.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeDeleteResellerModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition">
                    انصراف
                </button>
                <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl transition shadow flex items-center gap-1.5">
                    <i class="fa-solid fa-trash"></i>
                    <span>تأیید و حذف نماینده</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="resetPwdModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeResetPwdModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
            <i class="fa-solid fa-key text-amber-400"></i>
            <span>تغییر کلمه عبور نماینده</span>
        </h3>
        <p class="text-slate-400 mb-4">نماینده: <span id="resetPwdUsername" class="font-bold text-white font-mono"></span></p>

        <div id="currentPwdBox" class="p-3 bg-slate-800/80 rounded-xl mb-4 border border-slate-700/80 hidden">
            <span class="text-[11px] text-slate-400 block mb-1">آخرین کلمه عبور ثبت‌شده در سیستم:</span>
            <span id="currentPwdVal" class="font-mono text-sm font-bold text-emerald-400 select-all"></span>
        </div>

        <form action="<?= Helpers::url('resellers/reset-password') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_id" id="resetPwdUserId">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">کلمه عبور جدید (حداقل ۶ کاراکتر) *</label>
                <div class="flex gap-2">
                    <input type="text" name="new_password" id="resetPwdInput" required minlength="6" placeholder="مثلاً: Pass@123" class="flex-1 bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center">
                    <button type="button" onclick="generateRandomPwd()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl border border-slate-700 text-xs font-semibold" title="تولید خودکار رمز تصادفی">
                        <i class="fa-solid fa-shuffle"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl shadow-md mt-2">
                ذخیره کلمه عبور جدید
            </button>
        </form>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

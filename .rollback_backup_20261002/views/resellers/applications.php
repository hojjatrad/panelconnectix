<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-user-clock text-amber-400"></i>
            <span>درخواست‌های اخذ پنل نمایندگی (Reseller Applications)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">بررسی، تایید و صدور آنی حساب نمایندگی برای کاربرانی که از طریق ربات تلگرام درخواست ثبت کرده‌اند</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-right"></i>
            <span>بازگشت به فهرست نمایندگان</span>
        </a>
    </div>
</div>

<!-- Filter Tabs -->
<div class="flex items-center gap-2 border-b border-slate-800 pb-3 text-xs mb-6">
    <a href="<?= Helpers::url('resellers/applications') ?>" class="px-3.5 py-1.5 rounded-xl font-medium transition <?= ($status ?? 'all') === 'all' ? 'bg-purple-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
        همه درخواست‌ها (<?= count($applications) ?>)
    </a>
    <a href="<?= Helpers::url('resellers/applications?status=pending') ?>" class="px-3.5 py-1.5 rounded-xl font-medium transition <?= ($status ?? '') === 'pending' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
        در انتظار تایید (<?= $pendingCount ?>)
    </a>
    <a href="<?= Helpers::url('resellers/applications?status=approved') ?>" class="px-3.5 py-1.5 rounded-xl font-medium transition <?= ($status ?? '') === 'approved' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
        تایید شده
    </a>
    <a href="<?= Helpers::url('resellers/applications?status=rejected') ?>" class="px-3.5 py-1.5 rounded-xl font-medium transition <?= ($status ?? '') === 'rejected' ? 'bg-rose-600 text-white font-bold' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
        رد شده
    </a>
</div>

<!-- Applications Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <?php if (empty($applications)): ?>
        <div class="p-12 text-center text-slate-400 space-y-3">
            <i class="fa-solid fa-inbox text-4xl text-slate-600 block"></i>
            <p class="text-sm">در حال حاضر هیچ درخواستی در این بخش ثبت نشده است.</p>
            <span class="text-xs text-slate-500">کاربران می‌توانند از منوی ربات تلگرام با لمس دکمه «🤝 درخواست نمایندگی» اقدام کنند.</span>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 font-semibold">ردیف</th>
                        <th class="p-3.5 font-semibold">برند / نام متقاضی</th>
                        <th class="p-3.5 font-semibold">اطلاعات تلگرام</th>
                        <th class="p-3.5 font-semibold">اطلاعات تماس</th>
                        <th class="p-3.5 font-semibold">نام کاربری دلخواه</th>
                        <th class="p-3.5 font-semibold">تخمین فروش</th>
                        <th class="p-3.5 font-semibold">وضعیت</th>
                        <th class="p-3.5 font-semibold">تاریخ ثبت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php foreach ($applications as $idx => $app): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="p-3.5 font-bold text-white">
                                <?= htmlspecialchars($app['brand_name']) ?>
                                <span class="text-[10px] text-slate-400 block font-normal mt-0.5"><?= htmlspecialchars($app['user_tg_name'] ?? 'بی‌نام') ?></span>
                            </td>
                            <td class="p-3.5">
                                <?php if (!empty($app['user_tg_username'])): ?>
                                    <a href="https://t.me/<?= htmlspecialchars($app['user_tg_username']) ?>" target="_blank" class="text-cyan-400 hover:underline font-mono flex items-center gap-1">
                                        <i class="fa-brands fa-telegram"></i> @<?= htmlspecialchars($app['user_tg_username']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 font-mono">ID: <?= htmlspecialchars($app['user_tg_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 font-mono text-slate-300"><?= htmlspecialchars($app['contact_info']) ?></td>
                            <td class="p-3.5 font-mono font-bold text-purple-300"><?= htmlspecialchars($app['preferred_username']) ?></td>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($app['estimated_sales']) ?></td>
                            <td class="p-3.5">
                                <?php if ($app['status'] === 'approved'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        تایید شده ✓
                                    </span>
                                <?php elseif ($app['status'] === 'rejected'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        رد شده ✕
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        در انتظار بررسی
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-slate-400 font-mono text-[11px]"><?= $app['created_at'] ?></td>
                            <td class="p-3.5 text-center">
                                <?php if ($app['status'] === 'pending'): ?>
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="openApproveModal(<?= htmlspecialchars(json_encode($app)) ?>)" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold transition flex items-center gap-1 shadow-sm">
                                            <i class="fa-solid fa-check"></i>
                                            <span>تایید و صدور</span>
                                        </button>
                                        <button onclick="openRejectModal(<?= $app['id'] ?>)" class="px-2.5 py-1.5 bg-rose-600/80 hover:bg-rose-600 text-white rounded-lg font-bold transition flex items-center gap-1 shadow-sm">
                                            <i class="fa-solid fa-xmark"></i>
                                            <span>رد</span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500">تعیین تکلیف شده</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Approve Application -->
<div id="approveModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-emerald-500/40 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeApproveModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
            <span>تایید و صدور آنی حساب نمایندگی</span>
        </h3>
        <p class="text-slate-400 mb-4">اطلاعات ورود پس از تایید مستقیماً به تلگرام متقاضی ارسال خواهد شد.</p>

        <form action="<?= Helpers::url('resellers/applications/approve') ?>" method="POST" class="space-y-3.5">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="app_id" id="approveAppId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نام کاربری اختصاصی پنل *</label>
                <input type="text" name="custom_username" id="approveUsername" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">رمز عبور اولیه (خالی = تولید خودکار)</label>
                <input type="text" name="custom_password" placeholder="مثال: res_123456" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">درصد تخفیف همکاری (%)</label>
                    <input type="number" name="discount_percent" value="15" min="0" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سقف اعتبار بدهی (تومان)</label>
                    <input type="number" name="credit_limit" value="0" step="50000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">شارژ هدیه / اولیه کیف پول (تومان)</label>
                <input type="number" name="wallet_balance" value="0" step="10000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg shadow-emerald-900/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-rocket"></i>
                    <span>تایید، ایجاد حساب و ارسال مشخصات به تلگرام</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reject Application -->
<div id="rejectModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-rose-500/40 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeRejectModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 text-rose-400">رد درخواست نمایندگی</h3>
        <p class="text-slate-400 mb-4">پیام رد درخواست به تلگرام متقاضی ارسال خواهد شد.</p>

        <form action="<?= Helpers::url('resellers/applications/reject') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="app_id" id="rejectAppId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">علت رد درخواست (اختیاری):</label>
                <input type="text" name="reason" value="عدم احراز شرایط همکاری در حال حاضر" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow-md">
                تایید رد درخواست
            </button>
        </form>
    </div>
</div>

<script>
function openApproveModal(app) {
    document.getElementById('approveAppId').value = app.id;
    document.getElementById('approveUsername').value = app.preferred_username;
    document.getElementById('approveModal').classList.remove('hidden');
    document.getElementById('approveModal').classList.add('flex');
}
function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('flex');
    document.getElementById('approveModal').classList.add('hidden');
}
function openRejectModal(id) {
    document.getElementById('rejectAppId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('flex');
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

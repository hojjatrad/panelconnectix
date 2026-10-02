<?php
$pageTitle = 'مدیریت کدهای تخفیف ربات';
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-ticket text-amber-400"></i>
                <span>سامانه کدهای تخفیف و کوپن‌های مناسبتی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">تعریف کدهای تخفیف درصدی جهت استفاده خریداران در هنگام پرداخت در ربات تلگرام</p>
        </div>

        <button onclick="openNewCouponModal()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>تعریف کد تخفیف جدید</span>
        </button>
    </div>

    <!-- Coupons Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <?php if (empty($coupons)): ?>
            <div class="p-12 text-center text-slate-400 space-y-2">
                <i class="fa-solid fa-ticket text-4xl text-slate-600 block"></i>
                <p class="text-sm">هنوز هیچ کد تخفیفی ایجاد نشده است.</p>
                <span class="text-xs text-slate-500">با ایجاد کد تخفیف، مشتریان می‌توانند در فاکتور ربات آن را وارد و تخفیف آنی دریافت کنند.</span>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                        <tr>
                            <th class="p-3.5 font-semibold">کد تخفیف</th>
                            <th class="p-3.5 font-semibold">درصد تخفیف</th>
                            <th class="p-3.5 font-semibold">تعداد استفاده</th>
                            <th class="p-3.5 font-semibold">سقف مجاز</th>
                            <th class="p-3.5 font-semibold">تاریخ انقضا</th>
                            <th class="p-3.5 font-semibold">وضعیت</th>
                            <th class="p-3.5 font-semibold text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70">
                        <?php foreach ($coupons as $c): 
                            $isExpired = !empty($c['expire_at']) && strtotime($c['expire_at']) < time();
                            $isMaxed = $c['used_count'] >= $c['max_uses'];
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-3.5 font-mono font-bold text-amber-300 tracking-wider">
                                    <span class="px-2 py-1 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                        <?= htmlspecialchars($c['code']) ?>
                                    </span>
                                </td>
                                <td class="p-3.5 font-bold text-white font-mono"><?= $c['discount_percent'] ?>%</td>
                                <td class="p-3.5 font-mono text-purple-300"><?= number_format($c['used_count']) ?> بار</td>
                                <td class="p-3.5 font-mono text-slate-400"><?= number_format($c['max_uses']) ?> بار</td>
                                <td class="p-3.5 font-mono text-slate-400">
                                    <?= !empty($c['expire_at']) ? substr($c['expire_at'], 0, 10) : 'نامحدود' ?>
                                </td>
                                <td class="p-3.5">
                                    <?php if ($isExpired): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">منقضی شده</span>
                                    <?php elseif ($isMaxed): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">تکمیل ظرفیت</span>
                                    <?php elseif ($c['is_active']): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">فعال</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-500 border border-slate-700">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <form action="<?= Helpers::url('settings/coupons/toggle') ?>" method="POST" class="m-0">
                                            <?= Helpers::csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700 transition">
                                                <?= $c['is_active'] ? 'غیرفعال' : 'فعال' ?>
                                            </button>
                                        </form>
                                        <form action="<?= Helpers::url('settings/coupons/delete') ?>" method="POST" class="m-0" onsubmit="return confirm('آیا از حذف این کد تخفیف مطمئنید؟')">
                                            <?= Helpers::csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="p-1 bg-slate-800 hover:bg-rose-900/40 text-rose-400 rounded-lg border border-slate-700 transition">
                                                <i class="fa-solid fa-trash text-[10px]"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Coupon -->
<div id="newCouponModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeNewCouponModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-ticket text-amber-400"></i>
            <span>تعریف کد تخفیف جدید</span>
        </h3>

        <form action="<?= Helpers::url('settings/coupons/store') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">کد تخفیف (حروف انگلیسی یا عدد) *</label>
                <input type="text" name="code" required placeholder="مثال: OFF20 یا NOROOZ" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono uppercase text-center text-sm font-bold tracking-widest">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">درصد تخفیف *</label>
                    <div class="relative">
                        <input type="number" name="discount_percent" value="20" min="1" max="100" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center font-bold">
                        <span class="absolute left-3 top-2.5 text-slate-400 font-bold">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">سقف تعداد استفاده</label>
                    <input type="number" name="max_uses" value="50" min="1" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">تاریخ انقضا (اختیاری)</label>
                <input type="date" name="expire_at" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-500 mt-1 block">در صورت خالی گذاشتن، کد تخفیف انقضای زمانی نخواهد داشت.</span>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ایجاد کد تخفیف
            </button>
        </form>
    </div>
</div>

<script>
    function openNewCouponModal() {
        document.getElementById('newCouponModal').classList.remove('hidden');
        document.getElementById('newCouponModal').classList.add('flex');
    }
    function closeNewCouponModal() {
        document.getElementById('newCouponModal').classList.remove('flex');
        document.getElementById('newCouponModal').classList.add('hidden');
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

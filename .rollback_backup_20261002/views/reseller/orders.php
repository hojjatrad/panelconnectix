<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-cart-shopping text-cyan-400"></i>
                <span>مدیریت و تایید سفارشات ربات من</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">مشاهده فیش‌های واریزی کارت‌به‌کارت مشتریان، تایید رسید و صدور آنی اکانت</p>
        </div>

        <div class="flex items-center gap-2 bg-slate-800/80 border border-slate-700/80 px-4 py-2.5 rounded-xl text-xs">
            <span class="text-slate-400">موجودی کیف پول شما:</span>
            <span class="font-mono font-bold text-emerald-400 text-sm"><?= Helpers::formatMoney($resellerInfo['wallet_balance']) ?></span>
            <a href="<?= Helpers::url('billing') ?>" class="text-[11px] text-purple-300 hover:text-white underline mr-2">افزایش موجودی</a>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-2 text-xs">
        <a href="<?= Helpers::url('reseller/orders?status=all') ?>" class="px-3.5 py-2 rounded-xl transition font-medium <?= ($status === 'all') ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            همه سفارشات
        </a>
        <a href="<?= Helpers::url('reseller/orders?status=pending_approval') ?>" class="px-3.5 py-2 rounded-xl transition font-medium flex items-center gap-1.5 <?= ($status === 'pending_approval') ? 'bg-amber-600 text-white' : 'text-amber-400 hover:bg-slate-800' ?>">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
            <span>منتظر تایید فیش</span>
        </a>
        <a href="<?= Helpers::url('reseller/orders?status=paid') ?>" class="px-3.5 py-2 rounded-xl transition font-medium <?= ($status === 'paid') ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            تایید شده و فعال
        </a>
        <a href="<?= Helpers::url('reseller/orders?status=rejected') ?>" class="px-3.5 py-2 rounded-xl transition font-medium <?= ($status === 'rejected') ? 'bg-rose-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            رد شده
        </a>
    </div>

    <!-- Orders Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 font-semibold">کد پیگیری / زمان</th>
                        <th class="p-3.5 font-semibold">مشتری تلگرام</th>
                        <th class="p-3.5 font-semibold">پلن سفارش</th>
                        <th class="p-3.5 font-semibold">مبلغ دریافتی شما</th>
                        <th class="p-3.5 font-semibold">وضعیت پرداخت</th>
                        <th class="p-3.5 font-semibold">رسید پرداخت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات تایید</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">
                                <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                                هیچ سفارشی در این وضعیت یافت نشد.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($orders as $o): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5">
                                <span class="font-mono font-bold text-white block">#<?= htmlspecialchars($o['order_code']) ?></span>
                                <span class="text-[10px] text-slate-400"><?= $o['created_at'] ?></span>
                            </td>

                            <td class="p-3.5">
                                <span class="font-medium text-slate-200 block"><?= htmlspecialchars($o['user_tg_name'] ?? 'کاربر تلگرام') ?></span>
                                <span class="text-[10px] text-cyan-400 font-mono">
                                    <?= !empty($o['user_tg_username']) ? '@' . htmlspecialchars($o['user_tg_username']) : 'ID: ' . $o['user_tg_id'] ?>
                                </span>
                            </td>

                            <td class="p-3.5">
                                <span class="font-bold text-white block"><?= htmlspecialchars($o['custom_title'] ?: $o['base_plan_title']) ?></span>
                                <?php
                                $tr = (float)$o['traffic_gb'];
                                $trTxt = ($tr > 0 && $tr < 1) ? round($tr * 1024) . 'MB' : (($tr == (int)$tr ? (int)$tr : $tr) . 'GB');
                                ?>
                                <span class="text-[10px] text-slate-400"><?= $trTxt ?> | <?= $o['duration_days'] ?> روزه</span>
                            </td>

                            <td class="p-3.5">
                                <span class="font-mono font-bold text-emerald-400 text-xs"><?= number_format($o['amount']) ?></span>
                                <span class="text-[10px] text-slate-400">تومان</span>
                            </td>

                            <td class="p-3.5">
                                <?php
                                $badgeClass = match($o['payment_status']) {
                                    'paid' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'pending_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'pending_receipt' => 'bg-slate-700/30 text-slate-400 border-slate-700',
                                    'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    default => 'bg-slate-800 text-slate-400'
                                };
                                $statusFa = match($o['payment_status']) {
                                    'paid' => '✅ تایید و تحویل شده',
                                    'pending_approval' => '⏳ در انتظار تایید فیش',
                                    'pending_receipt' => 'در انتظار پرداخت',
                                    'rejected' => '❌ رد شده',
                                    default => $o['payment_status']
                                };
                                ?>
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-semibold border <?= $badgeClass ?>">
                                    <?= $statusFa ?>
                                </span>
                            </td>

                            <td class="p-3.5">
                                <?php if (!empty($o['receipt_photo_id'])): ?>
                                    <span class="text-[11px] text-cyan-400 cursor-pointer font-medium" onclick="alert('فایل رسید در تلگرام برای شما ارسال گردیده است. FileID: <?= $o['receipt_photo_id'] ?>')">
                                        <i class="fa-solid fa-image ml-1"></i> فیش واریزی
                                    </span>
                                <?php elseif (!empty($o['receipt_note'])): ?>
                                    <span class="text-[10px] text-slate-300 font-mono bg-slate-800 p-1.5 rounded block max-w-xs truncate" title="<?= htmlspecialchars($o['receipt_note']) ?>">
                                        <?= htmlspecialchars($o['receipt_note']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500">ارسال نشده</span>
                                <?php endif; ?>
                            </td>

                            <td class="p-3.5 text-center">
                                <?php if ($o['payment_status'] === 'pending_approval'): ?>
                                    <div class="flex items-center justify-center gap-2">
                                        <form action="<?= Helpers::url('reseller/orders/approve') ?>" method="POST" onsubmit="return confirm('آیا از تایید این سفارش و تحویل خودکار اکانت به مشتری اطمینان دارید؟');">
                                            <?= Helpers::csrfField() ?>
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs transition shadow flex items-center gap-1">
                                                <i class="fa-solid fa-check"></i>
                                                <span>تایید و صدور اکانت</span>
                                            </button>
                                        </form>

                                        <form action="<?= Helpers::url('reseller/orders/reject') ?>" method="POST" onsubmit="return confirm('آیا از رد این سفارش اطمینان دارید؟');">
                                            <?= Helpers::csrfField() ?>
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 bg-rose-600/80 hover:bg-rose-700 text-white font-bold rounded-lg text-xs transition">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($o['payment_status'] === 'paid' && !empty($o['client_username'])): ?>
                                    <span class="text-[11px] text-emerald-400 font-mono font-bold">
                                        کلاینت: <?= htmlspecialchars($o['client_username']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[10px]">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

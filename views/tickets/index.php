<?php
$pageTitle = 'پشتیبانی و تیکت‌ها';
require __DIR__ . '/../layout/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-headset text-purple-400"></i>
                <span>سامانه تیکتینگ و پشتیبانی درون‌پنلی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">ارتباط مستقیم، پیگیری مشکلات فنی نودها، درخواست‌های مالی و پشتیبانی نمایندگان</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= Helpers::url('tickets/create') ?>" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition shadow flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>ارسال تیکت جدید</span>
            </a>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <?php if (empty($tickets)): ?>
            <div class="p-12 text-center text-slate-500 space-y-2">
                <i class="fa-solid fa-inbox text-3xl opacity-40"></i>
                <p class="text-xs">هیچ تیکت پشتیبانی در این بخش ثبت نشده است.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                        <tr>
                            <th class="p-3.5 font-semibold">شناسه</th>
                            <th class="p-3.5 font-semibold">موضوع تیکت</th>
                            <?php if (Auth::isAdmin()): ?>
                                <th class="p-3.5 font-semibold">نماینده / کاربر</th>
                            <?php endif; ?>
                            <th class="p-3.5 font-semibold">دپارتمان</th>
                            <th class="p-3.5 font-semibold">اولویت</th>
                            <th class="p-3.5 font-semibold">وضعیت</th>
                            <th class="p-3.5 font-semibold">آخرین به‌روزرسانی</th>
                            <th class="p-3.5 font-semibold text-center">اقدام</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($tickets as $t): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-3.5 font-mono text-purple-300 font-bold">#<?= $t['id'] ?></td>
                                <td class="p-3.5">
                                    <a href="<?= Helpers::url('tickets/show?id=' . $t['id']) ?>" class="font-bold text-white hover:text-purple-400 transition">
                                        <?= htmlspecialchars($t['subject']) ?>
                                    </a>
                                    <span class="text-[10px] text-slate-500 block mt-0.5"><?= $t['msg_count'] ?> پیام</span>
                                </td>
                                <?php if (Auth::isAdmin()): ?>
                                    <td class="p-3.5">
                                        <div class="font-bold text-slate-200"><?= htmlspecialchars($t['brand_name'] ?: $t['username']) ?></div>
                                        <span class="text-[10px] text-slate-400 font-mono">@<?= htmlspecialchars($t['username']) ?></span>
                                    </td>
                                <?php endif; ?>
                                <td class="p-3.5 text-slate-300"><?= htmlspecialchars($t['department']) ?></td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= match($t['priority']) {
                                        'urgent' => 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
                                        'high' => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                                        default => 'bg-slate-800 text-slate-400'
                                    } ?>">
                                        <?= match($t['priority']) {
                                            'urgent' => 'فوری',
                                            'high' => 'بالا',
                                            'low' => 'کم',
                                            default => 'متوسط'
                                        } ?>
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?= match($t['status']) {
                                        'open' => 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse',
                                        'answered' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                                        'waiting_reseller' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20',
                                        'closed' => 'bg-slate-800 text-slate-500',
                                        default => 'bg-slate-800 text-slate-300'
                                    } ?>">
                                        <?= match($t['status']) {
                                            'open' => 'در انتظار پاسخ',
                                            'answered' => 'پاسخ داده شده',
                                            'waiting_reseller' => 'پاسخ نماینده',
                                            'closed' => 'بسته شده',
                                            default => $t['status']
                                        } ?>
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono text-[11px] text-slate-400">
                                    <?= substr($t['updated_at'], 0, 16) ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <a href="<?= Helpers::url('tickets/show?id=' . $t['id']) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-purple-600 hover:text-white text-purple-300 rounded-lg text-xs font-semibold transition border border-slate-700">
                                        مشاهده و پاسخ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

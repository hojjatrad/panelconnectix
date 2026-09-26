<?php
$pageTitle = 'لاگ هوش مصنوعی';
require __DIR__ . '/../layout/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-simple text-amber-400"></i>
                <span>لاگ و داشبورد هوش مصنوعی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">همه فراخوانی‌ها، مصرف سهمیه روزانه و نتیجه هر تیکت</p>
        </div>
        <a href="<?= Helpers::url('settings/ai') ?>" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-right"></i> تنظیمات AI
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">کل فراخوانی‌ها</div>
            <div class="text-lg font-bold text-white font-mono"><?= $stats['total_calls'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">پاسخ خودکار</div>
            <div class="text-lg font-bold text-emerald-400 font-mono"><?= $stats['auto_replied'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">پیش‌نویس (در انتظار: <?= $stats['drafts_pending'] ?>)</div>
            <div class="text-lg font-bold text-amber-400 font-mono"><?= $stats['drafts_total'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">خطا / بدون پاسخ</div>
            <div class="text-lg font-bold <?= $stats['failed'] > 0 ? 'text-rose-400' : 'text-white' ?> font-mono"><?= $stats['failed'] ?></div>
        </div>
    </div>

    <!-- Quota -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
        <h3 class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-solid fa-gauge-high text-violet-400"></i> سهمیه رایگان امروز (سقف: <?= $stats['quota_cap'] ?>)</h3>
        <?php foreach ($stats['quota_today'] as $p => $v): ?>
            <div>
                <div class="flex items-center justify-between text-[11px] mb-1">
                    <span class="text-slate-300 font-bold"><?= htmlspecialchars(ucfirst($p)) ?></span>
                    <span class="text-slate-500 font-mono"><?= $v ?> / <?= $stats['quota_cap'] ?></span>
                </div>
                <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full <?= $v >= $stats['quota_cap'] ? 'bg-rose-500' : ($v >= (int)($stats['quota_cap'] * 0.7) ? 'bg-amber-500' : 'bg-emerald-500') ?>"
                         style="width: <?= min(100, (int)round($v / max(1, $stats['quota_cap']) * 100)) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent logs -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <table class="w-full text-right">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-950/50 text-[10px] text-slate-500">
                    <th class="px-4 py-3 font-bold">تاریخ</th>
                    <th class="px-4 py-3 font-bold">تیکت</th>
                    <th class="px-4 py-3 font-bold">نتیجه</th>
                    <th class="px-4 py-3 font-bold">دسته</th>
                    <th class="px-4 py-3 font-bold">Provider / مدل</th>
                    <th class="px-4 py-3 font-bold">اطمینان</th>
                    <th class="px-4 py-3 font-bold">زمان</th>
                    <th class="px-4 py-3 font-bold">پاسخ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $l): ?>
                <tr class="border-b border-slate-800/60 hover:bg-slate-950/40 transition align-top">
                    <td class="px-4 py-3 text-[10px] text-slate-500 font-mono whitespace-nowrap"><?= substr((string)$l['created_at'], 0, 16) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($l['ticket_id']): ?>
                            <a href="<?= Helpers::url('tickets/show?id=' . (int)$l['ticket_id']) ?>" class="text-[11px] text-violet-400 hover:underline font-mono">#<?= (int)$l['ticket_id'] ?></a>
                            <div class="text-[10px] text-slate-500 max-w-[160px] line-clamp-1"><?= htmlspecialchars((string)($l['subject'] ?? '')) ?></div>
                        <?php else: ?><span class="text-[10px] text-slate-600">—</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php $st = $l['status']; ?>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?=
                            $st === 'auto_replied' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                            ($st === 'draft' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                            'bg-rose-500/10 text-rose-400 border border-rose-500/20') ?>">
                            <?= $st === 'auto_replied' ? 'خودکار' : ($st === 'draft' ? 'پیش‌نویس' : 'خطا') ?>
                        </span>
                        <?php if ($l['stage'] === 'draft' && (int)$l['accepted'] === 0): ?>
                            <div class="mt-1 text-[9px] text-slate-500">در انتظار بررسی</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-[11px] text-slate-300"><?= htmlspecialchars((string)$l['category']) ?></td>
                    <td class="px-4 py-3 text-[10px] text-slate-400 font-mono"><?= htmlspecialchars((string)($l['provider'] ?: '—')) ?><br><?= htmlspecialchars(mb_substr((string)($l['model'] ?? ''), 0, 28)) ?></td>
                    <td class="px-4 py-3 text-[11px] text-slate-300 font-mono"><?= number_format((float)$l['confidence'] * 100, 0) ?>%</td>
                    <td class="px-4 py-3 text-[10px] text-slate-500 font-mono"><?= (int)$l['latency_ms'] ?>ms</td>
                    <td class="px-4 py-3 max-w-[280px]">
                        <?php if (!empty($l['answer'])): ?>
                            <details class="text-[10px]">
                                <summary class="cursor-pointer text-slate-400 hover:text-slate-200 line-clamp-2"><?= htmlspecialchars(mb_substr((string)$l['answer'], 0, 120)) ?>...</summary>
                                <div class="mt-2 bg-slate-950/60 border border-slate-800 rounded-lg p-2.5 text-slate-300 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars((string)$l['answer']) ?></div>
                            </details>
                        <?php elseif (!empty($l['error'])): ?>
                            <details class="text-[10px]">
                                <summary class="cursor-pointer text-rose-400/80 line-clamp-2"><?= htmlspecialchars(mb_substr((string)$l['error'], 0, 100)) ?></summary>
                                <div class="mt-2 bg-rose-950/20 border border-rose-900/40 rounded-lg p-2.5 text-rose-300/80 font-mono"><?= htmlspecialchars((string)$l['error']) ?></div>
                            </details>
                        <?php else: ?><span class="text-[10px] text-slate-600">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-xs text-slate-500">هنوز فراخوانی‌ای ثبت نشده. با فعال‌سازی AI و باز کردن اولین تیکت، لاگ‌ها اینجا ظاهر می‌شوند.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

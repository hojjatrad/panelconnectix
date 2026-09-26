<?php
$pageTitle = 'دستیار هوشمند';
require __DIR__ . '/../layout/header.php';
?>
<div class="max-w-3xl mx-auto space-y-6">
    <div class="bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-robot text-violet-400"></i>
            <span>دستیار هوشمند Connectix</span>
        </h2>
        <p class="text-xs text-slate-400 mt-2 leading-relaxed">
            با فعال‌شدن این سرویس، تیکت‌های پشتیبانی شما <b class="text-slate-200">بلافاصله و ۲۴ ساعته</b> توسط هوش مصنوعی پاسخ داده می‌شود
            (پاسخ‌های رایج از پایگاه دانش رسمی) و موضوعات پیچیده، سریع‌تر به پشتیبان انسانی می‌رسند.
        </p>
    </div>

    <?php if ($status === 'admin'): ?>
        <div class="bg-violet-950/30 border border-violet-800/40 rounded-2xl p-5 text-xs text-violet-200">
            شما مدیرکل هستید. مدیریت کامل سرویس (کلیدها، پایگاه دانش، فعال‌سازی نمایندگان) از بخش
            <a href="<?= Helpers::url('settings/ai') ?>" class="font-bold underline">تنظیمات ← دستیار هوش مصنوعی</a>
            انجام می‌شود.
        </div>

    <?php elseif ($status === 'active'): ?>
        <div class="bg-emerald-950/30 border border-emerald-800/50 rounded-2xl p-6 shadow-sm space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-xl"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="text-sm font-bold text-emerald-300">سرویس فعال است</div>
                    <div class="text-[11px] text-emerald-400/70 mt-0.5">
                        تا <b class="font-mono"><?= substr((string)$sub['expires_at'], 0, 16) ?></b> — <?= $daysLeft ?> روز باقی مانده
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-950/40 border border-slate-800 rounded-xl p-3">
                    <div class="text-[10px] text-slate-500 mb-1">پاسخ‌های AI تاکنون</div>
                    <div class="text-lg font-bold text-white font-mono"><?= $aiMsgCount ?></div>
                </div>
                <div class="bg-slate-950/40 border border-slate-800 rounded-xl p-3">
                    <div class="text-[10px] text-slate-500 mb-1">وضعیت پاسخ خودکار</div>
                    <div class="text-sm font-bold text-emerald-400 mt-1"><?= \AiService::autoReplyEnabled() ? 'فعال' : 'غیرفعال (پیش‌نویس برای مدیریت)' ?></div>
                </div>
            </div>
            <p class="text-[11px] text-emerald-400/60">برای تمدید، با مدیریت هماهنگ کنید یا درخواست تمدید را از بخش تیکت‌ها بفرستید.</p>
        </div>

    <?php else: ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center text-xl"><i class="fa-solid fa-lock"></i></div>
                <div>
                    <div class="text-sm font-bold text-white">سرویس هنوز برای شما فعال نیست</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        <?php if ($status === 'expired'): ?> دوره قبلی شما به پایان رسیده است. برای ادامه، تمدید کنید. <?php else: ?> هزینه ماهیانه: <b class="text-emerald-400 font-mono"><?= Helpers::formatMoney($price) ?></b> تومان <?php endif; ?>
                    </div>
                </div>
            </div>
            <form method="POST" action="<?= Helpers::url('reseller/ai/request') ?>" class="flex items-center justify-between gap-3 bg-slate-950/50 border border-slate-800 rounded-xl p-4">
                <div class="text-[11px] text-slate-400 leading-relaxed">
                    با ارسال درخواست، یک تیکت «درخواست خرید سرویس هوش مصنوعی» برای مدیریت ساخته می‌شود؛
                    پس از پرداخت و فعال‌سازی توسط مدیریت، سرویس بلافاصله شروع می‌شود (با تاریخ انقضا).
                </div>
                <button type="submit" class="shrink-0 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-900/30 flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>درخواست خرید</span>
                </button>
                <?= Helpers::csrfField() ?>
            </form>
        </div>
    <?php endif; ?>

    <!-- What you get -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm">
        <h3 class="text-xs font-bold text-white mb-3 flex items-center gap-2"><i class="fa-solid fa-list-check text-violet-400"></i> شامل این سرویس</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 text-[11px] text-slate-300">
            <div class="flex items-center gap-2 bg-slate-950/40 rounded-lg p-2.5"><i class="fa-solid fa-bolt text-amber-400"></i> پاسخ فوری (۱-۲ ثانیه) به تیکت‌های شما</div>
            <div class="flex items-center gap-2 bg-slate-950/40 rounded-lg p-2.5"><i class="fa-solid fa-clock text-emerald-400"></i> پوشش ۲۴ ساعته بدون انتظار</div>
            <div class="flex items-center gap-2 bg-slate-950/40 rounded-lg p-2.5"><i class="fa-solid fa-shield-halved text-sky-400"></i> موضوعات حساس به پشتیبان انسانی ارجاع می‌شوند</div>
            <div class="flex items-center gap-2 bg-slate-950/40 rounded-lg p-2.5"><i class="fa-solid fa-rotate-right text-violet-400"></i> تمدید آسان با یک تماس/تیکت</div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
$pageTitle = 'تیکت #' . $ticket['id'] . ': ' . htmlspecialchars($ticket['subject']);
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Ticket Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= Helpers::url('tickets') ?>" class="text-xs text-purple-400 hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-right"></i>
                    <span>تیکت‌ها</span>
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs font-mono text-purple-300 font-bold">#<?= $ticket['id'] ?></span>
            </div>
            <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($ticket['subject']) ?></h2>
            <div class="flex items-center gap-3 text-[11px] text-slate-400 mt-2 flex-wrap">
                <span>دپارتمان: <b class="text-slate-200"><?= htmlspecialchars($ticket['department']) ?></b></span>
                <span>•</span>
                <span>ارسال‌کننده: <b class="text-purple-300"><?= htmlspecialchars($ticket['brand_name'] ?: $ticket['username']) ?></b></span>
                <span>•</span>
                <span>تاریخ ایجاد: <b class="text-slate-300 font-mono"><?= substr($ticket['created_at'], 0, 16) ?></b></span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-bold <?= match($ticket['status']) {
                'open' => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                'answered' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                'waiting_reseller' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20',
                'closed' => 'bg-slate-800 text-slate-500',
                default => 'bg-slate-800 text-slate-300'
            } ?>">
                <?= match($ticket['status']) {
                    'open' => 'در انتظار پاسخ مدیریت',
                    'answered' => 'پاسخ داده شده',
                    'waiting_reseller' => 'در انتظار بررسی نماینده',
                    'closed' => 'بسته شده',
                    default => $ticket['status']
                } ?>
            </span>

            <?php if ($ticket['status'] !== 'closed'): ?>
                <form action="<?= Helpers::url('tickets/close') ?>" method="POST" class="m-0" onsubmit="return confirm('آیا از بستن این تیکت اطمینان دارید؟');">
                    <?= Helpers::csrfField() ?>
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-rose-900/40 text-rose-300 rounded-xl text-xs font-medium border border-slate-700 transition">
                        بستن تیکت
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- AI Draft Card (admin only) -->
    <?php if (!empty($aiDraft) && !empty($aiDraft['answer'])): ?>
        <div class="bg-violet-950/30 border border-violet-800/50 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-robot text-violet-400"></i>
                    <span class="text-xs font-bold text-violet-200">پیش‌نویس هوش مصنوعی</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-300 border border-violet-500/30 font-mono">
                        اطمینان <?= number_format((float)$aiDraft['confidence'] * 100, 0) ?>% — <?= htmlspecialchars((string)$aiDraft['category']) ?>
                    </span>
                </div>
                <span class="text-[10px] text-slate-500 font-mono"><?= substr((string)$aiDraft['created_at'], 0, 16) ?></span>
            </div>
            <div class="text-xs text-slate-200 leading-relaxed whitespace-pre-wrap bg-slate-950/50 border border-slate-800 rounded-xl p-4"><?= htmlspecialchars((string)$aiDraft['answer']) ?></div>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="flex items-center gap-2 justify-end">
                <form action="<?= Helpers::url('tickets/ai-draft/discard') ?>" method="POST" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <input type="hidden" name="log_id" value="<?= (int)$aiDraft['id'] ?>">
                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition">رد پیش‌نویس</button>
                </form>
                <form action="<?= Helpers::url('tickets/ai-draft/send') ?>" method="POST" class="m-0">
                    <?= Helpers::csrfField() ?>
                    <input type="hidden" name="log_id" value="<?= (int)$aiDraft['id'] ?>">
                    <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> تأیید و ارسال به نام من
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Message Thread -->
    <div class="space-y-4">
        <?php foreach ($messages as $msg):
            $isAiMsg = !empty($msg['is_ai']) || empty($msg['u_id']);
            $isAdminMsg = !$isAiMsg && ($msg['role'] === 'admin');
        ?>
            <div class="bg-slate-900/80 border <?= $isAiMsg ? 'border-emerald-800/60 bg-emerald-950/10' : ($isAdminMsg ? 'border-purple-800/60 bg-purple-950/10' : 'border-slate-800') ?> rounded-2xl p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl <?= $isAiMsg ? 'bg-emerald-600 text-white' : ($isAdminMsg ? 'bg-purple-600 text-white' : 'bg-slate-800 text-cyan-400') ?> flex items-center justify-center text-xs font-bold shrink-0">
                            <i class="fa-solid <?= $isAiMsg ? 'fa-robot' : ($isAdminMsg ? 'fa-shield-halved' : 'fa-user') ?>"></i>
                        </div>
                        <div>
                            <div class="font-bold text-xs text-white">
                                <?php if ($isAiMsg): ?>
                                    دستیار هوش مصنوعی
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 mr-1.5">🤖 پاسخ خودکار</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($msg['full_name'] ?: $msg['username']) ?>
                                    <?php if ($isAdminMsg): ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 mr-1.5">پشتیبان رسمی</span>
                                    <?php else: ?>
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-medium bg-slate-800 text-slate-400 mr-1.5">نماینده فروش</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="font-mono text-[11px] text-slate-500"><?= substr($msg['created_at'], 0, 16) ?></span>
                </div>

                <div class="text-xs text-slate-200 leading-relaxed whitespace-pre-wrap font-sans">
                    <?= htmlspecialchars($msg['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply Box -->
    <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-reply text-purple-400"></i>
                <span>ارسال پاسخ جدید</span>
            </h3>

            <form action="<?= Helpers::url('tickets/reply') ?>" method="POST" class="space-y-3 text-xs">
                <?= Helpers::csrfField() ?>
                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">

                <div>
                    <textarea name="message" rows="4" required placeholder="پاسخ خود را بنویسید..." 
                              class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs leading-relaxed focus:border-purple-500 focus:outline-none"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-purple-900/30 flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>ارسال پاسخ</span>
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="p-4 bg-slate-950/60 rounded-xl border border-slate-800 text-center text-xs text-slate-400">
            این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد. در صورت نیاز می‌توانید یک تیکت جدید باز کنید.
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

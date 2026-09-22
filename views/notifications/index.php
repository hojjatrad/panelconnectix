<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-bullhorn text-yellow-400"></i>
            <span>اعلان‌ها و اطلاعیه‌های سیستم</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده آخرین اخبار دیتاسنترها، تغییرات پروتکل‌ها و اطلاعیه‌های سراسری</p>
    </div>

    <?php if (Auth::isAdmin()): ?>
        <button onclick="openNewNoticeModal()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>ارسال اطلاعیه جدید</span>
        </button>
    <?php endif; ?>
</div>

<div class="space-y-4">
    <?php if (empty($notifications)): ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-8 text-center text-slate-500 text-xs">
            هیچ اطلاعیه‌ای در حال حاضر ثبت نشده است.
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                        <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($n['title']) ?></h3>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono"><?= $n['created_at'] ?></span>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: New Notice (Admin only) -->
<?php if (Auth::isAdmin()): ?>
<div id="newNoticeModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeNewNoticeModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4">ارسال پیام یا اطلاعیه سراسری</h3>

        <form action="<?= Helpers::url('notifications/store') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">عنوان اطلاعیه *</label>
                <input type="text" name="title" required placeholder="مثلاً: ارتقای هسته سرورهای آلمان" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">متن پیام *</label>
                <textarea name="message" rows="4" required placeholder="متن پیام خود را بنویسید..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></textarea>
            </div>

            <div class="flex items-center gap-2 p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                <input type="checkbox" name="send_telegram" value="1" id="sendTgCheck" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0">
                <label for="sendTgCheck" class="text-[11px] text-slate-300 cursor-pointer">
                    ارسال همگانی این پیام به تمام کاربران در ربات تلگرام 🤖
                </label>
            </div>

            <button type="submit" class="w-full py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white font-bold rounded-xl shadow-md mt-2">
                انتشار اطلاعیه
            </button>
        </form>
    </div>
</div>

<script>
    function openNewNoticeModal() {
        document.getElementById('newNoticeModal').classList.remove('hidden');
        document.getElementById('newNoticeModal').classList.add('flex');
    }
    function closeNewNoticeModal() {
        document.getElementById('newNoticeModal').classList.remove('flex');
        document.getElementById('newNoticeModal').classList.add('hidden');
    }
</script>
<?php endif; ?>

<?php
require __DIR__ . '/../layout/footer.php';
?>

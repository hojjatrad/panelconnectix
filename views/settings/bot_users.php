<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-users text-cyan-400"></i>
            <span>کاربران و اعضای ربات تلگرام (Bot Members & Audience)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده افرادی که ربات را استارت زده‌اند، ارسال پیام خصوصی و پیام همگانی (Broadcast)</p>
    </div>

    <div class="flex items-center gap-2">
        <button onclick="openBroadcastModal()" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl transition shadow flex items-center gap-2">
            <i class="fa-solid fa-bullhorn"></i>
            <span>ارسال پیام همگانی (Broadcast)</span>
        </button>
        <a href="<?= Helpers::url('settings/bot') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2">
            <i class="fa-brands fa-telegram text-cyan-400"></i>
            <span>تنظیمات ربات</span>
        </a>
    </div>
</div>

<!-- Stats Counter Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">مجموع اعضای ربات</span>
        <span class="text-xl font-bold text-white font-mono mt-1 block"><?= number_format($totalCount) ?> نفر</span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">فعال در ۲۴ ساعت گذشته</span>
        <span class="text-xl font-bold text-emerald-400 font-mono mt-1 block"><?= number_format($active24hCount) ?> نفر</span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">دارای نام کاربری (@)</span>
        <span class="text-xl font-bold text-cyan-400 font-mono mt-1 block"><?= number_format($hasUsernameCount) ?> نفر</span>
    </div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4">
        <span class="text-slate-400 text-xs block">تعداد سفارشات ثبت‌شده</span>
        <span class="text-xl font-bold text-amber-400 font-mono mt-1 block"><?= number_format($totalOrdersCount) ?> سفارش</span>
    </div>
</div>

<!-- Search Bar -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 mb-4">
    <form action="<?= Helpers::url('settings/bot-users') ?>" method="GET" class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute right-3.5 top-3 text-slate-500"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="جستجو بر اساس نام، یوزرنیم (@) یا شناسه عددی..." 
                   class="w-full bg-slate-800 border border-slate-700 rounded-xl pr-9 pl-4 py-2 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-cyan-500">
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 transition">
                فیلتر
            </button>
            <?php if (!empty($_GET['q'])): ?>
                <a href="<?= Helpers::url('settings/bot-users') ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-rose-400 font-semibold rounded-xl border border-slate-700 transition">
                    پاکسازی جستجو
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <?php if (empty($users)): ?>
        <div class="p-12 text-center text-slate-400 space-y-2">
            <i class="fa-brands fa-telegram text-4xl text-slate-600 block"></i>
            <p class="text-sm">هنوز کاربری ربات را استارت نکرده است.</p>
            <span class="text-xs text-slate-500">به محض ارسال دستور /start توسط هر فرد در تلگرام، مشخصات او در این جدول ذخیره خواهد شد.</span>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 font-semibold">ردیف</th>
                        <th class="p-3.5 font-semibold">نام در تلگرام</th>
                        <th class="p-3.5 font-semibold">یوزرنیم (@)</th>
                        <th class="p-3.5 font-semibold">شناسه عددی (Chat ID)</th>
                        <th class="p-3.5 font-semibold">زیرمجموعه‌ها و پاداش</th>
                        <th class="p-3.5 font-semibold">اولین عضویت</th>
                        <th class="p-3.5 font-semibold">آخرین فعالیت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php foreach ($users as $idx => $u): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="p-3.5 font-bold text-white">
                                <?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'کاربر تلگرام') ?>
                            </td>
                            <td class="p-3.5 font-mono">
                                <?php if (!empty($u['username'])): ?>
                                    <a href="https://t.me/<?= htmlspecialchars($u['username']) ?>" target="_blank" class="text-cyan-400 hover:underline flex items-center gap-1">
                                        <i class="fa-brands fa-telegram"></i> @<?= htmlspecialchars($u['username']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[11px]">بدون آیدی عمومی</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 font-mono text-purple-300 select-all"><?= htmlspecialchars($u['tg_id']) ?></td>
                            <td class="p-3.5">
                                <span class="font-bold text-cyan-300 font-mono"><?= number_format($u['referral_count'] ?? 0) ?> نفر</span>
                                <?php if (!empty($u['referral_balance'])): ?>
                                    <span class="text-[10px] text-emerald-400 block font-mono"><?= Helpers::formatMoney($u['referral_balance']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 font-mono text-slate-400 text-[11px]"><?= $u['created_at'] ?></td>
                            <td class="p-3.5 font-mono text-slate-300 text-[11px]"><?= $u['last_active_at'] ?></td>
                            <td class="p-3.5 text-center">
                                <button onclick="openDirectMsgModal('<?= htmlspecialchars($u['tg_id']) ?>', '<?= htmlspecialchars(addslashes(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))) ?>')" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-300 rounded-lg text-xs font-medium border border-slate-700 transition flex items-center gap-1.5 mx-auto shadow-sm">
                                    <i class="fa-regular fa-paper-plane"></i>
                                    <span>ارسال پیام</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Direct Message to a specific User -->
<div id="directMsgModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeDirectMsgModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i class="fa-regular fa-paper-plane text-cyan-400"></i>
            <span>ارسال پیام مستقیم به کاربر تلگرام</span>
        </h3>
        <p class="text-slate-400 mb-4">گیرنده: <span id="msgReceiverName" class="font-bold text-white"></span> (ID: <code id="msgReceiverIdText" class="text-cyan-300 font-mono"></code>)</p>

        <form action="<?= Helpers::url('settings/bot-users/send-msg') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="user_tg_id" id="msgReceiverId" value="">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">متن پیام تلگرام (پشتیبانی از HTML):</label>
                <textarea name="message" rows="4" required placeholder="پیام خود را اینجا بنویسید..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white font-bold rounded-xl shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>ارسال پیام از طریق ربات</span>
            </button>
        </form>
    </div>
</div>

<!-- Modal: Broadcast Message to ALL Users -->
<div id="broadcastModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-purple-500/40 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative text-xs">
        <button onclick="closeBroadcastModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i class="fa-solid fa-bullhorn text-purple-400"></i>
            <span>ارسال پیام همگانی به تمام اعضای ربات (Broadcast)</span>
        </h3>
        <p class="text-slate-400 mb-4">این پیام برای تمام <b class="text-purple-300"><?= number_format($totalCount) ?></b> کاربر استارت‌کننده ربات ارسال خواهد شد.</p>

        <form action="<?= Helpers::url('settings/bot-broadcast') ?>" method="POST" class="space-y-4" onsubmit="return confirm('آیا از ارسال این پیام به تمام اعضای ربات مطمئن هستید؟');">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">متن پیام همگانی:</label>
                <textarea name="message" rows="5" required placeholder="اطلاعیه، تخفیف، اخبار سرورها و..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs"></textarea>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-purple-900/40 flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>شروع ارسال همگانی</span>
            </button>
        </form>
    </div>
</div>

<script>
function openDirectMsgModal(tgId, name) {
    document.getElementById('msgReceiverId').value = tgId;
    document.getElementById('msgReceiverIdText').innerText = tgId;
    document.getElementById('msgReceiverName').innerText = name;
    document.getElementById('directMsgModal').classList.remove('hidden');
    document.getElementById('directMsgModal').classList.add('flex');
}
function closeDirectMsgModal() {
    document.getElementById('directMsgModal').classList.remove('flex');
    document.getElementById('directMsgModal').classList.add('hidden');
}
function openBroadcastModal() {
    document.getElementById('broadcastModal').classList.remove('hidden');
    document.getElementById('broadcastModal').classList.add('flex');
}
function closeBroadcastModal() {
    document.getElementById('broadcastModal').classList.remove('flex');
    document.getElementById('broadcastModal').classList.add('hidden');
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-shield-halved text-amber-400"></i>
            <span>لاگ‌های امنیتی و سوابق وقایع (Activity & Audit Logs)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">ردگیری دقیق تمامی رویدادها، ورود و خروج‌ها، ساخت کلاینت‌ها و تغییرات سیستمی</p>
    </div>

    <?php if (Auth::isAdmin()): ?>
        <form method="POST" action="<?= Helpers::url('logs/clear') ?>" onsubmit="return confirm('آیا از پاکسازی تمام لاگ‌های ثبت‌شده مطمئن هستید؟ این عمل غیرقابل بازگشت است.');">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-4 py-2 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-trash-can"></i>
                <span>پاکسازی تاریخچه لاگ‌ها</span>
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Search & Filter Bar -->
<form method="GET" action="<?= Helpers::url('logs') ?>" class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-900/50 p-4 rounded-xl border border-slate-800/80 text-xs">
    <div>
        <label class="block text-slate-400 mb-1">جستجو در توضیحات / IP / کاربر:</label>
        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="مثلاً: 192.168.1.1 یا نام کاربری..." 
               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
    </div>

    <div>
        <label class="block text-slate-400 mb-1">نوع رویداد (Action):</label>
        <select name="action" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500 font-mono">
            <option value="">همه رویدادها</option>
            <?php foreach ($distinctActions as $act): ?>
                <option value="<?= $act ?>" <?= ($_GET['action'] ?? '') === $act ? 'selected' : '' ?>><?= $act ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="flex items-end gap-2">
        <button type="submit" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium flex items-center justify-center gap-1.5 shadow">
            <i class="fa-solid fa-search"></i>
            <span>فیلتر لاگ‌ها</span>
        </button>
        <a href="<?= Helpers::url('logs') ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 rounded-lg border border-slate-700 transition-colors" title="پاکسازی">
            <i class="fa-solid fa-rotate-right"></i>
        </a>
    </div>
</form>

<!-- Logs Table -->
<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                <tr>
                    <th class="p-3.5 font-semibold">شناسه</th>
                    <th class="p-3.5 font-semibold">کاربر / نقش</th>
                    <th class="p-3.5 font-semibold">نوع رویداد</th>
                    <th class="p-3.5 font-semibold">شرح کامل واقعه</th>
                    <th class="p-3.5 font-semibold">آدرس IP</th>
                    <th class="p-3.5 font-semibold">زمان ثبت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-500">
                            <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                            هنوز هیچ لاگی ثبت نشده است.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $badgeColor = 'bg-slate-800 text-slate-300 border-slate-700';
                        if (str_contains($log['action'], 'login')) $badgeColor = 'bg-emerald-950/60 text-emerald-400 border-emerald-800/60';
                        elseif (str_contains($log['action'], 'failed')) $badgeColor = 'bg-rose-950/60 text-rose-400 border-rose-800/60';
                        elseif (str_contains($log['action'], 'create')) $badgeColor = 'bg-cyan-950/60 text-cyan-400 border-cyan-800/60';
                        elseif (str_contains($log['action'], 'delete')) $badgeColor = 'bg-rose-950/60 text-rose-400 border-rose-800/60';
                        elseif (str_contains($log['action'], 'bulk')) $badgeColor = 'bg-purple-950/60 text-purple-400 border-purple-800/60';
                    ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-3.5 text-slate-500 font-mono">#<?= $log['id'] ?></td>
                            <td class="p-3.5">
                                <div class="font-bold text-white flex items-center gap-1.5">
                                    <i class="fa-solid fa-user text-[10px] text-slate-400"></i>
                                    <span><?= htmlspecialchars($log['username'] ?? 'سیستم / میهمان') ?></span>
                                </div>
                                <?php if (!empty($log['role'])): ?>
                                    <span class="text-[10px] text-slate-400 block font-mono">
                                        <?= $log['role'] === 'admin' ? 'مدیر ارشد' : 'نماینده' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold border <?= $badgeColor ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-200 font-medium">
                                <?= htmlspecialchars($log['description']) ?>
                            </td>
                            <td class="p-3.5 font-mono text-[11px] text-slate-400" dir="ltr">
                                <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                            <td class="p-3.5 text-slate-400">
                                <div class="font-mono text-[11px] text-slate-300"><?= date('Y/m/d H:i:s', strtotime($log['created_at'])) ?></div>
                                <div class="text-[10px] text-purple-400"><?= Helpers::timeAgo($log['created_at']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

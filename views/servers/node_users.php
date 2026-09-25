<?php
require __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../core/Setting.php';

/** GB formatter */
function nu_gb($bytes) { return round(((int)$bytes) / 1073741824, 2); }

$driverLabel = [
    'marzban' => 'مرزبان (Marzban)',
    'pasargad' => 'پاسارگاد (Pasargad)',
    'pasar_guard' => 'پاسارگارد (PasarGuard)',
    'pasarguard' => 'پاسارگارد (PasarGuard)',
    '3xui' => 'X-UI',
    'xui' => 'X-UI',
    'mock' => 'دمو',
][$driverName] ?? strtoupper($driverName);
$isXui = in_array(strtolower((string)$server['driver']), ['3xui', 'xui'], true);
?>

<div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center gap-3">
            <a href="<?= Helpers::url('servers') ?>" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-xl text-slate-300 border border-slate-700" title="بازگشت به سرورها">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-users text-cyan-400"></i>
                    <span>کلاینت‌های سرور: <?= htmlspecialchars($server['name']) ?></span>
                    <span class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-[11px] font-mono text-cyan-300"><?= htmlspecialchars($driverLabel) ?></span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    <?= $connected
                        ? ' ' . count($users) . ' کلاینت زنده از API سرور دریافت شد — همه کلاینت‌های تعریف‌شده روی این سرور (اعم از کلاینت‌های پنل و مستقیم سرور)'
                        : '🔴 ' . htmlspecialchars($nodeError) ?>
                </p>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <form method="post" action="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/sync') ?>" class="m-0"
                  onsubmit="return confirm('کلاینت‌های جدید این سرور به بخش «مدیریت کلاینت‌ها» اضافه می‌شوند و اطلاعات کلاینت‌های واردشده با داده‌ی زنده‌ی سرور به‌روز می‌شود. ادامه می‌دهید؟');">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-3 py-2 bg-purple-600/20 hover:bg-purple-600/40 text-purple-300 border border-purple-500/30 text-xs font-bold rounded-xl transition" title="افزودن کلاینت‌های این سرور به بخش مدیریت کلاینت‌ها (با رمز و لینک ساب پنل)">
                    <i class="fa-solid fa-arrows-rotate ml-1"></i> همگام‌سازی با پنل
                </button>
            </form>
            <a href="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/export', ['format' => 'txt']) ?>"
               class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600/40 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition" title="دانلود TXT کامل (لینک + یوزرپسورد)">
                <i class="fa-solid fa-file-lines ml-1"></i> خروجی TXT
            </a>
            <a href="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/export', ['format' => 'csv']) ?>"
               class="px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/30 text-xs font-bold rounded-xl transition" title="دانلود CSV (مناسب اکسل)">
                <i class="fa-solid fa-file-csv ml-1"></i> خروجی CSV
            </a>
            <button type="button" onclick="location.reload()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-bold rounded-xl transition">
                <i class="fa-solid fa-rotate ml-1"></i> تازه‌سازی
            </button>
        </div>
    </div>

    <?php if ($connected && empty($users)): ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-10 text-center">
            <i class="fa-solid fa-inbox text-4xl text-slate-700 mb-3"></i>
            <p class="text-sm text-slate-400">این سرور فعلاً هیچ کلاینتی ندارد.</p>
        </div>
    <?php endif; ?>

    <?php if ($isXui): ?>
        <div class="bg-amber-950/40 border border-amber-700/40 rounded-2xl p-4 text-xs text-amber-200 leading-relaxed">
            <i class="fa-solid fa-triangle-exclamation text-amber-400 ml-1"></i>
            <b>هشدار درایور X-UI:</b> عملیات فعال/غیرفعال‌سازی، تمدید و حذف در این نسخه از پنل برای سرورهای X-UI هنوز پیاده‌سازی نشده است.
            نمایش، جستجو و <b>کپی لینک‌ها/کانفیگ‌ها</b> و خروجی TXT/CSV کامل است؛ برای تغییرات روی سرور لطفاً از پنل خود X-UI استفاده کنید.
        </div>
    <?php endif; ?>

    <?php if ($connected && !empty($users)): ?>
        <!-- Search -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3">
            <input type="text" id="nu_search" placeholder="جستجو در کلاینت‌ها (نام کاربری، لینک ساب، وضعیت)...
            " class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-white text-xs focus:outline-none focus:ring-2 focus:ring-cyan-500/50" oninput="nuFilter()">
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-800/70 text-slate-400 border-b border-slate-800">
                            <th class="text-right font-bold px-4 py-3">کلاینت</th>
                            <th class="text-right font-bold px-3 py-3">وضعیت</th>
                            <th class="text-right font-bold px-3 py-3">مصرف</th>
                            <th class="text-right font-bold px-3 py-3">انقضا</th>
                            <th class="text-right font-bold px-3 py-3">ثبت در پنل</th>
                            <th class="text-left font-bold px-4 py-3">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="nu_body" class="divide-y divide-slate-800/70">
                        <?php foreach ($users as $u):
                            $pi = $panelInfo[$u['username']] ?? null;
                            $limit = $u['traffic_limit_bytes'] > 0 ? nu_gb($u['traffic_limit_bytes']) : null;
                            $used = nu_gb($u['traffic_used_bytes']);
                            $pct = ($limit && $limit > 0) ? min(100, round($used / $limit * 100)) : 0;
                            $statusBadge = match ($u['status']) {
                                'active' => '<span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold">فعال</span>',
                                'disabled' => '<span class="px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-300 border border-rose-500/30 text-[10px] font-bold">غیرفعال</span>',
                                'expired' => '<span class="px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30 text-[10px] font-bold">منقضی</span>',
                                default => '<span class="px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-300 border border-slate-500/30 text-[10px] font-bold">' . htmlspecialchars($u['status']) . '</span>',
                            };
                            $fullInfo = "نام کاربری: {$u['username']}"
                                . ($pi ? "\nرمز عبور پنل: {$pi['password']}\nلینک ساب پنل: " . Helpers::subUrl((string)$pi['sub_token']) : '')
                                . (!empty($u['subscription_url']) ? "\nساب سرور: {$u['subscription_url']}" : '')
                                . (!empty($u['links']) ? "\nکانفیگ‌ها:\n" . implode("\n", $u['links']) : '');
                            $linksBlock = implode("\n", $u['links']);
                        ?>
                        <tr class="hover:bg-slate-800/30 transition" data-search="<?= htmlspecialchars(strtolower($u['username'] . ' ' . ($u['subscription_url'] ?? '') . ' ' . $u['status'])) ?>">
                            <td class="px-4 py-3">
                                <div class="font-mono text-slate-100 font-bold" dir="ltr"><?= htmlspecialchars($u['username']) ?></div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <?php if (!empty($u['online'])): ?><span class="text-[10px] text-emerald-400 font-bold">● آنلاین</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3 py-3"><?= $statusBadge ?></td>
                            <td class="px-3 py-3" dir="ltr">
                                <?php if (!empty($u['usage_unknown'])): ?>
                                    <span class="text-slate-500" title="دریافت مصرف در این درایور پشتیبانی نمی‌شود">—</span>
                                <?php else: ?>
                                    <span class="text-slate-300 font-mono"><?= $used ?><?= $limit ? " / {$limit}" : '' ?> GB</span>
                                    <?php if ($limit): ?>
                                        <div class="w-24 h-1.5 bg-slate-800 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?= $pct >= 95 ? 'bg-rose-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 font-mono text-slate-300" dir="ltr"><?= htmlspecialchars((string)($u['expire_at'] ?? '∞')) ?></td>
                            <td class="px-3 py-3">
                                <?php if ($pi): ?>
                                    <?php if (!empty($pi['node_sync'])): ?>
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold">
                                            <i class="fa-solid fa-server ml-0.5"></i> مستقیم سرور
                                        </span>
                                        <div class="text-[10px] text-slate-500 mt-1">ورود اپ: رمز خودکار تولیدشده (از مدیریت کلاینت‌ها)</div>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded bg-purple-500/15 text-purple-300 border border-purple-500/30 text-[10px] font-bold">
                                            <i class="fa-solid fa-link ml-0.5"></i><?= htmlspecialchars($pi['plan_title'] ?? 'پلن اختصاصی') ?>
                                        </span>
                                        <div class="text-[10px] text-slate-500 mt-1">نماینده: <?= htmlspecialchars($pi['reseller_name'] ?? $pi['reseller_username'] ?? '—') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500">مستقیم سرور (خارج از پنل)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <?php if (!empty($u['subscription_url'])): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($u['subscription_url'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                                class="px-2 py-1.5 bg-cyan-900/40 hover:bg-cyan-800/60 text-cyan-300 rounded-lg text-[10px] font-bold border border-cyan-800/50 transition" title="کپی لینک ساب سرور">
                                            <i class="fa-solid fa-link ml-0.5"></i> ساب
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($u['links'])): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($linksBlock, ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                                class="px-2 py-1.5 bg-indigo-900/40 hover:bg-indigo-800/60 text-indigo-300 rounded-lg text-[10px] font-bold border border-indigo-800/50 transition" title="کپی همه کانفیگ‌ها (vless/vmess/...)">
                                            <i class="fa-solid fa-layer-group ml-0.5"></i> کانفیگ‌ها
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" data-copy="<?= htmlspecialchars($fullInfo, ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                            class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-[10px] font-bold border border-slate-700 transition" title="کپی کامل اطلاعات (یوزرپسورد + لینک‌ها)">
                                        <i class="fa-solid fa-copy ml-0.5"></i> همه
                                    </button>
                                    <?php if (!$isXui): ?>
                                    <form method="post" action="<?= Helpers::url('servers/node-users/action') ?>" class="inline m-0">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="active" value="<?= $u['status'] === 'disabled' ? '1' : '0' ?>">
                                        <button type="submit" <?= $u['status'] === 'disabled' ? '' : 'onclick="return confirm(\'غیرفعال کردن کلاینت؟\')"' ?>
                                                class="px-2 py-1.5 bg-amber-900/40 hover:bg-amber-800/60 text-amber-300 rounded-lg text-[10px] font-bold border border-amber-800/50 transition"
                                                title="<?= $u['status'] === 'disabled' ? 'فعال‌سازی' : 'غیرفعال‌سازی' ?>">
                                            <i class="fa-solid <?= $u['status'] === 'disabled' ? 'fa-play' : 'fa-pause' ?> ml-0.5"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (!$isXui): ?>
                                    <button type="button" data-user="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>" onclick="openExtendModal(this.getAttribute('data-user'))"
                                            class="px-2 py-1.5 bg-emerald-900/40 hover:bg-emerald-800/60 text-emerald-300 rounded-lg text-[10px] font-bold border border-emerald-800/50 transition" title="تمدید ترافیک و زمان">
                                        <i class="fa-solid fa-plus ml-0.5"></i> تمدید
                                    </button>
                                    <form method="post" action="<?= Helpers::url('servers/node-users/action') ?>" class="inline m-0"
                                          onsubmit="return confirm('⚠️ حذف کلاینت ' + this.querySelector('input[name=username]').value + ' از سرور؟ این عملیات برگشت‌پذیر نیست.');">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="px-2 py-1.5 bg-rose-900/40 hover:bg-rose-800/60 text-rose-300 rounded-lg text-[10px] font-bold border border-rose-800/50 transition" title="حذف از سرور">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-slate-900/50 border border-slate-800/70 rounded-2xl p-4 text-[11px] text-slate-400 leading-relaxed">
            <i class="fa-solid fa-circle-info text-cyan-400 ml-1"></i>
            <b class="text-slate-300">راهنمای کپی:</b> دکمه «ساب» لینک اشتراک سرور را، «کانفیگ‌ها» تمام آدرس‌های vless/vmess/trojan/ss را و «همه» یوزرپسورد + لینک‌ها را یک‌جا کپی می‌کند — آماده استفاده در نرم‌افزار اختصاصی یا هر کلاینت دیگری.
            کلاینت‌های ساخته‌شده مستقیم روی مرزبان (خارج از پنل) نیز همین‌جا دیده می‌شوند؛ برای این کاربران رمز عبور جداگانه‌ای وجود ندارد و خودِ لینک کانفیگ اعتبار ورودی آن‌هاست.
        </div>
    <?php endif; ?>
</div>

<!-- Extend modal -->
<div id="nu_extend_modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 w-full max-w-sm space-y-4 shadow-2xl">
        <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-hourglass-half text-emerald-400"></i>
            تمدید کلاینت <span id="nu_extend_name" class="font-mono text-emerald-300" dir="ltr"></span>
        </h3>
        <form method="post" action="<?= Helpers::url('servers/node-users/action') ?>" class="space-y-3">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
            <input type="hidden" name="username" id="nu_extend_username" value="">
            <input type="hidden" name="action" value="extend">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">افزودن روز</label>
                    <input type="number" name="days" min="0" step="1" value="30" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">افزودن گیگابایت (۰ = فقط زمان)</label>
                    <input type="number" name="gb" min="0" step="0.5" value="0" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition">
                    <i class="fa-solid fa-check ml-1"></i> اعمال تمدید
                </button>
                <button type="button" onclick="closeExtendModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function nuFilter() {
    const q = (document.getElementById('nu_search').value || '').toLowerCase();
    document.querySelectorAll('#nu_body tr').forEach(tr => {
        const s = (tr.getAttribute('data-search') || '') + ' ' + (tr.innerText || '').toLowerCase();
        tr.style.display = s.includes(q) ? '' : 'none';
    });
}
function openExtendModal(username) {
    document.getElementById('nu_extend_name').textContent = username;
    document.getElementById('nu_extend_username').value = username;
    const m = document.getElementById('nu_extend_modal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function closeExtendModal() {
    const m = document.getElementById('nu_extend_modal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('nu_extend_modal').addEventListener('click', (e) => {
    if (e.target.id === 'nu_extend_modal') closeExtendModal();
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
require __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../core/Setting.php';

function nu_gb($bytes) { return round(((int)$bytes) / 1073741824, 2); }

$driverLabel = [
    'marzban' => 'مرزبان (Marzban)',
    'pasargad' => 'پاسارگاد (Pasargad)',
    'pasar_guard' => 'پاسارگارد (PasarGuard)',
    'pasarguard' => 'پاسارگارد (PasarGuard)',
    '3xui' => 'X-UI',
    'xui' => 'X-UI',
    'mock' => 'دمو',
    'connectix_seller' => 'Connectix Seller API',
    'connectix' => 'Connectix Seller API',
    'seller' => 'Connectix Seller API',
    'seller_api' => 'Connectix Seller API',
][$driverName] ?? strtoupper($driverName);

$isXui = in_array(strtolower((string)$server['driver']), ['3xui', 'xui'], true);
$isConnectix = in_array(strtolower((string)$server['driver']), ['connectix_seller','connectix','seller','seller_api'], true);
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
                    <span class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-[11px] font-mono <?= $isConnectix ? 'text-rose-300 border-rose-800/50 bg-rose-900/20' : 'text-cyan-300' ?>"><?= htmlspecialchars($driverLabel) ?></span>
                    <?php if ($isConnectix): ?>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-900/30 border border-emerald-700/40 text-[11px] text-emerald-300">85 کلاینت زنده</span>
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    <?= $connected
                        ? ($isConnectix ? ' ' . count($users) . ' کلاینت از Connectix Seller API (api.connectix.vip) — با نام، پلن، گروه و ترافیک' : ' ' . count($users) . ' کلاینت زنده از API سرور')
                        : '🔴 ' . htmlspecialchars($nodeError) ?>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <form method="post" action="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/sync') ?>" class="m-0"
                  onsubmit="return confirm('همگام‌سازی کلاینت‌های این سرور با پنل؟');">
                <?= Helpers::csrfField() ?>
                <button type="submit" class="px-3 py-2 bg-purple-600/20 hover:bg-purple-600/40 text-purple-300 border border-purple-500/30 text-xs font-bold rounded-xl transition">
                    <i class="fa-solid fa-arrows-rotate ml-1"></i> همگام‌سازی
                </button>
            </form>
            <a href="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/export', ['format' => 'txt']) ?>"
               class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600/40 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition">
                <i class="fa-solid fa-file-lines ml-1"></i> TXT
            </a>
            <a href="<?= Helpers::url('servers/' . (int)$server['id'] . '/node-users/export', ['format' => 'csv']) ?>"
               class="px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/30 text-xs font-bold rounded-xl transition">
                <i class="fa-solid fa-file-csv ml-1"></i> CSV
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
            <b>هشدار X-UI:</b> عملیات تغییر وضعیت و حذف هنوز پیاده‌سازی نشده.
        </div>
    <?php endif; ?>

    <?php if ($isConnectix): ?>
        <div class="bg-rose-950/20 border border-rose-800/30 rounded-2xl p-4 text-xs text-rose-200 leading-relaxed flex gap-3">
            <i class="fa-solid fa-circle-info text-rose-400 mt-0.5"></i>
            <div>
                <b class="text-rose-300">Connectix Seller API:</b> این سرور از API رسمی فروشندگان Connectix استفاده می‌کند.
                اطلاعات شامل نام مشتری، پلن، گروه (Economic / Iran Access)، ترافیک مصرفی <code class="bg-black/30 px-1 rounded">0.9/10</code> و باقی‌مانده روز است.
                لینک ساب اصلی <code>sub.irancdn.org</code> و لینک Outline <code>ss://</code> قابل کپی است.
                ایجاد کاربر جدید باید از پنل اصلی <a href="https://seller.connectix.vip/clients" target="_blank" class="underline text-cyan-300">seller.connectix.vip</a> انجام شود.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($connected && !empty($users)): ?>
        <!-- Search & Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="md:col-span-3 bg-slate-900/80 border border-slate-800 rounded-2xl p-3 flex gap-2">
                <div class="relative flex-1">
                    <i class="fa-solid fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" id="nu_search" placeholder="<?= $isConnectix ? 'جستجو: نام، یوزرنیم، پلن، گروه...' : 'جستجو در کلاینت‌ها...' ?>"
                    class="w-full bg-slate-800 border border-slate-700 rounded-xl pr-9 pl-4 py-2.5 text-white text-xs focus:outline-none focus:ring-2 focus:ring-cyan-500/50" oninput="nuFilter()">
                </div>
                <select id="nu_status_filter" onchange="nuFilter()" class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-300">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="active">فعال</option>
                    <option value="expired">منقضی</option>
                    <option value="limited">حجم تمام</option>
                    <option value="disabled">غیرفعال</option>
                </select>
            </div>
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-3 flex items-center justify-around text-[11px]">
                <div class="text-center"><div class="text-slate-400">فعال</div><div class="text-emerald-400 font-bold text-sm" id="stat_active">-</div></div>
                <div class="text-center"><div class="text-slate-400">منقضی</div><div class="text-amber-400 font-bold text-sm" id="stat_expired">-</div></div>
                <div class="text-center"><div class="text-slate-400">محدود</div><div class="text-rose-400 font-bold text-sm" id="stat_limited">-</div></div>
                <div class="text-center"><div class="text-slate-400">کل</div><div class="text-white font-bold text-sm"><?= count($users) ?></div></div>
            </div>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-800/80 text-slate-400 border-b border-slate-700/50 text-[11px] uppercase tracking-wider">
                            <th class="text-right font-bold px-4 py-3">کلاینت</th>
                            <?php if ($isConnectix): ?>
                            <th class="text-right font-bold px-3 py-3">پلن / گروه</th>
                            <?php endif; ?>
                            <th class="text-right font-bold px-3 py-3">وضعیت</th>
                            <th class="text-right font-bold px-3 py-3">مصرف</th>
                            <?php if ($isConnectix): ?>
                            <th class="text-right font-bold px-3 py-3">باقی‌مانده</th>
                            <?php endif; ?>
                            <th class="text-right font-bold px-3 py-3">انقضا</th>
                            <th class="text-right font-bold px-3 py-3">ثبت در پنل</th>
                            <th class="text-left font-bold px-4 py-3">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="nu_body" class="divide-y divide-slate-800/50">
                        <?php foreach ($users as $u):
                            $pi = $panelInfo[$u['username']] ?? null;
                            $limit = $u['traffic_limit_bytes'] > 0 ? nu_gb($u['traffic_limit_bytes']) : null;
                            $used = nu_gb($u['traffic_used_bytes']);
                            $pct = ($limit && $limit > 0) ? min(100, round($used / $limit * 100)) : 0;
                            
                            // Status badge - enhanced
                            $statusConfig = match ($u['status']) {
                                'active' => ['label'=>'فعال','cls'=>'bg-emerald-500/15 text-emerald-300 border-emerald-500/30','dot'=>'bg-emerald-400'],
                                'disabled' => ['label'=>'غیرفعال','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-400'],
                                'expired' => ['label'=>'منقضی','cls'=>'bg-amber-500/15 text-amber-300 border-amber-500/30','dot'=>'bg-amber-400'],
                                'limited' => ['label'=>'حجم تمام','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-500'],
                                default => ['label'=>htmlspecialchars($u['status']),'cls'=>'bg-slate-500/15 text-slate-300 border-slate-500/30','dot'=>'bg-slate-400'],
                            };
                            
                            $fullInfo = "نام کاربری: {$u['username']}"
                                . (!empty($u['name']) ? "\nنام: {$u['name']}" : '')
                                . (!empty($u['plan_name']) ? "\nپلن: {$u['plan_name']}" : '')
                                . ($pi ? "\nرمز پنل: {$pi['password']}\nساب پنل: " . Helpers::subUrl((string)$pi['sub_token']) : '')
                                . (!empty($u['subscription_url']) ? "\nساب سرور: {$u['subscription_url']}" : '')
                                . (!empty($u['links']) ? "\nکانفیگ:\n" . implode("\n", $u['links']) : '');
                            $linksBlock = implode("\n", $u['links']);
                            
                            // Connectix specific
                            $remains = $u['remains_days'] ?? '';
                            $remainsFloat = is_numeric($remains) ? floatval($remains) : null;
                            $remainsBadge = '';
                            if ($remainsFloat !== null) {
                                if ($remainsFloat <= 0) $remainsBadge = '<span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px]">تمام شده</span>';
                                elseif ($remainsFloat <= 1) $remainsBadge = '<span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px]">'.htmlspecialchars($remains).' روز</span>';
                                elseif ($remainsFloat <= 3) $remainsBadge = '<span class="px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30 text-[10px]">'.htmlspecialchars($remains).' روز</span>';
                                else $remainsBadge = '<span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 border border-slate-600 text-[10px]">'.htmlspecialchars($remains).' روز</span>';
                            }
                            
                            // Progress color
                            $barColor = $pct >= 90 ? 'bg-rose-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
                            if ($u['status'] === 'expired' || $u['status'] === 'limited') $barColor = 'bg-slate-600';
                        ?>
                        <tr class="hover:bg-slate-800/40 transition group" data-search="<?= htmlspecialchars(strtolower($u['username'] . ' ' . ($u['name'] ?? '') . ' ' . ($u['plan_name'] ?? '') . ' ' . ($u['group_name'] ?? '') . ' ' . ($u['subscription_url'] ?? '') . ' ' . $u['status'])) ?>" data-status="<?= $u['status'] ?>">
                            <!-- Client -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br <?= $isConnectix ? 'from-rose-500/20 to-pink-600/20 border border-rose-800/30' : 'from-cyan-500/20 to-blue-600/20 border border-cyan-800/30' ?> flex items-center justify-center text-[11px] font-bold <?= $isConnectix ? 'text-rose-300' : 'text-cyan-300' ?> shrink-0">
                                        <?= strtoupper(substr($u['username'], 0, 2)) ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-slate-100 font-bold text-[13px] flex items-center gap-2" dir="ltr">
                                            <?= htmlspecialchars($u['username']) ?>
                                            <?php if (!empty($u['online'])): ?><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse" title="آنلاین"></span><?php endif; ?>
                                        </div>
                                        <?php if (!empty($u['name'])): ?>
                                            <div class="text-[11px] text-white font-medium mt-0.5 truncate max-w-[160px]"><?= htmlspecialchars($u['name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($pi['customer_name'])): ?>
                                            <div class="text-[10px] text-cyan-300/80 flex items-center gap-1 mt-0.5">
                                                <i class="fa-solid fa-user text-[9px]"></i><span><?= htmlspecialchars($pi['customer_name']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-[10px] text-slate-500 font-mono mt-0.5 truncate max-w-[180px]" dir="ltr"><?= htmlspecialchars($u['id'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            
                            <?php if ($isConnectix): ?>
                            <!-- Plan / Group -->
                            <td class="px-3 py-3.5">
                                <?php if (!empty($u['plan_name'])): ?>
                                    <div class="text-[11px] text-slate-200 font-medium bg-slate-800/70 border border-slate-700/50 rounded-lg px-2 py-1 inline-block max-w-[160px] truncate" title="<?= htmlspecialchars($u['plan_name']) ?>">
                                        <?= htmlspecialchars($u['plan_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($u['group_name'])): ?>
                                    <div class="mt-1">
                                        <?php
                                        $groupColor = match(strtolower($u['group_name'])) {
                                            'economic' => 'bg-amber-900/30 text-amber-300 border-amber-700/40',
                                            'iran access', 'iran_access' => 'bg-blue-900/30 text-blue-300 border-blue-700/40',
                                            'default', 'ویژه' => 'bg-purple-900/30 text-purple-300 border-purple-700/40',
                                            default => 'bg-slate-800 text-slate-400 border-slate-700'
                                        };
                                        ?>
                                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold <?= $groupColor ?>"><?= htmlspecialchars($u['group_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            
                            <!-- Status -->
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $statusConfig['cls'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $statusConfig['dot'] ?>"></span>
                                    <?= $statusConfig['label'] ?>
                                </span>
                                <?php if ($isConnectix && !empty($u['used_traffic_str'])): ?>
                                    <div class="text-[10px] text-slate-500 mt-1 font-mono"><?= htmlspecialchars($u['used_traffic_str']) ?> GB</div>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Usage -->
                            <td class="px-3 py-3.5" dir="ltr">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-slate-100 font-mono font-bold text-[12px]"><?= $used ?></span>
                                    <?php if ($limit): ?><span class="text-slate-500 text-[11px]">/ <?= $limit ?> GB</span><?php endif; ?>
                                </div>
                                <?php if ($limit): ?>
                                    <div class="w-24 h-1.5 bg-slate-800 rounded-full mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full <?= $barColor ?> transition-all" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5"><?= $pct ?>%</div>
                                <?php else: ?>
                                    <div class="text-[10px] text-slate-600 mt-1">نامحدود</div>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($isConnectix): ?>
                            <td class="px-3 py-3.5 text-center"><?= $remainsBadge ?: '<span class="text-slate-600 text-[10px]">—</span>' ?></td>
                            <?php endif; ?>
                            
                            <!-- Expire -->
                            <td class="px-3 py-3.5">
                                <?php if (!empty($u['expire_at']) && $u['expire_at'] !== '∞'): ?>
                                    <div class="font-mono text-slate-300 text-[11px]" dir="ltr"><?= htmlspecialchars((string)$u['expire_at']) ?></div>
                                    <?php if ($remainsFloat !== null && $remainsFloat > 0): ?>
                                        <div class="text-[10px] text-slate-500 mt-0.5"><?= $remainsFloat <= 1 ? 'امروز' : ($remainsFloat <= 2 ? 'فردا' : '') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-600">∞ نامحدود</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Panel reg -->
                            <td class="px-3 py-3.5">
                                <?php if ($pi): ?>
                                    <?php if (!empty($pi['node_sync'])): ?>
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold">مستقیم سرور</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded bg-purple-500/15 text-purple-300 border border-purple-500/30 text-[10px] font-bold"><?= htmlspecialchars($pi['plan_title'] ?? 'پلن') ?></span>
                                        <div class="text-[10px] text-slate-500 mt-1 truncate max-w-[100px]"><?= htmlspecialchars($pi['reseller_name'] ?? $pi['reseller_username'] ?? '') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-500 bg-slate-800/50 border border-slate-700/30 rounded-full px-2 py-0.5">خارج از پنل</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Actions -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-end gap-1 flex-wrap">
                                    <?php if (!empty($u['subscription_url'])): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($u['subscription_url'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                                class="w-7 h-7 flex items-center justify-center bg-cyan-900/40 hover:bg-cyan-800/60 text-cyan-300 rounded-lg border border-cyan-800/50 transition group/btn" title="کپی لینک ساب">
                                            <i class="fa-solid fa-link text-[11px] group-hover/btn:scale-110 transition"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($u['links'])): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($linksBlock, ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                                class="w-7 h-7 flex items-center justify-center bg-indigo-900/40 hover:bg-indigo-800/60 text-indigo-300 rounded-lg border border-indigo-800/50 transition group/btn" title="کپی کانفیگ‌ها">
                                            <i class="fa-solid fa-layer-group text-[11px] group-hover/btn:scale-110 transition"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" data-copy="<?= htmlspecialchars($fullInfo, ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'), this)"
                                            class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700 transition group/btn" title="کپی همه">
                                        <i class="fa-solid fa-copy text-[11px] group-hover/btn:scale-110 transition"></i>
                                    </button>
                                    <?php if (!$isXui && !$isConnectix): ?>
                                    <form method="post" action="<?= Helpers::url('servers/node-users/action') ?>" class="inline m-0">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="active" value="<?= $u['status'] === 'disabled' ? '1' : '0' ?>">
                                        <button type="submit" <?= $u['status'] === 'disabled' ? '' : 'onclick="return confirm(\'غیرفعال کردن کلاینت؟\')"' ?>
                                                class="w-7 h-7 flex items-center justify-center bg-amber-900/40 hover:bg-amber-800/60 text-amber-300 rounded-lg border border-amber-800/50 transition" title="<?= $u['status'] === 'disabled' ? 'فعال‌سازی' : 'غیرفعال‌سازی' ?>">
                                            <i class="fa-solid <?= $u['status'] === 'disabled' ? 'fa-play' : 'fa-pause' ?> text-[10px]"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (!$isXui && !$isConnectix): ?>
                                    <button type="button" data-user="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>" onclick="openExtendModal(this.getAttribute('data-user'))"
                                            class="w-7 h-7 flex items-center justify-center bg-emerald-900/40 hover:bg-emerald-800/60 text-emerald-300 rounded-lg border border-emerald-800/50 transition" title="تمدید">
                                        <i class="fa-solid fa-plus text-[10px]"></i>
                                    </button>
                                    <form method="post" action="<?= Helpers::url('servers/node-users/action') ?>" class="inline m-0" onsubmit="return confirm('حذف کلاینت ' + this.querySelector('input[name=username]').value + '؟');">
                                        <?= Helpers::csrfField() ?>
                                        <input type="hidden" name="server_id" value="<?= (int)$server['id'] ?>">
                                        <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-rose-900/40 hover:bg-rose-800/60 text-rose-300 rounded-lg border border-rose-800/50 transition" title="حذف">
                                            <i class="fa-solid fa-trash text-[10px]"></i>
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
            <b class="text-slate-300">راهنما:</b>
            <?php if ($isConnectix): ?>
            دکمه <span class="inline-flex w-5 h-5 bg-cyan-900/40 border border-cyan-800/50 rounded items-center justify-center"><i class="fa-solid fa-link text-[9px] text-cyan-300"></i></span> لینک ساب <code>sub.irancdn.org</code>،
            <span class="inline-flex w-5 h-5 bg-indigo-900/40 border border-indigo-800/50 rounded items-center justify-center"><i class="fa-solid fa-layer-group text-[9px] text-indigo-300"></i></span> کانفیگ Outline <code>ss://</code>،
            و <span class="inline-flex w-5 h-5 bg-slate-800 border border-slate-700 rounded items-center justify-center"><i class="fa-solid fa-copy text-[9px] text-slate-300"></i></span> اطلاعات کامل را کپی می‌کند.
            <?php else: ?>
            دکمه «ساب» لینک اشتراک، «کانفیگ‌ها» vless/vmess/trojan/ss و «همه» یوزرپسورد + لینک‌ها را کپی می‌کند.
            <?php endif; ?>
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
                    <label class="block text-[11px] text-slate-400 mb-1">افزودن گیگ (۰=فقط زمان)</label>
                    <input type="number" name="gb" min="0" step="0.5" value="0" dir="ltr"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition">
                    <i class="fa-solid fa-check ml-1"></i> اعمال تمدید
                </button>
                <button type="button" onclick="closeExtendModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
function nuFilter() {
    const q = (document.getElementById('nu_search').value || '').toLowerCase();
    const statusF = document.getElementById('nu_status_filter')?.value || '';
    let active=0, expired=0, limited=0, disabled=0;
    document.querySelectorAll('#nu_body tr').forEach(tr => {
        const s = (tr.getAttribute('data-search') || '') + ' ' + (tr.innerText || '').toLowerCase();
        const st = tr.getAttribute('data-status') || '';
        const matchQ = s.includes(q);
        const matchS = !statusF || st === statusF;
        tr.style.display = (matchQ && matchS) ? '' : 'none';
        if (tr.style.display !== 'none') {
            if (st==='active') active++;
            else if (st==='expired') expired++;
            else if (st==='limited') limited++;
            else if (st==='disabled') disabled++;
        }
    });
    const set = (id,val) => { const el=document.getElementById(id); if(el) el.textContent=val; };
    set('stat_active', active);
    set('stat_expired', expired);
    set('stat_limited', limited);
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
document.getElementById('nu_extend_modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'nu_extend_modal') closeExtendModal();
});
nuFilter();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

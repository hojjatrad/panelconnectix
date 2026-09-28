<?php
$pageTitle = 'پشتیبانی و تیکت‌ها';
require __DIR__ . '/../layout/header.php';

$currentStatus = $_GET['status'] ?? '';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-headset text-purple-400"></i>
                <span>سامانه تیکتینگ و پشتیبانی درون‌پنلی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">ارتباط مستقیم، پیگیری مشکلات فنی نودها، درخواست‌های مالی و پشتیبانی نمایندگان</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= Helpers::url('tickets/create') ?>" class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-lg shadow-purple-900/30 flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>ارسال تیکت جدید</span>
            </a>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
        <a href="<?= Helpers::url('tickets') ?>" 
           class="px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-2 <?= empty($currentStatus) ? 'bg-purple-600 text-white shadow' : 'bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800' ?>">
            <i class="fa-solid fa-list-ul"></i>
            <span>همه تیکت‌ها</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= empty($currentStatus) ? 'bg-purple-800 text-purple-100' : 'bg-slate-800 text-slate-400' ?>"><?= $counts['all'] ?? 0 ?></span>
        </a>

        <a href="<?= Helpers::url('tickets?status=open') ?>" 
           class="px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-2 <?= $currentStatus === 'open' ? 'bg-amber-600 text-white shadow' : 'bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800' ?>">
            <i class="fa-solid fa-envelope-open-text text-amber-400"></i>
            <span>در انتظار پاسخ</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $currentStatus === 'open' ? 'bg-amber-800 text-amber-100' : 'bg-slate-800 text-slate-400' ?>"><?= $counts['open'] ?? 0 ?></span>
        </a>

        <a href="<?= Helpers::url('tickets?status=waiting_reseller') ?>" 
           class="px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-2 <?= $currentStatus === 'waiting_reseller' ? 'bg-cyan-600 text-white shadow' : 'bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800' ?>">
            <i class="fa-solid fa-reply text-cyan-400"></i>
            <span>پاسخ نماینده</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $currentStatus === 'waiting_reseller' ? 'bg-cyan-800 text-cyan-100' : 'bg-slate-800 text-slate-400' ?>"><?= $counts['waiting_reseller'] ?? 0 ?></span>
        </a>

        <a href="<?= Helpers::url('tickets?status=answered') ?>" 
           class="px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-2 <?= $currentStatus === 'answered' ? 'bg-emerald-600 text-white shadow' : 'bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800' ?>">
            <i class="fa-solid fa-check-circle text-emerald-400"></i>
            <span>پاسخ داده شده</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $currentStatus === 'answered' ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-800 text-slate-400' ?>"><?= $counts['answered'] ?? 0 ?></span>
        </a>

        <a href="<?= Helpers::url('tickets?status=closed') ?>" 
           class="px-3.5 py-2 rounded-xl font-semibold transition flex items-center gap-2 <?= $currentStatus === 'closed' ? 'bg-slate-700 text-white shadow' : 'bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800' ?>">
            <i class="fa-solid fa-lock text-slate-400"></i>
            <span>بسته شده</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono <?= $currentStatus === 'closed' ? 'bg-slate-800 text-slate-200' : 'bg-slate-800 text-slate-400' ?>"><?= $counts['closed'] ?? 0 ?></span>
        </a>
    </div>

    <!-- Bulk Action Form & Table -->
    <form action="<?= Helpers::url('tickets/bulk-action') ?>" method="POST" id="bulkTicketsForm" class="space-y-4">
        <?= Helpers::csrfField() ?>

        <?php if (!empty($tickets)): ?>
            <!-- Bulk Action Toolbar -->
            <div class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
                <!-- Selection & Direct Close -->
                <div class="flex items-center gap-2 flex-wrap w-full md:w-auto">
                    <span class="text-slate-400 font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-check-double text-purple-400"></i>
                        <span>عملیات گروهی:</span>
                    </span>

                    <button type="button" onclick="selectAllTickets()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition text-[11px] font-medium border border-slate-700">
                        انتخاب همه
                    </button>

                    <button type="button" onclick="selectOnlyOpenTickets()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-amber-200 rounded-lg transition text-[11px] font-medium border border-slate-700">
                        انتخاب فقط بازها
                    </button>

                    <button type="button" onclick="clearAllSelection()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-lg transition text-[11px] font-medium border border-slate-700">
                        لغو انتخاب‌ها
                    </button>

                    <span id="selectedCountBadge" class="px-2.5 py-1 bg-purple-950/60 text-purple-300 border border-purple-800/40 rounded-lg font-mono font-bold text-xs">
                        ۰ تیکت انتخاب شده
                    </span>

                    <!-- ONE-CLICK BULK CLOSE BUTTON -->
                    <button type="button" onclick="submitBulkDirect('close')" id="btnDirectClose" 
                            class="px-4 py-2 bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-700 hover:to-amber-700 text-white font-bold rounded-xl transition shadow-md flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-lock"></i>
                        <span>بستن گروهی تیکت‌های انتخاب‌شده</span>
                    </button>
                </div>

                <!-- Secondary Actions Dropdown -->
                <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                    <select name="bulk_action" id="bulkActionSelect" class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-none focus:border-purple-500">
                        <option value="close">🔒 بستن تیکت‌های انتخاب‌شده</option>
                        <option value="reopen">🟢 بازگشایی مجدد تیکت‌ها</option>
                        <?php if (Auth::isAdmin()): ?>
                            <option value="delete">🗑 حذف کامل تیکت‌های انتخاب‌شده</option>
                        <?php endif; ?>
                    </select>

                    <button type="button" onclick="submitBulkDropdown()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white font-semibold rounded-xl transition border border-slate-700">
                        اجرا
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tickets Table Container -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <?php if (empty($tickets)): ?>
                <div class="p-12 text-center text-slate-500 space-y-2">
                    <i class="fa-solid fa-inbox text-3xl opacity-40"></i>
                    <p class="text-xs">هیچ تیکت پشتیبانی در این بخش یافت نشد.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60 select-none">
                            <tr>
                                <th class="p-3.5 text-center w-12">
                                    <input type="checkbox" id="selectAllMaster" onclick="toggleSelectAll(this)" 
                                           class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0 cursor-pointer w-4 h-4"
                                           title="انتخاب یا لغو انتخاب تمام تیکت‌های این صفحه">
                                </th>
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
                                <tr class="hover:bg-slate-800/30 transition-colors ticket-row" data-id="<?= $t['id'] ?>" data-status="<?= $t['status'] ?>">
                                    <td class="p-3.5 text-center">
                                        <input type="checkbox" name="selected_ids[]" value="<?= $t['id'] ?>" 
                                               onchange="updateSelectedCount()"
                                               class="ticket-check rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0 cursor-pointer w-4 h-4">
                                    </td>
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
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="<?= Helpers::url('tickets/show?id=' . $t['id']) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-purple-600 hover:text-white text-purple-300 rounded-lg text-xs font-semibold transition border border-slate-700">
                                                مشاهده
                                            </a>
                                            <?php if ($t['status'] !== 'closed'): ?>
                                                <button type="button" onclick="singleCloseTicket(<?= $t['id'] ?>)" class="p-1.5 bg-slate-800 hover:bg-rose-900/60 text-slate-400 hover:text-rose-300 rounded-lg transition border border-slate-700 text-xs" title="بستن این تیکت">
                                                    <i class="fa-solid fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Hidden single close form for individual lock button -->
<form id="singleCloseForm" action="<?= Helpers::url('tickets/close') ?>" method="POST" class="hidden">
    <?= Helpers::csrfField() ?>
    <input type="hidden" name="ticket_id" id="singleCloseTicketId" value="0">
</form>

<script>
    function getSelectedCheckboxes() {
        return Array.from(document.querySelectorAll('.ticket-check:checked'));
    }

    function updateSelectedCount() {
        const count = getSelectedCheckboxes().length;
        const total = document.querySelectorAll('.ticket-check').length;
        const badge = document.getElementById('selectedCountBadge');
        if (badge) {
            badge.innerText = count.toLocaleString('fa-IR') + ' تیکت انتخاب شده';
            if (count > 0) {
                badge.className = 'px-2.5 py-1 bg-purple-600 text-white border border-purple-500 rounded-lg font-mono font-bold text-xs animate-pulse';
            } else {
                badge.className = 'px-2.5 py-1 bg-purple-950/60 text-purple-300 border border-purple-800/40 rounded-lg font-mono font-bold text-xs';
            }
        }

        const master = document.getElementById('selectAllMaster');
        if (master) {
            master.checked = (total > 0 && count === total);
            master.indeterminate = (count > 0 && count < total);
        }
    }

    function toggleSelectAll(master) {
        document.querySelectorAll('.ticket-check').forEach(cb => cb.checked = master.checked);
        updateSelectedCount();
    }

    function selectAllTickets() {
        document.querySelectorAll('.ticket-check').forEach(cb => cb.checked = true);
        const master = document.getElementById('selectAllMaster');
        if (master) master.checked = true;
        updateSelectedCount();
    }

    function selectOnlyOpenTickets() {
        document.querySelectorAll('.ticket-row').forEach(row => {
            const cb = row.querySelector('.ticket-check');
            const status = row.getAttribute('data-status');
            if (cb) {
                cb.checked = (status !== 'closed');
            }
        });
        updateSelectedCount();
    }

    function clearAllSelection() {
        document.querySelectorAll('.ticket-check').forEach(cb => cb.checked = false);
        const master = document.getElementById('selectAllMaster');
        if (master) {
            master.checked = false;
            master.indeterminate = false;
        }
        updateSelectedCount();
    }

    function submitBulkDirect(action) {
        const selected = getSelectedCheckboxes();
        if (selected.length === 0) {
            alert('لطفاً حداقل یک تیکت را برای بستن گروهی علامت بزنید.');
            return;
        }

        const countFa = selected.length.toLocaleString('fa-IR');
        if (!confirm(`آیا از بستن گروهی ${countFa} تیکت انتخاب‌شده اطمینان دارید؟`)) {
            return;
        }

        const form = document.getElementById('bulkTicketsForm');
        document.getElementById('bulkActionSelect').value = action;
        form.submit();
    }

    function submitBulkDropdown() {
        const selected = getSelectedCheckboxes();
        if (selected.length === 0) {
            alert('لطفاً ابتدا تیکت‌های مورد نظر را انتخاب فرمایید.');
            return;
        }

        const action = document.getElementById('bulkActionSelect').value;
        const countFa = selected.length.toLocaleString('fa-IR');

        let confirmMsg = `آیا از اعمال این عملیات روی ${countFa} تیکت انتخاب‌شده اطمینان دارید؟`;
        if (action === 'delete') {
            confirmMsg = `⚠️ هشدار: آیا از حذف قطعی ${countFa} تیکت و تمام پیام‌های آن‌ها اطمینان دارید؟ این عملیات غیرقابل بازگشت است!`;
        } else if (action === 'close') {
            confirmMsg = `آیا از بستن گروهی ${countFa} تیکت انتخاب‌شده اطمینان دارید؟`;
        }

        if (!confirm(confirmMsg)) {
            return;
        }

        document.getElementById('bulkTicketsForm').submit();
    }

    function singleCloseTicket(ticketId) {
        if (!confirm(`آیا از بستن تیکت #${ticketId} اطمینان دارید؟`)) {
            return;
        }
        document.getElementById('singleCloseTicketId').value = ticketId;
        document.getElementById('singleCloseForm').submit();
    }

    document.addEventListener('DOMContentLoaded', updateSelectedCount);
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

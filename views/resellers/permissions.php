<?php
$pageTitle = 'سطح دسترسی نماینده - ' . htmlspecialchars($reseller['username']);
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center"><i class="fa-solid fa-user-shield"></i></span>
                    سطح دسترسی نماینده
                </h1>
                <p class="text-sm text-slate-400 mt-2">نماینده: <span class="text-white font-bold"><?= htmlspecialchars($reseller['username']) ?> - <?= htmlspecialchars($reseller['full_name']) ?></span></p>
            </div>
            <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm">بازگشت</a>
        </div>
    </div>

    <!-- Template Selector -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-layer-group text-amber-400"></i> اعمال قالب آماده</h3>
        <form method="POST" action="<?= Helpers::url('resellers/permissions/apply-template') ?>" class="flex gap-3">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="reseller_id" value="<?= $resellerId ?>">
            <select name="template_id" class="flex-1 bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-2.5 text-sm">
                <?php foreach ($templates as $tpl): ?>
                    <option value="<?= $tpl['id'] ?>"><?= htmlspecialchars($tpl['name']) ?> - <?= htmlspecialchars($tpl['description']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-sm">اعمال قالب</button>
        </form>
    </div>

    <!-- Permissions Form -->
    <form method="POST" action="<?= Helpers::url('resellers/permissions/save') ?>" class="space-y-6">
        <?= Helpers::csrfField() ?>
        <input type="hidden" name="reseller_id" value="<?= $resellerId ?>">

        <?php
        $groupLabels = [
            'main' => 'منوهای اصلی',
            'bot' => 'ربات',
            'finance' => 'مالی',
            'system' => 'سیستم',
            'resellers' => 'نماینده‌ها',
            'infra' => 'زیرساخت',
            'capability' => 'قابلیت‌های داخلی'
        ];
        foreach ($grouped as $groupKey => $perms):
            $label = $groupLabels[$groupKey] ?? $groupKey;
        ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 class="text-sm font-black text-white mb-4 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-violet-600/20 text-violet-400 flex items-center justify-center text-xs"><i class="fa-solid fa-folder"></i></span>
                <?= $label ?> (<?= count($perms) ?> مورد)
            </h3>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($perms as $key => $info):
                    $curr = $permissions[$key] ?? ['visible'=>1,'enabled'=>1];
                    $vis = (int)$curr['visible'];
                    $en = (int)$curr['enabled'];
                ?>
                <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-slate-700 text-slate-300 flex items-center justify-center text-xs"><i class="fa-solid <?= $info['icon'] ?>"></i></span>
                        <div>
                            <div class="text-sm font-bold text-white"><?= $info['label'] ?></div>
                            <div class="text-[10px] text-slate-500 font-mono"><?= $key ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Visible -->
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="perm_<?= $key ?>_visible" value="1" <?= $vis ? 'checked' : '' ?> class="w-4 h-4 rounded bg-slate-700 border-slate-600 text-indigo-600 focus:ring-indigo-500" onchange="updateEnabledState(this, '<?= $key ?>')">
                            <span class="text-[11px] text-slate-300">نمایش</span>
                        </label>
                        <!-- Enabled -->
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="perm_<?= $key ?>_enabled" value="1" <?= $en ? 'checked' : '' ?> id="enabled_<?= $key ?>" <?= !$vis ? 'disabled' : '' ?> class="w-4 h-4 rounded bg-slate-700 border-slate-600 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-[11px] text-slate-300">فعال</span>
                        </label>
                        <!-- Status badge -->
                        <span id="badge_<?= $key ?>" class="text-[9px] px-2 py-1 rounded-full font-bold <?= $vis ? ($en ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30') : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
                            <?= $vis ? ($en ? 'فعال' : 'غیرفعال') : 'مخفی' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl text-sm shadow-lg shadow-indigo-900/30">💾 ذخیره سطح دسترسی</button>
            <a href="<?= Helpers::url('resellers') ?>" class="px-8 py-3.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-sm border border-slate-700">لغو</a>
        </div>
    </form>

    <!-- Info -->
    <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-4">
        <h4 class="text-xs font-bold text-white mb-2">💡 راهنما:</h4>
        <ul class="text-[11px] text-slate-400 space-y-1 list-disc pr-4">
            <li><b>نمایش:</b> اگر خاموش باشد، منو کاملاً مخفی می‌شود و نماینده نمی‌بیند</li>
            <li><b>فعال:</b> اگر نمایش روشن ولی فعال خاموش باشد، منو نمایش داده می‌شود ولی خاکستری و غیرقابل کلیک است</li>
            <li><b>قالب‌ها:</b> می‌توانید قالب بسازید و برای نماینده‌های مختلف اعمال کنید</li>
            <li><b>همگام‌سازی:</b> بعد هر بروزرسانی پنل، نماینده‌ها خودکار sync می‌شوند و دسترسی‌های جدید با پیش‌فرض فعال اضافه می‌شود</li>
        </ul>
    </div>
</div>

<script>
function updateEnabledState(visibleCheckbox, key) {
    const enabledCb = document.getElementById('enabled_' + key);
    const badge = document.getElementById('badge_' + key);
    if (visibleCheckbox.checked) {
        enabledCb.disabled = false;
        if (enabledCb.checked) {
            badge.textContent = 'فعال';
            badge.className = 'text-[9px] px-2 py-1 rounded-full font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
        } else {
            badge.textContent = 'غیرفعال';
            badge.className = 'text-[9px] px-2 py-1 rounded-full font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30';
        }
    } else {
        enabledCb.disabled = true;
        enabledCb.checked = false;
        badge.textContent = 'مخفی';
        badge.className = 'text-[9px] px-2 py-1 rounded-full font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30';
    }
}

// Update badge on enabled change
document.querySelectorAll('input[id^=\"enabled_\"]').forEach(cb => {
    cb.addEventListener('change', function() {
        const key = this.id.replace('enabled_', '');
        const badge = document.getElementById('badge_' + key);
        if (this.checked) {
            badge.textContent = 'فعال';
            badge.className = 'text-[9px] px-2 py-1 rounded-full font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
        } else {
            badge.textContent = 'غیرفعال';
            badge.className = 'text-[9px] px-2 py-1 rounded-full font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30';
        }
    });
});
</script>

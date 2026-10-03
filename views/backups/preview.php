<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-white">👁️ پیش‌نمایش بکاپ #<?= $backup['id'] ?> - <?= htmlspecialchars($backup['server_name']) ?></h1>
        <a href="<?= Helpers::url('backups') ?>" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">بازگشت</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-blue-400"><?= $backup['clients_count'] ?></div>
            <div class="text-xs text-slate-400">کلاینت</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-emerald-400"><?= $backup['plans_count'] ?></div>
            <div class="text-xs text-slate-400">پلن</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-amber-400"><?= $backup['categories_count'] ?></div>
            <div class="text-xs text-slate-400">دسته</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-lg font-bold text-white"><?= round($backup['file_size']/1024,1) ?>KB</div>
            <div class="text-xs text-slate-400"><?= htmlspecialchars($backup['file_name']) ?></div>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-4">
        <h3 class="text-white font-bold mb-3">📋 اطلاعات بکاپ</h3>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-400">نوع:</span><span class="text-white"><?= htmlspecialchars($backup['type']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">خودکار:</span><span class="text-white"><?= $backup['is_auto'] ? 'بله' : 'خیر' ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">تاریخ:</span><span class="text-white font-mono text-xs"><?= htmlspecialchars($backup['created_at']) ?></span></div>
            <div class="flex justify-between"><span class="text-slate-400">Checksum:</span><span class="text-white font-mono text-[10px]"><?= substr($backup['checksum'] ?? '',0,16) ?>...</span></div>
            <div class="col-span-2"><span class="text-slate-400">یادداشت:</span><span class="text-white"> <?= htmlspecialchars($backup['note'] ?? '-') ?></span></div>
        </div>
    </div>

    <?php if ($data): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h4 class="text-white font-bold mb-3">📂 دسته‌بندی‌ها (<?= count($data['categories'] ?? []) ?>)</h4>
            <div class="max-h-96 overflow-y-auto space-y-1">
                <?php foreach (array_slice($data['categories'] ?? [],0,50) as $c): ?>
                    <div class="p-2 bg-slate-800 rounded-lg text-xs text-slate-300 flex justify-between"><span><?= htmlspecialchars($c['name'] ?? '') ?></span><span class="text-slate-500"><?= htmlspecialchars($c['slug'] ?? '') ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h4 class="text-white font-bold mb-3">📋 پلن‌ها (<?= count($data['plans'] ?? []) ?>)</h4>
            <div class="max-h-96 overflow-y-auto space-y-1">
                <?php foreach (array_slice($data['plans'] ?? [],0,50) as $p): ?>
                    <div class="p-2 bg-slate-800 rounded-lg text-xs"><div class="text-white"><?= htmlspecialchars($p['title'] ?? $p['name'] ?? '') ?></div><div class="text-slate-400"><?= $p['traffic_gb'] ?? '' ?>GB - <?= $p['duration_days'] ?? '' ?>روز - <?= number_format($p['base_price'] ?? 0) ?> تومان</div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
            <h4 class="text-white font-bold mb-3">👥 کلاینت‌ها (<?= count($data['clients'] ?? []) ?>)</h4>
            <div class="max-h-96 overflow-y-auto space-y-1">
                <?php foreach (array_slice($data['clients'] ?? [],0,50) as $cli): ?>
                    <div class="p-2 bg-slate-800 rounded-lg text-xs"><div class="text-white flex justify-between"><span><?= htmlspecialchars($cli['username']) ?></span><span class="text-[10px] px-1 bg-slate-700 rounded"><?= htmlspecialchars($cli['status']) ?></span></div><div class="text-slate-400 truncate"><?= htmlspecialchars(substr($cli['node_sublink'] ?? '',0,40)) ?></div><div class="text-[10px] text-slate-500"><?= round(($cli['traffic_used_bytes'] ?? 0)/1024/1024/1024,2) ?>GB / <?= round(($cli['traffic_limit_bytes'] ?? 0)/1024/1024/1024,2) ?>GB</div></div>
                <?php endforeach; ?>
                <?php if (count($data['clients'] ?? []) > 50): ?>
                    <div class="text-center text-xs text-slate-500 mt-2">و <?= count($data['clients'])-50 ?> مورد دیگر...</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-6 flex gap-2">
        <a href="<?= Helpers::url('backups/'.$backup['id'].'/download') ?>" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-sm font-bold">⬇️ دانلود بکاپ</a>
        <a href="<?= Helpers::url('backups') ?>" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm">بازگشت به لیست</a>
    </div>
</div>

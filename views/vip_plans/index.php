<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <div class="flex flex-col md:flex-row justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-white flex items-center gap-2">
                    <i class="fa-solid fa-crown text-amber-400"></i>
                    مدیریت پلن‌های VIP - اقتصادی / ویژه / ایران‌اکسس
                </h2>
                <p class="text-sm text-slate-400 mt-2">سرور VIP شما دارای <?= count($vipPlans) ?> پلن و <?= count($vipGroups) ?> گروه است. اینجا مشخص می‌کنید هر پلن داخلی پنل دقیقاً به کدام پلن VIP متصل شود.</p>
                <?php if ($error): ?>
                    <p class="text-rose-400 text-sm mt-2">خطا: <?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <form method="post" action="<?= Helpers::url('vip_plans/auto-map') ?>">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-xl">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> نگاشت خودکار هوشمند
                    </button>
                </form>
                <a href="<?= Helpers::url('plans') ?>" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-sm">بازگشت به پلن‌ها</a>
            </div>
        </div>
    </div>

    <?php if (!$vipServer): ?>
        <div class="bg-rose-950/50 border border-rose-800 rounded-2xl p-6 text-center">
            <p class="text-rose-300">❌ سرور VIP یافت نشد! ابتدا در بخش سرورها یک سرور با درایور Connectix Seller اضافه کنید</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <h3 class="font-bold text-white mb-3 flex items-center gap-2"><span class="w-3 h-3 bg-emerald-500 rounded-full"></span> گروه‌های VIP (<?= count($vipGroups) ?>)</h3>
                <div class="space-y-2">
                    <?php foreach ($vipGroups as $vg): ?>
                        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-3 flex justify-between items-center">
                            <div>
                                <div class="font-bold text-sm text-white"><?= htmlspecialchars($vg['name']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($vg['name_translations']['fa'] ?? '') ?> - <?= substr($vg['id'],0,8) ?>...</div>
                            </div>
                            <span class="text-[10px] bg-slate-700 px-2 py-1 rounded-full font-mono"><?= htmlspecialchars(substr($vg['id'],0,8)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <?php foreach (['vip'=>'⭐ ویژه (بدون اقتصادی/ایران)','economic'=>'💰 اقتصادی','iran'=>'🇮🇷 ایران‌اکسس','free'=>'🆓 رایگان/تست','business'=>'💼 بیزنس','other'=>'📦 سایر'] as $catKey=>$catTitle): ?>
                    <?php if (empty($categorized[$catKey])) continue; ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                        <h4 class="font-bold text-white mb-3"><?= $catTitle ?> (<?= count($categorized[$catKey]) ?>)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <?php foreach ($categorized[$catKey] as $vp): ?>
                                <div class="bg-slate-800 border border-slate-700 rounded-xl p-3">
                                    <div class="font-bold text-sm text-white"><?= htmlspecialchars($vp['title']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-1"><?= htmlspecialchars($vp['id']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
            <h3 class="font-black text-white text-lg mb-4">🔗 نگاشت پلن‌های داخلی به VIP</h3>
            <p class="text-sm text-slate-400 mb-4">برای هر پلن داخلی مشخص کنید دقیقاً کدام پلن VIP و کدام گروه استفاده شود. مثلاً 10 گیگ اقتصادی با 10 گیگ ویژه فرق می‌کند.</p>
            
            <form method="post" action="<?= Helpers::url('vip_plans/save-mapping') ?>">
                <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-400 text-xs">
                                <th class="text-right p-3">پلن داخلی</th>
                                <th class="text-right p-3">حجم / روز</th>
                                <th class="text-right p-3">گروه فعلی</th>
                                <th class="text-right p-3">پلن VIP (انتخاب)</th>
                                <th class="text-right p-3">گروه VIP (انتخاب)</th>
                                <th class="text-right p-3">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($localPlans as $lp): ?>
                                <tr class="border-b border-slate-800/50 hover:bg-slate-800/30">
                                    <td class="p-3">
                                        <div class="font-bold text-white"><?= htmlspecialchars($lp['title']) ?></div>
                                        <div class="text-xs text-slate-500">ID: <?= $lp['id'] ?> - <?= number_format($lp['base_price']) ?> تومان</div>
                                    </td>
                                    <td class="p-3 text-slate-300"><?= $lp['traffic_gb'] ?>GB / <?= $lp['duration_days'] ?> روز</td>
                                    <td class="p-3"><span class="text-xs bg-slate-800 px-2 py-1 rounded-full"><?= htmlspecialchars($lp['server_group']) ?></span></td>
                                    <td class="p-3">
                                        <select name="mapping[<?= $lp['id'] ?>][vip_plan_id]" class="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs w-full max-w-[220px]" onchange="updatePlanTitle(this, <?= $lp['id'] ?>)">
                                            <option value="">-- خودکار بر اساس حجم --</option>
                                            <?php foreach ($vipPlans as $vp): ?>
                                                <option value="<?= htmlspecialchars($vp['id']) ?>" <?= ($lp['vip_plan_id'] ?? '') === $vp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vp['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="mapping[<?= $lp['id'] ?>][vip_plan_title]" id="plan_title_<?= $lp['id'] ?>" value="<?= htmlspecialchars($lp['vip_plan_title'] ?? '') ?>">
                                    </td>
                                    <td class="p-3">
                                        <select name="mapping[<?= $lp['id'] ?>][vip_group_id]" class="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs w-full max-w-[180px]" onchange="updateGroupName(this, <?= $lp['id'] ?>)">
                                            <option value="">-- پیش‌فرض (ویژه) --</option>
                                            <?php foreach ($vipGroups as $vg): ?>
                                                <option value="<?= htmlspecialchars($vg['id']) ?>" data-name="<?= htmlspecialchars($vg['name']) ?>" <?= ($lp['vip_group_id'] ?? '') === $vg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vg['name']) ?> (<?= htmlspecialchars($vg['name_translations']['fa'] ?? '') ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="mapping[<?= $lp['id'] ?>][vip_group_name]" id="group_name_<?= $lp['id'] ?>" value="<?= htmlspecialchars($lp['vip_group_name'] ?? '') ?>">
                                    </td>
                                    <td class="p-3">
                                        <?php if (!empty($lp['vip_plan_id'])): ?>
                                            <span class="text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-1 rounded-full">✅ نگاشت شده</span>
                                            <div class="text-[10px] text-slate-500 mt-1"><?= htmlspecialchars($lp['vip_plan_title'] ?? '') ?></div>
                                        <?php else: ?>
                                            <span class="text-xs bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-1 rounded-full">⚠️ خودکار</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-black rounded-xl">
                        💾 ذخیره نگاشت‌ها
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-gradient-to-br from-violet-900/20 to-cyan-900/20 border border-violet-800/30 rounded-2xl p-6">
            <h4 class="font-black text-white mb-3">💡 راهنمای انتخاب پلن:</h4>
            <ul class="text-sm text-slate-300 space-y-2 list-disc pr-5">
                <li><b>ویژه (default):</b> پلن‌هایی که در عنوانشان Economic یا Iran Access ندارند - مثل <code>(4x) 10GB-1M + 3D</code> - گروه <code>default</code> یا <code>ویژه</code></li>
                <li><b>اقتصادی:</b> پلن‌هایی که <code>+ Economic</code> دارند - مثل <code>(4x) 10GB-1M + 3D + Economic</code> - گروه <code>Economic</code> یا <code>اقتصادی</code></li>
                <li><b>ایران‌اکسس:</b> پلن‌هایی که <code>+ Iran Access</code> دارند - مثل <code>(4x) 20GB-1M + 3D + Iran Access</code> - گروه <code>Iran Access</code></li>
                <li>وقتی کاربر جدید می‌سازی، سیستم دقیقاً از همین نگاشت استفاده می‌کند و یوزر در پلن درست ساخته می‌شود</li>
                <li>اگر نگاشت خالی باشد، سیستم بر اساس حجم نزدیک‌ترین پلن را پیدا می‌کند (ممکن است اقتصادی/ویژه را اشتباه بگیرد)</li>
                <li>برای 10 گیگ 1 ماهه اقتصادی: پلن <code>(4x) 10GB-1M + 3D + Economic</code> + گروه <code>Economic</code></li>
                <li>برای 10 گیگ 1 ماهه ویژه: پلن <code>(4x) 10GB-1M + 3D</code> + گروه <code>default</code></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<script>
function updatePlanTitle(sel, id) {
    const title = sel.options[sel.selectedIndex]?.text || '';
    document.getElementById('plan_title_'+id).value = title;
}
function updateGroupName(sel, id) {
    const opt = sel.options[sel.selectedIndex];
    const name = opt?.dataset?.name || opt?.text?.split(' ')[0] || '';
    document.getElementById('group_name_'+id).value = name;
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

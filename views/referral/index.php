<?php
// F1: Enhanced Referral System UI
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../core/Referral.php';

$userId = Auth::id();
$stats = Referral::getStats($userId);
$panelDomain = Helpers::getPanelDomain(); $siteUrl = 'https://' . $panelDomain; $referralLink = (Setting::get('site_url', $siteUrl) . '/?ref=' . $stats['referral_code']);
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 border border-purple-500/30 rounded-2xl p-6">
        <h1 class="text-xl font-black text-white mb-2">🤝 سیستم دعوت دوستان - کسب درآمد 20%</h1>
        <p class="text-sm text-slate-300">با دعوت دوستان، 20% از هر خرید آنها را به کیف پول خود دریافت کنید</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-2xl font-black text-purple-400"><?= $stats['total_invited'] ?></div>
            <div class="text-xs text-slate-400 mt-1">دوستان دعوت شده</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-2xl font-black text-emerald-400"><?= number_format($stats['total_commission']) ?></div>
            <div class="text-xs text-slate-400 mt-1">پورسانت کل (تومان)</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 text-center">
            <div class="text-2xl font-black text-cyan-400"><?= $stats['commission_percent'] ?>%</div>
            <div class="text-xs text-slate-400 mt-1">درصد پورسانت</div>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
        <h2 class="font-bold text-white">🔗 لینک دعوت شما</h2>
        <div class="flex gap-2">
            <input type="text" value="<?= htmlspecialchars($referralLink) ?>" id="refLink" class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white font-mono" readonly>
            <button onclick="copyRef()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-bold">کپی</button>
        </div>
        <div class="flex gap-2">
            <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=<?= urlencode('با این لینک VPN پرسرعت بخر و من هم پورسانت بگیرم!') ?>" target="_blank" class="flex-1 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold text-center">اشتراک در تلگرام</a>
            <a href="https://wa.me/?text=<?= urlencode($referralLink) ?>" target="_blank" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold text-center">اشتراک در واتساپ</a>
        </div>
        <p class="text-xs text-slate-400">کد معرف شما: <code class="bg-slate-800 px-2 py-1 rounded text-purple-300"><?= $stats['referral_code'] ?></code></p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <h2 class="font-bold text-white mb-4">📋 آخرین دعوت‌ها</h2>
        <?php if (empty($stats['recent'])): ?>
            <p class="text-sm text-slate-400 text-center py-4">هنوز کسی را دعوت نکرده‌اید</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($stats['recent'] as $ref): ?>
                    <div class="flex justify-between items-center bg-slate-950 rounded-xl p-3">
                        <div>
                            <div class="text-sm text-white"><?= htmlspecialchars($ref['referred_username'] ?? 'کاربر #' . $ref['referred_id']) ?></div>
                            <div class="text-xs text-slate-400"><?= date('Y-m-d', strtotime($ref['created_at'])) ?></div>
                        </div>
                        <div class="text-sm font-bold text-emerald-400">+<?= number_format($ref['commission_amount']) ?> تومان</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function copyRef() {
    const input = document.getElementById('refLink');
    input.select();
    document.execCommand('copy');
    alert('لینک کپی شد!');
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
/** @var array|null $client */
$client = $client ?? null;
$brandName = $client['brand_name'] ?? 'Connectix VPN';
$logoUrl = (string)($client['logo_url'] ?? '');
$theme = $client['theme_color'] ?? 'violet';
$themeMap = ['violet' => '#6366F1', 'blue' => '#3B82F6', 'emerald' => '#10B981', 'rose' => '#F43F5E', 'amber' => '#F59E0B', 'cyan' => '#06B6D4'];
$accent = $themeMap[$theme] ?? '#6366F1';
$err = $error ?? '';

$usedBytes = (int)($client['traffic_used_bytes'] ?? 0);
$limitBytes = (int)($client['traffic_limit_bytes'] ?? 0);
$remBytes = max(0, $limitBytes - $usedBytes);
$usagePct = $limitBytes > 0 ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0;
$daysLeft = $client ? Helpers::daysRemaining($client['expire_at']) : '';
$expired = $client && !empty($client['expire_at']) && strtotime((string)$client['expire_at']) <= time();
$statusFa = match ($client['status'] ?? '') {
    'active' => ['🟢 فعال', '#10B981'],
    'expired' => ['🔴 منقضی شده', '#F43F5E'],
    'disabled' => ['⏸ غیرفعال', '#F59E0B'],
    default => ['⚪ ' . ($client['status'] ?? '-'), '#94A3B8'],
};
$subUrl = $client ? Helpers::subUrl((string)$client['sub_token']) : '';
$renewalUrl = trim((string)($client['renewal_url'] ?? ''));
$botUsername = trim((string)($client['reseller_bot_username'] ?? ''));
$telegramSupport = trim((string)($client['telegram_support'] ?? ''));
$whatsappSupport = trim((string)($client['whatsapp_support'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>پورتال مشتری — <?= htmlspecialchars($brandName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap');
* { font-family: 'Vazirmatn', sans-serif; }
body { background: radial-gradient(1200px 600px at 50% -100px, <?= $accent ?>22, transparent), #0B1120; }
.card { background: rgba(15,23,42,.85); border: 1px solid rgba(51,65,85,.6); }
input { direction: ltr; text-align: left; }
</style>
</head>
<body class="min-h-screen text-slate-100 flex items-center justify-center p-4">
<div class="w-full max-w-md space-y-4">

    <!-- Brand -->
    <div class="flex items-center justify-center gap-3 pt-4">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="logo" class="w-11 h-11 rounded-2xl object-cover border border-slate-700">
        <?php else: ?>
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-xl font-black" style="background:<?= $accent ?>22; color:<?= $accent ?>;">☰</div>
        <?php endif; ?>
        <div class="text-center">
            <h1 class="font-extrabold text-lg leading-tight"><?= htmlspecialchars($brandName) ?></h1>
            <p class="text-[11px] text-slate-400">پورتال خودخدمت مشتری</p>
        </div>
    </div>

    <?php if (!$client): ?>
        <!-- ============ LOGIN ============ -->
        <div class="card rounded-3xl p-6 shadow-2xl">
            <h2 class="font-bold text-white mb-1">ورود به حساب اشتراک</h2>
            <p class="text-xs text-slate-400 mb-5">همان نام کاربری و کلمه عبوری که در اپلیکیشن وارد می‌کنید.</p>
            <?php if ($err !== ''): ?>
                <div class="mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/40 text-rose-300 text-xs"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars(Helpers::url('client/login')) ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Helpers::csrfToken()) ?>">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">نام کاربری</label>
                    <input name="username" required autofocus class="w-full bg-slate-950/70 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none" style="border-color:<?= $accent ?>66" placeholder="username">
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">کلمه عبور</label>
                    <input type="password" name="password" required class="w-full bg-slate-950/70 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:outline-none" style="border-color:<?= $accent ?>66" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl text-white font-bold text-sm transition-opacity active:opacity-80" style="background:<?= $accent ?>">ورود</button>
            </form>
        </div>
        <p class="text-center text-[11px] text-slate-500 pb-6">مشکلی برای ورود دارید؟ از دکمه‌های پشتیبانی در همین صفحه بعد از ورود استفاده کنید.</p>
    <?php else: ?>
        <!-- ============ DASHBOARD ============ -->
        <div class="card rounded-3xl p-5 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] text-slate-400">نام کاربری</p>
                    <p class="font-bold text-sm"><span dir="ltr"><?= htmlspecialchars($client['username']) ?></span></p>
                </div>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full" style="background:<?= $statusFa[1] ?>1a; color:<?= $statusFa[1] ?>;"><?= $statusFa[0] ?></span>
            </div>

            <div class="h-px bg-slate-800"></div>

            <!-- Traffic -->
            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <span class="text-xs text-slate-400">مصرف ترافیک</span>
                    <span class="text-xs font-bold"><?= Helpers::formatBytes($usedBytes) ?> از <?= $limitBytes > 0 ? Helpers::formatBytes($limitBytes) : 'نامحدود' ?></span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full" style="width:<?= $limitBytes > 0 ? $usagePct : 2 ?>%; background:<?= $usagePct >= 95 ? '#F43F5E' : ($usagePct >= 80 ? '#F59E0B' : $accent) ?>"></div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">باقیمانده: <b class="text-slate-300"><?= $limitBytes > 0 ? Helpers::formatBytes($remBytes) : 'نامحدود' ?></b></p>
            </div>

            <!-- Time -->
            <div class="flex items-center justify-between bg-slate-950/50 rounded-xl p-3 border border-slate-800">
                <div>
                    <p class="text-[11px] text-slate-400">اعتبار زمانی</p>
                    <p class="text-sm font-bold <?= $expired ? 'text-rose-400' : 'text-emerald-400' ?>"><?= $daysLeft ?></p>
                </div>
                <div class="text-left">
                    <p class="text-[11px] text-slate-400">سرور</p>
                    <p class="text-sm font-bold"><?= htmlspecialchars((string)($client['server_name'] ?: 'سرور ابری')) ?></p>
                </div>
            </div>
            <?php if (!empty($client['plan_title'])): ?>
                <p class="text-[11px] text-slate-500 text-center">پلن: <b class="text-slate-300"><?= htmlspecialchars($client['plan_title']) ?></b></p>
            <?php endif; ?>

            <div class="h-px bg-slate-800"></div>

            <!-- Sublink + QR -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-slate-400">لینک اشتراک (Sublink)</span>
                    <div class="flex gap-2">
                        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>this.textContent='کپی شد ✓',setTimeout(()=>this.textContent='کپی لینک',1500))"
                                class="text-[11px] font-bold px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 active:scale-95 transition">کپی لینک</button>
                        <a href="<?= htmlspecialchars($subUrl) ?>" target="_blank" rel="noopener" class="text-[11px] font-bold px-3 py-1.5 rounded-lg" style="background:<?= $accent ?>22; color:<?= $accent ?>">باز کردن</a>
                    </div>
                </div>
                <div class="bg-white/95 rounded-2xl p-3 flex justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= urlencode($subUrl) ?>" alt="QR" class="w-44 h-44">
                </div>
                <p class="text-[10px] text-slate-500 text-center mt-1.5">اسکن با اپ Connectix / v2ray / sing-box / shadowrocket</p>
            </div>

            <div class="h-px bg-slate-800"></div>

            <!-- Actions -->
            <div class="grid grid-cols-2 gap-2">
                <?php if ($renewalUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($renewalUrl) ?>" target="_blank" rel="noopener" class="col-span-2 text-center py-3 rounded-xl text-white font-bold text-sm" style="background:<?= $accent ?>">🔄 تمدید آنلاین اشتراک</a>
                <?php elseif ($botUsername !== ''): ?>
                    <a href="https://t.me/<?= htmlspecialchars($botUsername) ?>" target="_blank" rel="noopener" class="col-span-2 text-center py-3 rounded-xl text-white font-bold text-sm" style="background:<?= $accent ?>">🔄 تمدید از طریق ربات</a>
                <?php endif; ?>
                <?php if ($telegramSupport !== '' && str_starts_with($telegramSupport, '@')): ?>
                    <a href="https://t.me/<?= htmlspecialchars(ltrim($telegramSupport, '@')) ?>" target="_blank" rel="noopener" class="py-2.5 rounded-xl text-center text-xs font-bold border border-slate-700 text-slate-200">☎️ پشتیبانی تلگرام</a>
                <?php endif; ?>
                <?php if ($whatsappSupport !== ''): ?>
                    <a href="https://wa.me/<?= htmlspecialchars(ltrim($whatsappSupport, '+')) ?>" target="_blank" rel="noopener" class="py-2.5 rounded-xl text-center text-xs font-bold border border-slate-700 text-slate-200">💬 واتس‌اپ</a>
                <?php endif; ?>
            </div>

            <a href="<?= htmlspecialchars(Helpers::url('client/logout')) ?>" class="block text-center text-[11px] text-slate-500 pt-1 pb-2">خروج از حساب ←</a>
        </div>
    <?php endif; ?>

</div>
</body>
</html>

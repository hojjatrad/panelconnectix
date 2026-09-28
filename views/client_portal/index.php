<?php
/**
 * Connectix Panel - Client Self-Service Portal
 * Clean, Responsive & Beautiful Customer Self-Service Dashboard
 */
/** @var array|null $client */
$client = $client ?? null;
$brandName = htmlspecialchars((string)($client['brand_name'] ?? 'Connectix VPN'));
$logoUrl = (string)($client['logo_url'] ?? '');
$theme = (string)($client['theme_color'] ?? 'violet');
$themeMap = [
    'violet' => '#8b5cf6',
    'blue' => '#3b82f6',
    'emerald' => '#10b981',
    'rose' => '#f43f5e',
    'amber' => '#f59e0b',
    'cyan' => '#06b6d4'
];
$accent = $themeMap[$theme] ?? '#8b5cf6';
$err = (string)($error ?? '');

// Load App Release Manifest
$manifestPath = dirname(__DIR__, 2) . '/app_release.json';
$manifest = [];
if (is_file($manifestPath)) {
    $manifest = @json_decode(file_get_contents($manifestPath), true) ?: [];
}
$appVersion = (string)($manifest['version'] ?? '3.5.1');
$apkUniversalUrl = (string)($manifest['apk']['universal'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.1/Connectix-Android-Universal.apk');
$apkArm64Url = (string)($manifest['apk']['arm64'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.1/Connectix-Android-ARM64.apk');
$windowsUrl = (string)($manifest['windows']['url'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.1/Connectix-Windows-x64.zip');

// Sanitized Client Data
$clientUsername = htmlspecialchars((string)($client['username'] ?? 'user'));
$clientPassword = htmlspecialchars((string)(!empty($client['password']) ? $client['password'] : '123456'));
$serverName = htmlspecialchars((string)(!empty($client['server_name']) ? $client['server_name'] : 'سرور ابری'));
$subToken = (string)($client['sub_token'] ?? '');
$subUrl = $subToken !== '' ? Helpers::subUrl($subToken) : '';
$subUrlEncoded = urlencode($subUrl);

$usedBytes = (int)($client['traffic_used_bytes'] ?? 0);
$limitBytes = (int)($client['traffic_limit_bytes'] ?? 0);
$remBytes = max(0, $limitBytes - $usedBytes);
$usagePct = $limitBytes > 0 ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0;
$daysLeft = $client ? Helpers::daysRemaining($client['expire_at'] ?? null) : '';
$expired = $client && !empty($client['expire_at']) && strtotime((string)$client['expire_at']) <= time();

$statusFa = match ($client['status'] ?? '') {
    'active' => ['🟢 فعال و متصل', 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20'],
    'expired' => ['🔴 منقضی شده', 'text-rose-400 bg-rose-500/10 border-rose-500/20'],
    'disabled' => ['⏸ غیرفعال موقت', 'text-amber-400 bg-amber-500/10 border-amber-500/20'],
    default => ['⚪ ' . ($client['status'] ?? 'نامشخص'), 'text-slate-400 bg-slate-800 border-slate-700'],
};

// Support & Renewal URLs
$rawTelegram = trim((string)($client['telegram_support'] ?? ''));
$telegramUsername = ltrim($rawTelegram, '@');
$telegramUrl = $telegramUsername !== '' ? 'https://t.me/' . $telegramUsername : '';

$rawWhatsapp = trim((string)($client['whatsapp_support'] ?? ''));
$whatsappPhone = preg_replace('/[^0-9]/', '', $rawWhatsapp);
$whatsappUrl = $whatsappPhone !== '' ? 'https://wa.me/' . $whatsappPhone : '';

$renewalUrl = trim((string)($client['renewal_url'] ?? ''));
$rawBot = trim((string)($client['reseller_bot_username'] ?? ''));
$botUsername = ltrim($rawBot, '@');
$botUrl = $botUsername !== '' ? 'https://t.me/' . $botUsername : '';

$clientIpLimit = (int)($client['ip_limit'] ?? 0);
$userLimitText = $clientIpLimit > 0 ? "{$clientIpLimit} دستگاه" : "نامحدود";
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $brandName ?> | پورتال خودخدمت مشتری</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-3 md:p-6 selection:bg-purple-600 selection:text-white relative overflow-x-hidden">
    <!-- Ambient Glow Background Effects -->
    <div class="fixed -top-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed -bottom-40 -left-40 w-96 h-96 bg-cyan-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-lg space-y-4 relative z-10 py-4">

        <!-- Header & Branding -->
        <div class="flex items-center justify-between bg-slate-900/80 border border-slate-800 rounded-3xl p-4 shadow-xl backdrop-blur-md">
            <div class="flex items-center gap-3">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="w-11 h-11 rounded-2xl object-cover border border-slate-700 shadow-md">
                <?php else: ?>
                    <div class="w-11 h-11 rounded-2xl bg-purple-600/20 text-purple-400 border border-purple-500/30 flex items-center justify-center text-xl font-bold shadow-md">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="font-extrabold text-base md:text-lg text-white leading-tight"><?= $brandName ?></h1>
                    <p class="text-[11px] text-slate-400">پورتال خودخدمت و مدیریت اشتراک</p>
                </div>
            </div>

            <?php if ($client): ?>
                <a href="<?= Helpers::url('client/logout') ?>" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-950/60 text-slate-300 hover:text-rose-300 border border-slate-700 hover:border-rose-800 text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-right-from-bracket text-[11px]"></i>
                    <span>خروج</span>
                </a>
            <?php else: ?>
                <a href="<?= Helpers::url('apps') ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-purple-600/20 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="fa-solid fa-download text-[11px]"></i>
                    <span>دانلود اپلیکیشن</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$client): ?>
            <!-- ==================== LOGIN VIEW ==================== -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 md:p-8 shadow-2xl backdrop-blur-xl space-y-5">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-right-to-bracket text-purple-400"></i>
                        <span>ورود به حساب اشتراک</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">نام کاربری و کلمه عبور اختصاصی خود را وارد فرمایید:</p>
                </div>

                <?php if ($err !== ''): ?>
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5 font-semibold">
                        <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                        <span><?= htmlspecialchars($err) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= Helpers::url('client/login') ?>" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::csrfToken() ?>">

                    <div>
                        <label class="text-xs text-slate-300 font-semibold block mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-user text-purple-400 text-xs"></i>
                            <span>نام کاربری</span>
                        </label>
                        <input name="username" required autofocus placeholder="username" class="w-full bg-slate-950 border border-slate-700/80 rounded-2xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-purple-500 transition" dir="ltr">
                    </div>

                    <div>
                        <label class="text-xs text-slate-300 font-semibold block mb-1.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-key text-purple-400 text-xs"></i>
                            <span>کلمه عبور</span>
                        </label>
                        <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-700/80 rounded-2xl px-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-purple-500 transition" dir="ltr">
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold rounded-2xl text-xs shadow-lg shadow-purple-900/30 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>ورود به پورتال</span>
                    </button>
                </form>

                <div class="pt-2 border-t border-slate-800 text-center">
                    <p class="text-[11px] text-slate-400">
                        همان نام کاربری و رمزی است که در اپلیکیشن وارد می‌کنید.
                    </p>
                </div>
            </div>

        <?php else: ?>
            <!-- ==================== LOGGED IN DASHBOARD ==================== -->

            <!-- Expired Notice Banner -->
            <?php if ($expired || ($limitBytes > 0 && $remBytes <= 0)): ?>
                <div class="p-4 bg-rose-950/80 border border-rose-600/70 rounded-3xl text-xs text-rose-200 space-y-2 shadow-lg">
                    <div class="flex items-center gap-2 font-bold text-rose-300">
                        <i class="fa-solid fa-triangle-exclamation text-base text-rose-400"></i>
                        <span>اشتراک شما منقضی یا حجم آن به پایان رسیده است!</span>
                    </div>
                    <p class="text-[11px] text-slate-300 leading-relaxed">جهت اتصال مجدد و جلوگیری از قطع دائم سرویس، نسبت به تمدید اشتراک اقدام فرمایید.</p>
                </div>
            <?php endif; ?>

            <!-- Card 1: User Account & Credentials -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 md:p-6 shadow-xl space-y-4 backdrop-blur-md">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div>
                        <span class="text-[10px] text-slate-400 block mb-0.5">مشخصات اشتراک</span>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-circle-user text-purple-400"></i>
                            <span class="font-mono" dir="ltr"><?= $clientUsername ?></span>
                        </h2>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 rounded-full border <?= $statusFa[1] ?>">
                        <?= $statusFa[0] ?>
                    </span>
                </div>

                <!-- Username & Password Credentials Box -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-3 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="text-[10px] text-slate-400 block">نام کاربری:</span>
                            <span class="font-mono font-bold text-xs text-white" dir="ltr"><?= $clientUsername ?></span>
                        </div>
                        <button type="button" data-copy="<?= $clientUsername ?>" onclick="copyFromData(this)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-[11px] font-semibold border border-slate-700 transition flex items-center gap-1">
                            <i class="fa-regular fa-copy"></i>
                            <span>کپی</span>
                        </button>
                    </div>

                    <div class="bg-slate-950/70 border border-purple-900/30 rounded-2xl p-3 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <span class="text-[10px] text-slate-400 block">کلمه عبور اشتراک:</span>
                            <span class="font-mono font-bold text-xs text-purple-300" dir="ltr"><?= $clientPassword ?></span>
                        </div>
                        <button type="button" data-copy="<?= $clientPassword ?>" onclick="copyFromData(this)" class="px-2.5 py-1 bg-slate-800 hover:bg-purple-600 text-slate-300 hover:text-white rounded-xl text-[11px] font-semibold border border-slate-700 transition flex items-center gap-1">
                            <i class="fa-regular fa-copy"></i>
                            <span>کپی</span>
                        </button>
                    </div>
                </div>

                <!-- Server & Limits Details -->
                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                    <div class="bg-slate-950/50 rounded-2xl p-3 border border-slate-800/80">
                        <span class="text-[10px] text-slate-400 block mb-0.5">سرور متصل:</span>
                        <span class="font-bold text-slate-200"><?= $serverName ?></span>
                    </div>

                    <div class="bg-slate-950/50 rounded-2xl p-3 border border-slate-800/80">
                        <span class="text-[10px] text-slate-400 block mb-0.5">سقف اتصال همزمان:</span>
                        <span class="font-bold text-purple-300 font-mono"><?= $userLimitText ?></span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Traffic Usage & Time Expiry -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 md:p-6 shadow-xl space-y-4 backdrop-blur-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie text-cyan-400"></i>
                        <span>مصرف ترافیک اینترنت</span>
                    </span>
                    <span class="text-xs font-mono font-bold text-white"><?= Helpers::formatBytes($usedBytes) ?> <span class="text-slate-400 font-normal">از</span> <?= $limitBytes > 0 ? Helpers::formatBytes($limitBytes) : 'نامحدود' ?></span>
                </div>

                <!-- Progress Bar -->
                <div>
                    <div class="w-full bg-slate-950 rounded-full h-3 overflow-hidden p-0.5 border border-slate-800">
                        <div class="h-2 rounded-full <?= $usagePct >= 90 ? 'bg-rose-500' : ($usagePct >= 75 ? 'bg-amber-500' : 'bg-gradient-to-r from-purple-500 to-cyan-500') ?> transition-all duration-500" style="width: <?= min(100, $usagePct) ?>%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1.5">
                        <span>باقیمانده: <strong class="text-emerald-400 font-bold"><?= $limitBytes > 0 ? Helpers::formatBytes($remBytes) : 'نامحدود' ?></strong></span>
                        <span class="font-mono"><?= $usagePct ?>% مصرف شده</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-clock text-amber-400"></i>
                        <span class="text-slate-400">اعتبار زمانی:</span>
                    </div>
                    <strong class="<?= $expired ? 'text-rose-400' : 'text-amber-300' ?>"><?= $daysLeft ?></strong>
                </div>
            </div>

            <!-- Card 3: Dedicated Official Client Apps (Android & Windows) -->
            <div class="bg-gradient-to-br from-purple-950/60 via-slate-900 to-indigo-950/50 border border-purple-500/40 rounded-3xl p-5 md:p-6 space-y-4 shadow-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-purple-600 flex items-center justify-center text-white text-base shadow-md">
                            <i class="fa-solid fa-rocket"></i>
                        </span>
                        <div>
                            <h3 class="font-bold text-white text-xs md:text-sm">اپلیکیشن اختصاصی <?= $brandName ?></h3>
                            <p class="text-[10px] text-purple-300">ورود آسان فقط با نام کاربری و پسورد بالا (بدون نیاز به لینک و تنظیمات)</p>
                        </div>
                    </div>
                    <span class="text-[9px] px-2.5 py-0.5 rounded-full font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        پیشنهادی
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                    <a href="<?= htmlspecialchars($apkUniversalUrl) ?>" class="py-3 px-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-2xl font-bold flex items-center justify-center gap-2 transition shadow-md text-center">
                        <i class="fa-brands fa-android text-base"></i>
                        <span>دانلود مستقیم نسخه اندروید</span>
                    </a>

                    <a href="<?= htmlspecialchars($windowsUrl) ?>" class="py-3 px-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-2xl font-bold flex items-center justify-center gap-2 transition shadow-md text-center">
                        <i class="fa-brands fa-windows text-base"></i>
                        <span>دانلود مستقیم نسخه ویندوز</span>
                    </a>
                </div>

                <div class="flex items-center justify-between pt-1 border-t border-slate-800/80 text-[11px] text-slate-400">
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-shield-check text-purple-400 text-[11px]"></i>
                        <span>تست پینگ لحظه‌ای و انتخاب خودکار خلوت‌ترین سرور</span>
                    </span>
                    <a href="<?= Helpers::url('apps') ?>" target="_blank" class="text-purple-400 hover:text-purple-300 font-bold flex items-center gap-1 transition">
                        <span>راهنمای تصویری</span>
                        <i class="fa-solid fa-arrow-left text-[9px]"></i>
                    </a>
                </div>
            </div>

            <!-- Card 4: Sublink & 1-Click VPN Connect -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 md:p-6 shadow-xl space-y-4 backdrop-blur-md">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-xs font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-link text-purple-400"></i>
                        <span>لینک اشتراک هوشمند (Sublink)</span>
                    </h3>
                    <button type="button" data-copy="<?= htmlspecialchars($subUrl) ?>" onclick="copyFromData(this)" class="px-3 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow">
                        <i class="fa-regular fa-copy"></i>
                        <span>کپی لینک</span>
                    </button>
                </div>

                <!-- Input Box -->
                <div class="space-y-1">
                    <input type="text" readonly value="<?= htmlspecialchars($subUrl) ?>" onclick="this.select()" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 font-mono text-[11px] text-slate-300 select-all focus:outline-none" dir="ltr">
                </div>

                <!-- QR Code Display -->
                <div class="text-center pt-2 space-y-2">
                    <span class="text-[11px] text-slate-400 block font-semibold">اسکن مستقیم با بارکدخوان نرم‌افزارها:</span>
                    <div class="bg-white p-3 rounded-2xl inline-block shadow-lg">
                        <div id="qrcode" class="w-40 h-40 flex items-center justify-center"></div>
                    </div>
                </div>

                <!-- 1-Click Import into VPN Apps -->
                <div class="pt-3 border-t border-slate-800 space-y-2">
                    <span class="text-[11px] text-slate-400 block font-semibold text-center">اتصال با یک کلیک در برنامه‌های استاندارد:</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <a href="hiddify://install-sub?url=<?= $subUrlEncoded ?>" class="py-2.5 px-3 bg-slate-950 hover:bg-purple-900/40 rounded-xl border border-slate-800 text-purple-300 flex items-center justify-center gap-2 transition font-semibold">
                            <i class="fa-solid fa-bolt"></i>
                            <span>ورود به Hiddify</span>
                        </a>

                        <a href="v2rayng://install-config?url=<?= $subUrlEncoded ?>" class="py-2.5 px-3 bg-slate-950 hover:bg-slate-800 rounded-xl border border-slate-800 text-emerald-400 flex items-center justify-center gap-2 transition font-semibold">
                            <i class="fa-brands fa-android"></i>
                            <span>ورود به V2rayNG</span>
                        </a>

                        <a href="streisand://import/<?= $subUrlEncoded ?>" class="py-2.5 px-3 bg-slate-950 hover:bg-slate-800 rounded-xl border border-slate-800 text-slate-200 flex items-center justify-center gap-2 transition font-semibold">
                            <i class="fa-brands fa-apple"></i>
                            <span>ورود به Streisand</span>
                        </a>

                        <a href="sing-box://import-remote-profile?url=<?= $subUrlEncoded ?>" class="py-2.5 px-3 bg-slate-950 hover:bg-slate-800 rounded-xl border border-slate-800 text-cyan-300 flex items-center justify-center gap-2 transition font-semibold">
                            <i class="fa-solid fa-box"></i>
                            <span>ورود به Sing-box</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 5: Renewal & Customer Support -->
            <?php 
            $hasRenewal = ($renewalUrl !== '' || $botUrl !== '');
            $hasTelegram = ($telegramUrl !== '');
            $hasWhatsapp = ($whatsappUrl !== '');
            if ($hasRenewal || $hasTelegram || $hasWhatsapp): 
            ?>
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-3 backdrop-blur-md">
                <span class="text-xs text-slate-400 block text-center font-semibold">نیاز به تمدید یا ارتباط با پشتیبانی دارید؟</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 text-xs font-semibold">
                    <?php if ($renewalUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($renewalUrl) ?>" target="_blank" class="py-3 px-3 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-center shadow transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>تمدید آنلاین اشتراک</span>
                        </a>
                    <?php elseif ($botUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($botUrl) ?>" target="_blank" class="py-3 px-3 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-center shadow transition flex items-center justify-center gap-2">
                            <i class="fa-brands fa-telegram"></i>
                            <span>تمدید از طریق ربات</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasTelegram): ?>
                        <a href="<?= htmlspecialchars($telegramUrl) ?>" target="_blank" class="py-3 px-3 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 rounded-xl text-center transition flex items-center justify-center gap-2">
                            <i class="fa-brands fa-telegram text-sm"></i>
                            <span>پشتیبانی تلگرام</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasWhatsapp): ?>
                        <a href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" class="py-3 px-3 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-xl text-center transition flex items-center justify-center gap-2">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>پشتیبانی واتس‌اپ</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Clean Logout Link -->
            <div class="text-center pt-1 pb-4">
                <a href="<?= Helpers::url('client/logout') ?>" class="text-xs text-slate-500 hover:text-rose-400 transition inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-right-from-bracket text-[10px]"></i>
                    <span>خروج از حساب کاربری</span>
                </a>
            </div>

        <?php endif; ?>

    </div>

    <script>
        // Render QR Code
        <?php if ($client && $subUrl !== ''): ?>
        try {
            if (typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("qrcode"), {
                    text: "<?= $subUrl ?>",
                    width: 160,
                    height: 160,
                    colorDark : "#0f172a",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            } else {
                document.getElementById("qrcode").innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= $subUrlEncoded ?>" alt="QR" class="w-40 h-40">';
            }
        } catch(e) {
            document.getElementById("qrcode").innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= $subUrlEncoded ?>" alt="QR" class="w-40 h-40">';
        }
        <?php endif; ?>

        // Copy from data attribute
        function copyFromData(btn) {
            const val = btn.getAttribute('data-copy') || '';
            copyToClipboard(val, btn);
        }

        // Robust Copy to Clipboard with zero error
        function copyToClipboard(text, btnElement) {
            const origHtml = btnElement.innerHTML;
            const showSuccess = function() {
                btnElement.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> کپی شد!';
                setTimeout(function() {
                    btnElement.innerHTML = origHtml;
                }, 2000);
            };

            if (!navigator.clipboard) {
                const ta = document.createElement("textarea");
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                document.body.removeChild(ta);
                showSuccess();
                return;
            }

            navigator.clipboard.writeText(text).then(showSuccess).catch(function() {
                const ta = document.createElement("textarea");
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                document.body.removeChild(ta);
                showSuccess();
            });
        }
    </script>
</body>
</html>

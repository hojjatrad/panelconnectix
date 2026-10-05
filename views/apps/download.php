<?php
/**
 * Connectix Panel - Public Apps Download & Connection Guides Center
 */
$manifest = $manifest ?? [];
$appVersion = $manifest['version'] ?? '3.7.0';
$apkUniversalUrl = $manifest['apk']['universal'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.7.0/Connectix-Android-Universal.apk';
$apkArm64Url = $manifest['apk']['arm64'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.7.0/Connectix-Android-ARM64.apk';
$apkArm32Url = $manifest['apk']['arm32'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.7.0/Connectix-Android-ARM32.apk';
$windowsUrl = $manifest['windows']['url'] ?? 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.7.0/Connectix-Windows-x64.zip';
$iosSibappUrl = $manifest['ios']['sibapp'] ?? 'https://sibapp.com/applications/connectix-vpn';
$iosAnardoniUrl = $manifest['ios']['anardoni'] ?? 'https://anardoni.com/applications/connectix-vpn';
$iosTestFlightUrl = $manifest['ios']['testflight'] ?? 'https://testflight.apple.com/join/connectix';
$iosIpaUrl = $manifest['ios']['ipa'] ?? (method_exists('Helpers','fullAssetUrl') ? Helpers::fullAssetUrl('Connectix-iOS-3.6.1.ipa') : '/assets/Connectix-iOS-3.6.1.ipa');
$brandName = htmlspecialchars($brandName ?? 'Connectix VPN');
$logoUrl = $logoUrl ?? '';
$guides = $guides ?? [];

$guidesByPlatform = [
    'android' => [],
    'ios' => [],
    'windows' => [],
    'macos' => [],
    'linux' => []
];

foreach ($guides as $g) {
    $p = $g['platform'] ?? 'android';
    if (isset($guidesByPlatform[$p])) {
        $guidesByPlatform[$p][] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title><?= $brandName ?> | مرکز دانلود نرم‌افزارها و راهنمای اتصال</title>
    <!-- v3.5.8 SAFE: Local assets for Iran -->
    <?php
    $base = method_exists('Helpers','basePath') ? Helpers::basePath() : '';
    $localTailwind = __DIR__ . '/../../assets/js/tailwind.js';
    $localFA = __DIR__ . '/../../assets/css/fontawesome.min.css';
    $localVazir = __DIR__ . '/../../assets/css/vazirmatn.css';
    ?>
    <?php if (file_exists($localTailwind)): ?>
    <script src="<?= $base ?>/assets/js/tailwind.js"></script>
    <?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    <?php if (file_exists($localFA)): ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/fontawesome.min.css">
    <?php else: ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>
    <?php if (file_exists($localVazir)): ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/vazirmatn.css">
    <?php else: ?>
    <style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');</style>
    <?php endif; ?>
    <style>* { font-family: 'Vazirmatn', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-purple-600 selection:text-white relative overflow-x-hidden">
    <!-- Ambient Background Lighting -->
    <div class="fixed -top-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed top-1/2 -left-40 w-96 h-96 bg-cyan-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed -bottom-40 right-1/4 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Navigation Header -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-10 w-10 object-contain rounded-xl">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center text-white shadow-lg shadow-purple-600/30">
                        <i class="fa-solid fa-shield-halved text-lg"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="font-extrabold text-white text-base md:text-lg leading-tight"><?= $brandName ?></h1>
                    <p class="text-[10px] text-slate-400">مرکز رسمی دانلود اپلیکیشن و راهنمای اتصال</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= Helpers::url('client') ?>" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-user text-[11px]"></i>
                    <span>ورود به پورتال</span>
                </a>
                <?php if (!empty($supportTelegram)): ?>
                    <a href="https://t.me/<?= ltrim($supportTelegram, '@') ?>" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold shadow-md transition flex items-center gap-1.5">
                        <i class="fa-brands fa-telegram text-[12px]"></i>
                        <span>پشتیبانی</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8 md:py-12 space-y-12 relative z-10">
        <!-- Hero Section -->
        <div class="text-center space-y-4 max-w-3xl mx-auto">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-bold">
                <i class="fa-solid fa-sparkles text-amber-400"></i>
                <span>نگارش جدید ۳.۶.۱ اپلیکیشن اختصاصی اندروید و iOS منتشر شد</span>
            </div>
            <div class="flex justify-center">
              <a href="<?= $base ?>/ios-guide" class="group inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-black shadow-lg shadow-indigo-900/30 transition-all">
                <i class="fa-solid fa-circle-play group-hover:scale-110 transition"></i>
                <span>📱 راهنمای کامل نصب آیفون با ویدیو و تصویر - جدید!</span>
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
              </a>
            </div>
            <h2 class="text-2xl md:text-4xl font-black text-white leading-tight">
                دانلود نرم‌افزارهای اتصال و <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-pink-400 to-cyan-400">راهنمای هوشمند</span>
            </h2>
            <p class="text-xs md:text-sm text-slate-300 leading-relaxed">
                برای تجربه بالاترین سرعت و اتصال پایدار بدون قطعی، از <b>اپلیکیشن اختصاصی ما</b> استفاده فرمایید؛ ورود آسان تنها با نام کاربری و رمز عبور اشتراک بدون نیاز به تنظیم دستی.
            </p>
        </div>

        <!-- Section 1: Our Dedicated Client Software (Android & Windows) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white text-sm shadow-md">
                        <i class="fa-solid fa-star"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-white">نرم‌افزار اختصاصی <?= $brandName ?> (پیشنهادی ویژه)</h3>
                        <p class="text-[11px] text-purple-300">بدون نیاز به لینک، کانفیگ یا فیلترشکن واسط؛ ورود سریع با نام کاربری و پسورد</p>
                    </div>
                </div>
                <span class="hidden sm:inline-block px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    نسخه <?= $appVersion ?>
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <!-- Android Card -->
                <div class="bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-purple-950/20 border border-emerald-500/30 rounded-3xl p-6 shadow-xl flex flex-col justify-between space-y-5 relative overflow-hidden group hover:border-emerald-500/60 transition-all">
                    <div class="absolute -top-12 -left-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
                    
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-2xl shadow-inner">
                                    <i class="fa-brands fa-android"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-white flex items-center gap-2">
                                        <span>نسخه اختصاصی اندروید</span>
                                        <span class="text-[9px] px-2 py-0.5 rounded font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">رسمی</span>
                                    </h4>
                                    <span class="text-[11px] text-slate-400 font-mono">v<?= $appVersion ?> • سازگار با اندروید ۵.۰ به بالا</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-300 leading-relaxed">
                            پایدارترین روش اتصال در تمامی اپراتورها (همراه اول، ایرانسل، مخابرات، رایتل). مجهز به تست لحظه‌ای پینگ، اتصال هوشمند به خلوت‌ترین سرور، دور زدن فیلترینگ شدید و مصرف کم باتری.
                        </p>

                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 pt-1">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-xs"></i>
                                <span>ورود با یوزرنیم و پسورد</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-xs"></i>
                                <span>انتخاب خودکار سرور</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-xs"></i>
                                <span>پشتیبانی از Reality و VLESS</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-emerald-400 text-xs"></i>
                                <span>به‌روزرسانی خودکار درون‌برنامه‌ای</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 pt-3 border-t border-slate-800/80 relative z-10">
                        <a href="<?= htmlspecialchars($apkUniversalUrl) ?>" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-900/30 transition-all">
                            <i class="fa-solid fa-download text-sm"></i>
                            <span>دانلود مستقیم نسخه Universal (پیشنهادی - تمام گوشی‌ها)</span>
                        </a>

                        <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                            <a href="<?= htmlspecialchars($apkArm64Url) ?>" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-center font-semibold border border-slate-700/80 transition flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-microchip text-slate-400"></i>
                                <span>نسخه ARM64 (مدرن)</span>
                            </a>
                            <a href="<?= htmlspecialchars($apkArm32Url) ?>" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-center font-semibold border border-slate-700/80 transition flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-microchip text-slate-400"></i>
                                <span>نسخه ARM32 (قدیمی)</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- iOS Card - NEW in 3.6.1 -->
                <div class="bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-indigo-950/20 border border-indigo-500/30 rounded-3xl p-6 shadow-xl flex flex-col justify-between space-y-5 relative overflow-hidden group hover:border-indigo-500/60 transition-all">
                    <div class="absolute -top-12 -left-12 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition-all"></div>
                    
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center text-2xl shadow-inner">
                                    <i class="fa-brands fa-apple"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-white flex items-center gap-2">
                                        <span>نسخه اختصاصی آیفون (iOS)</span>
                                        <span class="text-[9px] px-2 py-0.5 rounded font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">جدید 3.6.1</span>
                                    </h4>
                                    <span class="text-[11px] text-slate-400 font-mono">v<?= $appVersion ?> • iOS 12 به بالا - iPhone & iPad</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-300 leading-relaxed">
                            اپ اختصاصی iOS با Network Extension، اتصال پایدار، فیلتر خودکار اپ‌های بانکی، انتخاب هوشمند سرور، مصرف کم باتری. قابل نصب از سیب‌اپ، اناردونی و TestFlight.
                        </p>

                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 pt-1">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-indigo-400 text-xs"></i>
                                <span>Network Extension اختصاصی</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-indigo-400 text-xs"></i>
                                <span>فیلتر خودکار بانکی</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-indigo-400 text-xs"></i>
                                <span>اتصال 3 مرحله‌ای هوشمند</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-indigo-400 text-xs"></i>
                                <span>سیب‌اپ + اناردونی</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 pt-3 border-t border-slate-800/80 relative z-10">
                        <a href="<?= htmlspecialchars($iosSibappUrl) ?>" target="_blank" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-indigo-900/30 transition-all">
                            <i class="fa-solid fa-download text-sm"></i>
                            <span>دانلود از سیب‌اپ (پیشنهادی - با شماره ایرانی)</span>
                        </a>
                        <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                            <a href="<?= htmlspecialchars($iosAnardoniUrl) ?>" target="_blank" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-center font-semibold border border-slate-700/80 transition flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-apple-whole text-slate-400"></i>
                                <span>اناردونی</span>
                            </a>
                            <a href="<?= htmlspecialchars($iosTestFlightUrl) ?>" target="_blank" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-center font-semibold border border-slate-700/80 transition flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-flask text-slate-400"></i>
                                <span>TestFlight</span>
                            </a>
                        </div>
                        <p class="text-[10px] text-slate-400 text-center">یا فایل IPA مستقیم: <a href="<?= htmlspecialchars($iosIpaUrl) ?>" class="text-indigo-400 hover:underline">دانلود IPA</a> + <a href="<?= $base ?>/ios-guide" class="text-emerald-400 hover:underline font-bold"><i class="fa-solid fa-circle-play ml-1"></i>راهنمای تصویری و ویدیویی نصب آیفون</a></p>
                    </div>
                </div>

                <!-- Windows Card -->
                <div class="bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-cyan-950/20 border border-cyan-500/30 rounded-3xl p-6 shadow-xl flex flex-col justify-between space-y-5 relative overflow-hidden group hover:border-cyan-500/60 transition-all">
                    <div class="absolute -top-12 -left-12 w-32 h-32 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all"></div>

                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-2xl shadow-inner">
                                    <i class="fa-brands fa-windows"></i>
                                </div>
                                <div>
                                    <h4 class="text-base font-bold text-white flex items-center gap-2">
                                        <span>نسخه اختصاصی ویندوز</span>
                                        <span class="text-[9px] px-2 py-0.5 rounded font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">رسمی</span>
                                    </h4>
                                    <span class="text-[11px] text-slate-400 font-mono">v<?= $appVersion ?> • ویندوز ۱۰ و ۱۱ (64-bit)</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-300 leading-relaxed">
                            کلاینت کامپیوتر مجهز به حالت System Proxy و VPN Mode (تونل کل ترافیک سیستم). بدون نیاز به نصب نرم‌افزارهای جانبی، پکیج‌های اضافی یا تنظیم دستی کارت شبکه.
                        </p>

                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 pt-1">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-cyan-400 text-xs"></i>
                                <span>حالت VPN برای کل ویندوز</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-cyan-400 text-xs"></i>
                                <span>حالت System Proxy مرورگر</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-cyan-400 text-xs"></i>
                                <span>پشتیبانی از Reality Core</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-check text-cyan-400 text-xs"></i>
                                <span>مصرف فوق‌العاده ناچیز RAM</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800/80 relative z-10">
                        <a href="<?= htmlspecialchars($windowsUrl) ?>" class="w-full py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-bold rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-cyan-900/30 transition-all">
                            <i class="fa-solid fa-file-zipper text-sm"></i>
                            <span>دانلود مستقیم نرم‌افزار ویندوز (Windows 64-bit ZIP)</span>
                        </a>
                        <p class="text-[10px] text-slate-400 text-center mt-2">فایل را استخراج (Extract) کرده و Connectix.exe را اجرا نمایید.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Simple 3-Step Setup Guide -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 md:p-8 space-y-6 shadow-xl">
            <div class="text-center space-y-1">
                <h3 class="text-lg font-bold text-white flex items-center justify-center gap-2">
                    <i class="fa-solid fa-graduation-cap text-amber-400"></i>
                    <span>راهنمای ۳ مرحله‌ای اتصال با اپلیکیشن اختصاصی</span>
                </h3>
                <p class="text-xs text-slate-400">تنها در کمتر از ۳۰ ثانیه بدون پیچیدگی متصل شوید</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                <div class="bg-slate-950/60 border border-slate-800/90 rounded-2xl p-5 space-y-3 relative">
                    <div class="w-8 h-8 rounded-full bg-purple-600 text-white font-bold text-sm flex items-center justify-center shadow">۱</div>
                    <h4 class="font-bold text-sm text-white">نصب برنامه</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        فایل اندروید (APK) یا نسخه ویندوز را از دکمه‌های بالا دانلود نموده و روی دستگاه خود نصب فرمایید.
                    </p>
                </div>

                <div class="bg-slate-950/60 border border-slate-800/90 rounded-2xl p-5 space-y-3 relative">
                    <div class="w-8 h-8 rounded-full bg-cyan-600 text-white font-bold text-sm flex items-center justify-center shadow">۲</div>
                    <h4 class="font-bold text-sm text-white">ورود با نام کاربری و رمز</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        برنامه را اجرا کرده و نام کاربری و کلمه عبور اختصاصی اشتراک خود (دریافتی از ربات یا پنل) را وارد کنید.
                    </p>
                </div>

                <div class="bg-slate-950/60 border border-slate-800/90 rounded-2xl p-5 space-y-3 relative">
                    <div class="w-8 h-8 rounded-full bg-emerald-600 text-white font-bold text-sm flex items-center justify-center shadow">۳</div>
                    <h4 class="font-bold text-sm text-white">لمس دکمه اتصال</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        دکمه اتصال را لمس کنید؛ برنامه سریع‌ترین سرور را انتخاب کرده و فوراً ارتباط امن برقرار خواهد شد.
                    </p>
                </div>
            </div>
        </div>

        <!-- Section 3: Alternative & Third-Party Clients (iOS, Mac, etc.) -->
        <div class="space-y-6">
            <div class="border-b border-slate-800 pb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-shapes text-cyan-400"></i>
                        <span>سایر نرم‌افزارهای سازگار و کلاینت‌های استاندارد</span>
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">اگر تمایل دارید از طریق ساب‌لینک (Sublink) به نرم‌افزارهایی مثل Streisand یا v2rayNG متصل شوید:</p>
                </div>
            </div>

            <!-- Tabs / Platform Sections -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- iOS Section -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-apple text-xl text-slate-200"></i>
                            <h4 class="font-bold text-white text-sm">آیفون و آیپد (iOS)</h4>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">App Store</span>
                    </div>

                    <div class="space-y-3">
                        <?php 
                        $iosApps = $guidesByPlatform['ios'] ?? [];
                        if (empty($iosApps)): 
                        ?>
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-white">Streisand (پیشنهادی آیفون)</strong>
                                    <a href="https://apps.apple.com/app/streisand/id6450534064" target="_blank" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[10px] font-bold transition">دانلود از اپ‌استور</a>
                                </div>
                                <p class="text-[11px] text-slate-400">رایگان، بسیار سریع و سازگار با همراه اول و ایرانسل</p>
                            </div>
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-white">V2Box (کلاینت جایگزین iOS)</strong>
                                    <a href="https://apps.apple.com/app/v2box-v2ray-client/id6446814042" target="_blank" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-[10px] font-bold border border-slate-700 transition">دانلود از اپ‌استور</a>
                                </div>
                                <p class="text-[11px] text-slate-400">پشتیبانی کامل از ساب‌لینک هوشمند و پینگ تست آنی</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($iosApps as $a): ?>
                                <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-xs text-white"><?= htmlspecialchars($a['app_name']) ?></strong>
                                        <a href="<?= htmlspecialchars($a['download_url']) ?>" target="_blank" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[10px] font-bold transition shrink-0">دانلود</a>
                                    </div>
                                    <?php if (!empty($a['description'])): ?>
                                        <p class="text-[11px] text-slate-400 leading-relaxed"><?= htmlspecialchars($a['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Android Alternative Section -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-android text-xl text-emerald-400"></i>
                            <h4 class="font-bold text-white text-sm">سایر برنامه‌های اندروید (v2ray / sing-box)</h4>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">Google Play & GitHub</span>
                    </div>

                    <div class="space-y-3">
                        <?php 
                        $androidAlt = array_filter($guidesByPlatform['android'] ?? [], fn($x) => !str_contains($x['app_name'], 'Connectix'));
                        if (empty($androidAlt)): 
                        ?>
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-white">v2rayNG (پیشنهادی)</strong>
                                    <a href="https://github.com/2dust/v2rayNG/releases" target="_blank" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold transition">دانلود مستقیم</a>
                                </div>
                                <p class="text-[11px] text-slate-400">کلاینت متن‌باز و استاندارد برای پروتکل‌های Reality و VLESS</p>
                            </div>
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-white">NapsternetV</strong>
                                    <a href="https://play.google.com/store/apps/details?id=com.napsternetlabs.napsternetv" target="_blank" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-[10px] font-bold border border-slate-700 transition">دانلود از گوگل پلی</a>
                                </div>
                                <p class="text-[11px] text-slate-400">نرم‌افزار کمکی برای اینترنت‌های با اختلال بالا</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($androidAlt as $a): ?>
                                <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-xs text-white"><?= htmlspecialchars($a['app_name']) ?></strong>
                                        <a href="<?= htmlspecialchars($a['download_url']) ?>" target="_blank" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold transition shrink-0">دانلود</a>
                                    </div>
                                    <?php if (!empty($a['description'])): ?>
                                        <p class="text-[11px] text-slate-400 leading-relaxed"><?= htmlspecialchars($a['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Windows Alternative Section -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-windows text-xl text-cyan-400"></i>
                            <h4 class="font-bold text-white text-sm">سایر برنامه‌های ویندوز (NekoRay / v2rayN)</h4>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">Windows</span>
                    </div>

                    <div class="space-y-3">
                        <?php 
                        $winAlt = array_filter($guidesByPlatform['windows'] ?? [], fn($x) => !str_contains($x['app_name'], 'Connectix'));
                        if (empty($winAlt)): 
                        ?>
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-white">NekoRay</strong>
                                    <a href="https://github.com/MatsuriDayo/nekoray/releases" target="_blank" class="px-2.5 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-[10px] font-bold transition">دانلود نسخه ویندوز</a>
                                </div>
                                <p class="text-[11px] text-slate-400">دارای قابلیت VPN Mode برای عبور دادن کلیه نرم‌افزارهای ویندوز</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($winAlt as $a): ?>
                                <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-xs text-white"><?= htmlspecialchars($a['app_name']) ?></strong>
                                        <a href="<?= htmlspecialchars($a['download_url']) ?>" target="_blank" class="px-2.5 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-[10px] font-bold transition shrink-0">دانلود</a>
                                    </div>
                                    <?php if (!empty($a['description'])): ?>
                                        <p class="text-[11px] text-slate-400 leading-relaxed"><?= htmlspecialchars($a['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- macOS Section -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-apple text-xl text-purple-300"></i>
                            <h4 class="font-bold text-white text-sm">مک‌بوک (macOS)</h4>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">Mac App Store</span>
                    </div>

                    <div class="space-y-3">
                        <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-1">
                            <div class="flex items-center justify-between">
                                <strong class="text-xs text-white">FoXray (مک‌بوک)</strong>
                                <a href="https://apps.apple.com/app/foxray/id6448898396" target="_blank" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[10px] font-bold transition">دانلود از اپ‌استور</a>
                            </div>
                            <p class="text-[11px] text-slate-400">کلاینت بومی و سبک برای لپ‌تاپ‌ها و کامپیوترهای اپل (M1, M2, M3 و Intel)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: How to use Sublinks in Alternative Clients -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 space-y-3">
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-link text-purple-400"></i>
                <span>آموزش استفاده از لینک اشتراک (Sublink) در سایر کلاینت‌ها</span>
            </h4>
            <p class="text-xs text-slate-300 leading-relaxed">
                در صورتی که از نرم‌افزارهای عمومی نظیر v2rayNG یا Streisand استفاده می‌فرمایید:
            </p>
            <ol class="list-decimal list-inside text-xs text-slate-400 space-y-1.5 leading-relaxed pr-2">
                <li>لینک اشتراک اختصاصی (Sublink) یا بارکد QR ارسالی از ربات یا صفحه پورتال را کپی نمایید.</li>
                <li>در نرم‌افزار مورد نظر، علامت <b>+</b> یا <b>Import</b> را انتخاب کنید.</li>
                <li>گزینه <b>Import config from Clipboard</b> یا <b>Scan QR Code</b> را لمس کنید تا تمام سرورها بارگذاری شوند.</li>
                <li>سرور با کمترین تاخیر (پینگ) را انتخاب و متصل شوید.</li>
            </ol>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-8 text-center text-xs text-slate-500 relative z-10">
        <p><?= $brandName ?> &copy; <?= date('Y') ?> — تمامی حقوق محفوظ است.</p>
        <p class="text-[10px] text-slate-600 mt-1">توسعه‌یافته با پروتکل‌های امن نسل جدید</p>
    </footer>
</body>
</html>

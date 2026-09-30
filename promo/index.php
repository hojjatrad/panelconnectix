<?php
// Standalone Reseller Landing Page - No Auth Required
// Accessible via https://yourdomain.com/promo or https://yourdomain.com/contax/promo
// v5.7.1 - Connectix Reseller Promo
$brand = 'Connectix';
$telegram_support = '@YourSupport'; // <- اینجا آیدی پشتیبانی خودت رو بذار
$telegram_bot_demo = '@YourBrandBot';
$domain = (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'https') . '://' . $_SERVER['HTTP_HOST'] : 'https://vpbotn.ir/contax');
$base = rtrim($domain, '/') . '/';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل نمایندگی VPN با اپ اختصاصی | کسب درآمد ماهانه 10 تا 25 میلیون | <?= htmlspecialchars($brand) ?></title>
    <meta name="description" content="پنل نمایندگی VPN با اپ اندروید اختصاصی، ربات تلگرام فروش خودکار، سود 200%، سرور VLESS Reality ضدفیلتر. از 299 هزار تومان شروع کنید.">
    <meta name="keywords" content="نمایندگی VPN, پنل VPN, اپ اختصاصی VPN, کسب درآمد VPN, فروش VPN, ربات VPN">
    <meta property="og:title" content="پنل نمایندگی VPN با اپ اختصاصی - درآمد میلیونی">
    <meta property="og:description" content="اپ با برند خودت + ربات فروش 24 ساعته + سود 200% - از 299ت">
    <meta property="og:image" content="<?= $base ?>assets/ai_guides/reseller-panel.jpg">
    <meta name="theme-color" content="#7C3AED">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = { darkMode: 'class', corePlugins: { preflight: true } }
    </script>
    <style>
        *{font-family: Vazirmatn, sans-serif}
        html{scroll-behavior: smooth}
        .glass{backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px)}
        .gradient-text{background: linear-gradient(90deg,#7C3AED,#06B6D4); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text}
        .gradient-bg{background: linear-gradient(135deg,#7C3AED 0%,#06B6D4 100%)}
        .gradient-border{border-image: linear-gradient(90deg,#7C3AED,#06B6D4) 1}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .float{animation: float 6s ease-in-out infinite}
        @keyframes pulse-glow{0%,100%{box-shadow:0 0 20px rgba(124,58,237,.4)}50%{box-shadow:0 0 40px rgba(124,58,237,.7)}}
        .glow{animation: pulse-glow 2s ease-in-out infinite}
    </style>
</head>
<body class="bg-[#0B0F1A] text-white antialiased overflow-x-hidden">

<!-- NAV -->
<nav class="fixed top-0 w-full z-50 bg-[#0B0F1A]/80 glass border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 gradient-bg rounded-xl flex items-center justify-center font-black text-white">C</div>
                <span class="font-black text-xl"><?= htmlspecialchars($brand) ?></span>
                <span class="hidden sm:inline text-xs bg-white/10 border border-white/20 rounded-full px-2.5 py-1 mr-2">v5.7.1 AI</span>
            </div>
            <div class="hidden md:flex items-center gap-6 text-sm text-white/70">
                <a href="#features" class="hover:text-white transition">امکانات</a>
                <a href="#app" class="hover:text-white transition">اپ اختصاصی</a>
                <a href="#pricing" class="hover:text-white transition">تعرفه</a>
                <a href="#faq" class="hover:text-white transition">سوالات</a>
            </div>
            <div class="flex items-center gap-2">
                <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="hidden sm:flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl px-4 py-2 text-sm transition">
                    <i class="fa-brands fa-telegram text-sky-400"></i> پشتیبانی
                </a>
                <a href="#pricing" class="gradient-bg hover:opacity-90 rounded-xl px-5 py-2.5 text-sm font-bold shadow-lg shadow-violet-600/20 transition">شروع کنید</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="relative pt-32 pb-20 px-4 overflow-hidden">
    <div class="absolute inset-0 -z-10">
        <div class="absolute top-20 right-10 w-96 h-96 bg-violet-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-20 left-10 w-96 h-96 bg-cyan-500/15 rounded-full blur-[120px]"></div>
    </div>
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
        <div class="space-y-6">
            <div class="inline-flex items-center gap-2 bg-violet-500/10 border border-violet-500/30 rounded-full px-4 py-1.5 text-xs">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                <span class="text-violet-300">🔥 جشنواره: 3 ماه + 1 ماه هدیه + 500ت شارژ</span>
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-[52px] font-black leading-[1.15]">
                پنل نمایندگی VPN<br>
                با <span class="gradient-text">اپ اختصاصی</span><br>
                و ربات فروش خودکار
            </h1>
            <p class="text-white/60 text-base sm:text-lg leading-relaxed max-w-xl">
                بدون دانش فنی، با برند خودت کسب و کار VPN راه بنداز. اپ اندروید اختصاصی، ربات تلگرام 24 ساعته، سود 200% هر فروش. میانگین درآمد نمایندگان: <b class="text-white">10 تا 25 میلیون در ماه</b>
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="gradient-bg hover:opacity-90 rounded-2xl px-8 py-4 font-bold flex items-center gap-2 shadow-xl shadow-violet-600/25 glow transition">
                    <i class="fa-solid fa-rocket"></i> درخواست نمایندگی - 30 دقیقه تحویل
                </a>
                <a href="#demo" class="bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl px-6 py-4 font-bold flex items-center gap-2 transition">
                    <i class="fa-solid fa-play"></i> دیدن دمو
                </a>
            </div>
            <div class="flex items-center gap-6 pt-2 text-xs text-white/50">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> بدون نیاز به کدنویسی</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> تحویل آنی</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> پشتیبانی 24/7</span>
            </div>
            <div class="grid grid-cols-3 gap-4 pt-6 max-w-md">
                <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black gradient-text">200+</div>
                    <div class="text-[11px] text-white/50 mt-1">نماینده فعال</div>
                </div>
                <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-white">50K+</div>
                    <div class="text-[11px] text-white/50 mt-1">کاربر نهایی</div>
                </div>
                <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-emerald-400">99.9%</div>
                    <div class="text-[11px] text-white/50 mt-1">آپتایم</div>
                </div>
            </div>
        </div>
        <div class="relative">
            <div class="relative bg-gradient-to-b from-white/[0.08] to-white/[0.02] border border-white/10 rounded-[2rem] p-3 shadow-2xl float">
                <img src="<?= $base ?>assets/ai_guides/reseller-panel.jpg" alt="پنل نماینده" class="w-full rounded-[1.5rem] border border-white/10">
                <div class="absolute -bottom-6 -right-6 bg-[#0B0F1A] border border-white/20 rounded-2xl p-4 shadow-xl flex items-center gap-3">
                    <div class="w-12 h-12 bg-emerald-500/20 border border-emerald-500/30 rounded-xl flex items-center justify-center"><i class="fa-solid fa-chart-line text-emerald-400"></i></div>
                    <div>
                        <div class="text-xs text-white/50">سود امروز</div>
                        <div class="font-black text-emerald-400">+2,450,000 تومان</div>
                    </div>
                </div>
                <div class="absolute -top-6 -left-6 bg-[#0B0F1A] border border-white/20 rounded-2xl p-3 shadow-xl">
                    <div class="flex items-center gap-2 text-xs"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span> 12 فروش امروز</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TRUST -->
<section class="border-y border-white/10 bg-white/[0.02] py-4">
    <div class="max-w-7xl mx-auto px-4 flex flex-wrap justify-center gap-8 text-xs text-white/40">
        <span class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-violet-400"></i> پرداخت امن زرین‌پال</span>
        <span class="flex items-center gap-2"><i class="fa-brands fa-bitcoin text-amber-400"></i> پرداخت تتر و تون</span>
        <span class="flex items-center gap-2"><i class="fa-solid fa-bolt text-cyan-400"></i> تحویل آنی 30 دقیقه</span>
        <span class="flex items-center gap-2"><i class="fa-solid fa-headset text-emerald-400"></i> پشتیبانی 24/7</span>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-20 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300 mb-4">چرا Connectix؟</div>
            <h2 class="text-3xl sm:text-4xl font-black leading-tight">همه چیز برای یک کسب و کار کامل</h2>
            <p class="text-white/50 mt-3 text-sm">شما فقط پنل نمی‌خرید، یک بیزینس آماده با برند خودتان تحویل می‌گیرید</p>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <!-- 1 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 gradient-bg rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-mobile-screen"></i></div>
                <h3 class="font-bold">اپ اختصاصی با برند شما</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">نام، لوگو، رنگ شما. Universal + ARM64. ارزش بیرون 20 میلیون، اینجا رایگان در پلن حرفه‌ای</p>
                <div class="mt-4 flex gap-2">
                    <img src="<?= $base ?>assets/ai_guides/app-download.jpg" class="w-20 h-14 object-cover rounded-lg border border-white/10">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step1-copy-link.jpg" class="w-20 h-14 object-cover rounded-lg border border-white/10">
                </div>
            </div>
            <!-- 2 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-sky-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-brands fa-telegram"></i></div>
                <h3 class="font-bold">ربات تلگرام فروش خودکار</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">ربات با یوزرنیم دلخواه شما. فروش 24 ساعته، پرداخت کارت، تتر، تون. شما خواب، ربات می‌فروشد!</p>
                <div class="mt-3 text-[11px] bg-sky-500/10 border border-sky-500/20 rounded-xl px-3 py-2">💬 @YourBrandBot - همین الان تست کنید</div>
            </div>
            <!-- 3 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-sack-dollar"></i></div>
                <h3 class="font-bold">سود 200% - قیمت دست شما</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">خرید 40ت، فروش 120ت، سود 80ت. تخفیف تا 50% + شارژ هدیه + پورسانت 10% معرفی</p>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[10px]">
                    <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl py-2"><b class="block text-emerald-400 text-sm">80k</b>سود هر فروش</div>
                    <div class="bg-white/5 border border-white/10 rounded-xl py-2"><b class="block text-white text-sm">5</b>فروش روزانه</div>
                    <div class="bg-violet-500/10 border border-violet-500/20 rounded-xl py-2"><b class="block text-violet-300 text-sm">12م</b>درآمد ماه</div>
                </div>
            </div>
            <!-- 4 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-cyan-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-cyan-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-server"></i></div>
                <h3 class="font-bold">سرور VLESS Reality ضدفیلتر</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">آلمان، فنلاند، ترکیه، ایران‌اکسس. مقاوم‌ترین پروتکل 2024، آپتایم 99.9%، بدون قطعی</p>
                <img src="<?= $base ?>assets/ai_guides/server-status.jpg" class="mt-4 w-full h-28 object-cover rounded-xl border border-white/10">
            </div>
            <!-- 5 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-amber-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-amber-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-robot"></i></div>
                <h3 class="font-bold">هوش مصنوعی پاسخگو (جدید)</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">پاسخ خودکار به تیکت با متن + عکس راهنمای 3 مرحله‌ای. 80% تیکت‌های تکراری کم می‌شود!</p>
                <div class="mt-4 flex gap-2">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step2-import.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step3-connect.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                    <img src="<?= $base ?>assets/ai_guides/troubleshooting.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                </div>
            </div>
            <!-- 6 -->
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-emerald-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-indigo-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-users"></i></div>
                <h3 class="font-bold">پلن اختصاصی + ساب‌نماینده</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">حجم، مدت، سقف اتصال دلخواه بسازید. نماینده زیرمجموعه بگیرید و از فروشش پورسانت بگیرید</p>
                <img src="<?= $base ?>assets/ai_guides/billing-report.jpg" class="mt-4 w-full h-28 object-cover rounded-xl border border-white/10">
            </div>
        </div>
    </div>
</section>

<!-- APP SECTION -->
<section id="app" class="py-20 px-4 bg-white/[0.02] border-y border-white/10">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
        <div class="order-2 lg:order-1 relative">
            <div class="grid grid-cols-2 gap-4">
                <img src="<?= $base ?>assets/ai_guides/hiddify-step1-copy-link.jpg" class="rounded-[1.5rem] border border-white/10 shadow-xl">
                <img src="<?= $base ?>assets/ai_guides/hiddify-step2-import.jpg" class="rounded-[1.5rem] border border-white/10 shadow-xl mt-8">
                <img src="<?= $base ?>assets/ai_guides/hiddify-step3-connect.jpg" class="rounded-[1.5rem] border border-white/10 shadow-xl">
                <img src="<?= $base ?>assets/ai_guides/app-download.jpg" class="rounded-[1.5rem] border border-white/10 shadow-xl mt-8">
            </div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-[#0B0F1A] border border-white/20 rounded-full w-20 h-20 flex items-center justify-center shadow-2xl">
                <i class="fa-solid fa-play text-2xl gradient-text"></i>
            </div>
        </div>
        <div class="order-1 lg:order-2 space-y-6">
            <div class="inline-flex bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3 py-1 text-[11px] text-emerald-300">📱 اپ اختصاصی - White Label</div>
            <h2 class="text-3xl sm:text-4xl font-black leading-tight">اپ با برند شما<br>یعنی <span class="gradient-text">3 برابر فروش بیشتر</span></h2>
            <p class="text-white/60 text-sm leading-relaxed">90% مشتریان از طریق اپ خرید می‌کنند. وقتی اپ با نام و لوگوی شما را نصب می‌کنند، هر روز برند شما را می‌بینند و وفادار می‌مانند.</p>
            <div class="space-y-3">
                <div class="flex gap-3 bg-white/[0.04] border border-white/10 rounded-2xl p-4">
                    <div class="w-10 h-10 bg-violet-500/20 rounded-xl flex items-center justify-center shrink-0"><i class="fa-solid fa-palette text-violet-400"></i></div>
                    <div><div class="font-bold text-sm">شخصی‌سازی کامل</div><div class="text-xs text-white/50 mt-1">نام، لوگو، رنگ، آیکون، اسپلش - هیچ جا نام Connectix نیست</div></div>
                </div>
                <div class="flex gap-3 bg-white/[0.04] border border-white/10 rounded-2xl p-4">
                    <div class="w-10 h-10 bg-cyan-500/20 rounded-xl flex items-center justify-center shrink-0"><i class="fa-solid fa-bolt text-cyan-400"></i></div>
                    <div><div class="font-bold text-sm">دو نسخه بهینه</div><div class="text-xs text-white/50 mt-1">Universal (همه گوشی‌ها) + ARM64 (30% سریع‌تر برای گوشی‌های جدید)</div></div>
                </div>
                <div class="flex gap-3 bg-white/[0.04] border border-white/10 rounded-2xl p-4">
                    <div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center shrink-0"><i class="fa-solid fa-rotate text-emerald-400"></i></div>
                    <div><div class="font-bold text-sm">آپدیت مادام‌العمر رایگان</div><div class="text-xs text-white/50 mt-1">هر آپدیت Hiddify، اپ شما هم آپدیت می‌شود</div></div>
                </div>
            </div>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 text-xs">
                <div class="font-bold text-amber-300">💡 هزینه ساخت بیرون: 15 تا 30 میلیون + 2 ماه زمان</div>
                <div class="text-white/60 mt-1">در Connectix: 0 تومان + 24 ساعت تحویل (در پلن حرفه‌ای)</div>
            </div>
        </div>
    </div>
</section>

<!-- PRICING -->
<section id="pricing" class="py-20 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl sm:text-4xl font-black">تعرفه شفاف، سود بالا</h2>
            <p class="text-white/50 mt-3 text-sm">از 299 هزار تومان شروع کنید، هر وقت خواستید ارتقا دهید. بدون قرارداد بلندمدت</p>
            <div class="inline-flex mt-4 bg-amber-500/10 border border-amber-500/30 rounded-full px-4 py-2 text-xs text-amber-300">🎉 جشنواره: 3 ماه بخر، 1 ماه هدیه + 500ت شارژ</div>
        </div>
        <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            <!-- Starter -->
            <div class="bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-7 flex flex-col">
                <div class="text-xs text-white/40">شروع کسب و کار</div>
                <h3 class="text-xl font-black mt-1">🥉 استارتر</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">299</span><span class="text-sm text-white/50">هزار تومان / ماه</span></div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/70 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 20% تخفیف خرید عمده</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات تلگرام اختصاصی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پنل مدیریت کامل</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 50 کلاینت</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی تلگرام</li>
                </ul>
                <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="mt-6 bg-white/10 hover:bg-white/15 border border-white/20 rounded-xl py-3 text-center text-sm font-bold transition">شروع با استارتر</a>
            </div>
            <!-- Pro -->
            <div class="relative bg-gradient-to-b from-violet-600/20 to-violet-600/5 border border-violet-500/40 rounded-[1.8rem] p-7 flex flex-col shadow-2xl shadow-violet-600/10 scale-[1.02]">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-4 py-1 text-[10px] font-bold">⭐ پرفروش‌ترین</div>
                <div class="text-xs text-violet-300">حرفه‌ای - پیشنهاد ما</div>
                <h3 class="text-xl font-black mt-1">🥈 حرفه‌ای</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">599</span><span class="text-sm text-white/50">هزار تومان / ماه</span></div>
                <div class="mt-1 text-[11px] text-white/40 line-through">900 هزار تومان</div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/80 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>35% تخفیف</b> خرید عمده</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات + <b>اپ اندروید اختصاصی</b></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پلن اختصاصی نامحدود ⭐</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ساب‌نماینده + پورسانت تیمی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> API اختصاصی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 200 کلاینت</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> هوش مصنوعی پاسخگو</li>
                </ul>
                <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="mt-6 gradient-bg rounded-xl py-3 text-center text-sm font-bold shadow-lg shadow-violet-600/20 hover:opacity-90 transition">شروع با حرفه‌ای - 30 دقیقه تحویل</a>
            </div>
            <!-- Business -->
            <div class="bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-7 flex flex-col">
                <div class="text-xs text-amber-300">امپراتوری VPN</div>
                <h3 class="text-xl font-black mt-1">🥇 بیزینس</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">1.29</span><span class="text-sm text-white/50">میلیون / ماه</span></div>
                <div class="mt-1 text-[11px] text-white/40 line-through">2.5 میلیون</div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/70 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>50% تخفیف</b> - نصف قیمت!</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> همه چیز حرفه‌ای +</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> اپ iOS اختصاصی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> دامنه اختصاصی + سایت فروش</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی VIP + مشاوره بازاریابی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> کلاینت نامحدود</li>
                </ul>
                <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="mt-6 bg-white/10 hover:bg-white/15 border border-white/20 rounded-xl py-3 text-center text-sm font-bold transition">مشاوره بیزینس</a>
            </div>
        </div>
        <div class="text-center mt-8 text-[11px] text-white/40">💳 پرداخت: کارت به کارت، زرین‌پال، تتر TRC20، تون | بدون قرارداد | لغو آنی</div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="py-16 px-4 bg-white/[0.02] border-y border-white/10">
    <div class="max-w-7xl mx-auto">
        <h3 class="text-center font-black text-xl mb-8">نمایندگان موفق چی می‌گن؟</h3>
        <div class="grid md:grid-cols-3 gap-5">
            <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-5">
                <div class="flex gap-1 text-amber-400 text-xs">★★★★★</div>
                <p class="text-xs text-white/70 mt-3 leading-relaxed">"ماه اول 180 تا فروختم، 14 میلیون سود. اپ اختصاصی خیلی اعتماد مشتری رو جلب می‌کنه. عالیه!"</p>
                <div class="mt-4 flex items-center gap-2"><div class="w-8 h-8 bg-violet-500 rounded-full flex items-center justify-center text-xs font-bold">ر</div><div><div class="text-xs font-bold">آقای رضایی - NovinNet</div><div class="text-[10px] text-white/40">400 اشتراک فعال</div></div></div>
            </div>
            <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-5">
                <div class="flex gap-1 text-amber-400 text-xs">★★★★★</div>
                <p class="text-xs text-white/70 mt-3 leading-relaxed">"رباتش فوق‌العاده‌ست. من سر کارم، ربات خودش می‌فروشه و کانفیگ می‌ده. واقعاً 24 ساعته‌ست."</p>
                <div class="mt-4 flex items-center gap-2"><div class="w-8 h-8 bg-cyan-500 rounded-full flex items-center justify-center text-xs font-bold">م</div><div><div class="text-xs font-bold">خانم محمدی - VIP VPN</div><div class="text-[10px] text-white/40">250 اشتراک فعال</div></div></div>
            </div>
            <div class="bg-white/[0.04] border border-white/10 rounded-2xl p-5">
                <div class="flex gap-1 text-amber-400 text-xs">★★★★★</div>
                <p class="text-xs text-white/70 mt-3 leading-relaxed">"هوش مصنوعی جدیدش 80% تیکت‌ها رو خودش جواب می‌ده با عکس! دیگه لازم نیست همه رو خودم جواب بدم."</p>
                <div class="mt-4 flex items-center gap-2"><div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-xs font-bold">ا</div><div><div class="text-xs font-bold">آقای احمدی - Tehran VPN</div><div class="text-[10px] text-white/40">600 اشتراک فعال</div></div></div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-20 px-4 max-w-4xl mx-auto">
    <h2 class="text-3xl font-black text-center">سوالات متداول</h2>
    <div class="mt-10 space-y-3">
        <details class="group bg-white/[0.04] border border-white/10 rounded-2xl p-5 open:bg-white/[0.06] transition">
            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-sm">آیا نیاز به دانش فنی دارم؟ <i class="fa-solid fa-chevron-down group-open:rotate-180 transition"></i></summary>
            <p class="text-xs text-white/60 mt-3 leading-relaxed">خیر! همه چیز آماده است. پنل، ربات، اپ. فقط لینک ربات را به مشتری می‌دهید. آموزش صفر تا صد هم داریم.</p>
        </details>
        <details class="group bg-white/[0.04] border border-white/10 rounded-2xl p-5 open:bg-white/[0.06] transition">
            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-sm">مشتری از کجا بیارم؟ <i class="fa-solid fa-chevron-down group-open:rotate-180 transition"></i></summary>
            <p class="text-xs text-white/60 mt-3 leading-relaxed">پیج اینستا، کانال تلگرام، دیوار، دوستان. ما روش‌های جذب مشتری (تست رایگان، تخفیف، رفرال) را آموزش می‌دهیم. میانگین هر نماینده ماه اول 30-50 مشتری جذب می‌کند.</p>
        </details>
        <details class="group bg-white/[0.04] border border-white/10 rounded-2xl p-5 open:bg-white/[0.06] transition">
            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-sm">قیمت فروش دست کیه؟ <i class="fa-solid fa-chevron-down group-open:rotate-180 transition"></i></summary>
            <p class="text-xs text-white/60 mt-3 leading-relaxed">100% دست شما! مثلاً پلن 30 گیگ را 40ت می‌خرید، 120ت می‌فروشید. 80ت سود شما. می‌توانید 100ت یا 150ت هم بفروشید.</p>
        </details>
        <details class="group bg-white/[0.04] border border-white/10 rounded-2xl p-5 open:bg-white/[0.06] transition">
            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-sm">اپ اختصاصی چقدر طول می‌کشه؟ <i class="fa-solid fa-chevron-down group-open:rotate-180 transition"></i></summary>
            <p class="text-xs text-white/60 mt-3 leading-relaxed">24 تا 48 ساعت. لوگو و نام برند را بفرستید، ما اپ را می‌سازیم و APK تحویل می‌دهیم. آپدیت مادام‌العمر رایگان.</p>
        </details>
        <details class="group bg-white/[0.04] border border-white/10 rounded-2xl p-5 open:bg-white/[0.06] transition">
            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-sm">سرورها فیلتر می‌شه؟ <i class="fa-solid fa-chevron-down group-open:rotate-180 transition"></i></summary>
            <p class="text-xs text-white/60 mt-3 leading-relaxed">پروتکل VLESS Reality مقاوم‌ترین پروتکل حال حاضر است و به سختی قابل تشخیص است. آپتایم 99.9% داریم.</p>
        </details>
    </div>
</section>

<!-- CTA -->
<section class="py-20 px-4">
    <div class="max-w-4xl mx-auto text-center bg-gradient-to-b from-violet-600/20 to-cyan-500/10 border border-violet-500/30 rounded-[2rem] p-10 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-violet-600/20 rounded-full blur-[80px]"></div>
        <h2 class="text-3xl sm:text-4xl font-black leading-tight">آماده‌ای کسب و کارت رو شروع کنی؟</h2>
        <p class="text-white/60 mt-3 text-sm">همین حالا پیام بده، 30 دقیقه دیگه پنلت آماده‌ست. جشنواره محدود!</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="gradient-bg rounded-2xl px-10 py-4 font-black flex items-center gap-2 shadow-xl shadow-violet-600/20 hover:opacity-90 transition">
                <i class="fa-brands fa-telegram text-xl"></i> درخواست نمایندگی در تلگرام
            </a>
        </div>
        <div class="mt-6 text-[11px] text-white/40">🔒 پرداخت امن | ⚡ تحویل آنی | 🎁 500ت شارژ هدیه + 1 ماه هدیه</div>
    </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-white/10 py-10 px-4">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between gap-6 text-xs text-white/40">
        <div>
            <div class="flex items-center gap-2 font-black text-white text-sm"><div class="w-7 h-7 gradient-bg rounded-lg flex items-center justify-center">C</div> <?= htmlspecialchars($brand) ?> v5.7.1</div>
            <div class="mt-2 max-w-sm leading-relaxed">قدرتمندترین پنل نمایندگی VPN ایران با اپ اختصاصی، ربات فروش خودکار و هوش مصنوعی. بیش از 200 نماینده فعال.</div>
        </div>
        <div class="flex gap-8">
            <div>
                <div class="font-bold text-white mb-2">لینک‌ها</div>
                <div class="space-y-1"><a href="#features" class="block hover:text-white">امکانات</a><a href="#pricing" class="block hover:text-white">تعرفه</a><a href="#faq" class="block hover:text-white">سوالات</a></div>
            </div>
            <div>
                <div class="font-bold text-white mb-2">ارتباط</div>
                <div class="space-y-1"><a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="block hover:text-white">تلگرام: <?= htmlspecialchars($telegram_support) ?></a><a href="#" class="block">ربات دمو: <?= htmlspecialchars($telegram_bot_demo) ?></a></div>
            </div>
        </div>
    </div>
    <div class="max-w-7xl mx-auto mt-8 pt-6 border-t border-white/10 text-center text-[11px] text-white/30">© 2025 <?= htmlspecialchars($brand) ?> - همه حقوق محفوظ است. ساخته شده با ❤️ برای نمایندگان ایرانی</div>
</footer>

<!-- Floating CTA Mobile -->
<div class="fixed bottom-0 left-0 right-0 md:hidden bg-[#0B0F1A]/90 glass border-t border-white/10 p-3 z-40">
    <a href="https://t.me/<?= ltrim($telegram_support,'@') ?>" target="_blank" class="gradient-bg rounded-xl py-3.5 flex items-center justify-center gap-2 font-bold text-sm w-full">
        <i class="fa-brands fa-telegram"></i> درخواست نمایندگی - 500ت هدیه
    </a>
</div>

</body>
</html>

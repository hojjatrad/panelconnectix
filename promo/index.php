<?php
// Connectix Ultra Premium Landing - v5.8 - Persian - Full Features
// https://vpbotn.ir/contax/promo/ | https://vpbotn.ir/
// @mainAdminpanel
$brand = 'Connectix';
$tg = '@mainAdminpanel';
$tg_url = 'https://t.me/mainAdminpanel';
$panel_url = 'https://vpbotn.ir/contax/';
$domain = 'https://vpbotn.ir';
$base = $domain . '/contax/';
$success = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reseller_request'])) {
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $tgid = trim($_POST['telegram_id'] ?? '');
    $plan = trim($_POST['plan'] ?? ''); $biz = trim($_POST['business'] ?? ''); $msg = trim($_POST['message'] ?? '');
    if ($name === '' || ($phone === '' && $tgid === '')) { $error = 'لطفاً نام و یک راه ارتباطی (موبایل یا آیدی تلگرام) را وارد کنید.'; }
    else {
        @file_put_contents(__DIR__.'/requests.log', date('Y-m-d H:i:s')." | $name | $phone | $tgid | $plan | $biz | $msg | IP:".($_SERVER['REMOTE_ADDR']??'')."\n", FILE_APPEND);
        try {
            $root = dirname(__DIR__);
            if (file_exists($root.'/config.php')) {
                require_once $root.'/config.php'; require_once $root.'/core/Database.php'; require_once $root.'/core/Setting.php'; require_once $root.'/core/TelegramBot.php';
                $pdo = Database::getConnection(); $token = Setting::get('telegram_bot_token',''); $admin = Setting::get('telegram_admin_id',''); $logCh = Setting::get('bot_log_channel','') ?: Setting::get('telegram_log_channel_id','');
                $text = "🔥 <b>درخواست جدید نمایندگی از لندینگ</b>\n\n👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tgid\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n🌐 https://vpbotn.ir/contax/promo/\n🕐 ".date('Y-m-d H:i:s');
                if ($admin) TelegramBot::sendMessage($text,$admin,null,$token); if ($logCh) TelegramBot::sendMessage($text,$logCh,null,$token);
            }
        } catch (Throwable $e) {}
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل نمایندگی VPN با اپ اختصاصی فارسی | سود 200% | Connectix - @mainAdminpanel</title>
<meta name="description" content="پنل نمایندگی VPN فارسی با اپ اندروید اختصاصی، ربات تلگرام فروش خودکار 24 ساعته، سود 200%، سرور VLESS Reality ضدفیلتر، هوش مصنوعی با راهنمای تصویری. درخواست: @mainAdminpanel">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{font-family:Vazirmatn,system-ui!important}
html{scroll-behavior:smooth}
body{background:#05070D;color:#fff;overflow-x:hidden}
.glass{backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
.gradient-text{background:linear-gradient(90deg,#8B5CF6 0%,#06B6D4 50%,#10B981 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.gradient-bg{background:linear-gradient(135deg,#7C3AED 0%,#4F46E5 30%,#06B6D4 100%)}
.gradient-border{position:relative}.gradient-border::before{content:'';position:absolute;inset:0;border-radius:inherit;padding:1px;background:linear-gradient(135deg,#7C3AED,#06B6D4);-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
@keyframes float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-12px) rotate(1deg)}}
.float{animation:float 6s ease-in-out infinite}
@keyframes glow{0%,100%{box-shadow:0 0 30px rgba(124,58,237,.3),0 0 60px rgba(6,182,214,.15)}50%{box-shadow:0 0 50px rgba(124,58,237,.5),0 0 80px rgba(6,182,214,.25)}}
.glow{animation:glow 3s ease-in-out infinite}
@keyframes shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(200%)}}
.shimmer{position:relative;overflow:hidden}.shimmer::after{content:'';position:absolute;top:0;left:0;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);animation:shimmer 2.5s infinite}
.card-hover{transition:all .4s cubic-bezier(.4,0,.2,1)}.card-hover:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.4),0 0 0 1px rgba(124,58,237,.3)}
.text-balance{text-wrap:balance}
::-webkit-scrollbar{width:8px}::-webkit-scrollbar-track{background:#0B0F1A}::-webkit-scrollbar-thumb{background:#7C3AED;border-radius:4px}
</style>
</head>
<body class="antialiased selection:bg-violet-500/30">

<!-- Background Effects -->
<div class="fixed inset-0 -z-10 overflow-hidden">
<div class="absolute top-[-20%] right-[-15%] w-[800px] h-[800px] bg-violet-600/20 rounded-full blur-[150px]"></div>
<div class="absolute top-[30%] left-[-10%] w-[600px] h-[600px] bg-cyan-500/15 rounded-full blur-[130px]"></div>
<div class="absolute bottom-[-20%] right-[20%] w-[700px] h-[700px] bg-indigo-600/15 rounded-full blur-[140px]"></div>
<div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.01)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.01)_1px,transparent_1px)] bg-[size:50px_50px]"></div>
</div>

<!-- NAV -->
<nav class="fixed top-0 w-full z-50 bg-[#05070D]/70 glass border-b border-white/[0.06]">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between items-center h-[68px]">
<div class="flex items-center gap-3">
<div class="w-10 h-10 gradient-bg rounded-[12px] flex items-center justify-center font-black text-white shadow-lg shadow-violet-600/20">C</div>
<div><div class="font-black text-[17px] leading-none"><?= $brand ?> <span class="text-[10px] bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 rounded-full px-2 py-0.5 mr-1.5">فارسی v5.8</span></div><div class="text-[10px] text-white/40 mt-0.5">پنل نمایندگی حرفه‌ای VPN</div></div>
</div>
<div class="hidden lg:flex items-center gap-1 bg-white/[0.04] border border-white/[0.06] rounded-full p-1">
<a href="#features" class="px-4 py-2 rounded-full text-[13px] text-white/60 hover:text-white hover:bg-white/[0.06] transition">امکانات کامل</a>
<a href="#why" class="px-4 py-2 rounded-full text-[13px] text-white/60 hover:text-white hover:bg-white/[0.06] transition">چرا جذابه؟</a>
<a href="#app" class="px-4 py-2 rounded-full text-[13px] text-white/60 hover:text-white hover:bg-white/[0.06] transition">اپ اختصاصی</a>
<a href="#pricing" class="px-4 py-2 rounded-full text-[13px] text-white/60 hover:text-white hover:bg-white/[0.06] transition">تعرفه</a>
<a href="#faq" class="px-4 py-2 rounded-full text-[13px] text-white/60 hover:text-white hover:bg-white/[0.06] transition">سوالات</a>
</div>
<div class="flex items-center gap-2">
<a href="<?= $tg_url ?>" target="_blank" class="hidden sm:flex items-center gap-2 bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.08] rounded-full px-4 py-2.5 text-[13px] transition"><i class="fa-brands fa-telegram text-sky-400"></i> @mainAdminpanel</a>
<a href="#request" class="gradient-bg hover:opacity-90 rounded-full px-6 py-2.5 text-[13px] font-bold shadow-lg shadow-violet-600/20 transition">ثبت درخواست</a>
</div>
</div>
</div>
</nav>

<!-- HERO -->
<section class="relative pt-36 pb-20 px-4">
<div class="max-w-7xl mx-auto grid lg:grid-cols-[1.1fr_0.9fr] gap-12 items-center">
<div class="space-y-7">
<div class="inline-flex items-center gap-2.5 bg-gradient-to-r from-violet-500/10 to-cyan-500/10 border border-violet-500/20 rounded-full pl-2 pr-4 py-2">
<span class="bg-emerald-500 text-black text-[10px] font-black rounded-full px-2.5 py-1">جدید</span>
<span class="text-[12px] text-violet-200">هوش مصنوعی فارسی با راهنمای تصویری 3 مرحله‌ای + اپ اختصاصی رایگان</span>
</div>
<h1 class="text-[40px] sm:text-[52px] lg:text-[56px] font-black leading-[1.08] text-balance">
پنل نمایندگی VPN<br>
<span class="gradient-text">با اپ اختصاصی فارسی</span><br>
<span class="text-[32px] sm:text-[36px] text-white/90">و درآمد ماهانه 10 تا 25 میلیون</span>
</h1>
<p class="text-[15px] leading-7 text-white/60 max-w-[560px] text-balance">
بدون حتی یک خط کدنویسی، با برند خودت کسب و کار VPN راه بنداز. <b class="text-white">اپ اندروید اختصاصی فارسی</b>، <b class="text-white">ربات تلگرام فروش خودکار 24 ساعته</b>، سود <b class="text-emerald-300">200% هر فروش</b>، سرور <b class="text-white">VLESS Reality ضدفیلتر</b>. همه چیز آماده، فقط بفروش!
</p>
<div class="flex flex-wrap gap-3">
<a href="#request" class="group gradient-bg rounded-full px-8 py-4 font-black text-[14px] flex items-center gap-2.5 shadow-[0_0_40px_rgba(124,58,237,.35)] hover:shadow-[0_0_60px_rgba(124,58,237,.5)] transition-all">
<i class="fa-solid fa-rocket group-hover:translate-x-1 transition-transform"></i> ثبت درخواست نمایندگی - 30 دقیقه تحویل
</a>
<a href="https://t.me/mainAdminpanel" target="_blank" class="group bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.08] rounded-full px-7 py-4 font-bold text-[13px] flex items-center gap-2 transition">
<i class="fa-brands fa-telegram text-sky-400 text-[18px]"></i> مشاوره رایگان: @mainAdminpanel
</a>
</div>
<div class="flex flex-wrap gap-3 pt-2">
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.06] rounded-full px-3.5 py-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400"></i> بدون دانش فنی</span>
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.06] rounded-full px-3.5 py-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400"></i> کاملاً فارسی</span>
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.06] rounded-full px-3.5 py-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400"></i> تحویل آنی 30 دقیقه</span>
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.06] rounded-full px-3.5 py-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400"></i> پشتیبانی 24/7 فارسی</span>
</div>
<div class="grid grid-cols-3 gap-3 max-w-[420px] pt-4">
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center card-hover"><div class="text-[22px] font-black gradient-text">200+</div><div class="text-[10px] text-white/50 mt-1">نماینده فعال فارسی</div><div class="text-[9px] text-emerald-400 mt-1">● آنلاین</div></div>
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center card-hover"><div class="text-[22px] font-black text-white">50K+</div><div class="text-[10px] text-white/50 mt-1">کاربر نهایی راضی</div><div class="text-[9px] text-white/30 mt-1">در حال رشد</div></div>
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center card-hover"><div class="text-[22px] font-black text-emerald-400">99.9%</div><div class="text-[10px] text-white/50 mt-1">آپتایم سرورها</div><div class="text-[9px] text-emerald-400 mt-1">بدون قطعی</div></div>
</div>
</div>
<div class="relative lg:h-[620px]">
<div class="relative bg-gradient-to-b from-white/[0.08] to-white/[0.02] border border-white/[0.08] rounded-[28px] p-3 shadow-[0_0_80px_rgba(124,58,237,.15)] float">
<img src="<?= $base ?>assets/ai_guides/reseller-panel.jpg" alt="پنل مدیریت فارسی" class="w-full rounded-[20px] border border-white/[0.06]">
<div class="absolute -bottom-8 -right-8 bg-[#0A0D18] border border-white/[0.08] rounded-[18px] p-4 shadow-2xl flex items-center gap-3 min-w-[200px]">
<div class="w-11 h-11 bg-emerald-500/15 border border-emerald-500/20 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-arrow-trend-up text-emerald-400"></i></div>
<div><div class="text-[10px] text-white/40">سود امروز - خودکار</div><div class="font-black text-emerald-400 text-[14px]">+2,450,000 تومان</div><div class="text-[9px] text-white/30">12 فروش توسط ربات</div></div>
</div>
<div class="absolute -top-6 -left-6 bg-[#0A0D18] border border-white/[0.08] rounded-full px-4 py-2.5 shadow-xl flex items-center gap-2.5">
<span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,.8)]"></span><span class="text-[11px] font-bold">ربات فروش فعال - 24 ساعته</span>
</div>
<div class="absolute top-1/2 -left-10 bg-[#0A0D18]/80 glass border border-white/[0.08] rounded-[16px] p-3 shadow-xl hidden lg:flex items-center gap-2.5">
<img src="<?= $base ?>assets/ai_guides/hiddify-step3-connect.jpg" class="w-12 h-12 rounded-[10px] object-cover"><div><div class="text-[11px] font-bold">اتصال موفق</div><div class="text-[9px] text-emerald-400">● متصل - VLESS Reality</div></div>
</div>
</div>
<div class="mt-10 grid grid-cols-3 gap-3">
<img src="<?= $base ?>ads/banner-fa-1.jpg" class="rounded-[16px] border border-white/[0.06] h-[88px] object-cover card-hover">
<img src="<?= $base ?>ads/banner-fa-2.jpg" class="rounded-[16px] border border-white/[0.06] h-[88px] object-cover card-hover">
<img src="<?= $base ?>ads/banner-fa-3.jpg" class="rounded-[16px] border border-white/[0.06] h-[88px] object-cover card-hover">
</div>
</div>
</div>
</section>

<!-- LOGOS / TRUST -->
<section class="border-y border-white/[0.06] bg-white/[0.02] py-5">
<div class="max-w-7xl mx-auto px-4 flex flex-wrap justify-center gap-6 sm:gap-10 text-[11px] text-white/35">
<span class="flex items-center gap-2"><span class="w-7 h-7 bg-white/[0.06] rounded-full flex items-center justify-center"><i class="fa-solid fa-shield-halved text-violet-400"></i></span> پرداخت امن زرین‌پال + تتر TRC20 + تون</span>
<span class="flex items-center gap-2"><span class="w-7 h-7 bg-white/[0.06] rounded-full flex items-center justify-center"><i class="fa-solid fa-bolt text-cyan-400"></i></span> تحویل آنی 30 دقیقه‌ای - اتوماتیک</span>
<span class="flex items-center gap-2"><span class="w-7 h-7 bg-white/[0.06] rounded-full flex items-center justify-center"><i class="fa-solid fa-headset text-emerald-400"></i></span> پشتیبانی فارسی 24/7 - @mainAdminpanel</span>
<span class="flex items-center gap-2"><span class="w-7 h-7 bg-white/[0.06] rounded-full flex items-center justify-center"><i class="fa-solid fa-language text-amber-400"></i></span> پنل، اپ، ربات 100% فارسی</span>
</div>
</section>

<!-- WHY ATTRACTIVE -->
<section id="why" class="py-24 px-4 relative">
<div class="max-w-7xl mx-auto">
<div class="text-center max-w-3xl mx-auto mb-16">
<div class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-amber-500/20 rounded-full px-4 py-1.5 text-[11px] text-amber-300"><i class="fa-solid fa-fire"></i> چرا این پنل جذاب و پرفروشه؟</div>
<h2 class="text-[34px] sm:text-[42px] font-black leading-[1.1] mt-5 text-balance">چرا <span class="gradient-text">200 نماینده فعال</span><br>Connectix رو انتخاب کردن؟</h2>
<p class="text-white/50 text-[14px] leading-7 mt-4 text-balance">ما فقط پنل نمی‌فروشیم، یک اکوسیستم کامل کسب و کار با 6 مزیت رقابتی که هیچ‌کجا پیدا نمی‌کنی</p>
</div>
<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
<div class="group relative bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.06] rounded-[24px] p-7 card-hover overflow-hidden">
<div class="absolute top-0 right-0 w-32 h-32 bg-violet-500/10 rounded-full blur-[30px] group-hover:bg-violet-500/20 transition"></div>
<div class="w-12 h-12 gradient-bg rounded-[14px] flex items-center justify-center shadow-lg shadow-violet-600/20"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
<h3 class="font-black text-[16px] mt-5">جادوی وایت‌لیبل کامل</h3>
<p class="text-[12px] text-white/50 leading-6 mt-2.5">مشتری هیچ‌وقت نمی‌فهمه از جای دیگه می‌خری! اپ با نام و لوگوی تو، ربات با یوزرنیم تو، لینک ساب با دامنه تو، حتی صفحه وضعیت اشتراک با برند تو. انگار شرکت خودته!</p>
<div class="mt-4 inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[10px] text-violet-300">ارزش بیرون: 30 میلیون تومان</div>
</div>
<div class="group relative bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.06] rounded-[24px] p-7 card-hover overflow-hidden">
<div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-[30px] group-hover:bg-emerald-500/20 transition"></div>
<div class="w-12 h-12 bg-emerald-500 rounded-[14px] flex items-center justify-center shadow-lg shadow-emerald-500/20"><i class="fa-solid fa-sack-dollar"></i></div>
<h3 class="font-black text-[16px] mt-5">سود خالص 200% - قیمت دست تو</h3>
<p class="text-[12px] text-white/50 leading-6 mt-2.5">تو قیمت فروش رو تعیین می‌کنی، نه ما! مثلاً 30 گیگ رو 40ت می‌خری، 120ت می‌فروشی. 80ت سود خالص. روزی 5 فروش = 400ت، ماهی 12 میلیون. بدون سقف!</p>
<div class="mt-4 flex gap-2"><span class="bg-emerald-500/10 border border-emerald-500/20 rounded-full px-2.5 py-1 text-[10px] text-emerald-300">+15% هدیه شارژ</span><span class="bg-white/[0.04] border border-white/[0.06] rounded-full px-2.5 py-1 text-[10px] text-white/50">پورسانت 10% معرفی</span></div>
</div>
<div class="group relative bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.06] rounded-[24px] p-7 card-hover overflow-hidden">
<div class="absolute top-0 right-0 w-32 h-32 bg-sky-500/10 rounded-full blur-[30px] group-hover:bg-sky-500/20 transition"></div>
<div class="w-12 h-12 bg-sky-500 rounded-[14px] flex items-center justify-center shadow-lg shadow-sky-500/20"><i class="fa-brands fa-telegram"></i></div>
<h3 class="font-black text-[16px] mt-5">ربات فروش 24 ساعته - تو خواب، پول درمیاری</h3>
<p class="text-[12px] text-white/50 leading-6 mt-2.5">ربات با یوزرنیم دلخواه تو، منوهای کاملاً فارسی، پرداخت آنی کارت، تتر، تون. مشتری پرداخت می‌کنه، ربات خودکار کانفیگ می‌ده. نیاز به حضور تو نیست!</p>
<div class="mt-4 text-[10px] bg-sky-500/10 border border-sky-500/20 rounded-full px-3 py-1.5 inline-flex text-sky-300">💬 @YourBrandBot - نمونه: @mainAdminpanel</div>
</div>
</div>
</div>
</section>

<!-- FULL FEATURES LIST -->
<section id="features" class="py-24 px-4 bg-white/[0.02] border-y border-white/[0.06]">
<div class="max-w-7xl mx-auto">
<div class="flex flex-col lg:flex-row justify-between gap-6 mb-12">
<div><div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📋 لیست کامل امکانات پنل - فارسی</div><h2 class="text-[32px] sm:text-[40px] font-black leading-[1.1] mt-4">هرچی برای فروش نیاز داری،<br><span class="gradient-text">اینجاست - 100% فارسی</span></h2></div>
<p class="text-white/50 text-[13px] leading-6 max-w-md lg:text-left">از ساخت کلاینت تا گزارش مالی، از اپ اختصاصی تا هوش مصنوعی. 8 دسته اصلی، 50+ ویژگی. همه فارسی، همه آماده.</p>
</div>

<div class="grid lg:grid-cols-4 gap-5">
<!-- Category 1 -->
<div class="lg:col-span-1 space-y-4">
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 gradient-bg rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-users text-sm"></i></div><div><div class="font-bold text-[13px]">مدیریت مشتریان</div><div class="text-[10px] text-white/40">Client Management</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> ساخت کلاینت با نام و نام خانوادگی فارسی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> مشاهده مصرف لحظه‌ای (چقدر استفاده، چقدر مانده)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> تمدید با یک کلیک + تغییر پلن</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> تغییر حجم، سرور، سقف اتصال</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> حذف، مسدود، ریست ترافیک</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> لینک ساب اختصاصی برای هر مشتری</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> QR کد اتصال</li>
</ul>
</div>
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-emerald-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-chart-pie text-sm"></i></div><div><div class="font-bold text-[13px]">مالی و گزارش</div><div class="text-[10px] text-white/40">Billing & Reports</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> فاکتور ماهانه خودکار فارسی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> محاسبه سود خالص (فروش - خرید)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> نمودار درآمد روزانه/ماهانه</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> خروجی Excel / CSV فارسی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> کیف پول با هدیه پلکانی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> تاریخچه تراکنش‌ها</li>
</ul>
</div>
</div>
<!-- Category 2 -->
<div class="lg:col-span-1 space-y-4">
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-sky-500 rounded-[12px] flex items-center justify-center"><i class="fa-brands fa-telegram text-sm"></i></div><div><div class="font-bold text-[13px]">ربات تلگرام فارسی</div><div class="text-[10px] text-white/40">Telegram Bot</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> یوزرنیم دلخواه (@YourBrandBot)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> منوهای کاملاً فارسی (خرید، تمدید، حساب من)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> فروش خودکار 24 ساعته</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> پرداخت: کارت، زرین‌پال، تتر، تون، کیف پول</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> ارسال خودکار کانفیگ بعد پرداخت</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> تست رایگان خودکار</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> زیرمجموعه‌گیری و پورسانت</li>
</ul>
</div>
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-amber-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-mobile-screen text-sm"></i></div><div><div class="font-bold text-[13px]">اپ اختصاصی فارسی</div><div class="text-[10px] text-white/40">Custom App</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> نام و لوگوی شما (وایت‌لیبل 100%)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> دو نسخه: Universal + ARM64 بهینه</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> اتصال با یک کلیک فارسی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> نمایش حجم و روزهای باقی‌مانده</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> انتخاب سرور + تست سرعت</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> آپدیت مادام‌العمر رایگان</li>
</ul>
</div>
</div>
<!-- Category 3 -->
<div class="lg:col-span-1 space-y-4">
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-violet-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-layer-group text-sm"></i></div><div><div class="font-bold text-[13px]">پلن و سرور</div><div class="text-[10px] text-white/40">Plans & Servers</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> ساخت پلن اختصاصی با ⭐</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> حجم دلخواه: 5 گیگ تا نامحدود</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> مدت دلخواه: 7 روز تا 1 ساله</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> سقف اتصال: 1 تا نامحدود</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> انتخاب سرور: اقتصادی، VIP، ایران‌اکسس</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> لوکیشن: آلمان، فنلاند، ترکیه</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> پروتکل VLESS Reality ضدفیلتر</li>
</ul>
</div>
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-cyan-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-robot text-sm"></i></div><div><div class="font-bold text-[13px]">هوش مصنوعی فارسی</div><div class="text-[10px] text-white/40">AI Assistant v5.8</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> پاسخ خودکار به تیکت با متن + عکس فارسی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> راهنمای تصویری 3 مرحله‌ای (کپی، Import، اتصال)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> در پنل و تلگرام (آلبوم عکس)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> کاهش 80% تیکت‌های تکراری</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> پایگاه دانش 12 سند جامع فارسی</li>
</ul>
</div>
</div>
<!-- Category 4 -->
<div class="lg:col-span-1 space-y-4">
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-indigo-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-people-group text-sm"></i></div><div><div class="font-bold text-[13px]">کسب درآمد تیمی</div><div class="text-[10px] text-white/40">Team Earning</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> ساب‌نماینده بسازید (نماینده زیرمجموعه)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> پورسانت از فروش ساب‌نماینده</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> انتقال اعتبار به ساب‌نماینده</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> لینک دعوت اختصاصی + پورسانت 10%</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> کد تخفیف اختصاصی</li>
</ul>
</div>
<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-pink-500 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-shield-halved text-sm"></i></div><div><div class="font-bold text-[13px]">امنیت و وایت‌لیبل</div><div class="text-[10px] text-white/40">Security</div></div></div>
<ul class="mt-4 space-y-2.5 text-[11px] text-white/60">
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> وایت‌لیبل 100% - هیچ جا نام Connectix نیست</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> دامنه اختصاصی + لینک ساب اختصاصی</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> API اختصاصی (سبک PanelMS)</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> بک‌آپ خودکار روزانه</li>
<li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> لاگ کامل فعالیت‌ها + 2FA</li>
</ul>
</div>
<div class="bg-gradient-to-br from-violet-600/20 to-cyan-500/10 border border-violet-500/20 rounded-[20px] p-5 text-center">
<div class="text-[11px] text-violet-300">✨ 50+ ویژگی - همه فارسی</div>
<div class="font-black text-[13px] mt-1">لیست کامل بالا فقط خلاصه است!</div>
<div class="text-[10px] text-white/50 mt-2">برای دیدن دمو به @mainAdminpanel پیام بده</div>
<a href="#request" class="mt-3 inline-flex gradient-bg rounded-full px-4 py-2 text-[11px] font-bold">دیدن دمو رایگان</a>
</div>
</div>
</div>
</div>
</section>

<!-- APP SHOWCASE -->
<section id="app" class="py-24 px-4 relative overflow-hidden">
<div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
<div class="order-2 lg:order-1 relative">
<div class="absolute inset-0 bg-gradient-to-br from-violet-600/20 to-cyan-500/20 rounded-[32px] blur-[40px] -z-10"></div>
<div class="grid grid-cols-2 gap-4">
<div class="space-y-4"><img src="<?= $base ?>assets/ai_guides/hiddify-step1-copy-link.jpg" class="rounded-[20px] border border-white/[0.08] shadow-[0_20px_60px_rgba(0,0,0,.5)] card-hover"><img src="<?= $base ?>assets/ai_guides/hiddify-step3-connect.jpg" class="rounded-[20px] border border-white/[0.08] shadow-[0_20px_60px_rgba(0,0,0,.5)] card-hover"></div>
<div class="space-y-4 mt-8"><img src="<?= $base ?>assets/ai_guides/hiddify-step2-import.jpg" class="rounded-[20px] border border-white/[0.08] shadow-[0_20px_60px_rgba(0,0,0,.5)] card-hover"><img src="<?= $base ?>assets/ai_guides/app-download.jpg" class="rounded-[20px] border border-white/[0.08] shadow-[0_20px_60px_rgba(0,0,0,.5)] card-hover"></div>
</div>
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 bg-[#0A0D18] border border-white/[0.08] rounded-full flex items-center justify-center shadow-2xl glow"><i class="fa-solid fa-play text-xl gradient-text"></i></div>
</div>
<div class="order-1 lg:order-2 space-y-6">
<div class="inline-flex bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3 py-1 text-[11px] text-emerald-300">📱 اپ اختصاصی فارسی - White Label 100%</div>
<h2 class="text-[34px] sm:text-[42px] font-black leading-[1.1]">اپ با برند شما<br>یعنی <span class="gradient-text">3 برابر فروش بیشتر</span></h2>
<p class="text-white/60 text-[13px] leading-7">90% مشتریان از طریق اپ خرید می‌کنند. وقتی اپ با نام و لوگوی شما را نصب می‌کنند، هر روز برند شما را می‌بینند. <b class="text-white">مطالعات نشان داده اپ اختصاصی 3 برابر فروش بیشتر می‌آورد.</b></p>
<div class="grid sm:grid-cols-2 gap-3">
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[16px] p-4"><div class="w-8 h-8 bg-violet-500/20 rounded-[10px] flex items-center justify-center mb-2"><i class="fa-solid fa-palette text-violet-400 text-xs"></i></div><div class="font-bold text-[12px]">شخصی‌سازی کامل فارسی</div><div class="text-[11px] text-white/50 mt-1 leading-5">نام، لوگو، رنگ، آیکون، اسپلش - هیچ جا Connectix نیست - کاملاً فارسی</div></div>
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[16px] p-4"><div class="w-8 h-8 bg-cyan-500/20 rounded-[10px] flex items-center justify-center mb-2"><i class="fa-solid fa-bolt text-cyan-400 text-xs"></i></div><div class="font-bold text-[12px]">دو نسخه بهینه</div><div class="text-[11px] text-white/50 mt-1 leading-5">Universal (همه گوشی‌ها) + ARM64 (30% سریع‌تر) - حجم کم</div></div>
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[16px] p-4"><div class="w-8 h-8 bg-emerald-500/20 rounded-[10px] flex items-center justify-center mb-2"><i class="fa-solid fa-rotate text-emerald-400 text-xs"></i></div><div class="font-bold text-[12px]">آپدیت مادام‌العمر رایگان</div><div class="text-[11px] text-white/50 mt-1 leading-5">هر آپدیت Hiddify، اپ شما هم آپدیت می‌شود - رایگان</div></div>
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[16px] p-4"><div class="w-8 h-8 bg-amber-500/20 rounded-[10px] flex items-center justify-center mb-2"><i class="fa-solid fa-chart-line text-amber-400 text-xs"></i></div><div class="font-bold text-[12px]">افزایش وفاداری</div><div class="text-[11px] text-white/50 mt-1 leading-5">مشتری اپ شما را پاک نمی‌کند، هر روز برند شما را می‌بیند</div></div>
</div>
<div class="bg-amber-500/10 border border-amber-500/20 rounded-[16px] p-4 flex gap-3"><div class="w-10 h-10 bg-amber-500 rounded-[12px] flex items-center justify-center shrink-0"><i class="fa-solid fa-lightbulb"></i></div><div><div class="font-bold text-[12px] text-amber-300">هزینه ساخت بیرون: 15 تا 30 میلیون تومان + 2 ماه زمان</div><div class="text-[11px] text-white/60 mt-1">در Connectix: 0 تومان + 24 ساعت تحویل (در پلن حرفه‌ای به بالا) - کاملاً فارسی</div></div></div>
</div>
</div>
</section>

<!-- PRICING -->
<section id="pricing" class="py-24 px-4 bg-white/[0.02] border-y border-white/[0.06]">
<div class="max-w-7xl mx-auto">
<div class="text-center max-w-2xl mx-auto mb-14">
<div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">💰 تعرفه شفاف - پرداخت ریالی - فارسی</div>
<h2 class="text-[34px] sm:text-[42px] font-black mt-4">از 299 هزار تومان شروع کن<br><span class="gradient-text">ماهی 10 تا 25 میلیون دربیار</span></h2>
<p class="text-white/50 text-[13px] mt-3">بدون قرارداد بلندمدت، لغو آنی، ارتقا آنی. پشتیبانی: @mainAdminpanel</p>
<div class="inline-flex mt-5 bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-amber-500/20 rounded-full px-5 py-2.5 text-[12px] text-amber-300"><i class="fa-solid fa-gift ml-1"></i> جشنواره: 3 ماه بخر، 1 ماه هدیه + 500 هزار تومان شارژ هدیه - محدود</div>
</div>
<div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
<div class="group bg-[#0A0D18] border border-white/[0.06] rounded-[24px] p-7 flex flex-col card-hover">
<div class="text-[11px] text-white/40">شروع کسب و کار - فارسی</div><h3 class="text-[20px] font-black mt-1">🥉 استارتر</h3>
<div class="mt-5 flex items-baseline gap-2"><span class="text-[32px] font-black">299</span><span class="text-[13px] text-white/50">هزار تومان / ماه</span></div>
<ul class="mt-7 space-y-3 text-[12px] text-white/70 flex-1">
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 20% تخفیف خرید عمده</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات تلگرام اختصاصی فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پنل مدیریت کامل فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 50 کلاینت + 50 گیگ هدیه</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی فارسی: @mainAdminpanel</li>
</ul>
<a href="#request" class="mt-8 bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.08] rounded-full py-3.5 text-center text-[13px] font-bold transition">شروع با استارتر - فارسی</a>
</div>
<div class="group relative bg-gradient-to-b from-violet-600/15 to-violet-600/5 border border-violet-500/30 rounded-[24px] p-7 flex flex-col shadow-[0_0_60px_rgba(124,58,237,.2)] scale-[1.03] card-hover">
<div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-5 py-1.5 text-[11px] font-black shadow-lg">⭐ پرفروش‌ترین - پیشنهاد ما</div>
<div class="text-[11px] text-violet-300">حرفه‌ای - با اپ اختصاصی فارسی ⭐</div><h3 class="text-[20px] font-black mt-1">🥈 حرفه‌ای</h3>
<div class="mt-5 flex items-baseline gap-2"><span class="text-[32px] font-black">599</span><span class="text-[13px] text-white/50">هزار تومان / ماه</span></div><div class="text-[11px] text-white/30 line-through mt-1">900 هزار تومان - 33% تخفیف</div>
<ul class="mt-7 space-y-3 text-[12px] text-white/80 flex-1">
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>35% تخفیف</b> خرید عمده - سود بیشتر</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات + <b>اپ اندروید اختصاصی فارسی</b> با برند تو</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پلن اختصاصی نامحدود با ⭐</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ساب‌نماینده + پورسانت تیمی فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> API اختصاصی + هوش مصنوعی فارسی با عکس</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 200 کلاینت + 200 گیگ هدیه</li>
</ul>
<a href="#request" class="mt-8 gradient-bg rounded-full py-3.5 text-center text-[13px] font-black shadow-[0_0_30px_rgba(124,58,237,.4)] hover:shadow-[0_0_50px_rgba(124,58,237,.6)] transition">ثبت درخواست حرفه‌ای - 30 دقیقه تحویل فارسی</a>
</div>
<div class="group bg-[#0A0D18] border border-white/[0.06] rounded-[24px] p-7 flex flex-col card-hover">
<div class="text-[11px] text-amber-300">امپراتوری VPN - کامل فارسی</div><h3 class="text-[20px] font-black mt-1">🥇 بیزینس</h3>
<div class="mt-5 flex items-baseline gap-2"><span class="text-[32px] font-black">1.29</span><span class="text-[13px] text-white/50">میلیون / ماه</span></div><div class="text-[11px] text-white/30 line-through mt-1">2.5 میلیون - 48% تخفیف</div>
<ul class="mt-7 space-y-3 text-[12px] text-white/70 flex-1">
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>50% تخفیف</b> - نصف قیمت! بیشترین سود</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> همه چیز حرفه‌ای + اپ iOS فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> دامنه اختصاصی + سایت فروش فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی VIP @mainAdminpanel + مشاوره بازاریابی فارسی</li>
<li class="flex gap-2.5"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> کلاینت نامحدود + 500 گیگ هدیه</li>
</ul>
<a href="#request" class="mt-8 bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.08] rounded-full py-3.5 text-center text-[13px] font-bold transition">مشاوره بیزینس فارسی - @mainAdminpanel</a>
</div>
</div>
</div>
</section>

<!-- REQUEST FORM -->
<section id="request" class="py-24 px-4">
<div class="max-w-6xl mx-auto grid lg:grid-cols-5 gap-10 items-start">
<div class="lg:col-span-2 space-y-6">
<div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📝 فرم درخواست نمایندگی فارسی - 30 ثانیه</div>
<h2 class="text-[34px] font-black leading-[1.1]">فرم رو پر کن،<br><span class="gradient-text">30 دقیقه بعد پنلت آماده‌ست</span></h2>
<p class="text-white/60 text-[13px] leading-7">اطلاعاتت رو وارد کن، همکاران ما کمتر از 30 دقیقه از طریق تلگرام یا تماس با شما ارتباط می‌گیرند. مشاوره کاملاً رایگان و فارسی.</p>
<div class="space-y-3">
<div class="flex gap-3 bg-white/[0.04] border border-white/[0.06] rounded-[18px] p-4"><div class="w-10 h-10 bg-emerald-500/15 rounded-[12px] flex items-center justify-center shrink-0"><i class="fa-solid fa-bolt text-emerald-400"></i></div><div><div class="font-bold text-[13px]">تحویل آنی 30 دقیقه‌ای فارسی</div><div class="text-[11px] text-white/50 mt-1 leading-5">بعد از پرداخت، کمتر از 30 دقیقه پنل + ربات + اپ فارسی تحویل داده می‌شود - آموزش کامل فارسی</div></div></div>
<div class="flex gap-3 bg-white/[0.04] border border-white/[0.06] rounded-[18px] p-4"><div class="w-10 h-10 bg-sky-500/15 rounded-[12px] flex items-center justify-center shrink-0"><i class="fa-brands fa-telegram text-sky-400"></i></div><div><div class="font-bold text-[13px]">پشتیبانی مستقیم فارسی: @mainAdminpanel</div><div class="text-[11px] text-white/50 mt-1 leading-5">هر سوالی داری، مستقیم به آیدی بالا پیام بده - پاسخگویی 24 ساعته فارسی - https://vpbotn.ir</div></div></div>
</div>
<img src="<?= $base ?>ads/banner-fa-2.jpg" class="w-full rounded-[20px] border border-white/[0.06] shadow-xl">
</div>
<div class="lg:col-span-3">
<div class="relative bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[28px] p-1 shadow-[0_0_80px_rgba(124,58,237,.15)]">
<div class="bg-[#0A0D18] rounded-[24px] p-6 sm:p-8">
<?php if($success): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 rounded-[20px] p-8 text-center space-y-4">
<div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto text-3xl shadow-[0_0_40px_rgba(16,185,129,.4)]"><i class="fa-solid fa-check"></i></div>
<h3 class="font-black text-[20px] text-emerald-300">درخواست شما با موفقیت ثبت شد! ✅</h3>
<p class="text-[13px] text-white/70 leading-7">همکاران ما در کمتر از 30 دقیقه از طریق تلگرام یا تماس با شما ارتباط می‌گیرند.<br>برای ارتباط سریع‌تر به آیدی <a href="<?= $tg_url ?>" target="_blank" class="text-sky-400 font-bold underline">@mainAdminpanel</a> پیام دهید.<br><span class="text-[11px] text-white/40">سایت اصلی: https://vpbotn.ir - پنل: https://vpbotn.ir/contax/</span></p>
<div class="pt-3 flex justify-center gap-2"><a href="<?= $tg_url ?>" target="_blank" class="gradient-bg rounded-full px-8 py-3 text-[13px] font-bold flex items-center gap-2"><i class="fa-brands fa-telegram"></i> پیام به @mainAdminpanel</a></div>
</div>
<?php else: ?>
<?php if($error): ?><div class="bg-rose-500/10 border border-rose-500/20 rounded-[14px] p-3 text-[12px] text-rose-300 mb-5"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="space-y-5">
<input type="hidden" name="reseller_request" value="1">
<div class="grid sm:grid-cols-2 gap-4">
<div><label class="block text-[11px] text-white/50 mb-2">نام و نام خانوادگی <span class="text-rose-400">*</span></label><input type="text" name="name" required placeholder="مثلاً: علی رضایی" class="w-full bg-[#05070D] border border-white/[0.08] focus:border-violet-500/50 rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:bg-white/[0.04] transition"></div>
<div><label class="block text-[11px] text-white/50 mb-2">شماره موبایل</label><input type="text" name="phone" placeholder="0912..." class="w-full bg-[#05070D] border border-white/[0.08] focus:border-violet-500/50 rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:bg-white/[0.04] transition"></div>
</div>
<div class="grid sm:grid-cols-2 gap-4">
<div><label class="block text-[11px] text-white/50 mb-2">آیدی تلگرام <span class="text-rose-400">*</span></label><input type="text" name="telegram_id" required placeholder="@username" class="w-full bg-[#05070D] border border-white/[0.08] focus:border-violet-500/50 rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:bg-white/[0.04] transition"></div>
<div><label class="block text-[11px] text-white/50 mb-2">نوع کسب‌وکار فعلی</label><select name="business" class="w-full bg-[#05070D] border border-white/[0.08] focus:border-violet-500/50 rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:bg-white/[0.04] transition"><option>پیج اینستاگرام</option><option>کانال تلگرام</option><option>سایت</option><option>فروش حضوری / مغازه</option><option>شروع جدید - بدون سابقه</option><option>سایر</option></select></div>
</div>
<div><label class="block text-[11px] text-white/50 mb-2">پلن مورد نظر - فارسی</label>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
<label class="cursor-pointer bg-[#05070D] border border-white/[0.06] hover:border-violet-500/30 rounded-[14px] p-3.5 flex items-center gap-2.5 transition has-[:checked]:border-violet-500/50 has-[:checked]:bg-violet-500/[0.08]"><input type="radio" name="plan" value="استارتر 299ت - فارسی" class="accent-violet-500"><span class="text-[11px]"><b>استارتر</b><br><span class="text-white/40">299ت - 20% تخفیف فارسی</span></span></label>
<label class="cursor-pointer bg-[#05070D] border border-violet-500/40 bg-violet-500/[0.08] rounded-[14px] p-3.5 flex items-center gap-2.5 transition"><input type="radio" name="plan" value="حرفه‌ای 599ت ⭐ پرفروش - با اپ فارسی" class="accent-violet-500" checked><span class="text-[11px]"><b>حرفه‌ای ⭐</b><br><span class="text-violet-300">599ت - 35% + اپ اختصاصی فارسی</span></span></label>
<label class="cursor-pointer bg-[#05070D] border border-white/[0.06] hover:border-violet-500/30 rounded-[14px] p-3.5 flex items-center gap-2.5 transition has-[:checked]:border-violet-500/50 has-[:checked]:bg-violet-500/[0.08]"><input type="radio" name="plan" value="بیزینس 1.29م - کامل فارسی"><span class="text-[11px]"><b>بیزینس</b><br><span class="text-white/40">1.29م - 50% + همه چیز فارسی</span></span></label>
</div>
</div>
<div><label class="block text-[11px] text-white/50 mb-2">توضیحات / سوال شما (اختیاری - فارسی)</label><textarea name="message" rows="3" placeholder="مثلاً: من پیج 20k دارم، می‌خوام شروع کنم. لطفاً راهنمایی کنید..." class="w-full bg-[#05070D] border border-white/[0.08] focus:border-violet-500/50 rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:bg-white/[0.04] transition"></textarea></div>
<button type="submit" class="w-full gradient-bg hover:opacity-90 rounded-full py-4 font-black text-[14px] flex items-center justify-center gap-2.5 shadow-[0_0_40px_rgba(124,58,237,.35)] hover:shadow-[0_0_60px_rgba(124,58,237,.5)] transition-all shimmer">
<i class="fa-solid fa-paper-plane"></i> ثبت درخواست نمایندگی + دریافت 500 هزار تومان هدیه فارسی
</button>
<div class="text-[11px] text-white/30 text-center">با ثبت درخواست، همکاران ما در کمتر از 30 دقیقه با شما تماس می‌گیرند. اطلاعات شما محفوظ است - کاملاً فارسی</div>
<div class="flex items-center justify-center gap-2 text-[12px] pt-2"><span class="text-white/40">یا مستقیم پیام بده:</span><a href="<?= $tg_url ?>" target="_blank" class="bg-sky-500/15 border border-sky-500/20 text-sky-300 rounded-full px-4 py-1.5 font-bold hover:bg-sky-500/25 transition"><i class="fa-brands fa-telegram"></i> @mainAdminpanel</a></div>
</form>
<?php endif; ?>
</div>
</div>
</div>
</div>
</section>

<!-- FAQ -->
<section id="faq" class="py-24 px-4 max-w-4xl mx-auto">
<div class="text-center mb-12"><div class="inline-flex bg-white/[0.04] border border-white/[0.06] rounded-full px-3 py-1 text-[11px] text-white/50">❓ سوالات متداول فارسی</div><h2 class="text-[32px] font-black mt-4">هر سوالی داری، اینجا جوابشه - فارسی</h2></div>
<div class="space-y-3">
<details class="group bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5 open:bg-white/[0.05] open:border-violet-500/20 transition"><summary class="flex justify-between items-center cursor-pointer list-none font-bold text-[13px]">آیا نیاز به دانش فنی دارم؟ <span class="w-7 h-7 bg-white/[0.06] group-open:bg-violet-500 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-chevron-down text-[10px] group-open:rotate-180 transition"></i></span></summary><p class="text-[12px] text-white/50 mt-4 leading-6">خیر! همه چیز آماده و فارسی است. پنل، ربات، اپ. فقط لینک ربات را به مشتری می‌دهید. آموزش صفر تا صد فارسی هم داریم - ویدیو + PDF.</p></details>
<details class="group bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5 open:bg-white/[0.05] open:border-violet-500/20 transition"><summary class="flex justify-between items-center cursor-pointer list-none font-bold text-[13px]">مشتری از کجا بیارم؟ <span class="w-7 h-7 bg-white/[0.06] group-open:bg-violet-500 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-chevron-down text-[10px] group-open:rotate-180 transition"></i></span></summary><p class="text-[12px] text-white/50 mt-4 leading-6">پیج اینستا، کانال تلگرام، دیوار، دوستان. ما روش‌های جذب مشتری فارسی (تست رایگان، تخفیف، رفرال) را آموزش می‌دهیم. میانگین هر نماینده ماه اول 30-50 مشتری جذب می‌کند.</p></details>
<details class="group bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5 open:bg-white/[0.05] open:border-violet-500/20 transition"><summary class="flex justify-between items-center cursor-pointer list-none font-bold text-[13px]">قیمت فروش دست کیه؟ <span class="w-7 h-7 bg-white/[0.06] group-open:bg-violet-500 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-chevron-down text-[10px] group-open:rotate-180 transition"></i></span></summary><p class="text-[12px] text-white/50 mt-4 leading-6">100% دست شما! مثلاً پلن 30 گیگ را 40ت می‌خرید، 120ت می‌فروشید. 80ت سود خالص. می‌توانید 100ت یا 150ت هم بفروشید - کاملاً فارسی و آزاد.</p></details>
<details class="group bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5 open:bg-white/[0.05] open:border-violet-500/20 transition"><summary class="flex justify-between items-center cursor-pointer list-none font-bold text-[13px]">اپ اختصاصی فارسی چقدر طول می‌کشه؟ <span class="w-7 h-7 bg-white/[0.06] group-open:bg-violet-500 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-chevron-down text-[10px] group-open:rotate-180 transition"></i></span></summary><p class="text-[12px] text-white/50 mt-4 leading-6">24 تا 48 ساعت. لوگو و نام برند را بفرستید، ما اپ فارسی را می‌سازیم و APK تحویل می‌دهیم. آپدیت مادام‌العمر رایگان فارسی.</p></details>
<details class="group bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5 open:bg-white/[0.05] open:border-violet-500/20 transition"><summary class="flex justify-between items-center cursor-pointer list-none font-bold text-[13px]">سرورها فیلتر می‌شه؟ <span class="w-7 h-7 bg-white/[0.06] group-open:bg-violet-500 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-chevron-down text-[10px] group-open:rotate-180 transition"></i></span></summary><p class="text-[12px] text-white/50 mt-4 leading-6">پروتکل VLESS Reality مقاوم‌ترین پروتکل حال حاضر است و به سختی قابل تشخیص است. آپتایم 99.9% داریم - تست شده در ایران.</p></details>
</div>
</section>

<!-- FINAL CTA -->
<section class="py-20 px-4">
<div class="max-w-5xl mx-auto relative overflow-hidden bg-gradient-to-br from-violet-600/20 via-indigo-600/10 to-cyan-500/10 border border-violet-500/20 rounded-[32px] p-10 sm:p-14 text-center">
<div class="absolute top-0 right-0 w-[500px] h-[500px] bg-violet-600/20 rounded-full blur-[100px] -z-10"></div>
<div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-cyan-500/15 rounded-full blur-[100px] -z-10"></div>
<h2 class="text-[32px] sm:text-[44px] font-black leading-[1.1] text-balance">آماده‌ای کسب و کارت رو<br><span class="gradient-text">با برند خودت شروع کنی؟</span></h2>
<p class="text-white/60 text-[13px] mt-4 max-w-2xl mx-auto leading-6">همین حالا فرم بالا رو پر کن یا مستقیم به @mainAdminpanel پیام بده. 30 دقیقه دیگه پنلت آماده‌ست. جشنواره محدود - فقط 20 نفر! کاملاً فارسی.</p>
<div class="mt-10 flex flex-wrap justify-center gap-3">
<a href="#request" class="gradient-bg rounded-full px-10 py-4 font-black text-[14px] flex items-center gap-2 shadow-[0_0_40px_rgba(124,58,237,.4)] hover:shadow-[0_0_60px_rgba(124,58,237,.6)] transition-all"><i class="fa-solid fa-rocket"></i> ثبت درخواست نمایندگی فارسی</a>
<a href="<?= $tg_url ?>" target="_blank" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-8 py-4 font-bold text-[13px] flex items-center gap-2 hover:bg-white/[0.1] transition"><i class="fa-brands fa-telegram text-sky-400 text-[18px]"></i> @mainAdminpanel - مشاوره رایگان فارسی</a>
</div>
<div class="mt-8 inline-flex flex-wrap justify-center gap-2 text-[11px] text-white/40">
<span class="bg-white/[0.04] border border-white/[0.06] rounded-full px-3 py-1.5">🔒 پرداخت امن زرین‌پال + تتر + تون</span>
<span class="bg-white/[0.04] border border-white/[0.06] rounded-full px-3 py-1.5">⚡ تحویل آنی 30 دقیقه فارسی</span>
<span class="bg-white/[0.04] border border-white/[0.06] rounded-full px-3 py-1.5">🎁 500ت شارژ + 1 ماه هدیه</span>
<span class="bg-white/[0.04] border border-white/[0.06] rounded-full px-3 py-1.5">🇮🇷 100% فارسی</span>
</div>
</div>
</section>

<footer class="border-t border-white/[0.06] py-12 px-4">
<div class="max-w-7xl mx-auto grid md:grid-cols-4 gap-8 text-[12px]">
<div class="md:col-span-2"><div class="flex items-center gap-2.5 font-black text-[15px]"><div class="w-8 h-8 gradient-bg rounded-[10px] flex items-center justify-center">C</div> Connectix v5.8 - فارسی کامل<div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-full px-2.5 py-0.5 text-[10px]">● آنلاین</div></div><p class="text-white/40 leading-6 mt-3 max-w-md">قدرتمندترین پنل نمایندگی VPN ایران با اپ اختصاصی فارسی، ربات فروش خودکار فارسی و هوش مصنوعی با راهنمای تصویری فارسی. بیش از 200 نماینده فعال فارسی. پشتیبانی: @mainAdminpanel - دامنه: https://vpbotn.ir</p><div class="mt-5 flex gap-2.5"><img src="<?= $base ?>ads/banner-fa-1.jpg" class="w-36 h-24 object-cover rounded-[14px] border border-white/[0.06]"><img src="<?= $base ?>ads/banner-fa-2.jpg" class="w-36 h-24 object-cover rounded-[14px] border border-white/[0.06]"><img src="<?= $base ?>ads/banner-fa-3.jpg" class="w-36 h-24 object-cover rounded-[14px] border border-white/[0.06] hidden sm:block"></div></div>
<div><div class="font-bold text-white mb-3">لینک‌های سریع فارسی</div><div class="space-y-2.5 text-white/50"><a href="#features" class="block hover:text-white transition">📋 امکانات کامل پنل فارسی</a><a href="#why" class="block hover:text-white transition">🔥 چرا جذابه؟</a><a href="#app" class="block hover:text-white transition">📱 اپ اختصاصی فارسی</a><a href="#pricing" class="block hover:text-white transition">💰 تعرفه فارسی</a><a href="#request" class="block hover:text-white transition">📝 درخواست نمایندگی فارسی</a><a href="<?= $panel_url ?>" class="block hover:text-white transition">🔐 ورود به پنل</a></div></div>
<div><div class="font-bold text-white mb-3">ارتباط فارسی - @mainAdminpanel</div><div class="space-y-2.5 text-white/50"><a href="<?= $tg_url ?>" target="_blank" class="flex items-center gap-2 hover:text-white transition"><i class="fa-brands fa-telegram text-sky-400"></i> تلگرام: @mainAdminpanel</a><span class="flex items-center gap-2"><i class="fa-solid fa-globe text-violet-400"></i> سایت اصلی: https://vpbotn.ir</span><span class="flex items-center gap-2"><i class="fa-solid fa-server text-cyan-400"></i> پنل: https://vpbotn.ir/contax/</span><span class="flex items-center gap-2"><i class="fa-solid fa-robot text-amber-400"></i> ربات دمو: @mainAdminpanel</span><span class="flex items-center gap-2"><i class="fa-solid fa-envelope text-white/40"></i> پشتیبانی 24/7 فارسی</span></div><div class="mt-5 bg-white/[0.04] border border-white/[0.06] rounded-[14px] p-3 text-[11px]"><div class="font-bold text-white">💡 نکته:</div><div class="text-white/40 mt-1 leading-5">این صفحه کاملاً فارسی و بدون نیاز به لاگین است. می‌توانید لینک https://vpbotn.ir/ را در همه جا پخش کنید.</div></div></div>
</div>
<div class="max-w-7xl mx-auto mt-10 pt-6 border-t border-white/[0.06] flex flex-col sm:flex-row justify-between gap-3 text-[11px] text-white/25"><span>© 2025 Connectix - همه حقوق محفوظ است. ساخته شده با ❤️ برای نمایندگان ایرانی - نسخه 5.8 فارسی</span><span>🇮🇷 100% فارسی - @mainAdminpanel - https://vpbotn.ir</span></div>
</div>
</footer>

<div class="fixed bottom-0 left-0 right-0 md:hidden bg-[#05070D]/85 glass border-t border-white/[0.06] p-3 z-40">
<a href="#request" class="gradient-bg rounded-full py-3.5 flex items-center justify-center gap-2 font-bold text-[13px] w-full shadow-[0_0_30px_rgba(124,58,237,.4)]"><i class="fa-solid fa-rocket"></i> ثبت درخواست - @mainAdminpanel - فارسی</a>
</div>

<script>
// Smooth header links active
document.querySelectorAll('a[href^="#"]').forEach(a=>{
 a.addEventListener('click',e=>{
  const id=a.getAttribute('href'); if(id.length>1){ e.preventDefault(); document.querySelector(id)?.scrollIntoView({behavior:'smooth',block:'start'}); }
 });
});
// FAQ close others when open
document.querySelectorAll('#faq details').forEach(d=>{
 d.addEventListener('toggle',()=>{
  if(d.open){ document.querySelectorAll('#faq details').forEach(o=>{ if(o!==d) o.open=false; }); }
 });
});
</script>
</body>
</html>

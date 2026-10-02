<?php
// Connectix Ultra Premium Landing - v6.6 - Generic Product Sales Panel - No VPN/Filter Terms
// <?= htmlspecialchars($domain) ?>/contax/promo/ | <?= htmlspecialchars($domain) ?>/
// @mainAdminpanel - Generic Product Sales Panel Only
$brand = 'Connectix';
$tg = '@mainAdminpanel';
$tg_url = 'https://t.me/mainAdminpanel';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
$panel_url = $proto . $host . $basePath . '/';
$demo_user = 'demo';
$demo_pass = 'demo123';
$domain = $proto . $host;
$base = $panel_url;
$success = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reseller_request'])) {
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $tgid = trim($_POST['telegram_id'] ?? '');
    $plan = trim($_POST['plan'] ?? ''); $biz = trim($_POST['business'] ?? ''); $msg = trim($_POST['message'] ?? '');
    if ($name === '' || ($phone === '' && $tgid === '')) { $error = 'لطفاً نام و یک راه ارتباطی را وارد کنید.'; }
    else {
        @file_put_contents(__DIR__.'/requests.log', date('Y-m-d H:i:s')." | $name | $phone | $tgid | $plan | $biz | $msg | IP:".($_SERVER['REMOTE_ADDR']??'')."\n", FILE_APPEND);
        try {
            $root = dirname(__DIR__);
            if (file_exists($root.'/config.php')) {
                require_once $root.'/config.php'; require_once $root.'/core/Database.php'; require_once $root.'/core/Setting.php'; require_once $root.'/core/TelegramBot.php';
                $pdo = Database::getConnection(); $token = Setting::get('telegram_bot_token',''); $admin = Setting::get('telegram_admin_id',''); $logCh = Setting::get('bot_log_channel','') ?: Setting::get('telegram_log_channel_id','');
                $text = "🔥 <b>درخواست جدید پنل فروش</b>\n\n👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tgid\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n🌐 {$panel_url}promo/\n🕐 ".date('Y-m-d H:i:s');
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
<title>پنل فروش محصول با اپ اختصاصی فارسی | سود 200% | Connectix - @mainAdminpanel</title>
<meta name="description" content="پنل فروش محصول فارسی با اپ اندروید اختصاصی، ربات تلگرام فروش خودکار 24 ساعته، سود 200%، مدیریت سفارشات، ویدیو معرفی، دمو آنلاین demo/demo123. @mainAdminpanel">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="<?= htmlspecialchars($basePath) ?>/assets/js/tailwind.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/fontawesome.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<style>
*{font-family:Vazirmatn,system-ui!important}
html{scroll-behavior:smooth}
body{background:#03050A;color:#fff;overflow-x:hidden}
.glass{backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px)}
.gradient-text{background:linear-gradient(90deg,#8B5CF6 0%,#06B6D4 50%,#10B981 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.gradient-bg{background:linear-gradient(135deg,#7C3AED 0%,#4F46E5 30%,#06B6D4 100%)}
.cursor-dot{width:8px;height:8px;background:#8B5CF6;border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:9999;mix-blend-mode:screen;box-shadow:0 0 20px #8B5CF6}
.cursor-ring{width:40px;height:40px;border:1.5px solid rgba(139,92,246,.5);border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:9998;transition:all .15s ease-out}
.card-3d{transform-style:preserve-3d;transition:transform .6s cubic-bezier(.23,1,.32,1)}
.card-3d:hover{transform:translateY(-8px) scale(1.02)}
.tilt{transform-style:preserve-3d;transition:transform .3s ease-out}
@keyframes float3d{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
.float3d{animation:float3d 7s ease-in-out infinite}
@keyframes glowPulse{0%,100%{box-shadow:0 0 40px rgba(124,58,237,.3),0 0 80px rgba(6,182,214,.15)}50%{box-shadow:0 0 60px rgba(124,58,237,.5),0 0 100px rgba(6,182,214,.25)}}
.glow-3d{animation:glowPulse 4s ease-in-out infinite}
@keyframes orbit{from{transform:rotate(0) translateX(110px) rotate(0)}to{transform:rotate(360deg) translateX(110px) rotate(-360deg)}}
.orbit{animation:orbit 18s linear infinite}
#canvas3d{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;opacity:.55}
</style>
</head>
<body class="antialiased selection:bg-violet-500/30">

<div class="cursor-dot hidden md:block" id="cursorDot"></div>
<div class="cursor-ring hidden md:block" id="cursorRing"></div>
<canvas id="canvas3d"></canvas>

<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
<div class="absolute top-[-25%] right-[-15%] w-[900px] h-[900px] bg-violet-600/25 rounded-full blur-[160px]"></div>
<div class="absolute top-[35%] left-[-12%] w-[700px] h-[700px] bg-cyan-500/20 rounded-full blur-[140px]"></div>
<div class="absolute bottom-[-25%] right-[25%] w-[800px] h-[800px] bg-indigo-600/20 rounded-full blur-[150px]"></div>
<div class="absolute inset-0 opacity-[0.03]" style="background-image:linear-gradient(rgba(255,255,255,.1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.1) 1px,transparent 1px);background-size:60px 60px"></div>
</div>

<nav class="fixed top-0 w-full z-50 bg-[#03050A]/70 glass border-b border-white/[0.06]">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between items-center h-[70px]">
<div class="flex items-center gap-3">
<div class="relative w-11 h-11 gradient-bg rounded-[14px] flex items-center justify-center font-black text-white shadow-[0_0_30px_rgba(124,58,237,.4)]">C</div>
<div><div class="font-black text-[17px] leading-none flex items-center gap-2">Connectix <span class="text-[9px] bg-gradient-to-r from-violet-500 to-cyan-500 rounded-full px-2.5 py-1 font-black">فارسی v6.6</span></div><div class="text-[10px] text-white/40 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span> آنلاین • فارسی • @mainAdminpanel</div></div>
</div>
<div class="hidden lg:flex items-center gap-1 bg-white/[0.03] border border-white/[0.05] rounded-full p-1">
<a href="#features" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">امکانات کامل</a>
<a href="#why" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">چرا جذابه؟</a>
<a href="#app" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">اپ اختصاصی</a>
<a href="#video" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">ویدیو</a>
<a href="#demo" class="px-4 py-2 rounded-full text-[12px] bg-white/[0.08] text-white">دمو آنلاین</a>
<a href="#pricing" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">تعرفه</a>
</div>
<div class="flex items-center gap-2">
<a href="<?= $tg_url ?>" target="_blank" class="hidden sm:flex items-center gap-2 bg-white/[0.05] border border-white/[0.06] rounded-full px-4 py-2.5 text-[12px]"><i class="fa-brands fa-telegram text-sky-400"></i> @mainAdminpanel</a>
<a href="#request" class="gradient-bg rounded-full px-6 py-2.5 text-[12px] font-black shadow-[0_0_30px_rgba(124,58,237,.4)]">ثبت درخواست</a>
</div>
</div>
</div>
</nav>

<!-- HERO -->
<section class="relative min-h-[100vh] flex items-center pt-24 pb-12 px-4">
<div class="max-w-7xl mx-auto w-full grid lg:grid-cols-[1.15fr_0.85fr] gap-12 items-center">
<div class="space-y-7">
<div class="inline-flex items-center gap-2 bg-gradient-to-r from-violet-500/15 to-cyan-500/10 border border-violet-500/20 rounded-full pl-2 pr-5 py-2.5">
<span class="bg-gradient-to-r from-violet-500 to-cyan-500 text-white text-[10px] font-black rounded-full px-3 py-1">جدید v6.6</span>
<span class="text-[12px] text-violet-200">گرافیک سینمایی + ویدیو معرفی + دمو آنلاین فقط-دیدنی فارسی</span>
</div>
<h1 class="text-[40px] sm:text-[54px] lg:text-[58px] font-black leading-[0.95] tracking-tight">
<span class="block">پنل فروش محصول</span>
<span class="block gradient-text">با اپ اختصاصی فارسی</span>
<span class="block text-[26px] sm:text-[32px] text-white/85 mt-3">درآمد ماهانه 10 تا 25 میلیون</span>
</h1>
<p class="text-[14px] leading-7 text-white/60 max-w-[580px]">
بدون حتی یک خط کدنویسی، با برند خودت کسب و کار فروش محصول راه بنداز. <b class="text-white">اپ اندروید اختصاصی فارسی</b>، <b class="text-white">ربات تلگرام فروش خودکار 24 ساعته فارسی</b>، سود <b class="text-emerald-300">200% هر فروش</b>، مدیریت <b class="text-white">سفارشات و محصولات نامحدود</b>. <b class="text-violet-300">دمو: demo / demo123 - فقط دیدنی</b> - پشتیبانی فارسی: @mainAdminpanel
</p>
<div class="flex flex-wrap gap-3.5">
<a href="#request" class="gradient-bg rounded-full px-8 py-4 font-black text-[14px] flex items-center gap-2.5 shadow-[0_0_50px_rgba(124,58,237,.5)]">🚀 ثبت درخواست - 30 دقیقه تحویل فارسی</a>
<a href="#video" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-7 py-4 font-bold text-[13px] flex items-center gap-2.5"><span class="w-8 h-8 bg-white rounded-full flex items-center justify-center"><i class="fa-solid fa-play text-black text-[12px] ml-0.5"></i></span> دیدن ویدیو معرفی</a>
</div>
<div class="grid grid-cols-3 gap-3 max-w-[440px] pt-3">
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center"><div class="text-[22px] font-black gradient-text">200+</div><div class="text-[10px] text-white/50">فروشنده فعال</div></div>
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center"><div class="text-[22px] font-black">50K+</div><div class="text-[10px] text-white/50">مشتری راضی</div></div>
<div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[18px] p-4 text-center"><div class="text-[22px] font-black text-emerald-400">فارسی</div><div class="text-[10px] text-white/50">100% فارسی</div></div>
</div>
</div>
<div class="relative lg:h-[680px] flex items-center justify-center">
<div class="relative w-full max-w-[520px] aspect-[4/3]">
<div class="absolute inset-0 bg-gradient-to-br from-white/[0.08] to-white/[0.02] border border-white/[0.08] rounded-[32px] p-4 shadow-[0_0_100px_rgba(124,58,237,.2)] tilt float3d" id="tiltCard">
<img src="<?= $base ?>ads/3d-dashboard.jpg" alt="پنل فارسی" class="w-full h-full object-cover rounded-[22px] border border-white/[0.06]">
</div>
<div class="absolute -top-8 -right-8 w-20 h-20 bg-gradient-to-br from-violet-500 to-cyan-500 rounded-[20px] flex items-center justify-center shadow-[0_0_40px_rgba(124,58,237,.6)] orbit"><i class="fa-solid fa-mobile-screen text-xl"></i></div>
<div class="absolute -bottom-10 -left-10 w-16 h-16 bg-gradient-to-br from-emerald-500 to-cyan-500 rounded-[16px] flex items-center justify-center shadow-[0_0_30px_rgba(16,185,129,.5)] orbit" style="animation-direction:reverse"><i class="fa-brands fa-telegram"></i></div>
<div class="absolute top-1/2 -right-12 bg-[#0A0D18]/90 glass border border-white/[0.08] rounded-[18px] p-4 shadow-2xl hidden lg:flex items-center gap-3 min-w-[210px]"><div class="w-11 h-11 bg-emerald-500/15 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-arrow-trend-up text-emerald-400"></i></div><div><div class="text-[10px] text-white/40">سود امروز</div><div class="font-black text-emerald-400 text-[14px]">+2,450,000 تومان</div></div></div>
</div>
<div class="absolute -bottom-16 left-1/2 -translate-x-1/2 w-[110%] grid grid-cols-3 gap-3">
<img src="<?= $base ?>ads/3d-globe.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl">
<img src="<?= $base ?>ads/banner-fa-premium.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl mt-6">
<img src="<?= $base ?>ads/3d-phone.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl">
</div>
</div>
</div>
</section>

<!-- VIDEO + DEMO -->
<section id="video" class="py-24 px-4">
<div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-8">

<!-- Video Player with Audio -->
<div class="group relative bg-gradient-to-br from-violet-600/10 to-cyan-500/10 border border-violet-500/20 rounded-[32px] p-2 shadow-[0_0_80px_rgba(124,58,237,.15)]">
<div class="bg-[#0A0D18] rounded-[24px] overflow-hidden">
<div class="relative aspect-video bg-black overflow-hidden" id="videoContainer">
<img id="slideImage" src="<?= $base ?>ads/banner-fa-1.jpg" class="w-full h-full object-cover transition-all duration-700">
<div class="absolute inset-0 bg-gradient-to-t from-[#0A0D18] via-transparent to-transparent"></div>
<div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/20 group-hover:bg-black/10 transition">
<div class="relative">
<div class="absolute inset-0 bg-white/20 rounded-full animate-ping" style="animation-duration: 2s"></div>
<div class="absolute -inset-4 bg-gradient-to-br from-violet-600 to-cyan-400 rounded-full opacity-40 blur-2xl animate-pulse"></div>
<div class="absolute -inset-2 bg-white/10 rounded-full"></div>
<button id="playBtn" class="relative w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-[0_0_80px_rgba(255,255,255,.7),0_12px_40px_rgba(0,0,0,.5)] hover:scale-110 hover:shadow-[0_0_100px_rgba(255,255,255,.9)] transition-all duration-300 group/btn cursor-pointer">
<i class="fa-solid fa-play text-black text-[26px] ml-1 group-hover/btn:scale-110 transition-transform duration-300"></i>
</button>
<div class="absolute -top-1 -right-1 w-7 h-7 bg-red-500 rounded-full border-4 border-[#0A0D18] flex items-center justify-center shadow-lg">
<div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
</div>
</div>
<div class="flex flex-col items-center gap-2">
<div class="bg-black/70 backdrop-blur-xl border border-white/15 rounded-full px-6 py-2.5 flex items-center gap-2.5 shadow-[0_8px_32px_rgba(0,0,0,.5)] hover:bg-black/80 transition">
<div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(239,68,68,.5)]">
<i class="fa-solid fa-play text-white text-[11px] ml-0.5"></i>
</div>
<span class="text-[13px] font-black text-white tracking-wide">پخش ویدیو معرفی</span>
<span class="w-px h-4 bg-white/20"></span>
<span class="text-[11px] text-white/60">کلیک کنید</span>
</div>
<div class="bg-violet-600/20 backdrop-blur-md border border-violet-500/30 rounded-full px-3 py-1 flex items-center gap-1.5">
<i class="fa-solid fa-video text-violet-300 text-[10px]"></i>
<span class="text-[10px] font-bold text-violet-200">ویدیو موجود - با صداگذاری فارسی</span>
</div>
</div>
</div>
<div class="absolute bottom-0 left-0 right-0 p-6">
<h3 class="font-black text-[18px]">ویدیو معرفی پنل فروش</h3>
<p class="text-[11px] text-white/50 mt-1">@mainAdminpanel</p>
</div>
<div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10"><div id="progressBar" class="h-full gradient-bg w-0 transition-all duration-300"></div></div>
</div>
<div class="p-5">
<audio id="narration" src="<?= $base ?>ads/video-narration-fa.mp3" preload="metadata"></audio>
<div class="flex items-center gap-3">
<button id="playPause" class="w-11 h-11 bg-white rounded-full flex items-center justify-center hover:scale-105 transition shadow-[0_0_20px_rgba(255,255,255,.4)] group/btn2"><i class="fa-solid fa-play text-black text-[13px] ml-0.5 group-hover/btn2:scale-110 transition"></i></button>
<div class="flex-1"><div class="text-[13px] font-black flex items-center gap-2"><i class="fa-solid fa-circle-play text-violet-400"></i> پخش ویدیو معرفی</div><div class="text-[11px] text-white/50 flex items-center gap-1.5 mt-0.5"><i class="fa-solid fa-volume-high text-[10px]"></i> با صداگذاری فارسی - @mainAdminpanel</div></div>
<div class="bg-white/[0.06] border border-white/[0.1] rounded-full px-3 py-1.5 flex items-center gap-1.5"><div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div><span class="text-[10px] font-bold">VIDEO</span></div>
</div>
</div>
</div>
</div>

<!-- Demo View-Only -->
<div id="demo" class="group relative bg-gradient-to-br from-emerald-600/10 to-violet-600/10 border border-emerald-500/20 rounded-[32px] p-2 shadow-[0_0_80px_rgba(16,185,129,.15)]">
<div class="bg-[#0A0D18] rounded-[24px] p-6 h-full flex flex-col">
<div class="flex justify-between items-start">
<div><div class="inline-flex bg-emerald-500/15 border border-emerald-500/20 rounded-full px-3 py-1 text-[10px] text-emerald-300">● آنلاین - فقط دیدنی - فارسی</div><h3 class="font-black text-[20px] mt-3">دمو آنلاین - تست زنده پنل فارسی</h3><p class="text-[12px] text-white/50 mt-1">فقط برای نمایش - امکان ساخت و تغییر وجود ندارد - کاملاً فارسی</p></div>
<div class="w-12 h-12 bg-emerald-500/20 rounded-[14px] flex items-center justify-center"><i class="fa-solid fa-eye text-emerald-400"></i></div>
</div>
<div class="mt-6 bg-[#05070D] border border-white/[0.06] rounded-[18px] p-4 space-y-3">
<div class="flex justify-between items-center bg-white/[0.03] rounded-[12px] px-4 py-3"><span class="text-[11px] text-white/40">آدرس دمو:</span><span class="text-[12px] font-mono text-cyan-300"><?= htmlspecialchars($panel_url) ?>demo/</span></div>
<div class="grid grid-cols-2 gap-3">
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[12px] px-4 py-3"><div class="text-[10px] text-white/40">نام کاربری</div><div class="font-mono font-bold text-[13px] mt-1 flex items-center gap-2"><i class="fa-solid fa-user text-violet-400 text-[11px]"></i> <?= $demo_user ?> <button onclick="copyText('<?= $demo_user ?>')" class="mr-auto text-[10px] bg-white/[0.06] rounded-full px-2 py-1">کپی</button></div></div>
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[12px] px-4 py-3"><div class="text-[10px] text-white/40">رمز عبور</div><div class="font-mono font-bold text-[13px] mt-1 flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-400 text-[11px]"></i> <?= $demo_pass ?> <button onclick="copyText('<?= $demo_pass ?>')" class="mr-auto text-[10px] bg-white/[0.06] rounded-full px-2 py-1">کپی</button></div></div>
</div>
<div class="bg-amber-500/10 border border-amber-500/20 rounded-[12px] px-4 py-2.5 text-[11px] text-amber-300 flex gap-2"><i class="fa-solid fa-eye-slash mt-0.5"></i> این نسخه فقط برای نمایش است - امکان ساخت، تغییر تنظیمات و حذف وجود ندارد. برای پنل واقعی درخواست دهید.</div>
</div>
<div class="mt-4 grid grid-cols-3 gap-2 text-center text-[10px]">
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[12px] py-2.5"><b class="block text-[13px]">فقط دیدنی</b><span class="text-white/40">بدون دسترسی ساخت</span></div>
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[12px] py-2.5"><b class="block text-[13px] text-emerald-400">فارسی</b><span class="text-white/40">100% فارسی</span></div>
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[12px] py-2.5"><b class="block text-[13px] text-violet-300">زنده</b><span class="text-white/40">پنل واقعی</span></div>
</div>
<div class="mt-5 flex gap-2">
<a href="<?= $base ?>demo/" target="_blank" class="flex-1 gradient-bg rounded-full py-3.5 text-center text-[13px] font-black">👁️ ورود به دمو فقط-دیدنی فارسی</a>
<a href="<?= $tg_url ?>" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-5 py-3.5 text-[12px] font-bold"><i class="fa-brands fa-telegram text-sky-400"></i></a>
</div>
</div>
</div>

</div>
</div>
</section>

<!-- FEATURES FULL -->
<section id="features" class="py-20 px-4 bg-white/[0.02] border-y border-white/[0.06]">
<div class="max-w-7xl mx-auto">
<div class="text-center max-w-3xl mx-auto mb-12"><div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📋 لیست کامل 50+ امکانات - فارسی کامل</div><h2 class="text-[32px] sm:text-[42px] font-black mt-4 leading-[1.1]">هرچی برای فروش نیاز داری، اینجاست<br><span class="gradient-text">100% فارسی - جذاب و کامل</span></h2></div>
<div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
<?php
$feats = [
['مدیریت مشتریان','fa-users','gradient-bg',['ساخت مشتری با نام فارسی','سفارشات لحظه‌ای','تمدید 1 کلیک','تغییر محصول/پلن','حذف/مسدود/ریست','لینک تحویل + QR فارسی']],
['مالی فارسی','fa-chart-pie','bg-emerald-500',['فاکتور ماهانه فارسی','سود خالص','نمودار درآمد','Excel فارسی','کیف پول هدیه‌دار','تراکنش‌ها فارسی']],
['ربات تلگرام فارسی','fa-telegram','bg-sky-500',['یوزرنیم دلخواه','منوهای فارسی','فروش 24 ساعته فارسی','پرداخت کارت/تتر/تون','ارسال خودکار محصول فارسی','تست رایگان فارسی']],
['اپ اختصاصی فارسی','fa-mobile-screen','bg-amber-500',['برند شما - وایت‌لیبل فارسی','Universal+ARM64','اتصال 1 کلیک فارسی','سفارشات و موجودی فارسی','انتخاب محصول فارسی','آپدیت رایگان']],
['محصول و دسته‌بندی','fa-layer-group','bg-violet-500',['محصول اختصاصی ⭐ فارسی','دسته‌بندی تو در تو','مدت 1 روز تا 1 ساله','موجودی نامحدود','اقتصادی/VIP/ویژه','پنل فروش محصول']],
['هوش مصنوعی فارسی','fa-robot','bg-cyan-500',['پاسخ خودکار متن+عکس فارسی','راهنمای تصویری فارسی','پنل و تلگرام آلبوم','کاهش 80% تیکت فارسی','12 سند جامع فارسی','جستجوی هوشمند فارسی']],
['درآمد تیمی فارسی','fa-people-group','bg-indigo-500',['ساب‌فروشنده فارسی','پورسانت فارسی','انتقال اعتبار فارسی','لینک دعوت 10% فارسی','کد تخفیف فارسی']],
['امنیت فارسی','fa-shield-halved','bg-pink-500',['وایت‌لیبل 100% فارسی','دامنه اختصاصی فارسی','API فارسی','بک‌آپ روزانه فارسی','لاگ + 2FA فارسی']],
];
foreach($feats as $f){
 echo '<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5"><div class="flex items-center gap-3"><div class="w-10 h-10 '.$f[2].' rounded-[12px] flex items-center justify-center"><i class="fa-solid '.$f[1].' text-sm"></i></div><div class="font-bold text-[13px]">'.$f[0].'</div></div><ul class="mt-4 space-y-2">';
 foreach($f[3] as $it) echo '<li class="flex gap-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400 text-[10px] mt-0.5"></i> '.$it.'</li>';
 echo '</ul></div>';
}
?>
</div>
</div>
</section>

<!-- REQUEST -->
<section id="request" class="py-20 px-4">
<div class="max-w-6xl mx-auto grid lg:grid-cols-5 gap-8">
<div class="lg:col-span-2 space-y-5">
<h2 class="text-[34px] font-black leading-[1.1]">فرم درخواست<br><span class="gradient-text">30 دقیقه بعد پنلت آماده‌ست</span></h2>
<p class="text-white/60 text-[13px] leading-7">فرم فارسی - پشتیبانی: @mainAdminpanel - <?= htmlspecialchars($domain) ?> - پنل فروش محصول</p>
<img src="<?= $base ?>ads/banner-fa-2.jpg" class="w-full rounded-[20px] border border-white/[0.06]">
</div>
<div class="lg:col-span-3"><div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[28px] p-1"><div class="bg-[#0A0D18] rounded-[24px] p-6">
<?php if($success): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 rounded-[20px] p-8 text-center"><div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto text-3xl">✓</div><h3 class="font-black text-[20px] text-emerald-300 mt-3">ثبت شد! ✅</h3><p class="text-[13px] text-white/70 mt-2">کمتر از 30 دقیقه تماس می‌گیریم.<br><a href="<?= $tg_url ?>" class="text-sky-400 underline">@mainAdminpanel</a></p></div>
<?php else: ?>
<?php if($error): ?><div class="bg-rose-500/10 border border-rose-500/20 rounded-[14px] p-3 text-[12px] text-rose-300 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="space-y-4">
<input type="hidden" name="reseller_request" value="1">
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/50 mb-2">نام *</label><input type="text" name="name" required placeholder="علی رضایی" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"></div><div><label class="block text-[11px] text-white/50 mb-2">موبایل</label><input type="text" name="phone" placeholder="0912..." class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"></div></div>
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/50 mb-2">تلگرام *</label><input type="text" name="telegram_id" required placeholder="@username" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"></div><div><label class="block text-[11px] text-white/50 mb-2">پلن</label><select name="plan" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"><option>استارتر 299ت</option><option selected>حرفه‌ای 599ت ⭐ با اپ فارسی</option><option>بیزینس 1.29م</option></select></div></div>
<button type="submit" class="w-full gradient-bg rounded-full py-4 font-black text-[14px]">🚀 ثبت درخواست + 500ت هدیه - @mainAdminpanel</button>
</form>
<?php endif; ?>
</div></div></div>
</div>
</section>

<footer class="border-t border-white/[0.06] py-8 px-4 text-center text-[11px] text-white/25">© 2025 Connectix v6.6 فارسی - پنل فروش محصول - <?= htmlspecialchars($domain) ?> - @mainAdminpanel - demo/demo123 فقط دیدنی</footer>

<script>
// cursor
const dot=document.getElementById('cursorDot'), ring=document.getElementById('cursorRing');
if(dot&&ring&&innerWidth>768){let mx=0,my=0,rx=0,ry=0;addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;dot.style.left=mx-4+'px';dot.style.top=my-4+'px';});(function l(){rx+=(mx-rx)*.15;ry+=(my-ry)*.15;ring.style.left=rx-20+'px';ring.style.top=ry-20+'px';requestAnimationFrame(l);})();}
document.querySelectorAll('.tilt').forEach(c=>{c.addEventListener('mousemove',e=>{const r=c.getBoundingClientRect();const x=e.clientX-r.left-r.width/2,y=e.clientY-r.top-r.height/2;c.style.transform=`perspective(1000px) rotateY(${x/18}deg) rotateX(${-y/18}deg)`});c.addEventListener('mouseleave',()=>c.style.transform='perspective(1000px) rotateY(0) rotateX(0)');});

// Three.js
try{
 const canvas=document.getElementById('canvas3d'); const scene=new THREE.Scene(); const camera=new THREE.PerspectiveCamera(75,innerWidth/innerHeight,0.1,1000);
 const renderer=new THREE.WebGLRenderer({canvas,alpha:true,antialias:true}); renderer.setSize(innerWidth,innerHeight); renderer.setPixelRatio(Math.min(devicePixelRatio,2));
 const geo=new THREE.BufferGeometry(); const cnt=1200; const pos=new Float32Array(cnt*3); for(let i=0;i<cnt*3;i++) pos[i]=(Math.random()-0.5)*18; geo.setAttribute('position',new THREE.BufferAttribute(pos,3));
 const mat=new THREE.PointsMaterial({size:0.025,color:0x8B5CF6,transparent:true,opacity:0.5}); const pts=new THREE.Points(geo,mat); scene.add(pts);
 const torus=new THREE.Mesh(new THREE.TorusGeometry(2.8,0.25,16,100), new THREE.MeshBasicMaterial({color:0x06B6D4,wireframe:true,transparent:true,opacity:0.12})); scene.add(torus);
 camera.position.z=6; let mx=0,my=0; addEventListener('mousemove',e=>{mx=(e.clientX/innerWidth-0.5)*1.5;my=(e.clientY/innerHeight-0.5)*1.5;});
 (function anim(){requestAnimationFrame(anim); pts.rotation.y+=0.0004; pts.rotation.x+=0.00015; torus.rotation.x+=0.0015; torus.rotation.y+=0.002; camera.position.x+=(mx*0.4-camera.position.x)*0.04; camera.position.y+=(-my*0.4-camera.position.y)*0.04; camera.lookAt(0,0,0); renderer.render(scene,camera);})();
 addEventListener('resize',()=>{camera.aspect=innerWidth/innerHeight;camera.updateProjectionMatrix();renderer.setSize(innerWidth,innerHeight);});
}catch(e){}

// Video slideshow with audio
const slides=["<?= $base ?>ads/banner-fa-1.jpg","<?= $base ?>ads/3d-dashboard.jpg","<?= $base ?>ads/banner-fa-2.jpg","<?= $base ?>ads/3d-globe.jpg","<?= $base ?>ads/banner-fa-3.jpg","<?= $base ?>ads/3d-phone.jpg"];
let sIdx=0; const imgEl=document.getElementById('slideImage'); const audio=document.getElementById('narration'); const playBtn=document.getElementById('playBtn'); const playPause=document.getElementById('playPause'); const progress=document.getElementById('progressBar');
let slideInterval;
function startSlides(){slideInterval=setInterval(()=>{sIdx=(sIdx+1)%slides.length; if(imgEl) {imgEl.style.opacity=0; setTimeout(()=>{imgEl.src=slides[sIdx]; imgEl.style.opacity=1;},300);} },3000);}
function stopSlides(){clearInterval(slideInterval);}
function updateProgress(){if(audio.duration){progress.style.width=(audio.currentTime/audio.duration*100)+'%';}}

playBtn?.addEventListener('click',()=>{audio.play();});
playPause?.addEventListener('click',()=>{
 if(audio.paused){audio.play();} else {audio.pause();}
});
audio?.addEventListener('play',()=>{playBtn.parentElement.style.display='none'; playPause.innerHTML='<i class="fa-solid fa-pause text-xs"></i>'; startSlides();});
audio?.addEventListener('pause',()=>{playPause.innerHTML='<i class="fa-solid fa-play text-xs"></i>'; stopSlides();});
audio?.addEventListener('timeupdate',updateProgress);
audio?.addEventListener('ended',()=>{progress.style.width='0%'; playBtn.parentElement.style.display='flex'; sIdx=0; imgEl.src=slides[0];});

function copyText(t){navigator.clipboard.writeText(t); alert('کپی شد: '+t);}
</script>
</body>
</html>

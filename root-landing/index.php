<?php
// Connectix 3D Ultra Landing - v6.0 - Persian - Three.js + Video + Demo
// https://vpbotn.ir/contax/promo/ | https://vpbotn.ir/
// @mainAdminpanel - Full 3D with special effects
$brand = 'Connectix';
$tg = '@mainAdminpanel';
$tg_url = 'https://t.me/mainAdminpanel';
$panel_url = 'https://vpbotn.ir/contax/';
$demo_user = 'demo';
$demo_pass = 'demo123';
$domain = 'https://vpbotn.ir';
$base = $domain . '/contax/';
$success = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reseller_request'])) {
    $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $tgid = trim($_POST['telegram_id'] ?? '');
    $plan = trim($_POST['plan'] ?? ''); $biz = trim($_POST['business'] ?? ''); $msg = trim($_POST['message'] ?? '');
    if ($name === '' || ($phone === '' && $tgid === '')) { $error = 'لطفاً نام و یک راه ارتباطی را وارد کنید.'; }
    else {
        @file_put_contents(__DIR__.'/requests.log', date('Y-m-d H:i:s')." | 3D Landing | $name | $phone | $tgid | $plan | $biz | $msg | IP:".($_SERVER['REMOTE_ADDR']??'')."\n", FILE_APPEND);
        try {
            $root = dirname(__DIR__);
            if (file_exists($root.'/config.php')) {
                require_once $root.'/config.php'; require_once $root.'/core/Database.php'; require_once $root.'/core/Setting.php'; require_once $root.'/core/TelegramBot.php';
                $pdo = Database::getConnection(); $token = Setting::get('telegram_bot_token',''); $admin = Setting::get('telegram_admin_id',''); $logCh = Setting::get('bot_log_channel','') ?: Setting::get('telegram_log_channel_id','');
                $text = "🌌 <b>درخواست جدید از لندینگ سه‌بعدی</b>\n\n👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tgid\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n🌐 https://vpbotn.ir/contax/promo/ (نسخه سه‌بعدی)\n🕐 ".date('Y-m-d H:i:s');
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
<title>🌌 پنل نمایندگی VPN سه‌بعدی با اپ اختصاصی فارسی | Connectix 3D | @mainAdminpanel</title>
<meta name="description" content="اولین پنل VPN سه‌بعدی ایران با گرافیک سینمایی، اپ اختصاصی فارسی، ربات فروش خودکار، سود 200%، دمو آنلاین demo/demo123، ویدیو معرفی. @mainAdminpanel - https://vpbotn.ir">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<style>
*{font-family:Vazirmatn,system-ui!important}
html{scroll-behavior:smooth}
body{background:#03050A;color:#fff;overflow-x:hidden;cursor:none}
@media(max-width:768px){body{cursor:auto}}
.glass{backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px)}
.gradient-text{background:linear-gradient(90deg,#8B5CF6 0%,#06B6D4 40%,#10B981 80%,#F59E0B 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.gradient-bg{background:linear-gradient(135deg,#7C3AED 0%,#4F46E5 25%,#06B6D4 70%,#10B981 100%)}
.gradient-border{position:relative}
.gradient-border::before{content:'';position:absolute;inset:0;border-radius:inherit;padding:1.2px;background:linear-gradient(135deg,#7C3AED,#06B6D4,#10B981);-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
.cursor-dot{width:8px;height:8px;background:#8B5CF6;border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:9999;mix-blend-mode:screen;box-shadow:0 0 20px #8B5CF6}
.cursor-ring{width:40px;height:40px;border:1.5px solid rgba(139,92,246,.5);border-radius:50%;position:fixed;top:0;left:0;pointer-events:none;z-index:9998;transition:all .15s ease-out}
.card-3d{transform-style:preserve-3d;transition:transform .6s cubic-bezier(.23,1,.32,1)}
.card-3d:hover{transform:rotateY(5deg) rotateX(5deg) translateZ(20px)}
.tilt{transform-style:preserve-3d;transition:transform .3s ease-out}
@keyframes float3d{0%,100%{transform:translateY(0) rotateX(0) rotateY(0)}33%{transform:translateY(-10px) rotateX(2deg) rotateY(2deg)}66%{transform:translateY(-5px) rotateX(-1deg) rotateY(-2deg)}}
.float3d{animation:float3d 8s ease-in-out infinite}
@keyframes glowPulse{0%,100%{box-shadow:0 0 40px rgba(124,58,237,.3),0 0 80px rgba(6,182,214,.15),inset 0 0 20px rgba(255,255,255,.05)}50%{box-shadow:0 0 60px rgba(124,58,237,.5),0 0 100px rgba(6,182,214,.25),inset 0 0 30px rgba(255,255,255,.08)}}
.glow-3d{animation:glowPulse 4s ease-in-out infinite}
@keyframes orbit{from{transform:rotate(0) translateX(120px) rotate(0)}to{transform:rotate(360deg) translateX(120px) rotate(-360deg)}}
.orbit{animation:orbit 20s linear infinite}
#canvas3d{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;opacity:.6}
.video-modal{backdrop-filter:blur(20px)}
::-webkit-scrollbar{width:8px}::-webkit-scrollbar-track{background:#03050A}::-webkit-scrollbar-thumb{background:linear-gradient(#7C3AED,#06B6D4);border-radius:4px}
</style>
</head>
<body class="antialiased selection:bg-violet-500/30">

<!-- Custom Cursor -->
<div class="cursor-dot hidden md:block" id="cursorDot"></div>
<div class="cursor-ring hidden md:block" id="cursorRing"></div>

<!-- 3D Canvas Background -->
<canvas id="canvas3d"></canvas>

<!-- Background Glows -->
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
<div class="absolute top-[-30%] right-[-20%] w-[900px] h-[900px] bg-violet-600/25 rounded-full blur-[160px]"></div>
<div class="absolute top-[40%] left-[-15%] w-[700px] h-[700px] bg-cyan-500/20 rounded-full blur-[140px]"></div>
<div class="absolute bottom-[-30%] right-[30%] w-[800px] h-[800px] bg-indigo-600/20 rounded-full blur-[150px]"></div>
<div class="absolute inset-0 opacity-[0.03]" style="background-image:linear-gradient(rgba(255,255,255,.1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.1) 1px,transparent 1px);background-size:60px 60px"></div>
</div>

<!-- NAV 3D -->
<nav class="fixed top-0 w-full z-50 bg-[#03050A]/60 glass border-b border-white/[0.05]">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between items-center h-[72px]">
<div class="flex items-center gap-3.5">
<div class="relative w-11 h-11 gradient-bg rounded-[14px] flex items-center justify-center font-black text-white shadow-[0_0_30px_rgba(124,58,237,.4)]"><span class="relative z-10">C</span><div class="absolute inset-0 gradient-bg rounded-[14px] blur-[10px] opacity-60"></div></div>
<div><div class="font-black text-[17px] leading-none flex items-center gap-2">Connectix <span class="text-[9px] bg-gradient-to-r from-violet-500 to-cyan-500 rounded-full px-2.5 py-1 font-black">3D v6.0 فارسی</span></div><div class="text-[10px] text-white/40 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_8px_rgba(16,185,129,.8)]"></span> سه‌بعدی • فارسی • آنلاین • @mainAdminpanel</div></div>
</div>
<div class="hidden lg:flex items-center gap-1 bg-white/[0.03] border border-white/[0.05] rounded-full p-1 backdrop-blur-xl">
<a href="#features" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">امکانات 3D</a>
<a href="#why" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">چرا جذابه؟</a>
<a href="#app" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">اپ سه‌بعدی</a>
<a href="#video" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">ویدیو</a>
<a href="#demo" class="px-4 py-2 rounded-full text-[12px] bg-white/[0.08] text-white">دمو آنلاین</a>
<a href="#pricing" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">تعرفه</a>
</div>
<div class="flex items-center gap-2.5">
<a href="<?= $tg_url ?>" target="_blank" class="hidden sm:flex items-center gap-2 bg-white/[0.05] hover:bg-white/[0.08] border border-white/[0.06] rounded-full px-4 py-2.5 text-[12px] transition"><i class="fa-brands fa-telegram text-sky-400"></i> @mainAdminpanel</a>
<a href="#request" class="gradient-bg rounded-full px-6 py-2.5 text-[12px] font-black shadow-[0_0_30px_rgba(124,58,237,.4)] hover:shadow-[0_0_50px_rgba(124,58,237,.6)] transition-all">ثبت درخواست 3D</a>
</div>
</div>
</div>
</nav>

<!-- HERO 3D -->
<section class="relative min-h-[100vh] flex items-center pt-20 pb-12 px-4">
<div class="max-w-7xl mx-auto w-full grid lg:grid-cols-[1.15fr_0.85fr] gap-12 items-center">
<div class="space-y-7 relative z-10">
<div class="inline-flex items-center gap-2 bg-gradient-to-r from-violet-500/15 via-cyan-500/10 to-emerald-500/10 border border-violet-500/20 rounded-full pl-2 pr-5 py-2.5 backdrop-blur-xl">
<span class="bg-gradient-to-r from-violet-500 to-cyan-500 text-white text-[10px] font-black rounded-full px-3 py-1 shadow-[0_0_20px_rgba(124,58,237,.5)]">🌌 نسخه سه‌بعدی جدید</span>
<span class="text-[12px] text-violet-200">گرافیک سینمایی + افکت‌های ویژه + ویدیو + دمو آنلاین</span>
</div>
<h1 class="text-[42px] sm:text-[56px] lg:text-[62px] font-black leading-[0.95] tracking-tight">
<span class="block">پنل نمایندگی</span>
<span class="block gradient-text">سه‌بعدی VPN</span>
<span class="block text-[28px] sm:text-[34px] text-white/80 mt-2">با اپ اختصاصی فارسی</span>
<span class="block text-[18px] sm:text-[20px] font-bold text-emerald-300 mt-3">درآمد ماهانه 10 تا 25 میلیون - کاملاً فارسی</span>
</h1>
<p class="text-[14px] leading-7 text-white/55 max-w-[580px]">
اولین پنل VPN <b class="text-white">سه‌بعدی ایران</b> با گرافیک سینمایی. بدون کدنویسی، با برند خودت کسب و کار راه بنداز. اپ فارسی، ربات فروش 24 ساعته فارسی، سود 200%، سرور VLESS Reality. <b class="text-violet-300">دمو آنلاین: demo/demo123</b> - پشتیبانی: @mainAdminpanel
</p>
<div class="flex flex-wrap gap-3.5">
<a href="#request" class="group relative gradient-bg rounded-full px-8 py-4 font-black text-[14px] flex items-center gap-2.5 shadow-[0_0_50px_rgba(124,58,237,.5)] hover:shadow-[0_0_80px_rgba(124,58,237,.7)] transition-all overflow-hidden">
<span class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/20 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></span>
<i class="fa-solid fa-rocket group-hover:translate-x-1 transition-transform"></i> ثبت درخواست سه‌بعدی - 30 دقیقه تحویل
</a>
<a href="#video" class="group bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.08] rounded-full px-7 py-4 font-bold text-[13px] flex items-center gap-2.5 backdrop-blur-xl transition">
<span class="w-8 h-8 bg-white rounded-full flex items-center justify-center group-hover:scale-110 transition"><i class="fa-solid fa-play text-black text-[12px] ml-0.5"></i></span> دیدن ویدیو معرفی 60 ثانیه‌ای
</a>
</div>
<div class="flex flex-wrap gap-2.5 pt-1">
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.05] rounded-full px-3.5 py-2 text-[11px] text-white/60 backdrop-blur"><i class="fa-solid fa-cube text-violet-400"></i> سه‌بعدی واقعی - Three.js</span>
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.05] rounded-full px-3.5 py-2 text-[11px] text-white/60 backdrop-blur"><i class="fa-solid fa-language text-cyan-400"></i> 100% فارسی</span>
<span class="inline-flex items-center gap-1.5 bg-white/[0.04] border border-white/[0.05] rounded-full px-3.5 py-2 text-[11px] text-white/60 backdrop-blur"><i class="fa-solid fa-bolt text-amber-400"></i> افکت‌های ویژه سینمایی</span>
<span class="inline-flex items-center gap-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3.5 py-2 text-[11px] text-emerald-300 backdrop-blur"><i class="fa-solid fa-video"></i> ویدیو + دمو آنلاین</span>
</div>
</div>

<!-- 3D Hero Visual -->
<div class="relative lg:h-[700px] flex items-center justify-center">
<!-- Floating 3D Cards -->
<div class="relative w-full max-w-[520px] aspect-[4/3]">
<!-- Main 3D Panel -->
<div class="absolute inset-0 bg-gradient-to-br from-white/[0.08] to-white/[0.02] border border-white/[0.08] rounded-[32px] p-4 shadow-[0_0_100px_rgba(124,58,237,.2)] tilt float3d" id="tiltCard">
<img src="<?= $base ?>ads/3d-dashboard.jpg" alt="پنل سه‌بعدی فارسی" class="w-full h-full object-cover rounded-[22px] border border-white/[0.06]">
<div class="absolute inset-4 rounded-[22px] bg-gradient-to-t from-violet-600/20 via-transparent to-transparent pointer-events-none"></div>
</div>
<!-- Orbiting elements -->
<div class="absolute -top-8 -right-8 w-20 h-20 bg-gradient-to-br from-violet-500 to-cyan-500 rounded-[20px] flex items-center justify-center shadow-[0_0_40px_rgba(124,58,237,.6)] orbit" style="animation-duration:15s">
<i class="fa-solid fa-mobile-screen text-xl"></i>
</div>
<div class="absolute -bottom-10 -left-10 w-16 h-16 bg-gradient-to-br from-emerald-500 to-cyan-500 rounded-[16px] flex items-center justify-center shadow-[0_0_30px_rgba(16,185,129,.5)] orbit" style="animation-duration:12s;animation-direction:reverse">
<i class="fa-brands fa-telegram"></i>
</div>
<div class="absolute top-1/2 -right-12 bg-[#0A0D18]/90 glass border border-white/[0.08] rounded-[18px] p-4 shadow-2xl hidden lg:flex items-center gap-3 min-w-[210px] card-3d">
<div class="w-11 h-11 bg-emerald-500/15 border border-emerald-500/20 rounded-[12px] flex items-center justify-center"><i class="fa-solid fa-arrow-trend-up text-emerald-400"></i></div>
<div><div class="text-[10px] text-white/40">سود امروز - سه‌بعدی</div><div class="font-black text-emerald-400 text-[14px]">+2,450,000 تومان</div><div class="text-[9px] text-white/30">12 فروش توسط ربات فارسی</div></div>
</div>
<div class="absolute bottom-20 -left-12 bg-[#0A0D18]/90 glass border border-white/[0.08] rounded-full px-4 py-2.5 shadow-xl hidden lg:flex items-center gap-2.5">
<span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,.8)]"></span><span class="text-[11px] font-bold">ربات فروش سه‌بعدی فعال - فارسی</span>
</div>
</div>
<!-- 3D Images row -->
<div class="absolute -bottom-16 left-1/2 -translate-x-1/2 w-[110%] grid grid-cols-3 gap-3">
<img src="<?= $base ?>ads/3d-globe.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl card-3d">
<img src="<?= $base ?>ads/banner-fa-premium.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl card-3d mt-6">
<img src="<?= $base ?>ads/3d-phone.jpg" class="rounded-[18px] border border-white/[0.06] h-[100px] object-cover shadow-xl card-3d">
</div>
</div>
</div>
</section>

<!-- VIDEO + DEMO SECTION - NEW 3D -->
<section id="video" class="py-24 px-4 relative">
<div class="max-w-7xl mx-auto">
<div class="grid lg:grid-cols-2 gap-8">

<!-- Video -->
<div class="group relative bg-gradient-to-br from-violet-600/10 via-indigo-600/5 to-cyan-500/10 border border-violet-500/20 rounded-[32px] p-2 shadow-[0_0_80px_rgba(124,58,237,.15)] card-3d">
<div class="bg-[#0A0D18] rounded-[24px] overflow-hidden relative aspect-video">
<img src="<?= $base ?>ads/banner-fa-premium.jpg" alt="ویدیو معرفی پنل سه‌بعدی فارسی" class="w-full h-full object-cover">
<div class="absolute inset-0 bg-gradient-to-t from-[#0A0D18] via-[#0A0D18]/40 to-transparent"></div>
<div class="absolute inset-0 flex items-center justify-center">
<button onclick="openVideo()" class="group/btn relative w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-[0_0_60px_rgba(255,255,255,.4)] hover:scale-110 transition-transform">
<i class="fa-solid fa-play text-black text-xl ml-1"></i>
<span class="absolute inset-0 rounded-full bg-white animate-ping opacity-20"></span>
</button>
</div>
<div class="absolute bottom-0 left-0 right-0 p-6">
<div class="inline-flex bg-white/10 backdrop-blur border border-white/20 rounded-full px-3 py-1 text-[10px]">🎬 ویدیو معرفی 60 ثانیه‌ای - فارسی - سه‌بعدی</div>
<h3 class="font-black text-[20px] mt-3">ویدیو معرفی پنل سه‌بعدی - با افکت‌های سینمایی</h3>
<p class="text-[12px] text-white/50 mt-1">ببین چطور در 60 ثانیه کسب و کارت رو راه می‌ندازی - کاملاً فارسی</p>
</div>
</div>
<div class="p-5 flex justify-between items-center">
<div class="flex items-center gap-3"><div class="w-10 h-10 bg-violet-500/20 rounded-full flex items-center justify-center"><i class="fa-solid fa-film text-violet-400 text-sm"></i></div><div><div class="font-bold text-[13px]">نسخه ویدیویی فارسی</div><div class="text-[11px] text-white/40">60 ثانیه - با زیرنویس فارسی + افکت سه‌بعدی</div></div></div>
<button onclick="openVideo()" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-5 py-2.5 text-[12px] font-bold hover:bg-white/[0.1] transition">پخش ویدیو <i class="fa-solid fa-play mr-1 text-[10px]"></i></button>
</div>
</div>

<!-- Demo Online -->
<div id="demo" class="group relative bg-gradient-to-br from-emerald-600/10 via-cyan-600/5 to-violet-600/10 border border-emerald-500/20 rounded-[32px] p-2 shadow-[0_0_80px_rgba(16,185,129,.15)] card-3d">
<div class="bg-[#0A0D18] rounded-[24px] p-6 h-full flex flex-col">
<div class="flex justify-between items-start">
<div><div class="inline-flex bg-emerald-500/15 border border-emerald-500/20 rounded-full px-3 py-1 text-[10px] text-emerald-300">● آنلاین - دمو فعال - فارسی</div><h3 class="font-black text-[20px] mt-3">دمو آنلاین - تست زنده پنل سه‌بعدی فارسی</h3><p class="text-[12px] text-white/50 mt-1">بدون ثبت نام، همین الان وارد پنل دمو شو - کاملاً فارسی</p></div>
<div class="w-12 h-12 bg-emerald-500/20 rounded-[14px] flex items-center justify-center"><i class="fa-solid fa-laptop-code text-emerald-400"></i></div>
</div>

<div class="mt-6 bg-[#05070D] border border-white/[0.06] rounded-[18px] p-4 space-y-3">
<div class="flex justify-between items-center bg-white/[0.03] rounded-[12px] px-4 py-3"><span class="text-[11px] text-white/40">آدرس دمو:</span><span class="text-[12px] font-mono text-cyan-300">https://vpbotn.ir/contax/</span></div>
<div class="grid grid-cols-2 gap-3">
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[12px] px-4 py-3"><div class="text-[10px] text-white/40">نام کاربری</div><div class="font-mono font-bold text-[13px] mt-1 flex items-center gap-2"><i class="fa-solid fa-user text-violet-400 text-[11px]"></i> <?= $demo_user ?> <button onclick="copyText('<?= $demo_user ?>')" class="mr-auto text-[10px] bg-white/[0.06] rounded-full px-2 py-1 hover:bg-white/[0.1]">کپی</button></div></div>
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[12px] px-4 py-3"><div class="text-[10px] text-white/40">رمز عبور</div><div class="font-mono font-bold text-[13px] mt-1 flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-400 text-[11px]"></i> <?= $demo_pass ?> <button onclick="copyText('<?= $demo_pass ?>')" class="mr-auto text-[10px] bg-white/[0.06] rounded-full px-2 py-1 hover:bg-white/[0.1]">کپی</button></div></div>
</div>
<div class="bg-amber-500/10 border border-amber-500/20 rounded-[12px] px-4 py-2.5 text-[11px] text-amber-300 flex gap-2"><i class="fa-solid fa-lightbulb mt-0.5"></i> نکته: این دمو فقط برای نمایشه، تغییرات ذخیره نمی‌شه. برای پنل واقعی درخواست بده.</div>
</div>

<div class="mt-5 grid grid-cols-3 gap-2.5 text-center">
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[14px] py-3"><div class="font-black text-[14px]">50+</div><div class="text-[9px] text-white/40">قابلیت فارسی</div></div>
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[14px] py-3"><div class="font-black text-[14px] text-emerald-400">3D</div><div class="text-[9px] text-white/40">گرافیک سه‌بعدی</div></div>
<div class="bg-white/[0.03] border border-white/[0.05] rounded-[14px] py-3"><div class="font-black text-[14px] text-violet-300">فارسی</div><div class="text-[9px] text-white/40">100% فارسی</div></div>
</div>

<div class="mt-5 flex gap-2.5">
<a href="<?= $panel_url ?>" target="_blank" class="flex-1 gradient-bg rounded-full py-3.5 text-center text-[13px] font-black shadow-[0_0_30px_rgba(124,58,237,.4)] hover:shadow-[0_0_50px_rgba(124,58,237,.6)] transition">🚀 ورود به دمو آنلاین فارسی</a>
<a href="<?= $tg_url ?>" target="_blank" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-5 py-3.5 text-[12px] font-bold hover:bg-white/[0.1] transition"><i class="fa-brands fa-telegram text-sky-400"></i></a>
</div>

<div class="mt-4 relative rounded-[16px] overflow-hidden border border-white/[0.06] aspect-[16/9] bg-[#05070D]">
<div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-violet-600/20 to-cyan-500/20"><div class="text-center"><div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-solid fa-desktop text-xl"></i></div><div class="text-[11px] text-white/60">پیش‌نمایش زنده پنل فارسی</div><div class="text-[10px] text-white/30 mt-1">https://vpbotn.ir/contax/ - demo/demo123</div></div></div>
<img src="<?= $base ?>assets/ai_guides/reseller-panel.jpg" class="w-full h-full object-cover opacity-30">
</div>

</div>
</div>

</div>
</div>
</section>

<!-- FULL FEATURES 3D -->
<section id="features" class="py-24 px-4 bg-white/[0.02] border-y border-white/[0.06] relative">
<div class="max-w-7xl mx-auto">
<div class="flex flex-col lg:flex-row justify-between gap-6 mb-14">
<div><div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📋 لیست کامل 50+ امکانات - سه‌بعدی - فارسی</div><h2 class="text-[32px] sm:text-[44px] font-black leading-[1.1] mt-4">هرچی برای فروش نیاز داری،<br><span class="gradient-text">اینجاست - سه‌بعدی فارسی</span></h2></div>
<p class="text-white/50 text-[13px] leading-6 max-w-md">8 دسته اصلی، 50+ ویژگی. همه فارسی، همه سه‌بعدی، همه آماده. از ساخت کلاینت تا هوش مصنوعی با عکس.</p>
</div>
<div class="grid lg:grid-cols-4 gap-5">
<?php
$features = [
['مدیریت مشتریان','Client Management','fa-users','gradient-bg',['ساخت با نام فارسی','مصرف لحظه‌ای (استفاده/مانده)','تمدید 1 کلیک + تغییر پلن','تغییر حجم، سرور، سقف اتصال','حذف، مسدود، ریست ترافیک','لینک ساب اختصاصی + QR کد فارسی']],
['مالی و گزارش فارسی','Billing','fa-chart-pie','bg-emerald-500',['فاکتور ماهانه خودکار فارسی','سود خالص (فروش-خرید)','نمودار درآمد روزانه/ماهانه','Excel/CSV فارسی','کیف پول هدیه پلکانی','تاریخچه تراکنش‌ها فارسی']],
['ربات تلگرام فارسی','Telegram Bot','fa-telegram','bg-sky-500',['یوزرنیم دلخواه @YourBrandBot','منوهای کاملاً فارسی','فروش خودکار 24 ساعته فارسی','پرداخت کارت، زرین‌پال، تتر، تون','ارسال خودکار کانفیگ فارسی','تست رایگان + زیرمجموعه‌گیری فارسی']],
['اپ اختصاصی سه‌بعدی فارسی','Custom App 3D','fa-mobile-screen','bg-amber-500',['نام و لوگوی شما - وایت‌لیبل 100% فارسی','Universal + ARM64 بهینه','اتصال 1 کلیک فارسی سه‌بعدی','حجم و روزهای باقی‌مانده فارسی','انتخاب سرور + تست سرعت فارسی','آپدیت مادام‌العمر رایگان']],
['پلن و سرور ضدفیلتر','Plans & Servers','fa-layer-group','bg-violet-500',['پلن اختصاصی با ⭐ فارسی','حجم 5گیگ تا نامحدود','مدت 7 روز تا 1 ساله فارسی','سقف 1 تا نامحدود','دسته اقتصادی/VIP/ایران‌اکسس','VLESS Reality ضدفیلتر']],
['هوش مصنوعی سه‌بعدی فارسی','AI 3D Persian','fa-robot','bg-cyan-500',['پاسخ خودکار متن+عکس فارسی','راهنمای 3 مرحله‌ای تصویری فارسی','در پنل و تلگرام آلبوم سه‌بعدی','کاهش 80% تیکت فارسی','پایگاه دانش 12 سند جامع فارسی','جستجوی هوشمند با مترادف فارسی']],
['کسب درآمد تیمی فارسی','Team Earning','fa-people-group','bg-indigo-500',['ساب‌نماینده فارسی','پورسانت فروش ساب فارسی','انتقال اعتبار فارسی','لینک دعوت 10% فارسی','کد تخفیف اختصاصی فارسی']],
['امنیت و وایت‌لیبل فارسی','Security','fa-shield-halved','bg-pink-500',['وایت‌لیبل 100% فارسی - بدون نام Connectix','دامنه + لینک ساب اختصاصی فارسی','API سبک PanelMS فارسی','بک‌آپ خودکار روزانه فارسی','لاگ کامل + 2FA فارسی']],
];
foreach($features as $f){
 echo '<div class="bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-5 card-3d"><div class="flex items-center gap-3"><div class="w-10 h-10 '.$f[3].' rounded-[12px] flex items-center justify-center"><i class="fa-solid '.$f[2].' text-sm"></i></div><div><div class="font-bold text-[13px]">'.$f[0].'</div><div class="text-[10px] text-white/40">'.$f[1].'</div></div></div><ul class="mt-4 space-y-2.5">';
 foreach($f[4] as $it) echo '<li class="flex gap-2 text-[11px] text-white/60"><i class="fa-solid fa-check text-emerald-400 mt-0.5 text-[10px]"></i> '.$it.'</li>';
 echo '</ul></div>';
}
?>
</div>
</div>
</section>

<!-- REQUEST -->
<section id="request" class="py-24 px-4">
<div class="max-w-6xl mx-auto grid lg:grid-cols-5 gap-10">
<div class="lg:col-span-2 space-y-6">
<div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📝 فرم سه‌بعدی فارسی - 30 ثانیه</div>
<h2 class="text-[34px] font-black leading-[1.1]">فرم رو پر کن،<br><span class="gradient-text">30 دقیقه بعد پنلت سه‌بعدی آماده‌ست</span></h2>
<p class="text-white/60 text-[13px] leading-7">اطلاعاتت رو وارد کن، کمتر از 30 دقیقه با شما تماس می‌گیریم. مشاوره رایگان فارسی - @mainAdminpanel</p>
<img src="<?= $base ?>ads/banner-fa-2.jpg" class="w-full rounded-[20px] border border-white/[0.06] shadow-xl">
</div>
<div class="lg:col-span-3"><div class="bg-gradient-to-b from-white/[0.06] to-white/[0.02] border border-white/[0.08] rounded-[28px] p-1 shadow-[0_0_80px_rgba(124,58,237,.15)]"><div class="bg-[#0A0D18] rounded-[24px] p-6 sm:p-8">
<?php if($success): ?>
<div class="bg-emerald-500/10 border border-emerald-500/20 rounded-[20px] p-8 text-center space-y-4"><div class="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center mx-auto text-3xl shadow-[0_0_40px_rgba(16,185,129,.4)]"><i class="fa-solid fa-check"></i></div><h3 class="font-black text-[20px] text-emerald-300">درخواست سه‌بعدی ثبت شد! ✅</h3><p class="text-[13px] text-white/70 leading-7">کمتر از 30 دقیقه با شما تماس می‌گیریم.<br>سریع‌تر: <a href="<?= $tg_url ?>" class="text-sky-400 font-bold underline">@mainAdminpanel</a></p><a href="<?= $tg_url ?>" class="inline-flex gradient-bg rounded-full px-8 py-3 text-[13px] font-bold"><i class="fa-brands fa-telegram"></i> پیام به @mainAdminpanel</a></div>
<?php else: ?>
<?php if($error): ?><div class="bg-rose-500/10 border border-rose-500/20 rounded-[14px] p-3 text-[12px] text-rose-300 mb-5"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="space-y-5">
<input type="hidden" name="reseller_request" value="1">
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/50 mb-2">نام و نام خانوادگی *</label><input type="text" name="name" required placeholder="مثلاً: علی رضایی" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:border-violet-500/50"></div><div><label class="block text-[11px] text-white/50 mb-2">شماره موبایل</label><input type="text" name="phone" placeholder="0912..." class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:border-violet-500/50"></div></div>
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/50 mb-2">آیدی تلگرام *</label><input type="text" name="telegram_id" required placeholder="@username" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px] focus:outline-none focus:border-violet-500/50"></div><div><label class="block text-[11px] text-white/50 mb-2">پلن سه‌بعدی فارسی</label><select name="plan" class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"><option>استارتر 299ت - فارسی</option><option selected>حرفه‌ای 599ت ⭐ با اپ سه‌بعدی فارسی</option><option>بیزینس 1.29م - کامل سه‌بعدی فارسی</option></select></div></div>
<div><label class="block text-[11px] text-white/50 mb-2">پیام فارسی</label><textarea name="message" rows="3" placeholder="توضیحات فارسی..." class="w-full bg-[#05070D] border border-white/[0.08] rounded-[14px] px-4 py-3.5 text-[13px]"></textarea></div>
<button type="submit" class="w-full gradient-bg rounded-full py-4 font-black text-[14px] flex items-center justify-center gap-2.5 shadow-[0_0_40px_rgba(124,58,237,.35)]">🚀 ثبت درخواست سه‌بعدی + 500ت هدیه فارسی</button>
<div class="text-center"><a href="<?= $tg_url ?>" class="inline-flex bg-sky-500/15 border border-sky-500/20 text-sky-300 rounded-full px-4 py-1.5 text-xs font-bold">ارتباط مستقیم: @mainAdminpanel - سه‌بعدی فارسی</a></div>
</form>
<?php endif; ?>
</div></div></div>
</div>
</section>

<!-- VIDEO MODAL -->
<div id="videoModal" class="fixed inset-0 z-[100] hidden">
<div class="absolute inset-0 bg-black/80 backdrop-blur-xl" onclick="closeVideo()"></div>
<div class="relative z-10 min-h-screen flex items-center justify-center p-4">
<div class="bg-[#0A0D18] border border-white/[0.1] rounded-[24px] w-full max-w-3xl overflow-hidden shadow-[0_0_100px_rgba(124,58,237,.3)]">
<div class="flex justify-between items-center p-5 border-b border-white/[0.06]"><h3 class="font-black">🎬 ویدیو معرفی پنل سه‌بعدی فارسی - 60 ثانیه</h3><button onclick="closeVideo()" class="w-8 h-8 bg-white/[0.06] rounded-full flex items-center justify-center hover:bg-white/[0.1]"><i class="fa-solid fa-xmark text-xs"></i></button></div>
<div class="aspect-video bg-black relative">
<img src="<?= $base ?>ads/banner-fa-premium.jpg" class="w-full h-full object-cover">
<div class="absolute inset-0 flex items-center justify-center bg-black/40"><div class="text-center"><div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto shadow-[0_0_50px_rgba(255,255,255,.5)]"><i class="fa-solid fa-play text-black text-xl ml-1"></i></div><p class="text-sm mt-4 text-white/80">ویدیو معرفی به زودی - فعلاً تصاویر سه‌بعدی فارسی</p><p class="text-xs text-white/40 mt-1">برای دیدن دمو آنلاین: demo/demo123 در https://vpbotn.ir/contax/</p></div></div>
</div>
<div class="p-5 flex gap-3"><a href="<?= $panel_url ?>" target="_blank" class="flex-1 gradient-bg rounded-full py-3 text-center text-sm font-bold">🚀 ورود به دمو آنلاین demo/demo123</a><a href="<?= $tg_url ?>" target="_blank" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-6 py-3 text-sm font-bold"> @mainAdminpanel</a></div>
</div>
</div>
</div>

<footer class="border-t border-white/[0.06] py-10 px-4 text-center text-[11px] text-white/25">
<div>© 2025 Connectix 3D v6.0 - سه‌بعدی فارسی - https://vpbotn.ir - @mainAdminpanel - demo/demo123</div>
</footer>

<script>
// Custom cursor
const dot = document.getElementById('cursorDot'), ring = document.getElementById('cursorRing');
if(dot && ring && window.innerWidth>768){
 let mx=0,my=0,rx=0,ry=0;
 document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;dot.style.left=mx-4+'px';dot.style.top=my-4+'px';});
 (function loop(){rx+=(mx-rx)*0.15;ry+=(my-ry)*0.15;ring.style.left=rx-20+'px';ring.style.top=ry-20+'px';requestAnimationFrame(loop);})();
 document.querySelectorAll('a,button').forEach(el=>{
  el.addEventListener('mouseenter',()=>{ring.style.transform='scale(1.5)';ring.style.borderColor='rgba(139,92,246,.8)';});
  el.addEventListener('mouseleave',()=>{ring.style.transform='scale(1)';ring.style.borderColor='rgba(139,92,246,.5)';});
 });
}
// 3D tilt
document.querySelectorAll('.tilt').forEach(card=>{
 card.addEventListener('mousemove',e=>{
  const r=card.getBoundingClientRect(); const x=e.clientX-r.left-r.width/2; const y=e.clientY-r.top-r.height/2;
  card.style.transform=`perspective(1000px) rotateY(${x/20}deg) rotateX(${-y/20}deg) translateZ(10px)`;
 });
 card.addEventListener('mouseleave',()=>{card.style.transform='perspective(1000px) rotateY(0) rotateX(0) translateZ(0)';});
});
// Three.js 3D background
try{
 const canvas=document.getElementById('canvas3d'); const scene=new THREE.Scene(); const camera=new THREE.PerspectiveCamera(75,window.innerWidth/window.innerHeight,0.1,1000);
 const renderer=new THREE.WebGLRenderer({canvas,alpha:true,antialias:true}); renderer.setSize(window.innerWidth,window.innerHeight); renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
 const particlesGeometry=new THREE.BufferGeometry(); const count=1500; const pos=new Float32Array(count*3);
 for(let i=0;i<count*3;i++) pos[i]=(Math.random()-0.5)*20; particlesGeometry.setAttribute('position',new THREE.BufferAttribute(pos,3));
 const particlesMaterial=new THREE.PointsMaterial({size:0.02,color:0x8B5CF6,transparent:true,opacity:0.6}); const particles=new THREE.Points(particlesGeometry,particlesMaterial); scene.add(particles);
 const torusGeometry=new THREE.TorusGeometry(3,0.3,16,100); const torusMaterial=new THREE.MeshBasicMaterial({color:0x06B6D4,wireframe:true,transparent:true,opacity:0.15}); const torus=new THREE.Mesh(torusGeometry,torusMaterial); scene.add(torus);
 camera.position.z=6;
 let mouseX=0,mouseY=0; document.addEventListener('mousemove',e=>{mouseX=(e.clientX/window.innerWidth-0.5)*2;mouseY=(e.clientY/window.innerHeight-0.5)*2;});
 function animate(){requestAnimationFrame(animate); particles.rotation.y+=0.0005; particles.rotation.x+=0.0002; torus.rotation.x+=0.002; torus.rotation.y+=0.003; torus.rotation.x+=mouseY*0.0005; torus.rotation.y+=mouseX*0.0005; camera.position.x+= (mouseX*0.5 - camera.position.x)*0.05; camera.position.y+= (-mouseY*0.5 - camera.position.y)*0.05; camera.lookAt(0,0,0); renderer.render(scene,camera);} animate();
 window.addEventListener('resize',()=>{camera.aspect=window.innerWidth/window.innerHeight;camera.updateProjectionMatrix();renderer.setSize(window.innerWidth,window.innerHeight);});
}catch(e){console.log('3D fallback',e);}

function openVideo(){document.getElementById('videoModal').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeVideo(){document.getElementById('videoModal').classList.add('hidden');document.body.style.overflow='';}
function copyText(t){navigator.clipboard.writeText(t);alert('کپی شد: '+t+' - فارسی');}
</script>
</body>
</html>

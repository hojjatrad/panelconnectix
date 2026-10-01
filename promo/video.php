<?php
// Video Presentation Page - Generic Product Sales Panel - No VPN Terms
$base = 'https://vpbotn.ir/contax/';
$tg_url = 'https://t.me/mainAdminpanel';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ویدیو معرفی پنل فروش محصول فارسی - Connectix</title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>*{font-family:Vazirmatn!important} body{background:#05070A;color:#fff}</style>
</head>
<body class="antialiased">
<nav class="fixed top-0 w-full z-50 bg-[#05070A]/80 backdrop-blur-xl border-b border-white/[0.06] px-4">
<div class="max-w-7xl mx-auto flex justify-between items-center h-16">
<div class="flex items-center gap-3"><div class="w-9 h-9 bg-gradient-to-br from-violet-600 to-cyan-500 rounded-xl flex items-center justify-center font-black">C</div><span class="font-black">Connectix - ویدیو معرفی فارسی - پنل فروش محصول</span></div>
<div class="flex gap-2"><a href="./" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-4 py-2 text-xs">بازگشت</a><a href="<?= $tg_url ?>" target="_blank" class="bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-5 py-2 text-xs font-bold">@mainAdminpanel</a></div>
</div>
</nav>

<section class="pt-28 pb-12 px-4 max-w-5xl mx-auto">
<div class="text-center mb-8">
<h1 class="text-3xl sm:text-4xl font-black">🎬 ویدیو معرفی پنل فروش محصول فارسی</h1>
<p class="text-white/50 text-sm mt-3">60 ثانیه - با صداگذاری حرفه‌ای فارسی + تصاویر جذاب - @mainAdminpanel - https://vpbotn.ir - پنل فروش محصول</p>
</div>

<div class="bg-gradient-to-br from-violet-600/10 to-cyan-500/10 border border-violet-500/20 rounded-[28px] p-2">
<div class="bg-[#0A0D18] rounded-[20px] overflow-hidden">
<div class="relative aspect-video bg-black" id="videoStage">
<img id="slide" src="<?= $base ?>ads/banner-fa-1.jpg" class="w-full h-full object-cover">
<div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
<div class="absolute bottom-0 left-0 right-0 p-6">
<h3 id="slideTitle" class="font-black text-xl">پنل فروش محصول با اپ اختصاصی فارسی</h3>
<p id="slideDesc" class="text-xs text-white/60 mt-1">اپ با برند شما + ربات فروش 24 ساعته + سود 200%</p>
</div>
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
<button id="playBtn" class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-[0_0_50px_rgba(255,255,255,.5)] hover:scale-110 transition"><i class="fa-solid fa-play text-black text-xl ml-1"></i></button>
</div>
<div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10"><div id="progress" class="h-full bg-gradient-to-r from-violet-500 to-cyan-500 w-0"></div></div>
</div>
<div class="p-5 flex items-center gap-4">
<audio id="audio" src="<?= $base ?>ads/video-narration-fa.mp3" preload="metadata"></audio>
<button id="playPause" class="w-11 h-11 bg-white/[0.08] border border-white/[0.1] rounded-full flex items-center justify-center"><i class="fa-solid fa-play text-xs"></i></button>
<div class="flex-1"><div class="text-[11px] text-white/40">صداگذاری فارسی - 60 ثانیه - پنل فروش محصول</div><div class="font-bold text-sm">معرفی کامل پنل فروش محصول فارسی</div></div>
<div class="flex gap-2"><button onclick="downloadAudio()" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-4 py-2 text-xs"><i class="fa-solid fa-download"></i> دانلود صدا</button><a href="<?= $tg_url ?>" target="_blank" class="bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-5 py-2.5 text-xs font-bold">@mainAdminpanel</a></div>
</div>
</div>
</div>

<div class="mt-8 grid md:grid-cols-3 gap-4">
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-violet-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-solid fa-film text-violet-400"></i></div><div class="font-bold text-sm">ویدیو 60 ثانیه‌ای فارسی</div><div class="text-[11px] text-white/50 mt-2">با صداگذاری حرفه‌ای فارسی، تصاویر جذاب، متن کاملاً فارسی - آماده برای اینستاگرام و تلگرام - پنل فروش محصول</div></div>
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-solid fa-share text-emerald-400"></i></div><div class="font-bold text-sm">قابل اشتراک‌گذاری</div><div class="text-[11px] text-white/50 mt-2">لینک این صفحه: https://vpbotn.ir/contax/promo/video.php - در همه جا پخش کنید</div></div>
<div class="bg-white/[0.04] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-sky-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-solid fa-download text-sky-400"></i></div><div class="font-bold text-sm">دانلود و استفاده</div><div class="text-[11px] text-white/50 mt-2">صدا و تصاویر قابل دانلود - می‌تونی با CapCut یا InShot ویدیو نهایی بسازی</div></div>
</div>

<div class="mt-8 bg-[#0A0D18] border border-white/[0.06] rounded-[20px] p-6">
<h3 class="font-black">📝 متن ویدیو (برای زیرنویس فارسی) - پنل فروش محصول:</h3>
<p class="text-xs text-white/60 leading-7 mt-3 whitespace-pre-wrap">سلام! به پنل فروش محصول کانکتینکس خوش آمدید. بزرگترین و حرفه ای ترین سیستم فروش محصول در ایران.

با ما، صاحب یک کسب و کار کامل با برند خودتان می شوید. اپلیکیشن اندروید اختصاصی با نام و لوگوی شما، ربات تلگرام فروش خودکار بیست و چهار ساعته، و سود دویست درصدی هر فروش.

پنل مدیریت کاملا فارسی، ساخت محصول اختصاصی، گزارش مالی، دسته‌بندی تو در تو، و هوش مصنوعی با راهنمای تصویری.

مدیریت سفارشات، موجودی نامحدود، پرداخت کارت به کارت، تتر و تون، بدون محدودیت.

تعرفه از دویست و نود و نه هزار تومان. جشنواره: سه ماه بخر، یک ماه هدیه، به همراه پانصد هزار تومان شارژ هدیه.

همین حالا به آیدی اصلی ادمین پنل پیام دهید و سی دقیقه بعد، پنل شما آماده است.

تلگرام: @mainAdminpanel
سایت: https://vpbotn.ir - پنل فروش محصول
</p>
</div>

</section>

<script>
const slides=[
{img:"<?= $base ?>ads/banner-fa-1.jpg",title:"پنل فروش محصول با اپ اختصاصی فارسی",desc:"اپ با برند شما + ربات فروش 24 ساعته + سود 200%"},
{img:"<?= $base ?>ads/3d-dashboard.jpg",title:"پنل مدیریت کاملاً فارسی - فروش محصول",desc:"مدیریت مشتریان، گزارش مالی، ساخت محصول اختصاصی"},
{img:"<?= $base ?>ads/banner-fa-2.jpg",title:"سود 200% - قیمت دست شما",desc:"خرید 40ت، فروش 120ت، سود 80ت - بدون سقف"},
{img:"<?= $base ?>ads/3d-globe.jpg",title:"مدیریت سفارشات نامحدود",desc:"سفارشات، محصولات، دسته‌بندی تو در تو"},
{img:"<?= $base ?>ads/banner-fa-3.jpg",title:"اپ اختصاصی با برند شما - فارسی",desc:"نام، لوگو، رنگ شما - Universal + ARM64"},
{img:"<?= $base ?>ads/3d-phone.jpg",title:"ربات تلگرام فروش خودکار فارسی",desc:"24 ساعته، پرداخت کارت، تتر، تون - @mainAdminpanel"},
{img:"<?= $base ?>ads/banner-fa-premium.jpg",title:"هوش مصنوعی فارسی با راهنمای تصویری",desc:"پاسخ خودکار با عکس - کاهش 80% تیکت"},
{img:"<?= $base ?>ads/reseller-panel.jpg",title:"درخواست پنل فروش - 30 دقیقه تحویل",desc:"@mainAdminpanel - https://vpbotn.ir - 500ت هدیه - پنل فروش محصول"},
];
let idx=0; const slideEl=document.getElementById('slide'); const titleEl=document.getElementById('slideTitle'); const descEl=document.getElementById('slideDesc'); const audio=document.getElementById('audio'); const playBtn=document.getElementById('playBtn'); const playPause=document.getElementById('playPause'); const progress=document.getElementById('progress');
let interval;
function showSlide(i){slideEl.style.opacity=0; setTimeout(()=>{slideEl.src=slides[i].img; titleEl.textContent=slides[i].title; descEl.textContent=slides[i].desc; slideEl.style.opacity=1;},300);}
function startSlides(){interval=setInterval(()=>{idx=(idx+1)%slides.length; showSlide(idx);},3000);}
function stopSlides(){clearInterval(interval);}
playBtn.addEventListener('click',()=>audio.play());
playPause.addEventListener('click',()=>{if(audio.paused) audio.play(); else audio.pause();});
audio.addEventListener('play',()=>{playBtn.parentElement.style.display='none'; playPause.innerHTML='<i class="fa-solid fa-pause text-xs"></i>'; startSlides();});
audio.addEventListener('pause',()=>{playPause.innerHTML='<i class="fa-solid fa-play text-xs"></i>'; stopSlides();});
audio.addEventListener('timeupdate',()=>{if(audio.duration) progress.style.width=(audio.currentTime/audio.duration*100)+'%';});
audio.addEventListener('ended',()=>{progress.style.width='0%'; playBtn.parentElement.style.display='flex'; idx=0; showSlide(0);});
function downloadAudio(){const a=document.createElement('a'); a.href=audio.src; a.download='video-narration-fa.mp3'; a.click();}
</script>
</body>
</html>

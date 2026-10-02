<?php
$brandName = htmlspecialchars($brandName ?? 'Connectix VPN');
$logoUrl = $logoUrl ?? '';
$appVersion = $manifest['version'] ?? '3.6.1';
$base = defined('Helpers::class') && method_exists('Helpers','basePath') ? Helpers::basePath() : '/contax';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#0f172a">
<title><?= $brandName ?> | راهنمای کامل نصب آیفون با ویدیو و تصویر - v<?= $appVersion ?></title>
<script src="<?= $base ?>/assets/js/tailwind.js"></script>
<link rel="stylesheet" href="<?= $base ?>/assets/css/fontawesome.min.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/vazirmatn.css">
<style>
* { font-family: 'Vazirmatn', sans-serif; }
.tab-active { background: linear-gradient(135deg, #7C3AED, #4F46E5); color: white; border-color: transparent; }
.glass { backdrop-filter: blur(20px); }
</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen selection:bg-purple-600 selection:text-white relative overflow-x-hidden">
<div class="fixed -top-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>
<div class="fixed top-1/2 -left-40 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>

<header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-4 py-3.5 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <?php if (!empty($logoUrl)): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-10 w-10 object-contain rounded-xl">
      <?php else: ?>
        <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center text-white shadow-lg shadow-purple-600/30"><i class="fa-solid fa-shield-halved text-lg"></i></div>
      <?php endif; ?>
      <div>
        <h1 class="font-extrabold text-white text-base md:text-lg leading-tight"><?= $brandName ?> - راهنمای آیفون</h1>
        <p class="text-[10px] text-slate-400">نسخه <?= $appVersion ?> • با ویدیو و تصویر قدم به قدم</p>
      </div>
    </div>
    <a href="<?= $base ?>/download" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition"><i class="fa-solid fa-arrow-right ml-1"></i> بازگشت به دانلود</a>
  </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6 md:py-10 space-y-8">

  <!-- Hero -->
  <div class="relative overflow-hidden bg-gradient-to-br from-indigo-950/40 via-slate-900/80 to-purple-950/30 border border-indigo-500/20 rounded-[2rem] p-6 md:p-10">
    <div class="grid md:grid-cols-2 gap-8 items-center">
      <div class="space-y-5">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-[11px] text-indigo-300">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>جدید در نسخه <?= $appVersion ?> - اپ اختصاصی iOS</span>
        </div>
        <h2 class="text-2xl md:text-4xl font-black leading-tight">نصب <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">Connectix VPN</span> روی آیفون<br>در کمتر از 2 دقیقه</h2>
        <p class="text-sm text-slate-300 leading-relaxed">4 روش نصب رسمی با تصویر و ویدیو - پیشنهادی: سیب‌اپ با شماره ایرانی. فیلتر خودکار بانکی، اتصال 3 مرحله‌ای، مصرف کم باتری.</p>
        <div class="flex flex-wrap gap-2">
          <span class="px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-[11px]"><i class="fa-brands fa-apple ml-1"></i> iOS 12 به بالا</span>
          <span class="px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-[11px]"><i class="fa-solid fa-shield-halved ml-1"></i> Network Extension</span>
          <span class="px-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-[11px]"><i class="fa-solid fa-bolt ml-1"></i> اتصال 30 ثانیه‌ای</span>
        </div>
        <div class="flex gap-3 pt-2">
          <a href="#video" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold rounded-2xl text-sm shadow-lg shadow-indigo-900/30 transition flex items-center gap-2"><i class="fa-solid fa-play"></i> مشاهده ویدیو آموزشی</a>
          <a href="#methods" class="px-6 py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl text-sm border border-slate-700 transition">روش‌های نصب</a>
        </div>
      </div>
      <div class="relative">
        <img src="<?= $base ?>/assets/images/ios-guide/hero-iphones.png" alt="iPhones" class="w-full rounded-3xl shadow-2xl shadow-indigo-900/20 border border-slate-800">
        <div class="absolute -bottom-4 -right-4 bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-xl flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i class="fa-solid fa-check"></i></div>
          <div>
            <div class="text-xs font-bold text-white">متصل شد!</div>
            <div class="text-[10px] text-slate-400">فیلتر بانکی فعال</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Video Section -->
  <div id="video" class="bg-slate-900/80 border border-slate-800 rounded-[2rem] p-6 md:p-8 space-y-6 shadow-xl">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-bold flex items-center gap-2"><i class="fa-solid fa-video text-indigo-400"></i> ویدیو آموزشی کامل (2 دقیقه)</h3>
      <span class="text-[10px] px-2 py-1 rounded-full bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">با صدای فارسی</span>
    </div>
    
    <div class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-2">
        <div class="relative aspect-video bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 group" id="videoContainer">
          <video id="guideVideo" class="w-full h-full object-cover hidden" controls poster="<?= $base ?>/assets/images/ios-guide/hero-iphones.png">
            <source src="<?= $base ?>/assets/images/ios-guide/guide.mp4" type="video/mp4">
            مرورگر شما از ویدیو پشتیبانی نمی‌کند.
          </video>
          <div id="slideshow" class="w-full h-full relative">
            <img id="slideImg" src="<?= $base ?>/assets/images/ios-guide/hero-iphones.png" class="w-full h-full object-cover" alt="Guide">
            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                <span id="slideCaption" class="text-xs text-white font-bold">معرفی - 4 روش نصب آیفون</span>
                <span class="mr-auto text-[10px] text-slate-300" id="slideCounter">1 / 7</span>
              </div>
              <div class="mt-2 w-full bg-white/20 rounded-full h-1"><div id="progressBar" class="h-1 bg-indigo-500 rounded-full transition-all" style="width:14%"></div></div>
            </div>
          </div>
          <div id="videoPlaceholder" class="absolute inset-0 bg-gradient-to-br from-indigo-900/90 to-purple-900/90 flex flex-col items-center justify-center gap-4 p-6 text-center cursor-pointer">
            <div class="w-20 h-20 rounded-full bg-white/10 backdrop-blur flex items-center justify-center text-3xl animate-pulse"><i class="fa-solid fa-play text-white ml-1"></i></div>
            <div>
              <h4 class="font-bold text-white">ویدیو نصب آیفون - 4 روش (اسلایدشو + صوت)</h4>
              <p class="text-xs text-indigo-200 mt-1">سیب‌اپ • اناردونی • TestFlight • IPA مستقیم</p>
              <p class="text-[10px] text-slate-300 mt-2">کلیک کنید تا اسلایدشو با صدای فارسی پخش شود - ویدیو واقعی به زودی اضافه می‌شود</p>
            </div>
            <div class="flex gap-2 mt-2">
              <span class="px-3 py-1 rounded-full bg-white/10 text-[10px] text-white">🎬 7 تصویر مرحله‌ای</span>
              <span class="px-3 py-1 rounded-full bg-white/10 text-[10px] text-white">🎧 صدای فارسی</span>
              <span class="px-3 py-1 rounded-full bg-white/10 text-[10px] text-white">⏱️ 2 دقیقه</span>
            </div>
          </div>
          <button id="playPauseBtn" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-black/50 backdrop-blur text-white flex items-center justify-center hidden"><i class="fa-solid fa-pause"></i></button>
        </div>
        <div class="mt-3 flex items-center gap-3">
          <audio id="guideAudio" controls class="flex-1 h-10 rounded-xl bg-slate-800">
            <source src="<?= $base ?>/assets/audio/ios-guide-fa.mp3" type="audio/mpeg">
          </audio>
          <span class="text-[11px] text-slate-400"><i class="fa-solid fa-headphones ml-1"></i> راهنمای صوتی فارسی</span>
        </div>
        <div class="mt-3 p-3 bg-slate-800/50 border border-slate-700 rounded-xl text-[11px] text-slate-300 leading-relaxed">
          <b class="text-white">📹 ویدیو واقعی:</b> در حال ضبط ویدیو واقعی از صفحه آیفون هستیم. فعلا اسلایدشو تصویری بالا با 7 تصویر مرحله‌ای + صدای فارسی، تمام مراحل را پوشش می‌دهد. لینک یوتیوب به زودی: <span class="text-indigo-400">youtube.com/@connectixvpn</span>
        </div>
      </div>
      <div class="space-y-3">
        <h4 class="font-bold text-sm">آنچه در ویدیو می‌بینید:</h4>
        <div class="space-y-2 text-xs">
          <div class="flex gap-2 p-3 bg-slate-950/60 border border-slate-800 rounded-xl"><span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-bold">1</span><span class="text-slate-300">00:00 - معرفی 4 روش نصب</span></div>
          <div class="flex gap-2 p-3 bg-slate-950/60 border border-slate-800 rounded-xl"><span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-bold">2</span><span class="text-slate-300">00:25 - نصب از سیب‌اپ (پیشنهادی)</span></div>
          <div class="flex gap-2 p-3 bg-slate-950/60 border border-slate-800 rounded-xl"><span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-[10px] font-bold">3</span><span class="text-slate-300">01:10 - تایید VPN و ورود</span></div>
          <div class="flex gap-2 p-3 bg-slate-950/60 border border-slate-800 rounded-xl"><span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">4</span><span class="text-slate-300">01:40 - اتصال و فیلتر بانکی</span></div>
        </div>
        <div class="p-3 bg-amber-950/20 border border-amber-800/30 rounded-xl text-[11px] text-amber-200 leading-relaxed"><i class="fa-solid fa-lightbulb ml-1"></i> ویدیو کامل به زودی با ضبط صفحه آیفون واقعی جایگزین می‌شود. فعلا از تصاویر مرحله‌ای و صوت فارسی استفاده کنید.</div>
      </div>
    </div>
  </div>

  <!-- Methods Tabs -->
  <div id="methods" class="space-y-4">
    <h3 class="text-xl font-black text-center">4 روش نصب - یکی را انتخاب کنید</h3>
    <div class="flex flex-wrap justify-center gap-2 p-1 bg-slate-900 border border-slate-800 rounded-2xl w-fit mx-auto">
      <button onclick="showTab('sibapp')" id="tab-sibapp" class="tab-btn tab-active px-5 py-2.5 rounded-xl text-sm font-bold border transition"><i class="fa-solid fa-apple-whole ml-1"></i> سیب‌اپ (پیشنهادی)</button>
      <button onclick="showTab('anardoni')" id="tab-anardoni" class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition">اناردونی</button>
      <button onclick="showTab('testflight')" id="tab-testflight" class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition">TestFlight</button>
      <button onclick="showTab('ipa')" id="tab-ipa" class="tab-btn px-5 py-2.5 rounded-xl text-sm font-bold border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 transition">IPA مستقیم</button>
    </div>

    <!-- SibApp Tab -->
    <div id="content-sibapp" class="tab-content space-y-6">
      <div class="bg-gradient-to-br from-indigo-950/30 via-slate-900/60 to-slate-900 border border-indigo-500/20 rounded-[2rem] p-6 md:p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-xl"><i class="fa-solid fa-apple-whole"></i></div>
          <div>
            <h4 class="font-black text-lg">روش 1: سیب‌اپ - پیشنهادی (با شماره ایرانی)</h4>
            <p class="text-xs text-slate-400">ساده‌ترین روش - بدون نیاز به اپل آیدی خارجی - فقط با شماره موبایل ایران</p>
          </div>
          <span class="mr-auto text-[10px] px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">پیشنهادی ⭐</span>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
          <div class="md:col-span-2 space-y-4">
            <div class="grid gap-4">
              <div class="flex gap-4 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">1</div>
                <div class="space-y-1">
                  <h5 class="font-bold text-sm">وارد سیب‌اپ شوید</h5>
                  <p class="text-xs text-slate-400 leading-relaxed">لینک را باز کنید: <a href="https://sibapp.com/applications/connectix-vpn" target="_blank" class="text-indigo-400 underline">sibapp.com/applications/connectix-vpn</a> - با شماره موبایل ایرانی ثبت‌نام کنید (کد تایید پیامک می‌شود)</p>
                </div>
              </div>
              <div class="flex gap-4 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">2</div>
                <div class="space-y-1">
                  <h5 class="font-bold text-sm">اپ سیب‌اپ را نصب کنید (اگر ندارید)</h5>
                  <p class="text-xs text-slate-400">دکمه دانلود سیب‌اپ را بزنید - پروفایل را در Settings → General → VPN & Device Management تایید کنید</p>
                </div>
              </div>
              <div class="flex gap-4 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">3</div>
                <div class="space-y-1">
                  <h5 class="font-bold text-sm">Connectix VPN را جستجو و نصب کنید</h5>
                  <p class="text-xs text-slate-400">در اپ سیب‌اپ بنویسید Connectix - دکمه دریافت را بزنید - منتظر نصب بمانید</p>
                </div>
              </div>
              <div class="flex gap-4 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0">4</div>
                <div class="space-y-1">
                  <h5 class="font-bold text-sm">اعتماد سازی</h5>
                  <p class="text-xs text-slate-400">Settings → General → VPN & Device Management → SibApp → Trust - سپس Connectix VPN را باز کنید</p>
                </div>
              </div>
            </div>
            <a href="https://sibapp.com/applications/connectix-vpn" target="_blank" class="block w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-black rounded-2xl text-center shadow-lg shadow-indigo-900/30 transition"><i class="fa-solid fa-download ml-2"></i> دانلود از سیب‌اپ - مستقیم</a>
          </div>
          <div class="space-y-4">
            <img src="<?= $base ?>/assets/images/ios-guide/step1-sibapp.png" alt="SibApp" class="w-full rounded-2xl border border-slate-800 shadow-xl">
            <img src="<?= $base ?>/assets/images/ios-guide/step2-vpn-permission.png" alt="VPN Permission" class="w-full rounded-2xl border border-slate-800 shadow-xl">
          </div>
        </div>
      </div>
    </div>

    <!-- Anardoni Tab -->
    <div id="content-anardoni" class="tab-content hidden space-y-6">
      <div class="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 md:p-8">
        <h4 class="font-black text-lg flex items-center gap-3"><div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center"><i class="fa-solid fa-apple-whole"></i></div> روش 2: اناردونی</h4>
        <p class="text-sm text-slate-400 mt-4">مشابه سیب‌اپ - با شماره ایرانی - <a href="https://anardoni.com/applications/connectix-vpn" class="text-indigo-400 underline">anardoni.com</a></p>
        <ol class="mt-4 space-y-2 text-sm list-decimal pr-5 text-slate-300 leading-relaxed">
          <li>وارد لینک اناردونی شوید</li>
          <li>ثبت‌نام با شماره ایرانی</li>
          <li>نصب اپ اناردونی و سپس Connectix VPN</li>
          <li>تایید پروفایل در تنظیمات</li>
        </ol>
      </div>
    </div>

    <!-- TestFlight Tab -->
    <div id="content-testflight" class="tab-content hidden space-y-6">
      <div class="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 md:p-8">
        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <h4 class="font-black text-lg flex items-center gap-3"><div class="w-12 h-12 rounded-2xl bg-amber-600/20 text-amber-400 flex items-center justify-center"><i class="fa-solid fa-flask"></i></div> روش 3: TestFlight (رایگان - 10000 نفر)</h4>
            <ol class="mt-4 space-y-3 text-sm text-slate-300">
              <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-amber-600 text-white flex items-center justify-center text-xs">1</span> اپ TestFlight را از App Store نصب کنید</li>
              <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-amber-600 text-white flex items-center justify-center text-xs">2</span> لینک دعوت را باز کنید: <a href="https://testflight.apple.com/join/connectix" class="text-indigo-400 underline">testflight.apple.com/join/connectix</a></li>
              <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-amber-600 text-white flex items-center justify-center text-xs">3</span> Accept → Install</li>
              <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-xs">!</span> هر 90 روز منقضی می‌شود و نیاز به آپدیت دارد</li>
            </ol>
            <a href="https://testflight.apple.com/join/connectix" target="_blank" class="mt-4 block w-full py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-2xl text-center">رفتن به TestFlight</a>
          </div>
          <img src="<?= $base ?>/assets/images/ios-guide/step7-testflight.png" alt="TestFlight" class="w-full rounded-2xl border border-slate-800">
        </div>
      </div>
    </div>

    <!-- IPA Tab -->
    <div id="content-ipa" class="tab-content hidden space-y-6">
      <div class="bg-slate-900 border border-slate-800 rounded-[2rem] p-6 md:p-8">
        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <h4 class="font-black text-lg flex items-center gap-3"><div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center"><i class="fa-solid fa-file-code"></i></div> روش 4: IPA مستقیم با AltStore (پیشرفته)</h4>
            <div class="mt-4 p-3 bg-amber-950/20 border border-amber-800/30 rounded-xl text-[11px] text-amber-200">⚠️ هر 7 روز نیاز به تمدید دارد مگر با اکانت دولوپر 99$ - برای کاربران حرفه‌ای</div>
            <ol class="mt-4 space-y-2 text-sm list-decimal pr-5 text-slate-300 leading-relaxed">
              <li>AltStore را از <a href="https://altstore.io" class="text-indigo-400 underline">altstore.io</a> روی کامپیوتر نصب کنید</li>
              <li>آیفون را با کابل به کامپیوتر وصل کنید و AltStore را روی آیفون نصب کنید</li>
              <li>فایل IPA را دانلود کنید: <a href="https://github.com/hojjatrad/panelconnectix/releases/download/v3.6.1/Connectix-iOS-3.6.1.ipa" class="text-indigo-400 underline">دانلود IPA v3.6.1</a></li>
              <li>در آیفون: AltStore → My Apps → + → فایل IPA را انتخاب کنید</li>
              <li>با Apple ID ساین کنید</li>
            </ol>
          </div>
          <img src="<?= $base ?>/assets/images/ios-guide/step6-altstore.png" alt="AltStore" class="w-full rounded-2xl border border-slate-800">
        </div>
      </div>
    </div>
  </div>

  <!-- After Install -->
  <div class="bg-gradient-to-br from-emerald-950/20 via-slate-900/60 to-slate-900 border border-emerald-800/30 rounded-[2rem] p-6 md:p-8">
    <h3 class="text-lg font-black flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-400"></i> بعد از نصب - 3 مرحله تا اتصال</h3>
    <div class="grid md:grid-cols-3 gap-4 mt-6">
      <div class="bg-slate-950/60 border border-slate-800 rounded-2xl p-5 space-y-3">
        <img src="<?= $base ?>/assets/images/ios-guide/step3-login.png" alt="Login" class="w-full rounded-xl border border-slate-800">
        <div class="w-8 h-8 rounded-full bg-purple-600 text-white flex items-center justify-center font-bold text-sm">1</div>
        <h4 class="font-bold text-sm">ورود با نام کاربری</h4>
        <p class="text-xs text-slate-400 leading-relaxed">برنامه را باز کنید - نام کاربری و رمز اشتراک دریافتی از ربات تلگرام را وارد کنید - دقیقا مثل اندروید</p>
      </div>
      <div class="bg-slate-950/60 border border-slate-800 rounded-2xl p-5 space-y-3">
        <img src="<?= $base ?>/assets/images/ios-guide/step2-vpn-permission.png" alt="Permission" class="w-full rounded-xl border border-slate-800">
        <div class="w-8 h-8 rounded-full bg-cyan-600 text-white flex items-center justify-center font-bold text-sm">2</div>
        <h4 class="font-bold text-sm">تایید VPN</h4>
        <p class="text-xs text-slate-400 leading-relaxed">دکمه اتصال را بزنید - پیغام Allow VPN می‌آید - Allow را بزنید - اثر انگشت یا رمز آیفون را وارد کنید</p>
      </div>
      <div class="bg-slate-950/60 border border-slate-800 rounded-2xl p-5 space-y-3">
        <img src="<?= $base ?>/assets/images/ios-guide/step4-connected.png" alt="Connected" class="w-full rounded-xl border border-slate-800">
        <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">3</div>
        <h4 class="font-bold text-sm">متصل شدید!</h4>
        <p class="text-xs text-slate-400 leading-relaxed">تیک سبز و تایمر می‌بینید - حالا فیلترشکن فعال است - فیلتر خودکار بانکی هم فعال است</p>
      </div>
    </div>

    <div class="mt-6 grid md:grid-cols-2 gap-4">
      <div class="flex gap-3 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl">
        <img src="<?= $base ?>/assets/images/ios-guide/step5-banking.png" alt="Banking" class="w-20 h-20 rounded-xl object-cover border border-slate-700">
        <div>
          <h5 class="font-bold text-sm text-emerald-300">فیلتر خودکار بانکی</h5>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">اپ‌های بانکی (ملی، ملت، سپه، ...) به صورت خودکار بدون VPN باز می‌شوند - بدون نیاز به قطع کردن - فقط 30 اپ نصب شده شناسایی می‌شود تا کرش نکند</p>
        </div>
      </div>
      <div class="p-4 bg-indigo-950/20 border border-indigo-800/30 rounded-2xl">
        <h5 class="font-bold text-sm text-indigo-300"><i class="fa-solid fa-bug ml-1"></i> مشکل اتصال؟</h5>
        <ul class="text-xs text-slate-300 mt-2 space-y-1 list-disc pr-4 leading-relaxed">
          <li>دکمه اتصال 3 بار تلاش می‌کند (safe → original → no bypass)</li>
          <li>اینترنت را چک کنید - وای‌فای یا 4G</li>
          <li>برنامه را ببندید و دوباره باز کنید</li>
          <li>به پشتیبانی تلگرام پیام دهید</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- FAQ -->
  <div class="bg-slate-900/60 border border-slate-800 rounded-[2rem] p-6 md:p-8">
    <h3 class="font-bold text-lg mb-4"><i class="fa-solid fa-circle-question text-indigo-400 ml-2"></i> سوالات متداول</h3>
    <div class="grid md:grid-cols-2 gap-3 text-xs">
      <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl"><b class="text-white">کدام روش بهتر است؟</b><p class="text-slate-400 mt-1">سیب‌اپ - چون با شماره ایرانی است، تمدید خودکار دارد و مثل اپ استور آپدیت می‌شود</p></div>
      <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl"><b class="text-white">آیا اپل آیدی خارجی لازم است؟</b><p class="text-slate-400 mt-1">خیر - سیب‌اپ و اناردونی فقط شماره ایرانی می‌خواهند - TestFlight و IPA نیاز به اپل آیدی دارند</p></div>
      <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl"><b class="text-white">مصرف باتری چقدر است؟</b><p class="text-slate-400 mt-1">بهینه شده - Network Extension کم مصرف - حدود 2-3% در ساعت</p></div>
      <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl"><b class="text-white">آیا بانکی‌ها کار می‌کنند؟</b><p class="text-slate-400 mt-1">بله - فیلتر خودکار 30 اپ بانکی نصب شده را بدون VPN باز می‌کند - بدون قطع کردن</p></div>
    </div>
  </div>

  <div class="text-center py-4">
    <p class="text-[11px] text-slate-500">Connectix VPN v<?= $appVersion ?> • iOS 12+ • iPhone & iPad • Network Extension • 2026</p>
  </div>
</main>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(el => { el.classList.remove('tab-active'); el.classList.add('bg-slate-800','text-slate-300','border-slate-700'); });
  document.getElementById('content-'+name).classList.remove('hidden');
  const btn = document.getElementById('tab-'+name);
  btn.classList.add('tab-active');
  btn.classList.remove('bg-slate-800','text-slate-300','border-slate-700');
}

// Slideshow logic
const slides = [
  { img: '<?= $base ?>/assets/images/ios-guide/hero-iphones.png', caption: 'معرفی - 4 روش نصب آیفون - Connectix VPN 3.6.1' },
  { img: '<?= $base ?>/assets/images/ios-guide/step1-sibapp.png', caption: 'مرحله 1: ورود به سیب‌اپ و جستجوی Connectix' },
  { img: '<?= $base ?>/assets/images/ios-guide/step2-vpn-permission.png', caption: 'مرحله 2: تایید اجازه VPN در تنظیمات iOS' },
  { img: '<?= $base ?>/assets/images/ios-guide/step3-login.png', caption: 'مرحله 3: ورود با نام کاربری و رمز اشتراک' },
  { img: '<?= $base ?>/assets/images/ios-guide/step4-connected.png', caption: 'مرحله 4: اتصال موفق - تیک سبز' },
  { img: '<?= $base ?>/assets/images/ios-guide/step5-banking.png', caption: 'ویژگی: فیلتر خودکار بانکی - بدون قطع VPN' },
  { img: '<?= $base ?>/assets/images/ios-guide/step6-altstore.png', caption: 'روش جایگزین: نصب IPA با AltStore' },
  { img: '<?= $base ?>/assets/images/ios-guide/step7-testflight.png', caption: 'روش جایگزین: TestFlight اپل' },
];
let currentSlide = 0;
let slideInterval = null;
let isPlaying = false;

function updateSlide() {
  const img = document.getElementById('slideImg');
  const cap = document.getElementById('slideCaption');
  const counter = document.getElementById('slideCounter');
  const bar = document.getElementById('progressBar');
  if (!img) return;
  img.src = slides[currentSlide].img;
  if (cap) cap.textContent = slides[currentSlide].caption;
  if (counter) counter.textContent = (currentSlide+1) + ' / ' + slides.length;
  if (bar) bar.style.width = ((currentSlide+1)/slides.length*100) + '%';
}

function nextSlide() {
  currentSlide = (currentSlide + 1) % slides.length;
  updateSlide();
}

function startSlideshow() {
  if (isPlaying) return;
  isPlaying = true;
  document.getElementById('videoPlaceholder')?.classList.add('hidden');
  document.getElementById('playPauseBtn')?.classList.remove('hidden');
  const audio = document.getElementById('guideAudio');
  if (audio) { audio.play().catch(()=>{}); }
  slideInterval = setInterval(nextSlide, 3500);
  const btn = document.getElementById('playPauseBtn');
  if (btn) btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
}

function pauseSlideshow() {
  isPlaying = false;
  clearInterval(slideInterval);
  const audio = document.getElementById('guideAudio');
  if (audio) audio.pause();
  const btn = document.getElementById('playPauseBtn');
  if (btn) btn.innerHTML = '<i class="fa-solid fa-play"></i>';
}

document.getElementById('videoPlaceholder')?.addEventListener('click', startSlideshow);
document.getElementById('playPauseBtn')?.addEventListener('click', () => {
  if (isPlaying) pauseSlideshow(); else startSlideshow();
});
document.getElementById('slideImg')?.addEventListener('click', () => {
  if (isPlaying) pauseSlideshow(); else startSlideshow();
});

// Auto try video
const video = document.getElementById('guideVideo');
if (video) {
  video.addEventListener('error', () => {
    video.classList.add('hidden');
    document.getElementById('slideshow')?.classList.remove('hidden');
  });
  // Check if mp4 exists
  fetch(video.querySelector('source')?.src, {method:'HEAD'}).then(r => {
    if (r.ok) {
      video.classList.remove('hidden');
      document.getElementById('slideshow')?.classList.add('hidden');
      document.getElementById('videoPlaceholder')?.classList.remove('hidden');
      document.getElementById('videoPlaceholder').onclick = () => {
        document.getElementById('videoPlaceholder').style.display='none';
        video.play();
      };
    }
  }).catch(()=>{});
}

document.getElementById('guideVideo')?.addEventListener('play', () => {
  document.getElementById('videoPlaceholder')?.style.setProperty('display','none');
});
</script>
</body>
</html>

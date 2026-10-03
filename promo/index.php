<?php
// Connectix Generic Product Sales via Telegram Bot - v6.8.15 - بدون VPN
$brand = 'Connectix';
$tg = '@mainAdminpanel';
$tg_url = 'https://t.me/mainAdminpanel';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
// Panel URL - always at root /login regardless of promo location
$panel_login_url = $proto . $host . '/login';
$panel_dashboard_url = $proto . $host . '/dashboard';
$promo_url = $proto . $host . $basePath . '/';
$domain = $proto . $host;
$demo_user = 'demo';
$demo_pass = 'demo123';
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
                $token = Setting::get('telegram_bot_token',''); $admin = Setting::get('telegram_admin_chat_id','');
                $text = "🔥 <b>درخواست جدید پنل فروش</b>\n\n👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tgid\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n🌐 {$promo_url}\n🕐 ".date('Y-m-d H:i:s');
                if ($admin && $token) @TelegramBot::sendMessage($text,$admin,null,$token);
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
<title>پنل فروش محصولات با ربات تلگرام | تحویل آنی، درگاه پرداخت، مدیریت سفارشات | Connectix v6.8.15</title>
<meta name="description" content="پنل فروش محصولات با ربات تلگرام فروش خودکار، تحویل آنی، درگاه پرداخت، کیف پول، مدیریت سفارشات، پنل فارسی v6.8.15">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="<?= htmlspecialchars($basePath) ?>/assets/js/tailwind.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/fontawesome.min.css">
<style>
*{font-family:Vazirmatn,system-ui!important}
html{scroll-behavior:smooth}
body{background:#020208;color:#fff;overflow-x:hidden}
.glass{backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
.gradient-text{background:linear-gradient(90deg,#8B5CF6,#06B6D4,#10B981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.gradient-bg{background:linear-gradient(135deg,#7C3AED 0%,#4F46E5 40%,#06B6D4 100%)}
.gradient-border{position:relative;background:linear-gradient(#0a0a14,#0a0a14) padding-box,linear-gradient(135deg,#7C3AED,#06B6D4) border-box;border:1px solid transparent}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
.float{animation:float 6s ease-in-out infinite}
@keyframes pulseGlow{0%,100%{box-shadow:0 0 30px rgba(139,92,246,.4)}50%{box-shadow:0 0 60px rgba(139,92,246,.7),0 0 100px rgba(6,182,214,.3)}}
.glow{animation:pulseGlow 3s ease-in-out infinite}
.card-hover{transition:all .4s cubic-bezier(.23,1,.32,1)}
.card-hover:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 20px 60px rgba(124,58,237,.25),0 0 0 1px rgba(139,92,246,.2)}
</style>
</head>
<body class="antialiased selection:bg-violet-500/30">

<!-- Background -->
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
  <div class="absolute -top-1/4 -right-1/4 w-[900px] h-[900px] bg-violet-600/20 rounded-full blur-[180px]"></div>
  <div class="absolute top-1/3 -left-1/4 w-[700px] h-[700px] bg-cyan-500/15 rounded-full blur-[150px]"></div>
  <div class="absolute -bottom-1/4 right-1/3 w-[800px] h-[800px] bg-indigo-600/15 rounded-full blur-[160px]"></div>
  <div class="absolute inset-0 opacity-[0.02]" style="background-image:linear-gradient(rgba(255,255,255,.1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.1) 1px,transparent 1px);background-size:80px 80px"></div>
</div>

<!-- Navbar -->
<nav class="fixed top-0 w-full z-50 bg-[#020208]/80 glass border-b border-white/[0.06]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-[72px]">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 gradient-bg rounded-[14px] flex items-center justify-center font-black text-white shadow-[0_0_30px_rgba(124,58,237,.5)] glow">C</div>
        <div>
          <div class="font-black text-[18px] leading-none flex items-center gap-2">Connectix <span class="text-[9px] bg-gradient-to-r from-violet-500 to-cyan-500 rounded-full px-2.5 py-1 font-black tracking-wider">v6.8.15 BOT</span></div>
          <div class="text-[10px] text-white/40 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span> ربات فروش محصولات • @mainAdminpanel</div>
        </div>
      </div>
      <div class="hidden lg:flex items-center gap-1 bg-white/[0.03] border border-white/[0.06] rounded-full p-1">
        <a href="#features" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">امکانات</a>
        <a href="#bot" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">ربات تلگرام</a>
        <a href="#pricing" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">تعرفه</a>
        <a href="#demo" class="px-4 py-2 rounded-full text-[12px] bg-white text-black font-bold">دمو آنلاین</a>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="hidden sm:flex px-5 py-2.5 rounded-full bg-white/[0.06] border border-white/[0.08] text-[12px] font-bold hover:bg-white/[0.1] transition">ورود به پنل</a>
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="px-5 py-2.5 rounded-full gradient-bg text-[12px] font-black shadow-[0_0_20px_rgba(124,58,237,.4)] hover:shadow-[0_0_30px_rgba(124,58,237,.6)] transition">خرید پنل</a>
      </div>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="relative pt-32 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="grid lg:grid-cols-2 gap-12 items-center">
    <div class="space-y-7">
      <div class="inline-flex items-center gap-2 bg-violet-500/10 border border-violet-500/20 rounded-full px-4 py-1.5 text-[11px]">
        <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
        <span class="text-violet-300 font-bold">نسخه 6.8.15 - پنل فروش محصولات با ربات تلگرام - تحویل آنی</span>
      </div>
      <h1 class="text-[32px] sm:text-[44px] lg:text-[52px] font-black leading-[1.1] tracking-tight">
        فروش محصولات با<br>
        <span class="gradient-text">ربات تلگرام</span><br>
        خودکار و <span class="text-white">آنی</span>
      </h1>
      <p class="text-[14px] leading-7 text-white/50 max-w-[560px]">
        کامل‌ترین پنل مدیریت فروش محصولات با ربات تلگرام هوشمند: ثبت سفارش خودکار، پرداخت آنلاین، کیف پول، مدیریت موجودی، ارسال پیام، گزارش مالی و پنل فارسی قدرتمند. مناسب هر نوع محصول دیجیتال و فیزیکی.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="px-8 py-4 rounded-full gradient-bg font-black text-[13px] shadow-[0_0_40px_rgba(124,58,237,.5)] hover:shadow-[0_0_60px_rgba(124,58,237,.7)] transition flex items-center gap-2">
          <i class="fa-brands fa-telegram"></i> خرید آنی از تلگرام
        </a>
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="px-8 py-4 rounded-full bg-white text-black font-black text-[13px] hover:bg-white/90 transition flex items-center gap-2">
          <i class="fa-solid fa-right-to-bracket"></i> ورود به پنل
        </a>
      </div>
      <div class="grid grid-cols-3 gap-4 pt-6 max-w-[480px]">
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black gradient-text">+1.8k</div><div class="text-[10px] text-white/40 mt-1">فروشگاه فعال</div>
        </div>
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black text-white">99.9%</div><div class="text-[10px] text-white/40 mt-1">آپتایم</div>
        </div>
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black text-emerald-400">آنی</div><div class="text-[10px] text-white/40 mt-1">تحویل محصول</div>
        </div>
      </div>
    </div>

    <!-- Mockup -->
    <div class="relative lg:h-[560px] flex items-center justify-center">
      <div class="relative w-full max-w-[420px]">
        <div class="absolute -inset-6 gradient-bg opacity-20 blur-[50px] rounded-[40px]"></div>
        <div class="relative gradient-border rounded-[32px] p-[1px] float">
          <div class="bg-[#0a0a14] rounded-[31px] p-6 space-y-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-white/[0.06] flex items-center justify-center"><i class="fa-solid fa-bag-shopping text-violet-400 text-[12px]"></i></div><span class="text-[12px] font-bold">سفارشات امروز</span></div>
              <span class="text-[10px] bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 rounded-full px-2.5 py-1">3 سفارش جدید</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div class="text-[11px] text-white/40">فروش امروز</div><div class="text-[18px] font-black mt-1">2,850,000 <span class="text-[10px] text-white/40">تومان</span></div><div class="text-[10px] text-emerald-400 mt-1">↑ 18% نسبت به دیروز</div></div>
              <div class="bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div class="text-[11px] text-white/40">محصولات فعال</div><div class="text-[18px] font-black mt-1">24</div><div class="text-[10px] text-violet-400 mt-1">● 5 ناموجود</div></div>
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.05] rounded-xl p-3"><div class="flex items-center gap-2.5"><div class="w-7 h-7 rounded-full bg-violet-500/20 flex items-center justify-center"><i class="fa-solid fa-box text-[10px] text-violet-300"></i></div><div><div class="text-[11px] font-bold">پکیج آموزش فتوشاپ</div><div class="text-[9px] text-white/30">سفارش #1842 • 290,000 تومان</div></div></div><span class="text-[10px] bg-emerald-500 text-black font-black rounded-full px-2 py-1">پرداخت شد</span></div>
              <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.05] rounded-xl p-3"><div class="flex items-center gap-2.5"><div class="w-7 h-7 rounded-full bg-cyan-500/20 flex items-center justify-center"><i class="fa-solid fa-crown text-[10px] text-cyan-300"></i></div><div><div class="text-[11px] font-bold">اشتراک ویژه 3 ماهه</div><div class="text-[9px] text-white/30">سفارش #1841 • 450,000 تومان</div></div></div><span class="text-[10px] bg-amber-500/20 text-amber-400 border border-amber-500/20 rounded-full px-2 py-1">در انتظار</span></div>
              <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.05] rounded-xl p-3"><div class="flex items-center gap-2.5"><div class="w-7 h-7 rounded-full bg-emerald-500/20 flex items-center justify-center"><i class="fa-solid fa-gift text-[10px] text-emerald-300"></i></div><div><div class="text-[11px] font-bold">کد تخفیف 20%</div><div class="text-[9px] text-white/30">سفارش #1840 • هدیه</div></div></div><span class="text-[10px] bg-violet-500/20 text-violet-300 border border-violet-500/20 rounded-full px-2 py-1">ارسال شد</span></div>
            </div>
            <div class="flex gap-2">
              <div class="flex-1 py-2.5 rounded-full bg-white text-black text-[11px] font-black text-center">+ افزودن محصول</div>
              <div class="w-10 h-10 rounded-full bg-white/[0.06] border border-white/[0.08] flex items-center justify-center"><i class="fa-solid fa-ellipsis text-[12px]"></i></div>
            </div>
          </div>
        </div>
        <div class="absolute -top-4 -right-6 bg-[#0a0a14] border border-white/[0.08] rounded-2xl px-4 py-2.5 shadow-2xl flex items-center gap-2 float" style="animation-delay:1s"><div class="w-8 h-8 rounded-full bg-violet-500/15 flex items-center justify-center"><i class="fa-brands fa-telegram text-violet-400 text-[14px]"></i></div><div><div class="text-[11px] font-bold">ربات تلگرام</div><div class="text-[9px] text-white/40">فروش خودکار</div></div></div>
        <div class="absolute -bottom-6 -left-6 bg-[#0a0a14] border border-white/[0.08] rounded-2xl px-4 py-2.5 shadow-2xl flex items-center gap-2 float" style="animation-delay:2s"><div class="w-8 h-8 rounded-full bg-emerald-500/15 flex items-center justify-center"><i class="fa-solid fa-bolt text-emerald-400 text-[14px]"></i></div><div><div class="text-[11px] font-bold">تحویل آنی</div><div class="text-[9px] text-white/40">2 ثانیه</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section id="features" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="text-center max-w-2xl mx-auto mb-14">
    <div class="inline-flex items-center gap-2 bg-white/[0.04] border border-white/[0.06] rounded-full px-4 py-1.5 text-[11px] text-white/50 mb-4"><i class="fa-solid fa-sparkles text-violet-400"></i> امکانات پنل فروش با ربات</div>
    <h2 class="text-[28px] sm:text-[36px] font-black leading-tight">همه چیز برای <span class="gradient-text">فروش خودکار</span> آماده است</h2>
    <p class="text-[13px] text-white/40 mt-3 leading-6">بدون کدنویسی، فقط با یک ربات تلگرام و یک دامنه</p>
  </div>
  <div class="grid md:grid-cols-3 gap-5">
    <?php
    $features = [
      ['icon'=>'fa-robot','color'=>'violet','title'=>'ربات تلگرام فروش خودکار','desc'=>'ثبت سفارش، پرداخت، تحویل محصول، پشتیبانی، کیف پول، کد تخفیف، دعوت دوستان - همه خودکار در تلگرام'],
      ['icon'=>'fa-boxes-stacked','color'=>'cyan','title'=>'مدیریت محصولات نامحدود','desc'=>'محصول دیجیتال، فیزیکی، اشتراکی، فایل، لایسنس، کد - با دسته‌بندی، موجودی، قیمت‌گذاری هوشمند'],
      ['icon'=>'fa-credit-card','color'=>'emerald','title'=>'درگاه پرداخت و کیف پول','desc'=>'زرین‌پال، آیدی‌پی، کارت به کارت، کریپتو، کیف پول ریالی، پورسانت معرف، گزارش مالی کامل'],
      ['icon'=>'fa-bolt','color'=>'amber','title'=>'تحویل آنی محصولات','desc'=>'بعد پرداخت، محصول در 2 ثانیه تحویل داده میشه - فایل، کد، لینک، پیام اختصاصی'],
      ['icon'=>'fa-users','color'=>'blue','title'=>'مدیریت مشتریان و سفارشات','desc'=>'لیست مشتریان، سفارشات، جستجو، فیلتر، خروجی اکسل، ارسال پیام گروهی، یادآوری'],
      ['icon'=>'fa-chart-line','color'=>'rose','title'=>'گزارش و آمار دقیق','desc'=>'نمودار فروش، محصولات پرفروش، مشتریان وفادار، درآمد روزانه/ماهانه، همه با نمودار فارسی'],
    ];
    foreach ($features as $f):
    ?>
    <div class="group bg-white/[0.03] border border-white/[0.06] rounded-[24px] p-6 card-hover">
      <div class="w-12 h-12 rounded-[14px] bg-<?= $f['color'] ?>-500/15 border border-<?= $f['color'] ?>-500/20 flex items-center justify-center mb-4 group-hover:scale-110 transition"><i class="fa-solid <?= $f['icon'] ?> text-<?= $f['color'] ?>-400"></i></div>
      <h3 class="font-black text-[14px]"><?= $f['title'] ?></h3>
      <p class="text-[12px] leading-6 text-white/40 mt-2"><?= $f['desc'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Bot Section -->
<section id="bot" class="py-20 relative">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="gradient-border rounded-[32px] p-[1px]">
      <div class="bg-[#08080f] rounded-[31px] p-8 sm:p-12">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
          <div class="space-y-6">
            <div class="inline-flex items-center gap-2 bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300"><span class="w-1.5 h-1.5 bg-violet-400 rounded-full animate-pulse"></span> ربات تلگرام هوشمند</div>
            <h2 class="text-[26px] sm:text-[34px] font-black leading-tight">مشتری در تلگرام<br><span class="gradient-text">سفارش میده</span>،<br>ربات خودکار تحویل میده</h2>
            <ul class="space-y-3 text-[13px]">
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-violet-500/15 border border-violet-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-violet-400 text-[10px]"></i></span> نمایش محصولات با عکس و قیمت در تلگرام</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-cyan-500/15 border border-cyan-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-cyan-400 text-[10px]"></i></span> پرداخت آنلاین و کیف پول داخل ربات</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-[10px]"></i></span> تحویل آنی فایل، کد، لینک بعد پرداخت</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-amber-500/15 border border-amber-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-amber-400 text-[10px]"></i></span> پشتیبانی، پیگیری سفارش، تاریخچه خرید</li>
            </ul>
            <div class="flex gap-3">
              <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="px-6 py-3 rounded-full gradient-bg font-black text-[12px] flex items-center gap-2"><i class="fa-brands fa-telegram"></i> تست ربات</a>
              <a href="<?= htmlspecialchars($panel_login_url) ?>" class="px-6 py-3 rounded-full bg-white/[0.06] border border-white/[0.1] font-bold text-[12px] flex items-center gap-2"><i class="fa-solid fa-gear"></i> تنظیمات ربات</a>
            </div>
          </div>
          <div class="relative flex justify-center">
            <div class="relative w-[300px] bg-[#0f0f1a] rounded-[32px] border border-white/[0.08] shadow-[0_0_80px_rgba(124,58,237,.25)] p-4 space-y-3">
              <div class="flex items-center gap-3 bg-[#050508] rounded-2xl p-3"><img src="https://i.pravatar.cc/40?img=12" class="w-10 h-10 rounded-full"><div><div class="text-[12px] font-bold">فروشگاه شما</div><div class="text-[10px] text-emerald-400">● آنلاین - پاسخگو</div></div></div>
              <div class="bg-violet-500 text-white rounded-2xl rounded-br-sm p-3 text-[11px] ml-8">سلام! به فروشگاه خوش اومدی 🛍️<br>چه محصولی میخوای؟</div>
              <div class="grid grid-cols-2 gap-2"><div class="bg-white/[0.06] border border-white/[0.08] rounded-xl p-2.5 text-center"><div class="text-[20px]">📦</div><div class="text-[10px] font-bold mt-1">پکیج آموزش</div><div class="text-[9px] text-emerald-400">290,000 تومان</div></div><div class="bg-white/[0.06] border border-white/[0.08] rounded-xl p-2.5 text-center"><div class="text-[20px]">🎓</div><div class="text-[10px] font-bold mt-1">اشتراک ویژه</div><div class="text-[9px] text-emerald-400">450,000 تومان</div></div></div>
              <div class="bg-white text-black rounded-2xl rounded-bl-sm p-3 text-[11px] mr-8">پکیج آموزش فتوشاپ</div>
              <div class="bg-violet-500 text-white rounded-2xl rounded-br-sm p-3 text-[11px] ml-8">عالیه! برای پرداخت روی دکمه زیر بزن 👇<br><br><span class="bg-white text-black rounded-full px-3 py-1 text-[10px] font-black">💳 پرداخت 290,000 تومان</span></div>
              <div class="bg-emerald-500/15 border border-emerald-500/20 rounded-xl p-2.5 text-center text-[10px] text-emerald-300">✅ پرداخت موفق - فایل ارسال شد!</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Demo -->
<section id="demo" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="bg-white/[0.03] border border-white/[0.06] rounded-[32px] p-8 sm:p-10">
    <div class="grid md:grid-cols-3 gap-8 items-center">
      <div class="md:col-span-2">
        <h3 class="text-[22px] font-black">دمو آنلاین پنل مدیریت</h3>
        <p class="text-[13px] text-white/40 mt-2 leading-6">بدون ثبت‌نام، همین الان وارد پنل دمو شو و همه امکانات فروش با ربات رو تست کن.</p>
        <div class="flex flex-wrap gap-3 mt-5">
          <div class="bg-[#0a0a14] border border-white/[0.08] rounded-full px-4 py-2 text-[12px] font-mono">یوزر: <span class="text-violet-300 font-bold"><?= htmlspecialchars($demo_user) ?></span></div>
          <div class="bg-[#0a0a14] border border-white/[0.08] rounded-full px-4 py-2 text-[12px] font-mono">پسورد: <span class="text-cyan-300 font-bold"><?= htmlspecialchars($demo_pass) ?></span></div>
        </div>
      </div>
      <div class="flex flex-col gap-3">
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="w-full py-4 rounded-full gradient-bg font-black text-[13px] text-center shadow-[0_0_30px_rgba(124,58,237,.4)]">ورود به دمو آنلاین</a>
        <div class="text-[10px] text-center text-white/30">نسخه 6.8.15 • بدون VPN</div>
      </div>
    </div>
  </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="grid lg:grid-cols-2 gap-8">
    <div class="gradient-border rounded-[32px] p-[1px]">
      <div class="bg-[#08080f] rounded-[31px] p-8">
        <h3 class="text-[20px] font-black">پلن‌های پنل فروش</h3>
        <div class="mt-6 space-y-3">
          <div class="flex items-center justify-between bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div><div class="font-bold text-[13px]">استارتر</div><div class="text-[11px] text-white/40">1 ربات - 50 محصول - پشتیبانی</div></div><div class="text-left"><div class="font-black text-[16px]">1.2M</div><div class="text-[10px] text-white/30">تومان</div></div></div>
          <div class="flex items-center justify-between bg-violet-500/10 border border-violet-500/30 rounded-2xl p-4 relative overflow-hidden"><div class="absolute top-0 right-0 bg-violet-500 text-black text-[9px] font-black px-3 py-1 rounded-bl-xl">محبوب</div><div><div class="font-black text-[13px] text-violet-200">حرفه‌ای + ربات اختصاصی</div><div class="text-[11px] text-violet-300/60">نامحدود محصول - درگاه اختصاصی - گزارش پیشرفته</div></div><div class="text-left"><div class="font-black text-[18px] text-white">2.9M</div><div class="text-[10px] text-violet-300">تومان</div></div></div>
          <div class="flex items-center justify-between bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div><div class="font-bold text-[13px]">بیزینس</div><div class="text-[11px] text-white/40">سورس کامل + لایسنس مادام‌العمر</div></div><div class="text-left"><div class="font-black text-[16px]">5.5M</div><div class="text-[10px] text-white/30">تومان</div></div></div>
        </div>
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="mt-6 w-full py-4 rounded-full gradient-bg font-black text-[13px] text-center block">مشاوره و خرید در تلگرام</a>
      </div>
    </div>

    <div class="bg-white/[0.03] border border-white/[0.06] rounded-[32px] p-8">
      <h3 class="text-[18px] font-black">درخواست مشاوره</h3>
      <p class="text-[12px] text-white/40 mt-2">فرم رو پر کن، کمتر از 2 ساعت باهات تماس میگیریم</p>
      <?php if ($success): ?><div class="mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-[12px]">✅ درخواست شما ثبت شد</div>
      <?php elseif ($error): ?><div class="mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-[12px]"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" class="mt-6 space-y-3">
        <input type="hidden" name="reseller_request" value="1">
        <div class="grid grid-cols-2 gap-3">
          <input name="name" required placeholder="نام شما *" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
          <input name="phone" placeholder="موبایل" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <input name="telegram_id" placeholder="آیدی تلگرام" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
          <select name="plan" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none"><option value="">انتخاب پلن</option><option>استارتر 1.2M</option><option>حرفه‌ای 2.9M</option><option>بیزینس 5.5M</option></select>
        </div>
        <input name="business" placeholder="نوع محصولات شما" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
        <textarea name="message" rows="3" placeholder="پیام شما..." class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none"></textarea>
        <button type="submit" class="w-full py-4 rounded-full bg-white text-black font-black text-[13px] hover:bg-white/90 transition">ارسال درخواست</button>
        <div class="text-[10px] text-center text-white/20">پاسخگویی: <?= htmlspecialchars($tg) ?> • <?= htmlspecialchars($domain) ?></div>
      </form>
    </div>
  </div>
</section>

<footer class="border-t border-white/[0.06] py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-white/30">
    <div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full gradient-bg flex items-center justify-center font-black text-white text-[12px]">C</div><span>Connectix v6.8.15 • پنل فروش محصولات با ربات تلگرام • @mainAdminpanel</span></div>
    <div class="flex items-center gap-4"><a href="<?= htmlspecialchars($panel_login_url) ?>" class="hover:text-white transition">ورود به پنل</a><a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="hover:text-white transition">پشتیبانی تلگرام</a><span>© 2026 Connectix</span></div>
  </div>
</footer>

</body>
</html>

<?php
// Connectix ULTRA v7.1 - Promo Landing with dynamic pricing, 3D, testimonials, FAQ
$brand = 'Connectix ULTRA';
$tg = '@mainAdminpanel';
$tg_url = 'https://t.me/mainAdminpanel';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
$panel_login_url = $proto . $host . '/login';
$panel_dashboard_url = $proto . $host . '/dashboard';
$promo_url = $proto . $host . $basePath . '/';
$domain = $proto . $host;
$demo_user = 'admin';
$demo_pass = 'admin123';
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
                $text = "🔥 <b>درخواست جدید پنل ULTRA v7.1</b>\n\n👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tgid\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n🌐 {$promo_url}\n🕐 ".date('Y-m-d H:i:s');
                if ($admin && $token) @TelegramBot::sendMessage($admin, $text, $token);
            }
        } catch (Throwable $e) {}
        $success = true;
    }
}
// Dynamic pricing from DB if available
$dynamicPlans = [];
try {
    $root = dirname(__DIR__);
    if (file_exists($root.'/config.php')) {
        require_once $root.'/config.php';
        require_once $root.'/core/Database.php';
        $pdo = Database::getConnection();
        $dynamicPlans = $pdo->query("SELECT title, base_price, traffic_gb, duration_days, category FROM plans WHERE is_active=1 AND show_in_bot=1 ORDER BY base_price ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { $dynamicPlans = []; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connectix ULTRA v7.1 | پنل فروش VPN فوق حرفه‌ای - مانیتورینگ زنده، دامنه چرخشی، گزارش مالی، PWA</title>
<meta name="description" content="پنل فروش VPN فوق حرفه‌ای ULTRA v7.1: مانیتورینگ زنده سرورها، دامنه چرخشی ضد فیلتر، صف ضد 520، گزارش مالی پیشرفته، PWA، سیستم امتیاز نمایندگان، تست سرعت، QR هوشمند">
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
@keyframes rotate3d{0%{transform:rotateY(0deg)}100%{transform:rotateY(360deg)}}
.globe{animation:rotate3d 20s linear infinite; transform-style:preserve-3d}
</style>
</head>
<body class="antialiased selection:bg-violet-500/30">

<!-- Background ULTRA -->
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
  <div class="absolute -top-1/4 -right-1/4 w-[900px] h-[900px] bg-violet-600/20 rounded-full blur-[180px]"></div>
  <div class="absolute top-1/3 -left-1/4 w-[700px] h-[700px] bg-cyan-500/15 rounded-full blur-[150px]"></div>
  <div class="absolute -bottom-1/4 right-1/3 w-[800px] h-[800px] bg-indigo-600/15 rounded-full blur-[160px]"></div>
  <div class="absolute inset-0 opacity-[0.02]" style="background-image:linear-gradient(rgba(255,255,255,.1) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.1) 1px,transparent 1px);background-size:80px 80px"></div>
</div>

<!-- Navbar ULTRA -->
<nav class="fixed top-0 w-full z-50 bg-[#020208]/80 glass border-b border-white/[0.06]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-[72px]">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 gradient-bg rounded-[14px] flex items-center justify-center font-black text-white shadow-[0_0_30px_rgba(124,58,237,.5)] glow">C</div>
        <div>
          <div class="font-black text-[18px] leading-none flex items-center gap-2">Connectix <span class="text-[9px] bg-gradient-to-r from-violet-500 to-cyan-500 rounded-full px-2.5 py-1 font-black tracking-wider">ULTRA v7.1</span></div>
          <div class="text-[10px] text-white/40 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span> مانیتورینگ زنده • دامنه چرخشی • گزارش مالی</div>
        </div>
      </div>
      <div class="hidden lg:flex items-center gap-1 bg-white/[0.03] border border-white/[0.06] rounded-full p-1">
        <a href="#features" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">امکانات ULTRA</a>
        <a href="#monitoring" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">مانیتورینگ</a>
        <a href="#pricing" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">تعرفه</a>
        <a href="#faq" class="px-4 py-2 rounded-full text-[12px] text-white/50 hover:text-white hover:bg-white/[0.06] transition">سوالات</a>
        <a href="#demo" class="px-4 py-2 rounded-full text-[12px] bg-white text-black font-bold">دمو ULTRA</a>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="hidden sm:flex px-5 py-2.5 rounded-full bg-white/[0.06] border border-white/[0.08] text-[12px] font-bold hover:bg-white/[0.1] transition">ورود به پنل</a>
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="px-5 py-2.5 rounded-full gradient-bg text-[12px] font-black shadow-[0_0_20px_rgba(124,58,237,.4)] hover:shadow-[0_0_30px_rgba(124,58,237,.6)] transition">خرید ULTRA</a>
      </div>
    </div>
  </div>
</nav>

<!-- Hero ULTRA -->
<section class="relative pt-32 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="grid lg:grid-cols-2 gap-12 items-center">
    <div class="space-y-7">
      <div class="inline-flex items-center gap-2 bg-violet-500/10 border border-violet-500/20 rounded-full px-4 py-1.5 text-[11px]">
        <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
        <span class="text-violet-300 font-bold">ULTRA v7.1 • مانیتورینگ زنده • دامنه چرخشی • گزارش مالی • PWA • امتیاز نمایندگان</span>
      </div>
      <h1 class="text-[32px] sm:text-[44px] lg:text-[52px] font-black leading-[1.1] tracking-tight">
        پنل فروش VPN<br>
        <span class="gradient-text">فوق حرفه‌ای</span><br>
        <span class="text-white">ULTRA v7.1</span>
      </h1>
      <p class="text-[14px] leading-7 text-white/50 max-w-[560px]">
        کامل‌ترین پنل فروش VPN با مانیتورینگ زنده سرورها، دامنه چرخشی ضد فیلتر، صف ضد 520، گزارش مالی پیشرفته، PWA قابل نصب، سیستم امتیاز نمایندگان، تست سرعت، QR هوشمند و اپلیکیشن اختصاصی. همه چیز برای فروش میلیونی آماده است.
      </p>
      <div class="flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="px-8 py-4 rounded-full gradient-bg font-black text-[13px] shadow-[0_0_40px_rgba(124,58,237,.5)] hover:shadow-[0_0_60px_rgba(124,58,237,.7)] transition flex items-center gap-2">
          <i class="fa-brands fa-telegram"></i> خرید آنی ULTRA
        </a>
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="px-8 py-4 rounded-full bg-white text-black font-black text-[13px] hover:bg-white/90 transition flex items-center gap-2">
          <i class="fa-solid fa-bolt"></i> دمو ULTRA
        </a>
      </div>
      <div class="grid grid-cols-3 gap-4 pt-6 max-w-[480px]">
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black gradient-text">99.9%</div><div class="text-[10px] text-white/40 mt-1">آپتایم</div>
        </div>
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black text-emerald-400">LIVE</div><div class="text-[10px] text-white/40 mt-1">مانیتورینگ</div>
        </div>
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center">
          <div class="text-[22px] font-black text-cyan-400">ULTRA</div><div class="text-[10px] text-white/40 mt-1">نسخه 7.1</div>
        </div>
      </div>
    </div>

    <!-- 3D Mockup ULTRA -->
    <div class="relative lg:h-[600px] flex items-center justify-center">
      <div class="relative w-full max-w-[420px]">
        <div class="absolute -inset-6 gradient-bg opacity-20 blur-[50px] rounded-[40px]"></div>
        <div class="relative gradient-border rounded-[32px] p-[1px] float">
          <div class="bg-[#0a0a14] rounded-[31px] p-6 space-y-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center"><i class="fa-solid fa-heart-pulse text-emerald-400 text-[12px] animate-pulse"></i></div><span class="text-[12px] font-bold">مانیتورینگ زنده</span></div>
              <span class="text-[10px] bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 rounded-full px-2.5 py-1">8 سرور آنلاین</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div class="text-[11px] text-white/40">فروش امروز</div><div class="text-[18px] font-black mt-1">4,250,000 <span class="text-[10px] text-white/40">تومان</span></div><div class="text-[10px] text-emerald-400 mt-1">↑ 32% نسبت به دیروز</div></div>
              <div class="bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div class="text-[11px] text-white/40">سود خالص</div><div class="text-[18px] font-black mt-1 text-violet-300">1,912,500</div><div class="text-[10px] text-violet-400 mt-1">45% مارجین</div></div>
            </div>
            <div class="space-y-2.5">
              <div class="flex items-center justify-between bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3"><div class="flex items-center gap-2.5"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span><div><div class="text-[11px] font-bold">آلمان VIP • 45ms • 99.9% uptime</div><div class="text-[9px] text-white/40">آنلاین • 120 کلاینت فعال</div></div></div><span class="text-[10px] bg-emerald-500 text-black font-black rounded-full px-2 py-1">LIVE</span></div>
              <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.05] rounded-xl p-3"><div class="flex items-center gap-2.5"><span class="w-2 h-2 bg-cyan-400 rounded-full"></span><div><div class="text-[11px] font-bold">دامنه چرخشی: direct2.vpbotn.ir</div><div class="text-[9px] text-white/40">بهترین: 32ms • آنلاین</div></div></div><span class="text-[10px] bg-cyan-500/20 text-cyan-300 border border-cyan-500/20 rounded-full px-2 py-1">ANTI-FILTER</span></div>
              <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.05] rounded-xl p-3"><div class="flex items-center gap-2.5"><div class="w-7 h-7 rounded-full bg-amber-500/20 flex items-center justify-center"><i class="fa-solid fa-trophy text-[10px] text-amber-300"></i></div><div><div class="text-[11px] font-bold">نماینده الماس: novinvpn - 2,450 امتیاز</div><div class="text-[9px] text-white/30">20% تخفیف • 120 فروش</div></div></div><span class="text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/20 rounded-full px-2 py-1">💎</span></div>
            </div>
            <div class="flex gap-2">
              <div class="flex-1 py-2.5 rounded-full bg-white text-black text-[11px] font-black text-center">📊 گزارش مالی</div>
              <div class="w-10 h-10 rounded-full bg-white/[0.06] border border-white/[0.08] flex items-center justify-center"><i class="fa-solid fa-chart-line text-[12px]"></i></div>
            </div>
          </div>
        </div>
        <div class="absolute -top-4 -right-6 bg-[#0a0a14] border border-white/[0.08] rounded-2xl px-4 py-2.5 shadow-2xl flex items-center gap-2 float" style="animation-delay:1s"><div class="w-8 h-8 rounded-full bg-violet-500/15 flex items-center justify-center"><i class="fa-solid fa-bolt text-violet-400 text-[14px]"></i></div><div><div class="text-[11px] font-bold">PWA نصب شد</div><div class="text-[9px] text-white/40">روی موبایل</div></div></div>
        <div class="absolute -bottom-6 -left-6 bg-[#0a0a14] border border-white/[0.08] rounded-2xl px-4 py-2.5 shadow-2xl flex items-center gap-2 float" style="animation-delay:2s"><div class="w-8 h-8 rounded-full bg-cyan-500/15 flex items-center justify-center"><i class="fa-solid fa-gauge text-cyan-400 text-[14px]"></i></div><div><div class="text-[11px] font-bold">تست سرعت: 85 Mbps</div><div class="text-[9px] text-white/40">Ping 32ms</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- Features ULTRA -->
<section id="features" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="text-center max-w-2xl mx-auto mb-14">
    <div class="inline-flex items-center gap-2 bg-white/[0.04] border border-white/[0.06] rounded-full px-4 py-1.5 text-[11px] text-white/50 mb-4"><i class="fa-solid fa-sparkles text-violet-400"></i> امکانات ULTRA v7.1 - همه چیز برای فروش میلیونی</div>
    <h2 class="text-[28px] sm:text-[36px] font-black leading-tight">همه چیز برای <span class="gradient-text">فروش میلیونی</span> آماده است</h2>
    <p class="text-[13px] text-white/40 mt-3 leading-6">از مانیتورینگ زنده تا گزارش مالی، از دامنه چرخشی تا PWA - پنلی در حد 50 میلیون</p>
  </div>
  <div class="grid md:grid-cols-3 gap-5">
    <?php
    $features = [
      ['icon'=>'fa-heart-pulse','color'=>'emerald','title'=>'مانیتورینگ زنده + آلارم تلگرام','desc'=>'چک هر 5 دقیقه، نمودار آپتایم 24h/7d، آلارم آفلاین/آنلاین با cooldown، ویجت داشبورد LIVE'],
      ['icon'=>'fa-globe','color'=>'cyan','title'=>'دامنه چرخشی ضد فیلتر','desc'=>'اگر یک دامنه فیلتر شد، خودکار به دامنه بعدی سوییچ می‌کند + تست سلامت هر 24 ساعت + تلگرام'],
      ['icon'=>'fa-list-check','color'=>'violet','title'=>'صف همگام‌سازی ضد 520','desc'=>'ایمپورت کامل با progress 10% دسته 30% پلن 60-90% کلاینت + پردازش پس‌زمینه + نتیجه تلگرام'],
      ['icon'=>'fa-chart-pie','color'=>'emerald','title'=>'گزارش مالی پیشرفته ULTRA','desc'=>'نمودار درآمد 12 ماهه، سود خالص 45%، سودآوری سرورها، پرفروش‌ترین پلن‌ها، خروجی Excel'],
      ['icon'=>'fa-mobile-screen','color'=>'blue','title'=>'PWA + اپلیکیشن اختصاصی','desc'=>'پنل قابل نصب روی موبایل، اپلیکیشن اندروید/ویندوز با ورود یوزر پسورد، QR هوشمند، تست سرعت'],
      ['icon'=>'fa-trophy','color'=>'amber','title'=>'سیستم امتیاز نمایندگان','desc'=>'هر فروش = امتیاز، سطح برنز تا الماس، الماس 20% تخفیف، جدول برترین‌ها، رقابت و انگیزه فروش'],
      ['icon'=>'fa-bell','color'=>'yellow','title'=>'مرکز اعلان LIVE + صدا','desc'=>'زنگوله با عدد قرمز، فیلتر همه/خوانده نشده/سرور/مالی، صدای نوتیفیکیشن، mark all read'],
      ['icon'=>'fa-rocket','color'=>'violet','title'=>'ویزارد راه‌اندازی 2 دقیقه‌ای','desc'=>'بعد اولین لاگین، 4 مرحله: ربات، سرور، پلن، بکاپ - با وضعیت ✅/⚠️ و progress'],
      ['icon'=>'fa-shield-halved','color'=>'emerald','title'=>'استقلال کامل دامنه','desc'=>'نصب روی هاست جدید با دامنه جدید = همه چیز خودکار ساخته می‌شود، بدون دخالت دستی، بکاپ domain-free'],
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

<!-- Monitoring Section -->
<section id="monitoring" class="py-20 relative">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="gradient-border rounded-[32px] p-[1px]">
      <div class="bg-[#08080f] rounded-[31px] p-8 sm:p-12">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
          <div class="space-y-6">
            <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3 py-1 text-[11px] text-emerald-300"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span> مانیتورینگ زنده ULTRA</div>
            <h2 class="text-[26px] sm:text-[34px] font-black leading-tight">سرور آفلاین شد؟<br><span class="gradient-text">قبل از مشتری</span>،<br>تو باخبر میشی</h2>
            <ul class="space-y-3 text-[13px]">
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400 text-[10px]"></i></span> چک هر 5 دقیقه + نمودار آپتایم 24 ساعت و 7 روز</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-rose-500/15 border border-rose-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-rose-400 text-[10px]"></i></span> آلارم تلگرام آفلاین/آنلاین با cooldown 30 دقیقه ضد اسپم</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-cyan-500/15 border border-cyan-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-cyan-400 text-[10px]"></i></span> دامنه چرخشی: اگر فیلتر شد، خودکار سوییچ + تلگرام</li>
              <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full bg-violet-500/15 border border-violet-500/20 flex items-center justify-center"><i class="fa-solid fa-check text-violet-400 text-[10px]"></i></span> گزارش مالی: سود هر سرور، پرفروش‌ترین پلن، درآمد ماهانه</li>
            </ul>
            <div class="flex gap-3">
              <a href="<?= htmlspecialchars($panel_login_url) ?>/monitoring" class="px-6 py-3 rounded-full gradient-bg font-black text-[12px] flex items-center gap-2"><i class="fa-solid fa-heart-pulse"></i> دمو مانیتورینگ</a>
              <a href="<?= htmlspecialchars($panel_login_url) ?>/financial" class="px-6 py-3 rounded-full bg-white/[0.06] border border-white/[0.1] font-bold text-[12px] flex items-center gap-2"><i class="fa-solid fa-chart-pie"></i> گزارش مالی</a>
            </div>
          </div>
          <div class="relative flex justify-center">
            <div class="relative w-full max-w-[400px] bg-[#0f0f1a] rounded-[24px] border border-white/[0.08] p-5 space-y-4">
              <div class="flex justify-between items-center"><span class="text-xs font-bold">وضعیت سرورها LIVE</span><span class="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 rounded-full px-2 py-1">● زنده</span></div>
              <div class="space-y-2">
                <div class="flex items-center justify-between bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3"><div class="flex items-center gap-2"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span><span class="text-[11px] font-bold">آلمان VIP - 99.9% - 45ms</span></div><span class="text-[10px] bg-emerald-500 text-black font-black px-2 py-1 rounded-full">آنلاین</span></div>
                <div class="flex items-center justify-between bg-white/[0.03] border border-white/[0.06] rounded-xl p-3"><div class="flex items-center gap-2"><span class="w-2 h-2 bg-cyan-400 rounded-full"></span><span class="text-[11px] font-bold">ایران اکسس - 98.5% - 32ms</span></div><span class="text-[10px] bg-cyan-500/20 text-cyan-300 border border-cyan-500/20 px-2 py-1 rounded-full">کند</span></div>
                <div class="flex items-center justify-between bg-rose-500/10 border border-rose-500/20 rounded-xl p-3"><div class="flex items-center gap-2"><span class="w-2 h-2 bg-rose-400 rounded-full"></span><span class="text-[11px] font-bold">آمریکا - 0% - آفلاین</span></div><span class="text-[10px] bg-rose-500/20 text-rose-300 border border-rose-500/20 px-2 py-1 rounded-full">آفلاین</span></div>
              </div>
              <div class="bg-violet-500/10 border border-violet-500/20 rounded-xl p-3 text-[11px] text-violet-300">📊 سود امروز: 1,912,500 تومان (45% مارجین) • 4 فروش</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="text-center mb-10"><h2 class="text-[24px] font-black">نظرات مشتریان ULTRA</h2><p class="text-[12px] text-white/40 mt-2">بیش از 1,800 فروشگاه فعال به ما اعتماد کرده‌اند</p></div>
  <div class="grid md:grid-cols-3 gap-5">
    <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5"><div class="flex gap-1 text-amber-400 text-xs mb-3">★★★★★</div><p class="text-[12px] leading-6 text-white/60">“بعد از نصب ULTRA، فروشم 3 برابر شد. مانیتورینگ زنده نجاتم داد - سرور آفلاین شد قبل مشتری فهمیدم.”</p><div class="mt-4 flex items-center gap-2"><img src="https://i.pravatar.cc/32?img=11" class="w-8 h-8 rounded-full"><div><div class="text-xs font-bold">علی - NovinVPN</div><div class="text-[10px] text-white/30">💎 الماس - 2,450 امتیاز</div></div></div></div>
    <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5"><div class="flex gap-1 text-amber-400 text-xs mb-3">★★★★★</div><p class="text-[12px] leading-6 text-white/60">“دامنه چرخشی عالیه! direct.vpbotn.ir فیلتر شد، خودکار به direct2 سوییچ کرد و به تلگرامم پیام داد.”</p><div class="mt-4 flex items-center gap-2"><img src="https://i.pravatar.cc/32?img=22" class="w-8 h-8 rounded-full"><div><div class="text-xs font-bold">سارا - SpeedVP</div><div class="text-[10px] text-white/30">🥇 طلا - 890 امتیاز</div></div></div></div>
    <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl p-5"><div class="flex gap-1 text-amber-400 text-xs mb-3">★★★★★</div><p class="text-[12px] leading-6 text-white/60">“گزارش مالی ULTRA فوق‌العاده است. فهمیدم سرور آمریکا ضرر می‌دهد، حذفش کردم و سودم بیشتر شد.”</p><div class="mt-4 flex items-center gap-2"><img src="https://i.pravatar.cc/32?img=33" class="w-8 h-8 rounded-full"><div><div class="text-xs font-bold">رضا - VIPNet</div><div class="text-[10px] text-white/30">🥈 نقره - 320 امتیاز</div></div></div></div>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-16 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
  <div class="text-center mb-10"><h2 class="text-[24px] font-black">سوالات متداول ULTRA</h2></div>
  <div class="space-y-3">
    <?php
    $faqs = [
      ['q'=>'اگر دامنه عوض بشه چی؟','a'=>'پنل 100% مستقل از دامنه است. روی هاست جدید با دامنه جدید نصب کنی، همه چیز خودکار ساخته می‌شود: direct.newdomain.com + newdomain.com + panel_domain. بکاپ‌ها domain-free هستند.'],
      ['q'=>'مانیتورینگ چطور کار می‌کند؟','a'=>'هر 5 دقیقه همه سرورها چک می‌شوند، latency و uptime ثبت می‌شود. اگر 2 بار پشت سر هم آفلاین باشد، تلگرام آلارم می‌دهد با cooldown 30 دقیقه ضد اسپم. وقتی آنلاین شد هم اطلاع می‌دهد.'],
      ['q'=>'دامنه چرخشی ضد فیلتر چیست؟','a'=>'3 دامنه بساز: direct, direct2, direct3. اگر یکی فیلتر شد، سیستم خودکار به بهترین دامنه آنلاین با کمترین latency سوییچ می‌کند و به تلگرام اطلاع می‌دهد. ساب‌لینک‌های جدید با دامنه سالم ساخته می‌شوند.'],
      ['q'=>'صف ضد 520 چیست؟','a'=>'وقتی 1000 کلاینت را ایمپورت می‌کنی، اگر مستقیم انجام شود ارور 520 می‌دهد. الان می‌رود تو صف با progress 10% دسته 30% پلن 60-90% کلاینت و در پس‌زمینه پردازش می‌شود. نتیجه به تلگرام می‌آید.'],
      ['q'=>'گزارش مالی چی دارد؟','a'=>'نمودار درآمد 12 ماهه، درآمد 30 روزه، سود خالص 45%، سود هر سرور بر اساس کلاینت فعال، پرفروش‌ترین پلن‌ها، خروجی Excel. جدول financial_reports از قبل آماده بود و الان استفاده می‌شود.'],
      ['q'=>'PWA چیست؟','a'=>'پنل را می‌توانی روی موبایل Install کنی مثل اپلیکیشن - بدون نیاز به مرورگر. آیکون روی هوم اسکرین، کار آفلاین، shortcuts برای کاربر جدید و مانیتورینگ.'],
    ];
    foreach ($faqs as $i=>$faq):
    ?>
    <div class="bg-white/[0.03] border border-white/[0.06] rounded-2xl overflow-hidden">
      <button onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180')" class="w-full flex items-center justify-between p-5 text-right">
        <span class="font-bold text-[13px]"><?= $faq['q'] ?></span>
        <i class="fa-solid fa-chevron-down text-[10px] text-white/40 transition-transform"></i>
      </button>
      <div class="hidden px-5 pb-5 text-[12px] leading-7 text-white/50"><?= $faq['a'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Dynamic Pricing from DB -->
<section id="pricing" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="text-center max-w-2xl mx-auto mb-12">
    <h2 class="text-[28px] font-black">پلن‌های فروش - داینامیک از دیتابیس</h2>
    <p class="text-[12px] text-white/40 mt-2">قیمت‌ها زنده از پنل شما می‌آیند - اگر پلنی در پنل بسازی، اینجا هم نمایش داده می‌شود</p>
  </div>
  
  <?php if (!empty($dynamicPlans)): ?>
  <div class="grid md:grid-cols-3 gap-5 mb-10">
    <?php foreach ($dynamicPlans as $dp): ?>
    <div class="bg-white/[0.03] border border-white/[0.06] rounded-[24px] p-6 text-center card-hover">
      <div class="text-[11px] text-violet-300 bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 inline-block mb-3"><?= htmlspecialchars($dp['category'] ?? 'پیش‌فرض') ?></div>
      <h3 class="font-black text-[16px]"><?= htmlspecialchars($dp['title']) ?></h3>
      <div class="text-[12px] text-white/40 mt-1"><?= $dp['traffic_gb'] ?>GB • <?= $dp['duration_days'] ?> روزه</div>
      <div class="mt-4"><span class="text-[24px] font-black text-white"><?= number_format((int)$dp['base_price']) ?></span><span class="text-[11px] text-white/40"> تومان</span></div>
      <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="mt-5 block w-full py-3 rounded-full bg-white/[0.06] border border-white/[0.1] text-[12px] font-bold hover:bg-white/[0.1] transition">خرید آنی</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid lg:grid-cols-2 gap-8">
    <div class="gradient-border rounded-[32px] p-[1px]">
      <div class="bg-[#08080f] rounded-[31px] p-8">
        <h3 class="text-[20px] font-black">پلن‌های پنل ULTRA</h3>
        <div class="mt-6 space-y-3">
          <div class="flex items-center justify-between bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div><div class="font-bold text-[13px]">استارتر</div><div class="text-[11px] text-white/40">مانیتورینگ + دامنه چرخشی + مالی</div></div><div class="text-left"><div class="font-black text-[16px]">1.9M</div><div class="text-[10px] text-white/30">تومان</div></div></div>
          <div class="flex items-center justify-between bg-violet-500/10 border border-violet-500/30 rounded-2xl p-4 relative overflow-hidden"><div class="absolute top-0 right-0 bg-violet-500 text-black text-[9px] font-black px-3 py-1 rounded-bl-xl">ULTRA محبوب</div><div><div class="font-black text-[13px] text-violet-200">حرفه‌ای ULTRA + PWA + امتیاز</div><div class="text-[11px] text-violet-300/60">همه امکانات + اپ اختصاصی + گزارش مالی</div></div><div class="text-left"><div class="font-black text-[18px] text-white">3.9M</div><div class="text-[10px] text-violet-300">تومان</div></div></div>
          <div class="flex items-center justify-between bg-white/[0.04] border border-white/[0.06] rounded-2xl p-4"><div><div class="font-bold text-[13px]">بیزینس ULTRA</div><div class="text-[11px] text-white/40">سورس کامل + لایسنس مادام‌العمر + پشتیبانی ویژه</div></div><div class="text-left"><div class="font-black text-[16px]">7.5M</div><div class="text-[10px] text-white/30">تومان</div></div></div>
        </div>
        <a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="mt-6 w-full py-4 rounded-full gradient-bg font-black text-[13px] text-center block">مشاوره و خرید ULTRA در تلگرام</a>
      </div>
    </div>

    <div class="bg-white/[0.03] border border-white/[0.06] rounded-[32px] p-8">
      <h3 class="text-[18px] font-black">درخواست مشاوره ULTRA</h3>
      <p class="text-[12px] text-white/40 mt-2">فرم رو پر کن، کمتر از 2 ساعت باهات تماس میگیریم</p>
      <?php if ($success): ?><div class="mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-[12px]">✅ درخواست ULTRA شما ثبت شد - به زودی تماس می‌گیریم</div>
      <?php elseif ($error): ?><div class="mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-[12px]"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" class="mt-6 space-y-3">
        <input type="hidden" name="reseller_request" value="1">
        <div class="grid grid-cols-2 gap-3">
          <input name="name" required placeholder="نام شما *" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
          <input name="phone" placeholder="موبایل" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <input name="telegram_id" placeholder="آیدی تلگرام" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
          <select name="plan" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none"><option value="">انتخاب پلن</option><option>استارتر 1.9M</option><option>حرفه‌ای ULTRA 3.9M</option><option>بیزینس ULTRA 7.5M</option></select>
        </div>
        <input name="business" placeholder="نوع کسب‌وکار شما" class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none">
        <textarea name="message" rows="3" placeholder="پیام شما..." class="w-full bg-[#0a0a14] border border-white/[0.08] rounded-xl px-4 py-3 text-[12px] focus:border-violet-500/50 focus:outline-none"></textarea>
        <button type="submit" class="w-full py-4 rounded-full bg-white text-black font-black text-[13px] hover:bg-white/90 transition">ارسال درخواست ULTRA</button>
        <div class="text-[10px] text-center text-white/20">پاسخگویی: <?= htmlspecialchars($tg) ?> • <?= htmlspecialchars($domain) ?> • ULTRA v7.1</div>
      </form>
    </div>
  </div>
</section>

<!-- Demo -->
<section id="demo" class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="bg-white/[0.03] border border-white/[0.06] rounded-[32px] p-8 sm:p-10">
    <div class="grid md:grid-cols-3 gap-8 items-center">
      <div class="md:col-span-2">
        <h3 class="text-[22px] font-black">دمو آنلاین ULTRA v7.1</h3>
        <p class="text-[13px] text-white/40 mt-2 leading-6">بدون ثبت‌نام، همین الان وارد پنل ULTRA شو و مانیتورینگ زنده، گزارش مالی، دامنه چرخشی، PWA و سیستم امتیاز را تست کن.</p>
        <div class="flex flex-wrap gap-3 mt-5">
          <div class="bg-[#0a0a14] border border-white/[0.08] rounded-full px-4 py-2 text-[12px] font-mono">یوزر: <span class="text-violet-300 font-bold"><?= htmlspecialchars($demo_user) ?></span></div>
          <div class="bg-[#0a0a14] border border-white/[0.08] rounded-full px-4 py-2 text-[12px] font-mono">پسورد: <span class="text-cyan-300 font-bold"><?= htmlspecialchars($demo_pass) ?></span></div>
        </div>
      </div>
      <div class="flex flex-col gap-3">
        <a href="<?= htmlspecialchars($panel_login_url) ?>" class="w-full py-4 rounded-full gradient-bg font-black text-[13px] text-center shadow-[0_0_30px_rgba(124,58,237,.4)]">ورود به دمو ULTRA</a>
        <div class="text-[10px] text-center text-white/30">ULTRA v7.1 • مانیتورینگ LIVE • گزارش مالی • PWA</div>
      </div>
    </div>
  </div>
</section>

<footer class="border-t border-white/[0.06] py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
  <div class="flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-white/30">
    <div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full gradient-bg flex items-center justify-center font-black text-white text-[12px]">C</div><span>Connectix ULTRA v7.1 • مانیتورینگ زنده • دامنه چرخشی • گزارش مالی • PWA • امتیاز نمایندگان • @mainAdminpanel</span></div>
    <div class="flex items-center gap-4"><a href="<?= htmlspecialchars($panel_login_url) ?>" class="hover:text-white transition">ورود به پنل</a><a href="<?= htmlspecialchars($tg_url) ?>" target="_blank" class="hover:text-white transition">پشتیبانی تلگرام</a><span>© 2026 Connectix ULTRA</span></div>
  </div>
</footer>

</body>
</html>

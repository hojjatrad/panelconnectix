<?php
// Landing for https://vpbotn.ir/ - Main Domain Root
// Copy this file to public_html/index.php (one level above /contax)
// Telegram: @mainAdminpanel
$brand = 'Connectix - نوین نت پرو';
$telegram = '@mainAdminpanel';
$panel_url = 'https://vpbotn.ir/contax/';
$assets_base = 'https://vpbotn.ir/contax/assets/ai_guides/';
$ads_base = 'https://vpbotn.ir/contax/ads/';
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reseller_request'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $tg = trim($_POST['telegram_id'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $biz = trim($_POST['business'] ?? '');
    $msg = trim($_POST['message'] ?? '');

    if ($name === '' || ($phone === '' && $tg === '')) {
        $error = 'لطفاً نام و یک راه ارتباطی وارد کنید.';
    } else {
        $log = date('Y-m-d H:i:s') . " | $name | $phone | $tg | $plan | $biz | $msg | IP:" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        @file_put_contents(__DIR__ . '/requests.log', $log, FILE_APPEND);
        // Try send via contax bot if exists
        try {
            $contaxRoot = __DIR__ . '/contax';
            if (file_exists($contaxRoot . '/config.php')) {
                require_once $contaxRoot . '/config.php';
                require_once $contaxRoot . '/core/Database.php';
                require_once $contaxRoot . '/core/Setting.php';
                require_once $contaxRoot . '/core/TelegramBot.php';
                $pdo = Database::getConnection();
                $token = Setting::get('telegram_bot_token','');
                $admin = Setting::get('telegram_admin_id','');
                $logCh = Setting::get('bot_log_channel','') ?: Setting::get('telegram_log_channel_id','');
                $text = "🔥 <b>درخواست جدید از سایت اصلی vpbotn.ir</b>\n\n"
                      . "👤 نام: $name\n📱 موبایل: $phone\n✈️ تلگرام: $tg\n💼 کسب‌وکار: $biz\n📦 پلن: $plan\n💬 پیام: $msg\n\n"
                      . "🌐 https://vpbotn.ir/\n🕐 ".date('Y-m-d H:i:s');
                if ($admin) TelegramBot::sendMessage($text, $admin, null, $token);
                if ($logCh) TelegramBot::sendMessage($text, $logCh, null, $token);
            }
        } catch (Throwable $e) {}
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل نمایندگی VPN با اپ اختصاصی فارسی | Connectix | @mainAdminpanel</title>
<meta name="description" content="پنل نمایندگی VPN با اپ اندروید اختصاصی فارسی، ربات تلگرام فروش خودکار، سود 200%، سرور VLESS Reality ضدفیلتر. درخواست: @mainAdminpanel - https://vpbotn.ir">
<meta property="og:image" content="<?= $assets_base ?>reseller-panel.jpg">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>*{font-family:Vazirmatn,sans-serif}html{scroll-behavior:smooth}.glass{backdrop-filter:blur(16px)}.gradient-text{background:linear-gradient(90deg,#7C3AED,#06B6D4);-webkit-background-clip:text;-webkit-text-fill-color:transparent}.gradient-bg{background:linear-gradient(135deg,#7C3AED,#06B6D4)}@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}.float{animation:float 6s ease-in-out infinite}</style>
</head>
<body class="bg-[#0B0F1A] text-white">
<nav class="fixed top-0 w-full z-50 bg-[#0B0F1A]/80 glass border-b border-white/10">
<div class="max-w-7xl mx-auto px-4 flex justify-between items-center h-16">
<div class="flex items-center gap-3"><div class="w-9 h-9 gradient-bg rounded-xl flex items-center justify-center font-black">C</div><span class="font-black text-xl">Connectix</span><span class="hidden sm:inline text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-full px-2.5 py-1 mr-2">فارسی - آنلاین</span></div>
<div class="hidden md:flex gap-6 text-sm text-white/60"><a href="#features" class="hover:text-white">امکانات فارسی</a><a href="#pricing" class="hover:text-white">تعرفه</a><a href="#request" class="hover:text-white">درخواست</a><a href="<?= $panel_url ?>" class="hover:text-white">ورود به پنل</a></div>
<div class="flex gap-2"><a href="https://t.me/mainAdminpanel" target="_blank" class="hidden sm:flex bg-white/10 border border-white/20 rounded-xl px-4 py-2 text-sm"><i class="fa-brands fa-telegram text-sky-400"></i> @mainAdminpanel</a><a href="#request" class="gradient-bg rounded-xl px-5 py-2.5 text-sm font-bold">ثبت درخواست</a></div>
</div>
</nav>

<section class="pt-32 pb-12 px-4 relative overflow-hidden">
<div class="absolute top-20 right-10 w-96 h-96 bg-violet-600/20 rounded-full blur-[120px] -z-10"></div>
<div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-10 items-center">
<div class="space-y-5">
<div class="inline-flex bg-emerald-500/10 border border-emerald-500/30 rounded-full px-4 py-1.5 text-xs text-emerald-300">🎉 جشنواره: 3 ماه + 1 ماه هدیه + 500ت شارژ - محدود</div>
<h1 class="text-4xl sm:text-5xl font-black leading-[1.15]">پنل نمایندگی VPN<br>با <span class="gradient-text">اپ اختصاصی فارسی</span><br>درآمد ماهانه 10 تا 25 میلیون</h1>
<p class="text-white/60 text-sm leading-relaxed max-w-xl">بدون دانش فنی، با برند خودت کسب و کار VPN راه بنداز. اپ اندروید اختصاصی فارسی، ربات تلگرام فروش خودکار 24 ساعته، سود 200%. پشتیبانی کاملاً فارسی: <b class="text-white">@mainAdminpanel</b> - دامنه اصلی: https://vpbotn.ir</p>
<div class="flex flex-wrap gap-3"><a href="#request" class="gradient-bg rounded-2xl px-8 py-4 font-bold flex items-center gap-2 shadow-xl">🚀 ثبت درخواست نمایندگی</a><a href="https://t.me/mainAdminpanel" target="_blank" class="bg-white/10 border border-white/20 rounded-2xl px-6 py-4 font-bold flex items-center gap-2"><i class="fa-brands fa-telegram text-sky-400"></i> مشاوره: @mainAdminpanel</a></div>
<div class="grid grid-cols-3 gap-3 max-w-md pt-4"><div class="bg-white/[0.04] border border-white/10 rounded-2xl p-3 text-center"><div class="text-xl font-black gradient-text">200+</div><div class="text-[10px] text-white/50">نماینده فعال فارسی</div></div><div class="bg-white/[0.04] border border-white/10 rounded-2xl p-3 text-center"><div class="text-xl font-black">50K+</div><div class="text-[10px] text-white/50">کاربر نهایی</div></div><div class="bg-white/[0.04] border border-white/10 rounded-2xl p-3 text-center"><div class="text-xl font-black text-emerald-400">فارسی</div><div class="text-[10px] text-white/50">پنل و اپ کاملاً فارسی</div></div></div>
</div>
<div class="relative"><div class="bg-gradient-to-b from-white/[0.08] to-white/[0.02] border border-white/10 rounded-[2rem] p-3 float"><img src="<?= $assets_base ?>reseller-panel.jpg" class="w-full rounded-[1.5rem] border border-white/10"></div><img src="<?= $ads_base ?>banner-fa-1.jpg" class="mt-4 w-full rounded-2xl border border-white/10"></div>
</div>
</section>

<section id="request" class="py-14 px-4 bg-white/[0.02] border-y border-white/10">
<div class="max-w-5xl mx-auto grid lg:grid-cols-5 gap-8">
<div class="lg:col-span-2 space-y-4"><h2 class="text-3xl font-black">فرم درخواست نمایندگی فارسی</h2><p class="text-white/60 text-sm">فرم را پر کن، کمتر از 30 دقیقه با شما تماس می‌گیریم. یا مستقیم به @mainAdminpanel پیام بده.</p><div class="bg-sky-500/10 border border-sky-500/20 rounded-2xl p-4 text-xs"><i class="fa-brands fa-telegram text-sky-400"></i> پشتیبانی فارسی 24/7: <b>@mainAdminpanel</b><br>سایت اصلی: https://vpbotn.ir<br>پنل: https://vpbotn.ir/contax/</div><img src="<?= $ads_base ?>banner-fa-2.jpg" class="w-full rounded-2xl border border-white/10"></div>
<div class="lg:col-span-3 bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-6">
<?php if($success): ?>
<div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-6 text-center"><div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center mx-auto text-2xl"><i class="fa-solid fa-check"></i></div><h3 class="font-black text-xl text-emerald-300 mt-3">درخواست ثبت شد! ✅</h3><p class="text-sm text-white/70 mt-2">کمتر از 30 دقیقه با شما تماس می‌گیریم. برای ارتباط سریع‌تر به <a href="https://t.me/mainAdminpanel" class="text-sky-400 font-bold underline">@mainAdminpanel</a> پیام دهید.</p></div>
<?php else: ?>
<?php if($error): ?><div class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 text-xs text-rose-300 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" class="space-y-4">
<input type="hidden" name="reseller_request" value="1">
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/60 mb-1">نام و نام خانوادگی *</label><input type="text" name="name" required placeholder="مثلاً: علی رضایی" class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"></div><div><label class="block text-[11px] text-white/60 mb-1">شماره موبایل</label><input type="text" name="phone" placeholder="0912..." class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"></div></div>
<div class="grid sm:grid-cols-2 gap-4"><div><label class="block text-[11px] text-white/60 mb-1">آیدی تلگرام *</label><input type="text" name="telegram_id" required placeholder="@username" class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"></div><div><label class="block text-[11px] text-white/60 mb-1">پلن درخواستی</label><select name="plan" class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"><option>استارتر 299ت</option><option selected>حرفه‌ای 599ت ⭐ پرفروش - با اپ فارسی</option><option>بیزینس 1.29م - کامل</option></select></div></div>
<div><label class="block text-[11px] text-white/60 mb-1">نوع کسب‌وکار</label><select name="business" class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"><option>پیج اینستاگرام</option><option>کانال تلگرام</option><option>سایت</option><option>شروع جدید</option></select></div>
<div><label class="block text-[11px] text-white/60 mb-1">پیام شما</label><textarea name="message" rows="3" placeholder="توضیحات..." class="w-full bg-[#0B0F1A] border border-white/15 rounded-xl px-4 py-3 text-sm"></textarea></div>
<button type="submit" class="w-full gradient-bg rounded-xl py-4 font-black">ثبت درخواست + 500ت هدیه - @mainAdminpanel</button>
<div class="text-center"><a href="https://t.me/mainAdminpanel" target="_blank" class="inline-flex bg-sky-500/20 border border-sky-500/30 text-sky-300 rounded-full px-4 py-1.5 text-xs font-bold"><i class="fa-brands fa-telegram"></i> ارتباط مستقیم: @mainAdminpanel</a></div>
</form>
<?php endif; ?>
</div>
</div>
</section>

<footer class="py-8 px-4 border-t border-white/10 text-center text-[11px] text-white/30">
<div>© 2025 Connectix - پنل نمایندگی VPN فارسی - https://vpbotn.ir - پشتیبانی: @mainAdminpanel - نسخه 5.7.1</div>
<div class="mt-2"><a href="<?= $panel_url ?>" class="text-violet-400 hover:underline">ورود به پنل مدیریت</a> | <a href="https://t.me/mainAdminpanel" class="text-sky-400 hover:underline">تلگرام: @mainAdminpanel</a></div>
</footer>
</body>
</html>

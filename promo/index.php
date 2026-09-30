<?php
// Connectix Reseller Landing - v5.7.1 - Persian Full with Request Form
// For https://vpbotn.ir/contax/promo/ and https://vpbotn.ir/ (copy to root)
// Telegram: @mainAdminpanel
$brand = 'Connectix';
$telegram_support = '@mainAdminpanel';
$telegram_bot_demo = '@mainAdminpanel';
$domain = 'https://vpbotn.ir';
$base = $domain . '/contax/';
$base_root = $domain . '/';
$request_success = false;
$request_error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reseller_request'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $telegram_id = trim($_POST['telegram_id'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $business = trim($_POST['business'] ?? '');

    if ($name === '' || ($phone === '' && $telegram_id === '')) {
        $request_error = 'لطفاً نام و حداقل یک راه ارتباطی (شماره یا آیدی تلگرام) را وارد کنید.';
    } else {
        // Save to log
        $logDir = __DIR__;
        $logFile = $logDir . '/requests.log';
        $data = date('Y-m-d H:i:s') . " | نام: $name | موبایل: $phone | تلگرام: $telegram_id | پلن: $plan | کسب‌وکار: $business | پیام: $message | IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        @file_put_contents($logFile, $data, FILE_APPEND);

        // Try to send to Telegram via panel's bot
        try {
            $panelRoot = dirname(__DIR__);
            if (file_exists($panelRoot . '/config.php')) {
                require_once $panelRoot . '/config.php';
                require_once $panelRoot . '/core/Database.php';
                require_once $panelRoot . '/core/Setting.php';
                require_once $panelRoot . '/core/TelegramBot.php';
                $pdo = Database::getConnection();
                $botToken = Setting::get('telegram_bot_token', '');
                $adminChat = Setting::get('telegram_admin_id', '');
                $logChannel = Setting::get('bot_log_channel', '') ?: Setting::get('telegram_log_channel_id', '');

                $msg = "🔥 <b>درخواست جدید نمایندگی VPN</b>\n\n"
                     . "👤 <b>نام:</b> " . htmlspecialchars($name) . "\n"
                     . "📱 <b>موبایل:</b> " . htmlspecialchars($phone) . "\n"
                     . "✈️ <b>تلگرام:</b> " . htmlspecialchars($telegram_id) . "\n"
                     . "💼 <b>نوع کسب‌وکار:</b> " . htmlspecialchars($business) . "\n"
                     . "📦 <b>پلن درخواستی:</b> " . htmlspecialchars($plan) . "\n"
                     . "💬 <b>پیام:</b> " . htmlspecialchars($message) . "\n\n"
                     . "🌐 از صفحه: " . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'] . "\n"
                     . "🕐 " . date('Y-m-d H:i:s');

                // Send to admin chat if exists, else to log channel
                if (!empty($adminChat)) {
                    TelegramBot::sendMessage($msg, $adminChat, null, $botToken ?: null);
                }
                if (!empty($logChannel)) {
                    TelegramBot::sendMessage($msg, $logChannel, null, $botToken ?: null);
                }
                // Also try to send directly to @mainAdminpanel if bot can (via username not reliable, but try)
                // Telegram API doesn't support sending to @username for users, only channels. So we rely on adminChat.
            }
        } catch (Throwable $e) {
            // ignore telegram errors, still show success
        }

        $request_success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل نمایندگی VPN با اپ اختصاصی | درآمد ماهانه 10 تا 25 میلیون | <?= htmlspecialchars($brand) ?></title>
    <meta name="description" content="پنل نمایندگی VPN با اپ اندروید اختصاصی با برند شما، ربات تلگرام فروش خودکار 24 ساعته، سود 200%، سرور VLESS Reality ضدفیلتر. از 299 هزار تومان. پشتیبانی: @mainAdminpanel">
    <meta name="keywords" content="نمایندگی VPN, پنل VPN, اپ اختصاصی VPN, کسب درآمد, فروش VPN, ربات VPN, Connectix">
    <meta property="og:title" content="پنل نمایندگی VPN با اپ اختصاصی - درآمد میلیونی | @mainAdminpanel">
    <meta property="og:description" content="اپ با برند خودت + ربات فروش 24 ساعته + سود 200% - از 299 هزار تومان - درخواست: @mainAdminpanel">
    <meta property="og:image" content="<?= $base ?>assets/ai_guides/reseller-panel.jpg">
    <meta property="og:url" content="https://vpbotn.ir/">
    <meta name="theme-color" content="#7C3AED">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *{font-family: Vazirmatn, sans-serif}
        html{scroll-behavior: smooth}
        .glass{backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px)}
        .gradient-text{background: linear-gradient(90deg,#7C3AED,#06B6D4); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text}
        .gradient-bg{background: linear-gradient(135deg,#7C3AED 0%,#06B6D4 100%)}
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
                <span class="hidden sm:inline text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-full px-2.5 py-1 mr-2">● آنلاین - پشتیبانی فعال</span>
            </div>
            <div class="hidden md:flex items-center gap-6 text-sm text-white/70">
                <a href="#features" class="hover:text-white transition">امکانات</a>
                <a href="#app" class="hover:text-white transition">اپ اختصاصی</a>
                <a href="#pricing" class="hover:text-white transition">تعرفه</a>
                <a href="#request" class="hover:text-white transition">درخواست نمایندگی</a>
            </div>
            <div class="flex items-center gap-2">
                <a href="https://t.me/mainAdminpanel" target="_blank" class="hidden sm:flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl px-4 py-2 text-sm transition">
                    <i class="fa-brands fa-telegram text-sky-400"></i> @mainAdminpanel
                </a>
                <a href="#request" class="gradient-bg hover:opacity-90 rounded-xl px-5 py-2.5 text-sm font-bold shadow-lg shadow-violet-600/20 transition">ثبت درخواست</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="relative pt-32 pb-16 px-4 overflow-hidden">
    <div class="absolute inset-0 -z-10">
        <div class="absolute top-20 right-10 w-96 h-96 bg-violet-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-20 left-10 w-96 h-96 bg-cyan-500/15 rounded-full blur-[120px]"></div>
    </div>
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
        <div class="space-y-6">
            <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 rounded-full px-4 py-1.5 text-xs">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                <span class="text-emerald-300">🔥 جشنواره فعال: 3 ماه + 1 ماه هدیه + 500 هزار تومان شارژ هدیه</span>
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-[48px] font-black leading-[1.15]">
                پنل نمایندگی VPN<br>
                با <span class="gradient-text">اپ اختصاصی فارسی</span><br>
                و ربات فروش خودکار
            </h1>
            <p class="text-white/60 text-base sm:text-[15px] leading-relaxed max-w-xl">
                بدون دانش فنی، با برند خودت کسب و کار VPN راه بنداز. اپ اندروید اختصاصی با نام و لوگوی شما، ربات تلگرام 24 ساعته، سود <b class="text-white">200% هر فروش</b>. میانگین درآمد نمایندگان فعال: <b class="text-emerald-400">10 تا 25 میلیون در ماه</b>
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="#request" class="gradient-bg hover:opacity-90 rounded-2xl px-8 py-4 font-bold flex items-center gap-2 shadow-xl shadow-violet-600/25 glow transition">
                    <i class="fa-solid fa-rocket"></i> ثبت درخواست نمایندگی - تحویل 30 دقیقه‌ای
                </a>
                <a href="https://t.me/mainAdminpanel" target="_blank" class="bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl px-6 py-4 font-bold flex items-center gap-2 transition">
                    <i class="fa-brands fa-telegram text-sky-400"></i> مشاوره در تلگرام
                </a>
            </div>
            <div class="flex items-center gap-6 pt-2 text-xs text-white/50 flex-wrap">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> بدون کدنویسی</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> تحویل آنی</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> پشتیبانی 24/7</span>
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-emerald-400"></i> کاملاً فارسی</span>
            </div>
            <!-- Trust badges -->
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
                    <div class="text-[11px] text-white/50 mt-1">آپتایم سرور</div>
                </div>
            </div>
        </div>
        <div class="relative">
            <div class="relative bg-gradient-to-b from-white/[0.08] to-white/[0.02] border border-white/10 rounded-[2rem] p-3 shadow-2xl float">
                <img src="<?= $base ?>assets/ai_guides/reseller-panel.jpg" alt="پنل نماینده فارسی" class="w-full rounded-[1.5rem] border border-white/10">
                <div class="absolute -bottom-6 -right-6 bg-[#0B0F1A] border border-white/20 rounded-2xl p-4 shadow-xl flex items-center gap-3">
                    <div class="w-12 h-12 bg-emerald-500/20 border border-emerald-500/30 rounded-xl flex items-center justify-center"><i class="fa-solid fa-chart-line text-emerald-400"></i></div>
                    <div>
                        <div class="text-xs text-white/50">سود امروز</div>
                        <div class="font-black text-emerald-400">+2,450,000 تومان</div>
                    </div>
                </div>
                <div class="absolute -top-6 -left-6 bg-[#0B0F1A] border border-white/20 rounded-2xl p-3 shadow-xl">
                    <div class="flex items-center gap-2 text-xs"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span> 12 فروش امروز - ربات فعال</div>
                </div>
            </div>
            <!-- Persian banner -->
            <div class="mt-6 grid grid-cols-1 gap-3">
                <img src="<?= $base ?>ads/banner-fa-1.jpg" alt="پنل نمایندگی با اپ اختصاصی" class="w-full rounded-2xl border border-white/10">
            </div>
        </div>
    </div>
</section>

<!-- REQUEST FORM - NEW -->
<section id="request" class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="grid lg:grid-cols-5 gap-8 items-start">
            <div class="lg:col-span-2 space-y-5">
                <div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300">📝 فرم درخواست نمایندگی</div>
                <h2 class="text-3xl font-black leading-tight">فرم درخواست را پر کن<br><span class="gradient-text">30 دقیقه بعد پنلت آماده‌ست</span></h2>
                <p class="text-white/60 text-sm leading-relaxed">اطلاعاتت رو وارد کن، همکاران ما در کمتر از 30 دقیقه از طریق تلگرام یا تماس با شما ارتباط می‌گیرند. مشاوره کاملاً رایگان.</p>
                <div class="space-y-3 pt-2">
                    <div class="flex gap-3 bg-white/[0.04] border border-white/10 rounded-2xl p-4">
                        <div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center shrink-0"><i class="fa-solid fa-bolt text-emerald-400"></i></div>
                        <div><div class="font-bold text-sm">تحویل آنی</div><div class="text-xs text-white/50 mt-1">بعد از پرداخت، کمتر از 30 دقیقه پنل + ربات + اپ تحویل داده می‌شود</div></div>
                    </div>
                    <div class="flex gap-3 bg-white/[0.04] border border-white/10 rounded-2xl p-4">
                        <div class="w-10 h-10 bg-sky-500/20 rounded-xl flex items-center justify-center shrink-0"><i class="fa-brands fa-telegram text-sky-400"></i></div>
                        <div><div class="font-bold text-sm">پشتیبانی مستقیم: @mainAdminpanel</div><div class="text-xs text-white/50 mt-1">هر سوالی داری، مستقیم به آیدی بالا پیام بده - پاسخگویی 24 ساعته</div></div>
                    </div>
                </div>
                <img src="<?= $base ?>ads/banner-fa-2.jpg" alt="سود 200%" class="w-full rounded-2xl border border-white/10 mt-4">
            </div>
            <div class="lg:col-span-3">
                <div class="bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-6 sm:p-8 shadow-2xl">
                    <?php if ($request_success): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-6 text-center space-y-3">
                        <div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center mx-auto text-2xl"><i class="fa-solid fa-check"></i></div>
                        <h3 class="font-black text-xl text-emerald-300">درخواست شما با موفقیت ثبت شد! ✅</h3>
                        <p class="text-sm text-white/70 leading-relaxed">همکاران ما در کمتر از 30 دقیقه از طریق تلگرام یا تماس با شما ارتباط می‌گیرند.<br>برای ارتباط سریع‌تر به آیدی <a href="https://t.me/mainAdminpanel" target="_blank" class="text-sky-400 font-bold underline">@mainAdminpanel</a> پیام دهید.</p>
                        <div class="pt-2 flex justify-center gap-2">
                            <a href="https://t.me/mainAdminpanel" target="_blank" class="gradient-bg rounded-xl px-6 py-3 text-sm font-bold flex items-center gap-2"><i class="fa-brands fa-telegram"></i> پیام به پشتیبانی</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($request_error): ?><div class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 text-xs text-rose-300 mb-4"><?= htmlspecialchars($request_error) ?></div><?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="reseller_request" value="1">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] text-white/60 mb-1.5">نام و نام خانوادگی <span class="text-rose-400">*</span></label>
                                <input type="text" name="name" required placeholder="مثلاً: علی رضایی" class="w-full bg-[#0B0F1A] border border-white/15 focus:border-violet-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[11px] text-white/60 mb-1.5">شماره موبایل <span class="text-white/40">(اختیاری اگر تلگرام دارید)</span></label>
                                <input type="text" name="phone" placeholder="0912..." class="w-full bg-[#0B0F1A] border border-white/15 focus:border-violet-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition">
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] text-white/60 mb-1.5">آیدی تلگرام <span class="text-rose-400">*</span></label>
                                <input type="text" name="telegram_id" required placeholder="@username یا شماره" class="w-full bg-[#0B0F1A] border border-white/15 focus:border-violet-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[11px] text-white/60 mb-1.5">نوع کسب‌وکار فعلی</label>
                                <select name="business" class="w-full bg-[#0B0F1A] border border-white/15 focus:border-violet-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition">
                                    <option value="">انتخاب کنید</option>
                                    <option value="پیج اینستاگرام">پیج اینستاگرام</option>
                                    <option value="کانال تلگرام">کانال تلگرام</option>
                                    <option value="سایت">سایت</option>
                                    <option value="فروش حضوری">فروش حضوری / مغازه</option>
                                    <option value="شروع جدید">شروع جدید - بدون سابقه</option>
                                    <option value="سایر">سایر</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] text-white/60 mb-1.5">پلن مورد نظر</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <label class="cursor-pointer bg-[#0B0F1A] border border-white/10 hover:border-violet-500/50 rounded-xl p-3 flex items-center gap-2 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-500/10">
                                    <input type="radio" name="plan" value="استارتر 299ت" class="accent-violet-500" checked>
                                    <span class="text-xs"><b>استارتر</b><br><span class="text-white/50">299ت - 20% تخفیف</span></span>
                                </label>
                                <label class="cursor-pointer bg-[#0B0F1A] border border-violet-500/50 bg-violet-500/10 rounded-xl p-3 flex items-center gap-2 transition has-[:checked]:border-violet-500">
                                    <input type="radio" name="plan" value="حرفه‌ای 599ت ⭐ پرفروش" class="accent-violet-500" checked>
                                    <span class="text-xs"><b>حرفه‌ای ⭐</b><br><span class="text-violet-300">599ت - 35% + اپ اختصاصی</span></span>
                                </label>
                                <label class="cursor-pointer bg-[#0B0F1A] border border-white/10 hover:border-violet-500/50 rounded-xl p-3 flex items-center gap-2 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-500/10">
                                    <input type="radio" name="plan" value="بیزینس 1.29م">
                                    <span class="text-xs"><b>بیزینس</b><br><span class="text-white/50">1.29م - 50% + همه چیز</span></span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] text-white/60 mb-1.5">توضیحات / سوال شما (اختیاری)</label>
                            <textarea name="message" rows="3" placeholder="مثلاً: من پیج 20k دارم، می‌خوام شروع کنم. لطفاً راهنمایی کنید..." class="w-full bg-[#0B0F1A] border border-white/15 focus:border-violet-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition"></textarea>
                        </div>
                        <button type="submit" class="w-full gradient-bg hover:opacity-90 rounded-xl py-4 font-black flex items-center justify-center gap-2 shadow-xl shadow-violet-600/20 transition">
                            <i class="fa-solid fa-paper-plane"></i> ثبت درخواست نمایندگی + دریافت 500ت هدیه
                        </button>
                        <div class="text-[11px] text-white/40 text-center">با ثبت درخواست، همکاران ما در کمتر از 30 دقیقه با شما تماس می‌گیرند. اطلاعات شما محفوظ است.</div>
                        <div class="flex items-center justify-center gap-2 text-xs pt-2">
                            <span class="text-white/40">یا مستقیم پیام بده:</span>
                            <a href="https://t.me/mainAdminpanel" target="_blank" class="bg-sky-500/20 border border-sky-500/30 text-sky-300 rounded-full px-4 py-1.5 font-bold hover:bg-sky-500/30 transition"><i class="fa-brands fa-telegram"></i> @mainAdminpanel</a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-20 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <div class="inline-flex bg-violet-500/10 border border-violet-500/20 rounded-full px-3 py-1 text-[11px] text-violet-300 mb-4">چرا Connectix؟</div>
            <h2 class="text-3xl sm:text-4xl font-black leading-tight">همه چیز برای یک کسب و کار کامل فارسی</h2>
            <p class="text-white/50 mt-3 text-sm">شما فقط پنل نمی‌خرید، یک بیزینس آماده با برند خودتان تحویل می‌گیرید - کاملاً فارسی</p>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 gradient-bg rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-mobile-screen"></i></div>
                <h3 class="font-bold">📱 اپ اختصاصی با برند شما - فارسی</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">نام، لوگو، رنگ شما. Universal + ARM64. کاملاً فارسی. ارزش بیرون 20 میلیون، اینجا رایگان</p>
                <img src="<?= $base ?>ads/banner-fa-3.jpg" alt="اپ با برند شما" class="mt-4 w-full h-28 object-cover rounded-xl border border-white/10">
            </div>
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-sky-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-brands fa-telegram"></i></div>
                <h3 class="font-bold">🤖 ربات تلگرام فروش خودکار فارسی</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">ربات با یوزرنیم دلخواه شما، منوهای کاملاً فارسی. فروش 24 ساعته، پرداخت کارت، تتر، تون</p>
                <div class="mt-3 text-[11px] bg-sky-500/10 border border-sky-500/20 rounded-xl px-3 py-2">💬 درخواست: @mainAdminpanel</div>
            </div>
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-violet-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-sack-dollar"></i></div>
                <h3 class="font-bold">💰 سود 200% - قیمت دست شما</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">خرید 40ت، فروش 120ت، سود 80ت. تخفیف تا 50% + شارژ هدیه + پورسانت 10% معرفی + کیف پول</p>
                <img src="<?= $base ?>ads/banner-fa-2.jpg" alt="سود 200%" class="mt-4 w-full h-24 object-cover rounded-xl border border-white/10">
            </div>
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-cyan-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-cyan-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-server"></i></div>
                <h3 class="font-bold">🌐 سرور VLESS Reality ضدفیلتر</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">آلمان، فنلاند، ترکیه، ایران‌اکسس. مقاوم‌ترین پروتکل 2024، آپتایم 99.9%، بدون قطعی</p>
                <img src="<?= $base ?>assets/ai_guides/server-status.jpg" class="mt-4 w-full h-28 object-cover rounded-xl border border-white/10">
            </div>
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-amber-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-amber-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-robot"></i></div>
                <h3 class="font-bold">🤖 هوش مصنوعی فارسی با عکس</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">پاسخ خودکار به تیکت با متن + عکس راهنمای 3 مرحله‌ای فارسی. 80% تیکت‌ها کم می‌شود!</p>
                <div class="mt-4 flex gap-2">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step1-copy-link.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step2-import.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                    <img src="<?= $base ?>assets/ai_guides/hiddify-step3-connect.jpg" class="w-1/3 h-16 object-cover rounded-lg border border-white/10">
                </div>
            </div>
            <div class="group bg-white/[0.04] hover:bg-white/[0.06] border border-white/10 hover:border-emerald-500/30 rounded-[1.5rem] p-6 transition">
                <div class="w-12 h-12 bg-indigo-500 rounded-2xl flex items-center justify-center mb-4"><i class="fa-solid fa-users"></i></div>
                <h3 class="font-bold">👥 پلن اختصاصی + ساب‌نماینده فارسی</h3>
                <p class="text-xs text-white/50 mt-2 leading-relaxed">حجم، مدت، سقف اتصال دلخواه بسازید. نماینده زیرمجموعه بگیرید و از فروشش پورسانت بگیرید - پنل کاملاً فارسی</p>
                <img src="<?= $base ?>assets/ai_guides/reseller-panel.jpg" class="mt-4 w-full h-28 object-cover rounded-xl border border-white/10">
            </div>
        </div>
    </div>
</section>

<!-- PRICING -->
<section id="pricing" class="py-20 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl sm:text-4xl font-black">تعرفه شفاف، سود بالا - پرداخت ریالی</h2>
            <p class="text-white/50 mt-3 text-sm">از 299 هزار تومان شروع کنید، هر وقت خواستید ارتقا دهید. بدون قرارداد بلندمدت - پشتیبانی: @mainAdminpanel</p>
            <div class="inline-flex mt-4 bg-amber-500/10 border border-amber-500/30 rounded-full px-4 py-2 text-xs text-amber-300">🎉 جشنواره: 3 ماه بخر، 1 ماه هدیه + 500 هزار تومان شارژ هدیه</div>
        </div>
        <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            <div class="bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-7 flex flex-col">
                <div class="text-xs text-white/40">شروع کسب و کار</div>
                <h3 class="text-xl font-black mt-1">🥉 استارتر</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">299</span><span class="text-sm text-white/50">هزار تومان / ماه</span></div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/70 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 20% تخفیف خرید عمده</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات تلگرام اختصاصی فارسی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پنل مدیریت کامل فارسی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 50 کلاینت</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی: @mainAdminpanel</li>
                </ul>
                <a href="#request" class="mt-6 bg-white/10 hover:bg-white/15 border border-white/20 rounded-xl py-3 text-center text-sm font-bold transition">ثبت درخواست استارتر</a>
            </div>
            <div class="relative bg-gradient-to-b from-violet-600/20 to-violet-600/5 border border-violet-500/40 rounded-[1.8rem] p-7 flex flex-col shadow-2xl shadow-violet-600/10 scale-[1.02]">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-4 py-1 text-[10px] font-bold">⭐ پرفروش‌ترین - پیشنهاد ما</div>
                <div class="text-xs text-violet-300">حرفه‌ای - با اپ اختصاصی فارسی</div>
                <h3 class="text-xl font-black mt-1">🥈 حرفه‌ای</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">599</span><span class="text-sm text-white/50">هزار تومان / ماه</span></div>
                <div class="mt-1 text-[11px] text-white/40 line-through">900 هزار تومان</div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/80 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>35% تخفیف</b> خرید عمده</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ربات + <b>اپ اندروید اختصاصی فارسی</b></li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پلن اختصاصی نامحدود ⭐</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> ساب‌نماینده + پورسانت تیمی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> API اختصاصی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 200 کلاینت + هوش مصنوعی</li>
                </ul>
                <a href="#request" class="mt-6 gradient-bg rounded-xl py-3 text-center text-sm font-bold shadow-lg shadow-violet-600/20 hover:opacity-90 transition">ثبت درخواست حرفه‌ای - 30 دقیقه تحویل</a>
            </div>
            <div class="bg-white/[0.04] border border-white/10 rounded-[1.8rem] p-7 flex flex-col">
                <div class="text-xs text-amber-300">امپراتوری VPN - کامل</div>
                <h3 class="text-xl font-black mt-1">🥇 بیزینس</h3>
                <div class="mt-4 flex items-baseline gap-2"><span class="text-3xl font-black">1.29</span><span class="text-sm text-white/50">میلیون / ماه</span></div>
                <div class="mt-1 text-[11px] text-white/40 line-through">2.5 میلیون</div>
                <ul class="mt-6 space-y-2.5 text-xs text-white/70 flex-1">
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> <b>50% تخفیف</b> - نصف قیمت!</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> همه چیز حرفه‌ای +</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> اپ iOS اختصاصی فارسی</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> دامنه اختصاصی + سایت فروش</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> پشتیبانی VIP @mainAdminpanel</li>
                    <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> کلاینت نامحدود + مشاوره بازاریابی</li>
                </ul>
                <a href="#request" class="mt-6 bg-white/10 hover:bg-white/15 border border-white/20 rounded-xl py-3 text-center text-sm font-bold transition">مشاوره بیزینس - @mainAdminpanel</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-16 px-4">
    <div class="max-w-4xl mx-auto text-center bg-gradient-to-b from-violet-600/20 to-cyan-500/10 border border-violet-500/30 rounded-[2rem] p-10 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-violet-600/20 rounded-full blur-[80px]"></div>
        <h2 class="text-3xl sm:text-4xl font-black leading-tight">آماده‌ای کسب و کارت رو با برند خودت شروع کنی؟</h2>
        <p class="text-white/60 mt-3 text-sm">همین حالا فرم بالا رو پر کن یا مستقیم به @mainAdminpanel پیام بده. 30 دقیقه دیگه پنلت آماده‌ست. جشنواره محدود!</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="#request" class="gradient-bg rounded-2xl px-10 py-4 font-black flex items-center gap-2 shadow-xl shadow-violet-600/20 hover:opacity-90 transition">
                <i class="fa-solid fa-rocket"></i> ثبت درخواست نمایندگی
            </a>
            <a href="https://t.me/mainAdminpanel" target="_blank" class="bg-white/10 border border-white/20 rounded-2xl px-8 py-4 font-bold flex items-center gap-2 hover:bg-white/15 transition">
                <i class="fa-brands fa-telegram text-sky-400 text-xl"></i> @mainAdminpanel
            </a>
        </div>
        <div class="mt-6 text-[11px] text-white/40">🔒 پرداخت امن | ⚡ تحویل آنی 30 دقیقه | 🎁 500 هزار تومان شارژ هدیه + 1 ماه هدیه | کاملاً فارسی</div>
    </div>
</section>

<footer class="border-t border-white/10 py-10 px-4">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between gap-6 text-xs text-white/40">
        <div>
            <div class="flex items-center gap-2 font-black text-white text-sm"><div class="w-7 h-7 gradient-bg rounded-lg flex items-center justify-center">C</div> <?= htmlspecialchars($brand) ?> v5.7.1 - فارسی</div>
            <div class="mt-2 max-w-sm leading-relaxed">قدرتمندترین پنل نمایندگی VPN ایران با اپ اختصاصی فارسی، ربات فروش خودکار و هوش مصنوعی با راهنمای تصویری. بیش از 200 نماینده فعال. پشتیبانی: @mainAdminpanel</div>
            <div class="mt-3 flex gap-2">
                <img src="<?= $base ?>ads/banner-fa-1.jpg" class="w-32 h-20 object-cover rounded-xl border border-white/10">
                <img src="<?= $base ?>ads/banner-fa-2.jpg" class="w-32 h-20 object-cover rounded-xl border border-white/10">
            </div>
        </div>
        <div class="flex gap-8">
            <div>
                <div class="font-bold text-white mb-2">لینک‌ها</div>
                <div class="space-y-1"><a href="#features" class="block hover:text-white">امکانات فارسی</a><a href="#pricing" class="block hover:text-white">تعرفه</a><a href="#request" class="block hover:text-white">درخواست نمایندگی</a></div>
            </div>
            <div>
                <div class="font-bold text-white mb-2">ارتباط فارسی</div>
                <div class="space-y-1"><a href="https://t.me/mainAdminpanel" target="_blank" class="block hover:text-white">تلگرام: @mainAdminpanel</a><span class="block">سایت: https://vpbotn.ir</span><span class="block">پنل: https://vpbotn.ir/contax/</span></div>
            </div>
        </div>
    </div>
    <div class="max-w-7xl mx-auto mt-8 pt-6 border-t border-white/10 text-center text-[11px] text-white/30">© 2025 <?= htmlspecialchars($brand) ?> - همه حقوق محفوظ است. ساخته شده با ❤️ برای نمایندگان ایرانی - پشتیبانی فارسی: @mainAdminpanel - نسخه 5.7.1</div>
</footer>

<div class="fixed bottom-0 left-0 right-0 md:hidden bg-[#0B0F1A]/90 glass border-t border-white/10 p-3 z-40">
    <a href="#request" class="gradient-bg rounded-xl py-3.5 flex items-center justify-center gap-2 font-bold text-sm w-full">
        <i class="fa-solid fa-rocket"></i> ثبت درخواست - @mainAdminpanel
    </a>
</div>

</body>
</html>

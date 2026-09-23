<?php
require_once __DIR__ . '/../../core/Helpers.php';
$flash = Helpers::getFlash();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه مدیریت | Connectix Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-purple-600 selection:text-white relative overflow-hidden">
    <!-- Glow Background Effects -->
    <div class="absolute -top-32 -right-32 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-slate-900/90 border border-slate-800 rounded-2xl shadow-2xl p-8 backdrop-blur-xl relative z-10">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-purple-600 mx-auto flex items-center justify-center text-white shadow-xl shadow-purple-600/30 mb-4">
                <i class="fa-solid fa-bolt-lightning text-3xl"></i>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">کانکتیکس منیجر</h1>
            <p class="text-sm text-slate-400 mt-1">پنل مستقل مدیریت و نمایندگی فروش پروکسی</p>
        </div>

        <?php if ($flash): ?>
            <div class="mb-6 p-4 rounded-xl text-sm font-medium flex items-center gap-3 border <?= $flash['type'] === 'error' ? 'bg-rose-950/60 text-rose-200 border-rose-800' : 'bg-emerald-950/60 text-emerald-200 border-emerald-800' ?>">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-400' : 'fa-circle-check text-emerald-400' ?>"></i>
                <div><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="<?= Helpers::url('login') ?>" method="POST" class="space-y-5">
            <?= Helpers::csrfField() ?>

            <?php if (isset($_GET['step']) && $_GET['step'] === '2fa'): ?>
                <div class="p-3 bg-purple-950/40 border border-purple-800/40 rounded-xl text-xs text-purple-200 text-center">
                    <i class="fa-solid fa-shield-halved text-purple-400 text-lg mb-1 block"></i>
                    <span>ورود دوعاملی برای این حساب فعال است. لطفاً کد ۶ رقمی اپلیکیشن خود را وارد کنید:</span>
                </div>

                <input type="hidden" name="username" value="<?= htmlspecialchars($_SESSION['2fa_pending_username'] ?? '') ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($_SESSION['2fa_pending_password'] ?? '') ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">کد تأیید ۶ رقمی (Authenticator Code)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                            <i class="fa-solid fa-mobile-screen-button text-sm"></i>
                        </span>
                        <input type="text" name="two_factor_code" required autofocus maxlength="6" pattern="[0-9]{6}" placeholder="123456" dir="ltr"
                               class="w-full bg-slate-800/80 border border-purple-500 rounded-xl px-4 py-2.5 pr-10 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 text-center tracking-widest text-lg font-mono">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-purple-900/40 text-sm flex items-center justify-center gap-2">
                    <span>تأیید کد و ورود</span>
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </button>
                <div class="text-center pt-2">
                    <a href="<?= Helpers::url('login') ?>" class="text-[11px] text-slate-400 hover:text-white">بازگشت به فرم ورود عادی</a>
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">نام کاربری</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </span>
                        <input type="text" name="username" required dir="ltr" placeholder="admin / novinvpn"
                               class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 pr-10 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">رمز عبور</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" required dir="ltr" placeholder="••••••••"
                               class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 pr-10 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm font-mono">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-purple-900/40 text-sm flex items-center justify-center gap-2">
                    <span>ورود به پنل کاربری</span>
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </button>
            <?php endif; ?>
        </form>

        <!-- Demo Account Helper Box -->
        <div class="mt-8 pt-6 border-t border-slate-800/80 text-xs text-slate-400 space-y-2">
            <div class="font-bold text-slate-300 flex items-center gap-1.5 mb-1">
                <i class="fa-solid fa-key text-purple-400"></i>
                <span>حساب‌های پیش‌فرض جهت تست سریع:</span>
            </div>
            <div class="flex justify-between items-center bg-slate-800/50 p-2 rounded-lg">
                <span>مدیر ارشد: <code class="text-purple-300 font-mono">admin</code></span>
                <span>رمز: <code class="text-purple-300 font-mono">admin123</code></span>
            </div>
            <div class="flex justify-between items-center bg-slate-800/50 p-2 rounded-lg">
                <span>نماینده نمونه: <code class="text-cyan-300 font-mono">novinvpn</code></span>
                <span>رمز: <code class="text-cyan-300 font-mono">123456</code></span>
            </div>
        </div>
    </div>
</body>
</html>

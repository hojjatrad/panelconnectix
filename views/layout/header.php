<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

$currentUser = Auth::user();
$theme = $currentUser['theme_color'] ?? 'violet';
$themeClasses = [
    'violet' => ['primary' => 'bg-purple-600', 'hover' => 'hover:bg-purple-700', 'text' => 'text-purple-400', 'border' => 'border-purple-500'],
    'blue'   => ['primary' => 'bg-blue-600', 'hover' => 'hover:bg-blue-700', 'text' => 'text-blue-400', 'border' => 'border-blue-500'],
    'green'  => ['primary' => 'bg-emerald-600', 'hover' => 'hover:bg-emerald-700', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500'],
    'orange' => ['primary' => 'bg-amber-600', 'hover' => 'hover:bg-amber-700', 'text' => 'text-amber-400', 'border' => 'border-amber-500'],
    'black'  => ['primary' => 'bg-zinc-800', 'hover' => 'hover:bg-zinc-700', 'text' => 'text-zinc-300', 'border' => 'border-zinc-600'],
];
$t = $themeClasses[$theme] ?? $themeClasses['violet'];

$headerOpenTickets = 0;
try {
    $pdoHeader = Database::getConnection();
    if (Auth::isAdmin()) {
        $headerOpenTickets = (int)$pdoHeader->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'waiting_reseller')")->fetchColumn();
    } elseif (Auth::isReseller()) {
        $headerOpenTickets = (int)$pdoHeader->query("SELECT COUNT(*) FROM tickets WHERE user_id = " . Auth::id() . " AND status = 'answered'")->fetchColumn();
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentUser['brand_name'] ?? APP_NAME) ?> | پنل مدیریت و فروش</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col md:flex-row antialiased selection:bg-purple-600 selection:text-white">

    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 bg-slate-900/90 border-b md:border-b-0 md:border-l border-slate-800 flex flex-col shrink-0 backdrop-blur-md">
        <!-- Brand Header -->
        <div class="h-20 flex items-center px-6 gap-3 border-b border-slate-800/80">
            <?php if (!empty($currentUser['logo_url'])): ?>
                <img src="<?= htmlspecialchars($currentUser['logo_url']) ?>" alt="Logo" class="h-9 max-w-[120px] object-contain rounded">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl <?= $t['primary'] ?> flex items-center justify-center text-white shadow-lg shadow-purple-900/30">
                    <i class="fa-solid fa-bolt-lightning text-lg"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="font-bold text-base text-white tracking-wide truncate max-w-[140px]"><?= htmlspecialchars($currentUser['brand_name'] ?? 'Connectix') ?></h1>
                <span class="text-xs text-slate-400 block"><?= Auth::isAdmin() ? 'مدیر ارشد سیستم' : 'پنل نمایندگی' ?></span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <a href="<?= Helpers::url('dashboard') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'dashboard') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center text-purple-400"></i>
                <span>داشبورد و آمار</span>
            </a>

            <a href="<?= Helpers::url('clients') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'clients') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-users-gear w-5 text-center text-cyan-400"></i>
                <span>مدیریت کلاینت‌ها</span>
            </a>

            <a href="<?= Helpers::url('plans') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'plans') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-box-open w-5 text-center text-amber-400"></i>
                <span>پلن‌ها و تعرفه‌ها</span>
            </a>

            <a href="<?= Helpers::url('categories') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'categories') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-layer-group w-5 text-center text-purple-400"></i>
                <span>دسته‌بندی و خوشه‌ها</span>
            </a>

            <?php if (Auth::isReseller()): ?>
            <div class="pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider px-3.5">بخش اختصاصی نماینده</div>

            <a href="<?= Helpers::url('reseller/orders') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/orders') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-cart-shopping w-5 text-center text-cyan-400"></i>
                <span>سفارشات ربات من</span>
            </a>

            <a href="<?= Helpers::url('reseller/plans') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/plans') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-tags w-5 text-center text-indigo-400"></i>
                <span>تعرفه‌ها و دسته‌بندی من</span>
            </a>

            <a href="<?= Helpers::url('reseller/bot') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/bot') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-brands fa-telegram w-5 text-center text-sky-400"></i>
                <span>ربات تلگرام اختصاصی من</span>
            </a>

            <a href="<?= Helpers::url('reseller/banking') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/banking') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-credit-card w-5 text-center text-emerald-400"></i>
                <span>حساب بانکی و درگاه من</span>
            </a>

            <a href="<?= Helpers::url('reseller/branding') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/branding') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-palette w-5 text-center text-fuchsia-400"></i>
                <span>برندینگ و وایت‌لیبل من</span>
            </a>
            <?php endif; ?>

            <?php if (Auth::isAdmin()): ?>
            <div class="pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider px-3.5">بخش مدیریت کل</div>

            <a href="<?= Helpers::url('servers') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'servers') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-server w-5 text-center text-emerald-400"></i>
                <span>سرورها و نودها</span>
            </a>

            <a href="<?= Helpers::url('categories') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'categories') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-layer-group w-5 text-center text-purple-400"></i>
                <span>دسته‌بندی و خوشه‌ها</span>
            </a>

            <?php
            $headerPendingApps = 0;
            try {
                $dbInst = Database::getConnection();
                $headerPendingApps = (int)$dbInst->query("SELECT COUNT(*) FROM reseller_applications WHERE status = 'pending'")->fetchColumn();
            } catch (Throwable $e) {}
            ?>
            <a href="<?= Helpers::url('resellers') ?>" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= (str_contains($_SERVER['REQUEST_URI'] ?? '', 'resellers') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'reseller/')) ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <span class="flex items-center gap-3">
                    <i class="fa-solid fa-handshake w-5 text-center text-indigo-400"></i>
                    <span>مدیریت نمایندگان</span>
                </span>
                <?php if ($headerPendingApps > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-slate-950 font-mono">
                        <?= $headerPendingApps ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?= Helpers::url('settings/bot') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= (str_contains($_SERVER['REQUEST_URI'] ?? '', 'settings/bot') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'bot-users')) ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-brands fa-telegram w-5 text-center text-cyan-400"></i>
                <span>ربات تلگرام و فروش</span>
            </a>

            <a href="<?= Helpers::url('settings/bot-users') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'bot-users') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-users text-center w-5 text-cyan-300"></i>
                <span>کاربران ربات (Audience)</span>
            </a>

            <a href="<?= Helpers::url('settings/app-guides') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'app-guides') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-mobile-screen-button text-center w-5 text-emerald-400"></i>
                <span>نرم‌افزارها و آموزش‌ها</span>
            </a>

            <a href="<?= Helpers::url('settings/coupons') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'coupons') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-ticket text-center w-5 text-amber-400"></i>
                <span>کدهای تخفیف ربات</span>
            </a>

            <a href="<?= Helpers::url('updater') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'updater') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-brands fa-github w-5 text-center text-purple-400"></i>
                <span>به‌روزرسانی پنل (گیت‌هاب)</span>
            </a>
            <?php endif; ?>

            <div class="pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider px-3.5">مالی و پشتیبانی</div>

            <a href="<?= Helpers::url('tickets') ?>" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'tickets') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <span class="flex items-center gap-3">
                    <i class="fa-solid fa-headset w-5 text-center text-rose-400"></i>
                    <span>تیکت‌ها و پشتیبانی</span>
                </span>
                <?php if ($headerOpenTickets > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white font-mono">
                        <?= $headerOpenTickets ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?= Helpers::url('billing') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'billing') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-wallet w-5 text-center text-rose-400"></i>
                <span>کیف پول و تراکنش‌ها</span>
            </a>

            <a href="<?= Helpers::url('settings/metadata') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'settings') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-palette w-5 text-center text-fuchsia-400"></i>
                <span>شخصی‌سازی و برند</span>
            </a>

            <a href="<?= Helpers::url('notifications') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'notifications') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-bullhorn w-5 text-center text-yellow-400"></i>
                <span>اعلان‌ها و پیام‌ها</span>
            </a>

            <a href="<?= Helpers::url('logs') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'logs') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-shield-halved w-5 text-center text-amber-400"></i>
                <span>لاگ‌های امنیتی و وقایع</span>
            </a>

            <a href="<?= Helpers::url('profile') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-colors hover:bg-slate-800 hover:text-white <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'profile') ? 'bg-slate-800/90 text-white font-semibold shadow-sm' : 'text-slate-400' ?>">
                <i class="fa-solid fa-user-shield w-5 text-center text-teal-400"></i>
                <span>حساب کاربری و کلید API</span>
            </a>
        </nav>

        <!-- User Wallet Badge in Sidebar -->
        <div class="p-4 border-t border-slate-800/80 bg-slate-900/50">
            <div class="bg-slate-800/70 rounded-xl p-3 border border-slate-700/50 mb-3">
                <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                    <span>موجودی کیف پول:</span>
                    <a href="<?= Helpers::url('billing') ?>" class="text-purple-400 hover:underline">+ شارژ</a>
                </div>
                <div class="text-lg font-bold text-emerald-400 flex items-center justify-between">
                    <span><?= Helpers::formatMoney($currentUser['wallet_balance'] ?? 0) ?></span>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <span class="text-xs text-slate-400 truncate max-w-[130px]"><?= htmlspecialchars($currentUser['username'] ?? '') ?></span>
                <a href="<?= Helpers::url('logout') ?>" class="text-xs text-rose-400 hover:text-rose-300 flex items-center gap-1 transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>خروج</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navbar -->
        <header class="h-16 bg-slate-900/60 border-b border-slate-800/80 flex items-center justify-between px-6 shrink-0 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <span class="text-slate-400 text-sm hidden md:inline">اتصال فعال:</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    سیستم متصل و برخط
                </span>
            </div>

            <div class="flex items-center gap-4">
                <a href="<?= Helpers::url('clients/create') ?>" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg <?= $t['primary'] ?> <?= $t['hover'] ?> text-white transition-all shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>ایجاد کاربر جدید</span>
                </a>
            </div>
        </header>

        <!-- Flash Messages Alert -->
        <?php $flash = Helpers::getFlash(); if ($flash): ?>
            <div class="mx-6 mt-4 p-4 rounded-xl text-sm font-medium flex items-center gap-3 shadow-lg border <?= $flash['type'] === 'error' ? 'bg-rose-950/60 text-rose-200 border-rose-800' : ($flash['type'] === 'success' ? 'bg-emerald-950/60 text-emerald-200 border-emerald-800' : 'bg-cyan-950/60 text-cyan-200 border-cyan-800') ?>">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-400' : ($flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-info text-cyan-400') ?> text-lg"></i>
                <div class="flex-1"><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- GitHub New Version Available Banner for Admin -->
        <?php 
        if (Auth::isAdmin() && class_exists('Updater')) {
            $cachedUpdate = Setting::get('update_check_cache');
            $cacheTime = (int)Setting::get('update_check_time', '0');

            // If empty or older than 15 minutes, check in background
            if (empty($cachedUpdate) || (time() - $cacheTime > 900)) {
                $updateObj = Updater::checkForUpdates(false);
            } else {
                $updateObj = json_decode($cachedUpdate, true);
            }

            if (!empty($updateObj['has_update'])):
        ?>
            <div class="mx-6 mt-4 p-4 bg-gradient-to-r from-purple-900/80 via-indigo-900/80 to-slate-900/90 border border-purple-500/50 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-cloud-arrow-down animate-bounce"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">🎉 نگارش جدید پنل در مخزن گیت‌هاب در دسترس است (نسخه <?= htmlspecialchars($updateObj['latest_version']) ?>)</h4>
                        <p class="text-[11px] text-purple-200 mt-0.5"><?= htmlspecialchars($updateObj['release_title'] ?? '') ?> | امکان ارتقای آنی با ۱ کلیک بدون از دست رفتن داده‌ها</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="<?= Helpers::url('updater?autostart=1') ?>" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5">
                        <i class="fa-solid fa-rocket"></i>
                        <span>مشاهده و اعمال به‌روزرسانی (با نوار زنده)</span>
                    </a>
                </div>
            </div>
        <?php 
            endif;
        }
        ?>

        <!-- View Body Container -->
        <div class="p-6 space-y-6">

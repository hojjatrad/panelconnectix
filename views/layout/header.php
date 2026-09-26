<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Database.php';

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
$headerPendingApps = 0;
try {
    $pdoHeader = Database::getConnection();
    if (Auth::isAdmin()) {
        $headerOpenTickets = (int)$pdoHeader->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'waiting_reseller')")->fetchColumn();
        $headerPendingApps = (int)$pdoHeader->query("SELECT COUNT(*) FROM reseller_applications WHERE status = 'pending'")->fetchColumn();
    } elseif (Auth::isReseller()) {
        $headerOpenTickets = (int)$pdoHeader->query("SELECT COUNT(*) FROM tickets WHERE user_id = " . Auth::id() . " AND status = 'answered'")->fetchColumn();
    }
} catch (Throwable $e) {}

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
if (!function_exists('isActiveRoute')) {
    function isActiveRoute(string $route, string $currentUri): bool {
        return str_contains($currentUri, $route);
    }
}
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
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #090d16; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
        .accordion-content { transition: max-height 0.25s ease-in-out, opacity 0.2s ease-in-out; }
        .accordion-content.collapsed { max-height: 0 !important; opacity: 0; pointer-events: none; overflow: hidden; }
        .chevron-icon { transition: transform 0.2s ease; }
        .chevron-icon.rotated { transform: rotate(-90deg); }
        /* Touch & Mobile Modal Scrolling */
        .modal-overlay {
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .modal-box {
            max-height: 88vh !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 768px) {
            div[id*="Modal"].fixed.inset-0 {
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
                padding: 0.75rem !important;
                align-items: flex-start !important;
            }
            div[id*="Modal"] > div {
                max-height: 88vh !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
                margin-top: auto !important;
                margin-bottom: auto !important;
            }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col md:flex-row antialiased selection:bg-purple-600 selection:text-white">

    <!-- Sidebar Navigation (Compact & Collapsible Accordion) -->
    <aside class="w-full md:w-64 bg-slate-900/95 border-b md:border-b-0 md:border-l border-slate-800/90 flex flex-col shrink-0 backdrop-blur-xl z-20">
        
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800/80">
            <div class="flex items-center gap-2.5 min-w-0">
                <?php if (!empty($currentUser['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($currentUser['logo_url']) ?>" alt="Logo" class="h-8 max-w-[100px] object-contain rounded">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-xl <?= $t['primary'] ?> flex items-center justify-center text-white shadow-md shadow-purple-900/30 shrink-0">
                        <i class="fa-solid fa-bolt-lightning text-sm"></i>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h1 class="font-bold text-xs text-white tracking-wide truncate"><?= htmlspecialchars($currentUser['brand_name'] ?? 'Connectix') ?></h1>
                    <span class="text-[10px] text-slate-400 block truncate"><?= Auth::isAdmin() ? 'مدیر کل سامانه' : 'پنل همکار' ?></span>
                </div>
            </div>

            <!-- Mobile collapse toggle button -->
            <button type="button" onclick="document.getElementById('sidebarNav').classList.toggle('hidden')" class="md:hidden p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
                <i class="fa-solid fa-bars text-sm"></i>
            </button>
        </div>

        <!-- Navigation Links Container -->
        <nav id="sidebarNav" class="flex-1 p-2.5 space-y-1.5 overflow-y-auto text-xs hidden md:block">

            <!-- SECTION 1: عملیات اصلی و فروش -->
            <div class="menu-section" data-section="main">
                <button type="button" onclick="toggleMenuSection('main')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-chart-line text-purple-400 text-xs w-4 text-center"></i>
                        <span>عملیات اصلی و فروش</span>
                    </span>
                    <i id="chevron-main" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                </button>
                <div id="content-main" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <a href="<?= Helpers::url('dashboard') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('dashboard', $currentUri) ? 'bg-purple-600/15 text-purple-300 font-bold border-r-2 border-purple-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-chart-pie w-4 text-center text-purple-400"></i>
                        <span>داشبورد و آمار</span>
                    </a>
                    <a href="<?= Helpers::url('clients') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('clients', $currentUri) ? 'bg-purple-600/15 text-purple-300 font-bold border-r-2 border-purple-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-users-gear w-4 text-center text-cyan-400"></i>
                        <span>مدیریت کلاینت‌ها</span>
                    </a>
                    <a href="<?= Helpers::url('plans') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('plans', $currentUri) ? 'bg-purple-600/15 text-purple-300 font-bold border-r-2 border-purple-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-box-open w-4 text-center text-amber-400"></i>
                        <span>پلن‌ها و تعرفه‌ها</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 2: زیرساخت و سرورها (Admin only) -->
            <?php if (Auth::isAdmin()): ?>
            <div class="menu-section" data-section="infra">
                <button type="button" onclick="toggleMenuSection('infra')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-network-wired text-cyan-400 text-xs w-4 text-center"></i>
                        <span>زیرساخت و سرورها</span>
                    </span>
                    <i id="chevron-infra" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                </button>
                <div id="content-infra" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <a href="<?= Helpers::url('servers') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('servers', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-server w-4 text-center text-emerald-400"></i>
                        <span>سرورها و نودها</span>
                    </a>
                    <a href="<?= Helpers::url('categories') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('categories', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-layer-group w-4 text-center text-purple-400"></i>
                        <span>دسته‌بندی و خوشه‌ها</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- SECTION 3: نمایندگان و همکاران -->
            <div class="menu-section" data-section="resellers">
                <button type="button" onclick="toggleMenuSection('resellers')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-handshake-angle text-indigo-400 text-xs w-4 text-center"></i>
                        <span><?= Auth::isAdmin() ? 'مدیریت نمایندگان' : 'بخش اختصاصی همکار' ?></span>
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php if ($headerPendingApps > 0): ?>
                            <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-amber-500 text-slate-950 font-mono"><?= $headerPendingApps ?></span>
                        <?php endif; ?>
                        <i id="chevron-resellers" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                    </div>
                </button>
                <div id="content-resellers" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <?php if (Auth::isAdmin()): ?>
                        <a href="<?= Helpers::url('resellers') ?>" class="flex items-center justify-between px-3 py-1.5 rounded-lg transition <?= (isActiveRoute('resellers', $currentUri) && !isActiveRoute('applications', $currentUri) && !isActiveRoute('clients', $currentUri)) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-users w-4 text-center text-indigo-400"></i>
                                <span>لیست نمایندگان</span>
                            </span>
                        </a>
                        <a href="<?= Helpers::url('resellers/applications') ?>" class="flex items-center justify-between px-3 py-1.5 rounded-lg transition <?= isActiveRoute('applications', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <span class="flex items-center gap-2.5">
                                <i class="fa-solid fa-user-plus w-4 text-center text-amber-400"></i>
                                <span>درخواست‌های همکاری</span>
                            </span>
                            <?php if ($headerPendingApps > 0): ?>
                                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-amber-500 text-slate-950 font-mono"><?= $headerPendingApps ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?= Helpers::url('resellers/clients') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('resellers/clients', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-user-group w-4 text-center text-cyan-400"></i>
                            <span>کاربران نمایندگان</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= Helpers::url('reseller/orders') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/orders', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-cart-shopping w-4 text-center text-cyan-400"></i>
                            <span>سفارشات ربات من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/plans') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/plans', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-tags w-4 text-center text-indigo-400"></i>
                            <span>تعرفه‌ها و دسته‌های من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/bot') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/bot', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-brands fa-telegram w-4 text-center text-sky-400"></i>
                            <span>ربات اختصاصی من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/banking') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/banking', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-credit-card w-4 text-center text-emerald-400"></i>
                            <span>حساب بانکی و درگاه من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/branding') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/branding', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-palette w-4 text-center text-fuchsia-400"></i>
                            <span>برندینگ و لوگو من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/sub-resellers') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/sub-resellers', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-sitemap w-4 text-center text-purple-400"></i>
                            <span>ساب‌نمایندگان و شبکه فروش</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/ai') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/ai', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-robot w-4 text-center text-violet-400"></i>
                            <span>دستیار هوشمند (شارژ)</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECTION 4: ربات و بازاریابی -->
            <div class="menu-section" data-section="bot">
                <button type="button" onclick="toggleMenuSection('bot')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-brands fa-telegram text-sky-400 text-xs w-4 text-center"></i>
                        <span>ربات و بازاریابی</span>
                    </span>
                    <i id="chevron-bot" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                </button>
                <div id="content-bot" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= Helpers::url('settings/bot') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('settings/bot', $currentUri) && !isActiveRoute('bot-users', $currentUri) ? 'bg-sky-600/15 text-sky-300 font-bold border-r-2 border-sky-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-robot w-4 text-center text-sky-400"></i>
                        <span>تنظیمات ربات تلگرام</span>
                    </a>
                    <a href="<?= Helpers::url('settings/bot-users') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('bot-users', $currentUri) ? 'bg-sky-600/15 text-sky-300 font-bold border-r-2 border-sky-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-users-line w-4 text-center text-blue-400"></i>
                        <span>کاربران ربات</span>
                    </a>
                    <a href="<?= Helpers::url('settings/coupons') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('coupons', $currentUri) ? 'bg-sky-600/15 text-sky-300 font-bold border-r-2 border-sky-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-ticket w-4 text-center text-amber-400"></i>
                        <span>کدهای تخفیف</span>
                    </a>
                    <a href="<?= Helpers::url('settings/referrals') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('referrals', $currentUri) ? 'bg-sky-600/15 text-sky-300 font-bold border-r-2 border-sky-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-gift w-4 text-center text-purple-400"></i>
                        <span>سیستم معرف و پورسانت</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= Helpers::url('notifications') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('notifications', $currentUri) ? 'bg-sky-600/15 text-sky-300 font-bold border-r-2 border-sky-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-bullhorn w-4 text-center text-yellow-400"></i>
                        <span>پیام همگانی و اعلان‌ها</span>
                    </a>
                </div>
            </div>

            <!-- SECTION 5: مالی و پشتیبانی -->
            <div class="menu-section" data-section="finance">
                <button type="button" onclick="toggleMenuSection('finance')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-emerald-400 text-xs w-4 text-center"></i>
                        <span>مالی و پشتیبانی</span>
                    </span>
                    <div class="flex items-center gap-1.5">
                        <?php if ($headerOpenTickets > 0): ?>
                            <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-rose-500 text-white font-mono"><?= $headerOpenTickets ?></span>
                        <?php endif; ?>
                        <i id="chevron-finance" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                    </div>
                </button>
                <div id="content-finance" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <a href="<?= Helpers::url('billing') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('billing', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-credit-card w-4 text-center text-emerald-400"></i>
                        <span>کیف پول و تراکنش‌ها</span>
                    </a>
                    <a href="<?= Helpers::url('tickets') ?>" class="flex items-center justify-between px-3 py-1.5 rounded-lg transition <?= isActiveRoute('tickets', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <span class="flex items-center gap-2.5">
                            <i class="fa-solid fa-headset w-4 text-center text-rose-400"></i>
                            <span>تیکت‌ها و پشتیبانی</span>
                        </span>
                        <?php if ($headerOpenTickets > 0): ?>
                            <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-rose-500 text-white font-mono"><?= $headerOpenTickets ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- SECTION 6: سیستم، امنیت و تنظیمات -->
            <div class="menu-section" data-section="system">
                <button type="button" onclick="toggleMenuSection('system')" class="w-full flex items-center justify-between px-2.5 py-1.5 text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800/50 transition-colors font-bold text-[11px]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-sliders text-teal-400 text-xs w-4 text-center"></i>
                        <span>تنظیمات و ابزارها</span>
                    </span>
                    <i id="chevron-system" class="fa-solid fa-chevron-down chevron-icon text-[9px] text-slate-500"></i>
                </button>
                <div id="content-system" class="accordion-content space-y-0.5 mt-0.5 pr-2">
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= Helpers::url('settings/app-api') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('app-api', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-code w-4 text-center text-cyan-400"></i>
                        <span>وب‌سرویس و اپلیکیشن اختصاصی</span>
                    </a>
                    <a href="<?= Helpers::url('settings/app-guides') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('app-guides', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-mobile-screen-button w-4 text-center text-emerald-400"></i>
                        <span>نرم‌افزارها و راهنما</span>
                    </a>
                    <a href="<?= Helpers::url('settings/metadata') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('metadata', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-palette w-4 text-center text-fuchsia-400"></i>
                        <span>برند و قالب</span>
                    </a>
                    <a href="<?= Helpers::url('logs') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('logs', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-shield-halved w-4 text-center text-amber-400"></i>
                        <span>لاگ‌های امنیتی</span>
                    </a>
                    <a href="<?= Helpers::url('settings/ai') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('settings/ai', $currentUri) ? 'bg-violet-600/15 text-violet-300 font-bold border-r-2 border-violet-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-robot w-4 text-center text-violet-400"></i>
                        <span>دستیار هوش مصنوعی</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= Helpers::url('profile') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('profile', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-user-shield w-4 text-center text-teal-400"></i>
                        <span>حساب کاربری و 2FA</span>
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= Helpers::url('updater') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('updater', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-brands fa-github w-4 text-center text-purple-400"></i>
                        <span>به‌روزرسانی پنل</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </nav>

        <!-- User Wallet Badge in Sidebar -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-900/60 text-xs">
            <div class="bg-slate-800/70 rounded-xl p-2.5 border border-slate-700/50 mb-2">
                <div class="flex items-center justify-between text-[11px] text-slate-400 mb-0.5">
                    <span>موجودی کیف پول:</span>
                    <a href="<?= Helpers::url('billing') ?>" class="text-purple-400 hover:underline font-bold">+ شارژ</a>
                </div>
                <div class="text-sm font-bold text-emerald-400 flex items-center justify-between font-mono">
                    <span><?= Helpers::formatMoney($currentUser['wallet_balance'] ?? 0) ?></span>
                </div>
            </div>

            <div class="flex items-center justify-between px-1">
                <span class="text-[11px] text-slate-400 truncate max-w-[120px] font-mono"><?= htmlspecialchars($currentUser['username'] ?? '') ?></span>
                <a href="<?= Helpers::url('logout') ?>" class="text-[11px] text-rose-400 hover:text-rose-300 flex items-center gap-1 transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>خروج</span>
                </a>
            </div>

            <?php
            require_once __DIR__ . '/../../core/Updater.php';
            $panelVersion = Updater::CURRENT_VERSION;
            ?>
            <div class="mt-2 flex items-center justify-center gap-1.5 text-[10px] text-slate-500" title="پنل به‌صورت خودکار از گیت‌هاب به‌روز می‌شود — همه‌ی پنل‌ها (اصلی و نماینده‌ها) همگام‌اند">
                <i class="fa-brands fa-github text-[10px]"></i>
                <span>نسخه پنل</span>
                <span class="font-mono font-bold text-slate-300" dir="ltr">v<?= htmlspecialchars($panelVersion) ?></span>
                <span class="text-emerald-500">●</span>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navbar -->
        <header class="h-14 bg-slate-900/60 border-b border-slate-800/80 flex items-center justify-between px-6 shrink-0 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    سامانه هوشمند متصل و برخط
                </span>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?= Helpers::url('clients/create') ?>" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg <?= $t['primary'] ?> <?= $t['hover'] ?> text-white transition-all shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>کاربر جدید</span>
                </a>
            </div>
        </header>

        <!-- Flash Messages Alert -->
        <?php $flash = Helpers::getFlash(); if ($flash): ?>
            <div class="mx-6 mt-4 p-3.5 rounded-xl text-xs font-medium flex items-center gap-3 shadow-lg border <?= $flash['type'] === 'error' ? 'bg-rose-950/60 text-rose-200 border-rose-800' : ($flash['type'] === 'success' ? 'bg-emerald-950/60 text-emerald-200 border-emerald-800' : 'bg-cyan-950/60 text-cyan-200 border-cyan-800') ?>">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-400' : ($flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-info text-cyan-400') ?> text-base"></i>
                <div class="flex-1"><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- GitHub New Version Available Banner for Admin -->
        <?php 
        if (Auth::isAdmin() && class_exists('Updater')) {
            $cachedUpdate = Setting::get('update_check_cache');
            $cacheTime = (int)Setting::get('update_check_time', '0');

            if (empty($cachedUpdate) || (time() - $cacheTime > 900)) {
                $updateObj = Updater::checkForUpdates(false);
            } else {
                $updateObj = json_decode($cachedUpdate, true);
            }

            if (!empty($updateObj['has_update'])):
        ?>
            <div class="mx-6 mt-4 p-3.5 bg-gradient-to-r from-purple-900/80 via-indigo-900/80 to-slate-900/90 border border-purple-500/50 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xl text-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-cloud-arrow-down animate-bounce"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-white">🎉 نگارش جدید در گیت‌هاب منتشر شد (نسخه <?= htmlspecialchars($updateObj['latest_version']) ?>)</h4>
                        <p class="text-[11px] text-purple-200 mt-0.5"><?= htmlspecialchars($updateObj['release_title'] ?? '') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="<?= Helpers::url('updater?autostart=1') ?>" class="px-3.5 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl shadow-md transition flex items-center gap-1.5">
                        <i class="fa-solid fa-rocket"></i>
                        <span>ارتقای خودکار</span>
                    </a>
                </div>
            </div>
        <?php 
            endif;
        }
        ?>

        <!-- View Body Container -->
        <div class="p-5 space-y-5">

    <script>
    // Accordion Logic with localStorage Persistence & Auto-Expand for Active Route
    function toggleMenuSection(sectionId) {
        const content = document.getElementById('content-' + sectionId);
        const chevron = document.getElementById('chevron-' + sectionId);
        if (!content) return;

        const isCollapsed = content.classList.contains('collapsed');
        if (isCollapsed) {
            content.classList.remove('collapsed');
            if (chevron) chevron.classList.remove('rotated');
            localStorage.setItem('menu_' + sectionId, 'open');
        } else {
            content.classList.add('collapsed');
            if (chevron) chevron.classList.add('rotated');
            localStorage.setItem('menu_' + sectionId, 'closed');
        }
    }

    // Initialize state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sections = ['main', 'infra', 'resellers', 'bot', 'finance', 'system'];
        sections.forEach(secId => {
            const content = document.getElementById('content-' + secId);
            const chevron = document.getElementById('chevron-' + secId);
            if (!content) return;

            // Check if active route is inside this section
            const hasActiveLink = content.querySelector('.font-bold.border-r-2') !== null;
            if (hasActiveLink) {
                // Always open active section
                content.classList.remove('collapsed');
                if (chevron) chevron.classList.remove('rotated');
            } else {
                const savedState = localStorage.getItem('menu_' + secId);
                if (savedState === 'closed') {
                    content.classList.add('collapsed');
                    if (chevron) chevron.classList.add('rotated');
                }
            }
        });
    });
    </script>

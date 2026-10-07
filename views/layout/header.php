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
    <!-- v3.5.8 SAFE: Local bundled assets for Iran (no CDN) - fallback to CDN if local missing -->
    <!-- To revert: restore from backups/20260929-panel-optimizations/header.php.backup -->
    <?php
    $base = Helpers::basePath();
    $localTailwind = __DIR__ . '/../../assets/js/tailwind.js';
    $localFA = __DIR__ . '/../../assets/css/fontawesome.min.css';
    $localVazir = __DIR__ . '/../../assets/css/vazirmatn.css';
    $localChart = __DIR__ . '/../../assets/js/chart.min.js';
    $localCompiled = __DIR__ . '/../../assets/css/tailwind-compiled.css';
    ?>
    <!-- O6: Preload dashboard stats via AJAX for 0.3s initial load -->
    <script>
    window.ConnectixStats = {
        load: function() {
            fetch('<?= Helpers::url('api/dashboard_stats') ?>'.replace('/api/', '/api/dashboard_stats.php?').replace('api/dashboard_stats', 'api/dashboard_stats.php'), {credentials: 'same-origin'})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Update overview cards if they exist
                        const els = {
                            'today_sales': data.overview.today_sales,
                            'today_revenue': data.overview.today_revenue,
                            'active_clients': data.overview.active_clients,
                            'expiring_soon': data.overview.expiring_soon
                        };
                        for (const [k,v] of Object.entries(els)) {
                            const el = document.getElementById('stat-'+k);
                            if (el) el.textContent = v;
                        }
                        console.log('Dashboard stats loaded in', data.overview ? 'fast' : 'slow', data.generated_at);
                    }
                })
                .catch(e => console.log('Stats load failed', e));
        }
    };
    // Auto load on dashboard
    if (window.location.href.includes('dashboard')) {
        document.addEventListener('DOMContentLoaded', () => setTimeout(window.ConnectixStats.load, 300));
    }
    </script>
    <?php if (file_exists($localTailwind)): ?>
    <script src="<?= $base ?>/assets/js/tailwind.js"></script>
    <?php else: ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php endif; ?>
    <?php if (file_exists($localChart)): ?>
    <script src="<?= $base ?>/assets/js/chart.min.js"></script>
    <?php else: ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <?php if (file_exists($localFA)): ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/fontawesome.min.css">
    <?php else: ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>
    <?php if (file_exists($localVazir)): ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/vazirmatn.css">
    <?php else: ?>
    <style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');</style>
    <?php endif; ?>
    <link rel="manifest" href="<?= $base ?>/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Connectix ULTRA">
    <link rel="apple-touch-icon" href="<?= $base ?>/assets/img/icon-192.png">
    <style>
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
                    <a href="<?= Helpers::url('backups') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('backups', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-box-archive w-4 text-center text-cyan-400"></i>
                        <span>بکاپ‌های حرفه‌ای</span>
                        <span class="mr-auto text-[9px] bg-cyan-500/20 text-cyan-300 px-1.5 py-0.5 rounded-full border border-cyan-500/30">PRO</span>
                    </a>
                    <a href="<?= Helpers::url('monitoring') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('monitoring', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-heart-pulse w-4 text-center text-emerald-400"></i>
                        <span>مانیتورینگ زنده</span>
                        <span class="mr-auto text-[9px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded-full border border-emerald-500/30">LIVE</span>
                    </a>
                    <a href="<?= Helpers::url('settings/sublink-domains') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('sublink-domains', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-globe w-4 text-center text-cyan-400"></i>
                        <span>دامنه چرخشی</span>
                        <span class="mr-auto text-[9px] bg-cyan-500/20 text-cyan-300 px-1.5 py-0.5 rounded-full border border-cyan-500/30">ANTI-FILTER</span>
                    </a>
                    <a href="<?= Helpers::url('settings/domain-migration') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('domain-migration', $currentUri) ? 'bg-violet-600/15 text-violet-300 font-bold border-r-2 border-violet-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-right-left w-4 text-center text-violet-400"></i>
                        <span>مهاجرت دامنه</span>
                        <span class="mr-auto text-[9px] bg-violet-500/20 text-violet-300 px-1.5 py-0.5 rounded-full border border-violet-500/30">AUTO</span>
                    </a>
                    <a href="<?= Helpers::url('proxies') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('proxies', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-shield-halved w-4 text-center text-cyan-400"></i>
                        <span>پروکسی‌ها</span>
                        <span class="mr-auto text-[9px] bg-cyan-500/20 text-cyan-300 px-1.5 py-0.5 rounded-full border border-cyan-500/30">FREE</span>
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
                        <a href="<?= Helpers::url('resellers/invoice') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('resellers/invoice', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-file-invoice-dollar w-4 text-center text-emerald-400"></i>
                            <span>فاکتور ماهانه همکاران</span>
                        </a>
                        <a href="<?= Helpers::url('resellers/points') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('resellers/points', $currentUri) ? 'bg-amber-600/15 text-amber-300 font-bold border-r-2 border-amber-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-trophy w-4 text-center text-amber-400"></i>
                            <span>امتیاز و وفاداری</span>
                            <span class="mr-auto text-[9px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded-full border border-amber-500/30">LOYALTY</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= Helpers::url('reseller/orders') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/orders', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-cart-shopping w-4 text-center text-cyan-400"></i>
                            <span>سفارشات ربات من</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/invoice') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/invoice', $currentUri) ? 'bg-indigo-600/15 text-indigo-300 font-bold border-r-2 border-indigo-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-file-invoice-dollar w-4 text-center text-emerald-400"></i>
                            <span>صورت‌حساب و فاکتور ماهانه من</span>
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
                        <a href="<?= Helpers::url('reseller/monitoring') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/monitoring', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-heart-pulse w-4 text-center text-emerald-400"></i>
                            <span>مانیتورینگ LIVE</span>
                            <span class="mr-auto w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                        </a>
                        <a href="<?= Helpers::url('reseller/financial') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/financial', $currentUri) ? 'bg-amber-600/15 text-amber-300 font-bold border-r-2 border-amber-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-chart-line w-4 text-center text-amber-400"></i>
                            <span>گزارش مالی ULTRA</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/usage') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/usage', $currentUri) ? 'bg-cyan-600/15 text-cyan-300 font-bold border-r-2 border-cyan-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-chart-area w-4 text-center text-cyan-400"></i>
                            <span>مصرف و تاریخچه</span>
                        </a>
                        <a href="<?= Helpers::url('reseller/points') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('reseller/points', $currentUri) ? 'bg-amber-600/15 text-amber-300 font-bold border-r-2 border-amber-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                            <i class="fa-solid fa-trophy w-4 text-center text-amber-400"></i>
                            <span>امتیاز و جایزه من</span>
                            <span class="mr-auto text-[9px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded-full border border-amber-500/30">LOYALTY</span>
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
                    <a href="<?= Helpers::url('financial') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('financial', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-chart-pie w-4 text-center text-violet-400"></i>
                        <span>گزارش مالی پیشرفته</span>
                        <span class="mr-auto text-[9px] bg-violet-500/20 text-violet-300 px-1.5 py-0.5 rounded-full border border-violet-500/30">ULTRA</span>
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= Helpers::url('settings/bank-verification') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('bank-verification', $currentUri) ? 'bg-emerald-600/15 text-emerald-300 font-bold border-r-2 border-emerald-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-building-columns w-4 text-center text-emerald-400"></i>
                        <span>تایید خودکار بانکی 🤖</span>
                    </a>
                    <?php endif; ?>
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
                    <a href="<?= Helpers::url('settings/api-tokens') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('api-tokens', $currentUri) ? 'bg-amber-600/15 text-amber-300 font-bold border-r-2 border-amber-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-key w-4 text-center text-amber-400"></i>
                        <span>توکن API (خودکارسازی)</span>
                        <span class="mr-auto text-[9px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded-full border border-amber-500/30">AUTO</span>
                    </a>
                    <a href="<?= Helpers::url('settings/switch_root') ?>" class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition <?= isActiveRoute('switch_root', $currentUri) ? 'bg-teal-600/15 text-teal-300 font-bold border-r-2 border-teal-500' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-shuffle w-4 text-center text-cyan-400"></i>
                        <span>صفحه اصلی دامنه</span>
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
        <!-- Top Navbar ULTRA v7.0 -->
        <header class="h-14 bg-slate-900/70 border-b border-slate-800/80 flex items-center justify-between px-6 shrink-0 backdrop-blur-xl sticky top-0 z-30">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    سامانه هوشمند ULTRA v7.0
                </span>
                <div class="hidden md:flex items-center gap-2 text-[11px] text-slate-500">
                    <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                    <span id="liveClock" class="font-mono">--:--</span>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Theme Toggle -->
                <button onclick="toggleTheme()" id="themeToggle" class="w-9 h-9 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl flex items-center justify-center text-slate-400 hover:text-white transition" title="تغییر تم">
                    <i class="fa-solid fa-moon text-xs" id="themeIcon"></i>
                </button>
                <!-- Search Quick -->
                <button onclick="document.getElementById('quickSearchModal').classList.remove('hidden')" class="w-9 h-9 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl flex items-center justify-center text-slate-400 hover:text-white transition" title="جستجوی سریع (Ctrl+K)">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </button>
                <!-- Notifications Bell -->
                <a href="<?= Helpers::url('notifications') ?>" class="relative w-9 h-9 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl flex items-center justify-center text-slate-400 hover:text-white transition" title="اعلان‌ها">
                    <i class="fa-solid fa-bell text-xs"></i>
                    <?php if (($headerOpenTickets ?? 0) + ($headerPendingApps ?? 0) > 0): ?>
                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-rose-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-slate-900"><?= ($headerOpenTickets ?? 0) + ($headerPendingApps ?? 0) ?></span>
                    <?php endif; ?>
                </a>
                <!-- Monitoring LIVE -->
                <a href="<?= Helpers::url('monitoring') ?>" class="hidden md:flex items-center gap-1.5 px-3 py-1.5 bg-emerald-950/30 hover:bg-emerald-900/40 text-emerald-300 border border-emerald-800/30 rounded-xl text-[11px] font-bold transition">
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                    LIVE
                </a>
                <a href="<?= Helpers::url('clients/create') ?>" class="px-4 py-2 text-xs font-black rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white shadow-lg shadow-violet-900/20 flex items-center gap-1.5 transition-all">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>کاربر جدید</span>
                </a>
            </div>
        </header>

        <!-- Quick Search Modal ULTRA -->
        <div id="quickSearchModal" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[100] flex items-start justify-center pt-[20vh] p-4" onclick="if(event.target===this) this.classList.add('hidden')">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-4 border-b border-slate-800 flex items-center gap-3">
                    <i class="fa-solid fa-magnifying-glass text-slate-500"></i>
                    <input type="text" id="quickSearchInput" placeholder="جستجوی کلاینت، سرور، نماینده... (نام کاربری)" class="flex-1 bg-transparent text-white text-sm outline-none placeholder:text-slate-500" autofocus onkeyup="quickSearch(this.value)">
                    <button onclick="document.getElementById('quickSearchModal').classList.add('hidden')" class="w-7 h-7 bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-xs"></i></button>
                </div>
                <div id="quickSearchResults" class="max-h-80 overflow-y-auto p-2 text-xs text-slate-400 text-center py-8">برای جستجو تایپ کنید...</div>
            </div>
        </div>
        <script>
        // Theme toggle ULTRA v7.1
        function toggleTheme(){
            const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('bg-slate-950');
            const newTheme = isDark ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            document.getElementById('themeIcon').className = newTheme==='dark' ? 'fa-solid fa-moon text-xs' : 'fa-solid fa-sun text-xs';
            // For now just toggle class on html and show toast - full light theme needs Tailwind config
            if(newTheme==='light'){
                document.body.classList.remove('bg-slate-950','text-slate-100');
                document.body.classList.add('bg-slate-50','text-slate-900');
                document.querySelectorAll('.glass-card, .glass').forEach(el=>{
                    el.style.background='rgba(255,255,255,0.9)';
                    el.style.borderColor='rgba(0,0,0,0.08)';
                });
            } else {
                document.body.classList.add('bg-slate-950','text-slate-100');
                document.body.classList.remove('bg-slate-50','text-slate-900');
                document.querySelectorAll('.glass-card, .glass').forEach(el=>{
                    el.style.background='';
                    el.style.borderColor='';
                });
            }
        }
        // Init theme
        (function(){ const saved=localStorage.getItem('theme')||'dark'; document.getElementById('themeIcon').className = saved==='dark' ? 'fa-solid fa-moon text-xs' : 'fa-solid fa-sun text-xs'; })();

        // Live clock
        setInterval(()=>{ const el=document.getElementById('liveClock'); if(el){ const now=new Date(); el.textContent=now.toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit'}); } },1000);
        // Ctrl+K quick search
        document.addEventListener('keydown', (e)=>{ if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); document.getElementById('quickSearchModal').classList.remove('hidden'); document.getElementById('quickSearchInput').focus(); } if(e.key==='Escape'){ document.getElementById('quickSearchModal').classList.add('hidden'); } });
        function quickSearch(q){
            if(q.length<2){ document.getElementById('quickSearchResults').innerHTML='<div class="py-8 text-slate-500">حداقل 2 حرف...</div>'; return; }
            document.getElementById('quickSearchResults').innerHTML='<div class="py-4"><i class="fa-solid fa-spinner fa-spin"></i> جستجو...</div>';
            fetch('<?= Helpers::url('clients') ?>?search='+encodeURIComponent(q)+'&ajax=1').then(r=>r.text()).then(html=>{
                document.getElementById('quickSearchResults').innerHTML='<div class="p-3 bg-slate-800/50 rounded-xl"><a href="<?= Helpers::url('clients') ?>?search='+encodeURIComponent(q)+'" class="text-violet-400 hover:text-violet-300 font-bold">🔍 مشاهده نتایج کامل برای "'+q+'" در صفحه کلاینت‌ها →</a></div>';
            }).catch(()=>{ document.getElementById('quickSearchResults').innerHTML='<div class="py-4 text-rose-400">خطا در جستجو</div>'; });
        }
        </script>

        <!-- Flash Messages Alert -->
        <?php $flash = Helpers::getFlash(); if ($flash): ?>
            <div class="mx-6 mt-4 p-3.5 rounded-xl text-xs font-medium flex items-center gap-3 shadow-lg border <?= $flash['type'] === 'error' ? 'bg-rose-950/60 text-rose-200 border-rose-800' : ($flash['type'] === 'success' ? 'bg-emerald-950/60 text-emerald-200 border-emerald-800' : 'bg-cyan-950/60 text-cyan-200 border-cyan-800') ?>">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-400' : ($flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-info text-cyan-400') ?> text-base"></i>
                <div class="flex-1"><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- GitHub New Version Available Banner for Admin - v6.8.3: Never show commit-xxxx -->
        <?php 
        if (Auth::isAdmin() && class_exists('Updater')) {
            $cachedUpdate = Setting::get('update_check_cache');
            $cacheTime = (int)Setting::get('update_check_time', '0');

            if (empty($cachedUpdate) || (time() - $cacheTime > 900)) {
                $updateObj = Updater::checkForUpdates(false);
            } else {
                $updateObj = json_decode($cachedUpdate, true);
                // v6.8.3: If cached contains commit-xxxx, force refresh and sanitize
                if (is_array($updateObj)) {
                    $lv = $updateObj['latest_version'] ?? '';
                    if (is_string($lv) && (str_starts_with($lv, 'commit-') || preg_match('/^[0-9a-f]{7,40}$/i', $lv))) {
                        Setting::set('update_check_cache', '');
                        Setting::set('update_check_time', '0');
                        $updateObj = Updater::checkForUpdates(true);
                    }
                }
            }

            // v4.0.26 FINAL FOREVER: Never show banner if versions same (even if has_update true from old cache)
            if (!empty($updateObj['has_update'])) {
                $lvCheck = $updateObj['latest_version'] ?? '';
                $cvCheck = $updateObj['current_version'] ?? '';
                if (!empty($lvCheck) && !empty($cvCheck) && trim($lvCheck) === trim($cvCheck)) {
                    $updateObj['has_update'] = false;
                }
                // Also if version_compare says same, no banner
                if (!empty($lvCheck) && !empty($cvCheck) && version_compare(trim($lvCheck), trim($cvCheck), '<=')) {
                    // Only allow banner if latest > current
                    if (version_compare(trim($lvCheck), trim($cvCheck), '<=') ) {
                        // Check if latest is actually newer
                        if (trim($lvCheck) === trim($cvCheck)) {
                            $updateObj['has_update'] = false;
                        }
                    }
                }
            }

            if (!empty($updateObj['has_update'])):
                // Sanitize version for display - NEVER show commit-xxxx
                $displayVer = $updateObj['latest_version'] ?? Updater::CURRENT_VERSION;
                if (is_string($displayVer) && (str_starts_with($displayVer, 'commit-') || preg_match('/^[0-9a-f]{7,40}$/i', $displayVer))) {
                    $displayVer = $updateObj['current_version'] ?? Updater::CURRENT_VERSION;
                    $displayVer = ltrim($displayVer, 'vV');
                    if (str_starts_with($displayVer, 'commit-')) $displayVer = Updater::CURRENT_VERSION;
                }
                $displayVer = ltrim((string)$displayVer, 'vV');
                if (empty($displayVer) || $displayVer === 'commit') $displayVer = Updater::CURRENT_VERSION;
        ?>
            <div class="mx-6 mt-4 p-3.5 bg-gradient-to-r from-purple-900/80 via-indigo-900/80 to-slate-900/90 border border-purple-500/50 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xl text-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-cloud-arrow-down animate-bounce"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-white">🎉 نگارش جدید Connectix v<?= htmlspecialchars($displayVer) ?> منتشر شد</h4>
                        <p class="text-[11px] text-purple-200 mt-0.5"><?= htmlspecialchars($updateObj['release_title'] ?? "نسخه جدید Connectix v{$displayVer} در دسترس است") ?></p>
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

    // PWA Service Worker registration v7.0 ULTRA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= $base ?>/sw.js').then(()=>console.log('PWA SW registered')).catch(()=>{});
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

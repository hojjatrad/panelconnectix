<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($brandName) ?> - Mini App</title>
    <!-- Tailwind CSS (Offline-friendly CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: local('Vazirmatn'), local('Tahoma'), sans-serif;
        }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background-color: var(--tg-theme-bg-color, #090d16);
            color: var(--tg-theme-text-color, #f8fafc);
            -webkit-tap-highlight-color: transparent;
        }
        .gauge-circle {
            transition: stroke-dashoffset 1s ease-in-out;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between select-none pb-16">

    <!-- Top Navigation Bar -->
    <header class="p-4 bg-slate-900/90 backdrop-blur border-b border-slate-800/80 sticky top-0 z-30 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-8 w-auto object-contain rounded-lg">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white text-xs font-bold shadow-md shadow-purple-600/30">
                    <i class="fa-solid fa-bolt"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-sm font-extrabold text-white tracking-wide"><?= htmlspecialchars($brandName) ?></h1>
                <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>سرورهای ابری متصل</span>
                </span>
            </div>
        </div>

        <div class="flex items-center gap-1.5 text-xs">
            <span id="userName" class="font-bold text-slate-300"></span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 p-4 max-w-md mx-auto w-full space-y-4">

        <!-- TAB 1: Subscription (Default) -->
        <div id="tabSubscriptions" class="space-y-4">
            <?php if (empty($clientAccounts)): ?>
                <!-- No Account State -->
                <div class="p-6 bg-slate-900/80 border border-slate-800/90 rounded-3xl text-center space-y-3 shadow-xl">
                    <div class="w-14 h-14 bg-purple-500/10 border border-purple-500/20 text-purple-400 rounded-2xl flex items-center justify-center mx-auto text-2xl">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 class="text-sm font-bold text-white">اشتراک فعالی یافت نشد</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        جهت شروع اتصال به اینترنت آزاد و پرسرعت، می‌توانید همین حالا یکی از پلن‌های اقتصادی یا VIP ما را تهیه فرمایید.
                    </p>
                    <button onclick="switchTab('tabPlans')" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl text-xs shadow-lg shadow-purple-600/30 transition active:scale-95">
                        <i class="fa-solid fa-cart-shopping ml-1"></i>
                        <span>مشاهده پلن‌ها و خرید آنی</span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Account Card -->
                <?php 
                $acc = $clientAccounts[0]; 
                $usedGb = round($acc['traffic_used_bytes'] / (1024 * 1024 * 1024), 2);
                $totalGb = round($acc['traffic_limit_bytes'] / (1024 * 1024 * 1024), 1);
                $pct = ($totalGb > 0) ? min(100, round(($usedGb / $totalGb) * 100)) : 0;
                $radius = 42;
                $circumference = 2 * M_PI * $radius;
                $dashoffset = $circumference - ($pct / 100) * $circumference;
                $days = Helpers::daysRemaining($acc['expire_at']);
                $subUrl = Helpers::subUrl($acc['sub_token']);
                ?>
                <div class="p-5 bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-3xl shadow-xl space-y-4 relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-purple-600/10 rounded-full blur-2xl"></div>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-medium">سرویس فعال شما</span>
                            <strong class="text-sm font-extrabold text-white font-mono"><?= htmlspecialchars($acc['username']) ?></strong>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span><?= htmlspecialchars($acc['status']) ?></span>
                        </span>
                    </div>

                    <!-- Circular Progress Gauge -->
                    <div class="flex items-center justify-center py-2">
                        <div class="relative w-36 h-36 flex items-center justify-center">
                            <svg class="w-full h-full" viewBox="0 0 100 100">
                                <!-- Background Circle -->
                                <circle cx="50" cy="50" r="<?= $radius ?>" stroke="#1e293b" stroke-width="8" fill="transparent"/>
                                <!-- Animated Progress Circle -->
                                <circle cx="50" cy="50" r="<?= $radius ?>" stroke="<?= $pct > 85 ? '#f43f5e' : ($pct > 65 ? '#f59e0b' : '#a855f7') ?>" stroke-width="8" stroke-linecap="round" fill="transparent"
                                        stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $dashoffset ?>" class="gauge-circle"/>
                            </svg>
                            <div class="absolute flex flex-col items-center justify-center text-center">
                                <span class="text-xl font-extrabold text-white font-mono"><?= $pct ?>%</span>
                                <span class="text-[9px] text-slate-400">مصرف شده</span>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Details -->
                    <div class="grid grid-cols-2 gap-2 text-center text-xs">
                        <div class="p-2.5 bg-slate-900/90 rounded-2xl border border-slate-800/80">
                            <span class="text-[10px] text-slate-400 block mb-0.5">حجم باقیمانده</span>
                            <strong class="text-white font-mono text-xs font-bold"><?= max(0, round($totalGb - $usedGb, 2)) ?> GB</strong>
                        </div>
                        <div class="p-2.5 bg-slate-900/90 rounded-2xl border border-slate-800/80">
                            <span class="text-[10px] text-slate-400 block mb-0.5">اعتبار زمانی</span>
                            <strong class="text-amber-400 font-bold text-xs"><?= $days ?></strong>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-1">
                        <button onclick="copySublink('<?= $subUrl ?>')" class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-md shadow-purple-600/30 active:scale-95 transition">
                            <i class="fa-solid fa-copy"></i>
                            <span>کپی لینک اتصال</span>
                        </button>
                        <button onclick="openQrModal('<?= $subUrl ?>')" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold border border-slate-700 flex items-center justify-center transition active:scale-95">
                            <i class="fa-solid fa-qrcode text-base"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Plans Store -->
        <div id="tabPlans" class="hidden space-y-3">
            <div class="text-xs font-bold text-slate-300 flex items-center justify-between mb-1">
                <span>🛒 پلن‌های پرسرعت و بدون قطعی</span>
                <span class="text-[10px] text-purple-400 font-normal">تحویل آنی ۲۴/۷</span>
            </div>

            <div class="space-y-2.5">
                <?php foreach ($plans as $p): ?>
                    <div class="p-4 bg-slate-900/90 border border-slate-800/90 rounded-2xl flex items-center justify-between gap-3 shadow-md hover:border-purple-500/40 transition">
                        <div>
                            <strong class="text-xs font-bold text-white block mb-0.5"><?= htmlspecialchars($p['title']) ?></strong>
                            <div class="flex items-center gap-2 text-[10px] text-slate-400">
                                <span><i class="fa-solid fa-cloud text-purple-400"></i> <?= $p['traffic_gb'] ?> GB</span>
                                <span>•</span>
                                <span><i class="fa-solid fa-clock text-cyan-400"></i> <?= $p['duration_days'] ?> روزه</span>
                            </div>
                        </div>

                        <div class="text-left shrink-0">
                            <span class="text-xs font-extrabold text-emerald-400 block font-mono"><?= number_format($p['base_price']) ?> تومان</span>
                            <button onclick="orderPlan(<?= $p['id'] ?>)" class="mt-1 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white text-[10px] font-bold rounded-lg transition active:scale-95">
                                خرید پلن
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAB 3: Apps & Setup -->
        <div id="tabApps" class="hidden space-y-3">
            <div class="text-xs font-bold text-slate-300 mb-2">📱 انتخاب سیستم‌عامل جهت دانلود نرم‌افزار</div>
            
            <div class="grid grid-cols-2 gap-2 text-xs">
                <a href="https://github.com/2dust/v2rayNG/releases" target="_blank" class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col items-center justify-center text-center gap-2 hover:border-emerald-500/40 transition">
                    <i class="fa-brands fa-android text-2xl text-emerald-400"></i>
                    <strong class="text-white text-xs">اندروید</strong>
                    <span class="text-[9px] text-slate-400">v2rayNG / Hiddify</span>
                </a>
                <a href="https://apps.apple.com/us/app/streisand/id6450534064" target="_blank" class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col items-center justify-center text-center gap-2 hover:border-slate-400/40 transition">
                    <i class="fa-brands fa-apple text-2xl text-slate-200"></i>
                    <strong class="text-white text-xs">آیفون و آیپد</strong>
                    <span class="text-[9px] text-slate-400">Streisand / FoXray</span>
                </a>
                <a href="https://github.com/2dust/v2rayN/releases" target="_blank" class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col items-center justify-center text-center gap-2 hover:border-sky-500/40 transition">
                    <i class="fa-brands fa-windows text-2xl text-sky-400"></i>
                    <strong class="text-white text-xs">ویندوز</strong>
                    <span class="text-[9px] text-slate-400">v2rayN / Nekoray</span>
                </a>
                <a href="https://apps.apple.com/us/app/streisand/id6450534064" target="_blank" class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col items-center justify-center text-center gap-2 hover:border-indigo-500/40 transition">
                    <i class="fa-solid fa-laptop text-2xl text-indigo-400"></i>
                    <strong class="text-white text-xs">مک‌بوک</strong>
                    <span class="text-[9px] text-slate-400">Streisand / V2Box</span>
                </a>
            </div>
        </div>

        <!-- TAB 4: Lucky Wheel -->
        <?php if (Setting::get('btn_wheel_enabled', '1') === '1'): ?>
        <div id="tabWheel" class="hidden space-y-4 text-center">
            <div class="p-5 bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-3xl shadow-xl space-y-4">
                <h3 class="text-sm font-extrabold text-white flex items-center justify-center gap-2">
                    <i class="fa-solid fa-gift text-amber-400 text-lg"></i>
                    <span>گردونه شانس و هدیه ۲۴ ساعته</span>
                </h3>
                <p class="text-xs text-slate-400">روزانه یک بار گردونه را بچرخانید و شارژ رایگان کیف‌پول یا کدهای تخفیف ویژه برنده شوید!</p>

                <!-- Wheel Visual Container -->
                <div class="relative w-48 h-48 mx-auto my-4 flex items-center justify-center">
                    <div id="wheelGraphic" class="w-full h-full rounded-full border-4 border-amber-400/40 shadow-2xl shadow-amber-500/20 bg-gradient-to-tr from-purple-900 via-slate-900 to-indigo-900 flex items-center justify-center relative overflow-hidden transition-all duration-[4000ms] ease-out">
                        <div class="text-center space-y-1">
                            <i class="fa-solid fa-crown text-3xl text-amber-400 animate-bounce"></i>
                            <span class="text-[10px] text-white font-extrabold block">LUCKY SPIN</span>
                        </div>
                    </div>
                    <!-- Pointer Indicator -->
                    <div class="absolute -top-3 inset-x-0 mx-auto w-0 h-0 border-l-[10px] border-l-transparent border-r-[10px] border-r-transparent border-t-[16px] border-t-amber-400 z-10"></div>
                </div>

                <div id="wheelStatus" class="text-xs font-bold text-amber-300 min-h-[24px]"></div>

                <button id="btnSpin" onclick="spinWheel()" class="w-full py-3.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-black rounded-xl text-xs shadow-lg shadow-amber-500/30 transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-play"></i>
                    <span>چرخاندن گردونه شانس</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 inset-x-0 bg-slate-950/95 backdrop-blur border-t border-slate-800/80 px-2 py-2 z-30 flex items-center justify-around text-[10px]">
        <button onclick="switchTab('tabSubscriptions')" id="navSubscriptions" class="nav-btn flex flex-col items-center text-purple-400 font-bold gap-1 transition">
            <i class="fa-solid fa-bolt text-base"></i>
            <span>اشتراک من</span>
        </button>
        <button onclick="switchTab('tabPlans')" id="navPlans" class="nav-btn flex flex-col items-center text-slate-400 gap-1 transition">
            <i class="fa-solid fa-store text-base"></i>
            <span>فروشگاه پلن</span>
        </button>
        <?php if (Setting::get('btn_wheel_enabled', '1') === '1'): ?>
        <button onclick="switchTab('tabWheel')" id="navWheel" class="nav-btn flex flex-col items-center text-slate-400 gap-1 transition">
            <i class="fa-solid fa-gift text-base text-amber-400"></i>
            <span>گردونه شانس</span>
        </button>
        <?php endif; ?>
        <button onclick="switchTab('tabApps')" id="navApps" class="nav-btn flex flex-col items-center text-slate-400 gap-1 transition">
            <i class="fa-solid fa-download text-base"></i>
            <span>نرم‌افزارها</span>
        </button>
    </nav>

    <!-- QR Code Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-xs w-full text-center space-y-4">
            <h4 class="text-xs font-bold text-white">اسکن بارکد QR اتصال</h4>
            <div class="bg-white p-3 rounded-2xl inline-block shadow-inner">
                <img id="qrImage" src="" alt="QR Code" class="w-48 h-48 mx-auto">
            </div>
            <p class="text-[10px] text-slate-400">کافیست در نرم‌افزار خود علامت + و سپس اسکن بارکد را انتخاب فرمایید.</p>
            <button onclick="closeQrModal()" class="w-full py-2.5 bg-slate-800 text-slate-300 font-bold rounded-xl text-xs">
                بستن
            </button>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-5 inset-x-0 mx-auto w-max max-w-xs bg-emerald-600 text-white text-xs px-4 py-2 rounded-xl shadow-lg shadow-emerald-600/30 transition-all opacity-0 pointer-events-none z-50 flex items-center gap-2">
        <i class="fa-solid fa-check"></i>
        <span id="toastMsg">لینک اتصال کپی شد</span>
    </div>

    <script>
        // Telegram WebApp Initialization
        if (window.Telegram && window.Telegram.WebApp) {
            const tg = window.Telegram.WebApp;
            tg.ready();
            tg.expand();
            if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
                const u = tg.initDataUnsafe.user;
                document.getElementById('userName').innerText = (u.first_name || '') + (u.username ? ' (@' + u.username + ')' : '');
            }
        }

        function switchTab(tabId) {
            document.getElementById('tabSubscriptions').classList.add('hidden');
            document.getElementById('tabPlans').classList.add('hidden');
            document.getElementById('tabApps').classList.add('hidden');
            const wheelTab = document.getElementById('tabWheel');
            if (wheelTab) wheelTab.classList.add('hidden');

            document.getElementById(tabId).classList.remove('hidden');

            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.className = 'nav-btn flex flex-col items-center text-slate-400 gap-1 transition';
            });

            if (tabId === 'tabSubscriptions') {
                document.getElementById('navSubscriptions').className = 'nav-btn flex flex-col items-center text-purple-400 font-bold gap-1 transition';
            } else if (tabId === 'tabPlans') {
                document.getElementById('navPlans').className = 'nav-btn flex flex-col items-center text-purple-400 font-bold gap-1 transition';
            } else if (tabId === 'tabApps') {
                document.getElementById('navApps').className = 'nav-btn flex flex-col items-center text-purple-400 font-bold gap-1 transition';
            } else if (tabId === 'tabWheel') {
                const navWheel = document.getElementById('navWheel');
                if (navWheel) navWheel.className = 'nav-btn flex flex-col items-center text-amber-400 font-bold gap-1 transition';
            }
        }

        let isSpinning = false;
        let currentRotation = 0;

        function spinWheel() {
            if (isSpinning) return;
            const btn = document.getElementById('btnSpin');
            const status = document.getElementById('wheelStatus');
            const wheel = document.getElementById('wheelGraphic');
            
            let tgId = '';
            if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initDataUnsafe && window.Telegram.WebApp.initDataUnsafe.user) {
                tgId = window.Telegram.WebApp.initDataUnsafe.user.id;
            }
            if (!tgId) {
                const params = new URLSearchParams(window.location.search);
                tgId = params.get('tg_id') || '';
            }

            if (!tgId) {
                alert('شناسه کاربر تلگرام یافت نشد. لطفاً این صفحه را از داخل ربات تلگرام باز فرمایید.');
                return;
            }

            isSpinning = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>در حال چرخش...</span>';
            status.innerText = 'گردونه در حال چرخیدن است...';

            // Random rotation
            currentRotation += 1800 + Math.floor(Math.random() * 360);
            wheel.style.transform = `rotate(${currentRotation}deg)`;

            const fd = new FormData();
            fd.append('tg_id', tgId);

            fetch('<?= Helpers::url('webapp/spin') ?>', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                setTimeout(() => {
                    isSpinning = false;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-play"></i> <span>چرخاندن مجدد گردونه</span>';
                    
                    if (data.success) {
                        status.className = 'text-xs font-extrabold text-emerald-400 min-h-[24px]';
                        status.innerText = '🎉 ' + data.reward_text;
                        showToast('تبریک! هدیه برای شما ثبت شد 🎁');
                    } else {
                        status.className = 'text-xs font-bold text-amber-300 min-h-[24px]';
                        status.innerText = data.message;
                    }
                }, 4000);
            })
            .catch(err => {
                setTimeout(() => {
                    isSpinning = false;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-play"></i> <span>چرخاندن گردونه</span>';
                    status.className = 'text-xs font-bold text-rose-400 min-h-[24px]';
                    status.innerText = 'خطا در ارتباط با سرور.';
                }, 2000);
            });
        }

        function copySublink(url) {
            navigator.clipboard.writeText(url).then(() => {
                showToast('لینک اتصال اختصاصی با موفقیت کپی شد ✅');
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.remove('opacity-0', 'pointer-events-none');
            toast.classList.add('opacity-100');
            setTimeout(() => {
                toast.classList.remove('opacity-100');
                toast.classList.add('opacity-0', 'pointer-events-none');
            }, 2500);
        }

        function openQrModal(subUrl) {
            document.getElementById('qrImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(subUrl);
            const m = document.getElementById('qrModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }

        function closeQrModal() {
            const m = document.getElementById('qrModal');
            m.classList.remove('flex');
            m.classList.add('hidden');
        }

        function orderPlan(planId) {
            if (window.Telegram && window.Telegram.WebApp) {
                // If in Telegram WebApp, can send command or close with data
                window.Telegram.WebApp.sendData(JSON.stringify({action: 'order_plan', plan_id: planId}));
                window.Telegram.WebApp.close();
            } else {
                alert('جهت نهایی‌سازی سفارش، به ربات تلگرام هدایت می‌شوید.');
                window.location.href = 'https://t.me/<?= Setting::get('telegram_bot_username', 'ConnectixBot') ?>?start=plan_' + planId;
            }
        }
    </script>
</body>
</html>

<?php
$usedBytes = $client['traffic_used_bytes'];
$totalBytes = $client['traffic_limit_bytes'];
$pct = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;
$remBytes = max(0, $totalBytes - $usedBytes);
$brandName = htmlspecialchars($client['brand_name'] ?? 'Connectix VPN');
$subUrl = !empty($client['node_sublink']) ? $client['node_sublink'] : Helpers::fullUrl('sub/' . $client['sub_token']);
$botUsername = !empty($client['reseller_bot_username']) ? $client['reseller_bot_username'] : Setting::get('telegram_bot_username', '');
$passwordVal = !empty($client['password']) ? $client['password'] : '123456';
$isExpiringSoon = false;
$remDays = 999;
if (!empty($client['expire_at'])) {
    $diff = strtotime($client['expire_at']) - time();
    $remDays = floor($diff / 86400);
    if ($diff <= 3 * 86400 && $diff > 0) $isExpiringSoon = true;
}
$isExpired = ($client['status'] === 'expired' || (!empty($client['expire_at']) && strtotime($client['expire_at']) <= time()) || ($totalBytes > 0 && $remBytes <= 0));
$isLowTraffic = ($totalBytes > 0 && ($remBytes <= 2 * 1024 * 1024 * 1024 || $pct >= 90));
$renewalLink = !empty($client['renewal_url']) ? $client['renewal_url'] : (!empty($client['telegram_support']) ? 'https://t.me/' . ltrim($client['telegram_support'], '@') : null);
$themeColor = $client['theme_color'] ?? 'violet';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= $brandName ?>">
    <title><?= $brandName ?> | پنل اشتراک فوق حرفه‌ای</title>
    <?php
    $base = Helpers::basePath();
    $localTailwind = __DIR__ . '/../../assets/js/tailwind.js';
    $localFA = __DIR__ . '/../../assets/css/fontawesome.min.css';
    $localVazir = __DIR__ . '/../../assets/css/vazirmatn.css';
    $localQR = __DIR__ . '/../../assets/js/qrcode.min.js';
    ?>
    <?php if (file_exists($localTailwind)): ?><script src="<?= $base ?>/assets/js/tailwind.js"></script><?php else: ?><script src="https://cdn.tailwindcss.com"></script><?php endif; ?>
    <?php if (file_exists($localFA)): ?><link rel="stylesheet" href="<?= $base ?>/assets/css/fontawesome.min.css"><?php else: ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><?php endif; ?>
    <?php if (file_exists($localVazir)): ?><link rel="stylesheet" href="<?= $base ?>/assets/css/vazirmatn.css"><?php else: ?><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');</style><?php endif; ?>
    <?php if (file_exists($localQR)): ?><script src="<?= $base ?>/assets/js/qrcode.min.js"></script><?php else: ?><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><?php endif; ?>
    <style>
        * { font-family: 'Vazirmatn', sans-serif; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
        @keyframes pulse-glow { 0%,100%{box-shadow:0 0 20px rgba(168,85,247,0.3)} 50%{box-shadow:0 0 40px rgba(168,85,247,0.6)} }
        .glass { backdrop-filter: blur(16px); background: linear-gradient(135deg, rgba(30,41,59,0.9), rgba(15,23,42,0.9)); border:1px solid rgba(255,255,255,0.08); }
        .float { animation: float 3s ease-in-out infinite; }
        .glow { animation: pulse-glow 2s ease-in-out infinite; }
    </style>
</head>
<body class="bg-[#050a14] text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-violet-600 selection:text-white relative overflow-x-hidden">
    <!-- Ambient Background ULTRA -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-violet-600/20 rounded-full blur-[100px]"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-cyan-600/20 rounded-full blur-[100px]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-indigo-600/10 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-[480px] space-y-5 relative z-10">
        <!-- Header ULTRA -->
        <div class="glass rounded-[2rem] p-6 text-center shadow-2xl">
            <div class="flex justify-center mb-4">
                <?php if (!empty($client['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($client['logo_url']) ?>" alt="Logo" class="h-14 object-contain rounded-2xl shadow-lg">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-[1.2rem] bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center text-white shadow-xl shadow-violet-600/30 text-2xl float glow">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h1 class="text-xl font-black text-white"><?= $brandName ?></h1>
            <p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars($client['welcome_message'] ?? 'سرویس فوق سریع و ضد فیلتر - اتصال پایدار') ?></p>
            <div class="mt-3 flex justify-center gap-2">
                <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span> آنلاین و فعال</span>
                <span class="px-2.5 py-1 rounded-full bg-violet-500/10 text-violet-300 border border-violet-500/20 text-[10px] font-bold">v4.0.45</span>
            </div>
        </div>

        <?php if ($isExpired): ?>
        <div class="glass rounded-2xl p-4 border-rose-500/30 bg-gradient-to-br from-rose-950/40 to-red-950/40">
            <div class="flex items-center gap-2 font-black text-rose-300 text-sm mb-2"><i class="fa-solid fa-triangle-exclamation"></i> اشتراک منقضی شده!</div>
            <p class="text-[11px] text-slate-300 leading-relaxed mb-3">حجم یا زمان سرویس شما تمام شده. برای جلوگیری از قطع دائم، همین حالا تمدید کنید.</p>
            <?php if (!empty($renewalLink)): ?><a href="<?= htmlspecialchars($renewalLink) ?>" target="_blank" class="w-full py-3 bg-gradient-to-r from-rose-600 to-red-600 text-white font-black rounded-xl text-xs flex items-center justify-center gap-2 shadow-lg"><i class="fa-solid fa-bolt"></i> تمدید فوری - کلیک کنید</a><?php endif; ?>
        </div>
        <?php elseif ($isExpiringSoon || $isLowTraffic): ?>
        <div class="glass rounded-2xl p-4 border-amber-500/30 bg-gradient-to-br from-amber-950/30 to-orange-950/30">
            <div class="flex items-center gap-2 font-black text-amber-300 text-xs mb-2"><i class="fa-solid fa-clock"></i> اعتبار رو به پایان (<?= $remDays ?> روز / <?= Helpers::formatBytes($remBytes) ?> باقی)</div>
            <?php if (!empty($renewalLink)): ?><a href="<?= htmlspecialchars($renewalLink) ?>" target="_blank" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2"><i class="fa-solid fa-cart-shopping"></i> تمدید با 20% تخفیف</a><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Traffic Circle ULTRA -->
        <div class="glass rounded-[1.8rem] p-6 shadow-xl">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-gauge-high text-violet-400"></i> وضعیت مصرف</h3>
                <span class="text-[10px] px-2 py-1 bg-slate-800 rounded-full text-slate-400 font-mono"><?= $pct ?>% مصرف</span>
            </div>
            
            <!-- Circular Progress -->
            <div class="flex justify-center mb-6">
                <div class="relative w-40 h-40">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="#1e293b" stroke-width="8"/>
                        <circle cx="50" cy="50" r="42" fill="none" stroke="<?= $pct>=90?'#ef4444':($pct>=75?'#f59e0b':'#8b5cf6') ?>" stroke-width="8" stroke-linecap="round" stroke-dasharray="<?= 2*3.14159*42 ?>" stroke-dashoffset="<?= 2*3.14159*42*(1-$pct/100) ?>" class="transition-all duration-1000"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-black text-white"><?= $pct ?>%</span>
                        <span class="text-[10px] text-slate-400">مصرف شده</span>
                        <span class="text-[11px] font-mono text-violet-300 mt-1"><?= Helpers::formatBytes($remBytes) ?> باقی</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <span class="text-[10px] text-slate-400 block">مصرف شده</span>
                    <span class="font-black text-white font-mono"><?= Helpers::formatBytes($usedBytes) ?></span>
                    <div class="w-full h-1 bg-slate-800 rounded-full mt-2 overflow-hidden"><div class="h-full bg-violet-500" style="width: <?= $pct ?>%"></div></div>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <span class="text-[10px] text-slate-400 block">حجم کل</span>
                    <span class="font-black text-cyan-300 font-mono"><?= Helpers::formatBytes($totalBytes) ?></span>
                    <div class="text-[10px] text-slate-500 mt-2"><?= Helpers::daysRemaining($client['expire_at']) ?></div>
                </div>
            </div>

            <!-- Credentials ULTRA -->
            <div class="mt-5 bg-gradient-to-br from-violet-950/30 to-indigo-950/30 border border-violet-500/20 rounded-xl p-4 space-y-2.5">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-user text-violet-400"></i> نام کاربری</span>
                    <div class="flex items-center gap-2">
                        <code class="font-mono font-black text-white bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800"><?= htmlspecialchars($client['username']) ?></code>
                        <button onclick="copyRaw('<?= htmlspecialchars($client['username']) ?>', this)" class="w-7 h-7 bg-slate-800 hover:bg-violet-600 text-slate-400 hover:text-white rounded-lg flex items-center justify-center transition"><i class="fa-regular fa-copy text-[11px]"></i></button>
                    </div>
                </div>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-key text-amber-400"></i> رمز عبور</span>
                    <div class="flex items-center gap-2">
                        <code class="font-mono font-black text-amber-300 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800"><?= htmlspecialchars($passwordVal) ?></code>
                        <button onclick="copyRaw('<?= htmlspecialchars($passwordVal) ?>', this)" class="w-7 h-7 bg-slate-800 hover:bg-amber-600 text-slate-400 hover:text-white rounded-lg flex items-center justify-center transition"><i class="fa-regular fa-copy text-[11px]"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Speed Test ULTRA (new) -->
        <div class="glass rounded-2xl p-5">
            <h3 class="font-black text-xs text-white mb-3 flex items-center gap-2"><i class="fa-solid fa-gauge text-cyan-400"></i> تست سرعت اینترنت</h3>
            <div class="flex items-center gap-3">
                <button id="speedTestBtn" onclick="runSpeedTest()" class="flex-1 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 transition shadow-lg"><i class="fa-solid fa-bolt"></i> شروع تست سرعت</button>
                <div id="speedResult" class="flex-1 bg-slate-900 border border-slate-800 rounded-xl p-3 text-center hidden">
                    <div class="text-[10px] text-slate-400">سرعت دانلود</div>
                    <div class="font-black text-cyan-400 text-sm font-mono" id="speedValue">-- Mbps</div>
                    <div class="text-[10px] text-slate-500" id="pingValue">Ping: -- ms</div>
                </div>
            </div>
            <div class="mt-3 text-[10px] text-slate-500 text-center">تست مستقیم از مرورگر شما - بدون نیاز به VPN</div>
        </div>

        <!-- QR Code ULTRA -->
        <div class="glass rounded-2xl p-6 text-center">
            <h3 class="font-bold text-xs text-white mb-4 flex items-center justify-center gap-2"><i class="fa-solid fa-qrcode text-violet-400"></i> اسکن سریع با دوربین</h3>
            <div class="bg-white p-4 rounded-[1.2rem] inline-block shadow-2xl">
                <div id="qrcode" class="w-48 h-48 flex items-center justify-center"></div>
            </div>
            <div class="mt-4">
                <button onclick="copyToClipboard('<?= $subUrl ?>', this)" class="w-full py-3.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black rounded-xl text-xs shadow-xl shadow-violet-900/30 flex items-center justify-center gap-2 transition-all"><i class="fa-solid fa-copy"></i> کپی لینک اشتراک هوشمند</button>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-[10px]">
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-2.5"><span class="text-slate-400 block">باقیمانده</span><span class="font-bold text-emerald-400"><?= Helpers::formatBytes($remBytes) ?></span></div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-2.5"><span class="text-slate-400 block">انقضا</span><span class="font-bold text-amber-300"><?= Helpers::daysRemaining($client['expire_at']) ?></span></div>
            </div>
        </div>

        <!-- App Download ULTRA -->
        <div class="glass rounded-2xl p-5 bg-gradient-to-br from-violet-950/40 via-slate-900 to-indigo-950/40 border-violet-500/20">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center text-white shadow-lg"><i class="fa-solid fa-rocket"></i></span>
                <div>
                    <h3 class="font-black text-white text-xs">اپلیکیشن اختصاصی <?= $brandName ?></h3>
                    <p class="text-[10px] text-violet-300">ورود فقط با یوزر و پسورد - بدون کانفیگ دستی</p>
                </div>
                <span class="mr-auto text-[9px] px-2 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full font-bold">پیشنهادی</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <a href="https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-Universal.apk" class="py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl font-black text-xs flex items-center justify-center gap-2 shadow-lg transition"><i class="fa-brands fa-android text-sm"></i> اندروید</a>
                <a href="https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Windows-x64.zip" class="py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-xl font-black text-xs flex items-center justify-center gap-2 shadow-lg transition"><i class="fa-brands fa-windows text-sm"></i> ویندوز</a>
            </div>
        </div>

        <!-- One-Click Import ULTRA -->
        <div class="glass rounded-2xl p-5">
            <h3 class="font-bold text-xs text-white mb-3 text-center">اتصال با یک کلیک به اپ‌ها</h3>
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <a href="hiddify://install-sub?url=<?= urlencode($subUrl) ?>" class="py-3 bg-slate-800/60 hover:bg-violet-900/30 border border-slate-700 hover:border-violet-500/30 rounded-xl text-violet-300 font-bold flex items-center justify-center gap-2 transition"><i class="fa-solid fa-bolt"></i> Hiddify</a>
                <a href="v2rayng://install-config?url=<?= urlencode($subUrl) ?>" class="py-3 bg-slate-800/60 hover:bg-emerald-900/30 border border-slate-700 hover:border-emerald-500/30 rounded-xl text-emerald-400 font-bold flex items-center justify-center gap-2 transition"><i class="fa-brands fa-android"></i> V2rayNG</a>
                <a href="streisand://import/<?= urlencode($subUrl) ?>" class="py-3 bg-slate-800/60 hover:bg-slate-700 border border-slate-700 rounded-xl text-white font-bold flex items-center justify-center gap-2 transition"><i class="fa-brands fa-apple"></i> Streisand</a>
                <a href="sing-box://import-remote-profile?url=<?= urlencode($subUrl) ?>" class="py-3 bg-slate-800/60 hover:bg-cyan-900/30 border border-slate-700 hover:border-cyan-500/30 rounded-xl text-cyan-300 font-bold flex items-center justify-center gap-2 transition"><i class="fa-solid fa-box"></i> Sing-box</a>
            </div>
        </div>

        <!-- Configs List -->
        <?php if (!empty($configs)): ?>
        <div class="glass rounded-2xl p-4">
            <button onclick="document.getElementById('fallbackConfigsBox').classList.toggle('hidden')" class="w-full flex items-center justify-between text-xs font-black text-white"><span class="flex items-center gap-2"><i class="fa-solid fa-network-wired text-violet-400"></i> کانفیگ‌های مجزا (<?= count($configs) ?>)</span><i class="fa-solid fa-chevron-down text-[10px] text-slate-500"></i></button>
            <div id="fallbackConfigsBox" class="hidden mt-3 space-y-2 max-h-[300px] overflow-y-auto">
                <?php foreach ($configs as $k=>$cfg): $parsed=parse_url($cfg); $proto=strtoupper($parsed['scheme'] ?? 'CONFIG'); $remark=!empty($parsed['fragment'])?urldecode($parsed['fragment']):$proto; ?>
                <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
                    <div class="flex justify-between items-center mb-2"><span class="text-[11px] font-bold text-violet-300"><?= htmlspecialchars($remark) ?></span><button onclick="copyRaw('<?= htmlspecialchars($cfg) ?>', this)" class="px-2 py-1 bg-slate-800 hover:bg-violet-600 rounded-lg text-[10px] text-slate-300 hover:text-white transition">کپی</button></div>
                    <input readonly value="<?= htmlspecialchars($cfg) ?>" class="w-full bg-slate-950 border border-slate-800 rounded-lg p-2 font-mono text-[10px] text-slate-400" dir="ltr">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Support -->
        <div class="text-center pb-6">
            <p class="text-[11px] text-slate-500 mb-3">نیاز به کمک داری؟</p>
            <div class="flex justify-center gap-2.5">
                <?php if (!empty($client['telegram_support'])): ?><a href="https://t.me/<?= ltrim($client['telegram_support'], '@') ?>" target="_blank" class="px-4 py-2.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/20 rounded-xl text-xs font-bold flex items-center gap-2 transition"><i class="fa-brands fa-telegram"></i> پشتیبانی تلگرام</a><?php endif; ?>
                <?php if (!empty($client['whatsapp_support'])): ?><a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$client['whatsapp_support']) ?>" target="_blank" class="px-4 py-2.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 rounded-xl text-xs font-bold flex items-center gap-2 transition"><i class="fa-brands fa-whatsapp"></i> واتساپ</a><?php endif; ?>
            </div>
            <div class="mt-4 text-[10px] text-slate-600 font-mono">Powered by <?= $brandName ?> • v4.0.45 • ضد فیلتر هوشمند</div>
        </div>
    </div>

<script>
try{
    if(typeof QRCode!=='undefined'){
        new QRCode(document.getElementById("qrcode"), { text: "<?= $subUrl ?>", width:192, height:192, colorDark:"#0f172a", colorLight:"#ffffff", correctLevel: QRCode.CorrectLevel.M });
    }
}catch(e){}

function copyToClipboard(text, btn){
    navigator.clipboard.writeText(text).then(()=>{
        const orig=btn.innerHTML;
        btn.innerHTML='<i class="fa-solid fa-check text-emerald-400"></i> کپی شد!';
        btn.classList.add('from-emerald-600','to-teal-600');
        setTimeout(()=>{btn.innerHTML=orig; btn.classList.remove('from-emerald-600','to-teal-600');},2000);
    });
}
function copyRaw(text, btn){
    navigator.clipboard.writeText(text).then(()=>{
        const orig=btn.innerHTML;
        btn.innerHTML='<i class="fa-solid fa-check"></i>';
        setTimeout(()=>btn.innerHTML=orig,1500);
    });
}
async function runSpeedTest(){
    const btn=document.getElementById('speedTestBtn');
    const res=document.getElementById('speedResult');
    const speedVal=document.getElementById('speedValue');
    const pingVal=document.getElementById('pingValue');
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> در حال تست...';
    btn.disabled=true;
    const start=Date.now();
    try{
        // Ping test via small image
        const pingStart=Date.now();
        await fetch('https://www.cloudflare.com/cdn-cgi/trace?'+Date.now(), {cache:'no-store'});
        const ping=Date.now()-pingStart;
        // Download test 1MB
        const dlStart=Date.now();
        const resp=await fetch('https://speed.cloudflare.com/__down?bytes=1000000&cb='+Date.now(), {cache:'no-store'});
        await resp.arrayBuffer();
        const dlTime=(Date.now()-dlStart)/1000;
        const speed=((1*8)/dlTime).toFixed(1); // Mbps
        speedVal.textContent=speed+' Mbps';
        pingVal.textContent='Ping: '+ping+' ms';
        res.classList.remove('hidden');
        btn.innerHTML='<i class="fa-solid fa-rotate"></i> تست مجدد';
    }catch(e){
        speedVal.textContent='خطا';
        pingVal.textContent='اتصال ضعیف';
        res.classList.remove('hidden');
        btn.innerHTML='<i class="fa-solid fa-rotate"></i> تلاش مجدد';
    }
    btn.disabled=false;
}
</script>
</body>
</html>

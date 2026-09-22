<?php
$usedBytes = $client['traffic_used_bytes'];
$totalBytes = $client['traffic_limit_bytes'];
$pct = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;
$remBytes = max(0, $totalBytes - $usedBytes);
$brandName = htmlspecialchars($client['brand_name'] ?? 'Connectix VPN');
$subUrl = Helpers::fullUrl('sub/' . $client['sub_token']);
$botUsername = !empty($client['reseller_bot_username']) ? $client['reseller_bot_username'] : Setting::get('telegram_bot_username', '');
$passwordVal = !empty($client['password']) ? $client['password'] : '123456';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $brandName ?> | وضعیت و اطلاعات اشتراک</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap');
        * { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-purple-600 selection:text-white relative overflow-x-hidden">
    <!-- Ambient Glow Background Effects -->
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl p-6 md:p-8 backdrop-blur-xl relative z-10 space-y-6">
        <!-- Header & Logo -->
        <div class="text-center">
            <?php if (!empty($client['logo_url'])): ?>
                <img src="<?= htmlspecialchars($client['logo_url']) ?>" alt="Logo" class="h-12 mx-auto mb-3 object-contain rounded">
            <?php else: ?>
                <div class="w-14 h-14 rounded-2xl bg-purple-600 mx-auto flex items-center justify-center text-white shadow-xl shadow-purple-600/30 mb-3">
                    <i class="fa-solid fa-shield-halved text-2xl"></i>
                </div>
            <?php endif; ?>
            <h1 class="text-xl font-black text-white"><?= $brandName ?></h1>
            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($client['welcome_message'] ?? 'سرویس امن و بدون محدودیت اینترنت آزاد') ?></p>
        </div>

        <!-- Account Status Card -->
        <div class="bg-slate-950/70 border border-slate-800/90 rounded-2xl p-5 space-y-4">
            <!-- Username & Password Credentials Box -->
            <div class="bg-slate-900/80 border border-purple-900/40 rounded-xl p-3.5 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">👤 نام کاربری شما:</span>
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono font-bold text-white"><?= htmlspecialchars($client['username']) ?></span>
                        <button onclick="copyRaw('<?= htmlspecialchars($client['username']) ?>', this)" class="text-slate-400 hover:text-white px-1.5 py-0.5 rounded bg-slate-800 text-[10px]">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">🔑 کلمه عبور اشتراک:</span>
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono font-bold text-purple-300"><?= htmlspecialchars($passwordVal) ?></span>
                        <button onclick="copyRaw('<?= htmlspecialchars($passwordVal) ?>', this)" class="text-slate-400 hover:text-white px-1.5 py-0.5 rounded bg-slate-800 text-[10px]">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (!empty($botUsername)): ?>
                <!-- 1-Click Telegram Bot Connect -->
                <a href="https://t.me/<?= ltrim($botUsername, '@') ?>?start=bind_<?= $client['sub_token'] ?>" target="_blank" class="w-full py-2.5 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md transition">
                    <i class="fa-brands fa-telegram text-sm"></i>
                    <span>اتصال خودکار به ربات تلگرام با ۱ کلیک</span>
                </a>
            <?php endif; ?>

            <!-- Traffic Usage Progress -->
            <div>
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="text-slate-400">حجم مصرفی:</span>
                    <span class="font-bold text-white"><?= Helpers::formatBytes($usedBytes) ?> <span class="text-slate-400 font-normal">از</span> <?= Helpers::formatBytes($totalBytes) ?></span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden p-0.5 border border-slate-700/60">
                    <div class="h-2 rounded-full <?= $pct >= 90 ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-purple-500') ?> transition-all duration-500" style="width: <?= min(100, $pct) ?>%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1">
                    <span>باقیمانده: <strong class="text-emerald-400"><?= Helpers::formatBytes($remBytes) ?></strong></span>
                    <span><?= $pct ?>% مصرف شده</span>
                </div>
            </div>

            <!-- Expiration Date & IP Limit -->
            <div class="pt-3 border-t border-slate-800/80 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">اعتبار زمانی:</span>
                    <span class="font-bold text-amber-300"><?= Helpers::daysRemaining($client['expire_at']) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">سقف اتصال همزمان:</span>
                    <span class="font-bold text-purple-300 font-mono"><?= ($client['ip_limit'] ?? 2) > 0 ? ($client['ip_limit'] ?? 2) . ' دستگاه' : 'نامحدود' ?></span>
                </div>
            </div>

            <!-- Reserved Plan Banner (if queued) -->
            <?php if (!empty($client['reserved_id'])): ?>
                <div class="p-3 bg-cyan-950/50 border border-cyan-800/60 rounded-xl text-xs text-cyan-200 flex items-center gap-2.5">
                    <i class="fa-solid fa-sparkles text-cyan-400 text-sm"></i>
                    <div>
                        <strong class="block text-cyan-300 font-bold">پلن رزرو هوشمند فعال است!</strong>
                        <span>یک بسته <?= $client['reserved_gb'] ?>GB رزرو دارید که پس از پایان حجم فعلی، خودکار فعال خواهد شد.</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- QR Code for fast import (Rendered with local fallback) -->
        <div class="text-center space-y-3">
            <span class="text-xs text-slate-300 block font-semibold"><i class="fa-solid fa-qrcode text-purple-400 ml-1"></i> اسکن مستقیم بارکد اشتراک:</span>
            <div class="bg-white p-3 rounded-2xl inline-block shadow-lg">
                <div id="qrcode" class="w-44 h-44 flex items-center justify-center"></div>
            </div>

            <!-- Service Details Box immediately below QR Code -->
            <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 text-xs text-right space-y-2">
                <div class="text-[11px] font-bold text-slate-400 border-b border-slate-800 pb-1.5 flex items-center justify-between">
                    <span>📋 مشخصات سرویس فوق</span>
                    <span class="text-emerald-400 font-mono">STATUS: ACTIVE</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-slate-300">
                    <div class="bg-slate-900/90 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">نام کاربری:</span>
                        <code class="font-bold text-white select-all text-xs"><?= htmlspecialchars($client['username']) ?></code>
                    </div>
                    <div class="bg-slate-900/90 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">کلمه عبور:</span>
                        <code class="font-bold text-amber-300 select-all text-xs"><?= htmlspecialchars($passwordVal) ?></code>
                    </div>
                    <div class="bg-slate-900/90 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">حجم باقیمانده:</span>
                        <span class="font-bold text-cyan-300"><?= Helpers::formatBytes($remBytes) ?></span>
                    </div>
                    <div class="bg-slate-900/90 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">اعتبار زمانی:</span>
                        <span class="font-bold text-emerald-300"><?= Helpers::daysRemaining($client['expire_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1-Click Copy Subscription Button -->
        <div class="space-y-2">
            <button onclick="copyToClipboard('<?= $subUrl ?>', this)" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/40 flex items-center justify-center gap-2">
                <i class="fa-solid fa-copy"></i>
                <span>کپی لینک اشتراک هوشمند (Sublink)</span>
            </button>
        </div>

        <!-- 1-Click Direct Import into VPN Apps -->
        <div>
            <span class="text-xs text-slate-400 block mb-2 font-semibold text-center">اتصال مستقیم با یک کلیک:</span>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <a href="hiddify://install-sub?url=<?= urlencode($subUrl) ?>" class="py-2.5 px-3 bg-slate-800 hover:bg-purple-900/40 rounded-xl border border-slate-700 text-purple-300 flex items-center justify-center gap-2 transition-colors font-semibold">
                    <i class="fa-solid fa-bolt"></i>
                    <span>ورود به Hiddify</span>
                </a>

                <a href="v2rayng://install-config?url=<?= urlencode($subUrl) ?>" class="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 text-emerald-400 flex items-center justify-center gap-2 transition-colors">
                    <i class="fa-brands fa-android"></i>
                    <span>ورود به V2rayNG</span>
                </a>

                <a href="streisand://import/<?= urlencode($subUrl) ?>" class="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 text-slate-200 flex items-center justify-center gap-2 transition-colors">
                    <i class="fa-brands fa-apple"></i>
                    <span>ورود به Streisand</span>
                </a>

                <a href="sing-box://import-remote-profile?url=<?= urlencode($subUrl) ?>" class="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 text-cyan-300 flex items-center justify-center gap-2 transition-colors">
                    <i class="fa-solid fa-box"></i>
                    <span>ورود به Sing-box</span>
                </a>
            </div>
        </div>

        <!-- Multi-Inbound Fallback Individual Configs Box -->
        <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-4 space-y-3">
            <button type="button" onclick="document.getElementById('fallbackConfigsBox').classList.toggle('hidden')" class="w-full flex items-center justify-between text-xs font-bold text-slate-300 hover:text-white transition">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-purple-400"></i>
                    <span>کانفیگ‌های مجزا و مسیرهای پشتیبان (Multi-Inbound)</span>
                </span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-500"></i>
            </button>

            <div id="fallbackConfigsBox" class="hidden space-y-2.5 pt-2 border-t border-slate-800/80">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    در صورتی که نرم‌افزار شما از ساب‌لینک خودکار پشتیبانی نمی‌کند، می‌توانید هر یک از مسیرهای زیر را به تفکیک کپی و وارد کنید:
                </p>
                <?php 
                $protoLabels = [
                    'vless_reality' => ['title' => '⚡️ VLESS Reality (مستقیم پرسرعت)', 'color' => 'text-purple-400'],
                    'vless_ws' => ['title' => '🛡 VLESS CDN (ضد فیلتر شبکه ملی)', 'color' => 'text-cyan-400'],
                    'trojan' => ['title' => '🔒 Trojan TLS (پایدار برای iOS و مک)', 'color' => 'text-emerald-400'],
                    'vmess' => ['title' => '🚀 VMess WS (سازگار با کلیه اوپراتورها)', 'color' => 'text-amber-400'],
                ];
                foreach ($configs as $k => $cfg): 
                    $info = $protoLabels[$k] ?? ['title' => strtoupper($k), 'color' => 'text-white'];
                ?>
                    <div class="bg-slate-900/90 border border-slate-800 p-2.5 rounded-xl space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold <?= $info['color'] ?>"><?= $info['title'] ?></span>
                            <button onclick="copyRaw('<?= htmlspecialchars($cfg) ?>', this)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-purple-600 text-[10px] text-slate-300 hover:text-white transition">
                                <i class="fa-regular fa-copy ml-1"></i> کپی
                            </button>
                        </div>
                        <input type="text" readonly value="<?= htmlspecialchars($cfg) ?>" class="w-full bg-slate-950 border border-slate-800/80 rounded-lg p-1.5 font-mono text-[10px] text-slate-400 select-all" dir="ltr">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reseller Support Links -->
        <div class="pt-4 border-t border-slate-800 text-center space-y-3">
            <span class="text-xs text-slate-400 block">نیاز به راهنمایی یا پشتیبانی دارید؟</span>
            <div class="flex items-center justify-center gap-3">
                <?php if (!empty($client['telegram_support'])): ?>
                    <a href="https://t.me/<?= ltrim($client['telegram_support'], '@') ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/20 text-xs font-semibold flex items-center gap-2 hover:bg-sky-500/20 transition-all">
                        <i class="fa-brands fa-telegram text-sm"></i>
                        <span>تلگرام پشتیبانی</span>
                    </a>
                <?php endif; ?>

                <?php if (!empty($client['whatsapp_support'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $client['whatsapp_support']) ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-semibold flex items-center gap-2 hover:bg-emerald-500/20 transition-all">
                        <i class="fa-brands fa-whatsapp text-sm"></i>
                        <span>واتساپ پشتیبانی</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Render QR Code locally
        try {
            if (typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("qrcode"), {
                    text: "<?= $subUrl ?>",
                    width: 160,
                    height: 160,
                    colorDark : "#0f172a",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            } else {
                document.getElementById("qrcode").innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($subUrl) ?>" alt="QR" class="w-40 h-40">';
            }
        } catch(e) {
            document.getElementById("qrcode").innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($subUrl) ?>" alt="QR" class="w-40 h-40">';
        }

        function copyToClipboard(text, btnElement) {
            navigator.clipboard.writeText(text).then(function() {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> لینک اشتراک کپی شد!';
                setTimeout(() => {
                    btnElement.innerHTML = originalHtml;
                }, 2500);
            }).catch(function(err) {
                alert('خطا در کپی: ' + err);
            });
        }

        function copyRaw(text, btnElement) {
            navigator.clipboard.writeText(text).then(function() {
                const orig = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i>';
                setTimeout(() => { btnElement.innerHTML = orig; }, 2000);
            });
        }
    </script>
</body>
</html>

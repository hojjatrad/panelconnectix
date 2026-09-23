<?php
$pageTitle = 'وب‌سرویس و اپلیکیشن اختصاصی کلاینت (User/Pass App)';
require __DIR__ . '/../layout/header.php';

$sampleUsername = $sampleClient['username'] ?? 'testuser';
$samplePassword = $sampleClient['password'] ?? '123456';
$sampleSubUrl = !empty($sampleClient['sub_token']) ? Helpers::subUrl($sampleClient['sub_token']) : 'https://example.com/sub/token';
$brandName = $branding['brand_name'] ?? 'Connectix VPN';
$themeColor = $branding['theme_color'] ?? 'violet';
$logoUrl = $branding['logo_url'] ?? '';
$tgSupport = $branding['telegram_support'] ?? '@Support';
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 rounded-3xl shadow-xl">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">Client App API v1</span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">آماده اتصال و انتشار</span>
            </div>
            <h1 class="text-lg font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-mobile-screen-button text-cyan-400"></i>
                <span>اپلیکیشن کلاینت اختصاصی و وب‌سرویس احراز هویت</span>
            </h1>
            <p class="text-xs text-slate-400">
                مشتریان با وارد کردن نام‌کاربری و پسورد اختصاصی خود در اپلیکیشن متصل می‌شوند؛ بدون نیاز به کپی ساب‌لینک یا اسکن بارکد.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="#simulator" class="px-4 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold rounded-xl text-xs shadow-lg shadow-cyan-600/30 transition flex items-center gap-2">
                <i class="fa-solid fa-play"></i>
                <span>تست در شبیه‌ساز زنده</span>
            </a>
            <a href="#flutter_code" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs border border-slate-700 transition flex items-center gap-2">
                <i class="fa-brands fa-flutter text-sky-400"></i>
                <span>سورس‌کد کلاینت Flutter</span>
            </a>
        </div>
    </div>

    <!-- Main Grid: Live Simulator vs API Documentation -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Column 1: Live Interactive Smartphone Simulator (5 Cols) -->
        <div id="simulator" class="lg:col-span-5 space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-mobile-screen text-cyan-400"></i>
                    <span>شبیه‌ساز تعاملی موبایل (Live App Preview)</span>
                </h2>
                <span class="text-[10px] text-slate-500">مبتنی بر API واقعی پنل</span>
            </div>

            <!-- Smartphone Frame Mockup -->
            <div class="mx-auto max-w-[340px] bg-slate-950 p-3 rounded-[40px] border-4 border-slate-800 shadow-2xl relative">
                <!-- Phone Speaker / Dynamic Island -->
                <div class="w-24 h-4 bg-slate-900 rounded-full mx-auto mb-2 flex items-center justify-center">
                    <span class="w-2 h-2 rounded-full bg-slate-800 inline-block mr-2"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-700 inline-block"></span>
                </div>

                <!-- Phone Inner Screen -->
                <div class="w-full bg-[#0b0f19] rounded-[32px] overflow-hidden border border-slate-800 text-slate-100 min-h-[580px] flex flex-col justify-between relative select-none">
                    
                    <!-- App Status Bar -->
                    <div class="px-4 pt-2 flex items-center justify-between text-[10px] text-slate-400">
                        <span>12:45</span>
                        <div class="flex items-center gap-1">
                            <i class="fa-solid fa-signal text-[8px]"></i>
                            <i class="fa-solid fa-wifi text-[8px]"></i>
                            <i class="fa-solid fa-battery-full text-[9px]"></i>
                        </div>
                    </div>

                    <!-- SCREEN 1: LOGIN SCREEN (shown if not logged in) -->
                    <div id="simScreenLogin" class="p-5 flex-1 flex flex-col justify-between">
                        <div class="text-center pt-6 space-y-2">
                            <?php if (!empty($logoUrl)): ?>
                                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="h-12 w-auto mx-auto object-contain rounded-xl">
                            <?php else: ?>
                                <div class="w-14 h-14 bg-gradient-to-tr from-cyan-600 to-indigo-600 rounded-2xl mx-auto flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-cyan-600/30">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="text-sm font-black text-white"><?= htmlspecialchars($brandName) ?></h3>
                            <p class="text-[10px] text-slate-400">ورود با نام کاربری و رمز عبور اشتراک</p>
                        </div>

                        <div class="space-y-3 py-4">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">نام کاربری (Username)</label>
                                <input type="text" id="simInputUser" value="<?= htmlspecialchars($sampleUsername) ?>" placeholder="usr_xxxxxx" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1">کلمه عبور (Password)</label>
                                <input type="password" id="simInputPass" value="<?= htmlspecialchars($samplePassword) ?>" placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-500 font-mono text-left" dir="ltr">
                            </div>

                            <button type="button" onclick="simDoLogin()" id="simBtnLogin" class="w-full py-2.5 bg-gradient-to-r from-cyan-600 to-indigo-600 hover:from-cyan-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs shadow-lg shadow-cyan-600/30 transition active:scale-95 flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                <span>ورود به حساب و اتصال</span>
                            </button>
                            <span id="simLoginErr" class="text-[10px] text-rose-400 block text-center min-h-[16px]"></span>
                        </div>

                        <div class="text-center space-y-1.5 pb-2">
                            <span class="text-[9px] text-slate-500">حساب کاربری ندارید؟</span>
                            <div class="flex justify-center gap-2">
                                <a href="https://t.me/<?= ltrim($tgSupport, '@') ?>" target="_blank" class="text-[10px] text-cyan-400 hover:underline">تماس با پشتیبانی</a>
                            </div>
                        </div>
                    </div>

                    <!-- SCREEN 2: MAIN DASHBOARD SCREEN (shown after login) -->
                    <div id="simScreenDashboard" class="p-4 flex-1 flex flex-col justify-between hidden">
                        <!-- Top Bar inside App -->
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800/80">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-cyan-600/20 text-cyan-400 rounded-lg flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <div>
                                    <strong id="simDispUser" class="text-xs font-bold text-white block leading-tight"></strong>
                                    <span id="simDispStatus" class="text-[9px] text-emerald-400 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span>حساب فعال</span>
                                    </span>
                                </div>
                            </div>
                            <button onclick="simLogout()" class="text-[10px] text-slate-400 hover:text-rose-400 transition flex items-center gap-1">
                                <i class="fa-solid fa-power-off"></i>
                                <span>خروج</span>
                            </button>
                        </div>

                        <!-- Central Connection Button with Glowing Wave Animation -->
                        <div class="my-auto text-center space-y-3 py-4">
                            <div class="relative w-36 h-36 mx-auto flex items-center justify-center">
                                <!-- Pulsing Ring -->
                                <div id="simPulseRing" class="absolute inset-0 rounded-full bg-cyan-500/20 animate-ping hidden"></div>
                                <div id="simGlowRing" class="absolute -inset-1 rounded-full bg-gradient-to-tr from-cyan-600 to-indigo-600 opacity-30 blur-md"></div>
                                
                                <button type="button" onclick="simToggleConnect()" id="simBtnConnect" class="relative w-32 h-32 rounded-full bg-gradient-to-b from-slate-900 to-slate-950 border-4 border-slate-800 text-slate-300 flex flex-col items-center justify-center transition-all duration-300 active:scale-95 shadow-2xl">
                                    <i id="simPowerIcon" class="fa-solid fa-power-off text-3xl mb-1 text-slate-400 transition"></i>
                                    <span id="simConnLabel" class="text-[11px] font-extrabold text-slate-300">لمس برای اتصال</span>
                                    <span id="simTimer" class="text-[9px] font-mono text-cyan-400 hidden">00:00:00</span>
                                </button>
                            </div>

                            <!-- Live Latency & Speed -->
                            <div class="flex items-center justify-center gap-4 text-[10px] font-mono">
                                <div class="flex items-center gap-1 text-slate-400">
                                    <i class="fa-solid fa-arrow-down text-emerald-400"></i>
                                    <span id="simSpeedDown">0 KB/s</span>
                                </div>
                                <div class="flex items-center gap-1 text-slate-400">
                                    <i class="fa-solid fa-arrow-up text-cyan-400"></i>
                                    <span id="simSpeedUp">0 KB/s</span>
                                </div>
                                <div class="flex items-center gap-1 text-amber-300">
                                    <i class="fa-solid fa-gauge-high"></i>
                                    <span id="simPingMs">42 ms</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quota & Days Left Card -->
                        <div class="p-3 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">حجم مصرفی:</span>
                                <span class="font-bold text-white font-mono"><span id="simUsedGb">0</span> / <span id="simTotalGb">0</span> GB</span>
                            </div>
                            <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                <div id="simUsageBar" class="bg-gradient-to-r from-cyan-500 to-indigo-500 h-full w-0 transition-all duration-500"></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                <span>زمان باقیمانده:</span>
                                <span id="simDaysLeft" class="font-bold text-amber-300 font-mono"></span>
                            </div>
                        </div>

                        <!-- Active Server Selector Pill -->
                        <div onclick="simOpenServerModal()" class="mt-2 p-2.5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between cursor-pointer hover:border-cyan-500/40 transition">
                            <div class="flex items-center gap-2">
                                <span id="simActiveFlag" class="text-base">🇩🇪</span>
                                <div class="text-right">
                                    <strong id="simActiveServerName" class="text-[11px] font-bold text-white block leading-tight">🇩🇪 آلمان - همراه اول</strong>
                                    <span id="simActiveProto" class="text-[9px] text-cyan-400 font-mono">VLESS Reality</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 text-slate-400 text-xs">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- SCREEN 3: SERVER SELECTOR MODAL (Popup drawer) -->
                    <div id="simModalServers" class="absolute inset-0 bg-slate-950/95 backdrop-blur-md p-4 flex flex-col justify-between hidden z-30">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                                <strong class="text-xs font-bold text-white">انتخاب سرور و پروتکل</strong>
                                <button onclick="simCloseServerModal()" class="text-xs text-slate-400 hover:text-white">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <div id="simServersList" class="space-y-2 mt-3 max-h-[460px] overflow-y-auto pr-1">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>
                        <button onclick="simCloseServerModal()" class="w-full py-2 bg-slate-800 text-white rounded-xl text-xs font-bold mt-2">
                            بستن
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Column 2: REST API Specifications & Code Integration (7 Cols) -->
        <div class="lg:col-span-7 space-y-4">
            
            <!-- Quick Feature Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-800 pb-2">
                <button onclick="switchApiTab('tabSpecs')" id="btnTabSpecs" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-600 text-white transition flex items-center gap-1.5 shadow">
                    <i class="fa-solid fa-book-open"></i>
                    <span>مستندات کامل API</span>
                </button>
                <button onclick="switchApiTab('tabFlutter')" id="btnTabFlutter" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5">
                    <i class="fa-brands fa-flutter text-sky-400"></i>
                    <span>سورس‌کد پروژه فلوتر (Flutter)</span>
                </button>
                <button onclick="switchApiTab('tabCore')" id="btnTabCore" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-virus text-emerald-400"></i>
                    <span>هسته‌های V2Ray و تونلینگ</span>
                </button>
            </div>

            <!-- TAB 1: API Specs -->
            <div id="tabSpecs" class="space-y-4">
                
                <!-- 1. Endpoint: POST /api/v1/app/login -->
                <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 rounded text-[10px] font-mono font-bold">POST</span>
                            <code class="text-xs text-white font-mono">/api/v1/app/login</code>
                        </div>
                        <span class="text-[10px] text-slate-400">ورود و دریافت توکن</span>
                    </div>
                    <p class="text-xs text-slate-300">
                        بررسی یوزر و پسورد کلاینت، محاسبه وضعیت فعال/منقضی و صدور توکن دسترسی برای نشست اپلیکیشن.
                    </p>
                    <div class="p-3 bg-slate-950 rounded-xl font-mono text-[11px] text-slate-300 space-y-1">
                        <div class="text-[10px] text-slate-500 mb-1">// Request Body (JSON or Form-Data)</div>
                        <div>{</div>
                        <div class="pr-4 text-cyan-300">"username": "<span class="text-amber-300">usr_123456</span>",</div>
                        <div class="pr-4 text-cyan-300">"password": "<span class="text-amber-300">secret123</span>"</div>
                        <div>}</div>
                    </div>
                </div>

                <!-- 2. Endpoint: GET /api/v1/app/configs -->
                <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 bg-sky-600/20 text-sky-400 border border-sky-500/30 rounded text-[10px] font-mono font-bold">GET</span>
                            <code class="text-xs text-white font-mono">/api/v1/app/configs</code>
                        </div>
                        <span class="text-[10px] text-slate-400">کانفیگ‌ها و سرورها</span>
                    </div>
                    <p class="text-xs text-slate-300">
                        دریافت لیست تفکیک‌شده سرورها (MCI Reality, Irancell CDN, Rightel Trojan, Wi-Fi VMess) همراه با پرچم و ساب‌لینک خام Base64.
                    </p>
                    <div class="p-3 bg-slate-950 rounded-xl font-mono text-[11px] text-slate-300 space-y-1">
                        <div class="text-[10px] text-slate-500 mb-1">// Request Header</div>
                        <div>Authorization: <span class="text-emerald-400">Bearer &lt;auth_token&gt;</span></div>
                    </div>
                </div>

                <!-- 3. Endpoint: GET /api/v1/app/profile -->
                <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 bg-sky-600/20 text-sky-400 border border-sky-500/30 rounded text-[10px] font-mono font-bold">GET</span>
                            <code class="text-xs text-white font-mono">/api/v1/app/profile</code>
                        </div>
                        <span class="text-[10px] text-slate-400">استعلام زنده ترافیک</span>
                    </div>
                    <p class="text-xs text-slate-300">
                        دریافت لحظه‌ای مانده حجم، روزهای باقیمانده و درصد مصرف برای به‌روزرسانی گیج داشبورد.
                    </p>
                </div>

                <!-- 4. Endpoint: POST /api/v1/app/feedback -->
                <div class="p-4 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 rounded text-[10px] font-mono font-bold">POST</span>
                            <code class="text-xs text-white font-mono">/api/v1/app/feedback</code>
                        </div>
                        <span class="text-[10px] text-slate-400">ارسال لاگ و تیکت پشتیبانی</span>
                    </div>
                    <p class="text-xs text-slate-300">
                        ارسال لاگ خطای اتصال کاربر از داخل اپ به بخش تیکت‌های پنل جهت عیب‌یابی سریع.
                    </p>
                </div>

            </div>

            <!-- TAB 2: Flutter Source Code Project Structure -->
            <div id="tabFlutter" class="space-y-4 hidden">
                <div class="p-5 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-white flex items-center gap-2">
                            <i class="fa-brands fa-flutter text-sky-400"></i>
                            <span>معماری و ساختار پروژه فلوتر (Flutter Multiplatform)</span>
                        </h3>
                        <span class="text-[10px] text-slate-400 font-mono">Android / iOS / Windows / macOS</span>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        با یک بار توسعه در Flutter، می‌توانید اپلیکیشن را برای تمام سیستم‌عامل‌ها (اندروید، آیفون، ویندوز و مک) خروجی بگیرید. برای پیاده‌سازی هسته VPN از پکیج <code>flutter_v2ray</code> یا <code>libxray</code> استفاده می‌شود.
                    </p>

                    <!-- Code Preview: api_service.dart -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span class="font-mono">lib/services/api_service.dart</span>
                            <button onclick="copyCode('codeFlutterApi')" class="text-cyan-400 hover:underline">کپی کد</button>
                        </div>
                        <pre id="codeFlutterApi" class="p-3 bg-slate-950 rounded-xl text-[11px] font-mono text-cyan-300 overflow-x-auto max-h-60">import 'dart:convert';
import 'package:http/http.dart' as http;

class ConnectixApiService {
  final String baseUrl = "<?= Helpers::fullUrl('') ?>";
  String? authToken;

  Future&lt;Map&lt;String, dynamic&gt;&gt; login(String username, String password) async {
    final res = await http.post(
      Uri.parse("$baseUrl/api/v1/app/login"),
      headers: {"Content-Type": "application/json"},
      body: jsonEncode({"username": username, "password": password}),
    );
    final data = jsonDecode(res.body);
    if (data['success'] == true) {
      authToken = data['data']['auth_token'];
    }
    return data;
  }

  Future&lt;List&lt;dynamic&gt;&gt; getServers() async {
    final res = await http.get(
      Uri.parse("$baseUrl/api/v1/app/configs"),
      headers: {"Authorization": "Bearer $authToken"},
    );
    final data = jsonDecode(res.body);
    return data['data']['servers'] ?? [];
  }
}</pre>
                    </div>

                    <!-- Code Preview: main.dart -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span class="font-mono">lib/main.dart (تکه کد اتصال و هسته VPN)</span>
                            <button onclick="copyCode('codeFlutterVpn')" class="text-cyan-400 hover:underline">کپی کد</button>
                        </div>
                        <pre id="codeFlutterVpn" class="p-3 bg-slate-950 rounded-xl text-[11px] font-mono text-emerald-300 overflow-x-auto max-h-60">import 'package:flutter/material.dart';
import 'package:flutter_v2ray/flutter_v2ray.dart';

void startVpnConnection(String v2rayUri) async {
  final FlutterV2ray flutterV2ray = FlutterV2ray();
  await flutterV2ray.initializeV2Ray();
  
  if (await flutterV2ray.requestPermission()) {
    flutterV2ray.startV2Ray(
      remark: "Connectix Premium",
      config: v2rayUri,
      blockedApps: null,
      bypassSubnets: ["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"], // Split Tunneling
    );
  }
}</pre>
                    </div>
                </div>
            </div>

            <!-- TAB 3: VPN Cores & Tunneling -->
            <div id="tabCore" class="space-y-4 hidden">
                <div class="p-5 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-4">
                    <h3 class="text-xs font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-shield-virus text-emerald-400"></i>
                        <span>هسته‌های پیشنهادی جهت خروجی اپلیکیشن نیتیو</span>
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 space-y-2">
                            <div class="flex items-center gap-2 font-bold text-emerald-400">
                                <i class="fa-brands fa-android text-base"></i>
                                <span>v2rayNG / LibXray (اندروید)</span>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-relaxed">
                                پایدارترین و کم‌حجم‌ترین هسته Xray برای سیستم‌عامل اندروید. پشتیبانی از VLESS Reality، gRPC، WebSocket و اسکوپ دور زدن سایت‌های دولتی و بانکی ایران.
                            </p>
                        </div>

                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 space-y-2">
                            <div class="flex items-center gap-2 font-bold text-sky-400">
                                <i class="fa-brands fa-windows text-base"></i>
                                <span>Wintun / Xray Core (ویندوز)</span>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-relaxed">
                                ساخت آداپتور مجازی Wintun در ویندوز ۱۰ و ۱۱ برای تونل کردن کل ترافیک بازی‌ها و مرورگرها با کمترین میزان Latency و سازگاری کامل.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
let simAuthToken = '';
let simIsConnected = false;
let simTimerInterval = null;
let simSeconds = 0;
let simServers = [];
let simActiveServerIndex = 0;

function switchApiTab(tabId) {
    document.getElementById('tabSpecs').classList.add('hidden');
    document.getElementById('tabFlutter').classList.add('hidden');
    document.getElementById('tabCore').classList.add('hidden');

    document.getElementById(tabId).classList.remove('hidden');

    document.getElementById('btnTabSpecs').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5';
    document.getElementById('btnTabFlutter').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5';
    document.getElementById('btnTabCore').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800 transition flex items-center gap-1.5';

    if (tabId === 'tabSpecs') {
        document.getElementById('btnTabSpecs').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-600 text-white transition flex items-center gap-1.5 shadow';
    } else if (tabId === 'tabFlutter') {
        document.getElementById('btnTabFlutter').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-600 text-white transition flex items-center gap-1.5 shadow';
    } else if (tabId === 'tabCore') {
        document.getElementById('btnTabCore').className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-600 text-white transition flex items-center gap-1.5 shadow';
    }
}

function simDoLogin() {
    const u = document.getElementById('simInputUser').value.trim();
    const p = document.getElementById('simInputPass').value.trim();
    const btn = document.getElementById('simBtnLogin');
    const err = document.getElementById('simLoginErr');

    if (!u || !p) {
        err.innerText = 'لطفاً نام کاربری و رمز را وارد فرمایید.';
        return;
    }

    err.innerText = '';
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال بررسی...';

    fetch('<?= Helpers::url('api/v1/app/login') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: jsonEncodeSafe({username: u, password: p})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> <span>ورود به حساب و اتصال</span>';

        if (data.success) {
            simAuthToken = data.data.auth_token;
            renderSimDashboard(data.data.client);
            fetchSimConfigs();
        } else {
            err.innerText = data.error || 'خطا در احراز هویت';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> <span>ورود به حساب و اتصال</span>';
        err.innerText = 'خطا در ارتباط با سرور.';
    });
}

function renderSimDashboard(client) {
    document.getElementById('simScreenLogin').classList.add('hidden');
    document.getElementById('simScreenDashboard').classList.remove('hidden');

    document.getElementById('simDispUser').innerText = client.username;
    document.getElementById('simUsedGb').innerText = client.traffic_used_gb;
    document.getElementById('simTotalGb').innerText = client.traffic_total_gb;
    document.getElementById('simDaysLeft').innerText = client.days_remaining;
    document.getElementById('simUsageBar').style.width = client.usage_percent + '%';

    const st = document.getElementById('simDispStatus');
    if (client.status === 'active') {
        st.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span><span>حساب فعال</span>';
        st.className = 'text-[9px] text-emerald-400 flex items-center gap-1';
    } else {
        st.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span><span>' + client.status + '</span>';
        st.className = 'text-[9px] text-rose-400 flex items-center gap-1';
    }
}

function fetchSimConfigs() {
    fetch('<?= Helpers::url('api/v1/app/configs') ?>', {
        headers: {'Authorization': 'Bearer ' + simAuthToken}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.servers) {
            simServers = data.data.servers;
            if (simServers.length > 0) {
                simSelectServer(0);
                buildServersListHtml();
            }
        }
    });
}

function simSelectServer(idx) {
    simActiveServerIndex = idx;
    const s = simServers[idx];
    if (s) {
        document.getElementById('simActiveFlag').innerText = s.flag;
        document.getElementById('simActiveServerName').innerText = s.name;
        document.getElementById('simActiveProto').innerText = s.protocol.toUpperCase() + ' (' + s.operator_name + ')';
    }
    simCloseServerModal();
}

function buildServersListHtml() {
    const c = document.getElementById('simServersList');
    c.innerHTML = '';
    simServers.forEach((s, idx) => {
        const item = document.createElement('div');
        const isActive = (idx === simActiveServerIndex);
        item.className = 'p-2.5 rounded-xl border flex items-center justify-between cursor-pointer transition ' + 
            (isActive ? 'bg-cyan-950/60 border-cyan-500/50 text-white' : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700');
        item.onclick = () => simSelectServer(idx);

        item.innerHTML = `
            <div class="flex items-center gap-2">
                <span class="text-base">${s.flag}</span>
                <div class="text-right">
                    <strong class="text-[11px] font-bold block leading-tight text-white">${s.name}</strong>
                    <span class="text-[9px] text-slate-400">${s.protocol.toUpperCase()} • ${s.operator_name}</span>
                </div>
            </div>
            <div class="text-left font-mono text-[10px] text-emerald-400 flex items-center gap-1">
                <span>${Math.floor(Math.random() * 25) + 30} ms</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            </div>
        `;
        c.appendChild(item);
    });
}

function simToggleConnect() {
    const btn = document.getElementById('simBtnConnect');
    const icon = document.getElementById('simPowerIcon');
    const label = document.getElementById('simConnLabel');
    const timer = document.getElementById('simTimer');
    const pulse = document.getElementById('simPulseRing');

    if (!simIsConnected) {
        // Connecting state
        label.innerText = 'در حال اتصال...';
        icon.className = 'fa-solid fa-spinner fa-spin text-3xl mb-1 text-cyan-400';
        
        setTimeout(() => {
            simIsConnected = true;
            pulse.classList.remove('hidden');
            btn.className = 'relative w-32 h-32 rounded-full bg-gradient-to-b from-cyan-600 to-indigo-700 border-4 border-cyan-400 text-white flex flex-col items-center justify-center transition-all duration-300 shadow-2xl shadow-cyan-500/50';
            icon.className = 'fa-solid fa-shield-halved text-3xl mb-1 text-white animate-bounce';
            label.innerText = 'متصل شد';
            timer.classList.remove('hidden');

            simSeconds = 0;
            simTimerInterval = setInterval(() => {
                simSeconds++;
                const hrs = String(Math.floor(simSeconds / 3600)).padStart(2, '0');
                const mins = String(Math.floor((simSeconds % 3600) / 60)).padStart(2, '0');
                const secs = String(simSeconds % 60).padStart(2, '0');
                timer.innerText = `${hrs}:${mins}:${secs}`;

                // Random speed jitter
                document.getElementById('simSpeedDown').innerText = (Math.floor(Math.random() * 800) + 200) + ' KB/s';
                document.getElementById('simSpeedUp').innerText = (Math.floor(Math.random() * 150) + 30) + ' KB/s';
            }, 1000);
        }, 800);
    } else {
        // Disconnecting
        clearInterval(simTimerInterval);
        simIsConnected = false;
        pulse.classList.add('hidden');
        btn.className = 'relative w-32 h-32 rounded-full bg-gradient-to-b from-slate-900 to-slate-950 border-4 border-slate-800 text-slate-300 flex flex-col items-center justify-center transition-all duration-300 shadow-2xl';
        icon.className = 'fa-solid fa-power-off text-3xl mb-1 text-slate-400';
        label.innerText = 'لمس برای اتصال';
        timer.classList.add('hidden');
        document.getElementById('simSpeedDown').innerText = '0 KB/s';
        document.getElementById('simSpeedUp').innerText = '0 KB/s';
    }
}

function simLogout() {
    if (simIsConnected) simToggleConnect();
    simAuthToken = '';
    document.getElementById('simScreenDashboard').classList.add('hidden');
    document.getElementById('simScreenLogin').classList.remove('hidden');
}

function simOpenServerModal() {
    document.getElementById('simModalServers').classList.remove('hidden');
}

function simCloseServerModal() {
    document.getElementById('simModalServers').classList.add('hidden');
}

function copyCode(id) {
    const text = document.getElementById(id).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('کد با موفقیت کپی شد ✅');
    });
}

function jsonEncodeSafe(obj) {
    return JSON.stringify(obj);
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php require __DIR__ . '/../layout/header.php'; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2"><i class="fa-solid fa-shield-halved text-cyan-400"></i> مدیریت پروکسی‌ها v4.0.21</h1>
            <p class="text-xs text-slate-400 mt-1">لینک پروکسی جدا برای هر کاربر - کلیک کن کپی کن + ارسال به مشتری - رایگان برای VPN + پلن پروکسی-only</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= Helpers::url('plans') ?>" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold rounded-xl">+ پلن پروکسی</a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-cyan-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-brands fa-telegram text-cyan-400"></i><span class="font-bold text-white text-sm">SOCKS5 + HTTP</span></div>
            <p class="text-xs text-slate-400">برای تلگرام و سایر برنامه‌ها، بدون نیاز به VPN - لینک جدا قابل کپی</p>
            <p class="text-[11px] text-emerald-300 mt-2">✅ کلیک → کپی → ارسال</p>
        </div>
        <div class="bg-slate-900 border border-violet-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-solid fa-paper-plane text-violet-400"></i><span class="font-bold text-white text-sm">MTProto</span></div>
            <p class="text-xs text-slate-400">مخصوص تلگرام، لینک https://t.me/proxy - مستقیم باز میشه</p>
            <p class="text-[11px] text-emerald-300 mt-2">✅ یک کلیک تا اتصال</p>
        </div>
        <div class="bg-slate-900 border border-amber-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-solid fa-tag text-amber-400"></i><span class="font-bold text-white text-sm">پلن پروکسی-only</span></div>
            <p class="text-xs text-slate-400">ارزان‌تر از VPN، فقط پروکسی - در کلاینت‌ها لینک جدا نمایش داده میشه</p>
            <p class="text-[11px] text-amber-300 mt-2">💰 درآمد جدید</p>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
        <h3 class="font-bold text-white text-sm mb-3 flex items-center gap-2"><i class="fa-solid fa-list text-cyan-400"></i> آخرین 100 کاربر فعال - لینک پروکسی جدا</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-400 border-b border-slate-800"><th class="p-2 text-right">یوزر</th><th class="p-2">سرور</th><th class="p-2">پلن</th><th class="p-2">وضعیت</th><th class="p-2 text-center">پروکسی - کپی جدا</th></tr></thead>
                <tbody>
                <?php foreach ($proxies as $p): ?>
                    <tr class="border-b border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-2 font-mono text-cyan-300"><?= htmlspecialchars($p['username']) ?></td>
                        <td class="p-2 text-slate-300"><?= htmlspecialchars($p['server_name']) ?> <span class="text-[10px] text-slate-500"><?= htmlspecialchars(parse_url($p['api_url'] ?? '', PHP_URL_HOST) ?: ($p['sub_domain'] ?? '')) ?></span></td>
                        <td class="p-2 text-slate-400"><?= htmlspecialchars($p['plan_title'] ?? '-') ?></td>
                        <td class="p-2"><span class="px-2 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px]"><?= htmlspecialchars($p['status']) ?></span></td>
                        <td class="p-2">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="openProxyModalFromList(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['username'], ENT_QUOTES) ?>')" class="px-3 py-1.5 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 rounded-lg text-[11px] font-bold"><i class="fa-solid fa-shield-halved ml-1"></i> پروکسی</button>
                                <a href="<?= Helpers::url('clients') ?>?search=<?= urlencode($p['username']) ?>" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-[10px]">کلاینت</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="bg-cyan-950/30 border border-cyan-800/30 rounded-2xl p-4 text-xs space-y-2">
        <p class="font-bold text-cyan-300">💡 راهنمای v4.0.21 - کپی پروکسی جدا:</p>
        <p class="text-slate-300">• در <b>کلاینت‌ها</b> دکمه <i class="fa-solid fa-shield-halved text-cyan-400"></i> اضافه شد - کلیک کن → مودال پروکسی باز میشه → هر لینک (SOCKS/HTTP/MTProto) را جدا کپی کن</p>
        <p class="text-slate-300">• در همین صفحه هم دکمه <b>پروکسی</b> برای هر یوزر هست</p>
        <p class="text-slate-300">• دکمه <i class="fa-solid fa-share-nodes text-emerald-400"></i> (سبز) الان <b>ساب + پروکسی</b> را با هم کپی میکنه برای ارسال کامل به مشتری</p>
        <p class="text-slate-300">• پلن پروکسی-only بساز: پلن‌ها → افزودن → تیک <code>🔒 فقط پروکسی</code> → قیمت ارزان‌تر</p>
        <p class="text-slate-300">• وقتی کلاینت با پلن پروکسی میسازی، باز هم دکمه پروکسی در لیست کلاینت‌ها لینک جدا نشون میده</p>
    </div>
</div>

<!-- Proxy Modal for proxies page -->
<div id="proxyModalList" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-[60]">
    <div class="bg-slate-900 border border-cyan-800/30 rounded-2xl max-w-xl w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeProxyModalList()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <h3 class="text-base font-bold text-white">پروکسی‌های <span id="proxyModalListUsername" class="font-mono text-cyan-400">user</span></h3>
                <p class="text-[11px] text-slate-400">کلیک → کپی → ارسال</p>
            </div>
        </div>
        <div id="proxyModalListLoading" class="py-8 text-center text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin ml-2"></i>در حال دریافت...</div>
        <div id="proxyModalListContent" class="hidden space-y-3">
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-white">SOCKS5</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300">تلگرام</span>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="proxyListSocksUrl" readonly class="flex-1 bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-[11px] text-cyan-300 font-mono text-left" dir="ltr">
                    <button onclick="copyToClipboard(document.getElementById('proxyListSocksUrl').value,this)" class="px-3 py-2 bg-purple-600 text-white rounded-lg text-xs font-bold">کپی</button>
                </div>
            </div>
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-white">HTTP</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300">مرورگر</span>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="proxyListHttpUrl" readonly class="flex-1 bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-[11px] text-amber-300 font-mono text-left" dir="ltr">
                    <button onclick="copyToClipboard(document.getElementById('proxyListHttpUrl').value,this)" class="px-3 py-2 bg-amber-600 text-white rounded-lg text-xs font-bold">کپی</button>
                </div>
            </div>
            <div class="bg-slate-950 border border-cyan-800/30 rounded-xl p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-brands fa-telegram text-cyan-400"></i> MTProto</span>
                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300">تلگرام</span>
                </div>
                <div class="flex gap-2 mb-2">
                    <input type="text" id="proxyListMtprotoUrl" readonly class="flex-1 bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-[11px] text-cyan-300 font-mono text-left" dir="ltr">
                    <button onclick="copyToClipboard(document.getElementById('proxyListMtprotoUrl').value,this)" class="px-3 py-2 bg-cyan-600 text-white rounded-lg text-xs font-bold">کپی</button>
                </div>
                <button onclick="if(document.getElementById('proxyListMtprotoUrl').value){window.open(document.getElementById('proxyListMtprotoUrl').value,'_blank')}" class="w-full py-2 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 rounded-lg text-xs font-bold"><i class="fa-brands fa-telegram ml-1"></i> باز کردن در تلگرام</button>
            </div>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-3">
                <button onclick="copyProxyListDelivery()" class="w-full py-2.5 bg-gradient-to-r from-cyan-600 to-violet-600 text-white font-bold rounded-xl text-xs"><i class="fa-solid fa-share-nodes ml-1"></i> کپی متن کامل برای مشتری</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentProxyListData = null;
function openProxyModalFromList(clientId, username){
    document.getElementById('proxyModalListUsername').innerText = username || clientId;
    document.getElementById('proxyModalListLoading').classList.remove('hidden');
    document.getElementById('proxyModalListContent').classList.add('hidden');
    const m=document.getElementById('proxyModalList'); m.classList.remove('hidden'); m.classList.add('flex');
    
    fetch('<?= Helpers::url('api/v1/admin/client-proxies') ?>?id='+clientId)
    .then(r=>r.json())
    .then(data=>{
        document.getElementById('proxyModalListLoading').classList.add('hidden');
        if(!data.success){
            document.getElementById('proxyModalListLoading').innerHTML = '<span class="text-rose-400">❌ '+ (data.error || 'خطا') +'</span>';
            document.getElementById('proxyModalListLoading').classList.remove('hidden');
            return;
        }
        const d = data.data;
        const p = d.proxies;
        currentProxyListData = d;
        document.getElementById('proxyListSocksUrl').value = p.dedicated?.socks?.url || '';
        document.getElementById('proxyListHttpUrl').value = p.dedicated?.http?.url || '';
        document.getElementById('proxyListMtprotoUrl').value = p.mtproto?.url || '';
        document.getElementById('proxyModalListContent').classList.remove('hidden');
    }).catch(e=>{
        document.getElementById('proxyModalListLoading').innerHTML = '<span class="text-rose-400">❌ خطا: '+e.message+'</span>';
    });
}
function closeProxyModalList(){ const m=document.getElementById('proxyModalList'); m.classList.remove('flex'); m.classList.add('hidden'); }
function copyProxyListDelivery(){
    if(!currentProxyListData) return;
    const d=currentProxyListData; const p=d.proxies;
    let text = `👤 یوزر: ${d.username}\n🔗 ساب:\n${d.sub_url}\n\n🛡️ SOCKS5:\n${p.dedicated?.socks?.url||''}\n\n🌐 HTTP:\n${p.dedicated?.http?.url||''}\n\n✈️ MTProto:\n${p.mtproto?.url||''}\n`;
    navigator.clipboard.writeText(text).then(()=>alert('✅ کپی شد!'));
}
function copyToClipboard(text, btn){
    if(!text) return;
    navigator.clipboard.writeText(text).then(()=>{
        if(btn){
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> کپی شد';
            setTimeout(()=>{ btn.innerHTML = orig; }, 2000);
        }
    }).catch(()=>{
        const ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        if(btn){ btn.innerHTML = '✅'; setTimeout(()=>{ btn.innerHTML = 'کپی'; }, 1500); }
    });
}
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>

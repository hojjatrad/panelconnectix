<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="relative overflow-hidden bg-gradient-to-br from-yellow-900/20 via-slate-900 to-amber-900/20 border border-yellow-500/20 rounded-[1.5rem] p-6">
        <div class="absolute -top-20 -right-20 w-72 h-72 bg-yellow-600/10 rounded-full blur-[60px]"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
            <div>
                <h2 class="text-xl font-black text-white flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-500 to-amber-500 flex items-center justify-center text-white"><i class="fa-solid fa-bell"></i></span>
                    مرکز اعلان‌ها ULTRA
                    <span class="text-[10px] bg-yellow-500/20 text-yellow-300 px-2.5 py-1 rounded-full border border-yellow-500/30">LIVE + SOUND</span>
                </h2>
                <p class="text-xs text-slate-400 mt-2">اعلان‌های سیستم، سرور آفلاین، درخواست‌ها، پرداخت‌ها - با صدا و فیلتر</p>
            </div>
            <div class="flex gap-2">
                <button onclick="toggleSound()" id="soundToggle" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white rounded-xl text-xs font-bold flex items-center gap-2"><i class="fa-solid fa-volume-high"></i> صدا: روشن</button>
                <?php if (Auth::isAdmin()): ?>
                <button onclick="openNewNoticeModal()" class="px-5 py-2.5 bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-500 hover:to-amber-500 text-white rounded-xl text-xs font-black shadow-lg flex items-center gap-2"><i class="fa-solid fa-plus"></i> اطلاعیه جدید</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2">
        <button onclick="filterNotifs('all')" class="filter-btn active px-4 py-2 bg-violet-600 text-white rounded-xl text-xs font-bold" data-filter="all">همه</button>
        <button onclick="filterNotifs('unread')" class="filter-btn px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold border border-slate-700" data-filter="unread">خوانده نشده <span class="bg-rose-500 text-white px-1.5 py-0.5 rounded-full text-[10px] ml-1"><?= count($notifications) ?></span></button>
        <button onclick="filterNotifs('server')" class="filter-btn px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold border border-slate-700" data-filter="server">سرور</button>
        <button onclick="filterNotifs('payment')" class="filter-btn px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold border border-slate-700" data-filter="payment">مالی</button>
        <button onclick="markAllRead()" class="mr-auto px-4 py-2 bg-emerald-900/20 hover:bg-emerald-900/30 text-emerald-300 border border-emerald-800/30 rounded-xl text-xs font-bold">✅ همه را خوانده علامت بزن</button>
    </div>

    <!-- Notifications List -->
    <div class="space-y-3" id="notifList">
        <?php if (empty($notifications)): ?>
            <div class="glass rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-800 mx-auto flex items-center justify-center text-slate-500 text-2xl mb-3"><i class="fa-solid fa-bell-slash"></i></div>
                <div class="text-white font-bold">اعلانی وجود ندارد</div>
                <div class="text-xs text-slate-400 mt-1">همه چیز آرام است - هیچ هشدار جدیدی نیست</div>
            </div>
        <?php else: foreach ($notifications as $n):
            $isServer = str_contains($n['title'],'سرور') || str_contains($n['message'],'سرور');
            $isPayment = str_contains($n['title'],'پرداخت') || str_contains($n['title'],'فروش') || str_contains($n['message'],'تومان');
            $type = $isServer ? 'server' : ($isPayment ? 'payment' : 'general');
            $icon = $isServer ? 'fa-server text-rose-400' : ($isPayment ? 'fa-credit-card text-emerald-400' : 'fa-bullhorn text-yellow-400');
            $bg = $isServer ? 'border-rose-800/30 bg-rose-950/10' : ($isPayment ? 'border-emerald-800/30 bg-emerald-950/10' : 'border-yellow-800/20 bg-yellow-950/10');
        ?>
            <div class="notif-item glass rounded-2xl p-5 border <?= $bg ?> hover:scale-[1.01] transition-all duration-300 group" data-type="<?= $type ?>" data-read="false">
                <div class="flex items-start gap-4">
                    <span class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center shrink-0 group-hover:scale-110 transition"><i class="fa-solid <?= $icon ?>"></i></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-black text-white text-sm truncate"><?= htmlspecialchars($n['title']) ?></h3>
                            <span class="w-2 h-2 bg-violet-500 rounded-full animate-pulse shrink-0"></span>
                            <span class="text-[10px] text-slate-500 font-mono mr-auto"><?= htmlspecialchars($n['created_at']) ?></span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                        <div class="mt-3 flex gap-2">
                            <button onclick="markRead(this)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-[11px] font-bold border border-slate-700 transition">خوانده شد</button>
                            <button onclick="this.closest('.notif-item').remove()" class="px-3 py-1.5 bg-slate-800/50 hover:bg-rose-900/20 text-slate-400 hover:text-rose-300 rounded-xl text-[11px] transition">حذف</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        
        <!-- Simulated live notifications for demo -->
        <div class="notif-item glass rounded-2xl p-5 border border-emerald-800/30 bg-emerald-950/10" data-type="server" data-read="false">
            <div class="flex items-start gap-4">
                <span class="w-10 h-10 rounded-xl bg-emerald-900/30 border border-emerald-800/30 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-heart-pulse"></i></span>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1"><h3 class="font-black text-white text-sm">✅ سرور آنلاین شد</h3><span class="text-[10px] text-slate-500 font-mono mr-auto">همین الان</span></div>
                    <p class="text-xs text-slate-300">سرور آلمان VIP با تاخیر 45ms آنلاین شد - آپتایم 99.9%</p>
                </div>
            </div>
        </div>
        <div class="notif-item glass rounded-2xl p-5 border border-cyan-800/30 bg-cyan-950/10" data-type="payment" data-read="false">
            <div class="flex items-start gap-4">
                <span class="w-10 h-10 rounded-xl bg-cyan-900/30 border border-cyan-800/30 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-cart-shopping"></i></span>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1"><h3 class="font-black text-white text-sm">💰 فروش جدید</h3><span class="text-[10px] text-slate-500 font-mono mr-auto">5 دقیقه پیش</span></div>
                    <p class="text-xs text-slate-300">نماینده novinvpn یک پلن 3 ماهه فروخت - 450,000 تومان</p>
                </div>
            </div>
        </div>
    </div>

    <audio id="notifSound" src="data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA==" preload="auto"></audio>
</div>

<!-- Modal New Notice (Admin) -->
<?php if (Auth::isAdmin()): ?>
<div id="newNoticeModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="closeNewNoticeModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white w-8 h-8 bg-slate-800 rounded-xl flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="text-base font-black text-white mb-5 flex items-center gap-2"><i class="fa-solid fa-bullhorn text-yellow-400"></i> اطلاعیه سراسری جدید</h3>
        <form action="<?= Helpers::url('notifications/store') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <div><label class="block text-slate-300 mb-1.5 text-xs font-bold">عنوان *</label><input type="text" name="title" required placeholder="مثلاً: ارتقای سرورهای آلمان" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-sm focus:border-violet-500 outline-none"></div>
            <div><label class="block text-slate-300 mb-1.5 text-xs font-bold">متن *</label><textarea name="message" rows="4" required placeholder="متن اطلاعیه..." class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-sm focus:border-violet-500 outline-none"></textarea></div>
            <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="send_telegram" value="1" id="sendTgCheck" onchange="document.getElementById('segmentOptions').classList.toggle('hidden', !this.checked)" class="rounded"><span class="text-xs text-white font-bold">ارسال به ربات تلگرام 🤖</span></label>
                <div id="segmentOptions" class="hidden mt-3 pt-3 border-t border-slate-800 space-y-2">
                    <select name="target_segment" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs"><option value="all">🌐 همه کاربران</option><option value="active">🟢 فقط فعال</option><option value="expired">🔴 فقط منقضی</option><option value="cluster">🖥 خوشه خاص</option></select>
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-500 hover:to-amber-500 text-white font-black rounded-xl shadow-lg transition">📢 انتشار اطلاعیه</button>
        </form>
    </div>
</div>
<script>
function openNewNoticeModal(){ document.getElementById('newNoticeModal').classList.remove('hidden'); document.getElementById('newNoticeModal').classList.add('flex'); }
function closeNewNoticeModal(){ document.getElementById('newNoticeModal').classList.add('hidden'); document.getElementById('newNoticeModal').classList.remove('flex'); }
function filterNotifs(type){
    document.querySelectorAll('.filter-btn').forEach(b=>{ b.classList.remove('bg-violet-600','text-white'); b.classList.add('bg-slate-800','text-slate-300'); });
    document.querySelector(`[data-filter="${type}"]`).classList.add('bg-violet-600','text-white'); document.querySelector(`[data-filter="${type}"]`).classList.remove('bg-slate-800','text-slate-300');
    document.querySelectorAll('.notif-item').forEach(el=>{ if(type==='all' || el.dataset.type===type) el.classList.remove('hidden'); else el.classList.add('hidden'); });
}
function markRead(btn){ const item=btn.closest('.notif-item'); item.dataset.read='true'; item.style.opacity='0.6'; btn.textContent='✅ خوانده شد'; playSound(); }
function markAllRead(){ document.querySelectorAll('.notif-item').forEach(el=>{ el.dataset.read='true'; el.style.opacity='0.6'; }); playSound(); }
let soundEnabled = localStorage.getItem('notif_sound') !== '0';
function toggleSound(){ soundEnabled=!soundEnabled; localStorage.setItem('notif_sound', soundEnabled?'1':'0'); document.getElementById('soundToggle').innerHTML=soundEnabled?'<i class="fa-solid fa-volume-high"></i> صدا: روشن':'<i class="fa-solid fa-volume-xmark"></i> صدا: خاموش'; }
function playSound(){ if(!soundEnabled) return; try{ const ctx=new (window.AudioContext||window.webkitAudioContext)(); const osc=ctx.createOscillator(); const gain=ctx.createGain(); osc.connect(gain); gain.connect(ctx.destination); osc.frequency.value=800; gain.gain.setValueAtTime(0.3, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime+0.3); osc.start(); osc.stop(ctx.currentTime+0.3); }catch(e){} }
// Init sound toggle text
document.addEventListener('DOMContentLoaded', ()=>{ document.getElementById('soundToggle').innerHTML=soundEnabled?'<i class="fa-solid fa-volume-high"></i> صدا: روشن':'<i class="fa-solid fa-volume-xmark"></i> صدا: خاموش'; });
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>

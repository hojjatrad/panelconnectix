<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-server text-emerald-400"></i>
            <span>مدیریت سرورها و نودهای شبکه (Multi-Core Nodes)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">اتصال مستقیم به مرزبان (Marzban)، پاسارگاد (Pasargad) و ۳x-ui با پایش زنده پینگ و ترافیک</p>
    </div>

    <div class="flex items-center flex-wrap gap-2">
        <button onclick="pingAllServers()" class="px-4 py-2 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-gauge-high"></i>
            <span>پایش و تست پینگ تمام نودها</span>
        </button>

        <form method="POST" action="<?= Helpers::url('servers/sync') ?>" class="m-0">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>همگام‌سازی لحظه‌ای نودها</span>
            </button>
        </form>

        <button onclick="openNewServerModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>افزودن سرور جدید</span>
        </button>
    </div>
</div>

<!-- Server Nodes Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <?php foreach ($servers as $s): 
        $stat = $serverStats[$s['id']] ?? ['status' => 'unknown'];
    ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4 relative group" id="server-card-<?= $s['id'] ?>">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span id="dot-<?= $s['id'] ?>" class="w-2.5 h-2.5 rounded-full <?= ($stat['status'] ?? '') === 'online' ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
                        <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($s['name']) ?></h3>
                    </div>
                    <span class="text-xs text-slate-400 font-mono block mt-1"><?= htmlspecialchars($s['sub_domain'] ?: $s['api_url']) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span id="ping-badge-<?= $s['id'] ?>" class="px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                        ⚡ -- ms
                    </span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase font-mono bg-slate-800 text-purple-400 border border-slate-700">
                        <?= htmlspecialchars($s['driver']) ?>
                    </span>
                </div>
            </div>

            <!-- Server Metrics Grid -->
            <div class="grid grid-cols-4 gap-2 bg-slate-800/40 p-3 rounded-xl border border-slate-800 text-center text-xs">
                <div>
                    <span class="text-[10px] text-slate-400 block mb-0.5">وضعیت سرویس</span>
                    <span id="status-text-<?= $s['id'] ?>" class="font-bold <?= ($stat['status'] ?? '') === 'online' ? 'text-emerald-400' : 'text-amber-400' ?>">
                        <?= ($stat['status'] ?? '') === 'online' ? 'برخط' : 'آماده' ?>
                    </span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block mb-0.5">کاربران متصل</span>
                    <span class="font-bold text-white"><?= $stat['users'] ?? 0 ?> کاربر</span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block mb-0.5">کلاینت‌های ثبت‌شده</span>
                    <span class="font-bold text-purple-300"><?= $s['client_count'] ?? 0 ?> اکانت</span>
                </div>
                <div>
                    <span class="text-[10px] text-slate-400 block mb-0.5">گروه کلاستر</span>
                    <span class="font-bold text-cyan-300 font-mono text-[11px]"><?= $s['server_group'] ?></span>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-800">
                <span class="text-slate-400 font-mono text-[11px]">حداکثر ظرفیت: <?= $s['max_clients'] ?> کلاینت</span>
                
                <div class="flex items-center gap-1.5">
                    <button onclick="pingSingleServer(<?= $s['id'] ?>)" title="تست پینگ و زمان پاسخگویی" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-300 rounded-lg text-xs font-medium border border-slate-700 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-gauge text-[10px]"></i>
                        <span>پینگ</span>
                    </button>

                    <button onclick="testServerConnection(<?= $s['id'] ?>, this)" title="احراز هویت و دریافت آمار کامل" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-emerald-300 rounded-lg text-xs font-medium border border-slate-700 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-bolt-lightning text-[10px]"></i>
                        <span>تست API</span>
                    </button>

                    <button onclick='openEditServerModal(<?= json_encode($s) ?>)' title="ویرایش اطلاعات سرور" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 rounded-lg text-xs border border-slate-700 transition-colors">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>

                    <?php if (($s['client_count'] ?? 0) == 0): ?>
                        <form method="POST" action="<?= Helpers::url('servers/delete') ?>" class="m-0" onsubmit="return confirm('آیا از حذف این سرور اطمینان دارید؟');">
                            <?= Helpers::csrfField() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" title="حذف سرور" class="p-1.5 bg-slate-800 hover:bg-rose-900/50 text-rose-400 rounded-lg text-xs border border-slate-700 hover:border-rose-700 transition-colors">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <button disabled title="برای حذف سرور ابتدا کلاینت‌های آن را منتقل کنید" class="p-1.5 bg-slate-800/40 text-slate-600 rounded-lg text-xs border border-slate-800 cursor-not-allowed">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal to add Server -->
<div id="newServerModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
        <button onclick="closeNewServerModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-plus-circle text-emerald-400"></i>
            <span>اتصال سرور و نود جدید</span>
        </h3>

        <form action="<?= Helpers::url('servers/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">عنوان سرور *</label>
                <input type="text" name="name" required placeholder="مثلاً: سرور آلمان هتزنر شماره ۱" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">هسته و نوع پنل *</label>
                    <select name="driver" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        <option value="marzban">Marzban (مرزبان)</option>
                        <option value="pasargad">Pasargad (پاسارگاد)</option>
                        <option value="3xui">3x-ui / X-UI</option>
                        <option value="mock">Mock Node (شبیه‌ساز تستی)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">گروه سرور (کلاستر)</label>
                    <select name="server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="default">عادی (Default)</option>
                        <option value="economic">اقتصادی (Economic)</option>
                        <option value="iran_access">ایران اکسس (Iran Access)</option>
                        <option value="vip">تجاری VIP (Business Class)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">آدرس API سرور (با پورت و پروتکل) *</label>
                <input type="url" name="api_url" required dir="ltr" placeholder="https://vpn.example.com:8000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام کاربری / ادمین</label>
                    <input type="text" name="api_username" dir="ltr" placeholder="admin" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رمز عبور / Secret</label>
                    <input type="password" name="api_password" dir="ltr" placeholder="••••••••" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه سابسکریپشن / CDN</label>
                    <input type="text" name="sub_domain" dir="ltr" placeholder="de1.connectix.space" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">حداکثر ظرفیت کاربر</label>
                    <input type="number" name="max_clients" value="500" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                اتصال و ذخیره سرور
            </button>
        </form>
    </div>
</div>

<!-- Modal to edit Server -->
<div id="editServerModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
        <button onclick="closeEditServerModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-amber-400"></i>
            <span>ویرایش اطلاعات سرور</span>
        </h3>

        <form action="<?= Helpers::url('servers/update') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="id" id="edit_server_id">

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">عنوان سرور *</label>
                <input type="text" name="name" id="edit_server_name" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">هسته و نوع پنل *</label>
                    <select name="driver" id="edit_server_driver" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                        <option value="marzban">Marzban (مرزبان)</option>
                        <option value="pasargad">Pasargad (پاسارگاد)</option>
                        <option value="3xui">3x-ui / X-UI</option>
                        <option value="mock">Mock Node (شبیه‌ساز تستی)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">گروه سرور (کلاستر)</label>
                    <select name="server_group" id="edit_server_group" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                        <option value="default">عادی (Default)</option>
                        <option value="economic">اقتصادی (Economic)</option>
                        <option value="iran_access">ایران اکسس (Iran Access)</option>
                        <option value="vip">تجاری VIP (Business Class)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">آدرس API سرور *</label>
                <input type="url" name="api_url" id="edit_server_api_url" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">نام کاربری ادمین</label>
                    <input type="text" name="api_username" id="edit_server_api_username" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">رمز عبور (خالی بگذارید تا تغییر نکند)</label>
                    <input type="password" name="api_password" dir="ltr" placeholder="••••••••" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">دامنه سابسکریپشن / CDN</label>
                    <input type="text" name="sub_domain" id="edit_server_sub_domain" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">حداکثر ظرفیت کاربر</label>
                    <input type="number" name="max_clients" id="edit_server_max_clients" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2">
                ذخیره تغییرات سرور
            </button>
        </form>
    </div>
</div>

<script>
    const serverIds = <?= json_encode(array_column($servers, 'id')) ?>;

    function openNewServerModal() {
        document.getElementById('newServerModal').classList.remove('hidden');
        document.getElementById('newServerModal').classList.add('flex');
    }
    function closeNewServerModal() {
        document.getElementById('newServerModal').classList.remove('flex');
        document.getElementById('newServerModal').classList.add('hidden');
    }

    function openEditServerModal(s) {
        document.getElementById('edit_server_id').value = s.id;
        document.getElementById('edit_server_name').value = s.name;
        document.getElementById('edit_server_driver').value = s.driver;
        document.getElementById('edit_server_group').value = s.server_group;
        document.getElementById('edit_server_api_url').value = s.api_url;
        document.getElementById('edit_server_api_username').value = s.api_username || '';
        document.getElementById('edit_server_sub_domain').value = s.sub_domain || '';
        document.getElementById('edit_server_max_clients').value = s.max_clients || 500;

        document.getElementById('editServerModal').classList.remove('hidden');
        document.getElementById('editServerModal').classList.add('flex');
    }
    function closeEditServerModal() {
        document.getElementById('editServerModal').classList.remove('flex');
        document.getElementById('editServerModal').classList.add('hidden');
    }

    function pingSingleServer(id) {
        const badge = document.getElementById('ping-badge-' + id);
        const dot = document.getElementById('dot-' + id);
        const statusText = document.getElementById('status-text-' + id);

        badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[10px]"></i>';
        badge.className = 'px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-slate-800 text-cyan-400 border border-slate-700';

        fetch('<?= Helpers::url('servers/ping') ?>?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.latency !== null) {
                    let color = 'text-emerald-400 border-emerald-500/30 bg-emerald-950/40';
                    if (data.latency > 150) color = 'text-amber-400 border-amber-500/30 bg-amber-950/40';
                    if (data.latency > 300) color = 'text-rose-400 border-rose-500/30 bg-rose-950/40';

                    badge.className = 'px-2 py-0.5 rounded text-[11px] font-mono font-bold border ' + color;
                    badge.innerHTML = '⚡ ' + data.latency + ' ms';
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse';
                    statusText.innerText = 'برخط';
                    statusText.className = 'font-bold text-emerald-400';
                } else {
                    badge.className = 'px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-950/50 text-rose-400 border border-rose-800/40';
                    badge.innerHTML = '✖ قطع';
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-rose-500';
                    statusText.innerText = 'آفلاین';
                    statusText.className = 'font-bold text-rose-400';
                }
            })
            .catch(() => {
                badge.className = 'px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-950/50 text-rose-400 border border-rose-800/40';
                badge.innerHTML = 'خطا';
            });
    }

    function pingAllServers() {
        serverIds.forEach(id => {
            pingSingleServer(id);
        });
    }

    function testServerConnection(id, btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> بررسی...';
        btn.disabled = true;

        fetch('<?= Helpers::url('servers/test') ?>?id=' + id)
            .then(res => res.json())
            .then(data => {
                alert(data.message + '\nکاربران فعال: ' + (data.stats ? data.stats.users : '۰'));
                btn.innerHTML = original;
                btn.disabled = false;
            })
            .catch(err => {
                alert('خطا در تست: ' + err);
                btn.innerHTML = original;
                btn.disabled = false;
            });
    }

    // Auto-ping servers on page load
    document.addEventListener('DOMContentLoaded', () => {
        pingAllServers();
    });
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

<?php
require __DIR__ . '/../layout/header.php';
?>

<style>
.modal-overlay {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
.modal-scroll-body {
    max-height: calc(88vh - 130px);
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
</style>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-server text-emerald-400"></i>
            <span>مدیریت سرورها و نودهای شبکه (Multi-Core Nodes)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">اتصال مستقیم به مرزبان (Marzban)، پاسارگاد (Pasargad) و ۳x-ui با پایش زنده پینگ و ترافیک</p>
    </div>

    <div class="flex items-center flex-wrap gap-2">
        <a href="<?= Helpers::url('servers/health-check') ?>" class="px-4 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>پایش سلامت نودها و سوئیچ هوشمند (Failover)</span>
        </a>

        <button onclick="openMigrateModal()" class="px-4 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-people-carry-box"></i>
            <span>مهاجرت دسته‌جمعی</span>
        </button>

        <form method="POST" action="<?= Helpers::url('servers/sync') ?>" class="m-0">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span>همگام‌سازی لحظه‌ای نودها</span>
            </button>
        </form>

        <a href="<?= Helpers::url('categories') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-purple-300 border border-slate-700 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-layer-group"></i>
            <span>مدیریت خوشه‌ها و لوکیشن‌ها</span>
        </a>

        <button onclick="openNewServerModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>افزودن سرور جدید</span>
        </button>

        <?php if (!empty($servers)): ?>
        <form method="POST" action="<?= Helpers::url('servers/clear-all') ?>" class="m-0" onsubmit="return confirm('⚠️ اخطار بسیار مهم:\nآیا از خام‌سازی و پاکسازی تمامی سرورها اطمینان دارید؟\nکلیه سرورهای قبلی حذف خواهند شد تا بتوانید سرورهای اختصاصی خود را تعریف کنید.');">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-3 py-2 bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 border border-rose-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5" title="پاکسازی تمامی سرورها جهت معرفی سرور جدید">
                <i class="fa-solid fa-trash-can text-[11px]"></i>
                <span>خام‌سازی سرورها</span>
            </button>
        </form>
        <?php endif; ?>

        <form method="POST" action="<?= Helpers::url('servers/purge-all-samples') ?>" class="m-0" onsubmit="return confirm('⚠️ اخطار بسیار مهم:\nآیا از پاکسازی کامل تمامی پلن‌های نمونه، سفارشات تستی و سرورهای ماک اطمینان دارید؟\nسیستم کاملاً خام خواهد شد تا بتوانید سرور و پلن‌های اختصاصی خود را از اول تعریف کنید.');">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5" title="پاکسازی تمامی نمونه‌ها و شروع تمیز از صفر">
                <i class="fa-solid fa-broom text-[11px]"></i>
                <span>پاکسازی کامل نمونه‌ها (شروع تمیز)</span>
            </button>
        </form>
    </div>
</div>

<!-- Server Nodes Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <?php if (empty($servers)): ?>
        <div class="col-span-1 md:col-span-2 bg-slate-900/60 border border-dashed border-slate-800 rounded-3xl p-12 text-center space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mx-auto flex items-center justify-center text-3xl shadow-lg">
                <i class="fa-solid fa-server"></i>
            </div>
            <h3 class="text-base font-bold text-white">بخش سرورها خام و آماده معرفی نودهای شماست</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
                کلیه سرورهای آزمایشی پیش‌فرض پاکسازی شدند. اکنون می‌توانید با خیال راحت سرورهای واقعی خود را با هسته‌های مرزبان (Marzban)، پاسارگاد (Pasargad) یا ۳x-ui اضافه کنید و تست‌های نهایی را انجام دهید.
            </p>
            <button onclick="openNewServerModal()" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-900/30 inline-flex items-center gap-2">
                <i class="fa-solid fa-plus"></i>
                <span>افزودن اولین سرور</span>
            </button>
        </div>
    <?php else: ?>
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
                    <span id="ping-badge-<?= $s['id'] ?>" class="px-2 py-0.5 rounded text-[11px] font-mono font-semibold <?= (($s['health_status'] ?? '') === 'offline') ? 'bg-rose-500/10 text-rose-400 border border-rose-500/30' : 'bg-slate-800 text-cyan-300 border border-slate-700' ?>">
                        ⚡ <?= !empty($s['latency_ms']) ? $s['latency_ms'] . ' ms' : '-- ms' ?>
                    </span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase font-mono bg-slate-800 text-purple-400 border border-slate-700">
                        <?= htmlspecialchars($s['driver']) ?>
                    </span>
                </div>
            </div>

            <!-- Server Metrics Grid -->
            <div class="grid grid-cols-4 gap-2 bg-slate-800/40 p-3 rounded-xl border border-slate-800 text-center text-xs">
                <div>
                    <span class="text-[10px] text-slate-400 block mb-0.5">وضعیت سلامت نود</span>
                    <span id="status-text-<?= $s['id'] ?>" class="font-bold <?= match($s['health_status'] ?? 'online') {
                        'offline' => 'text-rose-400',
                        'degraded' => 'text-amber-400',
                        default => 'text-emerald-400'
                    } ?>">
                        <?= match($s['health_status'] ?? 'online') {
                            'offline' => 'قطع (Failover)',
                            'degraded' => 'تاخیر بالا',
                            default => 'سالم و فعال'
                        } ?>
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
                    <span class="text-[10px] text-slate-400 block mb-0.5">دسته‌بندی (کلاستر)</span>
                    <span class="inline-flex items-center gap-1 font-bold font-mono text-[11px] <?= match($s['category_color'] ?? 'purple') {
                        'amber' => 'text-amber-400',
                        'blue' => 'text-blue-400',
                        'emerald', 'green' => 'text-emerald-400',
                        'cyan' => 'text-cyan-400',
                        'rose', 'red' => 'text-rose-400',
                        default => 'text-purple-300'
                    } ?>">
                        <i class="fa-solid <?= htmlspecialchars($s['category_icon'] ?: 'fa-server') ?> text-[10px]"></i>
                        <span><?= htmlspecialchars($s['category_name'] ?: $s['server_group']) ?></span>
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-800">
                <span class="text-slate-400 font-mono text-[11px]">حداکثر ظرفیت: <?= (empty($s['max_clients']) || (int)$s['max_clients'] <= 0) ? '<span class="text-emerald-400 font-sans font-bold">نامحدود (∞)</span>' : (number_format((int)$s['max_clients']) . ' کلاینت') ?></span>
                
                <div class="flex items-center gap-1.5">
                    <button onclick="pingSingleServer(<?= $s['id'] ?>)" title="تست پینگ و زمان پاسخگویی" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-300 rounded-lg text-xs font-medium border border-slate-700 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-gauge text-[10px]"></i>
                        <span>پینگ</span>
                    </button>

                    <button onclick="testServerConnection(<?= $s['id'] ?>, this)" title="احراز هویت و دریافت آمار کامل" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-emerald-300 rounded-lg text-xs font-medium border border-slate-700 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-bolt-lightning text-[10px]"></i>
                        <span>تست API</span>
                    </button>

                    <a href="<?= Helpers::url('servers/' . (int)$s['id'] . '/node-users') ?>" title="مشاهده و مدیریت کلاینت‌های این سرور (همه کلاینت‌های تعریف‌شده روی پنل سرور)" class="px-2.5 py-1.5 bg-cyan-900/40 hover:bg-cyan-800/60 text-cyan-300 rounded-lg text-xs font-medium border border-cyan-800/50 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-users text-[10px]"></i>
                        <span>کلاینت‌های سرور</span>
                    </a>

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
    <?php endif; ?>
</div>

<!-- Modal to add Server -->
<div id="newServerModal" class="fixed inset-0 bg-black/85 backdrop-blur-md hidden z-50 overflow-y-auto modal-overlay" style="-webkit-overflow-scrolling: touch;">
    <div class="min-h-full w-full flex items-center justify-center p-2 sm:p-4 md:py-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full shadow-2xl relative my-auto flex flex-col max-h-[90vh] overflow-hidden modal-box">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-800 bg-slate-900 shrink-0">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle text-emerald-400"></i>
                    <span>اتصال سرور و نود جدید</span>
                </h3>
                <button type="button" onclick="closeNewServerModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Scrollable Body -->
            <form action="<?= Helpers::url('servers/store') ?>" method="POST" id="newServerForm" class="flex flex-col flex-1 overflow-hidden">
                <?= Helpers::csrfField() ?>

                <div class="overflow-y-auto p-4 sm:p-5 space-y-4 text-xs flex-1 overscroll-contain modal-scroll-body" style="scrollbar-width: thin; scrollbar-color: #8b5cf6 #1e293b;">
                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">عنوان سرور *</label>
                        <input type="text" name="name" required placeholder="مثلاً: سرور آلمان هتزنر شماره ۱" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">هسته و نوع پنل *</label>
                            <select name="driver" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                                <option value="auto" selected>✨ تشخیص ۱۰۰٪ خودکار (پاسارگاد / مرزبان / 3X-UI)</option>
                                <option value="pasargad">Pasargad (پاسارگاد)</option>
                                <option value="marzban">Marzban (مرزبان)</option>
                                <option value="3xui">3x-ui / X-UI</option>
                                <option value="mock">Mock Node (شبیه‌ساز تستی)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">دسته‌بندی (کلاستر) *</label>
                            <select name="category_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= htmlspecialchars($cat['slug']) ?>)</option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">پیش‌فرض (Default)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">آدرس API سرور (با پورت و پروتکل) *</label>
                        <input type="url" name="api_url" required dir="ltr" oninput="autoFillCdn(this, 'new')" placeholder="https://vpn.example.com:8000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
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

                    <!-- MirzaPro-Style Smart Inbound & CDN Discovery Card -->
                    <div class="p-3.5 bg-gradient-to-br from-purple-950/50 via-slate-900 to-indigo-950/40 border border-purple-500/50 rounded-2xl space-y-2.5 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white flex items-center gap-1.5 text-xs">
                                <i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i>
                                <span>تنظیم ۱۰۰٪ خودکار اینباندها و CDN (استایل میرزاپرو)</span>
                            </span>
                            <span class="text-[10px] text-emerald-300 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-800 font-bold">هوشمند</span>
                        </div>

                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold text-[11px]">
                                نام کاربری یک کاربر فعال در سرور (مانند میرزاپرو)
                            </label>
                            <div class="flex gap-2">
                                <input type="text" name="sample_username" dir="ltr" placeholder="مثلاً: test یا هر اکانت فعال در پنل" class="w-full bg-slate-950 border border-purple-700/60 rounded-xl px-3 py-2 text-white font-mono text-xs focus:border-purple-400">
                                <button type="button" onclick="detectInboundsAndSample('new')" id="btn_detect_new" class="shrink-0 px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-purple-900/40 flex items-center gap-1.5">
                                    <i class="fa-solid fa-bolt"></i>
                                    <span>استخراج خودکار</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-purple-200/80 mt-1 leading-relaxed">
                                با زدن دکمه استخراج، سیستم نوع پنل (پاسارگاد/مرزبان)، کلیه اینباندهای فعال سرور و دامنه CDN را مستقیماً از روی کانفیگ‌های این کاربر می‌خواند و فرم را خودکار پر می‌کند.
                            </p>
                        </div>

                        <div id="inbounds_container_new" class="hidden space-y-3 p-3.5 bg-slate-950/90 border border-purple-900/40 rounded-xl text-xs"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold flex items-center justify-between">
                                <span>دامنه CDN / ساب‌لینک</span>
                                <span class="text-[10px] text-emerald-400 font-normal">خودکار تکمیل می‌شود</span>
                            </label>
                            <input type="text" name="sub_domain" dir="ltr" placeholder="خودکار تکمیل می‌شود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">در صورت خالی بودن، به صورت هوشمند از آدرس سرور تکمیل می‌شود.</span>
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">حداکثر ظرفیت کاربر</label>
                            <input type="number" name="max_clients" value="0" min="0" placeholder="0 = نامحدود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">عدد 0 یعنی ظرفیت نامحدود (∞)</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold flex items-center justify-between">
                            <span>الگوی کانفیگ اختصاصی سرور (VLESS Reality / Trojan)</span>
                            <span class="text-[10px] text-cyan-400 font-normal">اختیاری - پشتیبانی از {uuid}</span>
                        </label>
                        <textarea name="config_template" rows="2" dir="ltr" placeholder="اختیاری - با دکمه استخراج خودکار بالا تکمیل می‌گردد" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-[11px] leading-relaxed"></textarea>
                    </div>

                    <div id="new_test_result" class="hidden p-3 rounded-xl text-xs transition-all"></div>
                </div>

                <!-- Modal Sticky Footer (Always Visible at bottom) -->
                <div class="p-4 border-t border-slate-800 bg-slate-900 shrink-0 flex items-center gap-2">
                    <button type="button" onclick="testRawInModal('new')" id="btn_test_new" class="w-1/2 py-3 bg-slate-800 hover:bg-slate-700 text-cyan-300 font-bold rounded-xl text-xs border border-cyan-800/40 transition flex items-center justify-center gap-1.5 shadow">
                        <i class="fa-solid fa-bolt-lightning text-cyan-400"></i>
                        <span>تست زنده اتصال</span>
                    </button>
                    <button type="submit" class="w-1/2 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-900/40 flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>اتصال و ذخیره سرور</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal to edit Server -->
<div id="editServerModal" class="fixed inset-0 bg-black/85 backdrop-blur-md hidden z-50 overflow-y-auto modal-overlay" style="-webkit-overflow-scrolling: touch;">
    <div class="min-h-full w-full flex items-center justify-center p-2 sm:p-4 md:py-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full shadow-2xl relative my-auto flex flex-col max-h-[90vh] overflow-hidden modal-box">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-800 bg-slate-900 shrink-0">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-amber-400"></i>
                    <span>ویرایش اطلاعات سرور</span>
                </h3>
                <button type="button" onclick="closeEditServerModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Scrollable Body -->
            <form action="<?= Helpers::url('servers/update') ?>" method="POST" id="editServerForm" class="flex flex-col flex-1 overflow-hidden">
                <?= Helpers::csrfField() ?>
                <input type="hidden" name="id" id="edit_server_id">

                <div class="overflow-y-auto p-4 sm:p-5 space-y-4 text-xs flex-1 overscroll-contain modal-scroll-body" style="scrollbar-width: thin; scrollbar-color: #8b5cf6 #1e293b;">
                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">عنوان سرور *</label>
                        <input type="text" name="name" id="edit_server_name" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">هسته و نوع پنل *</label>
                            <select name="driver" id="edit_server_driver" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                                <option value="auto">✨ تشخیص ۱۰۰٪ خودکار (پاسارگاد / مرزبان / 3X-UI)</option>
                                <option value="pasargad">Pasargad (پاسارگاد)</option>
                                <option value="marzban">Marzban (مرزبان)</option>
                                <option value="3xui">3x-ui / X-UI</option>
                                <option value="mock">Mock Node (شبیه‌ساز تستی)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">دسته‌بندی (کلاستر) *</label>
                            <select name="category_id" id="edit_server_category_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= htmlspecialchars($cat['slug']) ?>)</option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">پیش‌فرض (Default)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold">آدرس API سرور *</label>
                        <input type="url" name="api_url" id="edit_server_api_url" required dir="ltr" oninput="autoFillCdn(this, 'edit')" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
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

                    <!-- MirzaPro-Style Smart Inbound & CDN Discovery Card -->
                    <div class="p-3.5 bg-gradient-to-br from-purple-950/50 via-slate-900 to-indigo-950/40 border border-purple-500/50 rounded-2xl space-y-2.5 shadow-lg">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white flex items-center gap-1.5 text-xs">
                                <i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i>
                                <span>تنظیم ۱۰۰٪ خودکار اینباندها و CDN (استایل میرزاپرو)</span>
                            </span>
                            <span class="text-[10px] text-emerald-300 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-800 font-bold">هوشمند</span>
                        </div>

                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold text-[11px]">
                                نام کاربری یک کاربر فعال در سرور (مانند میرزاپرو)
                            </label>
                            <div class="flex gap-2">
                                <input type="text" name="sample_username" id="edit_sample_username" dir="ltr" placeholder="مثلاً: test یا هر اکانت فعال در پنل" class="w-full bg-slate-950 border border-purple-700/60 rounded-xl px-3 py-2 text-white font-mono text-xs focus:border-purple-400">
                                <button type="button" onclick="detectInboundsAndSample('edit')" id="btn_detect_edit" class="shrink-0 px-3.5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-purple-900/40 flex items-center gap-1.5">
                                    <i class="fa-solid fa-bolt"></i>
                                    <span>استخراج خودکار</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-purple-200/80 mt-1 leading-relaxed">
                                با زدن دکمه استخراج، سیستم نوع پنل (پاسارگاد/مرزبان)، کلیه اینباندهای فعال سرور و دامنه CDN را مستقیماً از روی کانفیگ‌های این کاربر می‌خواند و فرم را خودکار پر می‌کند.
                            </p>
                        </div>

                        <div id="inbounds_container_edit" class="hidden space-y-3 p-3.5 bg-slate-950/90 border border-purple-900/40 rounded-xl text-xs"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold flex items-center justify-between">
                                <span>دامنه CDN / ساب‌لینک</span>
                                <span class="text-[10px] text-emerald-400 font-normal">خودکار تکمیل می‌شود</span>
                            </label>
                            <input type="text" name="sub_domain" id="edit_server_sub_domain" dir="ltr" placeholder="خودکار تکمیل می‌شود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">در صورت خالی بودن، به صورت هوشمند از آدرس سرور تکمیل می‌شود.</span>
                        </div>
                        <div>
                            <label class="block text-slate-300 mb-1 font-semibold">حداکثر ظرفیت کاربر</label>
                            <input type="number" name="max_clients" id="edit_server_max_clients" min="0" placeholder="0 = نامحدود" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">عدد 0 یعنی ظرفیت نامحدود (∞)</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-1 font-semibold flex items-center justify-between">
                            <span>الگوی کانفیگ اختصاصی سرور (VLESS Reality / Trojan)</span>
                            <span class="text-[10px] text-cyan-400 font-normal">اختیاری - پشتیبانی از {uuid}</span>
                        </label>
                        <textarea name="config_template" id="edit_server_config_template" rows="2" dir="ltr" placeholder="اختیاری - با دکمه استخراج خودکار بالا تکمیل می‌گردد" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-[11px] leading-relaxed"></textarea>
                    </div>

                    <div id="edit_test_result" class="hidden p-3 rounded-xl text-xs transition-all"></div>
                </div>

                <!-- Modal Sticky Footer (Always Visible at bottom) -->
                <div class="p-4 border-t border-slate-800 bg-slate-900 shrink-0 flex items-center gap-2">
                    <button type="button" onclick="testRawInModal('edit')" id="btn_test_edit" class="w-1/2 py-3 bg-slate-800 hover:bg-slate-700 text-cyan-300 font-bold rounded-xl text-xs border border-cyan-800/40 transition flex items-center justify-center gap-1.5 shadow">
                        <i class="fa-solid fa-bolt-lightning text-cyan-400"></i>
                        <span>تست زنده اتصال</span>
                    </button>
                    <button type="submit" class="w-1/2 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-900/40 flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>ذخیره تغییرات سرور</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const serverIds = <?= json_encode(array_column($servers, 'id')) ?>;

    function buildUrl(endpoint, params) {
        let url = endpoint;
        const entries = Object.entries(params);
        if (!entries.length) return url;
        const qs = entries.map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v)).join('&');
        return url + (url.includes('?') ? '&' : '?') + qs;
    }

    function openNewServerModal() {
        const resBox = document.getElementById('new_test_result');
        if (resBox) {
            resBox.className = 'hidden mb-3 p-3 rounded-xl text-xs';
            resBox.innerHTML = '';
        }
        document.getElementById('newServerModal').classList.remove('hidden');
        document.getElementById('newServerModal').classList.add('flex');
    }
    function closeNewServerModal() {
        document.getElementById('newServerModal').classList.remove('flex');
        document.getElementById('newServerModal').classList.add('hidden');
    }

    function openEditServerModal(s) {
        const resBox = document.getElementById('edit_test_result');
        if (resBox) {
            resBox.className = 'hidden mb-3 p-3 rounded-xl text-xs';
            resBox.innerHTML = '';
        }
        document.getElementById('edit_server_id').value = s.id;
        document.getElementById('edit_server_name').value = s.name;
        document.getElementById('edit_server_driver').value = s.driver;
        if (document.getElementById('edit_server_category_id')) {
            document.getElementById('edit_server_category_id').value = s.category_id || (s.server_group === 'vip' ? 2 : (s.server_group === 'economic' ? 3 : (s.server_group === 'iran_access' ? 4 : 1)));
        }
        document.getElementById('edit_server_api_url').value = s.api_url;
        document.getElementById('edit_server_api_username').value = s.api_username || '';
        document.getElementById('edit_server_sub_domain').value = s.sub_domain || '';
        document.getElementById('edit_server_max_clients').value = (s.max_clients !== null && s.max_clients !== undefined) ? s.max_clients : 0;
        if (document.getElementById('edit_server_config_template')) {
            document.getElementById('edit_server_config_template').value = s.config_template || '';
        }

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

        fetch(buildUrl('<?= Helpers::url('servers/ping') ?>', {id: id}), {
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
        })
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

        fetch(buildUrl('<?= Helpers::url('servers/test') ?>', {id: id}), {
            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let msg = '✅ ' + data.message;
                    if (data.stats && data.stats.users !== undefined) {
                        msg += '\n• تعداد کاربران نود: ' + data.stats.users;
                    }
                    if (data.stats && data.stats.cpu) {
                        msg += '\n• پردازنده (CPU): ' + data.stats.cpu;
                    }
                    if (data.stats && data.stats.ram) {
                        msg += '\n• مصرف رم: ' + data.stats.ram;
                    }
                    alert(msg);
                } else {
                    alert('❌ خطا در برقراری ارتباط با سرور:\n' + (data.error || data.message));
                }
                btn.innerHTML = original;
                btn.disabled = false;
            })
            .catch(err => {
                alert('خطای سیستمی در ارتباط با کنترلر: ' + err);
                btn.innerHTML = original;
                btn.disabled = false;
            });
    }

    function testRawInModal(mode) {
        const isNew = (mode === 'new');
        const modal = document.getElementById(isNew ? 'newServerModal' : 'editServerModal');
        const form = modal.querySelector('form');
        const resultBox = document.getElementById(isNew ? 'new_test_result' : 'edit_test_result');
        const btn = document.getElementById(isNew ? 'btn_test_new' : 'btn_test_edit');

        const driverEl = form.querySelector('[name="driver"]');
        const urlEl = form.querySelector('[name="api_url"]');
        const userEl = form.querySelector('[name="api_username"]');
        const passEl = form.querySelector('[name="api_password"]');
        const tokenEl = form.querySelector('[name="api_token"]');

        const driver = driverEl ? driverEl.value : 'marzban';
        const apiUrl = urlEl ? urlEl.value.trim() : '';
        const username = userEl ? userEl.value.trim() : '';
        const password = passEl ? passEl.value.trim() : '';
        const token = tokenEl ? tokenEl.value.trim() : '';

        if (!apiUrl) {
            resultBox.className = 'mb-3 p-3 rounded-xl text-xs bg-rose-950/70 border border-rose-800/60 text-rose-300';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-exclamation ml-1 text-rose-400"></i> لطفاً ابتدا فیلد آدرس سرور (API URL) را وارد فرمایید.';
            resultBox.classList.remove('hidden');
            return;
        }

        const origBtn = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-cyan-300"></i> در حال ارزیابی...';
        btn.disabled = true;

        resultBox.className = 'mb-3 p-3 rounded-xl text-xs bg-slate-800/90 border border-slate-700 text-slate-300 flex items-center gap-2';
        resultBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-cyan-400 text-sm shrink-0"></i> <span>در حال ارسال درخواست احراز هویت به نود و بررسی پاسخ...</span>';
        resultBox.classList.remove('hidden');

        const formData = new FormData();
        formData.append('driver', driver);
        formData.append('api_url', apiUrl);
        formData.append('api_username', username);
        formData.append('api_password', password);
        formData.append('api_token', token);
        formData.append('csrf_token', '<?= Helpers::csrfToken() ?>');

        fetch('<?= Helpers::url('servers/test-raw') ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.innerHTML = origBtn;
            btn.disabled = false;

            if (data.success) {
                let html = '<div class="flex items-start gap-2">';
                html += '<i class="fa-solid fa-circle-check text-emerald-400 text-base shrink-0 mt-0.5"></i>';
                html += '<div>';
                html += '<b class="text-emerald-300 block mb-1">' + (data.message || 'اتصال با موفقیت تایید شد!') + '</b>';
                if (data.stats) {
                    html += '<div class="text-[11px] text-slate-300 leading-relaxed font-mono opacity-90">';
                    if (data.stats.version) html += '• هسته نود: <span class="text-white">' + data.stats.version + '</span><br>';
                    if (data.stats.users !== undefined) html += '• تعداد کاربران ثبت‌شده: <span class="text-emerald-300 font-bold">' + data.stats.users + '</span><br>';
                    if (data.stats.cpu) html += '• پردازنده (CPU): <span class="text-cyan-300">' + data.stats.cpu + '</span> | رم: <span class="text-purple-300">' + (data.stats.ram || '-') + '</span>';
                    html += '</div>';
                }
                if (data.suggest_switch && driverEl) {
                    driverEl.value = data.driver;
                    html += '<div class="mt-2 p-1.5 bg-emerald-900/80 border border-emerald-700/60 rounded-lg text-[11px] text-emerald-200">';
                    html += '💡 توجه: نوع درایور به صورت خودکار روی «<b>' + (data.driver === 'marzban' ? 'مرزبان (Marzban)' : 'پاسارگاد (Pasargad)') + '</b>» تنظیم شد.';
                    html += '</div>';
                }
                html += '</div></div>';

                resultBox.className = 'mb-3 p-3.5 rounded-xl text-xs bg-emerald-950/70 border border-emerald-600/50 text-emerald-300';
                resultBox.innerHTML = html;
            } else {
                let errMsg = data.error || data.message || 'خطای نامشخص در احراز هویت با سرور';
                let html = '<div class="flex items-start gap-2">';
                html += '<i class="fa-solid fa-circle-xmark text-rose-400 text-base shrink-0 mt-0.5"></i>';
                html += '<div>';
                html += '<b class="text-rose-300 block mb-1">عدم موفقیت در احراز هویت یا اتصال:</b>';
                html += '<span class="text-slate-300 text-[11px] leading-relaxed block">' + errMsg + '</span>';
                html += '</div></div>';

                resultBox.className = 'mb-3 p-3.5 rounded-xl text-xs bg-rose-950/70 border border-rose-700/60 text-rose-300';
                resultBox.innerHTML = html;
            }
        })
        .catch(err => {
            btn.innerHTML = origBtn;
            btn.disabled = false;
            resultBox.className = 'mb-3 p-3.5 rounded-xl text-xs bg-rose-950/70 border border-rose-700/60 text-rose-300';
            resultBox.innerHTML = '<div class="flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation text-rose-400 text-base shrink-0"></i><span>خطا در پاسخ کنترلر: ' + err.message + '</span></div>';
        });
    }

    function autoFillCdn(el, mode) {
        try {
            const val = el.value.trim();
            if (!val) return;
            const u = new URL(val);
            const modal = document.getElementById(mode === 'new' ? 'newServerModal' : 'editServerModal');
            if (!modal) return;
            const subInput = modal.querySelector('input[name="sub_domain"]');
            if (subInput && (!subInput.value || subInput.value.includes('montago-shop.ir'))) {
                subInput.value = u.host;
            }
        } catch(e) {}
    }

    async function detectInboundsAndSample(mode) {
        const isNew = (mode === 'new');
        const modal = document.getElementById(isNew ? 'newServerModal' : 'editServerModal');
        const form = modal.querySelector('form');
        const container = document.getElementById(isNew ? 'inbounds_container_new' : 'inbounds_container_edit');
        const btn = document.getElementById(isNew ? 'btn_test_new' : 'btn_test_edit');
        const detectBtn = document.getElementById(isNew ? 'btn_detect_new' : 'btn_detect_edit');
        const origBtnText = detectBtn.innerHTML;

        detectBtn.disabled = true;
        detectBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-purple-400"></i> در حال اتصال به سرور و استخراج نمونه لینک...';
        container.classList.remove('hidden');
        container.innerHTML = '<div class="text-center py-4 text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-xl text-purple-400 block mb-2"></i> در حال احراز هویت با وب‌سرویس و دریافت نمونه لینک‌ها...</div>';

        const formData = new FormData(form);

        try {
            const res = await fetch('<?= Helpers::url('servers/fetch-inbounds-sample') ?>', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = `<div class="p-3 bg-rose-950/60 border border-rose-800 text-rose-300 rounded-xl">❌ <b>خطا در ارتباط با سرور:</b> ${data.message || data.error}</div>`;
                detectBtn.disabled = false;
                detectBtn.innerHTML = origBtnText;
                return;
            }

            // Auto-fill detected driver
            const driverSelect = form.querySelector('select[name="driver"]');
            if (driverSelect && data.detected_driver) {
                driverSelect.value = data.detected_driver;
            }

            // Auto-fill extracted sub_domain
            const subInput = form.querySelector('input[name="sub_domain"]');
            if (subInput && data.extracted_sub_domain) {
                subInput.value = data.extracted_sub_domain;
            }

            let html = `
                <div class="p-3 bg-purple-950/60 border border-purple-800 rounded-xl space-y-1.5 mb-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i>
                            <span>تشخیص خودکار نوع پنل:</span>
                            <span class="text-emerald-400 font-mono font-bold">${data.detected_driver_label || data.detected_driver}</span>
                        </span>
                        <span class="text-[10px] text-purple-300 bg-purple-900/60 px-2 py-0.5 rounded border border-purple-700">${(data.inbounds || []).length} اینباند فعال</span>
                    </div>
                    ${data.sample && data.sample.source ? `<div class="text-[10px] text-slate-300"><b>منبع استخراج:</b> ${data.sample.source}</div>` : ''}
                    ${data.extracted_sub_domain ? `<div class="text-[10px] text-cyan-300 font-mono"><b>دامنه ساب استخراج‌شده:</b> ${data.extracted_sub_domain}</div>` : ''}
                </div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <span class="font-bold text-white flex items-center gap-1.5 text-xs">
                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                        <span>اینباندهای فعال انتخاب‌شده جهت تحویل به کلاینت</span>
                    </span>
                </div>
            `;

            if (data.inbounds && data.inbounds.length > 0) {
                html += `<div class="grid grid-cols-1 md:grid-cols-2 gap-2 my-2">`;
                data.inbounds.forEach((ib) => {
                    html += `
                        <label class="flex items-center gap-2 p-2 bg-slate-900 border border-slate-800 rounded-lg hover:border-purple-600/50 cursor-pointer transition">
                            <input type="checkbox" name="selected_inbounds[]" value="${ib.tag}" checked class="rounded text-purple-600 focus:ring-0">
                            <div class="text-[11px] leading-tight">
                                <span class="font-mono font-bold text-white block">${ib.tag}</span>
                                <span class="text-[10px] text-slate-400 uppercase font-mono">${ib.protocol} | ${ib.network} | ${ib.tls} | Port: ${ib.port}</span>
                            </div>
                        </label>
                    `;
                });
                html += `</div>`;
            } else {
                html += `<p class="text-slate-400 text-[11px] py-1">تمام پروتکل‌های استاندارد فعال به صورت خودکار به کاربر اختصاص می‌یابند.</p>`;
            }

            if (data.sample) {
                if (data.sample.sublink) {
                    html += `
                        <div class="space-y-1.5 pt-2 border-t border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-emerald-400">⚡ لینک ساب‌لینک مستقیم استخراج‌شده از مرزبان (مانند میرزا پرو):</span>
                                <button type="button" onclick="navigator.clipboard.writeText('${data.sample.sublink}').then(() => alert('لینک ساب‌لینک با موفقیت کپی شد!'))" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] rounded border border-slate-700">
                                    <i class="fa-regular fa-copy"></i> کپی ساب‌لینک
                                </button>
                            </div>
                            <input type="text" readonly value="${data.sample.sublink}" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 font-mono text-[10px] text-emerald-300 select-all" dir="ltr">
                        </div>
                    `;
                }

                if (data.sample.vless_link) {
                    html += `
                        <div class="space-y-1.5 pt-2 border-t border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-purple-400">🚀 نمونه کانکشن خام تولیدشده توسط سرور:</span>
                                <button type="button" onclick="applySampleToTemplate('${mode}', '${encodeURIComponent(data.sample.vless_link)}')" class="px-2 py-0.5 bg-purple-600/30 hover:bg-purple-600/50 text-purple-300 text-[10px] rounded border border-purple-500/40">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> اعمال در الگوی سرور
                                </button>
                            </div>
                            <textarea readonly rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 font-mono text-[10px] text-slate-300 select-all" dir="ltr">${data.sample.vless_link}</textarea>
                        </div>
                    `;
                }
            }

            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = `<div class="p-3 bg-rose-950/60 border border-rose-800 text-rose-300 rounded-xl">❌ خطا در ارتباط: ${e.message}</div>`;
        } finally {
            detectBtn.disabled = false;
            detectBtn.innerHTML = origBtnText;
        }
    }

    function applySampleToTemplate(mode, encodedLink) {
        const link = decodeURIComponent(encodedLink);
        // Replace UUID with {uuid}
        const templated = link.replace(/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/, '{uuid}');
        const textarea = (mode === 'edit') 
            ? document.getElementById('edit_server_config_template') 
            : document.querySelector('#newServerModal textarea[name="config_template"]');
        if (textarea) {
            textarea.value = templated;
            alert('نمونه کانکشن استخراج‌شده با موفقیت در فیلد الگوی اختصاصی سرور قرار گرفت!');
        }
    }

    function openMigrateModal() {
        document.getElementById('migrateModal').classList.remove('hidden');
        document.getElementById('migrateModal').classList.add('flex');
    }
    function closeMigrateModal() {
        document.getElementById('migrateModal').classList.remove('flex');
        document.getElementById('migrateModal').classList.add('hidden');
    }

    // Auto-ping servers on page load
    document.addEventListener('DOMContentLoaded', () => {
        pingAllServers();
    });
</script>

<!-- Modal: Bulk Server Migration -->
<div id="migrateModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 z-50 overflow-y-auto modal-overlay">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-2xl relative my-auto max-h-[88vh] overflow-y-auto modal-box">
        <button onclick="closeMigrateModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-2 flex items-center gap-2">
            <i class="fa-solid fa-people-carry-box text-amber-400"></i>
            <span>مهاجرت و انتقال دسته‌جمعی کلاینت‌ها</span>
        </h3>
        <p class="text-xs text-slate-400 mb-4">انتقال آنی تمامی کاربران فعال از یک سرور مسدود یا در حال تعمیر به یک سرور سالم و پایدار.</p>

        <form action="<?= Helpers::url('servers/migrate') ?>" method="POST" class="space-y-4 text-xs" onsubmit="return confirm('آیا از انتقال کلیه کاربران سرور مبدا به سرور مقصد اطمینان دارید؟');">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">سرور مبدا (سرور فعلی کاربران) *</label>
                <select name="from_server_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                    <option value="">-- انتخاب سرور مبدا --</option>
                    <?php foreach ($servers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['client_count'] ?> کلاینت)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">سرور مقصد (نود جایگزین) *</label>
                <select name="to_server_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white">
                    <option value="">-- انتخاب سرور مقصد --</option>
                    <?php foreach ($servers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (ظرفیت: <?= (empty($s['max_clients']) || (int)$s['max_clients'] <= 0) ? 'نامحدود ∞' : number_format($s['max_clients']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="p-3 bg-amber-950/30 border border-amber-800/40 rounded-xl text-[11px] text-amber-300/90 leading-relaxed">
                <i class="fa-solid fa-circle-exclamation text-amber-400 ml-1"></i>
                کلیه کانفیگ‌های کاربران بر روی سرور مقصد بازسازی شده و ساب‌لینک‌ها بدون نیاز به تغییر لینک در سمت مشتری، بلافاصله روی سرور جدید کار خواهند کرد.
            </div>

            <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition-all shadow-md mt-2 flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                <span>شروع انتقال خودکار کاربران</span>
            </button>
        </form>
    </div>
</div>

<?php
require __DIR__ . '/../layout/footer.php';
?>

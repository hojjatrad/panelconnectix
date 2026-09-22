<?php
require __DIR__ . '/../layout/header.php';
?>

<!-- Header with Filters & Actions -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-users text-cyan-400"></i>
            <span>مدیریت کلاینت‌ها و مشترکین</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">مشاهده، تمدید، رزرو پلن، خروجی ساب‌لینک، کد QR و عملیات گروهی</p>
    </div>

    <div class="flex items-center gap-2">
        <form method="POST" action="<?= Helpers::url('clients/test-account') ?>" class="m-0" onsubmit="return confirm('آیا مایلید یک اکانت تست ۲۴ ساعته (۱ گیگابایت رایگان) فوراً ایجاد شود؟');">
            <?= Helpers::csrfField() ?>
            <button type="submit" class="px-3.5 py-2.5 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5" title="صدور آنی اکانت تست ۱ روزه رایگان">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <span>اکانت تست سریع</span>
            </button>
        </form>

        <a href="<?= Helpers::url('clients/export') ?>?<?= http_build_query($_GET) ?>" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2" title="دانلود خروجی اکسل و CSV از نتایج فیلترشده">
            <i class="fa-solid fa-file-excel text-emerald-400"></i>
            <span>خروجی اکسل</span>
        </a>

        <a href="<?= Helpers::url('clients/create') ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-purple-900/30 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            <span>ایجاد کاربر جدید</span>
        </a>
    </div>
</div>

<!-- Search & Filter Form -->
<form method="GET" action="<?= Helpers::url('clients') ?>" class="bg-slate-900/50 p-4 rounded-xl border border-slate-800/80 text-xs space-y-3">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div>
            <label class="block text-slate-400 mb-1">جستجو:</label>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="نام کاربری، یادداشت..." 
                   class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
        </div>

        <div>
            <label class="block text-slate-400 mb-1">وضعیت اشتراک:</label>
            <select name="status" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option value="">همه وضعیت‌ها</option>
                <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>فعال (Active)</option>
                <option value="expired" <?= ($_GET['status'] ?? '') === 'expired' ? 'selected' : '' ?>>منقضی (Expired)</option>
                <option value="disabled" <?= ($_GET['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>معلق (Disabled)</option>
                <option value="never_connected" <?= ($_GET['status'] ?? '') === 'never_connected' ? 'selected' : '' ?>>هرگز متصل نشده</option>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1">کلاستر سرور:</label>
            <select name="group" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option value="">همه کلاسترها</option>
                <option value="default" <?= ($_GET['group'] ?? '') === 'default' ? 'selected' : '' ?>>عادی (Default)</option>
                <option value="economic" <?= ($_GET['group'] ?? '') === 'economic' ? 'selected' : '' ?>>اقتصادی (Economic)</option>
                <option value="iran_access" <?= ($_GET['group'] ?? '') === 'iran_access' ? 'selected' : '' ?>>ایران اکسس (Iran Access)</option>
                <option value="vip" <?= ($_GET['group'] ?? '') === 'vip' ? 'selected' : '' ?>>تجاری (VIP Business)</option>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1">پلن تعرفه:</label>
            <select name="plan_id" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option value="">همه پلن‌ها</option>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int)($_GET['plan_id'] ?? 0) === $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1">فیلتر انقضا:</label>
            <select name="expire_filter" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option value="">همه تاریخ‌ها</option>
                <option value="expiring_soon" <?= ($_GET['expire_filter'] ?? '') === 'expiring_soon' ? 'selected' : '' ?>>کمتر از ۳ روز مانده</option>
                <option value="expired" <?= ($_GET['expire_filter'] ?? '') === 'expired' ? 'selected' : '' ?>>منقضی شده</option>
                <option value="unlimited" <?= ($_GET['expire_filter'] ?? '') === 'unlimited' ? 'selected' : '' ?>>بدون تاریخ انقضا</option>
            </select>
        </div>

        <div>
            <label class="block text-slate-400 mb-1">میزان مصرف حجم:</label>
            <select name="usage_filter" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option value="">همه مقادیر مصرف</option>
                <option value="critical" <?= ($_GET['usage_filter'] ?? '') === 'critical' ? 'selected' : '' ?>>بیش از ۹۰٪ (حجم بحرانی)</option>
                <option value="heavy" <?= ($_GET['usage_filter'] ?? '') === 'heavy' ? 'selected' : '' ?>>۵۰٪ الی ۹۰٪ مصرف</option>
                <option value="low" <?= ($_GET['usage_filter'] ?? '') === 'low' ? 'selected' : '' ?>>کمتر از ۲۰٪ مصرف</option>
            </select>
        </div>
    </div>

    <div class="flex items-center justify-between pt-2 border-t border-slate-800">
        <div class="text-[11px] text-slate-400">
            نمایش <b class="text-white"><?= count($clients) ?></b> کاربر با شرایط انتخاب‌شده
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium flex items-center gap-1.5 shadow">
                <i class="fa-solid fa-filter text-xs"></i>
                <span>اعمال فیلترها</span>
            </button>
            <a href="<?= Helpers::url('clients') ?>" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-lg border border-slate-700 transition-colors flex items-center gap-1" title="پاکسازی همه فیلترها">
                <i class="fa-solid fa-rotate-right"></i>
                <span>حذف فیلترها</span>
            </a>
        </div>
    </div>
</form>

<!-- Clients Table with Bulk Operations Form -->
<form action="<?= Helpers::url('clients/bulk') ?>" method="POST" id="bulkForm">
    <?= Helpers::csrfField() ?>

    <!-- Bulk Action Toolbar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-900/60 p-3 rounded-xl border border-slate-800 text-xs">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-slate-400 font-semibold">عملیات دسته‌جمعی:</span>
            <select name="bulk_action" required class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-white">
                <option value="">-- انتخاب عملیات روی انتخاب‌شده‌ها --</option>
                <option value="extend_30_days">➕ تمدید ۳۰ روزه کلیه انتخاب‌شده‌ها</option>
                <option value="add_10_gb">➕ افزودن ۱۰ گیگابایت حجم اضافی</option>
                <option value="disable">⏸ غیرفعال‌سازی موقت</option>
                <option value="enable">▶️ فعال‌سازی مجدد</option>
                <option value="delete">🗑 حذف قطعی کاربران انتخاب‌شده</option>
            </select>
            <button type="submit" onclick="return confirm('آیا از اعمال این عملیات روی کاربران انتخاب‌شده مطمئن هستید؟');" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition shadow">
                اعمال عملیات
            </button>
        </div>
        <div class="text-[11px] text-slate-400">
            تعداد کل: <?= count($clients) ?> کاربر
        </div>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
                    <tr>
                        <th class="p-3.5 text-center w-10">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0 cursor-pointer">
                        </th>
                        <th class="p-3.5 font-semibold">شناسه / کاربر</th>
                        <th class="p-3.5 font-semibold">پلن و سرور</th>
                        <th class="p-3.5 font-semibold">ترافیک مصرفی</th>
                        <th class="p-3.5 font-semibold">زمان انقضا</th>
                        <th class="p-3.5 font-semibold">پلن رزرو هوشمند</th>
                        <th class="p-3.5 font-semibold">وضعیت</th>
                        <th class="p-3.5 font-semibold text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="8" class="py-10 text-center text-slate-500">هیچ کاربری با مشخصات انتخابی یافت نشد.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): 
                            $pct = $c['traffic_limit_bytes'] > 0 ? round(($c['traffic_used_bytes'] / $c['traffic_limit_bytes']) * 100, 1) : 0;
                            $subUrl = Helpers::fullUrl('sub/' . $c['sub_token']);
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-3.5 text-center">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $c['id'] ?>" class="client-check rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0 cursor-pointer">
                                </td>

                                <td class="p-3.5">
                                    <div class="font-bold text-white text-sm font-mono"><?= htmlspecialchars($c['username']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5 truncate max-w-[140px]"><?= htmlspecialchars($c['uuid']) ?></div>
                                    <?php if (Auth::isAdmin() && !empty($c['reseller_username'])): ?>
                                        <span class="inline-block mt-1 text-[10px] px-1.5 py-0.5 rounded bg-purple-950/60 text-purple-300 border border-purple-800/40">نماینده: <?= htmlspecialchars($c['reseller_username']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5">
                                    <div class="font-medium text-slate-200"><?= htmlspecialchars($c['plan_title'] ?? 'پلن عادی') ?></div>
                                    <div class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($c['server_name'] ?? 'سرور ابری') ?></div>
                                </td>

                                <td class="p-3.5 min-w-[140px]">
                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                        <span class="text-slate-300 font-medium"><?= Helpers::formatBytes($c['traffic_used_bytes']) ?></span>
                                        <span class="text-slate-400"><?= Helpers::formatBytes($c['traffic_limit_bytes']) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full <?= $pct >= 90 ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-purple-500') ?>" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 mt-0.5 block"><?= $pct ?>% مصرف</span>
                                </td>

                                <td class="p-3.5">
                                    <div class="font-medium text-white"><?= Helpers::daysRemaining($c['expire_at']) ?></div>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= $c['expire_at'] ?: 'نامحدود' ?></span>
                                </td>

                                <td class="p-3.5">
                                    <?php if (!empty($c['reserved_id'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                            <i class="fa-solid fa-sparkles text-[9px]"></i>
                                            رزرو: <?= $c['reserved_gb'] ?>GB
                                        </span>
                                    <?php else: ?>
                                        <button type="button" onclick="openReserveModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['username']) ?>')" class="text-[11px] text-slate-500 hover:text-cyan-400 transition-colors flex items-center gap-1">
                                            <i class="fa-solid fa-plus text-[9px]"></i>
                                            <span>افزودن رزرو</span>
                                        </button>
                                    <?php endif; ?>
                                </td>

                                <td class="p-3.5">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $c['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($c['status'] === 'expired' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-800 text-slate-400') ?>">
                                        <?= match($c['status']) {
                                            'active' => 'فعال',
                                            'expired' => 'منقضی',
                                            'disabled' => 'غیرفعال',
                                            'never_connected' => 'عدم اتصال',
                                            default => $c['status']
                                        } ?>
                                    </span>
                                </td>

                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Inspector & QR Modal Button -->
                                        <button type="button" onclick="openInspectModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['username']) ?>', '<?= $subUrl ?>')" 
                                                class="p-2 bg-slate-800 hover:bg-slate-700 text-purple-300 hover:text-white rounded-lg transition-colors border border-slate-700" title="مشاهده کانفیگ‌ها، ساب‌لینک و بارکد QR">
                                            <i class="fa-solid fa-qrcode"></i>
                                        </button>

                                        <!-- Quick Copy Sublink -->
                                        <button type="button" onclick="copyToClipboard('<?= $subUrl ?>', this)" 
                                                class="p-2 bg-slate-800 hover:bg-slate-700 text-cyan-300 hover:text-white rounded-lg transition-colors border border-slate-700" title="کپی سریع لینک سابسکریپشن">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>

                                        <!-- Renew Modal Button -->
                                        <button type="button" onclick="openRenewModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['username']) ?>')" 
                                                class="p-2 bg-slate-800 hover:bg-slate-700 text-emerald-400 hover:text-white rounded-lg transition-colors border border-slate-700" title="تمدید آنی">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" onclick="if(confirm('آیا از حذف این کاربر اطمینان دارید؟')) { document.getElementById('deleteIdInput').value = <?= $c['id'] ?>; document.getElementById('deleteForm').submit(); }" class="p-2 bg-slate-800 hover:bg-rose-900/60 text-rose-400 hover:text-rose-200 rounded-lg transition-colors border border-slate-700" title="حذف کاربر">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Single Delete Helper Form -->
<form id="deleteForm" action="<?= Helpers::url('clients/delete') ?>" method="POST" class="hidden">
    <?= Helpers::csrfField() ?>
    <input type="hidden" name="client_id" id="deleteIdInput" value="">
</form>

<!-- Modal 1: Client Inspector & Delivery Dialog -->
<div id="inspectModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeInspectModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
            <div>
                <h3 id="inspectUsername" class="text-base font-bold text-white font-mono">user_xxx</h3>
                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                    <span id="inspectTraffic">-- / --</span>
                    <span>•</span>
                    <span id="inspectDays" class="text-cyan-400">-- روز مانده</span>
                </div>
            </div>
        </div>

        <!-- Credentials Quick Display -->
        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 grid grid-cols-2 gap-3 text-xs mb-3">
            <div>
                <span class="text-slate-400 block text-[10px]">نام کاربری (Username):</span>
                <span id="inspectUserField" class="font-mono font-bold text-white text-xs">--</span>
            </div>
            <div>
                <span class="text-slate-400 block text-[10px]">کلمه عبور (Password):</span>
                <span id="inspectPassField" class="font-mono font-bold text-purple-300 text-xs">--</span>
            </div>
        </div>

        <!-- Copy Full Customer Delivery Text Button -->
        <button type="button" onclick="copyCustomerDeliveryText(this)" class="w-full py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md transition mb-4">
            <i class="fa-solid fa-share-nodes"></i>
            <span>کپی متن کامل تحویل به مشتری (شامل یوزر، پسورد، ساب‌لینک و ربات)</span>
        </button>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-800 text-xs mb-4">
            <button onclick="switchInspectTab('sub')" id="tabBtnSub" class="px-3 py-2 border-b-2 border-purple-500 text-purple-400 font-bold transition-colors">
                <i class="fa-solid fa-link ml-1"></i> ساب‌لینک و QR
            </button>
            <button onclick="switchInspectTab('apps')" id="tabBtnApps" class="px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-white font-medium transition-colors">
                <i class="fa-solid fa-mobile-screen ml-1"></i> اتصال سریع اپ‌ها
            </button>
            <button onclick="switchInspectTab('raw')" id="tabBtnRaw" class="px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-white font-medium transition-colors">
                <i class="fa-solid fa-code ml-1"></i> کانفیگ‌های مجزا
            </button>
        </div>

        <!-- Tab 1: Sublink & QR -->
        <div id="tabSub" class="space-y-4">
            <div class="bg-white p-3 rounded-2xl inline-block shadow-inner mx-auto block text-center w-fit">
                <img id="inspectQrImage" src="" alt="QR" class="w-44 h-44 mx-auto">
            </div>

            <!-- Service Details beneath QR Code -->
            <div class="bg-slate-800/90 border border-slate-700/80 rounded-xl p-3.5 space-y-2.5 text-xs">
                <div class="text-[11px] font-bold text-slate-300 border-b border-slate-700 pb-1.5 flex items-center justify-between">
                    <span><i class="fa-solid fa-circle-info text-cyan-400 ml-1"></i> مشخصات و اطلاعات سرویس</span>
                    <span class="text-[10px] text-slate-400">شناسه اختصاصی کاربر</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-slate-300">
                    <div class="bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">نام کاربری:</span>
                        <code id="inspectUserField" class="font-bold text-purple-300 select-all">-</code>
                    </div>
                    <div class="bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">کلمه عبور:</span>
                        <code id="inspectPassField" class="font-bold text-amber-300 select-all">-</code>
                    </div>
                    <div class="bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">مصرف ترافیک:</span>
                        <span id="inspectTraffic" class="font-bold text-cyan-300">-</span>
                    </div>
                    <div class="bg-slate-900/60 p-2 rounded-lg border border-slate-800">
                        <span class="text-slate-400 block text-[10px] mb-0.5">اعتبار زمانی:</span>
                        <span id="inspectDays" class="font-bold text-emerald-300">-</span>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[11px] text-slate-400">آدرس لینک اشتراک هوشمند (Sublink):</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="inspectSubUrl" readonly class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-slate-300 font-mono text-center select-all">
                    <button onclick="copyToClipboard(document.getElementById('inspectSubUrl').value, this)" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shrink-0">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
                <a id="inspectLandingBtn" href="#" target="_blank" class="block w-full py-2 bg-slate-800 hover:bg-slate-700 text-cyan-300 rounded-xl text-xs font-medium border border-slate-700 transition-colors text-center">
                    <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
                    مشاهده صفحه ساب‌لینک اختصاصی کاربر
                </a>
            </div>
        </div>

        <!-- Tab 2: One-Click App Connect -->
        <div id="tabApps" class="hidden space-y-3">
            <p class="text-xs text-slate-400 mb-2">با کلیک روی هر دکمه، اشتراک مستقیماً در نرم‌افزار مربوطه در گوشی یا کامپیوتر باز و ثبت می‌شود:</p>
            
            <a id="btnHiddify" href="#" class="w-full py-3 bg-gradient-to-r from-purple-700 to-indigo-700 hover:from-purple-600 hover:to-indigo-600 text-white font-bold rounded-xl text-xs flex items-center justify-between px-4 shadow transition">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-rocket text-sm"></i>
                    <span>اتصال خودکار با هیدیفای (Hiddify)</span>
                </span>
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>

            <a id="btnV2rayNG" href="#" class="w-full py-3 bg-gradient-to-r from-cyan-700 to-blue-700 hover:from-cyan-600 hover:to-blue-600 text-white font-bold rounded-xl text-xs flex items-center justify-between px-4 shadow transition">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                    <span>اتصال خودکار با V2rayNG</span>
                </span>
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>

            <a id="btnSingbox" href="#" class="w-full py-3 bg-gradient-to-r from-emerald-700 to-teal-700 hover:from-emerald-600 hover:to-teal-600 text-white font-bold rounded-xl text-xs flex items-center justify-between px-4 shadow transition">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-sm"></i>
                    <span>اتصال خودکار با Sing-box / Streisand</span>
                </span>
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
        </div>

        <!-- Tab 3: Raw Individual Configs -->
        <div id="tabRaw" class="hidden space-y-3">
            <div id="rawConfigsContainer" class="space-y-3">
                <div class="text-center py-6 text-slate-500">
                    <i class="fa-solid fa-spinner fa-spin text-lg"></i>
                    <span class="block mt-2 text-xs">در حال بارگذاری کانفیگ‌ها...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Renew Subscription -->
<div id="renewModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative">
        <button onclick="closeRenewModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-1">تمدید اشتراک</h3>
        <p class="text-xs text-slate-400 mb-4">کاربر: <span id="renewUsername" class="font-mono text-emerald-400 font-bold"></span></p>

        <form action="<?= Helpers::url('clients/renew') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="client_id" id="renewClientId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">انتخاب پلن جدید جهت تمدید:</label>
                <select name="plan_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:ring-1 focus:ring-purple-500">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB / <?= $p['duration_days'] ?> روز) - <?= Helpers::formatMoney($p['reseller_price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="text-[11px] text-amber-300/80 bg-amber-950/40 border border-amber-900/60 p-3 rounded-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
                حجم و روزهای پلن جدید به باقیمانده سرویس کاربر اضافه شده و مبلغ پلن از کیف پول کسر می‌گردد.
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all shadow-md">
                تایید و تمدید آنی
            </button>
        </form>
    </div>
</div>

<!-- Modal 3: Reserve Plan -->
<div id="reserveModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative">
        <button onclick="closeReserveModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <h3 class="text-base font-bold text-white mb-1">رزرو هوشمند پلن بعدی</h3>
        <p class="text-xs text-slate-400 mb-4">کاربر: <span id="reserveUsername" class="font-mono text-cyan-400 font-bold"></span></p>

        <form action="<?= Helpers::url('clients/reserve') ?>" method="POST" class="space-y-4">
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="client_id" id="reserveClientId" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">انتخاب پلن رزرو:</label>
                <select name="plan_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:ring-1 focus:ring-cyan-500">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB / <?= $p['duration_days'] ?> روز) - <?= Helpers::formatMoney($p['reseller_price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="text-[11px] text-cyan-300/80 bg-cyan-950/40 border border-cyan-900/60 p-3 rounded-xl">
                <i class="fa-solid fa-circle-info"></i>
                این پلن در صف رزرو قرار می‌گیرد. به محض اینکه حجم بسته فعلی کاربر تمام شود یا تاریخ آن به پایان برسد، سیستم این پلن را به صورت کاملاً خودکار و بدون نیاز به دخالت شما فعال خواهد کرد.
            </div>

            <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white font-bold rounded-xl text-xs transition-all shadow-md">
                پیش‌خرید و ثبت در صف رزرو
            </button>
        </form>
    </div>
</div>

<script>
    function toggleSelectAll(master) {
        document.querySelectorAll('.client-check').forEach(cb => cb.checked = master.checked);
    }

    let currentClientId = 0;
    let cachedConfigs = null;

    function openInspectModal(id, username, subUrl) {
        currentClientId = id;
        document.getElementById('inspectUsername').innerText = username;
        document.getElementById('inspectSubUrl').value = subUrl;
        document.getElementById('inspectLandingBtn').href = subUrl + '?web=1';
        document.getElementById('inspectQrImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(subUrl);

        // Setup App Deeplinks
        document.getElementById('btnHiddify').href = 'hiddify://install-sub?url=' + encodeURIComponent(subUrl);
        document.getElementById('btnV2rayNG').href = 'v2rayng://install-config?url=' + encodeURIComponent(subUrl);
        document.getElementById('btnSingbox').href = 'sing-box://import-remote-profile?url=' + encodeURIComponent(subUrl);

        // Fetch configs asynchronously
        fetch('<?= Helpers::url('clients/configs') ?>?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cachedConfigs = data;
                    document.getElementById('inspectTraffic').innerText = data.client.traffic_used + ' / ' + data.client.traffic_limit;
                    document.getElementById('inspectDays').innerText = data.client.days_remaining;
                    document.getElementById('inspectUserField').innerText = data.client.username;
                    document.getElementById('inspectPassField').innerText = data.client.password;

                    let html = '';
                    const titles = {
                        'vless_reality': 'کانفیگ ضد فیلتر VLESS Reality',
                        'vless_ws': 'کانفیگ CDN WebSocket VLESS',
                        'vmess': 'کانفیگ استاندارد VMess',
                        'trojan': 'کانفیگ ایمن Trojan TLS'
                    };

                    for (const [key, val] of Object.entries(data.configs)) {
                        const title = titles[key] || key;
                        html += `
                            <div class="bg-slate-800/80 border border-slate-700/80 p-3 rounded-xl space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-200">${title}</span>
                                    <button onclick="copyToClipboard('${val}', this)" class="px-2.5 py-1 bg-purple-600/30 hover:bg-purple-600 text-purple-300 hover:text-white rounded-lg text-[11px] transition">
                                        <i class="fa-solid fa-copy ml-1"></i> کپی
                                    </button>
                                </div>
                                <input type="text" readonly value="${val}" class="w-full bg-slate-900 border border-slate-700/60 rounded-lg p-2 font-mono text-[10px] text-slate-300 select-all" dir="ltr">
                            </div>
                        `;
                    }
                    document.getElementById('rawConfigsContainer').innerHTML = html;
                }
            })
            .catch(() => {
                document.getElementById('rawConfigsContainer').innerHTML = '<div class="text-rose-400 text-center py-4 text-xs">خطا در دریافت کانفیگ‌ها</div>';
            });

        switchInspectTab('sub');
        const modal = document.getElementById('inspectModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function copyCustomerDeliveryText(btn) {
        if (!cachedConfigs || !cachedConfigs.client) {
            alert('اطلاعات کاربر هنوز کامل بارگذاری نشده است. لطفاً چند لحظه صبر کنید.');
            return;
        }

        const c = cachedConfigs.client;
        let text = `🎉 اشتراک اینترنت آزاد شما آماده استفاده است:

👤 نام کاربری: ${c.username}
🔑 کلمه عبور: ${c.password}
📊 ترافیک مجاز: ${c.traffic_limit}
⏳ اعتبار زمانی: ${c.days_remaining}

🔗 لینک اشتراک هوشمند (Sublink):
${c.sub_url}`;

        if (c.bot_bind_url) {
            text += `\n\n🤖 اتصال خودکار به ربات تلگرام (جهت استعلام حجم و تمدید):\n${c.bot_bind_url}`;
        }

        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-emerald-300"></i> متن کامل تحویل کپی شد!';
            setTimeout(() => { btn.innerHTML = orig; }, 2500);
        }).catch(err => {
            alert('خطا در کپی: ' + err);
        });
    }

    function closeInspectModal() {
        const modal = document.getElementById('inspectModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function switchInspectTab(tab) {
        document.getElementById('tabSub').classList.add('hidden');
        document.getElementById('tabApps').classList.add('hidden');
        document.getElementById('tabRaw').classList.add('hidden');

        document.getElementById('tabBtnSub').className = 'px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-white font-medium transition-colors';
        document.getElementById('tabBtnApps').className = 'px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-white font-medium transition-colors';
        document.getElementById('tabBtnRaw').className = 'px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-white font-medium transition-colors';

        if (tab === 'sub') {
            document.getElementById('tabSub').classList.remove('hidden');
            document.getElementById('tabBtnSub').className = 'px-3 py-2 border-b-2 border-purple-500 text-purple-400 font-bold transition-colors';
        } else if (tab === 'apps') {
            document.getElementById('tabApps').classList.remove('hidden');
            document.getElementById('tabBtnApps').className = 'px-3 py-2 border-b-2 border-purple-500 text-purple-400 font-bold transition-colors';
        } else if (tab === 'raw') {
            document.getElementById('tabRaw').classList.remove('hidden');
            document.getElementById('tabBtnRaw').className = 'px-3 py-2 border-b-2 border-purple-500 text-purple-400 font-bold transition-colors';
        }
    }

    function openRenewModal(id, username) {
        document.getElementById('renewClientId').value = id;
        document.getElementById('renewUsername').innerText = username;
        const modal = document.getElementById('renewModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeRenewModal() {
        const modal = document.getElementById('renewModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    function openReserveModal(id, username) {
        document.getElementById('reserveClientId').value = id;
        document.getElementById('reserveUsername').innerText = username;
        const modal = document.getElementById('reserveModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeReserveModal() {
        const modal = document.getElementById('reserveModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

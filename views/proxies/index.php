<?php require __DIR__ . '/../layout/header.php'; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-black text-white flex items-center gap-2"><i class="fa-solid fa-shield-halved text-cyan-400"></i> مدیریت پروکسی‌ها v4.0.19</h1>
            <p class="text-xs text-slate-400 mt-1">لیست پروکسی‌های فعال برای تلگرام و سایر برنامه‌ها - رایگان برای مشتریان VPN + قابل فروش جدا به عنوان پلن پروکسی-only</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= Helpers::url('plans') ?>" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold rounded-xl">+ پلن پروکسی</a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-cyan-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-brands fa-telegram text-cyan-400"></i><span class="font-bold text-white text-sm">SOCKS5 + HTTP</span></div>
            <p class="text-xs text-slate-400">برای تلگرام و سایر برنامه‌ها، بدون نیاز به VPN (از نود مشتری)</p>
            <p class="text-[11px] text-emerald-300 mt-2">✅ رایگان برای مشتریان VPN</p>
        </div>
        <div class="bg-slate-900 border border-violet-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-solid fa-paper-plane text-violet-400"></i><span class="font-bold text-white text-sm">MTProto</span></div>
            <p class="text-xs text-slate-400">مخصوص تلگرام، با لینک مستقیم tg://</p>
            <p class="text-[11px] text-emerald-300 mt-2">✅ رایگان + بدون فیلترشکن</p>
        </div>
        <div class="bg-slate-900 border border-amber-800/30 rounded-2xl p-4">
            <div class="flex items-center gap-2 mb-2"><i class="fa-solid fa-tag text-amber-400"></i><span class="font-bold text-white text-sm">پلن پروکسی-only</span></div>
            <p class="text-xs text-slate-400">قابل فروش جدا، ارزان‌تر از VPN، برای تلگرام</p>
            <p class="text-[11px] text-amber-300 mt-2">💰 درآمد جدید</p>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
        <h3 class="font-bold text-white text-sm mb-3">آخرین 100 کاربر فعال (نمونه پروکسی)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-slate-400 border-b border-slate-800"><th class="p-2 text-right">یوزر</th><th class="p-2">سرور</th><th class="p-2">پلن</th><th class="p-2">وضعیت</th><th class="p-2">پروکسی</th></tr></thead>
                <tbody>
                <?php foreach ($proxies as $p): ?>
                    <tr class="border-b border-slate-800/50 hover:bg-slate-800/30">
                        <td class="p-2 font-mono text-cyan-300"><?= htmlspecialchars($p['username']) ?></td>
                        <td class="p-2 text-slate-300"><?= htmlspecialchars($p['server_name']) ?> <span class="text-[10px] text-slate-500"><?= htmlspecialchars(parse_url($p['api_url'] ?? '', PHP_URL_HOST) ?: ($p['sub_domain'] ?? '')) ?></span></td>
                        <td class="p-2 text-slate-400"><?= htmlspecialchars($p['plan_title'] ?? '-') ?></td>
                        <td class="p-2"><span class="px-2 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px]"><?= htmlspecialchars($p['status']) ?></span></td>
                        <td class="p-2"><a href="<?= Helpers::url('clients') ?>?search=<?= urlencode($p['username']) ?>" class="text-cyan-400 hover:underline">نمایش</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="bg-cyan-950/30 border border-cyan-800/30 rounded-2xl p-4 text-xs space-y-2">
        <p class="font-bold text-cyan-300">💡 راهنمای کامل پروکسی v4.0.19:</p>
        <p class="text-slate-300">• <b>رایگان برای VPN:</b> تمام مشتری‌های VPN میتونن از پروکسی نود خودشون (SOCKS5/HTTP/MTProto) استفاده کنن بدون نیاز به خرید جدا</p>
        <p class="text-slate-300">• <b>فروش جدا:</b> پلن بسازید با تیک "🔒 فقط پروکسی" - قیمت ارزان‌تر از VPN، برای تلگرام</p>
        <p class="text-slate-300">• <b>API اپ:</b> <code class="bg-slate-800 px-2 py-1 rounded">/api/v1/app/proxies?auth_token=CLIENT_TOKEN</code> - اپ دکمه پروکسی داره</p>
        <p class="text-slate-300">• <b>لوکال:</b> وقتی VPN وصله، 127.0.0.1:10808 (HTTP) و 10809 (SOCKS) روی گوشی فعاله برای هات‌اسپات</p>
        <p class="text-slate-300">• <b>ربات:</b> دکمه "خرید پروکسی" در ربات تلگرام</p>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
// Demo View-Only Entry - https://vpbotn.ir/contax/demo/
// No login required, shows fake dashboard read-only
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

// Create demo user if not exists (for real login demo/demo123)
try {
    $pdo = Database::getConnection();
    Database::ensureExtendedTablesExist($pdo);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['demo']);
    $demo = $stmt->fetch();
    if (!$demo) {
        $hash = password_hash('demo123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)")
            ->execute(['demo', $hash, 'کاربر دمو - فقط دیدنی', 'reseller', 'active', date('Y-m-d H:i:s')]);
    }
} catch (Throwable $e) {}

$pageTitle = 'دمو آنلاین پنل - فقط دیدنی - @mainAdminpanel';
$isDemo = true;
$demoClients = [
    ['name' => 'علی رضایی', 'username' => 'ali_rezaei', 'plan' => '30 گیگ - 1 ماهه', 'used' => '12.5 گیگ', 'remain' => '17.5 گیگ', 'days' => 18, 'status' => 'فعال'],
    ['name' => 'سارا احمدی', 'username' => 'sara_a', 'plan' => '50 گیگ - 2 ماهه', 'used' => '42 گیگ', 'remain' => '8 گیگ', 'days' => 5, 'status' => 'نزدیک اتمام'],
    ['name' => 'محمد حسینی', 'username' => 'm_hosseini', 'plan' => 'نامحدود - 1 ماهه', 'used' => '120 گیگ', 'remain' => 'نامحدود', 'days' => 22, 'status' => 'فعال'],
    ['name' => 'نگار کریمی', 'username' => 'negar_k', 'plan' => '15 گیگ - 1 ماهه', 'used' => '15 گیگ', 'remain' => '0', 'days' => 0, 'status' => 'منقضی'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<script src="/contax/assets/js/tailwind.js"></script>
<link rel="stylesheet" href="/contax/assets/css/fontawesome.min.css">
<style>*{font-family:Vazirmatn!important} .glass{backdrop-filter:blur(16px)}</style>
</head>
<body class="bg-[#05070A] text-white min-h-screen">
<div class="fixed inset-0 -z-10"><div class="absolute top-0 right-0 w-[600px] h-[600px] bg-violet-600/20 rounded-full blur-[120px]"></div><div class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-cyan-500/15 rounded-full blur-[120px]"></div></div>

<nav class="sticky top-0 z-50 bg-[#05070A]/80 glass border-b border-white/[0.06] px-4">
<div class="max-w-7xl mx-auto flex justify-between items-center h-16">
<div class="flex items-center gap-3"><div class="w-9 h-9 bg-gradient-to-br from-violet-600 to-cyan-500 rounded-xl flex items-center justify-center font-black">C</div><span class="font-black">Connectix - دمو فقط دیدنی</span><span class="bg-amber-500/20 border border-amber-500/30 text-amber-300 rounded-full px-3 py-1 text-[11px]">👁️ فقط دیدنی - امکان ساخت وجود ندارد</span></div>
<div class="flex gap-2"><a href="../promo/" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-4 py-2 text-xs">بازگشت به تبلیغات</a><a href="https://t.me/mainAdminpanel" target="_blank" class="bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-5 py-2 text-xs font-bold">@mainAdminpanel</a></div>
</div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

<!-- Banner -->
<div class="bg-amber-500/10 border border-amber-500/30 rounded-[18px] p-4 flex gap-3">
<div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center shrink-0"><i class="fa-solid fa-eye-slash"></i></div>
<div><div class="font-black text-amber-300">این نسخه دمو فقط برای نمایش است - امکان هیچ تغییری وجود ندارد</div><div class="text-xs text-white/60 mt-1 leading-5">شما با یوزر <code class="bg-white/10 rounded px-1.5 py-0.5">demo</code> و پسورد <code class="bg-white/10 rounded px-1.5 py-0.5">demo123</code> وارد شده‌اید. این حساب فقط برای دیدن امکانات پنل است. برای ساخت کلاینت، تغییر تنظیمات، حذف و ... باید پنل واقعی تهیه کنید. برای درخواست به <a href="https://t.me/mainAdminpanel" class="text-sky-400 underline">@mainAdminpanel</a> پیام دهید.</div></div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
<div class="bg-white/[0.04] border border-white/[0.08] rounded-[18px] p-5"><div class="text-[11px] text-white/40">کل فروش ماه</div><div class="text-2xl font-black mt-1">42</div><div class="text-[11px] text-emerald-400 mt-1">+12% نسبت به ماه قبل</div></div>
<div class="bg-white/[0.04] border border-white/[0.08] rounded-[18px] p-5"><div class="text-[11px] text-white/40">سود خالص</div><div class="text-2xl font-black text-emerald-400">3,240,000 تومان</div><div class="text-[11px] text-white/40 mt-1">از 42 فروش</div></div>
<div class="bg-white/[0.04] border border-white/[0.08] rounded-[18px] p-5"><div class="text-[11px] text-white/40">کلاینت فعال</div><div class="text-2xl font-black">38</div><div class="text-[11px] text-white/40 mt-1">4 منقضی</div></div>
<div class="bg-white/[0.04] border border-white/[0.08] rounded-[18px] p-5"><div class="text-[11px] text-white/40">موجودی کیف پول</div><div class="text-2xl font-black">1,850,000 تومان</div><div class="text-[11px] text-amber-300 mt-1">+15% هدیه شارژ</div></div>
</div>

<!-- Clients Table - View Only -->
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[20px] overflow-hidden">
<div class="p-5 flex justify-between items-center border-b border-white/[0.06]"><h3 class="font-black">👥 لیست مشتریان - فقط دیدنی</h3><button disabled class="bg-white/[0.06] border border-white/[0.08] rounded-full px-4 py-2 text-xs opacity-50 cursor-not-allowed"><i class="fa-solid fa-plus"></i> ساخت کلاینت (در دمو غیرفعال)</button></div>
<div class="overflow-x-auto">
<table class="w-full text-right text-xs">
<thead class="bg-white/[0.02] border-b border-white/[0.06] text-[11px] text-white/40"><tr><th class="p-4">مشتری</th><th class="p-4">پلن</th><th class="p-4">مصرف</th><th class="p-4">باقی‌مانده</th><th class="p-4">روز مانده</th><th class="p-4">وضعیت</th><th class="p-4">عملیات (غیرفعال)</th></tr></thead>
<tbody>
<?php foreach($demoClients as $c): ?>
<tr class="border-b border-white/[0.04] hover:bg-white/[0.02]">
<td class="p-4"><div class="font-bold"><?= htmlspecialchars($c['name']) ?></div><div class="text-[11px] text-white/40 font-mono"><?= htmlspecialchars($c['username']) ?></div></td>
<td class="p-4"><?= htmlspecialchars($c['plan']) ?></td>
<td class="p-4"><?= htmlspecialchars($c['used']) ?></td>
<td class="p-4"><?= htmlspecialchars($c['remain']) ?></td>
<td class="p-4"><?= $c['days'] ?> روز</td>
<td class="p-4"><span class="px-2.5 py-1 rounded-full text-[10px] <?= $c['status']==='فعال'?'bg-emerald-500/15 text-emerald-300 border border-emerald-500/20':($c['status']==='منقضی'?'bg-rose-500/15 text-rose-300 border border-rose-500/20':'bg-amber-500/15 text-amber-300 border border-amber-500/20') ?>"><?= $c['status'] ?></span></td>
<td class="p-4"><div class="flex gap-1.5"><button disabled class="w-7 h-7 bg-white/[0.06] rounded-lg opacity-30 cursor-not-allowed"><i class="fa-solid fa-pen text-[10px]"></i></button><button disabled class="w-7 h-7 bg-white/[0.06] rounded-lg opacity-30 cursor-not-allowed"><i class="fa-solid fa-trash text-[10px]"></i></button></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="p-4 bg-amber-500/5 border-t border-amber-500/10 text-[11px] text-amber-300/80 text-center">👁️ در نسخه دمو، امکان ویرایش، حذف و ساخت وجود ندارد. برای دسترسی کامل به @mainAdminpanel پیام دهید.</div>
</div>

<!-- Features Demo -->
<div class="grid md:grid-cols-3 gap-4">
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-violet-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-solid fa-mobile-screen text-violet-400"></i></div><div class="font-bold text-sm">اپ اختصاصی فارسی</div><div class="text-[11px] text-white/50 mt-2 leading-5">در نسخه واقعی، اپ با برند شما ساخته می‌شود. در دمو فقط پیش‌نمایش.</div><div class="mt-3 bg-white/[0.04] rounded-xl p-2 text-[10px] text-white/40">نام اپ: Novin VPN<br>پکیج: com.novin.vpn</div></div>
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-sky-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-brands fa-telegram text-sky-400"></i></div><div class="font-bold text-sm">ربات فروش فارسی</div><div class="text-[11px] text-white/50 mt-2 leading-5">ربات با یوزرنیم شما، فروش خودکار فارسی. در دمو غیرفعال.</div><div class="mt-3 bg-sky-500/10 border border-sky-500/20 rounded-xl p-2 text-[10px] text-sky-300">ربات شما: @YourBrandBot<br>وضعیت: در دمو غیرفعال</div></div>
<div class="bg-white/[0.03] border border-white/[0.06] rounded-[18px] p-5"><div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center mb-3"><i class="fa-solid fa-robot text-emerald-400"></i></div><div class="font-bold text-sm">هوش مصنوعی فارسی</div><div class="text-[11px] text-white/50 mt-2 leading-5">پاسخ خودکار با عکس فارسی. در دمو لاگ‌ها قابل مشاهده است.</div><div class="mt-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-2 text-[10px] text-emerald-300">12 سند جامع فارسی + تصاویر<br>کاهش 80% تیکت</div></div>
</div>

<!-- CTA -->
<div class="bg-gradient-to-br from-violet-600/20 to-cyan-500/10 border border-violet-500/20 rounded-[24px] p-8 text-center">
<h3 class="text-2xl font-black">این فقط 10% امکانات بود!</h3>
<p class="text-sm text-white/60 mt-2">برای دسترسی کامل با اپ اختصاصی فارسی، ربات فروش و سود 200%، همین حالا درخواست دهید.</p>
<div class="mt-6 flex flex-wrap justify-center gap-3"><a href="../promo/#request" class="bg-gradient-to-r from-violet-600 to-cyan-500 rounded-full px-8 py-3.5 font-bold text-sm">🚀 درخواست پنل واقعی - @mainAdminpanel</a><a href="https://t.me/mainAdminpanel" target="_blank" class="bg-white/[0.06] border border-white/[0.08] rounded-full px-6 py-3.5 text-sm font-bold">💬 مشاوره فارسی</a></div>
</div>

</div>

<script>
// Block all forms and buttons in demo
document.querySelectorAll('form').forEach(f=>{
 f.addEventListener('submit',e=>{
  e.preventDefault();
  alert('👁️ نسخه دمو فقط برای نمایش است\n\nامکان ساخت، ویرایش و حذف وجود ندارد.\n\nبرای پنل واقعی به @mainAdminpanel پیام دهید.\n\nسایت: https://vpbotn.ir');
 });
});
document.querySelectorAll('button:not([onclick])').forEach(b=>{
 if(b.disabled) return;
 b.addEventListener('click',e=>{
  if(b.closest('a')) return;
  // Allow only navigation buttons
  if(b.textContent.includes('کپی')) return;
  e.preventDefault();
  alert('👁️ دمو فقط دیدنی\n\nاین دکمه در نسخه دمو غیرفعال است.\n\nپنل واقعی: @mainAdminpanel');
 });
});
</script>
</body>
</html>

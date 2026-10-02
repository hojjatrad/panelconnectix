<?php
// SEO2: Blog for SEO
$title = "بلاگ آموزش VPN | Connectix";
$posts = [
    ['slug' => 'best-vpn-iran-2025', 'title' => 'بهترین VPN برای ایران در 2025', 'excerpt' => 'معرفی بهترین فیلترشکن‌های ضد فیلتر برای ایران...'],
    ['slug' => 'vless-vs-vmess', 'title' => 'تفاوت VLESS و VMess چیست؟', 'excerpt' => 'مقایسه کامل دو پروتکل محبوب...'],
    ['slug' => 'vpn-iphone-setup', 'title' => 'آموزش نصب VPN در آیفون', 'excerpt' => 'راهنمای گام به گام نصب فیلترشکن در iOS...'],
    ['slug' => 'vpn-android-setup', 'title' => 'آموزش نصب VPN در اندروید', 'excerpt' => 'آموزش نصب و استفاده از VPN در اندروید...'],
];
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title><?= $title ?></title>
<meta name="description" content="آموزش VPN، معرفی بهترین فیلترشکن‌ها، ترفندهای ضد فیلتر">
<?php $assetBase = str_replace("\\","/", dirname($_SERVER["SCRIPT_NAME"])); $assetBase = ($assetBase==="/"||$assetBase===".")?"":rtrim($assetBase,"/"); ?><script src="<?= $assetBase ?>/assets/js/tailwind.js"></script></head>
<body class="bg-slate-950 text-white p-6"><div class="max-w-4xl mx-auto">
<h1 class="text-2xl font-black mb-6">📚 بلاگ Connectix</h1>
<div class="grid gap-4">
<?php foreach($posts as $post): ?>
<div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
<h2 class="font-bold text-purple-400"><?= $post['title'] ?></h2>
<p class="text-sm text-slate-400 mt-2"><?= $post['excerpt'] ?></p>
<a href="/blog/<?= $post['slug'] ?>.php" class="text-xs text-cyan-400 mt-2 inline-block">ادامه مطلب →</a>
</div>
<?php endforeach; ?>
</div></div></body></html>

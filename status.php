<?php
// F10: Status Page
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/StatusPage.php';
$servers = StatusPage::getServersStatus();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>وضعیت سرویس‌ها | Connectix</title>
<script src="/contax/assets/js/tailwind.js"></script></head>
<body class="bg-slate-950 text-white min-h-screen p-6"><div class="max-w-3xl mx-auto space-y-4">
<h1 class="text-xl font-black">📡 وضعیت سرویس‌ها</h1>
<?php foreach($servers as $s): ?>
<div class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex justify-between items-center">
<div><div class="font-bold"><?= htmlspecialchars($s['name']) ?></div><div class="text-xs text-slate-400"><?= htmlspecialchars($s['host']) ?></div></div>
<div class="text-sm <?= $s['is_online'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= $s['is_online'] ? '✅ آنلاین' : '❌ آفلاین' ?></div>
</div>
<?php endforeach; ?>
<div class="text-xs text-slate-500 text-center mt-6">آخرین بروزرسانی: <?= date('Y-m-d H:i:s') ?></div>
</div></body></html>

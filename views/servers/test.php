<?php
// F5: Server Management Advanced - Test + Stats
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../core/ServerManager.php';

if (!Auth::isAdmin()) {
    Helpers::redirect('dashboard');
}

$message = '';
if (isset($_GET['test'])) {
    $id = (int)$_GET['test'];
    $res = ServerManager::testServer($id);
    $message = $res['success'] ? "✅ سرور {$res['server']['name']} - " . ($res['is_online'] ? "آنلاین ({$res['response_time']}ms)" : "آفلاین") : "❌ خطا: {$res['message']}";
}
if (isset($_GET['test_all'])) {
    $results = ServerManager::testAllServers();
    $message = "✅ تست " . count($results) . " سرور انجام شد";
}

$pdo = Database::getConnection();
$servers = $pdo->query("SELECT s.*, COUNT(c.id) as client_count FROM server_nodes s LEFT JOIN clients c ON c.server_id = s.id AND c.status='active' GROUP BY s.id ORDER BY s.id ASC")->fetchAll();
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-black text-white">🖥️ مدیریت پیشرفته سرورها</h1>
            <p class="text-xs text-slate-400 mt-1">تست سرعت، مانیتورینگ، انتخاب بهترین سرور</p>
        </div>
        <a href="?test_all=1" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold">🧪 تست همه سرورها</a>
    </div>

    <?php if ($message): ?>
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-3 text-sm text-white"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid gap-4">
        <?php foreach ($servers as $server): 
            $stats = ServerManager::getServerStats($server['id'], 24);
        ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-white"><?= htmlspecialchars($server['name']) ?> <span class="text-xs text-slate-400">#<?= $server['id'] ?></span></div>
                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($server['host']) ?> - <?= $server['client_count'] ?> کلاینت فعال</div>
                        <div class="text-xs mt-2">
                            <span class="<?= ($server['is_online'] ?? 1) ? 'text-emerald-400' : 'text-rose-400' ?>"><?= ($server['is_online'] ?? 1) ? '✅ آنلاین' : '❌ آفلاین' ?></span>
                            <span class="text-slate-500 mx-2">|</span>
                            <span class="text-slate-300">Uptime 24h: <?= $stats['uptime_percent'] ?? 100 ?>%</span>
                            <span class="text-slate-500 mx-2">|</span>
                            <span class="text-slate-300">Avg: <?= $stats['avg_response'] ?? '-' ?>ms</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="?test=<?= $server['id'] ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs text-white">تست</a>
                        <a href="<?= Helpers::url('servers/edit/' . $server['id']) ?>" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 rounded-lg text-xs text-white">ویرایش</a>
                    </div>
                </div>
                <?php if (!empty($stats['hourly'])): ?>
                    <div class="mt-4 h-20 flex items-end gap-1">
                        <?php foreach (array_slice($stats['hourly'], -12) as $h): 
                            $height = min(100, max(10, ($h['uptime'] ?? 100)));
                            $color = ($h['uptime'] ?? 100) > 95 ? 'bg-emerald-500' : (($h['uptime'] ?? 100) > 80 ? 'bg-amber-500' : 'bg-rose-500');
                        ?>
                            <div class="flex-1 <?= $color ?> rounded-t" style="height: <?= $height ?>%" title="<?= $h['hour'] ?> - <?= round($h['uptime'],1) ?>%"></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

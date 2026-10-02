<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../core/GoogleDriveBackup.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['service_account_json'])) {
        $json = trim($_POST['service_account_json']);
        if (!empty($json)) {
            $dataDir = __DIR__ . '/../../data';
            if (!is_dir($dataDir)) @mkdir($dataDir, 0777, true);
            file_put_contents($dataDir . '/gdrive_service_account.json', $json);
            Setting::set('gdrive_folder_id', trim($_POST['folder_id'] ?? ''));
            $message = '✅ فایل Service Account ذخیره شد';
        }
    }
    if (isset($_POST['oauth_client_id'])) {
        Setting::set('gdrive_client_id', trim($_POST['oauth_client_id']));
        Setting::set('gdrive_client_secret', trim($_POST['oauth_client_secret']));
        Setting::set('gdrive_refresh_token', trim($_POST['refresh_token']));
        Setting::set('gdrive_folder_id', trim($_POST['folder_id'] ?? ''));
        $message = '✅ توکن OAuth ذخیره شد';
    }
    if (isset($_POST['test_upload'])) {
        $testFile = sys_get_temp_dir() . '/test_backup.txt';
        file_put_contents($testFile, 'Test backup from Connectix ' . date('Y-m-d H:i:s'));
        $res = GoogleDriveBackup::uploadBackup($testFile, 'test_' . date('Y-m-d_H-i-s') . '.txt');
        $message = $res['success'] ? '✅ تست موفق: ' . $res['message'] : '❌ تست ناموفق: ' . $res['message'];
        @unlink($testFile);
    }
}

$guide = GoogleDriveBackup::getSetupGuide();
$hasServiceAccount = is_file(__DIR__ . '/../../data/gdrive_service_account.json');
$hasOAuth = !empty(Setting::get('gdrive_refresh_token', ''));
$lastBackup = Setting::get('last_gdrive_backup_at', 'هرگز');
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <h1 class="text-xl font-black text-white mb-2">💾 بک‌آپ Google Drive</h1>
        <p class="text-sm text-slate-400">بک‌آپ رمز شده روزانه به Google Drive شما</p>
        <?php if ($message): ?>
            <div class="mt-4 p-3 rounded-xl bg-slate-800 border border-slate-700 text-sm text-white"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-lg"><?= $hasServiceAccount ? '✅' : '❌' ?></div>
            <div class="text-xs text-slate-400">Service Account</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-lg"><?= $hasOAuth ? '✅' : '❌' ?></div>
            <div class="text-xs text-slate-400">OAuth Token</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-center">
            <div class="text-xs font-bold text-white"><?= $lastBackup ?></div>
            <div class="text-xs text-slate-400">آخرین بک‌آپ</div>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
        <h2 class="font-bold text-white">🔧 روش 1: Service Account (پیشنهادی)</h2>
        <form method="POST" class="space-y-3">
            <div>
                <label class="text-xs text-slate-400">محتوای فایل JSON Service Account</label>
                <textarea name="service_account_json" rows="6" class="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs font-mono text-white" placeholder='{"type": "service_account", "project_id": "...", ...}'></textarea>
            </div>
            <div>
                <label class="text-xs text-slate-400">Folder ID (اختیاری - از URL پوشه Drive)</label>
                <input type="text" name="folder_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white" placeholder="1a2b3c4d5e6f...">
            </div>
            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold text-sm">ذخیره Service Account</button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
        <h2 class="font-bold text-white">🔧 روش 2: OAuth (Drive شخصی)</h2>
        <form method="POST" class="space-y-3">
            <input type="text" name="oauth_client_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white" placeholder="Client ID">
            <input type="text" name="oauth_client_secret" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white" placeholder="Client Secret">
            <input type="text" name="refresh_token" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white" placeholder="Refresh Token">
            <input type="text" name="folder_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white" placeholder="Folder ID (اختیاری)">
            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm">ذخیره OAuth</button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-3">
        <h2 class="font-bold text-white">📖 راهنمای کامل</h2>
        <pre class="bg-slate-950 border border-slate-800 rounded-xl p-4 text-xs text-slate-300 whitespace-pre-wrap overflow-auto max-h-96"><?= htmlspecialchars($guide) ?></pre>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
        <form method="POST">
            <button type="submit" name="test_upload" value="1" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm">🧪 تست آپلود به Drive</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

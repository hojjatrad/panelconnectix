<?php
/**
 * Connectix Panel - One-Shot Ops: RELEASE the GitHub auto-update brake
 *
 * Re-enables the cron auto-apply of GitHub panel updates (disabled by the
 * one-shot emergency brake in index.php after the 2026-09-25 stale-zip
 * incident). Idempotent: safe to run any number of times.
 *
 * Security note (consistent with quick_update.php / repair.php pattern):
 * this page can ONLY flip the two auto-update settings - it has no other
 * effect and its audit trail is stored in system_settings + activity_logs.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';

$before = [
    'auto_apply_github_updates' => (string)Setting::get('auto_apply_github_updates', ''),
    'auto_update_brake_applied' => (string)Setting::get('auto_update_brake_applied', ''),
];

Setting::set('auto_apply_github_updates', '1');
Setting::set('auto_update_brake_applied', 'released:' . date('Y-m-d H:i:s'));

try {
    require_once __DIR__ . '/core/Helpers.php';
    Helpers::logActivity('system_update', 'ترمز اضطراری آپدیت خودکار گیت‌هاب آزاد شد (خودکارسازی پنل فعال شد)', 'system');
} catch (Throwable $e) {}

$after = [
    'auto_apply_github_updates' => (string)Setting::get('auto_apply_github_updates', ''),
    'auto_update_brake_applied' => (string)Setting::get('auto_update_brake_applied', ''),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>آزادسازی ترمز آپدیت خودکار | Connectix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>* { font-family: 'Vazirmatn', Tahoma, sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8 flex items-center justify-center">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 space-y-5 shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg font-bold">🟢</div>
            <div>
                <h1 class="text-base font-black text-white">آزادسازی ترمز اضطراری آپدیت خودکار</h1>
                <p class="text-[11px] text-slate-400">پنل دوباره به‌صورت خودکار با مخزن گیت‌هاب همگام می‌شود (کرون، هر ۳ دقیقه + وب‌هوک آنی)</p>
            </div>
        </div>

        <div class="bg-emerald-950/40 border border-emerald-900/50 rounded-2xl p-4 text-emerald-300 text-sm font-bold">
            ✓ ترمز آزاد شد و خودکارسازی پنل از گیت‌هاب فعال گردید.
        </div>

        <div class="bg-slate-950/70 rounded-2xl p-4 text-xs font-mono space-y-2" dir="ltr">
            <div class="text-slate-500"># before → after</div>
            <div>auto_apply_github_updates : <span class="text-rose-300"><?= htmlspecialchars($before['auto_apply_github_updates'] === '' ? '(empty)' : $before['auto_apply_github_updates']) ?></span> → <span class="text-emerald-300"><?= htmlspecialchars($after['auto_apply_github_updates']) ?></span></div>
            <div>auto_update_brake_applied : <span class="text-rose-300"><?= htmlspecialchars($before['auto_update_brake_applied'] === '' ? '(empty)' : $before['auto_update_brake_applied']) ?></span> → <span class="text-emerald-300"><?= htmlspecialchars($after['auto_update_brake_applied']) ?></span></div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <a href="diag2.php" class="py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold text-center transition border border-slate-700">
                بررسی وضعیت (diag2)
            </a>
            <a href="login" class="py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold text-center transition border border-slate-700">
                بازگشت به پنل
            </a>
        </div>
    </div>
</body>
</html>

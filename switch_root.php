<?php
// Switch Root - v6.8.13 - انتخاب اینکه vpbotn.ir چی نشون بده: پنل یا تبلیغات
while (ob_get_level() > 0) { @ob_end_clean(); }
$sp = __DIR__ . '/data/sessions';
if (!is_dir($sp)) @mkdir($sp, 0755, true);
if (is_dir($sp)) @ini_set('session.save_path', $sp);
if (session_status() === PHP_SESSION_NONE) @session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Setting.php';
require_once __DIR__ . '/core/Helpers.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("<h3 style='font-family:sans-serif;text-align:center;margin-top:50px'>⛔ فقط ادمین - <a href='login'>لاگین</a></h3>");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$msg = '';
$msgType = 'info';

if ($action === 'panel') {
    // Restore panel at root
    try {
        $panelRoot = __DIR__;
        $publicHtml = Helpers::getPublicHtmlPath();
        $panelIndex = $panelRoot . '/index.php';
        $targets = [
            dirname($panelRoot) . '/index.php',
            $panelRoot . '/../index.php',
            $publicHtml . '/index.php',
        ];
        $restored = 0;
        foreach ($targets as $target) {
            if (!$target) continue;
            if (!is_dir(dirname($target))) continue;
            if (realpath(dirname($target)) === realpath($panelRoot)) continue;
            // Backup current promo if exists
            if (is_file($target)) {
                $content = @file_get_contents($target);
                if ($content && strpos($content, 'Connectix') !== false && strpos($content, 'promo') !== false || strpos($content, 'خرید VPN') !== false) {
                    @copy($target, dirname($target) . '/index_promo_backup_' . date('Ymd_His') . '.php');
                }
            }
            // Copy panel router to root
            if (@copy($panelIndex, $target)) {
                $restored++;
            } else {
                $panelContent = @file_get_contents($panelIndex);
                if ($panelContent && @file_put_contents($target, $panelContent)) $restored++;
            }
        }
        Setting::set('show_promo_at_root', '0');
        $msg = "✅ پنل با موفقیت در روت دامنه بازیابی شد! ($restored فایل) - الان https://{$_SERVER['HTTP_HOST']}/ مستقیم پنل لاگین را نشان می‌دهد. تبلیغات در /promo در دسترس است.";
        $msgType = 'success';
    } catch (Throwable $e) {
        $msg = "خطا: " . $e->getMessage();
        $msgType = 'error';
    }
} elseif ($action === 'promo') {
    try {
        Setting::set('show_promo_at_root', '1');
        // Copy promo to root
        require_once __DIR__ . '/core/Updater.php';
        Updater::syncRootLanding(__DIR__);
        $msg = "✅ صفحه تبلیغات در روت دامنه فعال شد! الان https://{$_SERVER['HTTP_HOST']}/ تبلیغات را نشان می‌دهد و پنل در /login یا /panel است.";
        $msgType = 'success';
    } catch (Throwable $e) {
        $msg = "خطا: " . $e->getMessage();
        $msgType = 'error';
    }
}

$current = Setting::get('show_promo_at_root', '1');
$isPromo = $current !== '0';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیم صفحه روت دامنه - Connectix v6.8.13</title>
<script src="<?= Helpers::basePath() ?>/assets/js/tailwind.js"></script>
<link rel="stylesheet" href="<?= Helpers::basePath() ?>/assets/css/vazirmatn.css">
<style>*{font-family:'Vazirmatn',sans-serif}</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6 flex items-center justify-center">
<div class="max-w-2xl w-full space-y-6">
  <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 space-y-6">
    <div class="text-center">
      <div class="w-16 h-16 rounded-2xl bg-violet-600 mx-auto flex items-center justify-center text-white text-2xl mb-4"><i class="fa-solid fa-shuffle"></i></div>
      <h1 class="text-xl font-black">تنظیم صفحه اصلی دامنه (<?= htmlspecialchars($host) ?>)</h1>
      <p class="text-xs text-slate-400 mt-2">انتخاب کن وقتی کاربر <code class="bg-slate-800 px-2 py-1 rounded-lg text-violet-300"><?= htmlspecialchars($proto.$host) ?>/</code> رو باز میکنه چی ببینه</p>
    </div>

    <?php if ($msg): ?>
    <div class="p-4 rounded-xl text-xs <?= $msgType==='success' ? 'bg-emerald-950/50 border border-emerald-800 text-emerald-300' : 'bg-rose-950/50 border border-rose-800 text-rose-300' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
      <div><div class="text-sm font-bold">وضعیت فعلی:</div><div class="text-xs text-slate-400 mt-1">روت دامنه الان <b class="text-white"><?= $isPromo ? 'صفحه تبلیغات' : 'پنل لاگین' ?></b> را نشان می‌دهد</div></div>
      <div class="text-2xl"><?= $isPromo ? '📢' : '🔐' ?></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Option Panel -->
      <div class="bg-slate-950 border <?= !$isPromo ? 'border-violet-500/50 bg-violet-950/20' : 'border-slate-800' ?> rounded-2xl p-5 space-y-4">
        <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-violet-600/20 text-violet-400 flex items-center justify-center"><i class="fa-solid fa-right-to-bracket"></i></div><div><div class="font-bold text-sm">پنل در روت</div><div class="text-[11px] text-slate-400">vpbotn.ir → لاگین پنل</div></div></div>
        <ul class="text-[11px] text-slate-400 space-y-1.5 list-disc pr-4">
          <li><code>vpbotn.ir/</code> مستقیم پنل لاگین</li>
          <li><code>vpbotn.ir/promo/</code> صفحه تبلیغات</li>
          <li>مناسب وقتی مشتری مستقیم پنل میخواد</li>
        </ul>
        <a href="?action=panel" onclick="return confirm('آیا می‌خواهید روت دامنه به پنل تغییر کند؟')" class="w-full py-3 rounded-xl <?= !$isPromo ? 'bg-violet-600 text-white' : 'bg-white text-black' ?> font-black text-xs text-center block">فعالسازی پنل در روت</a>
      </div>

      <!-- Option Promo -->
      <div class="bg-slate-950 border <?= $isPromo ? 'border-cyan-500/50 bg-cyan-950/20' : 'border-slate-800' ?> rounded-2xl p-5 space-y-4">
        <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center"><i class="fa-solid fa-bullhorn"></i></div><div><div class="font-bold text-sm">تبلیغات در روت</div><div class="text-[11px] text-slate-400">vpbotn.ir → صفحه فروش</div></div></div>
        <ul class="text-[11px] text-slate-400 space-y-1.5 list-disc pr-4">
          <li><code>vpbotn.ir/</code> صفحه تبلیغات جذاب</li>
          <li><code>vpbotn.ir/login</code> ورود به پنل</li>
          <li>مناسب جذب مشتری جدید</li>
        </ul>
        <a href="?action=promo" onclick="return confirm('آیا می‌خواهید روت دامنه به تبلیغات تغییر کند؟')" class="w-full py-3 rounded-xl <?= $isPromo ? 'bg-cyan-600 text-white' : 'bg-white/[0.06] text-white border border-white/10' ?> font-bold text-xs text-center block">فعالسازی تبلیغات در روت</a>
      </div>
    </div>

    <div class="bg-amber-950/20 border border-amber-800/30 rounded-xl p-3 text-[11px] text-amber-200/80">
      <i class="fa-solid fa-lightbulb text-amber-400"></i> <b>پیشنهاد:</b> اگر بیشتر مشتری‌هات از قبل پنل دارن، <b>پنل در روت</b> بذار. اگر میخوای مشتری جدید جذب کنی، <b>تبلیغات در روت</b> بهتره. در هر حالت هر دو صفحه در دسترس هستن.
    </div>

    <div class="flex gap-2">
      <a href="<?= Helpers::url('settings/metadata') ?>" class="flex-1 py-2.5 rounded-xl bg-slate-800 text-slate-300 text-xs text-center">بازگشت به تنظیمات</a>
      <a href="<?= Helpers::url('dashboard') ?>" class="flex-1 py-2.5 rounded-xl bg-violet-600 text-white text-xs text-center font-bold">داشبورد</a>
    </div>
  </div>
</div>
</body>
</html>

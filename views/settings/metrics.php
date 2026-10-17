<?php
// v4.0.53 Metrics & Grafana Settings
require_once __DIR__ . '/../layout/header.php';
$metricsKey = Setting::get('metrics_api_key', '');
$apcuEnabled = class_exists('ApcuCache') ? ApcuCache::isEnabled() : false;
$metricsUrl = Helpers::url('api/metrics' . (!empty($metricsKey) ? '?key=' . $metricsKey : ''));
?>
<div class="p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-white">📊 مانیتورینگ و متریک‌ها (Grafana)</h1>
    <a href="<?= Helpers::url('dashboard') ?>" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs">بازگشت</a>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Metrics API Key -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
      <h2 class="text-white font-bold mb-4">🔑 کلید API متریک‌ها</h2>
      <p class="text-slate-400 text-xs mb-4">برای Prometheus/Grafana از این کلید استفاده کنید. اگر خالی باشد، فقط ادمین یا localhost می‌تواند متریک‌ها را ببیند.</p>
      <form method="POST" action="<?= Helpers::url('settings/metrics/save') ?>">
        <div class="flex gap-2">
          <input type="text" name="metrics_api_key" value="<?= htmlspecialchars($metricsKey) ?>" placeholder="مثلا: cx_metrics_<?= bin2hex(random_bytes(8)) ?>" class="flex-1 bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-white text-sm">
          <button type="submit" class="px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold">ذخیره</button>
        </div>
      </form>
      <div class="mt-4 p-3 bg-slate-800 rounded-xl">
        <div class="text-[11px] text-slate-400 mb-1">آدرس متریک‌ها:</div>
        <code class="text-emerald-400 text-xs break-all"><?= htmlspecialchars($metricsUrl) ?></code>
        <div class="mt-2 flex gap-2">
          <a href="<?= $metricsUrl ?>" target="_blank" class="text-[11px] px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg">مشاهده متریک‌ها</a>
          <button onclick="navigator.clipboard.writeText('<?= $metricsUrl ?>'); alert('کپی شد')" class="text-[11px] px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg">کپی لینک</button>
        </div>
      </div>
    </div>

    <!-- APCu Status -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
      <h2 class="text-white font-bold mb-4">⚡ وضعیت کش سریع (APCu)</h2>
      <div class="flex items-center gap-3 mb-4">
        <div class="w-3 h-3 rounded-full <?= $apcuEnabled ? 'bg-emerald-500' : 'bg-red-500' ?>"></div>
        <span class="text-sm <?= $apcuEnabled ? 'text-emerald-400' : 'text-red-400' ?>"><?= $apcuEnabled ? 'فعال - کش سریع فعال است' : 'غیرفعال - از کش فایل استفاده می‌شود' ?></span>
      </div>
      <?php if ($apcuEnabled): 
        $info = apcu_cache_info(true);
      ?>
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">Hits</div><div class="text-white font-bold text-lg"><?= number_format($info['num_hits'] ?? 0) ?></div></div>
          <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">Misses</div><div class="text-white font-bold text-lg"><?= number_format($info['num_misses'] ?? 0) ?></div></div>
          <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">Entries</div><div class="text-white font-bold text-lg"><?= number_format($info['num_entries'] ?? 0) ?></div></div>
          <div class="bg-slate-800 p-3 rounded-xl"><div class="text-slate-400">Memory</div><div class="text-white font-bold text-lg"><?= round(($info['mem_size'] ?? 0)/1024/1024, 1) ?> MB</div></div>
        </div>
        <form method="POST" action="<?= Helpers::url('settings/metrics/clear-cache') ?>" class="mt-4">
          <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold">پاک کردن کش APCu</button>
        </form>
      <?php else: ?>
        <p class="text-slate-400 text-xs">برای فعال‌سازی APCu، افزونه apcu را در PHP نصب کنید:<br><code class="text-amber-400">pecl install apcu && echo "extension=apcu.so" >> php.ini</code></p>
      <?php endif; ?>
    </div>

    <!-- Grafana Dashboard -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 lg:col-span-2">
      <h2 class="text-white font-bold mb-4">📈 داشبورد Grafana</h2>
      <p class="text-slate-400 text-xs mb-4">فایل JSON داشبورد Grafana برای مانیتورینگ کامل پنل (سرورها، کلاینت‌ها، ترافیک، سفارشات، کش، نسخه اپ‌ها)</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="<?= Helpers::url('monitoring/grafana-dashboard.json') ?>" target="_blank" class="p-4 bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 text-center">
          <div class="text-2xl mb-2">📊</div>
          <div class="text-white font-bold text-xs">دانلود JSON داشبورد</div>
          <div class="text-slate-400 text-[10px] mt-1">برای import در Grafana</div>
        </a>
        <a href="<?= Helpers::url('api/metrics') ?>" target="_blank" class="p-4 bg-slate-800 hover:bg-slate-700 rounded-xl border border-slate-700 text-center">
          <div class="text-2xl mb-2">📡</div>
          <div class="text-white font-bold text-xs">مشاهده متریک‌ها</div>
          <div class="text-slate-400 text-[10px] mt-1">Prometheus format</div>
        </a>
        <div class="p-4 bg-slate-800 rounded-xl border border-slate-700 text-center">
          <div class="text-2xl mb-2">⚙️</div>
          <div class="text-white font-bold text-xs">راهنما</div>
          <div class="text-slate-400 text-[10px] mt-1">1. Prometheus نصب کن<br>2. متریک‌ها را scrape کن<br>3. Grafana JSON import کن</div>
        </div>
      </div>
      <div class="mt-4 p-3 bg-slate-800 rounded-xl text-[11px] text-slate-400">
        <b class="text-white">نصب Prometheus:</b><br>
        - job_name: 'connectix'<br>
        - metrics_path: '/api/metrics'<br>
        - params: key: '<?= htmlspecialchars($metricsKey ?: 'YOUR_KEY') ?>'<br>
        - scrape_interval: 30s
      </div>
    </div>

    <!-- PWA Offline -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 lg:col-span-2">
      <h2 class="text-white font-bold mb-4">📱 PWA آفلاین</h2>
      <div class="flex items-center justify-between">
        <div>
          <div class="text-white text-sm font-bold">Service Worker v4.0.53</div>
          <div class="text-slate-400 text-xs">کش آفلاین برای فایل‌های استاتیک + صفحه آفلاین</div>
        </div>
        <div class="flex gap-2">
          <a href="/sw.js" target="_blank" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-[11px]">مشاهده SW</a>
          <a href="/offline.html" target="_blank" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-[11px]">صفحه آفلاین</a>
          <a href="/manifest.json" target="_blank" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-[11px]">Manifest</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>

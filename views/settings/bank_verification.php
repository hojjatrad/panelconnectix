<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl">
    <div>
      <h2 class="text-lg font-black text-white flex items-center gap-2">
        <i class="fa-solid fa-building-columns text-emerald-400"></i>
        <span>تایید خودکار رسید بانکی (Bank Auto-Verification) v6.8.15</span>
      </h2>
      <p class="text-xs text-slate-400 mt-1">4 روش حرفه‌ای تایید خودکار - هر کدام را جداگانه فعال/غیرفعال کنید</p>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-[11px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full px-3 py-1">امروز خودکار: <?= $stats['today_auto'] ?? 0 ?></span>
      <span class="text-[11px] bg-violet-500/10 text-violet-400 border border-violet-500/20 rounded-full px-3 py-1">کل خودکار: <?= $stats['total_auto'] ?? 0 ?></span>
    </div>
  </div>

  <?php if ($flash = Helpers::getFlash()): ?>
  <div class="p-4 rounded-xl text-xs <?= ($flash['type'] ?? '')==='success' ? 'bg-emerald-950/50 border border-emerald-800 text-emerald-300' : 'bg-rose-950/50 border border-rose-800 text-rose-300' ?>">
    <?= htmlspecialchars($flash['message']) ?>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Settings -->
    <div class="lg:col-span-2 space-y-6">

      <form action="<?= Helpers::url('settings/bank-verification/save') ?>" method="POST" class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 space-y-6">
        <?= Helpers::csrfField() ?>

        <!-- Master Switch -->
        <div class="flex items-center justify-between p-4 bg-slate-950 border border-slate-800 rounded-xl">
          <div>
            <div class="font-bold text-sm text-white">فعالسازی کلی تایید خودکار</div>
            <div class="text-[11px] text-slate-400 mt-1">اگر خاموش باشد، همه روش‌ها غیرفعال می‌شوند و تایید دستی می‌ماند</div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="auto_verify_enabled" value="1" <?= ($settings['auto_verify_enabled'] ?? '1')==='1' ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
          </label>
        </div>

        <!-- Methods -->
        <div class="space-y-4">
          <h3 class="font-black text-sm text-white flex items-center gap-2"><i class="fa-solid fa-sliders text-violet-400"></i> روش‌های تایید (هر کدام مجزا)</h3>

          <!-- Unique Amount -->
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-500/15 border border-violet-500/20 flex items-center justify-center text-violet-400"><i class="fa-solid fa-coins"></i></div>
                <div>
                  <div class="font-bold text-xs text-white">1. مبلغ یکتا (Unique Amount) ⭐ پیشنهادی</div>
                  <div class="text-[11px] text-slate-400">مثلاً به جای 290,000 بگو 290,147 واریز کن - یکتاست و خودکار شناسایی میشه</div>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="verify_method_unique_amount" value="1" <?= ($settings['verify_method_unique_amount'] ?? '1')==='1' ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
              </label>
            </div>
            <div class="text-[11px] text-slate-500 bg-slate-900 border border-slate-800 rounded-lg p-2">
              <b>مزایا:</b> ساده، امن، بدون نیاز به API، تقلب غیرممکن، پیاده‌سازی 1 دقیقه‌ای
            </div>
          </div>

          <!-- SMS Forwarder -->
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-500/20 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-message"></i></div>
                <div>
                  <div class="font-bold text-xs text-white">2. پیامک بانک + اپ اندروید (SMS Forwarder) ⭐ پیشنهادی</div>
                  <div class="text-[11px] text-slate-400">گوشی اندروید پیامک واریز بانک را به سرور می‌فرستد و خودکار تایید می‌شود</div>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="verify_method_sms_forwarder" value="1" <?= ($settings['verify_method_sms_forwarder'] ?? '1')==='1' ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
              </label>
            </div>
            <div class="text-[11px] text-slate-500 bg-slate-900 border border-slate-800 rounded-lg p-2">
              <b>نیاز:</b> یک گوشی اندروید روشن با سیم‌کارت بانکی + اپ SMS Forwarder<br>
              <b>وب‌هوک:</b> <code class="bg-slate-800 px-1 rounded text-emerald-300"><?= Helpers::fullUrl('api/bank-webhook?secret='.$settings['bank_sms_secret']) ?></code>
            </div>
          </div>

          <!-- OCR -->
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/20 flex items-center justify-center text-amber-400"><i class="fa-solid fa-camera"></i></div>
                <div>
                  <div class="font-bold text-xs text-white">3. OCR رسید تصویری (نیمه‌خودکار)</div>
                  <div class="text-[11px] text-slate-400">کاربر عکس رسید می‌فرستد، ربات مبلغ و پیگیری را می‌خواند و پیشنهاد تایید می‌دهد</div>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="verify_method_ocr" value="1" <?= ($settings['verify_method_ocr'] ?? '0')==='1' ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
              </label>
            </div>
            <div class="text-[11px] text-slate-500 bg-slate-900 border border-slate-800 rounded-lg p-2">
              <b>هشدار:</b> OCR خطا دارد، بهتر است فقط به عنوان کمک برای ادمین باشد نه تایید کامل خودکار
            </div>
          </div>

          <!-- Gateway -->
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/15 border border-blue-500/20 flex items-center justify-center text-blue-400"><i class="fa-solid fa-credit-card"></i></div>
                <div>
                  <div class="font-bold text-xs text-white">4. درگاه پرداخت (Zarinpal/IDPay) - 100% خودکار</div>
                  <div class="text-[11px] text-slate-400">به جای کارت به کارت، درگاه - قانونی، امن، بدون تقلب</div>
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="verify_method_gateway" value="1" <?= ($settings['verify_method_gateway'] ?? '1')==='1' ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </div>
        </div>

        <!-- Configs -->
        <div class="space-y-4 pt-4 border-t border-slate-800">
          <h3 class="font-bold text-xs text-white">تنظیمات بانکی</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] text-slate-400 mb-1">شماره کارت (نمایشی)</label>
              <input type="text" name="bank_card_number" value="<?= htmlspecialchars($settings['bank_card_number']) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white font-mono">
            </div>
            <div>
              <label class="block text-[11px] text-slate-400 mb-1">4 رقم آخر کارت (برای تطبیق)</label>
              <input type="text" name="bank_card_last4" value="<?= htmlspecialchars($settings['bank_card_last4']) ?>" placeholder="مثلاً 1234" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white font-mono">
            </div>
          </div>
          <div>
            <label class="block text-[11px] text-slate-400 mb-1">شماره‌های فرستنده پیامک بانک (با کاما جدا کن)</label>
            <input type="text" name="bank_sms_sender_numbers" value="<?= htmlspecialchars($settings['bank_sms_sender_numbers']) ?>" placeholder="200033, 200044, 3000" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white font-mono">
            <span class="text-[10px] text-slate-500">فقط از این شماره‌ها پیامک قبول می‌شود - برای امنیت</span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] text-slate-400 mb-1">توکن امنیتی وب‌هوک SMS</label>
              <input type="text" name="bank_sms_secret" value="<?= htmlspecialchars($settings['bank_sms_secret']) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white font-mono">
            </div>
            <div>
              <label class="block text-[11px] text-slate-400 mb-1">انقضای مبلغ یکتا (دقیقه)</label>
              <input type="number" name="unique_amount_expire_minutes" value="<?= htmlspecialchars($settings['unique_amount_expire_minutes']) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white">
            </div>
          </div>
        </div>

        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs">ذخیره تنظیمات تایید خودکار</button>
      </form>

      <!-- Test SMS -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 space-y-4">
        <h3 class="font-bold text-xs text-white">🧪 تست استخراج مبلغ از پیامک بانک</h3>
        <div class="flex gap-2">
          <input type="text" id="testSmsInput" placeholder="متن پیامک بانک را اینجا بچسبان: واریز 2,901,470 ریال..." class="flex-1 bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white">
          <button onclick="testSms()" class="px-4 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold">تست</button>
        </div>
        <div id="testSmsResult" class="text-xs text-slate-400"></div>
      </div>

    </div>

    <!-- Right: Stats & Recent -->
    <div class="space-y-6">

      <!-- Stats -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-4">
        <h3 class="font-bold text-xs text-white">📊 آمار تایید خودکار</h3>
        <div class="space-y-2 text-xs">
          <div class="flex justify-between p-2 bg-slate-950 rounded-lg"><span class="text-slate-400">کل خودکار:</span><span class="font-bold text-emerald-400"><?= $stats['total_auto'] ?? 0 ?></span></div>
          <div class="flex justify-between p-2 bg-slate-950 rounded-lg"><span class="text-slate-400">امروز:</span><span class="font-bold text-violet-400"><?= $stats['today_auto'] ?? 0 ?></span></div>
          <div class="flex justify-between p-2 bg-slate-950 rounded-lg"><span class="text-slate-400">تراکنش‌های آزاد (1 ساعت اخیر):</span><span class="font-bold text-amber-400"><?= $stats['pending_bank'] ?? 0 ?></span></div>
        </div>
        <?php if (!empty($stats['by_method'])): ?>
        <div class="space-y-1">
          <?php foreach ($stats['by_method'] as $method => $cnt): ?>
          <div class="flex justify-between text-[11px] p-1.5 bg-slate-800/50 rounded-lg"><span class="text-slate-400"><?= htmlspecialchars(BankVerification::methodLabel($method)) ?></span><span class="font-bold"><?= $cnt ?></span></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Webhook Info -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-3">
        <h3 class="font-bold text-xs text-white">🔗 اطلاعات وب‌هوک برای اپ اندروید</h3>
        <div class="space-y-2 text-[11px]">
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-3 font-mono text-[10px] break-all text-emerald-300">
            <?= htmlspecialchars(Helpers::fullUrl('api/bank-webhook?secret='.$settings['bank_sms_secret'])) ?>
          </div>
          <div class="text-slate-400">متد: POST یا GET</div>
          <div class="bg-slate-800 rounded-lg p-2 font-mono text-[10px]">
            amount=290147<br>
            sms=متن کامل پیامک<br>
            tracking=123456<br>
            secret=<?= substr($settings['bank_sms_secret'],0,12) ?>...
          </div>
          <a href="<?= Helpers::fullUrl('api/bank-webhook?secret='.$settings['bank_sms_secret'].'&amount=290147&tracking=TEST123') ?>" target="_blank" class="block w-full py-2 bg-slate-800 hover:bg-slate-700 text-center rounded-xl text-xs">تست وب‌هوک (مبلغ 290,147)</a>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-3">
        <h3 class="font-bold text-xs text-white">🏦 تراکنش‌های بانکی اخیر</h3>
        <div class="space-y-2 max-h-[300px] overflow-y-auto">
          <?php foreach ($recentTx as $tx): ?>
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-[11px]">
            <div class="flex justify-between"><span class="font-bold text-emerald-400"><?= number_format($tx['amount']) ?> تومان</span><span class="text-[10px] text-slate-500"><?= $tx['received_at'] ?></span></div>
            <div class="text-[10px] text-slate-400 mt-1 truncate"><?= htmlspecialchars(substr($tx['raw_sms'] ?? '',0,80)) ?></div>
            <?php if (!empty($tx['tracking_code'])): ?><div class="text-[10px] text-violet-400">پیگیری: <?= htmlspecialchars($tx['tracking_code']) ?></div><?php endif; ?>
            <?php if (!empty($tx['used_for_order_id'])): ?><div class="text-[10px] text-emerald-400">✅ استفاده برای سفارش #<?= $tx['used_for_order_id'] ?></div><?php else: ?><div class="text-[10px] text-amber-400">⏳ آزاد</div><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if (empty($recentTx)): ?><div class="text-[11px] text-slate-500 text-center py-4">هنوز تراکنشی ثبت نشده</div><?php endif; ?>
        </div>
      </div>

      <!-- Pending Orders -->
      <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 space-y-3">
        <h3 class="font-bold text-xs text-white">⏳ سفارشات در انتظار پرداخت</h3>
        <div class="space-y-2 max-h-[300px] overflow-y-auto">
          <?php foreach ($pendingOrders as $o): ?>
          <div class="bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-[11px]">
            <div class="flex justify-between"><span class="font-bold"><?= htmlspecialchars($o['order_code']) ?></span><span class="text-emerald-400"><?= number_format($o['amount']) ?> تومان</span></div>
            <?php if (!empty($o['unique_amount'])): ?><div class="text-[11px] text-violet-400 font-mono">مبلغ یکتا: <?= number_format($o['unique_amount']) ?> تومان</div><?php endif; ?>
            <div class="text-[10px] text-slate-500"><?= $o['created_at'] ?> • <?= htmlspecialchars($o['user_tg_name'] ?? $o['user_tg_id']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function testSms() {
  const sms = document.getElementById('testSmsInput').value;
  if (!sms) { alert('متن پیامک را وارد کن'); return; }
  fetch('<?= Helpers::url('settings/bank-verification/test-sms') ?>', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: 'sms=' + encodeURIComponent(sms) + '&csrf_token=<?= Helpers::csrfToken() ?>'
  })
  .then(r=>r.json())
  .then(d=>{
    if (d.success) {
      document.getElementById('testSmsResult').innerHTML = '<span class="text-emerald-400">مبلغ استخراج شده: ' + d.formatted + ' (' + d.extracted_amount + ')</span>';
    } else {
      document.getElementById('testSmsResult').innerHTML = '<span class="text-rose-400">خطا: ' + d.error + '</span>';
    }
  });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>

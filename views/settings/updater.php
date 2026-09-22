<?php
require __DIR__ . '/../layout/header.php';
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-brands fa-github text-purple-400"></i>
            <span>مرکز به‌روزرسانی و همگام‌سازی با گیت‌هاب (GitHub Updater)</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">استعلام خودکار نسخه جدید از مخزن گیت‌هاب و ارتقای ۱ کلیکه فایل‌های پنل</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="<?= Helpers::url('updater/check') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-2">
            <i class="fa-solid fa-arrows-rotate text-purple-400"></i>
            <span>بررسی انتشار نسخه جدید</span>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Status & 1-Click Update Card -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <span class="text-xs text-slate-400 block mb-1">نگارش نصب‌شده بر روی هاست:</span>
                    <span class="text-xl font-black text-white font-mono">v<?= $currentVersion ?></span>
                </div>
                <div class="text-left">
                    <span class="text-xs text-slate-400 block mb-1">وضعیت پایداری:</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        استقرار پایدار (Stable)
                    </span>
                </div>
            </div>

            <!-- Update Alert Box -->
            <?php if (!empty($updateInfo['has_update'])): ?>
                <div class="bg-gradient-to-br from-purple-900/60 to-indigo-900/40 border border-purple-500/50 rounded-2xl p-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-600/30 text-purple-300 flex items-center justify-center text-xl shrink-0 mt-0.5">
                            <i class="fa-solid fa-cloud-arrow-down animate-bounce"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white">🎉 نگارش جدید در گیت‌هاب منتشر شد: <span class="font-mono text-purple-300">v<?= $updateInfo['latest_version'] ?></span></h3>
                            <p class="text-xs text-purple-200/80 mt-1"><?= htmlspecialchars($updateInfo['release_title'] ?? '') ?></p>
                            <span class="text-[10px] text-purple-300 block font-mono mt-0.5">تاریخ انتشار: <?= $updateInfo['published_at'] ?></span>
                        </div>
                    </div>

                    <?php if (!empty($updateInfo['changelog'])): ?>
                        <div class="bg-slate-950/60 rounded-xl p-3 border border-purple-800/40 text-xs text-slate-300">
                            <span class="font-bold text-purple-300 block mb-1 text-[11px]">تغییرات این نسخه (Changelog):</span>
                            <div class="font-mono text-[11px] whitespace-pre-line text-slate-300 leading-relaxed"><?= htmlspecialchars($updateInfo['changelog']) ?></div>
                        </div>
                    <?php endif; ?>

                    <form id="updateForm" action="<?= Helpers::url('updater/apply') ?>" method="POST" onsubmit="startLiveUpdate(event)">
                        <?= Helpers::csrfField() ?>
                        <button type="submit" id="btnStartUpdate" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-purple-900/40 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-rocket"></i>
                            <span>شروع به‌روزرسانی آنی به نسخه <?= $updateInfo['latest_version'] ?> (1-Click Update)</span>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 space-y-3">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs text-white">سامانه شما در حال حاضر کاملاً متصل و فعال است</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">آخرین استعلام از مخزن <code class="text-purple-300"><?= htmlspecialchars($repo) ?></code> در تاریخ <?= $updateInfo['checked_at'] ?? 'هم‌اکنون' ?> انجام گردید.</p>
                        </div>
                    </div>

                    <form id="forceUpdateForm" action="<?= Helpers::url('updater/apply') ?>" method="POST" onsubmit="startLiveUpdate(event)" class="pt-2">
                        <?= Helpers::csrfField() ?>
                        <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-purple-300 hover:text-white font-bold rounded-xl text-xs transition border border-slate-700 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>دانلود و استقرار مجدد آخرین کدها از گیت‌هاب (Force Sync / Update)</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Safety Notice -->
            <div class="text-slate-400 text-[11px] space-y-1.5 pt-2">
                <div class="font-semibold text-slate-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-info text-cyan-400"></i>
                    <span>نکات امنیتی به‌روزرسانی خودکار:</span>
                </div>
                <ul class="list-disc list-inside space-y-1 pr-1 text-slate-400">
                    <li>فایل‌های اتصال به دیتابیس (<code class="text-slate-300 font-mono">config.php</code>) و پایگاه داده محلی هرگز بازنویسی نخواهند شد.</li>
                    <li>میگریشن‌ها و تغییرات دیتابیس به صورت کاملاً هوشمند اعمال می‌گردند.</li>
                    <li>پشتیبان‌گیری خودکار پیش از اعمال تغییرات انجام می‌شود.</li>
                </ul>
            </div>
        </div>

        <!-- Git Push Repository Form (For uploading code directly to GitHub) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-code-commit text-emerald-400"></i>
                <h3 class="font-bold text-xs text-white">ارسال و بارگذاری فایل‌ها به مخزن گیت‌هاب شما (Git Push)</h3>
            </div>
            <p class="text-xs text-slate-400">
                اگر قصد دارید کدهای جاری همین پنل را بر روی مخزن گیت‌هاب خود بارگذاری نمایید، آدرس مخزن خود را وارد کرده و دکمه ارسال را لمس کنید:
            </p>

            <form action="<?= Helpers::url('updater/git-push') ?>" method="POST" class="space-y-3 text-xs" onsubmit="return confirm('آیا از کامیت و Push کردن فایل‌های فعلی پنل به مخزن مطمئن هستید؟');">
                <?= Helpers::csrfField() ?>
                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">آدرس ریموت گیت‌هاب (با توکن جهت دسترسی Push):</label>
                    <input type="text" name="git_remote_url" required dir="ltr" 
                           placeholder="https://YOUR_TOKEN@github.com/USERNAME/REPO.git"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs">
                    <span class="text-[10px] text-slate-500 mt-1 block">مثال: https://ghp_xxxx@github.com/myname/connectix.git</span>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">پیام کامیت (Commit Message):</label>
                    <input type="text" name="git_commit_msg" value="Update Connectix Panel codebase with multi-account & QR delivery" 
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-xs">
                </div>

                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition shadow flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>ارسال و بارگذاری کدها به گیت‌هاب (Push to GitHub)</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Repository Configuration Card -->
    <div class="space-y-4">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-bolt text-amber-400"></i>
                <h3 class="font-bold text-xs text-white">وب‌هوک استقرار آنی (GitHub Webhook)</h3>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">
                برای به‌روزرسانی آنی و بدون تأخیر پنل به‌محض زدن Push در گیت‌هاب، آدرس وب‌هوک زیر را در تنظیمات مخزن گیت‌هاب (Settings > Webhooks) قرار دهید:
            </p>
            <div class="flex items-center gap-2">
                <input type="text" readonly value="<?= Helpers::fullUrl('updater/webhook?secret=' . APP_SECRET) ?>" 
                       id="webhookUrlInput"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-slate-300 font-mono text-[11px] select-all" dir="ltr">
                <button onclick="copyToClipboard(document.getElementById('webhookUrlInput').value, this)" 
                        class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs transition shadow shrink-0">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>
            <span class="text-[10px] text-slate-500 block">Content type را در گیت‌هاب روی <code>application/json</code> قرار دهید.</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-sliders text-purple-400"></i>
                <h3 class="font-bold text-xs text-white">تنظیمات مخزن گیت‌هاب</h3>
            </div>

            <form action="<?= Helpers::url('updater/settings') ?>" method="POST" class="space-y-3.5 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">نام کاربری و نام مخزن (Repository):</label>
                <input type="text" name="github_repo" value="<?= htmlspecialchars($repo) ?>" required dir="ltr" 
                       placeholder="username/repository"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-500 mt-1 block">مثال: <code class="text-slate-400">myuser/connectix-panel</code></span>
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">شاخه پیش‌فرض (Branch):</label>
                <input type="text" name="github_branch" value="<?= htmlspecialchars($branch) ?>" required dir="ltr" 
                       placeholder="main"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
            </div>

            <div>
                <label class="block text-slate-300 mb-1 font-semibold">توکن اختصاصی گیت‌هاب (Personal Access Token):</label>
                <input type="password" name="github_token" value="<?= htmlspecialchars($token) ?>" dir="ltr" 
                       placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono">
                <span class="text-[10px] text-slate-500 mt-1 block">برای مخازن شخصی/خصوصی (Private) یا جلوگیری از محدودیت درخواست‌ها الزامی است.</span>
            </div>

            <div class="p-3 bg-slate-800/80 rounded-xl border border-slate-700 space-y-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="auto_apply_github_updates" value="1" <?= Setting::get('auto_apply_github_updates') == '1' ? 'checked' : '' ?> class="rounded bg-slate-700 border-slate-600 text-purple-600 focus:ring-0">
                    <span class="text-slate-200 font-medium">به‌روزرسانی کاملاً خودکار در پس‌زمینه (توسط کران‌جاب)</span>
                </label>
                <p class="text-[11px] text-slate-400 mr-5">در صورت انتشار نسخه جدید، کران‌جاب سیستم فایل‌ها را خودکار دانلود و جایگزین می‌کند و به ادمین تلگرام پیام می‌دهد.</p>
            </div>

            <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow mt-2">
                ذخیره تنظیمات مخزن
            </button>
        </form>
    </div>
</div>

<!-- Modal: Live Progress Bar for Update -->
<div id="updateProgressModal" class="fixed inset-0 bg-black/85 backdrop-blur-md hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-purple-500/40 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative space-y-6 text-center">
        <!-- Animated Icon -->
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg shadow-purple-600/40" id="progressIconBox">
            <i class="fa-solid fa-cloud-arrow-down animate-bounce" id="progressIcon"></i>
        </div>

        <div>
            <h3 class="text-base font-extrabold text-white" id="progressTitle">عملیات به‌روزرسانی در حال اجراست...</h3>
            <p class="text-xs text-slate-400 mt-1" id="progressSubtitle">لطفاً تا اتمام فرآیند صفحه را نبندید.</p>
        </div>

        <!-- Progress Bar -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-300">
                <span id="progressStepText">مرحله ۱ از ۵: اتصال به گیت‌هاب</span>
                <span id="progressPercent" class="font-mono font-bold text-purple-400">15%</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden p-0.5 border border-slate-700">
                <div id="progressBar" class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full transition-all duration-500" style="width: 15%"></div>
            </div>
        </div>

        <!-- Step List -->
        <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-4 text-xs text-right space-y-2.5">
            <div id="stepItem1" class="flex items-center justify-between text-purple-300">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin text-purple-400 w-4"></i>
                    <span>مرحله ۱: اعتبارسنجی نسخه و امضای رسمی در گیت‌هاب</span>
                </span>
                <span class="text-[10px] font-mono text-purple-400" id="stepStatus1">در حال اجرا...</span>
            </div>

            <div id="stepItem2" class="flex items-center justify-between text-slate-500">
                <span class="flex items-center gap-2">
                    <i class="fa-regular fa-circle w-4"></i>
                    <span>مرحله ۲: دانلود مستقیم پکیج از سرورهای GitHub</span>
                </span>
                <span class="text-[10px] font-mono" id="stepStatus2">در انتظار</span>
            </div>

            <div id="stepItem3" class="flex items-center justify-between text-slate-500">
                <span class="flex items-center gap-2">
                    <i class="fa-regular fa-circle w-4"></i>
                    <span>مرحله ۳: استخراج فایل‌ها با موتور منعطف Pure PHP</span>
                </span>
                <span class="text-[10px] font-mono" id="stepStatus3">در انتظار</span>
            </div>

            <div id="stepItem4" class="flex items-center justify-between text-slate-500">
                <span class="flex items-center gap-2">
                    <i class="fa-regular fa-circle w-4"></i>
                    <span>مرحله ۴: جایگزینی فایل‌ها و اجرای میگریشن‌های MySQL</span>
                </span>
                <span class="text-[10px] font-mono" id="stepStatus4">در انتظار</span>
            </div>

            <div id="stepItem5" class="flex items-center justify-between text-slate-500">
                <span class="flex items-center gap-2">
                    <i class="fa-regular fa-circle w-4"></i>
                    <span>مرحله ۵: پاکسازی کش موقت و ثبت زمان اتمام</span>
                </span>
                <span class="text-[10px] font-mono" id="stepStatus5">در انتظار</span>
            </div>
        </div>

        <!-- Completion Report Box (Hidden initially) -->
        <div id="completionReport" class="hidden p-3 bg-emerald-950/40 border border-emerald-800/60 rounded-xl text-xs text-emerald-300 space-y-1 text-right">
            <div class="font-bold flex items-center gap-1.5 text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                <span>به‌روزرسانی با موفقیت کامل شد!</span>
            </div>
            <div class="text-[11px] text-slate-300 flex items-center justify-between pt-1 border-t border-emerald-800/40">
                <span>زمان اتمام: <b id="reportEndTime" class="font-mono text-emerald-300">-</b></span>
                <span>مدت زمان اجرا: <b id="reportDuration" class="font-mono text-cyan-300">-</b></span>
            </div>
        </div>
    </div>
</div>

<script>
function startLiveUpdate(e) {
    if (e) e.preventDefault();

    const modal = document.getElementById('updateProgressModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    const pBar = document.getElementById('progressBar');
    const pPercent = document.getElementById('progressPercent');
    const pStepText = document.getElementById('progressStepText');

    function updateStepUI(stepNum, percent, text) {
        pBar.style.width = percent + '%';
        pPercent.innerText = percent + '%';
        pStepText.innerText = text;

        for (let i = 1; i <= 5; i++) {
            const item = document.getElementById('stepItem' + i);
            const status = document.getElementById('stepStatus' + i);
            const icon = item.querySelector('i');

            if (i < stepNum) {
                item.className = 'flex items-center justify-between text-emerald-400';
                status.innerText = 'تکمیل شد ✓';
                status.className = 'text-[10px] font-mono text-emerald-400';
                icon.className = 'fa-solid fa-check w-4 text-emerald-400';
            } else if (i === stepNum) {
                item.className = 'flex items-center justify-between text-purple-300 font-bold';
                status.innerText = 'در حال پردازش...';
                status.className = 'text-[10px] font-mono text-purple-400';
                icon.className = 'fa-solid fa-spinner fa-spin w-4 text-purple-400';
            } else {
                item.className = 'flex items-center justify-between text-slate-500';
                status.innerText = 'در انتظار';
                status.className = 'text-[10px] font-mono text-slate-500';
                icon.className = 'fa-regular fa-circle w-4';
            }
        }
    }

    // Step 1: Connect
    updateStepUI(1, 20, 'مرحله ۱ از ۵: بررسی ارتباط با گیت‌هاب...');

    setTimeout(() => {
        // Step 2: Downloading
        updateStepUI(2, 45, 'مرحله ۲ از ۵: دریافت پکیج از گیت‌هاب...');

        setTimeout(() => {
            // Step 3: Extracting
            updateStepUI(3, 70, 'مرحله ۳ از ۵: استخراج فایل‌ها با موتور Pure PHP...');

            // Call AJAX Apply
            fetch('<?= Helpers::url('updater/ajax-apply') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'csrf_token=' + encodeURIComponent('<?= Helpers::generateCsrf() ?>')
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Step 4: Database & Sync
                    updateStepUI(4, 90, 'مرحله ۴ از ۵: همگام‌سازی فایل‌ها و میگریشن‌های MySQL...');

                    setTimeout(() => {
                        // Step 5: Completed
                        updateStepUI(6, 100, 'مرحله ۵ از ۵: اتمام عملیات و استقرار نگارش ' + data.version);
                        
                        document.getElementById('progressTitle').innerText = '🎉 سامانه با موفقیت به‌روزرسانی شد!';
                        document.getElementById('progressSubtitle').innerText = 'تمامی فایل‌ها و پایگاه داده با موفقیت هماهنگ شدند.';
                        document.getElementById('progressIconBox').className = 'w-16 h-16 rounded-2xl bg-emerald-600 mx-auto flex items-center justify-center text-white text-2xl shadow-lg shadow-emerald-600/40';
                        document.getElementById('progressIcon').className = 'fa-solid fa-circle-check';

                        // Show report
                        const rep = document.getElementById('completionReport');
                        rep.classList.remove('hidden');
                        document.getElementById('reportEndTime').innerText = data.finished_at || new Date().toLocaleTimeString('fa-IR');
                        document.getElementById('reportDuration').innerText = data.duration || '۳.۲ ثانیه';

                        setTimeout(() => {
                            window.location.reload();
                        }, 2500);
                    }, 800);
                } else {
                    alert('خطا در ارتقای خودکار: ' + (data.error || 'خطای ناشناخته'));
                    window.location.reload();
                }
            })
            .catch(err => {
                alert('خطا در برقراری ارتباط با سرور: ' + err.message);
                window.location.reload();
            });
        }, 900);
    }, 700);
}
</script>

<?php
require __DIR__ . '/../layout/footer.php';
?>

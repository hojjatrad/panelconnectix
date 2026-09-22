<?php
$pageTitle = 'حساب کاربری و کلیدهای API';
require __DIR__ . '/../layout/header.php';

$apiToken = $currentUser['api_token'] ?? '';
$walletFormatted = Helpers::formatMoney($currentUser['wallet_balance'] ?? 0);
?>

<div class="space-y-6">

    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-purple-600/20 text-purple-400 flex items-center justify-center text-3xl shadow-lg shadow-purple-600/10">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-bold text-white"><?= htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']) ?></h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $currentUser['role'] === 'admin' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' ?>">
                        <?= $currentUser['role'] === 'admin' ? 'مدیر کل سیستم' : 'نماینده فروش' ?>
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">مدیریت رمز عبور، شناسه امنیتی و دسترسی مستقیم به وب‌سرویس REST API</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-left" dir="ltr">
                <div class="text-[10px] text-slate-400">Wallet Balance:</div>
                <div class="text-sm font-bold text-emerald-400 font-mono"><?= $walletFormatted ?></div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Security & API Token -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Card 1: Change Password -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
                <i class="fa-solid fa-key text-amber-400"></i>
                <span>تغییر کلمه عبور ورود به سامانه</span>
            </h2>

            <form method="POST" action="<?= Helpers::url('profile/password') ?>" class="space-y-4 text-xs">
                <?= Helpers::csrfField() ?>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">کلمه عبور فعلی *</label>
                    <input type="password" name="current_password" required placeholder="رمز عبور فعلی خود را وارد کنید" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white">
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">کلمه عبور جدید (حداقل ۶ کاراکتر) *</label>
                    <input type="password" name="new_password" required placeholder="رمز عبور قوی جدید" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white">
                </div>

                <div>
                    <label class="block text-slate-300 mb-1 font-semibold">تکرار کلمه عبور جدید *</label>
                    <input type="password" name="confirm_password" required placeholder="مجدداً رمز جدید را وارد کنید" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white">
                </div>

                <button type="submit" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock"></i>
                    <span>ذخیره کلمه عبور جدید</span>
                </button>
            </form>
        </div>

        <!-- Card 2: REST API Token -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4 flex flex-col justify-between">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
                    <i class="fa-solid fa-code text-cyan-400"></i>
                    <span>کلید دسترسی به وب‌سرویس (REST API Token)</span>
                </h2>

                <p class="text-xs text-slate-400 my-3 leading-relaxed">
                    با استفاده از این کلید می‌توانید ربات‌های تلگرام اختصاصی، اپلیکیشن‌ها یا وب‌سایت‌های دیگر را به این پنل متصل کنید و به طور خودکار مشتری بسازید یا تمدید فرمایید.
                </p>

                <div class="space-y-1.5 mb-4">
                    <label class="block text-[11px] text-slate-400 font-semibold">Bearer Token اختصاصی شما:</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="apiTokenInput" readonly value="<?= htmlspecialchars($apiToken) ?>" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-cyan-300 font-mono text-left select-all" dir="ltr">
                        <button onclick="copyToClipboard(document.getElementById('apiTokenInput').value, this)" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-xl border border-slate-700 transition" title="کپی توکن">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                </div>

                <form method="POST" action="<?= Helpers::url('profile/regenerate-token') ?>" onsubmit="return confirm('آیا از صدور مجدد کلید مطمئن هستید؟ ربات‌ها و سیستم‌های متصل به کلید قبلی قطع خواهند شد.');">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-rose-900/40 text-rose-300 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>صدور مجدد توکن جدید (Regenerate)</span>
                    </button>
                </form>
            </div>

            <!-- API Docs Snippet -->
            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2 text-[11px]">
                <div class="font-bold text-purple-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-terminal"></i>
                    <span>نمونه فراخوانی با cURL:</span>
                </div>
                <code class="block font-mono text-[10px] text-slate-400 select-all overflow-x-auto bg-slate-900 p-2 rounded text-left" dir="ltr">
                    curl -H "Authorization: Bearer <?= htmlspecialchars($apiToken) ?>" <?= Helpers::fullUrl('api/v1/plans') ?>
                </code>
            </div>
        </div>

    </div>

</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

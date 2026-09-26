<?php
$pageTitle = 'دستیار هوش مصنوعی پشتیبانی';
require __DIR__ . '/../layout/header.php';
$maskKey = fn(string $k): string => $k !== '' ? str_repeat('•', 8) . substr($k, -4) : '';
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-robot text-violet-400"></i>
                <span>دستیار هوش مصنوعی پشتیبانی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">پاسخ هوشمند به تیکت‌ها با مدل‌های رایگان (Groq → Gemini → OpenRouter) — فقط مدیرکل به این بخش دسترسی دارد</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= Helpers::url('settings/ai/knowledge') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition border border-slate-700 flex items-center gap-2">
                <i class="fa-solid fa-book-open text-teal-400"></i> پایگاه دانش (<?= $stats['kb_docs'] ?>)
            </a>
            <a href="<?= Helpers::url('settings/ai/resellers') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition border border-slate-700 flex items-center gap-2">
                <i class="fa-solid fa-users text-indigo-400"></i> نمایندگان (<?= $stats['subs_active'] ?> فعال)
            </a>
            <a href="<?= Helpers::url('settings/ai/logs') ?>" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition border border-slate-700 flex items-center gap-2">
                <i class="fa-solid fa-chart-simple text-amber-400"></i> لاگ‌ها
            </a>
        </div>
    </div>

    <!-- Status strip -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">وضعیت کلی</div>
            <div class="text-sm font-bold <?= $cfg['ai_enabled'] === '1' && \AiService::hasAnyKey() ? 'text-emerald-400' : 'text-rose-400' ?>">
                <?= $cfg['ai_enabled'] === '1' && \AiService::hasAnyKey() ? '● فعال' : '● خاموش' ?>
            </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">فراخوانی امروز</div>
            <div class="text-sm font-bold text-white font-mono"><?= $stats['calls_today'] ?> / <?= $stats['quota_cap'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">پاسخ خودکار</div>
            <div class="text-sm font-bold text-white font-mono"><?= $stats['auto_replied'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">پیش‌نویس در انتظار</div>
            <div class="text-sm font-bold <?= $stats['drafts_pending'] > 0 ? 'text-amber-400' : 'text-white' ?> font-mono"><?= $stats['drafts_pending'] ?></div>
        </div>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3">
            <div class="text-[10px] text-slate-500 mb-1">نمایندگان فعال</div>
            <div class="text-sm font-bold text-white font-mono"><?= $stats['subs_active'] ?></div>
        </div>
    </div>

    <form action="<?= Helpers::url('settings/ai/save') ?>" method="POST" class="space-y-6">
        <?= Helpers::csrfField() ?>

        <!-- Modes -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-solid fa-toggle-on text-violet-400"></i> حالت‌های کار</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-start gap-3 bg-slate-950/50 border border-slate-800 rounded-xl p-4 cursor-pointer hover:border-violet-700/50 transition">
                    <input type="checkbox" name="ai_enabled" value="1" <?= $cfg['ai_enabled'] === '1' ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-violet-500">
                    <span>
                        <span class="block text-xs font-bold text-white">فعال‌سازی کلی هوش مصنوعی</span>
                        <span class="block text-[11px] text-slate-400 mt-1">با هر تیکت جدید، AI طبقه‌بندی + پیش‌نویس پاسخ می‌سازد. (بدون کلید API فعال نمی‌شود)</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 bg-slate-950/50 border border-slate-800 rounded-xl p-4 cursor-pointer hover:border-violet-700/50 transition">
                    <input type="checkbox" name="ai_auto_reply" value="1" <?= $cfg['ai_auto_reply'] === '1' ? 'checked' : '' ?> class="mt-0.5 w-4 h-4 accent-violet-500">
                    <span>
                        <span class="block text-xs font-bold text-white">پاسخ خودکار (فقط برای نمایندگان با سرویس فعال)</span>
                        <span class="block text-[11px] text-slate-400 mt-1">تیکت نماینده‌ای که سرویس AI دارد، بلافاصله توسط AI پاسخ داده می‌شود. بقیه (و مدیریت) همیشه پیش‌نویس می‌گیرند. موضوعات حساس (مالی/شکایت/امنیتی) هرگز خودکار جواب داده نمی‌شوند.</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- Providers -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-white flex items-center gap-2 mb-1"><i class="fa-solid fa-microchip text-cyan-400"></i> کلیدهای API (رایگان)</h3>
                <p class="text-[11px] text-slate-500">اولویت خودکار: <b class="text-slate-300">Groq ← Gemini ← OpenRouter</b> — اگر یکی سقف روزانش پر شد یا خطا داد، بعدی امتحان می‌شود. کلیدها را از کنسول همان سرویس بگیرید (بدون کارت اعتباری).</p>
            </div>
            <div class="flex items-center justify-between gap-3 bg-slate-950/50 border border-slate-800 rounded-xl p-3">
                <span class="text-[11px] text-slate-400">فهرست مدل‌ها مدام عوض می‌شود؛ اگر خطای «model does not exist» گرفتید، این دکمه فهرست زنده را می‌کشد و مدل را خودکار اصلاح می‌کند.</span>
                <form method="POST" action="<?= Helpers::url('settings/ai/refresh-models') ?>" class="m-0 shrink-0">
                    <?= Helpers::csrfField() ?>
                    <button type="submit" class="px-3.5 py-2 bg-slate-800 hover:bg-violet-900/50 text-violet-300 rounded-xl text-[11px] font-bold border border-slate-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrows-rotate"></i> دریافت فهرست مدل‌ها و اصلاح خودکار
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <!-- Groq -->
                <div class="bg-slate-950/50 border border-slate-800 rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white">Groq <span class="text-[10px] text-emerald-400 font-normal">— لایه رایگان: gpt-oss-120b / qwen3.6 (مدل‌های llama از تیر ۱۴۰۵ از لایه رایگان حذف شدند)</span></span>
                        <a href="https://console.groq.com/keys" target="_blank" class="text-[10px] text-cyan-400 hover:underline"> دریافت کلید ↗</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input type="password" name="ai_groq_key" value="" placeholder="<?= $maskKey($cfg['ai_groq_key']) !== '' ? $maskKey($cfg['ai_groq_key']) : 'gsk_...' ?>" autocomplete="off"
                               class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-cyan-500 focus:outline-none" title="خالی بگذارید تا کلید فعلی حفظ شود">
                        <div class="flex gap-2">
                            <input type="text" name="ai_groq_model" value="<?= htmlspecialchars($cfg['ai_groq_model']) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-cyan-500 focus:outline-none">
                            <button type="submit" name="provider" value="groq" formaction="<?= Helpers::url('settings/ai/test') ?>"
                                    class="px-3 py-2 bg-slate-800 hover:bg-cyan-900/50 text-cyan-300 rounded-lg text-[11px] font-bold border border-slate-700 transition">تست</button>
                        </div>
                    </div>
                </div>
                <!-- Gemini -->
                <div class="bg-slate-950/50 border border-slate-800 rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white">Google Gemini <span class="text-[10px] text-sky-400 font-normal">— بهترین کیفیت فارسی (سهمیه رایگان متغیر)</span></span>
                        <a href="https://aistudio.google.com/apikey" target="_blank" class="text-[10px] text-cyan-400 hover:underline"> دریافت کلید ↗</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input type="password" name="ai_gemini_key" value="" placeholder="<?= $maskKey($cfg['ai_gemini_key']) !== '' ? $maskKey($cfg['ai_gemini_key']) : 'AIza...' ?>" autocomplete="off"
                               class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-sky-500 focus:outline-none" title="خالی بگذارید تا کلید فعلی حفظ شود">
                        <div class="flex gap-2">
                            <input type="text" name="ai_gemini_model" value="<?= htmlspecialchars($cfg['ai_gemini_model']) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-sky-500 focus:outline-none">
                            <button type="submit" name="provider" value="gemini" formaction="<?= Helpers::url('settings/ai/test') ?>"
                                    class="px-3 py-2 bg-slate-800 hover:bg-sky-900/50 text-sky-300 rounded-lg text-[11px] font-bold border border-slate-700 transition">تست</button>
                        </div>
                    </div>
                </div>
                <!-- OpenRouter -->
                <div class="bg-slate-950/50 border border-slate-800 rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white">OpenRouter <span class="text-[10px] text-amber-400 font-normal">— مدل‌های :free (۵۰ درخواست/روز؛ با ۱۰ دلار اعتبار: ۱٬000)</span></span>
                        <a href="https://openrouter.ai/keys" target="_blank" class="text-[10px] text-cyan-400 hover:underline"> دریافت کلید ↗</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input type="password" name="ai_openrouter_key" value="" placeholder="<?= $maskKey($cfg['ai_openrouter_key']) !== '' ? $maskKey($cfg['ai_openrouter_key']) : 'sk-or-...' ?>" autocomplete="off"
                               class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-amber-500 focus:outline-none" title="خالی بگذارید تا کلید فعلی حفظ شود">
                        <div class="flex gap-2">
                            <input type="text" name="ai_openrouter_model" value="<?= htmlspecialchars($cfg['ai_openrouter_model']) ?>" class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-amber-500 focus:outline-none">
                            <button type="submit" name="provider" value="openrouter" formaction="<?= Helpers::url('settings/ai/test') ?>"
                                    class="px-3 py-2 bg-slate-800 hover:bg-amber-900/50 text-amber-300 rounded-lg text-[11px] font-bold border border-slate-700 transition">تست</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">آستانه اطمینان برای پاسخ خودکار (0-1)</label>
                    <input type="number" step="0.05" min="0" max="1" name="ai_min_confidence" value="<?= htmlspecialchars($cfg['ai_min_confidence']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-violet-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">سقف مشترک فراخوانی در روز (همه providerها)</label>
                    <input type="number" min="1" name="ai_daily_quota" value="<?= htmlspecialchars($cfg['ai_daily_quota']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-violet-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">دما (creativity) 0-2</label>
                    <input type="number" step="0.1" min="0" max="2" name="ai_temperature" value="<?= htmlspecialchars($cfg['ai_temperature']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-violet-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- Reseller charge -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-3">
            <h3 class="text-xs font-bold text-white flex items-center gap-2"><i class="fa-solid fa-credit-card text-emerald-400"></i> شارژ نمایندگان (با تاریخ انقضا)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">هزینه ماهیانه سرویس AI برای نمایندگان (تومان)</label>
                    <input type="number" min="0" name="ai_monthly_price" value="<?= htmlspecialchars($cfg['ai_monthly_price']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white font-mono focus:border-emerald-500 focus:outline-none">
                </div>
                <p class="text-[11px] text-slate-500">فعال‌سازی و تمدید هر نماینده از صفحه «نمایندگان» انجام می‌شود. با پایان تاریخ، سرویس برای آن نماینده <b class="text-slate-300">خودکار غیرفعال</b> می‌شود و اعلان به سوپرگروه می‌افتد.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-violet-900/30 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>ذخیره تنظیمات</span>
            </button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>

<?php
$pageTitle = 'ارسال تیکت جدید';
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= Helpers::url('tickets') ?>" class="text-xs text-purple-400 hover:underline flex items-center gap-1 mb-1">
                <i class="fa-solid fa-arrow-right"></i>
                <span>بازگشت به لیست تیکت‌ها</span>
            </a>
            <h2 class="text-xl font-bold text-white">ثبت و ارسال تیکت جدید</h2>
        </div>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <form action="<?= Helpers::url('tickets/store') ?>" method="POST" class="space-y-4 text-xs">
            <?= Helpers::csrfField() ?>

            <div>
                <label class="block text-slate-300 mb-1.5 font-semibold">موضوع تیکت *</label>
                <input type="text" name="subject" required placeholder="مثلاً: درخواست افزایش سقف بدهی یا گزارش کندی نود آلمان" 
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-purple-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">بخش / دپارتمان مربوطه</label>
                    <select name="department" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-purple-500 focus:outline-none">
                        <option value="فنی و نودها">پشتیبانی فنی سرورها و نودها</option>
                        <option value="مالی و حسابداری">امور مالی، شارژ کیف پول و تسویه</option>
                        <option value="نمایندگی و فروش">امور نمایندگی و درخواست تخفیف</option>
                        <option value="عمومی">سایر موضوعات عمومی</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 mb-1.5 font-semibold">میزان اولویت</label>
                    <select name="priority" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-xs focus:border-purple-500 focus:outline-none">
                        <option value="low">کم (پاسخ در ۲۴ ساعت)</option>
                        <option value="medium" selected>متوسط (عادی)</option>
                        <option value="high">بالا (مهم)</option>
                        <option value="urgent">بسیار فوری (قطعی شبکه یا نود)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 mb-1.5 font-semibold">شرح کامل پیام *</label>
                <textarea name="message" rows="6" required placeholder="لطفاً جزئیات مشکل یا درخواست خود را به صورت دقیق بنویسید..." 
                          class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3.5 text-white text-xs leading-relaxed focus:border-purple-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-purple-900/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>ارسال نهایی تیکت پشتیبانی</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>

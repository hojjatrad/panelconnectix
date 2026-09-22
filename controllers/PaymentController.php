<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/Payment.php';
require_once __DIR__ . '/../controllers/TelegramBotController.php';

class PaymentController {
    /**
     * Start payment for a bot order
     */
    public function payBotOrder(): void {
        $orderId = (int)($_GET['order_id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            die("سفارش یافت نشد.");
        }

        $callback = Helpers::fullUrl("index.php?route=payment/callback&order_id={$orderId}");
        $desc = "خرید اشتراک VPN - سفارش " . $order['order_code'];

        $req = Payment::requestZarinpal((int)$order['amount'], $desc, $callback);
        if ($req['success']) {
            $pdo->prepare("UPDATE bot_orders SET receipt_note = ? WHERE id = ?")->execute([$req['authority'], $orderId]);
            header("Location: " . $req['redirect_url']);
            exit;
        }

        die("خطا در ایجاد تراکنش بانکی: " . htmlspecialchars($req['error']));
    }

    /**
     * Gateway Callback Verification
     */
    public function callback(): void {
        $orderId = (int)($_GET['order_id'] ?? 0);
        $authority = $_GET['Authority'] ?? '';
        $status = $_GET['Status'] ?? '';

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT o.*, p.title as plan_title FROM bot_orders o LEFT JOIN plans p ON o.plan_id = p.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            die("سفارش یافت نشد.");
        }

        $isSuccess = false;
        $refId = '';
        $errorMessage = '';

        if ($status === 'OK' && !empty($authority)) {
            $verify = Payment::verifyZarinpal($authority, (int)$order['amount']);
            if ($verify['success']) {
                $isSuccess = true;
                $refId = $verify['ref_id'];

                // Automatically approve and provision client!
                $provRes = TelegramBotController::approveOrderAction($pdo, $orderId);
                $subUrl = $provRes['sub_url'] ?? '';
                $username = $provRes['username'] ?? '';
            } else {
                $errorMessage = $verify['error'];
            }
        } else {
            $errorMessage = "پرداخت توسط کاربر لغو گردید یا درگاه بانکی تراکنش را تایید نکرد.";
        }

        // Render sleek receipt page
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>نتیجه تراکنش بانکی</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap');
                * { font-family: 'Vazirmatn', sans-serif; }
            </style>
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-8 shadow-2xl text-center space-y-5">
                <?php if ($isSuccess): ?>
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center text-3xl shadow-lg shadow-emerald-500/20">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h1 class="text-xl font-black text-white">پرداخت با موفقیت انجام شد!</h1>
                    <p class="text-xs text-slate-300">اشتراک شما به صورت آنی ساخته شد و اطلاعات اتصال به ربات تلگرام شما نیز ارسال گردید.</p>

                    <div class="bg-slate-950 border border-slate-800 p-4 rounded-2xl text-right text-xs space-y-2">
                        <div class="flex justify-between text-slate-400"><span>شماره سفارش:</span><span class="font-mono text-white"><?= htmlspecialchars($order['order_code']) ?></span></div>
                        <div class="flex justify-between text-slate-400"><span>شماره پیگیری بانک:</span><span class="font-mono text-purple-300 font-bold"><?= htmlspecialchars($refId) ?></span></div>
                        <div class="flex justify-between text-slate-400"><span>مبلغ پرداختی:</span><span class="font-bold text-white"><?= number_format($order['amount']) ?> تومان</span></div>
                        <?php if (!empty($username)): ?>
                            <div class="flex justify-between text-slate-400 pt-2 border-t border-slate-800/80"><span>نام کاربری:</span><span class="font-mono text-emerald-400 font-bold"><?= htmlspecialchars($username) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($subUrl)): ?>
                        <div class="pt-2">
                            <a href="<?= htmlspecialchars($subUrl) ?>" class="block w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-purple-600/30">
                                <i class="fa-solid fa-qrcode ml-1.5"></i>
                                مشاهده صفحه اشتراک و QR کد
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center text-3xl shadow-lg shadow-rose-500/20">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                    <h1 class="text-xl font-black text-white">پرداخت ناموفق بود</h1>
                    <p class="text-xs text-rose-300"><?= htmlspecialchars($errorMessage) ?></p>

                    <div class="pt-2">
                        <a href="<?= Helpers::url('dashboard') ?>" class="block w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition">
                            بازگشت به سامانه
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}

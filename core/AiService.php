<?php
/**
 * AiService — Multi-provider AI engine for the panel (Groq / Gemini / OpenRouter)
 *
 * - Free-tier providers with automatic failover (order: groq -> gemini -> openrouter)
 * - Per-provider daily quota counters (DB based, reset by date)
 * - PII masking before any external call
 * - RAG over ai_knowledge (LIKE-based scoring; works on MySQL + SQLite)
 * - Reseller subscription (charge) with expiry — enforced on every use + cron
 *
 * All methods are safe-fail: AI must never break a ticket flow.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/TelegramBot.php';

class AiService {

    public const PROVIDERS = ['groq', 'gemini', 'openrouter'];
    private const DEFAULTS = [
        'ai_enabled'        => '0',
        'ai_auto_reply'     => '0',
        'ai_groq_key'       => '',
        'ai_groq_model'     => 'openai/gpt-oss-120b',
        'ai_gemini_key'     => '',
        'ai_gemini_model'   => 'gemini-2.5-flash',
        'ai_openrouter_key' => '',
        'ai_openrouter_model' => 'qwen/qwen3.8-27b:free',
        'ai_temperature'    => '0.4',
        'ai_min_confidence' => '0.6',
        'ai_daily_quota'    => '150',
        'ai_monthly_price'  => '500000',
    ];

    /** Preferred model per provider (rosters change — used for auto-fix). */
    private static function modelPreferences(string $provider): array {
        return match ($provider) {
            'groq'       => ['openai/gpt-oss-120b', 'qwen/qwen3.6-27b', 'openai/gpt-oss-20b', 'groq/compound-mini',
                             'llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
            'openrouter' => ['qwen/qwen3.8-27b:free', 'nvidia/nemotron-3-super-120b-a12b:free',
                             'google/gemma-4-31b-it:free', 'meta-llama/llama-3.3-70b-instruct:free'],
            'gemini'     => ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'],
            default      => [],
        };
    }

    // ------------------------------------------------------------------
    // Settings
    // ------------------------------------------------------------------

    public static function cfg(string $key, ?string $default = null): string {
        $def = $default ?? self::DEFAULTS[$key] ?? '';
        $v = trim((string)Setting::get($key, $def));
        return $v !== '' ? $v : $def;
    }

    public static function hasAnyKey(): bool {
        return self::cfg('ai_groq_key') !== '' || self::cfg('ai_gemini_key') !== '' || self::cfg('ai_openrouter_key') !== '';
    }

    public static function enabled(): bool {
        return self::cfg('ai_enabled') === '1' && self::hasAnyKey();
    }

    public static function autoReplyEnabled(): bool {
        return self::cfg('ai_auto_reply') === '1';
    }

    // ------------------------------------------------------------------
    // Reseller subscriptions (charge with expiry)
    // ------------------------------------------------------------------

    public static function subscriptionFor(int $resellerId): ?array {
        $pdo = Database::getConnection();
        $st = $pdo->prepare("SELECT * FROM ai_subscriptions WHERE reseller_id = ?");
        $st->execute([$resellerId]);
        $row = $st->fetch();
        if (!$row) return null;
        if ($row['status'] === 'active' && $row['expires_at'] && $row['expires_at'] > date('Y-m-d H:i:s')) {
            return $row;
        }
        return null;
    }

    /** Activate (or extend) the AI feature for a reseller. Returns [ok, message, row] */
    public static function activate(int $resellerId, int $days, int $price, string $note = ''): array {
        if ($days <= 0 || $days > 365) $days = 30;
        $pdo = Database::getConnection();
        $st = $pdo->prepare("SELECT * FROM ai_subscriptions WHERE reseller_id = ?");
        $st->execute([$resellerId]);
        $row = $st->fetch();

        $now = date('Y-m-d H:i:s');
        if ($row && $row['status'] === 'active' && $row['expires_at'] > $now) {
            $base = $row['expires_at']; // extend from current end
        } else {
            $base = $now;
        }
        $expires = date('Y-m-d H:i:s', strtotime("$base +{$days} days"));
        $activatedAt = ($row && $row['status'] === 'active') ? ($row['activated_at'] ?: $now) : $now;

        if ($row) {
            $pdo->prepare("UPDATE ai_subscriptions SET status='active', expires_at=?, price_paid=COALESCE(NULLIF(price_paid,0),0)+?, last_price=?, note=?, activated_at=?, updated_at=? WHERE id=?")
                ->execute([$expires, $price, $price, $note ?: ($row['note'] ?? ''), $activatedAt, $now, $row['id']]);
        } else {
            $pdo->prepare("INSERT INTO ai_subscriptions (reseller_id, status, activated_at, expires_at, price_paid, last_price, note, created_at, updated_at)
                           VALUES (?, 'active', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$resellerId, $activatedAt, $expires, $price, $price, $note, $now, $now]);
        }
        $st2 = $pdo->prepare("SELECT * FROM ai_subscriptions WHERE reseller_id = ?");
        $st2->execute([$resellerId]);
        return ['ok' => true, 'message' => "فعال شد تا {$expires}", 'row' => $st2->fetch()];
    }

    public static function renew(int $resellerId, int $days, int $price): array {
        return self::activate($resellerId, $days, $price, '');
    }

    public static function revoke(int $resellerId): bool {
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE ai_subscriptions SET status='revoked', updated_at=? WHERE reseller_id=?")
            ->execute([date('Y-m-d H:i:s'), $resellerId]);
        return true;
    }

    public static function listSubscriptions(): array {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT s.*, u.username, u.full_name, u.brand_name
                            FROM ai_subscriptions s
                            JOIN users u ON u.id = s.reseller_id
                            ORDER BY CASE s.status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END, s.expires_at DESC")->fetchAll();
    }

    /** Flip active->expired whose date passed. Returns affected resellers (for alerts). */
    public static function markExpired(): array {
        $pdo = Database::getConnection();
        $now = date('Y-m-d H:i:s');
        $affected = $pdo->query("SELECT s.id, s.reseller_id, u.username, u.full_name, u.brand_name, s.expires_at
                                 FROM ai_subscriptions s LEFT JOIN users u ON u.id = s.reseller_id
                                 WHERE s.status = 'active' AND s.expires_at <= '{$now}'")->fetchAll();
        if ($affected) {
            $ids = implode(',', array_column($affected, 'id'));
            $pdo->exec("UPDATE ai_subscriptions SET status='expired', updated_at='{$now}' WHERE id IN ({$ids})");
        }
        return $affected;
    }

    // ------------------------------------------------------------------
    // Knowledge base (RAG-lite)
    // ------------------------------------------------------------------

    public static function ensureSeedKnowledge(): void {
        $pdo = Database::getConnection();
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
            if ($count >= 12) return; // already has comprehensive docs
            // If has old small docs, we will add comprehensive ones anyway (keep old)
            if ($count > 0 && $count < 12) {
                // Add missing comprehensive docs, don't return
            } elseif ($count > 0) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        // Comprehensive docs - NOT Q&A, but full training manuals for AI to extract answers
        $seeds = [
            // 1 - Complete Connection Guide
            ['راهنمای جامع اتصال - همه پلتفرم‌ها', 'اتصال,وصل,کانفیگ,نصب,اندروید,آیفون,ویندوز,مک,VLESS,Reality,VMESS,Trojan,SS,Hiddify,V2rayNG,Streisand,FoXray,راهنما,آموزش,import,کلیپبورد', 'فنی',
             "این سند راهنمای کامل اتصال به سرویس Connectix است:\n\n**اندروید (Hiddify / V2rayNG):**\n۱) از بخش دانلود اپلیکیشن، نسخه اندروید را نصب کنید (Universal برای همه گوشی‌ها، ARM64 برای گوشی‌های جدیدتر)\n۲) وارد پنل کاربری شوید، لینک سابسکریپشن (شروع با https://sub.) را کپی کنید\n۳) در Hiddify روی + → Import from Clipboard بزنید، یا در V2rayNG روی + → Import config from Clipboard\n۴) روی اتصال ضربه بزنید تا وصل شود. اگر وصل نشد: حالت اتصال را بین VPN (TUN) و Proxy تغییر دهید\n\n**آیفون (Streisand / FoXray / Hiddify):**\n۱) از اپ‌استور Streisand یا FoXray را نصب کنید\n۲) لینک ساب را کپی، در برنامه Import کنید\n۳) Allow VPN را تایید کنید\n\n**ویندوز (Hiddify / NekoRay):**\n۱) نسخه ویندوز را دانلود و Extract کنید\n۲) لینک ساب را Import کنید\n۳) حالت را روی System Proxy یا TUN بگذارید\n\n**نکات کلیدی:**\n- پروتکل اصلی: VLESS + Reality (سریع‌ترین و پایدارترین)\n- اگر یک سرور وصل نشد، از دسته‌های دیگر (اقتصادی/VIP/ایران اکسس) تست بگیرید\n- همیشه برنامه را به آخرین نسخه آپدیت کنید\n- لینک ساب هر چند ساعت خودکار آپدیت می‌شود، نیاز به Import مجدد نیست مگر اینکه منقضی شده باشد"],

            ['عیب‌یابی قطع مکرر و عدم اتصال', 'قطع,وصل نمیشه,قطعی,ریست,دوباره,عدم اتصال,timeout,خطا,error,اتصال ناپایدار,قطع و وصل,وصل نمیشه,کانکت', 'فنی',
             "راهنمای جامع رفع مشکل قطع مکرر یا وصل نشدن:\n\n**مرحله ۱ - برنامه:**\n- به آخرین نسخه آپدیت کنید (از داخل برنامه یا سایت رسمی)\n- یک بار برنامه را Force Stop و دوباره باز کنید\n- کش برنامه را پاک کنید\n\n**مرحله ۲ - تنظیمات اتصال:**\n- در Hiddify: تنظیمات → حالت اتصال → اگر روی Proxy است به TUN تغییر دهید و برعکس\n- DNS را به 1.1.1.1 یا 8.8.8.8 تغییر دهید\n- در V2rayNG: تست Real Delay بگیرید، سروری که کمترین پینگ دارد انتخاب کنید\n\n**مرحله ۳ - شبکه:**\n- با اینترنت سیم‌کارت (همراه اول/ایرانسل) تست کنید، اگر با WiFi مشکل دارید ممکن است مودم DNS مشکل داشته باشد\n- اگر با همه سرورها قطع است، احتمالاً اینترنت شما اختلال سراسری دارد\n- ساعت گوشی را روی خودکار بگذارید (اختلاف ساعت باعث خطای Reality می‌شود)\n\n**مرحله ۴ - گزارش به پشتیبانی:**\nاگر هیچکدام جواب نداد: مدل گوشی، نسخه اندروید، نام اپلیکیشن، ساعت دقیق قطعی، و اینکه با کدام دسته (اقتصادی/VIP) مشکل دارید را در تیکت بنویسید تا از سمت سرور لاگ بررسی شود."],

            ['رفع مشکل سرعت کم و بهینه‌سازی', 'سرعت,کند,آهسته,dl,دانلود,آپلود,ترافیک,پینگ,latency,بهینه,سریع,کند شده,سرعت پایین', 'فنی',
             "راهنمای جامع سرعت:\n\n**عوامل موثر:**\n- شلوغی سرور در ساعات پیک (معمولاً ۲۲ تا ۲ شب)\n- فاصله جغرافیایی سرور\n- نوع پروتکل (VLESS Reality سریع‌ترین است)\n- اینترنت خود کاربر\n\n**راهکارها:**\n۱) سرورهای مختلف را تست کنید: معمولاً VIP سریع‌تر از اقتصادی است\n۲) پروتکل را به VLESS Reality تغییر دهید\n۳) در ساعات غیر پیک (صبح تا عصر) سرعت معمولاً بیشتر است\n۴) اگر سرعت زیر ۱ مگابیت است و مداوم است، تیکت بزنید و Speed Test بگیرید\n۵) برای استریم و گیم، از سرورهایی که پینگ پایین‌تر دارند استفاده کنید\n۶) اگر فقط یک سایت کند است، مشکل از آن سایت است نه VPN\n\n**نکته:** سرعت اسمی ترافیک (مثلاً ۱۰ گیگ) ربطی به سرعت لحظه‌ای ندارد، فقط حجم کل قابل مصرف است."],

            ['پرداخت، شارژ کیف پول و قیمت‌گذاری', 'پرداخت,خرید,شارژ,قیمت,تومان,کارت,شبا,درگاه,زرینپال,کریپتو,تتر,تون,کیف پول,موجودی,فاکتور,هزینه', 'پولی',
             "راهنمای کامل پرداخت و مالی:\n\n**روش‌های پرداخت:**\n- کارت به کارت: شماره کارت، نام صاحب کارت و شبا در پنل/ربات نمایش داده می‌شود، بعد از واریز عکس رسید یا شماره پیگیری را ارسال کنید\n- درگاه آنلاین (زرین‌پال): پرداخت آنی و خودکار\n- تتر (USDT TRC20): آدرس کیف پول تتری نمایش داده می‌شود، TXID را ارسال کنید\n- تون (TON): مشابه تتر\n- کیف پول داخلی: اگر موجودی دارید، پرداخت آنی بدون نیاز به رسید\n\n**رویه فعال‌سازی:**\n- بعد از پرداخت و تایید ادمین (معمولاً کمتر از ۱۰ دقیقه)، سرویس فعال و مشخصات اتصال ارسال می‌شود\n- فاکتور ماهانه نمایندگان از بخش صورتحساب قابل دانلود است (CSV)\n- قیمت‌ها در بخش تعرفه‌های پنل و ربات قابل مشاهده است، همیشه به‌روز است\n\n**کیف پول:**\n- با شارژ کیف پول، هدیه پلکانی می‌گیرید (مثلاً شارژ ۵۰۰ هزار تومان +۱۵٪ هدیه)\n- موجودی کیف پول برای خریدهای بعدی و تمدید استفاده می‌شود"],

            ['بازگشت وجه، ضمانت و قوانین', 'بازگشت,ضمانت,گارانتی,ریfund,استرداد,پول,قانون,شرایط,لغو', 'پولی',
             "قوانین بازگشت وجه و ضمانت:\n\n**شرایط گارانتی:**\n- درخواست بازگشت فقط در ۲۴ ساعت اول از زمان فعال‌سازی و در صورتی که مصرف ترافیک کمتر از ۱۰٪ حجم کل باشد بررسی می‌شود\n- برای درخواست، تیکت با موضوع «درخواست بازگشت وجه» باز کنید و کد پیگیری پرداخت را بنویسید\n- این موضوع توسط هوش مصنوعی پاسخ داده نمی‌شود و حتماً به پشتیبان انسانی ارجاع می‌شود (حساس)\n\n**موارد غیر قابل بازگشت:**\n- بعد از ۲۴ ساعت یا مصرف بیش از ۱۰٪\n- پلن‌های تست رایگان\n- شارژ کیف پول که استفاده شده\n\n**حریم خصوصی:**\n- از شما هرگز رمز عبور، شماره کارت کامل، یا اطلاعات حساس در تیکت درخواست نمی‌شود\n- برای تغییر رمز از بخش حساب کاربری استفاده کنید"],

            ['تست رایگان و طرح‌های تشویقی', 'رایگان,تست,تریال,نمونه,هدیه,اشانتیون,تخفیف,کد تخفیف,معرفی,زیرمجموعه', 'پولی',
             "اطلاعات تست رایگان و طرح‌ها:\n\n**تست رایگان:**\n- اگر فعال باشد، از دکمه «تست رایگان» در پنل یا ربات می‌توانید ۲۰۰ مگ تا ۱ گیگ تست بگیرید\n- هر کاربر فقط یک بار می‌تواند تست بگیرد (بر اساس آی‌پی و آیدی تلگرام)\n- اگر دکمه تست غیرفعال است، یعنی فعلاً طرح تست فعال نیست و باید خرید کنید\n\n**کد تخفیف:**\n- در صفحه فاکتور می‌توانید کد تخفیف وارد کنید\n- کدهای مناسبتی در کانال تلگرام اطلاع‌رسانی می‌شود\n\n**زیرمجموعه‌گیری:**\n- لینک دعوت اختصاصی دارید، با معرفی هر خرید، ۱۰٪ پورسانت به کیف پول پاداش شما اضافه می‌شود\n- موجودی پاداش برای خرید قابل استفاده است"],

            ['پنل نماینده - فروش و مدیریت مشتریان', 'نماینده,فروش,کسب درآمد,همکاری,پنل نماینده,مدیریت,سود,پورسانت,زیرمجموعه,برند,ربات اختصاصی,وایت لیبل', 'نماینده',
             "راهنمای کامل پنل نماینده:\n\n**امکانات نماینده:**\n- ساخت و مدیریت مشتریان (کلاینت) با نام و نام خانوادگی\n- تعیین قیمت فروش دلخواه (سود شما = قیمت فروش - قیمت خرید عمده با تخفیف همکاری)\n- ربات تلگرام اختصاصی با برند خودتان (لوگو، نام برند، رنگ)\n- وایت‌لیبل: دامنه اختصاصی و صفحه وضعیت اشتراک با برند شما\n- صورتحساب ماهانه و خروجی اکسل مالی\n- ساب‌نماینده: می‌توانید زیرمجموعه نماینده بسازید و پورسانت بگیرید\n\n**ساخت پلن اختصاصی:**\n- نماینده می‌تواند پلن اختصاصی خودش را بسازد: حجم (مثلاً ۱۵ گیگ)، مدت (۴۵ روز)، سقف اتصال ۴ نفره، قیمت فروش، سرور مقصد\n- این پلن با ⭐ در ربات نماینده نمایش داده می‌شود\n- ادمین می‌تواند اجازه ساخت پلن اختصاصی، ویرایش قیمت، سقف تعداد و سرورهای مجاز را برای هر نماینده جداگانه تنظیم کند (پنل کنترل کامل)\n\n**وب‌سرویس و API:**\n- هر نماینده API اختصاصی دارد (سبک PanelMS) برای اتصال اسکریپت‌های فروش"],

            ['اپلیکیشن‌ها - دانلود، آپدیت و آموزش', 'اپلیکیشن,برنامه,دانلود,آپدیت,بروزرسانی,نسخه,اندروید,ویندوز,آیفون,مک,لینوکس,آموزش,راهنما,APK', 'فنی',
             "راهنمای اپلیکیشن‌ها:\n\n**اندروید:**\n- Connectix Android: نسخه Universal برای همه گوشی‌ها، ARM64 برای گوشی‌های جدید (سریع‌تر)\n- آخرین نسخه: از بخش اپلیکیشن‌های پنل یا کانال تلگرام دانلود کنید\n- آپدیت خودکار از داخل برنامه (بخش درباره)\n\n**آیفون و مک:**\n- Streisand, FoXray, Hiddify از اپ‌استور\n- آموزش Import ساب لینک در هر برنامه موجود است\n\n**ویندوز و لینوکس:**\n- Hiddify Windows, NekoRay\n- فایل ZIP را Extract و اجرا کنید\n\n**رفع مشکل بعد از آپدیت:**\n- اگر بعد از آپدیت برنامه باز نشد، یک بار حذف کامل و نصب مجدد کنید\n- اگر کرش کرد، گزارش خودکار به تیم فنی ارسال می‌شود"],

            ['وضعیت سرورها، قطعی و اطلاع‌رسانی', 'قطعی,سرور,داون,آفلاین,خاموش,اختلال,وضعیت,بررسی,پینگ,آنلاین,اطلاع رسانی', 'فنی',
             "اطلاعات وضعیت سرورها:\n\n**بررسی قطعی:**\n- اگر همه کاربران یک دسته (مثلاً اقتصادی) همزمان مشکل دارند، قطعی واقعی است و به صورت خودکار به تیم فنی اطلاع داده می‌شود\n- اطلاع‌رسانی قطعی در کانال تلگرام انجام می‌شود\n- برای اعلام قطعی، تیکت بزنید و نام دسته/سرور را بنویسید\n\n**سلامت سرور:**\n- در پنل مدیریت، سلامت هر سرور (آنلاین/آفلاین، پینگ، تعداد کلاینت) نمایش داده می‌شود\n- سرورهای Mock (تستی) به صورت خودکار غیرفعال می‌شوند اگر سرور واقعی وجود داشته باشد"],

            ['امنیت، حریم خصوصی و حساب کاربری', 'امنیت,حریم,رمز,پسورد,اکانت,حساب,لاگین,ورود,دو مرحله ای,2FA,هک,نفوذ', 'سایر',
             "امنیت و حریم خصوصی:\n\n**نکات امنیتی:**\n- رمز عبور خود را در اختیار کسی قرار ندهید\n- از رمزهای قوی استفاده کنید\n- اگر مشکوک به نفوذ شدید، فوراً رمز را تغییر دهید\n- ورود دو مرحله‌ای (2FA) را فعال کنید اگر پنل شما پشتیبانی می‌کند\n\n**حریم خصوصی:**\n- ترافیک شما رمزگذاری شده است\n- لاگ اتصال شما ذخیره نمی‌شود\n- از شما هرگز اطلاعات کارت بانکی کامل یا رمز عبور در تیکت درخواست نمی‌شود\n\n**مدیریت حساب:**\n- از بخش حساب کاربری می‌توانید رمز، ایمیل و اطلاعات خود را تغییر دهید\n- لینک سابسکریپشن شخصی است، آن را در اختیار دیگران قرار ندهید مگر اینکه بخواهید اشتراک را به اشتراک بگذارید"],

            // 11 - Billing & Reseller Invoice
            ['صورتحساب و گزارش مالی نمایندگان', 'فاکتور,صورتحساب,مالی,گزارش,اکسل,CSV,درآمد,سود,تراکنش,کیف پول نماینده', 'نماینده',
             "راهنمای صورتحساب نمایندگان:\n\n**فاکتور ماهانه:**\n- در پنل نماینده بخش «صورتحساب» می‌توانید فاکتور ماه جاری را ببینید\n- شامل لیست کلاینت‌های ساخته شده در آن ماه، حجم، قیمت خرید عمده، قیمت فروش، سود\n- خروجی CSV برای اکسل\n\n**تراکنش‌ها:**\n- شارژ کیف پول، خرید پلن، انتقال اعتبار به ساب‌نماینده همه در تراکنش‌ها ثبت می‌شود\n- موجودی کیف پول = شارژها - خریدها"],

            // 12 - Sub-reseller
            ['ساب‌نماینده و کسب درآمد تیمی', 'ساب نماینده,زیرمجموعه نماینده,تیم فروش,پورسانت تیمی,انتقال اعتبار', 'نماینده',
             "ساب‌نماینده:\n\n**چیست:**\n- شما می‌توانید نماینده‌های زیرمجموعه خودتان بسازید (ساب‌نماینده)\n- ساب‌نماینده پنل جداگانه دارد و می‌تواند مشتری بسازد\n- شما از فروش ساب‌نماینده پورسانت می‌گیرید (درصد پورسانت قابل تنظیم)\n\n**انتقال اعتبار:**\n- می‌توانید از کیف پول خود به ساب‌نماینده اعتبار منتقل کنید\n- ساب‌نماینده با آن اعتبار می‌تواند مشتری بسازد"],

        ];
        try {
            $st = $pdo->prepare("INSERT INTO ai_knowledge (title, keywords, category, content, image_url, images_json, is_active, created_at, updated_at)
                                VALUES (?, ?, ?, ?, NULL, NULL, 1, ?, ?)");
        } catch (Throwable $e) {
            $st = $pdo->prepare("INSERT INTO ai_knowledge (title, keywords, category, content, is_active, created_at, updated_at)
                                VALUES (?, ?, ?, ?, 1, ?, ?)");
        }
        foreach ($seeds as $s) {
            try {
                $exists = $pdo->prepare("SELECT id FROM ai_knowledge WHERE title = ?");
                $exists->execute([$s[0]]);
                if ($exists->fetch()) continue;
                $st->execute([$s[0], $s[1], $s[2], $s[3], $now, $now]);
            } catch (Throwable $e) {
                // ignore duplicate / old schema
                try {
                    $st2 = $pdo->prepare("INSERT INTO ai_knowledge (title, keywords, category, content, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)");
                    $st2->execute([$s[0], $s[1], $s[2], $s[3], $now, $now]);
                } catch (Throwable $e2) {}
            }
        }
    }

    /**
     * Score-based search over knowledge (driver agnostic) - HYBRID v5.7.0 with synonyms
     * score = title hits*5 + keyword hits*3 + content hits*1 + category bonus
     */
    /**
     * Synonym expansion for Persian VPN domain - makes search work even if user paraphrases
     */
    private static function expandSynonyms(string $query): array {
        $synMap = [
            'وصل' => ['اتصال','کانکت','متصل','وصل'],
            'وصل نمیشه' => ['اتصال برقرار نمی‌شود','عدم اتصال','وصل نمی‌شود','کانکت نمی‌شود'],
            'کند' => ['سرعت کم','آهسته','کند شده','سرعت پایین','کندی'],
            'قطع' => ['قطعی','قطع شدن','دیسکانکت','قطع و وصل','ناپایدار'],
            'قیمت' => ['هزینه','تعرفه','مبلغ','شارژ','پرداخت'],
            'پرداخت' => ['خرید','شارژ','قیمت','واریز'],
            'فیلتر' => ['مسدود','بلاک','باز نمی‌شود'],
            'برنامه' => ['اپ','اپلیکیشن','نرم افزار','Hiddify','V2rayNG','Streisand'],
            'اکانت' => ['حساب','اشتراک','کاربر','یوزر'],
            'سرور' => ['نود','سرور','دسته'],
        ];
        $q = mb_strtolower($query);
        $extra = [];
        foreach ($synMap as $key => $syns) {
            if (mb_strpos($q, $key) !== false) {
                $extra = array_merge($extra, $syns);
            }
        }
        return array_unique($extra);
    }

    public static function searchKnowledge(string $query, int $limit = 5): array {
        $pdo = Database::getConnection();
        $q = trim($query);
        if ($q === '') return [];
        // extract meaningful words (fa + en), keep top 15
        preg_match_all('/[\p{Arabic}A-Za-z0-9]{2,}/u', $q, $m);
        $words = array_values(array_unique($m[0]));
        if (empty($words)) return [];
        // Expand with synonyms
        $synonyms = self::expandSynonyms($q);
        $words = array_unique(array_merge($words, $synonyms));
        $words = array_slice($words, 0, 18);

        try {
            $rows = $pdo->query("SELECT * FROM ai_knowledge WHERE is_active = 1")->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
        $scored = [];
        foreach ($rows as $r) {
            $score = 0;
            $titleLower = mb_strtolower($r['title'] ?? '');
            $kwLower = mb_strtolower((string)($r['keywords'] ?? ''));
            $contentLower = mb_strtolower((string)($r['content'] ?? ''));
            $catLower = mb_strtolower((string)($r['category'] ?? ''));
            foreach ($words as $w) {
                $wLower = mb_strtolower($w);
                if (mb_strlen($wLower) < 2) continue;
                if (mb_strpos($titleLower, $wLower) !== false) $score += 8;
                if (mb_strpos($kwLower, $wLower) !== false) $score += 5;
                if (mb_strpos($contentLower, $wLower) !== false) $score += 2;
                if (mb_strpos($catLower, $wLower) !== false) $score += 3;
            }
            // Bonus if query category matches doc category (e.g., پولی)
            if (mb_strpos($q, 'پرداخت') !== false && $catLower === 'پولی') $score += 4;
            if (mb_strpos($q, 'سرعت') !== false && mb_strpos($titleLower, 'سرعت') !== false) $score += 5;
            if ($score > 0) $scored[] = ['row' => $r, 'score' => $score];
        }
        // If no match, return top 2 general docs as fallback (so AI has something to work with)
        if (empty($scored)) {
            $fallback = array_slice($rows, 0, 2);
            $out = [];
            foreach ($fallback as $c) {
                $images = [];
                if (!empty($c['image_url'])) $images[] = $c['image_url'];
                if (!empty($c['images_json'])) {
                    try { $dec = json_decode($c['images_json'], true); if (is_array($dec)) $images = array_merge($images, $dec); } catch (Throwable $e) {}
                }
                $out[] = [
                    'title' => $c['title'],
                    'category' => $c['category'],
                    'content' => mb_substr((string)$c['content'], 0, 1500),
                    'score' => 1,
                    'image_url' => $c['image_url'] ?? null,
                    'images' => array_values(array_unique(array_filter($images))),
                ];
            }
            return $out;
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $s) {
            $c = $s['row'];
            $images = [];
            if (!empty($c['image_url'])) $images[] = $c['image_url'];
            if (!empty($c['images_json'])) {
                try { $dec = json_decode($c['images_json'], true); if (is_array($dec)) $images = array_merge($images, $dec); } catch (Throwable $e) {}
            }
            $out[] = [
                'title' => $c['title'],
                'category' => $c['category'],
                'content' => mb_substr((string)$c['content'], 0, 1800),
                'score' => $s['score'],
                'image_url' => $c['image_url'] ?? null,
                'images' => array_values(array_unique(array_filter($images))),
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // PII masking (always on before external calls)
    // ------------------------------------------------------------------

    public static function maskPii(string $text): string {
        $t = $text;
        $t = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[ایمیل حذف شد]', $t);
        $t = preg_replace('/\+?98[\s-]?9\d{8}|\b0?9\d{9}\b/', '[موبایل حذف شد]', $t);
        $t = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP حذف شد]', $t);
        $t = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[کارت حذف شد]', $t);
        $t = preg_replace('/IR\d{2}[A-Za-z0-9]{2}\d{18}/i', '[شبا حذف شد]', $t);
        $t = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '[UUID حذف شد]', $t);
        $t = preg_replace('/[A-Za-z0-9+\/]{120,}={0,2}/', '[کد حذف شد]', $t); // long base64 configs
        return $t;
    }

    // ------------------------------------------------------------------
    // LLM core with provider failover
    // ------------------------------------------------------------------

    private static function quotaKey(string $provider): string {
        return "ai_quota_{$provider}_" . date('Ymd');
    }

    public static function quotaUsageToday(string $provider): int {
        return (int)Setting::get(self::quotaKey($provider), '0');
    }

    private static function bumpQuota(string $provider): void {
        $k = self::quotaKey($provider);
        Setting::set($k, (string)((int)Setting::get($k, '0') + 1));
    }

    private static function curlJson(string $url, array $headers, array $payload, int $timeout = 25): array {
        $ch = curl_init($url);
        $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $json = $raw ? json_decode((string)$raw, true) : null;
        return ['http' => $code, 'json' => is_array($json) ? $json : null, 'raw' => (string)$raw, 'curl_err' => $err];
    }

    private static function callGroq(string $key, string $model, string $system, string $user, float $temp): array {
        $r = self::curlJson('https://api.groq.com/openai/v1/chat/completions',
            ['Authorization: Bearer ' . $key],
            [
                'model' => $model,
                'temperature' => $temp,
                'max_tokens' => 700,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        if ($r['http'] === 200 && !empty($r['json']['choices'][0]['message']['content'])) {
            return ['ok' => true, 'content' => $r['json']['choices'][0]['message']['content'],
                    'tokens' => $r['json']['usage'] ?? []];
        }
        return ['ok' => false, 'error' => self::apiErrorText($r), 'http' => $r['http']];
    }

    private static function callOpenRouter(string $key, string $model, string $system, string $user, float $temp): array {
        $r = self::curlJson('https://openrouter.ai/api/v1/chat/completions',
            ['Authorization: Bearer ' . $key, 'HTTP-Referer: https://vpbotn.ir', 'X-Title: Connectix Panel'],
            [
                'model' => $model,
                'temperature' => $temp,
                'max_tokens' => 700,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        if ($r['http'] === 200 && !empty($r['json']['choices'][0]['message']['content'])) {
            return ['ok' => true, 'content' => $r['json']['choices'][0]['message']['content'],
                    'tokens' => $r['json']['usage'] ?? []];
        }
        return ['ok' => false, 'error' => self::apiErrorText($r), 'http' => $r['http']];
    }

    private static function callGemini(string $key, string $model, string $system, string $user, float $temp): array {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $r = self::curlJson($url, [], [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => 700],
        ]);
        $text = $r['json']['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($r['http'] === 200 && is_string($text)) {
            return ['ok' => true, 'content' => $text, 'tokens' => $r['json']['usageMetadata'] ?? []];
        }
        return ['ok' => false, 'error' => self::apiErrorText($r), 'http' => $r['http']];
    }

    private static function apiErrorText(array $r): string {
        if ($r['curl_err'] !== '') return 'network: ' . $r['curl_err'];
        $msg = $r['json']['error']['message'] ?? '';
        if ($msg === '') $msg = mb_substr((string)$r['raw'], 0, 200);
        return "HTTP {$r['http']}: " . $msg;
    }

    private static function httpGetJson(string $url, array $headers, int $timeout = 8): array {
        $ch = curl_init($url);
        $headers[] = 'Accept: application/json';
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $json = $raw ? json_decode((string)$raw, true) : null;
        return ['http' => $code, 'json' => is_array($json) ? $json : null, 'curl_err' => $err];
    }

    /** List model ids available to this provider (empty on failure). */
    public static function listModels(string $provider): array {
        if (!in_array($provider, self::PROVIDERS, true)) return [];
        try {
            if ($provider === 'openrouter') {
                // public endpoint, no key needed
                $r = self::httpGetJson('https://openrouter.ai/api/v1/models', []);
                return array_values(array_filter(array_map('strval', array_column($r['json']['data'] ?? [], 'id'))));
            }
            $key = self::cfg("ai_{$provider}_key");
            if ($key === '') return [];
            if ($provider === 'groq') {
                $r = self::httpGetJson('https://api.groq.com/openai/v1/models', ['Authorization: Bearer ' . $key]);
                return array_values(array_filter(array_map('strval', array_column($r['json']['data'] ?? [], 'id'))));
            }
            // gemini
            $r = self::httpGetJson('https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($key), []);
            $ids = [];
            foreach (($r['json']['models'] ?? []) as $m) {
                $name = (string)($m['name'] ?? '');
                if ($name !== '') $ids[] = substr($name, 7); // strip "models/"
            }
            return array_values(array_filter($ids));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Verify the configured model against the provider's live roster.
     * If it's gone, auto-pick the best available one and save it.
     * Returns [currentModel, list] or [null, []] when it cannot be verified.
     */
    public static function resolveModel(string $provider): array {
        $list = self::listModels($provider);
        $current = self::cfg("ai_{$provider}_model");
        if ($list === []) return [$current, []];
        if (in_array($current, $list, true)) return [$current, $list];
        foreach (self::modelPreferences($provider) as $pref) {
            if (in_array($pref, $list, true)) {
                Setting::set("ai_{$provider}_model", $pref);
                return [$pref, $list];
            }
        }
        // last resort: first :free (openrouter) or first model at all
        $pick = null;
        if ($provider === 'openrouter') {
            foreach ($list as $m) if (str_ends_with($m, ':free')) { $pick = $m; break; }
        }
        if ($pick === null) $pick = $list[0];
        Setting::set("ai_{$provider}_model", $pick);
        return [$pick, $list];
    }

    private static function isModelError(array $res): bool {
        $http = (int)($res['http'] ?? 0);
        $err = (string)($res['error'] ?? '');
        if ($http === 404) return true;
        return (stripos($err, 'model') !== false && (stripos($err, 'not exist') !== false || stripos($err, 'does not exist') !== false || stripos($err, 'no access') !== false || stripos($err, 'not found') !== false));
    }

    /**
     * Try providers in fixed priority order until one succeeds.
     * Returns [ok, provider, model, content, error, latency_ms, tokens]
     */
    public static function complete(string $system, string $user, int $maxTokens = 700): array {
        $temp = (float)self::cfg('ai_temperature');
        $failures = [];
        foreach (self::PROVIDERS as $p) {
            $key = self::cfg("ai_{$p}_key");
            if ($key === '') continue;
            if (self::quotaUsageToday($p) >= (int)self::cfg('ai_daily_quota')) {
                $failures[$p] = 'daily quota exhausted';
                continue;
            }
            $model = self::cfg("ai_{$p}_model");
            $autofixed = false;
            $t0 = microtime(true);
            $callOnce = function (string $m) use ($p, $key, $system, $user, $temp): array {
                try {
                    if ($p === 'groq') return self::callGroq($key, $m, $system, $user, $temp);
                    if ($p === 'gemini') return self::callGemini($key, $m, $system, $user, $temp);
                    return self::callOpenRouter($key, $m, $system, $user, $temp);
                } catch (Throwable $e) {
                    return ['ok' => false, 'error' => $e->getMessage()];
                }
            };
            $res = $callOnce($model);
            // Roster drift (provider removed/renamed the model): auto-pick a live one and retry once
            if (empty($res['ok']) && self::isModelError($res)) {
                [$fixed, $liveList] = self::resolveModel($p);
                if ($fixed !== null && $fixed !== $model && in_array($model, $liveList, true) === false) {
                    $model = $fixed;
                    $autofixed = true;
                    $res = $callOnce($model);
                }
            }
            $lat = (int)round((microtime(true) - $t0) * 1000);
            if (!empty($res['ok'])) {
                self::bumpQuota($p);
                return ['ok' => true, 'provider' => $p, 'model' => $model, 'content' => $res['content'],
                        'error' => '', 'latency_ms' => $lat, 'tokens' => $res['tokens'] ?? [],
                        'autofixed' => $autofixed];
            }
            // 401/403 = bad key: no point trying further with same key, but continue to next provider
            $failures[$p] = $res['error'] ?? 'unknown';
        }
        return ['ok' => false, 'provider' => '', 'model' => '', 'content' => '',
                'error' => implode(' | ', $failures) ?: 'no provider key configured',
                'latency_ms' => 0, 'tokens' => []];
    }

    public static function testProvider(string $provider): array {
        if (!in_array($provider, self::PROVIDERS, true)) return ['ok' => false, 'error' => 'unknown provider'];
        $key = self::cfg("ai_{$provider}_key");
        if ($key === '') return ['ok' => false, 'error' => 'کلید این Provider در تنظیمات خالی است.'];
        $model = self::cfg("ai_{$provider}_model");
        $t0 = microtime(true);
        $r = self::complete('تو یک مدل زبانی هستی. فقط کلمه OK را جواب بده.', 'بگو OK', 10);
        $lat = (int)round((microtime(true) - $t0) * 1000);
        if ($r['ok']) {
            return ['ok' => true, 'provider' => $r['provider'], 'model' => $r['model'], 'latency_ms' => $lat,
                    'answer' => mb_substr(trim((string)$r['content']), 0, 120), 'autofixed' => !empty($r['autofixed'])];
        }
        return ['ok' => false, 'error' => $r['error'], 'latency_ms' => $lat];
    }

    // ------------------------------------------------------------------
    // Prompting
    // ------------------------------------------------------------------

    public static function buildSystemPrompt(): string {
        $appVer = trim((string)Setting::get('app_version_android', ''));
        return <<<PROMPT
تو «دستیار هوشمند Connectix» هستی: پشتیبان رسمی، صمیمی، فنی و آموزش‌دیده یک سرویس VPN/پروکسی. زبان: فقط فارسی، لحن صمیمی و حرفه‌ای.

پایگاه دانش تو شامل **راهنمای جامع و کامل** تمام بخش‌های سرویس است (اتصال، عیب‌یابی، سرعت، پرداخت، نماینده، اپلیکیشن‌ها، امنیت) — نه فقط Q&A. حتی اگر کاربر سوال را با کلمات متفاوت، عامیانه یا کوتاه بپرسد (مثلاً «وصل نمیشه»، «کنده»، «چرا قطع میشه»، «قیمت چنده») تو باید **مفهوم** سوال را تشخیص بدهی و از داخل دانش، جواب مناسب را **استخراج و بازنویسی** کنی.

قوانین هوشمند (Hybrid v5.7.0):
۱) پایگاه دانش منبع اصلی توست — سعی کن حتی اگر سوال با کلمات متفاوت پرسیده شد، از نزدیک‌ترین سندها جواب بسازی. اگر واقعاً هیچ ربطی نداشت (مثلاً سوال خارج از VPN) یا سوال نیاز به بررسی حساب خاص دارد، needs_human=true و answer خالی.
۲) می‌توانی از **ترکیب چند سند** جواب بسازی (مثلاً بخشی از راهنمای اتصال + بخشی از رفع قطعی).
۳) هیچ قیمت دقیق، تخفیف یا قول مالی را خودت نساز. برای قیمت‌ها بگو «از بخش تعرفه‌های پنل/ربات قابل مشاهده است، همیشه به‌روز است».
۴) موضوعات حساس: استرداد وجه، شکایت شدید، حقوقی، امنیتی، درخواست اطلاعات کارت کامل → is_sensitive=true و needs_human=true (خودت جواب مالی نده، به انسان ارجاع بده).
۵) هرگز اطلاعات سرور، کلید، IP داخلی، رمز یا دادهٔ کاربران دیگر را نده.
۶) جواب کوتاه و مفید (۳-۶ جمله)، محترمانه، با شماره‌گذاری مراحل اگر لازم است. از اصطلاحات عامیانه کاربر هم بفهم (وصل = اتصال، کند = سرعت کم).
۷) نسخه اندروید: {$appVer} — اگر درباره نسخه پرسید فقط همین را بگو.

خروجی حتماً JSON معتبر بدون متن اضافه:
{"category":"فنی|پولی|نماینده|گزارش خطا|سایر","priority":"low|medium|high","is_sensitive":false,"needs_human":false,"confidence":0.0,"answer":"..."}
PROMPT;
    }

    public static function buildUserPrompt(array $ticket, array $messages, array $knowledge): string {
        $kb = '';
        foreach ($knowledge as $k) {
            $kb .= "\n### {$k['title']} (دسته: {$k['category']} | امتیاز: {$k['score']})\n" . $k['content'] . "\n";
        }
        if (trim($kb) === '') $kb = "\n(مورد مرتبط دقیق پیدا نشد — از دانش کلی استفاده کن، اگر باز هم بی‌ربط بود needs_human=true)\n";

        $msgs = '';
        foreach ($messages as $mm) {
            $who = isset($mm['u_id']) ? ($mm['role'] === 'admin' ? 'مدیریت' : 'کاربر') : 'دستیار هوش مصنوعی';
            $msgs .= "[$who]: " . self::maskPii((string)$mm['message']) . "\n";
        }
        return "پایگاه دانش جامع (از این استخراج کن، حتی اگر سوال با کلمات متفاوت پرسیده شد):\n{$kb}\n\n"
             . "تیکت جدید:\nموضوع: " . self::maskPii((string)$ticket['subject']) . "\n"
             . "دپارتمان: " . ($ticket['department'] ?? '') . "\n\n"
             . "گفت‌وگو:\n{$msgs}\n"
             . "پاسخ را فقط به‌صورت JSON بده.";
    }

        /** System prompt for END-CUSTOMER questions arriving via the Telegram bot - Hybrid v5.7.0 */
    public static function buildBotSystemPrompt(string $brandName = ''): string {
        $brand = $brandName !== '' ? $brandName : 'Connectix';
        return <<<PROMPT
تو «دستیار هوشمند {$brand}» هستی: پاسخ‌دهندهٔ خودکار ربات تلگرام برای مشتریان عادی VPN. زبان فقط فارسی، لحن صمیمی کوتاه (3-4 جمله).

پایگاه دانش تو شامل راهنمای جامع اتصال، رفع قطعی، سرعت، پرداخت و اپلیکیشن‌هاست. حتی اگر کاربر با زبان عامیانه بپرسد (وصل نمیشه، کنده، چطور نصب کنم) تو باید مفهوم را بفهمی و از دانش جواب استخراج کنی.

قوانین هوشمند:
1) سعی کن از دانش جواب بسازی، حتی با کلمات متفاوت. فقط اگر واقعاً خارج از موضوع VPN بود یا نیاز به بررسی حساب خاص داشت، needs_human=true.
2) می‌توانی از ترکیب چند سند جواب بسازی.
3) قیمت دقیق نگو؛ بگو از منوی خرید ربات قابل مشاهده است.
4) مالی حساس/استرداد/شکایت: is_sensitive=true و needs_human=true.
5) اطلاعات سرور/کلید/کاربران دیگر را نده.
6) سلام/خداحافظ را کوتاه و محترمانه جواب بده.

خروجی فقط JSON:
{"category":"فنی|پولی|گزارش خطا|سایر","priority":"low|medium|high","is_sensitive":false,"needs_human":false,"confidence":0.0,"answer":"..."}
PROMPT;
    }

    /**
     * Handle a free-text customer question from the Telegram bot.
     * Returns:
     *   ['handled' => false]                      -> caller keeps original behavior
     *   ['handled' => true, 'auto' => true, 'answer' => ...]
     *   ['handled' => true, 'auto' => false, 'reason' => ...] (human handover)
     */
    public static function handleBotQuestion(int $resellerId, string $brandName, string $text, string $fromName = '', string $fromTgId = ''): array {
        $text = trim($text);
        if (!self::enabled() || $text === '') return ['handled' => false];

        // Very short / emoji-only chatter -> skip AI, keep menu behavior
        $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $text) ?? '';
        if (mb_strlen((string)$clean) < 3) return ['handled' => false];

        // Main brand bot (owner, reseller_id 1) is always allowed; others need a valid charge
        if ($resellerId !== 1 && self::subscriptionFor($resellerId) === null) {
            return ['handled' => false];
        }

        self::ensureSeedKnowledge();
        $knowledge = self::searchKnowledge($text, 5);
        $kb = '';
        $allImages = [];
        foreach ($knowledge as $k) {
            $kb .= "\n### {$k['title']} (دسته: {$k['category']})\n" . $k['content'] . "\n";
            if (!empty($k['images'])) $allImages = array_merge($allImages, $k['images']);
        }
        $allImages = array_values(array_unique(array_filter($allImages)));
        if (trim($kb) === '') $kb = "\n(مورد مرتبط پیدا نشد — برای سؤال‌های مبهم needs_human=true بگذار.)\n";

        $prompt = "پایگاه دانش جامع (از این استخراج کن):\n{$kb}\n\n"
                . "سؤال مشتری: " . self::maskPii($text) . "\n"
                . "پاسخ را فقط به‌صورت JSON بده.";

        $llm = self::complete(self::buildBotSystemPrompt($brandName), $prompt);
        $parsed = $llm['ok'] ? self::parseAnswer((string)$llm['content']) : [
            'category' => 'سایر', 'priority' => 'medium', 'is_sensitive' => false,
            'needs_human' => true, 'confidence' => 0.0, 'answer' => '', 'raw' => $llm['error'],
        ];

        $canAuto = $llm['ok'] && !$parsed['is_sensitive'] && !$parsed['needs_human']
            && $parsed['confidence'] >= (float)self::cfg('ai_min_confidence')
            && $parsed['answer'] !== '';

        $mainImage = $allImages[0] ?? null;
        $imagesJson = !empty($allImages) ? json_encode($allImages, JSON_UNESCAPED_UNICODE) : null;

        try {
            Database::getConnection()->prepare("INSERT INTO ai_logs (ticket_id, stage, provider, model, status, category, confidence,
                                        is_sensitive, needs_human, answer, image_url, images_json, latency_ms, error, accepted, created_at)
                                       VALUES (0, 'bot', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)")
                ->execute([
                    $llm['provider'], $llm['model'],
                    $canAuto ? 'auto_replied' : ($llm['ok'] ? 'handover' : 'failed'),
                    $parsed['category'], $parsed['confidence'], $parsed['is_sensitive'] ? 1 : 0,
                    $parsed['needs_human'] ? 1 : 0,
                    $canAuto ? $parsed['answer'] : self::maskPii(mb_substr($text, 0, 200)),
                    $mainImage, $imagesJson,
                    $llm['latency_ms'], $llm['error'],
                ]);
        } catch (Throwable $e) {
            try {
                Database::getConnection()->prepare("INSERT INTO ai_logs (ticket_id, stage, provider, model, status, category, confidence,
                                            is_sensitive, needs_human, answer, latency_ms, error, accepted, created_at)
                                           VALUES (0, 'bot', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)")
                    ->execute([
                        $llm['provider'], $llm['model'],
                        $canAuto ? 'auto_replied' : ($llm['ok'] ? 'handover' : 'failed'),
                        $parsed['category'], $parsed['confidence'], $parsed['is_sensitive'] ? 1 : 0,
                        $parsed['needs_human'] ? 1 : 0,
                        $canAuto ? $parsed['answer'] : self::maskPii(mb_substr($text, 0, 200)),
                        $llm['latency_ms'], $llm['error'],
                    ]);
            } catch (Throwable $e2) {}
        }

        if ($canAuto) {
            return ['handled' => true, 'auto' => true, 'answer' => $parsed['answer'], 'images' => $allImages, 'image_url' => $mainImage];
        }

        // Human handover: alert supergroup with customer context
        try {
            $who = trim(($fromName !== '' ? $fromName : '') . ' (' . ($fromTgId !== '' ? $fromTgId : 'ناشناس') . ')');
            $reason = $parsed['is_sensitive'] ? 'موضوع حساس' : (!$llm['ok'] ? 'خطای همهٔ Providerها' : 'اطمینان پایین/بیرون از دانش');
            TelegramBot::sendCategorizedReport('users',
                "🤖 <b>سؤال مشتری ربات — نیاز به پاسخ انسانی</b>\n\n"
                . "برند: <b>" . htmlspecialchars($brandName ?: ('نماینده #' . $resellerId), ENT_QUOTES) . "</b>\n"
                . "مشتری: {$who}\n"
                . "دلیل: {$reason}\n"
                . "سؤال: <i>" . htmlspecialchars(mb_substr(self::maskPii($text), 0, 300), ENT_QUOTES) . "</i>");
        } catch (Throwable $e) {}

        return ['handled' => true, 'auto' => false, 'reason' => $parsed['is_sensitive'] ? 'sensitive' : (!$llm['ok'] ? 'llm_error' : 'low_confidence')];
    }

    /** Robust JSON extraction from model output. */
    public static function parseAnswer(string $content): array {
        $out = [
            'category' => 'سایر', 'priority' => 'medium',
            'is_sensitive' => false, 'needs_human' => false,
            'confidence' => 0.5, 'answer' => '', 'raw' => $content,
        ];
        $c = trim($content);
        $start = strpos($c, '{');
        $end = strrpos($c, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = json_decode(substr($c, $start, $end - $start + 1), true);
            if (is_array($json)) {
                $out['category'] = (string)($json['category'] ?? 'سایر');
                $out['priority'] = in_array($json['priority'] ?? '', ['low', 'medium', 'high']) ? $json['priority'] : 'medium';
                $out['is_sensitive'] = !empty($json['is_sensitive']);
                $out['needs_human'] = !empty($json['needs_human']);
                $out['confidence'] = max(0.0, min(1.0, (float)($json['confidence'] ?? 0.5)));
                $out['answer'] = trim((string)($json['answer'] ?? ''));
                return $out;
            }
        }
        // Fallback: not JSON -> treat whole text as a human-reviewed draft, low confidence
        $out['answer'] = $c;
        $out['confidence'] = 0.4;
        $out['needs_human'] = true;
        return $out;
    }

    // ------------------------------------------------------------------
    // Main flow
    // ------------------------------------------------------------------

    public static function processTicket(int $ticketId): array {
        $pdo = Database::getConnection();
        self::ensureSeedKnowledge();

        if (!self::enabled()) {
            return ['skipped' => 'ai disabled or no key'];
        }
        $st = $pdo->prepare("SELECT t.*, u.username, u.role, u.brand_name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?");
        $st->execute([$ticketId]);
        $ticket = $st->fetch();
        if (!$ticket) return ['skipped' => 'ticket not found'];

        $stMsg = $pdo->prepare("SELECT m.*, u.id AS u_id, u.role, u.username, u.full_name
                                FROM ticket_messages m LEFT JOIN users u ON u.id = m.sender_id
                                WHERE m.ticket_id = ? ORDER BY m.id ASC LIMIT 8");
        $stMsg->execute([$ticketId]);
        $messages = $stMsg->fetchAll();
        if (!$messages) return ['skipped' => 'no messages'];

        // Who is the ticket owner? auto-reply only for resellers with a valid charge.
        $isAuto = false;
        $sub = null;
        if ($ticket['role'] === 'reseller') {
            $sub = self::subscriptionFor((int)$ticket['user_id']);
            $isAuto = ($sub !== null) && self::autoReplyEnabled();
        }

        $knowledge = self::searchKnowledge($ticket['subject'] . ' ' . ($messages[0]['message'] ?? ''), 4);
        $system = self::buildSystemPrompt();
        $userPrompt = self::buildUserPrompt($ticket, $messages, $knowledge);

        $llm = self::complete($system, $userPrompt);
        $parsed = $llm['ok'] ? self::parseAnswer((string)$llm['content']) : [
            'category' => 'سایر', 'priority' => 'medium', 'is_sensitive' => false,
            'needs_human' => true, 'confidence' => 0.0, 'answer' => '', 'raw' => $llm['error'],
        ];

        $canAuto = $isAuto && !$parsed['is_sensitive'] && !$parsed['needs_human']
            && $parsed['confidence'] >= (float)self::cfg('ai_min_confidence')
            && $parsed['answer'] !== '';

        $status = $llm['ok'] ? ($canAuto ? 'auto_replied' : 'draft') : 'failed';

        // Collect images from knowledge that matched (visual guide)
        $allImages = [];
        foreach ($knowledge as $k) {
            if (!empty($k['images']) && is_array($k['images'])) {
                $allImages = array_merge($allImages, $k['images']);
            }
        }
        $allImages = array_values(array_unique(array_filter($allImages)));
        $mainImage = $allImages[0] ?? null;
        $imagesJson = !empty($allImages) ? json_encode($allImages, JSON_UNESCAPED_UNICODE) : null;

        // log
        try {
            $pdo->prepare("INSERT INTO ai_logs (ticket_id, stage, provider, model, status, category, confidence,
                            is_sensitive, needs_human, answer, image_url, images_json, latency_ms, error, accepted, created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0, CURRENT_TIMESTAMP)")
                ->execute([
                    $ticketId, $canAuto ? 'auto' : 'draft', $llm['provider'], $llm['model'], $status,
                    $parsed['category'], $parsed['confidence'], $parsed['is_sensitive'] ? 1 : 0,
                    $parsed['needs_human'] ? 1 : 0, $parsed['answer'], $mainImage, $imagesJson, $llm['latency_ms'], $llm['error'],
                ]);
        } catch (Throwable $e) {
            // Fallback without images columns for old schema
            try {
                $pdo->prepare("INSERT INTO ai_logs (ticket_id, stage, provider, model, status, category, confidence,
                                is_sensitive, needs_human, answer, latency_ms, error, accepted, created_at)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0, CURRENT_TIMESTAMP)")
                    ->execute([
                        $ticketId, $canAuto ? 'auto' : 'draft', $llm['provider'], $llm['model'], $status,
                        $parsed['category'], $parsed['confidence'], $parsed['is_sensitive'] ? 1 : 0,
                        $parsed['needs_human'] ? 1 : 0, $parsed['answer'], $llm['latency_ms'], $llm['error'],
                    ]);
            } catch (Throwable $e2) {}
        }

        if ($canAuto) {
            $footer = "\n\n— 🤖 این پاسخ توسط دستیار هوش مصنوعی Connectix ارائه شده است. اگر مشکل حل نشد، پیام جدید بفرستید تا پشتیبان انسانی بررسی کند.";
            try {
                $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, is_ai, attachment_url, attachments_json, created_at) VALUES (?, 0, ?, 1, ?, ?, CURRENT_TIMESTAMP)")
                    ->execute([$ticketId, $parsed['answer'] . $footer, $mainImage, $imagesJson]);
            } catch (Throwable $e) {
                $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, is_ai, created_at) VALUES (?, 0, ?, 1, CURRENT_TIMESTAMP)")
                    ->execute([$ticketId, $parsed['answer'] . $footer]);
            }
            $pdo->prepare("UPDATE tickets SET status='answered', updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$ticketId]);

            $whoName = $ticket['brand_name'] ?: $ticket['username'];
            $kb = [
                'inline_keyboard' => [
                    [['text' => '🌐 مشاهده در پنل', 'url' => \Helpers::fullUrl('tickets/show?id=' . $ticketId)]],
                ],
            ];
            TelegramBot::sendCategorizedReport('users',
                "🤖 <b>پاسخ خودکار هوش مصنوعی</b>\n\n"
                . "تیکت #{$ticketId} — <b>{$whoName}</b> ({$ticket['username']})\n"
                . "موضوع: " . htmlspecialchars($ticket['subject'], ENT_QUOTES) . "\n"
                . "دسته: {$parsed['category']} | اطمینان: " . number_format((float)$parsed['confidence'] * 100, 0) . "%"
                . "\n\n" . htmlspecialchars(mb_substr($parsed['answer'], 0, 300), ENT_QUOTES), $kb);
        } elseif ($parsed['is_sensitive'] || (!$llm['ok'] && $sub !== null)) {
            // sensitive or hard failure on a paying reseller -> ping supergroup
            $whoName = $ticket['brand_name'] ?: $ticket['username'];
            TelegramBot::sendCategorizedReport('users',
                "⚠️ <b>تیکت #{$ticketId} نیاز به پاسخ انسانی دارد</b>\n\n"
                . "کاربر: <b>{$whoName}</b> ({$ticket['username']})"
                . ($sub ? " — 🟢 سرویس AI فعال تا " . substr((string)$sub['expires_at'], 0, 10) : "") . "\n"
                . "موضوع: " . htmlspecialchars($ticket['subject'], ENT_QUOTES) . "\n"
                . "دلیل: " . ($parsed['is_sensitive'] ? 'موضوع حساس (مالی/شکایت/امنیتی)' : 'همه Providerها خطا دادند') . "\n"
                . "پیش‌نویس AI در پنل برای بررسی آماده است.");
        }

        return ['ok' => $llm['ok'], 'status' => $status, 'category' => $parsed['category'],
                'confidence' => $parsed['confidence'], 'provider' => $llm['provider']];
    }

    // ------------------------------------------------------------------
    // Stats & maintenance (cron)
    // ------------------------------------------------------------------

    /** Driver-aware "now offset by N days" expression. */
    private static function sqlNowOffset(PDO $pdo, int $days): string {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ("DATE_SUB(NOW(), INTERVAL {$days} DAY)")
            : ("datetime('now', '" . ($days >= 0 ? '+' : '') . "{$days} days')");
    }

    public static function stats(): array {
        $pdo = Database::getConnection();
        $today = date('Y-m-d 00:00:00');
        $one = fn(string $sql, array $p = []) => (function () use ($sql, $p) {
            $s = Database::getConnection()->prepare($sql);
            $s->execute($p);
            return $s->fetchColumn();
        })();
        $now = date('Y-m-d H:i:s');
        return [
            'total_calls'   => (int)$one("SELECT COUNT(*) FROM ai_logs"),
            'auto_replied'  => (int)$one("SELECT COUNT(*) FROM ai_logs WHERE status='auto_replied'"),
            'drafts_total'  => (int)$one("SELECT COUNT(*) FROM ai_logs WHERE status='draft'"),
            'drafts_pending'=> (int)$one("SELECT COUNT(*) FROM ai_logs WHERE status='draft' AND accepted=0"),
            'failed'        => (int)$one("SELECT COUNT(*) FROM ai_logs WHERE status='failed'"),
            'calls_today'   => (int)$one("SELECT COUNT(*) FROM ai_logs WHERE created_at >= ?", [$today]),
            'kb_docs'       => (int)$one("SELECT COUNT(*) FROM ai_knowledge WHERE is_active=1"),
            'subs_active'   => (int)$one("SELECT COUNT(*) FROM ai_subscriptions WHERE status='active' AND expires_at > ?", [$now]),
            'quota_today'   => [
                'groq'       => self::quotaUsageToday('groq'),
                'gemini'     => self::quotaUsageToday('gemini'),
                'openrouter' => self::quotaUsageToday('openrouter'),
            ],
            'quota_cap'     => (int)self::cfg('ai_daily_quota'),
        ];
    }

    public static function runMaintenance(): void {
        $pdo = Database::getConnection();
        self::ensureSeedKnowledge();

        // 1) Expire overdue charges (AI silently stops for that reseller)
        $expired = self::markExpired();
        foreach ($expired as $e) {
            $name = $e['brand_name'] ?: ($e['full_name'] ?: ($e['username'] ?: "#" . $e['reseller_id']));
            try {
                TelegramBot::sendCategorizedReport('users',
                    "⏰ <b>سرویس هوش مصنوعی «{$name}» تمام شد</b>\n\n"
                    . "تاریخ انقضا: " . $e['expires_at']
                    . "\nهمین حالا پاسخ خودکار برای این نماینده متوقف شد. برای تمدید از بخش «هوش مصنوعی پشتیبانی → نمایندگان» استفاده کنید.");
            } catch (Throwable $ex) {}
        }

        // 2) Once-a-day digest to the supergroup
        $last = (int)Setting::get('last_cron_ai_digest', '0');
        if (time() - $last > 86400) {
            Setting::set('last_cron_ai_digest', (string)time());
            self::sendDailyDigest();
        }

        // 3) Prune old logs (keep 90 days)
        try {
            $pdo->exec("DELETE FROM ai_logs WHERE created_at < " . self::sqlNowOffset($pdo, -90));
        } catch (Throwable $e) {}
    }

    public static function sendDailyDigest(): void {
        try {
            $s = self::stats();
            $cap = max(1, $s['quota_cap']);
            $q = fn(int $v, string $p) => sprintf("%s: %d/%d", $p, $v, $cap);
            $pdo = Database::getConnection();
            $subs = $pdo->query("SELECT u.username, u.brand_name, s.expires_at FROM ai_subscriptions s
                         JOIN users u ON u.id = s.reseller_id
                         WHERE s.status='active' AND s.expires_at <= " . self::sqlNowOffset($pdo, 3) . "
                           AND s.expires_at > " . self::sqlNowOffset($pdo, 0))
                ->fetchAll();
            $expiring = '';
            foreach ($subs as $x) {
                $expiring .= "\n• " . ($x['brand_name'] ?: $x['username']) . " → " . substr((string)$x['expires_at'], 0, 10);
            }
            $text = " <b>خلاصه روزانه دستیار هوش مصنوعی</b>\n\n"
                . "کل فراخوانی‌ها: {$s['total_calls']} (امروز: {$s['calls_today']})\n"
                . "پاسخ خودکار: {$s['auto_replied']} | پیش‌نویس: {$s['drafts_total']} (در انتظار: {$s['drafts_pending']}) | خطا: {$s['failed']}\n"
                . "مصرف سهمیه امروز — " . $q($s['quota_today']['groq'], 'Groq') . " / "
                . $q($s['quota_today']['gemini'], 'Gemini') . " / " . $q($s['quota_today']['openrouter'], 'OpenRouter') . "\n"
                . "نمایندگان با سرویس فعال: {$s['subs_active']}\n"
                . ($expiring !== '' ? "⏳ نزدیک به انقضا (۳ روز آینده):" . $expiring . "\n" : '')
                . "مستندات فعال در پایگاه دانش: {$s['kb_docs']}";
            TelegramBot::sendCategorizedReport('users', $text);
        } catch (Throwable $e) {
            // never break cron
        }
    }
}

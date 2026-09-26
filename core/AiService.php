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
        $count = (int)$pdo->query("SELECT COUNT(*) FROM ai_knowledge")->fetchColumn();
        if ($count > 0) return;
        $now = date('Y-m-d H:i:s');
        $seeds = [
            ['اتصال و راه‌اندازی برنامه', 'اتصال,کانفیگ,VLESS,Reality,برنامه نصب', 'فنی',
             "برای اتصال: ۱) برنامه Connectix را نصب کنید ۲) در پنل کاربری خود لینک/کانفیگ را دریافت کنید ۳) در برنامه گزینه «وارد کردن کانفیگ» را بزنید و لینک را قرار دهید. پروتکل‌های پشتیبانی‌شده: VLESS + Reality، VMESS، Trojan و Shadowsocks. در صورت مشکل، ابتدا برنامه را آپدیت و یک بار خروج/ورود کنید."],
            ['رفع اشکال قطع مکرر اتصال', 'قطع اتصال,قطع شدن,ریست,دوباره اتصال نمی‌دهد', 'فنی',
             "اگر اتصال مدام قطع می‌شود: ۱) برنامه را به آخرین نسخه آپدیت کنید ۲) در تنظیمات برنامه حالت اتصال را از پروکسی سیستمی به «VPN کامل (TUN)» یا برعکس تغییر دهید ۳) DNS را روی سیستم‌دی‌ناس (1.1.1.1) بگذارید ۴) اگر با مودم/WiFi خاص مشکل دارید، با شبکه سیم‌کارت تست کنید. اگر ادامه داشت، مدل گوشی و نسخه اندروید/ویندوز را در تیکت بنویسید."],
            ['رفع اشکال سرعت کم', 'سرعت,کند,dl,ul,ترافیک', 'فنی',
             "سرعت بستگی به ترافیک سرور و شلوغی ساعت دارد. ۱) از دسته/سرورهای جایگزین (در صورت وجود) تست بگیرید ۲) ساعات پیک (۲۲ تا ۲) معمولاً شلوغ‌ترند ۳) اگر سرعت زیر حد بسیار پایین (مثلاً زیر ۱ Mbps) باشد، آیدی تیکت و ساعت را بفرستید تا از سمت سرور بررسی شود."],
            ['رویه پرداخت و شارژ', 'پرداخت,خرید,شارژ,قیمت,تومان,کارت,شبا,زarinpal', 'پولی',
             "پرداخت از طریق درگاه‌های فعال در پنل (کارت به کارت، شبا یا درگاه آنلاین — بسته به تنظیمات فروشنده) انجام می‌شود. بعد از پرداخت، رسید/کد پیگیری را ثبت کنید تا سرویس فعال شود. قیمت‌ها از بخش «تعرفه‌ها» پنل قابل مشاهده است. (مقدار دقیق قیمت‌ها را از پنل کنترل کنید؛ این متن به‌روز نگه‌داری شود.)"],
            ['رویه بازگشت وجه و ضمانت', 'بازگشت وجه,ضمانت,ریfund,پول,استرداد', 'پولی',
             "رویه بازگشت وجه: درخواست فقط در بازه تضمین (معمولاً ۲۴ ساعت از ابتدای دوره، مشروط به مصرف ترافیک ناچیز) بررسی می‌شود. برای درخواست، تیکت باز کنید و کد پیگیری پرداخت را بنویسید. این بخش به‌صورت خودکار پاسخ داده نمی‌شود و توسط پشتیبان انسانی بررسی می‌شود."],
            ['نمونه رایگان / تریال', 'رایگان,تریال,نمونه,test', 'پولی',
             "نمونه رایگان (در صورت فعال بودن) از طریق دکمه نمونه در پنل/ربات فعال می‌شود و مدت محدود دارد. هر کاربر فقط یک بار می‌تواند نمونه بگیرد. (اگر تریال فعال نیست، بگویید فعلاً تریال فعال نیست و برای خرید راهنمایی کنید.)"],
            ['وب‌سرویس اختصاصی و API', 'api,وب‌سرویس,وب‌سروس,panelpal,panelms,ادمین پنل', 'نماینده',
             "هر نماینده یک وب‌سرویس اختصاصی با API (سبک PanelMS/پنل‌پال) دارد: آدرس و کلید از بخش «وب‌سرویس و اپلیکیشن» در پنل اصلی قابل مشاهده است. با این API می‌توان کاربران را از طریق اسکریپت‌های پنل‌پال/PanelMS مدیریت کرد."],
            ['اپدیت برنامه کاربری', 'آپدیت,بروزرسانی,نسخه,اندرود,ویندوز', 'فنی',
             "برنامه کاربر Connectix از داخل برنامه آپدیت می‌شود (بخش درباره/آپدیت) و از آدرس رسمی پنل دانلود می‌شود. کاربران باید فقط نسخه‌های رسمی پنل را نصب کنند. اگر بعد از آپدیت برنامه باز نشد، یک‌بار حذف کامل و نصب مجدد را امتحان کنند و اگر ادامه داشت، گزارش کرش خودکار به پشتیبانی می‌افتد."],
            ['وضعیت سرورها و قطعی', 'قطعی,سرور,داون,آف,خاموش,استقرار', 'فنی',
             "در صورت قطعی سرور، معمولاً به‌صورت خودکار به تیم فنی اطلاع می‌رسد. اگر همه کاربران یک دسته هم‌زمان مشکل دارند، قطعی واقعی است؛ اطلاع‌رسانی در تلگرام/کانال انجام می‌شود. برای اعلام قطعی، تیکت بزنید و دسته/سرور موردنظر را بنویسید."],
            ['هویت کاربر و محرمانگی', 'رمز,پسورد,حذف اطلاعات,حریم,امنیت', 'سایر',
             "از شما هرگز رمز عبور، کد کارت بانکی کامل یا اطلاعات حساس دیگری در تیکت درخواست نمی‌شود. برای تغییر رمز از بخش «حساب کاربری» پنل استفاده کنید. در صورت مشکوک شدن به دسترسی غیرمجاز، فوراً رمز را تغییر دهید و 2FA را فعال کنید."],
        ];
        $st = $pdo->prepare("INSERT INTO ai_knowledge (title, keywords, category, content, is_active, created_at, updated_at)
                            VALUES (?, ?, ?, ?, 1, ?, ?)");
        foreach ($seeds as $s) {
            $st->execute([$s[0], $s[1], $s[2], $s[3], $now, $now]);
        }
    }

    /**
     * Score-based search over knowledge (driver agnostic).
     * score = title hits*5 + keyword hits*3 + content hits*1
     */
    public static function searchKnowledge(string $query, int $limit = 4): array {
        $pdo = Database::getConnection();
        $q = trim($query);
        if ($q === '') return [];
        // extract meaningful words (fa + en), keep top 12
        preg_match_all('/[\p{Arabic}A-Za-z0-9]{3,}/u', $q, $m);
        $words = array_values(array_unique($m[0]));
        if (empty($words)) return [];
        $words = array_slice($words, 0, 12);

        $rows = $pdo->query("SELECT * FROM ai_knowledge WHERE is_active = 1")->fetchAll();
        $scored = [];
        foreach ($rows as $r) {
            $score = 0;
            foreach ($words as $w) {
                if (stripos($r['title'], $w) !== false) $score += 5;
                if (stripos((string)$r['keywords'], $w) !== false) $score += 3;
                if (stripos((string)$r['content'], $w) !== false) $score += 1;
            }
            if ($score > 0) $scored[] = ['row' => $r, 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $s) {
            $c = $s['row'];
            $out[] = [
                'title' => $c['title'],
                'category' => $c['category'],
                'content' => mb_substr((string)$c['content'], 0, 1200),
                'score' => $s['score'],
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
تو «دستیار هوشمند Connectix» هستی: پشتیبان رسمی، صمیمی و فنی یک سرویس VPN/پروکسی. زبان جواب: فقط فارسی.
قوانین:
۱) فقط با استفاده از «پایگاه دانش» زیر جواب بده. اگر سؤال در پایگاه دانش نبود یا پیچیده/نامشخص بود، needs_human را true بگذار و answer را خالی بده.
۲) هیچ قیمت، تخفیف یا قول مالی را خودت نساز. برای قیمت‌ها بگو «از بخش تعرفه‌های پنل قابل مشاهده است».
۳) اگر موضوع پول‌برداری، استرداد، شکایت شدید، حقوقی یا امنیتی است: is_sensitive = true و needs_human = true.
۴) هرگز اطلاعات سرور، کلید، IP داخلی، رمز یا دادهٔ کاربران دیگر را نده.
۵) جواب کوتاه (حداکثر ۴-۶ جمله)، محترمانه، عمل‌گرا و بدون حاشیه. شماره‌گذاری مراحل اگر لازم است.
۶) نسخه فعلی برنامه اندروید: {$appVer} — اگر سؤال درباره نسخه بود فقط همین را بگو.
خروجی حتماً یک JSON معتبر و بدون هیچ متن اضافه با این ساختار باشد:
{"category":"فنی|پولی|نماینده|گزارش خطا|سایر","priority":"low|medium|high","is_sensitive":false,"needs_human":false,"confidence":0.0,"answer":"..."}
PROMPT;
    }

    public static function buildUserPrompt(array $ticket, array $messages, array $knowledge): string {
        $kb = '';
        foreach ($knowledge as $k) {
            $kb .= "\n### {$k['title']} (دسته: {$k['category']})\n" . $k['content'] . "\n";
        }
        if (trim($kb) === '') $kb = "\n(مورد مرتبط در پایگاه دانش پیدا نشد — اگر سؤال عمومی و ساده نیست، needs_human=true بگذار.)\n";

        $msgs = '';
        foreach ($messages as $mm) {
            $who = isset($mm['u_id']) ? ($mm['role'] === 'admin' ? 'مدیریت' : 'کاربر') : 'دستیار هوش مصنوعی';
            $msgs .= "[$who]: " . self::maskPii((string)$mm['message']) . "\n";
        }
        $q = self::maskPii($ticket['subject'] . ' — ' . ($messages[0]['message'] ?? ''));
        return "پایگاه دانش (فقط از این استفاده کن):\n{$kb}\n\n"
             . "تیکت جدید:\nموضوع: " . self::maskPii((string)$ticket['subject']) . "\n"
             . "دپارتمان: " . ($ticket['department'] ?? '') . "\n\n"
             . "گفت‌وگو:\n{$msgs}\n"
             . "پاسخ را فقط به‌صورت JSON بده.";
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

        // log
        try {
            $pdo->prepare("INSERT INTO ai_logs (ticket_id, stage, provider, model, status, category, confidence,
                            is_sensitive, needs_human, answer, latency_ms, error, accepted, created_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0, CURRENT_TIMESTAMP)")
                ->execute([
                    $ticketId, $canAuto ? 'auto' : 'draft', $llm['provider'], $llm['model'], $status,
                    $parsed['category'], $parsed['confidence'], $parsed['is_sensitive'] ? 1 : 0,
                    $parsed['needs_human'] ? 1 : 0, $parsed['answer'], $llm['latency_ms'], $llm['error'],
                ]);
        } catch (Throwable $e) {}

        if ($canAuto) {
            $footer = "\n\n— 🤖 این پاسخ توسط دستیار هوش مصنوعی Connectix ارائه شده است. اگر مشکل حل نشد، پیام جدید بفرستید تا پشتیبان انسانی بررسی کند.";
            $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, is_ai, created_at) VALUES (?, 0, ?, 1, CURRENT_TIMESTAMP)")
                ->execute([$ticketId, $parsed['answer'] . $footer]);
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

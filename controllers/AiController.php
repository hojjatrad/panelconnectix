<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/AiService.php';

/**
 * AI Assistant management — ADMIN ONLY (مدیرکل).
 * Routes: settings/ai, settings/ai/knowledge, settings/ai/resellers, settings/ai/logs
 */
class AiController {

    private static function adminOnly(): void {
        Auth::requireAdmin();
    }

    // ------------------------------------------------------------------
    // Main settings
    // ------------------------------------------------------------------

    public function index(): void {
        self::adminOnly();
        $pdo = Database::getConnection();
        $cfg = [
            'ai_enabled'        => AiService::cfg('ai_enabled'),
            'ai_auto_reply'     => AiService::cfg('ai_auto_reply'),
            'ai_groq_key'       => AiService::cfg('ai_groq_key'),
            'ai_groq_model'     => AiService::cfg('ai_groq_model'),
            'ai_gemini_key'     => AiService::cfg('ai_gemini_key'),
            'ai_gemini_model'   => AiService::cfg('ai_gemini_model'),
            'ai_openrouter_key' => AiService::cfg('ai_openrouter_key'),
            'ai_openrouter_model' => AiService::cfg('ai_openrouter_model'),
            'ai_temperature'    => AiService::cfg('ai_temperature'),
            'ai_min_confidence' => AiService::cfg('ai_min_confidence'),
            'ai_daily_quota'    => AiService::cfg('ai_daily_quota'),
            'ai_monthly_price'  => AiService::cfg('ai_monthly_price'),
        ];
        $stats = AiService::stats();
        $testResult = $_GET['test_result'] ?? '';
        require __DIR__ . '/../views/settings/ai.php';
    }

    public function save(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai');
        }
        $map = [
            'ai_enabled' => in_array($_POST['ai_enabled'] ?? '', ['0', '1'], true) ? $_POST['ai_enabled'] : '0',
            'ai_auto_reply' => in_array($_POST['ai_auto_reply'] ?? '', ['0', '1'], true) ? $_POST['ai_auto_reply'] : '0',
            'ai_groq_model' => trim($_POST['ai_groq_model'] ?? '') ?: AiService::cfg('ai_groq_model'),
            'ai_gemini_model' => trim($_POST['ai_gemini_model'] ?? '') ?: AiService::cfg('ai_gemini_model'),
            'ai_openrouter_model' => trim($_POST['ai_openrouter_model'] ?? '') ?: AiService::cfg('ai_openrouter_model'),
            'ai_temperature' => (string)min(2.0, max(0.0, (float)($_POST['ai_temperature'] ?? 0.4))),
            'ai_min_confidence' => (string)min(1.0, max(0.0, (float)($_POST['ai_min_confidence'] ?? 0.6))),
            'ai_daily_quota' => (string)max(1, (int)($_POST['ai_daily_quota'] ?? 150)),
            'ai_monthly_price' => (string)max(0, (int)($_POST['ai_monthly_price'] ?? 0)),
        ];
        // Keys: keep existing if field left blank (masked inputs)
        foreach (['ai_groq_key', 'ai_gemini_key', 'ai_openrouter_key'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if ($v !== '') $map[$k] = $v;
        }
        foreach ($map as $k => $v) {
            Setting::set($k, $v);
        }
        Helpers::flash('success', 'تنظیمات هوش مصنوعی ذخیره شد.');
        Helpers::redirect('settings/ai');
    }

    public function testProvider(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai');
        }
        $provider = (string)($_POST['provider'] ?? '');
        $res = AiService::testProvider($provider);
        if ($res['ok']) {
            Helpers::flash('success', "اتصال {$provider} موفق بود ✓ (مدل: " . ($res['model'] ?? '') . " — {$res['latency_ms']}ms)");
        } else {
            Helpers::flash('error', "اتصال {$provider} ناموفق: " . ($res['error'] ?? 'خطای ناشناخته'));
        }
        Helpers::redirect('settings/ai');
    }

    // ------------------------------------------------------------------
    // Knowledge base
    // ------------------------------------------------------------------

    public function knowledge(): void {
        self::adminOnly();
        AiService::ensureSeedKnowledge();
        $pdo = Database::getConnection();
        $docs = $pdo->query("SELECT * FROM ai_knowledge ORDER BY is_active DESC, id ASC")->fetchAll();
        $editing = null;
        if (!empty($_GET['edit'])) {
            $st = $pdo->prepare("SELECT * FROM ai_knowledge WHERE id = ?");
            $st->execute([(int)$_GET['edit']]);
            $editing = $st->fetch() ?: null;
        }
        $searchHits = null;
        if (!empty($_GET['q'])) {
            $searchHits = AiService::searchKnowledge((string)$_GET['q'], 5);
        }
        require __DIR__ . '/../views/settings/ai_knowledge.php';
    }

    public function knowledgeStore(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $title = trim($_POST['title'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $category = trim($_POST['category'] ?? 'فنی') ?: 'فنی';
        $content = trim($_POST['content'] ?? '');
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if ($title === '' || $content === '') {
            Helpers::flash('error', 'عنوان و متن سند الزامی است.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $now = date('Y-m-d H:i:s');
        $pdo = Database::getConnection();
        $pdo->prepare("INSERT INTO ai_knowledge (title, keywords, category, content, is_active, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?)")
            ->execute([$title, $keywords, $category, $content, $active, $now, $now]);
        Helpers::flash('success', 'سند به پایگاه دانش اضافه شد.');
        Helpers::redirect('settings/ai/knowledge');
    }

    public function knowledgeUpdate(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $category = trim($_POST['category'] ?? 'فنی') ?: 'فنی';
        $content = trim($_POST['content'] ?? '');
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if ($id <= 0 || $title === '' || $content === '') {
            Helpers::flash('error', 'ورودی نامعتبر.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE ai_knowledge SET title=?, keywords=?, category=?, content=?, is_active=?, updated_at=? WHERE id=?")
            ->execute([$title, $keywords, $category, $content, $active, date('Y-m-d H:i:s'), $id]);
        Helpers::flash('success', 'سند به‌روزرسانی شد.');
        Helpers::redirect('settings/ai/knowledge');
    }

    public function knowledgeToggle(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::getConnection();
            $pdo->prepare("UPDATE ai_knowledge SET is_active = 1 - is_active, updated_at=? WHERE id=?")
                ->execute([date('Y-m-d H:i:s'), $id]);
        }
        Helpers::redirect('settings/ai/knowledge');
    }

    public function knowledgeDelete(): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai/knowledge');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::getConnection();
            $pdo->prepare("DELETE FROM ai_knowledge WHERE id = ?")->execute([$id]);
            Helpers::flash('success', 'سند حذف شد.');
        }
        Helpers::redirect('settings/ai/knowledge');
    }

    // ------------------------------------------------------------------
    // Reseller charges (subscriptions with expiry)
    // ------------------------------------------------------------------

    public function resellers(): void {
        self::adminOnly();
        $pdo = Database::getConnection();
        $resellers = $pdo->query("SELECT id, username, full_name, brand_name, status, created_at FROM users
                                  WHERE role = 'reseller' ORDER BY id ASC")->fetchAll();
        $subs = [];
        foreach (AiService::listSubscriptions() as $s) {
            $subs[(int)$s['reseller_id']] = $s;
        }
        $now = date('Y-m-d H:i:s');
        require __DIR__ . '/../views/settings/ai_resellers.php';
    }

    private function doResellerAction(string $action): void {
        self::adminOnly();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/ai/resellers');
        }
        $resellerId = (int)($_POST['reseller_id'] ?? 0);
        $pdo = Database::getConnection();
        $st = $pdo->prepare("SELECT username, full_name, brand_name FROM users WHERE id = ? AND role = 'reseller'");
        $st->execute([$resellerId]);
        $res = $st->fetch();
        if (!$res) {
            Helpers::flash('error', 'نماینده یافت نشد.');
            Helpers::redirect('settings/ai/resellers');
        }
        $name = $res['brand_name'] ?: ($res['full_name'] ?: $res['username']);

        if ($action === 'activate') {
            $days = max(1, min(365, (int)($_POST['days'] ?? 30)));
            $price = max(0, (int)($_POST['price'] ?? (int)AiService::cfg('ai_monthly_price')));
            $note = trim($_POST['note'] ?? '') ?: ('فعال‌سازی ' . $days . ' روزه');
            $r = AiService::activate($resellerId, $days, $price, $note);
            Helpers::flash('success', "سرویس AI برای «{$name}» {$r['message']}");
            TelegramBot::sendCategorizedReport('users',
                "🤖 <b>سرویس هوش مصنوعی فعال شد</b>\n\n"
                . "نماینده: <b>{$name}</b> ({$res['username']})\n"
                . "دوره: {$days} روز — انقضا: " . substr((string)$r['row']['expires_at'], 0, 16) . "\n"
                . "دریافتی: " . Helpers::formatMoney($price) . " تومان");
        } elseif ($action === 'renew') {
            $days = max(1, min(365, (int)($_POST['days'] ?? 30)));
            $price = max(0, (int)($_POST['price'] ?? (int)AiService::cfg('ai_monthly_price')));
            $r = AiService::renew($resellerId, $days, $price);
            Helpers::flash('success', "سرویس AI «{$name}» تمدید شد تا " . substr((string)$r['row']['expires_at'], 0, 16));
            TelegramBot::sendCategorizedReport('users',
                "🤖 <b>تمدید سرویس هوش مصنوعی</b>\n\nنماینده: <b>{$name}</b>\n"
                . "تمدید: {$days} روز — انقضا: " . substr((string)$r['row']['expires_at'], 0, 16)
                . "\nدریافتی: " . Helpers::formatMoney($price) . " تومان");
        } elseif ($action === 'revoke') {
            AiService::revoke($resellerId);
            Helpers::flash('success', "سرویس AI «{$name}» غیرفعال شد.");
            TelegramBot::sendCategorizedReport('users',
                "🤖 <b>سرویس هوش مصنوعی «{$name}» غیرفعال شد</b> (کنسل توسط مدیریت)");
        }
        Helpers::redirect('settings/ai/resellers');
    }

    public function activate(): void { $this->doResellerAction('activate'); }
    public function renew(): void { $this->doResellerAction('renew'); }
    public function revoke(): void { $this->doResellerAction('revoke'); }

    // ------------------------------------------------------------------
    // Logs & dashboard
    // ------------------------------------------------------------------

    public function logs(): void {
        self::adminOnly();
        $stats = AiService::stats();
        $pdo = Database::getConnection();
        $recent = $pdo->query("SELECT l.*, t.subject, t.id AS tid
                               FROM ai_logs l LEFT JOIN tickets t ON t.id = l.ticket_id
                               ORDER BY l.id DESC LIMIT 50")->fetchAll();
        require __DIR__ . '/../views/settings/ai_logs.php';
    }
}

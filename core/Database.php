<?php
require_once __DIR__ . '/../config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                if (DB_DRIVER === 'sqlite') {
                    if (!extension_loaded('pdo_sqlite')) {
                        throw new Exception("اکستنشن pdo_sqlite روی هاست فعال نیست. لطفاً از طریق install.php دیتابیس MySQL سی‌پنل را متصل کنید.");
                    }
                    $dbDir = dirname(SQLITE_PATH);
                    if (!is_dir($dbDir)) {
                        mkdir($dbDir, 0777, true);
                    }
                    $isNew = !file_exists(SQLITE_PATH);
                    self::$instance = new PDO('sqlite:' . SQLITE_PATH);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    // Performance optimizations - Phase 1 & 2
                    self::$instance->exec('PRAGMA foreign_keys = ON;');
                    self::$instance->exec('PRAGMA journal_mode=WAL;'); // Write-Ahead Logging - allows concurrent read/write
                    self::$instance->exec('PRAGMA synchronous=NORMAL;'); // Faster, still safe with WAL
                    self::$instance->exec('PRAGMA cache_size=-64000;'); // 64MB cache
                    self::$instance->exec('PRAGMA temp_store=MEMORY;');
                    self::$instance->exec('PRAGMA mmap_size=268435456;'); // 256MB mmap
                    self::$instance->exec('PRAGMA busy_timeout=5000;'); // 5 sec busy timeout for concurrent access
                    
                    if ($isNew || filesize(SQLITE_PATH) === 0) {
                        self::initializeSqliteSchema(self::$instance);
                    }
                } else {
                    $hosts = [DB_HOST];
                    if (DB_HOST === 'localhost') {
                        $hosts[] = '127.0.0.1';
                    } elseif (DB_HOST === '127.0.0.1') {
                        $hosts[] = 'localhost';
                    }

                    $opts = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ];

                    $lastEx = null;
                    foreach ($hosts as $h) {
                        for ($attempt = 0; $attempt < 3; $attempt++) {
                            try {
                                $dsn = "mysql:host={$h};port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $opts);
                                break 2;
                            } catch (Throwable $pe) {
                                $lastEx = $pe;
                                usleep(150000); // 150ms backoff
                            }
                        }
                    }
                    if (self::$instance === null) {
                        throw $lastEx ?: new Exception("عدم امکان اتصال به MySQL");
                    }
                }
                self::ensureExtendedTablesExist(self::$instance);
            } catch (Throwable $e) {
                if (defined('CONNECTIX_REPAIR') || defined('CONNECTIX_INSTALL') || (defined('CONNECTIX_NO_DIE') && CONNECTIX_NO_DIE) || (defined('IS_AJAX_UPDATER') && IS_AJAX_UPDATER)) {
                    throw $e;
                }
                // v6.8.9: For AJAX updater, never die with HTML - throw instead
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                $isUpdaterRoute = str_contains($_SERVER['REQUEST_URI'] ?? '', 'updater/ajax-apply') || ($_GET['route'] ?? '') === 'updater/ajax-apply';
                if ($isAjax || $isUpdaterRoute) {
                    throw $e;
                }
                die("<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>خطای پایگاه داده</title><script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap'); *{font-family:'Vazirmatn',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4'><div class='bg-slate-900 border border-rose-900/50 p-8 rounded-2xl max-w-md w-full text-center shadow-2xl space-y-4'><div class='w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center text-3xl'>⚠️</div><h2 class='text-lg font-bold text-white'>خطا در ارتباط با دیتابیس</h2><p class='text-xs text-rose-300 leading-relaxed'>" . htmlspecialchars($e->getMessage()) . "</p><div class='pt-2'><a href='install.php?reinstall=1' class='inline-block w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md'>ورود به نصب‌کننده و تنظیم مجدد دیتابیس</a></div></div></body></html>");
            }
        }
        return self::$instance;
    }

    public static function safeAddColumn(PDO $pdo, string $table, string $column, string $definition): void {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $check = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch();
                if (!$check) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            } else {
                $cols = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(PDO::FETCH_ASSOC);
                $names = array_column($cols, 'name');
                if (!in_array($column, $names)) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            }
        } catch (Throwable $e) {}
    }

    public static function ensureExtendedTablesExist(PDO $pdo): void {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isSqlite = ($driver === 'sqlite');
            $autoInc = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

            // 1. Auxiliary Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TEXT NULL
            )");

            // v6.8.15: Bank auto-verification tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS bank_transactions (
                id $autoInc,
                amount BIGINT NOT NULL,
                raw_sms TEXT NULL,
                tracking_code VARCHAR(64) NULL,
                card_last4 VARCHAR(16) NULL,
                sender_number VARCHAR(32) NULL,
                received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                used_for_order_id INT NULL,
                verified TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            // Index for fast lookup
            try {
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_amount ON bank_transactions(amount)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_tracking ON bank_transactions(tracking_code)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_used ON bank_transactions(used_for_order_id)");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("CREATE INDEX idx_bank_tx_amount ON bank_transactions(amount)");
            } catch (Throwable $e) {}
            try {
                $pdo->exec("CREATE INDEX idx_bank_tx_tracking ON bank_transactions(tracking_code)");
            } catch (Throwable $e) {}


            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_orders (
                id $autoInc,
                order_code VARCHAR(32) UNIQUE,
                reseller_id INT NULL DEFAULT 1,
                bot_token VARCHAR(255) NULL,
                user_tg_id VARCHAR(64) NOT NULL,
                user_tg_name VARCHAR(128) NULL,
                user_tg_username VARCHAR(128) NULL,
                order_type VARCHAR(32) DEFAULT 'new',
                plan_id INT NULL,
                server_id INT NULL,
                target_username VARCHAR(64) NULL,
                amount BIGINT DEFAULT 0,
                coupon_code VARCHAR(64) NULL,
                discount_amount BIGINT DEFAULT 0,
                payment_method VARCHAR(32) DEFAULT 'card',
                payment_status VARCHAR(32) DEFAULT 'pending_receipt',
                receipt_photo_id VARCHAR(255) NULL,
                receipt_note TEXT NULL,
                client_id INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_sessions (
                tg_id VARCHAR(64) NOT NULL,
                reseller_id INT NOT NULL DEFAULT 1,
                step VARCHAR(64) NULL,
                data TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tg_id, reseller_id)
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_users (
                id $autoInc,
                reseller_id INT NULL DEFAULT 1,
                tg_id VARCHAR(64) UNIQUE NOT NULL,
                first_name VARCHAR(128) NULL,
                last_name VARCHAR(128) NULL,
                username VARCHAR(128) NULL,
                phone VARCHAR(32) NULL,
                referred_by VARCHAR(64) NULL,
                referral_balance BIGINT DEFAULT 0,
                referral_count INT DEFAULT 0,
                is_blocked TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_active_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS trial_logs (
                id $autoInc,
                user_id INT NULL,
                reseller_id INT NULL DEFAULT 1,
                telegram_id VARCHAR(64) NULL,
                ip_address VARCHAR(64) NULL,
                client_id INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS lucky_wheel_logs (
                id $autoInc,
                user_tg_id VARCHAR(64) NOT NULL,
                reward_type VARCHAR(32) NOT NULL,
                reward_value INT NOT NULL,
                reward_text VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_logs (
                id $autoInc,
                tg_id VARCHAR(64) NOT NULL,
                amount BIGINT NOT NULL,
                balance_after BIGINT NOT NULL,
                type VARCHAR(32) NOT NULL,
                description VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS crypto_payments (
                id $autoInc,
                user_id INT NULL,
                reseller_id INT NULL DEFAULT 1,
                order_id INT NULL,
                currency VARCHAR(16) DEFAULT 'USDT',
                network VARCHAR(16) DEFAULT 'TRC20',
                expected_amount_usdt DECIMAL(10,2) DEFAULT 0.00,
                toman_amount BIGINT DEFAULT 0,
                wallet_address VARCHAR(128) NOT NULL,
                tx_hash VARCHAR(128) NULL,
                status VARCHAR(32) DEFAULT 'pending',
                admin_notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS app_guides (
                id $autoInc,
                platform VARCHAR(32) NOT NULL,
                app_name VARCHAR(128) NOT NULL,
                download_url VARCHAR(255) NOT NULL,
                guide_url VARCHAR(255) NULL,
                description TEXT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
                id $autoInc,
                code VARCHAR(64) UNIQUE NOT NULL,
                discount_percent INT DEFAULT 10,
                max_uses INT DEFAULT 100,
                used_count INT DEFAULT 0,
                expire_at DATETIME NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_plans (
                id $autoInc,
                reseller_id INT NOT NULL,
                plan_id INT NOT NULL DEFAULT 0,
                custom_title VARCHAR(128) NULL,
                custom_category VARCHAR(64) DEFAULT 'پیش‌فرض',
                retail_price BIGINT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                is_custom TINYINT(1) DEFAULT 0,
                traffic_gb FLOAT DEFAULT 0,
                duration_days INT DEFAULT 30,
                ip_limit INT DEFAULT 4,
                server_id INT NULL,
                base_cost BIGINT DEFAULT 0,
                description TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_applications (
                id $autoInc,
                user_tg_id VARCHAR(64) NOT NULL,
                user_tg_name VARCHAR(128) NULL,
                user_tg_username VARCHAR(128) NULL,
                brand_name VARCHAR(128) NOT NULL,
                contact_info VARCHAR(128) NOT NULL,
                preferred_username VARCHAR(64) NOT NULL,
                estimated_sales VARCHAR(64) NULL,
                experience_notes TEXT NULL,
                status VARCHAR(32) DEFAULT 'pending',
                approved_user_id INT NULL,
                admin_notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
                id $autoInc,
                user_id INT NOT NULL,
                subject VARCHAR(255) NOT NULL,
                department VARCHAR(64) DEFAULT 'support',
                priority VARCHAR(32) DEFAULT 'medium',
                status VARCHAR(32) DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
                id $autoInc,
                ticket_id INT NOT NULL,
                sender_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
                id $autoInc,
                name VARCHAR(128) NOT NULL,
                slug VARCHAR(64) UNIQUE NOT NULL,
                type VARCHAR(32) DEFAULT 'both',
                icon VARCHAR(64) DEFAULT 'fa-server',
                badge_color VARCHAR(32) DEFAULT 'purple',
                description TEXT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
                id $autoInc,
                user_id INT NULL,
                action VARCHAR(64) NOT NULL,
                entity_type VARCHAR(64) NULL,
                entity_id VARCHAR(64) NULL,
                description TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Time-series snapshots for dashboard trend charts (72h sparklines)
            $pdo->exec("CREATE TABLE IF NOT EXISTS metrics (
                id $autoInc,
                ts DATETIME NOT NULL,
                active_clients INT DEFAULT 0,
                total_clients INT DEFAULT 0,
                online_nodes INT DEFAULT 0,
                total_nodes INT DEFAULT 0,
                traffic_used_total BIGINT DEFAULT 0,
                traffic_limit_total BIGINT DEFAULT 0
            )");

            // Category aliases for smart duplicate detection
            $pdo->exec("CREATE TABLE IF NOT EXISTS category_aliases (
                id $autoInc,
                category_id INT NOT NULL,
                alias VARCHAR(128) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(alias)
            )");

            // Server sync logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS server_sync_logs (
                id $autoInc,
                server_id INT NOT NULL,
                action VARCHAR(64) NOT NULL,
                details TEXT NULL,
                plans_imported INT DEFAULT 0,
                plans_skipped INT DEFAULT 0,
                categories_created INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Domain replacements setting table (optional, but we use system_settings)
            // Ensure default settings for domain independence exist
            try {
                $existingSettings = $pdo->query("SELECT setting_key FROM system_settings WHERE setting_key IN ('panel_domain','sublink_custom_domain','old_domains','domain_replacements','github_webhook_secret')")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('old_domains', $existingSettings)) {
                    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('old_domains', ?)")->execute(['montago-shop.ir,gga1.montago-shop.ir,node.connectix.space,sub.speedur.org']);
                }
            } catch (Throwable $e) {}

            // 2. Safe Column Additions for Core Tables
            $userCols = [
                'telegram_bot_token' => 'VARCHAR(255) NULL',
                'telegram_bot_username' => 'VARCHAR(128) NULL',
                'telegram_admin_chat_id' => 'VARCHAR(64) NULL',
                'brand_name' => 'VARCHAR(128) NULL',
                'logo_url' => 'VARCHAR(255) NULL',
                'theme_color' => "VARCHAR(32) DEFAULT 'violet'",
                'card_number' => 'VARCHAR(32) NULL',
                'card_holder' => 'VARCHAR(128) NULL',
                'card_shaba' => 'VARCHAR(34) NULL',
                'zarinpal_merchant' => 'VARCHAR(64) NULL',
                'support_username' => 'VARCHAR(128) NULL',
                'welcome_message' => 'TEXT NULL',
                'custom_domain' => 'VARCHAR(128) NULL',
                'telegram_channel' => 'VARCHAR(128) NULL',
                'credit_limit' => 'BIGINT DEFAULT 0',
                'auto_tier_enabled' => 'TINYINT(1) DEFAULT 1',
                'tier_level' => "VARCHAR(32) DEFAULT 'bronze'",
                'parent_reseller_id' => 'INT NULL DEFAULT NULL',
                'commission_percent' => 'INT DEFAULT 10',
                'crypto_wallet_address' => 'VARCHAR(128) NULL',
                'crypto_network' => "VARCHAR(32) DEFAULT 'TRC20'",
                'total_spent' => 'BIGINT DEFAULT 0',
                'telegram_chat_id' => 'VARCHAR(64) NULL',
                'magic_login_token' => 'VARCHAR(64) NULL',
                'magic_login_expires' => 'DATETIME NULL',
                'panel_password_display' => 'VARCHAR(128) NULL',
                'allow_custom_plans' => 'TINYINT(1) DEFAULT 1',
                'allow_price_edit' => 'TINYINT(1) DEFAULT 1',
                'allowed_servers' => 'TEXT NULL',
                'max_custom_plans' => 'INT DEFAULT 10',
                'custom_plan_approval_required' => 'TINYINT(1) DEFAULT 0'
            ];
            foreach ($userCols as $c => $d) {
                self::safeAddColumn($pdo, 'users', $c, $d);
            }

            $orderCols = [
                'reseller_id' => 'INT NULL DEFAULT 1',
                'bot_token' => 'VARCHAR(255) NULL',
                'coupon_code' => 'VARCHAR(64) NULL',
                'discount_amount' => 'BIGINT DEFAULT 0',
                'unique_amount' => 'BIGINT NULL',
                'verification_method' => 'VARCHAR(32) NULL',
                'bank_tracking_code' => 'VARCHAR(64) NULL',
                'verified_at' => 'DATETIME NULL',
                'verification_data' => 'TEXT NULL'
            ];
            foreach ($orderCols as $c => $d) {
                self::safeAddColumn($pdo, 'bot_orders', $c, $d);
            }

            $botUserCols = [
                'referred_by' => 'VARCHAR(64) NULL',
                'referral_balance' => 'BIGINT DEFAULT 0',
                'referral_count' => 'INT DEFAULT 0',
                'wallet_balance' => 'BIGINT DEFAULT 0'
            ];
            foreach ($botUserCols as $c => $d) {
                self::safeAddColumn($pdo, 'bot_users', $c, $d);
            }

            $clientCols = [
                'customer_name' => 'VARCHAR(128) NULL',
                'updated_at' => 'DATETIME NULL',
                'alert_80_sent' => 'TINYINT(1) DEFAULT 0',
                'alert_95_sent' => 'TINYINT(1) DEFAULT 0',
                'alert_exp_sent' => 'TINYINT(1) DEFAULT 0',
                'alert_final_sent' => 'TINYINT(1) DEFAULT 0',
                'telegram_chat_id' => 'VARCHAR(64) NULL',
                'ip_limit' => 'INT DEFAULT 0',
                'max_devices' => 'INT DEFAULT 0',
                'start_on_first_use' => 'TINYINT(1) DEFAULT 0',
                'first_connected_at' => 'DATETIME NULL',
                'duration_days' => 'INT DEFAULT 30',
                'node_sublink' => 'TEXT NULL',
                'node_sync' => 'TINYINT(1) DEFAULT 0',
                'custom_note' => 'TEXT NULL',
                'original_password' => 'VARCHAR(64) NULL',
                'api_group_name' => 'VARCHAR(64) NULL',
                'api_plan_name' => 'VARCHAR(128) NULL',
                'api_created_at' => 'VARCHAR(32) NULL'
            ];
            foreach ($clientCols as $c => $d) {
                self::safeAddColumn($pdo, 'clients', $c, $d);
            }

            $serverCols = [
                'api_token' => 'TEXT NULL',
                'health_status' => "VARCHAR(32) DEFAULT 'online'",
                'latency_ms' => 'INT DEFAULT 0',
                'last_checked_at' => 'DATETIME NULL',
                'error_message' => 'TEXT NULL',
                'category_id' => 'INT NULL DEFAULT NULL',
                'config_template' => 'TEXT NULL',
                'selected_inbounds' => 'TEXT NULL',
                'is_vip' => 'TINYINT(1) DEFAULT 0',
                'auto_import_plans' => 'TINYINT(1) DEFAULT 0',
                'last_sync_at' => 'DATETIME NULL',
                'sync_enabled' => 'TINYINT(1) DEFAULT 1',
                'price_multiplier' => 'DECIMAL(4,2) DEFAULT 1.00',
                'region' => 'VARCHAR(32) NULL',
                'seller_code' => 'VARCHAR(16) NULL',
                'custom_prefix' => 'VARCHAR(8) NULL',
                'priority' => 'INT DEFAULT 0',
                'auto_detect_category' => 'TINYINT(1) DEFAULT 1',
            ];
            foreach ($serverCols as $c => $d) {
                self::safeAddColumn($pdo, 'server_nodes', $c, $d);
            }

            $planCols = [
                'show_in_bot' => 'TINYINT(1) DEFAULT 1',
                'category' => "VARCHAR(64) DEFAULT '۱ ماهه'",
                'ip_limit' => 'INT DEFAULT 0',
                'max_devices' => 'INT DEFAULT 0',
                'start_on_first_use' => 'TINYINT(1) DEFAULT 0',
                'server_id' => 'INT NULL DEFAULT NULL',
                'category_id' => 'INT NULL DEFAULT NULL',
                'vip_plan_id' => 'VARCHAR(128) NULL',
                'vip_group_id' => 'VARCHAR(128) NULL',
                'vip_group_name' => 'VARCHAR(64) NULL',
                'vip_plan_title' => 'VARCHAR(255) NULL'
            ];
            foreach ($planCols as $c => $d) {
                self::safeAddColumn($pdo, 'plans', $c, $d);
            }

            $categoryCols = [
                'parent_id' => 'INT NULL DEFAULT NULL',
                'level' => 'INT DEFAULT 0',
                'bot_label' => 'VARCHAR(128) NULL',
                'bot_icon' => 'VARCHAR(32) NULL',
            ];
            foreach ($categoryCols as $c => $d) {
                self::safeAddColumn($pdo, 'categories', $c, $d);
            }

            $isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
            if ($isMysql) {
                try {
                    $pdo->exec("ALTER TABLE `plans` MODIFY COLUMN `traffic_gb` DECIMAL(10,3) NOT NULL DEFAULT 1.000");
                    $pdo->exec("ALTER TABLE `reserved_plans` MODIFY COLUMN `traffic_gb` DECIMAL(10,3) NOT NULL DEFAULT 1.000");
                } catch (Throwable $e) {}
            }

            // Sync clients ip_limit to match plan if plan is unlimited (ip_limit=0)
            try {
                if ($isMysql) {
                    $pdo->exec("ALTER TABLE `plans` ALTER COLUMN `ip_limit` SET DEFAULT 0");
                    $pdo->exec("ALTER TABLE `plans` ALTER COLUMN `max_devices` SET DEFAULT 0");
                    $pdo->exec("ALTER TABLE `clients` ALTER COLUMN `ip_limit` SET DEFAULT 0");
                    $pdo->exec("ALTER TABLE `clients` ALTER COLUMN `max_devices` SET DEFAULT 0");
                    $pdo->exec("UPDATE clients c INNER JOIN plans p ON c.plan_id = p.id SET c.ip_limit = p.ip_limit, c.max_devices = p.max_devices WHERE p.ip_limit = 0 AND c.ip_limit = 2");
                } else {
                    $pdo->exec("UPDATE clients SET ip_limit = 0, max_devices = 0 WHERE ip_limit = 2 AND plan_id IN (SELECT id FROM plans WHERE ip_limit = 0)");
                }
            } catch (Throwable $e) {}

            // Auto-disable mock servers and detach them from plans if at least one real server is active
            try {
                $hasRealServer = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE driver != 'mock' AND is_active = 1")->fetchColumn();
                if ($hasRealServer > 0) {
                    $pdo->exec("UPDATE server_nodes SET is_active = 0 WHERE driver = 'mock'");
                    $pdo->exec("UPDATE plans SET server_id = NULL WHERE server_id IN (SELECT id FROM server_nodes WHERE driver = 'mock')");
                }
            } catch (Throwable $e) {}

            // v6.8.19 FIX: Prevent deleted plans from resurrecting - set NULL auto_import_plans to 0 (disabled)
            try {
                $pdo->exec("UPDATE server_nodes SET auto_import_plans = 0 WHERE auto_import_plans IS NULL");
            } catch (Throwable $e) {}

            // v6.8.20 FIX: If plans table is empty (user purged), disable auto_import for ALL servers + set global kill switch
            try {
                $plansCount = (int)$pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
                if ($plansCount === 0) {
                    $pdo->exec("UPDATE server_nodes SET auto_import_plans = 0");
                    try {
                        require_once __DIR__ . '/Setting.php';
                        // Only set if not already explicitly enabled after purge - if empty, disable
                        $current = Setting::get('auto_import_disabled', '');
                        if ($current !== '0') { // if not explicitly re-enabled, disable
                            Setting::set('auto_import_disabled', '1');
                        }
                    } catch (Throwable $e2) {}
                }
            } catch (Throwable $e) {}

            // Seed Default Categories if empty
            try {
                $catCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                if ($catCount === 0) {
                    $defaultCats = [
                        ['پیش‌فرض (استاندارد)', 'default', 'both', 'fa-globe', 'purple', 'خوشه سرورها و پلن‌های استاندارد بین‌الملل', 1],
                        ['سرورهای VIP و پرسرعت', 'vip', 'servers', 'fa-crown', 'amber', 'سرورهای بهینه‌شده با پهنای باند اختصاصی و پینگ پایین', 2],
                        ['سرورهای اقتصادی (Economic)', 'economic', 'both', 'fa-tag', 'blue', 'پلن‌های باصرفه و اقتصادی جهت وب‌گردی روزمره', 3],
                        ['ایران اکسس (ملی و نامحدود)', 'iran_access', 'both', 'fa-shield-halved', 'emerald', 'سرورهای با دسترسی به سایت‌های داخلی و ترافیک نامحدود', 4],
                        ['مخصوص بازی و گیمینگ (Gaming)', 'gaming', 'servers', 'fa-gamepad', 'cyan', 'سرورهای تونل‌شده بدون نوسان و کمترین زمان پاسخگویی', 5],
                        ['پلن‌های ۱ ماهه', 'period_1m', 'plans', 'fa-calendar-days', 'purple', 'اشتراک‌های استاندارد ۳۰ روزه', 10],
                        ['پلن‌های ۲ ماهه', 'period_2m', 'plans', 'fa-calendar-week', 'blue', 'اشتراک‌های میان‌مدت ۶۰ روزه', 11],
                        ['پلن‌های ۳ ماهه', 'period_3m', 'plans', 'fa-calendar-check', 'amber', 'اشتراک‌های فصلی ۹۰ روزه با تخفیف', 12],
                        ['پلن‌های ۶ ماهه و سالانه', 'period_long', 'plans', 'fa-calendar-plus', 'emerald', 'اشتراک‌های بلندمدت با بیشترین صرفه اقتصادی', 13],
                        ['پلن‌های نامحدود / حجمی', 'unlimited_custom', 'plans', 'fa-infinity', 'cyan', 'پلن‌های با ترافیک بالا یا نامحدود زمانی', 14],
                    ];
                    $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($defaultCats as $dc) {
                        $stmtCat->execute($dc);
                    }
                } else {
                    // Ensure plan categories also exist if only servers were seeded
                    $planCatCount = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE type IN ('plans', 'plan')")->fetchColumn();
                    if ($planCatCount === 0) {
                        $defaultPlanCats = [
                            ['پلن‌های ۱ ماهه', 'period_1m', 'plans', 'fa-calendar-days', 'purple', 'اشتراک‌های استاندارد ۳۰ روزه', 10],
                            ['پلن‌های ۲ ماهه', 'period_2m', 'plans', 'fa-calendar-week', 'blue', 'اشتراک‌های میان‌مدت ۶۰ روزه', 11],
                            ['پلن‌های ۳ ماهه', 'period_3m', 'plans', 'fa-calendar-check', 'amber', 'اشتراک‌های فصلی ۹۰ روزه با تخفیف', 12],
                            ['پلن‌های ۶ ماهه و سالانه', 'period_long', 'plans', 'fa-calendar-plus', 'emerald', 'اشتراک‌های بلندمدت با بیشترین صرفه اقتصادی', 13],
                            ['پلن‌های نامحدود / حجمی', 'unlimited_custom', 'plans', 'fa-infinity', 'cyan', 'پلن‌های با ترافیک بالا یا نامحدود زمانی', 14],
                        ];
                        $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        foreach ($defaultPlanCats as $dpc) {
                            $stmtCat->execute($dpc);
                        }
                    }

                    // PROFESSIONAL MERGE: Fix duplicate month categories and ensure canonical categories
                    try {
                        // Ensure daily and canonical categories exist
                        $dailyExists = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE slug IN ('period_1d', 'daily', '1d')")->fetchColumn();
                        if ($dailyExists === 0) {
                            $pdo->exec("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order) VALUES ('پلن‌های ۱ روزه', 'period_1d', 'plans', 'fa-calendar-day', 'rose', 'پلن‌های تست یک روزه', 5)");
                            $pdo->exec("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order) VALUES ('پلن‌های ۳ روزه', 'period_3d', 'plans', 'fa-calendar-days', 'amber', 'پلن‌های کوتاه‌مدت ۳ روزه', 6)");
                        }

                        // Define canonical mapping: canonical name => variants (including Persian/English digits, with/without space, different spellings)
                        $canonicalMap = [
                            '۱ روزه' => ['1 روزه', '۱روزه', '1روزه', 'روزانه', 'یک روزه', 'یکروزه', '1D', '1d', 'daily', 'روزه 1', '1 روز', 'یک روز'],
                            '۳ روزه' => ['3 روزه', '۳روزه', '3روزه', 'سه روزه', 'سه‌روزه', '3D', '3d'],
                            'هفتگی' => ['هفتگی', 'هفته‌ای', 'هفته ای', '7 روزه', '۷ روزه', '1 هفته', 'یک هفته', 'یک هفته‌ای', '7D', 'weekly'],
                            '۱ ماهه' => ['1 ماهه', '۱ماهه', '1ماهه', 'یک ماهه', 'یکماهه', 'یک ماه', '30 روزه', '۳۰ روزه', '1M', '1m', 'یکماه', '1 ماه', 'یک ماهه', '30روزه', 'یکماهه'],
                            '۲ ماهه' => ['2 ماهه', '۲ماهه', '2ماهه', 'دو ماهه', 'دوماهه', '60 روزه', '۶۰ روزه', '2M', '2m', '2 ماه'],
                            '۳ ماهه' => ['3 ماهه', '۳ماهه', '3ماهه', 'سه ماهه', 'سه‌ماهه', '90 روزه', '۹۰ روزه', '3M', '3m', '3 ماه'],
                            '۶ ماهه' => ['6 ماهه', '۶ماهه', '6ماهه', 'شش ماهه', 'شش‌ماهه', '180 روزه', '۱۸۰ روزه', '6M', '6m'],
                            '۱۲ ماهه' => ['12 ماهه', '۱۲ماهه', '12ماهه', 'یک ساله', 'یکساله', '1 ساله', 'یکسال', 'سالانه', '365 روزه', '۳۶۵ روزه', '12M', 'yearly', 'یک ساله'],
                        ];

                        // Helper to normalize Persian digits to English for comparison
                        $normalizeDigits = function($str) {
                            $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
                            $english = ['0','1','2','3','4','5','6','7','8','9'];
                            return str_replace($persian, $english, $str);
                        };

                        // Build lookup: variant normalized => canonical
                        $variantToCanonical = [];
                        foreach ($canonicalMap as $canonical => $variants) {
                            $variantToCanonical[mb_strtolower(trim($canonical))] = $canonical;
                            foreach ($variants as $v) {
                                $variantToCanonical[mb_strtolower(trim($v))] = $canonical;
                                $variantToCanonical[mb_strtolower(trim($normalizeDigits($v)))] = $canonical;
                            }
                        }

                        // 1. Fix plans table: unify category string based on duration_days AND existing category name
                        // First, fix by duration_days (most reliable)
                        $durationToCanonical = [
                            1 => '۱ روزه',
                            2 => '۳ روزه',
                            3 => '۳ روزه',
                            7 => 'هفتگی',
                            30 => '۱ ماهه',
                            60 => '۲ ماهه',
                            90 => '۳ ماهه',
                            180 => '۶ ماهه',
                            365 => '۱۲ ماهه',
                            360 => '۱۲ ماهه',
                        ];
                        foreach ($durationToCanonical as $days => $canon) {
                            $stmt = $pdo->prepare("UPDATE plans SET category = ? WHERE duration_days = ? AND category != ?");
                            $stmt->execute([$canon, $days, $canon]);
                        }
                        // Also fix where duration is 0 or null but category is variant
                        $allPlans = $pdo->query("SELECT id, category, duration_days FROM plans")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($allPlans as $pl) {
                            $cat = trim($pl['category'] ?? '');
                            $catLower = mb_strtolower($cat);
                            $catNormDigits = mb_strtolower($normalizeDigits($cat));
                            $canonical = $variantToCanonical[$catLower] ?? $variantToCanonical[$catNormDigits] ?? null;
                            if ($canonical && $canonical !== $cat) {
                                $pdo->prepare("UPDATE plans SET category = ? WHERE id = ?")->execute([$canonical, $pl['id']]);
                            }
                        }

                        // 2. Merge duplicate categories in categories table
                        $allCats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
                        $catGroups = []; // canonical => list of cat rows
                        foreach ($allCats as $cat) {
                            $name = trim($cat['name'] ?? '');
                            $nameLower = mb_strtolower($name);
                            $nameNorm = mb_strtolower($normalizeDigits($name));
                            $canon = $variantToCanonical[$nameLower] ?? $variantToCanonical[$nameNorm] ?? null;
                            // Also check slug
                            if (!$canon) {
                                $slug = mb_strtolower(trim($cat['slug'] ?? ''));
                                // Map slug variants
                                if (in_array($slug, ['period_1m', '1m', '1_m', 'one_month'])) $canon = '۱ ماهه';
                                elseif (in_array($slug, ['period_2m', '2m'])) $canon = '۲ ماهه';
                                elseif (in_array($slug, ['period_3m', '3m'])) $canon = '۳ ماهه';
                                elseif (in_array($slug, ['period_6m', '6m', 'period_long'])) $canon = '۶ ماهه';
                                elseif (in_array($slug, ['period_12m', '12m', 'yearly', '1y'])) $canon = '۱۲ ماهه';
                                elseif (in_array($slug, ['period_1d', '1d', 'daily'])) $canon = '۱ روزه';
                                elseif (in_array($slug, ['weekly', '7d', 'period_7d'])) $canon = 'هفتگی';
                            }
                            if ($canon) {
                                $catGroups[$canon][] = $cat;
                            }
                        }

                        // For each canonical group with duplicates, merge into primary
                        foreach ($catGroups as $canonical => $group) {
                            if (count($group) <= 1) continue;
                            // Choose primary: prefer slug period_Xm, or lowest id with most plans
                            usort($group, function($a,$b){
                                $aScore = 0;
                                $bScore = 0;
                                if (strpos($a['slug'] ?? '', 'period_') === 0) $aScore += 10;
                                if (strpos($b['slug'] ?? '', 'period_') === 0) $bScore += 10;
                                // Prefer Persian canonical name exactly
                                if (($a['name'] ?? '') === $a['name']) $aScore += 1;
                                return $bScore <=> $aScore ?: ($a['id'] <=> $b['id']);
                            });
                            $primary = $group[0];
                            $primaryId = $primary['id'];
                            // Ensure primary has canonical name
                            if ($primary['name'] !== $canonical) {
                                $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$canonical, $primaryId]);
                            }
                            // Merge others into primary
                            for ($i=1; $i<count($group); $i++) {
                                $dup = $group[$i];
                                $dupId = $dup['id'];
                                if ($dupId == $primaryId) continue;
                                // Update plans category_id
                                $pdo->prepare("UPDATE plans SET category_id = ? WHERE category_id = ?")->execute([$primaryId, $dupId]);
                                // Update server_nodes category_id
                                $pdo->prepare("UPDATE server_nodes SET category_id = ? WHERE category_id = ?")->execute([$primaryId, $dupId]);
                                // Update child categories parent_id
                                $pdo->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?")->execute([$primaryId, $dupId]);
                                // Delete duplicate
                                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$dupId]);
                            }
                        }

                        // 3. Ensure all plans with same duration have same category_id (canonical)
                        foreach ($durationToCanonical as $days => $canon) {
                            $catRow = $pdo->query("SELECT id FROM categories WHERE name = " . $pdo->quote($canon) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            if ($catRow) {
                                $catId = $catRow['id'];
                                $pdo->prepare("UPDATE plans SET category_id = ? WHERE duration_days = ? AND (category_id IS NULL OR category_id != ?)")->execute([$catId, $days, $catId]);
                            }
                        }

                    } catch (Throwable $e) {
                        error_log("Category merge error: " . $e->getMessage());
                    }
                }
            } catch (Throwable $e) {}

            // 3. Seed Default App Guides if empty or ensure proprietary Connectix apps are present
            try {
                $appCount = (int)$pdo->query("SELECT COUNT(*) FROM app_guides")->fetchColumn();
                if ($appCount === 0) {
                    $defaultApps = [
                        ['android', '🚀 Connectix Android (اپلیکیشن اختصاصی - پیشنهادی)', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-Universal.apk', '', 'نرم‌افزار رسمی و اختصاصی با ورود آسان تنها با نام کاربری و پسورد، بدون نیاز به کانفیگ دستی و تست خودکار پینگ', 0],
                        ['android', 'v2rayNG (پیشنهادی اندروید)', 'https://github.com/2dust/v2rayNG/releases', 'https://t.me/connectix/79', 'پایدارترین کلاینت اندروید با قابلیت اتصال خودکار و پشتیبانی از همه پروتکل‌ها', 1],
                        ['android', 'NapsternetV (کلاینت دوم اندروید)', 'https://play.google.com/store/apps/details?id=com.napsternetlabs.napsternetv', '', 'نرم‌افزار کمکی برای اینترنت‌های با اختلال بالا', 2],
                        ['windows', '🚀 Connectix Windows (نرم‌افزار اختصاصی ویندوز - پیشنهادی)', 'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Windows-x64.zip', '', 'کلاینت اختصاصی ویندوز با تونل کل ترافیک سیستم (VPN Mode) و اتصال ۱ کلیک فوق‌العاده سریع', 0],
                        ['windows', 'NekoRay (پیشنهادی ویندوز)', 'https://github.com/MatsuriDayo/nekoray/releases', '', 'دارای حالت System Proxy و VPN Mode برای کل ترافیک ویندوز', 1],
                        ['windows', 'v2rayN (کلاینت کلاسیک ویندوز)', 'https://github.com/2dust/v2rayN/releases', '', 'پشتیبانی از Reality و Xray Core', 2],
                        ['ios', 'Streisand (پیشنهادی آیفون و آیپد)', 'https://apps.apple.com/app/streisand/id6450534064', '', 'رایگان، بسیار سریع و سازگار با اینترنت‌های همراه اول و ایرانسل', 1],
                        ['ios', 'V2Box (کلاینت جایگزین iOS)', 'https://apps.apple.com/app/v2box-v2ray-client/id6446814042', '', 'پشتیبانی کامل از ساب‌لینک هوشمند و پینگ تست آنی', 2],
                        ['macos', 'FoXray (مک‌بوک)', 'https://apps.apple.com/app/foxray/id6448898396', '', 'کلاینت رسمی و بسیار سبک سیستم‌عامل macOS', 1]
                    ];
                    $stmtApp = $pdo->prepare("INSERT INTO app_guides (platform, app_name, download_url, guide_url, description, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($defaultApps as $da) {
                        $stmtApp->execute($da);
                    }
                } else {
                    // Ensure proprietary Connectix apps exist in app_guides
                    $cxCount = (int)$pdo->query("SELECT COUNT(*) FROM app_guides WHERE app_name LIKE '%Connectix%'")->fetchColumn();
                    if ($cxCount === 0) {
                        $stmtIns = $pdo->prepare("INSERT INTO app_guides (platform, app_name, download_url, guide_url, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                        $stmtIns->execute([
                            'android',
                            '🚀 Connectix Android (اپلیکیشن اختصاصی - پیشنهادی)',
                            'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Android-Universal.apk',
                            '',
                            'نرم‌افزار رسمی و اختصاصی با ورود آسان تنها با نام کاربری و پسورد، بدون نیاز به کانفیگ دستی و تست خودکار پینگ',
                            0
                        ]);
                        $stmtIns->execute([
                            'windows',
                            '🚀 Connectix Windows (نرم‌افزار اختصاصی ویندوز - پیشنهادی)',
                            'https://github.com/hojjatrad/panelconnectix/releases/download/v3.5.8/Connectix-Windows-x64.zip',
                            '',
                            'کلاینت اختصاصی ویندوز با تونل کل ترافیک سیستم (VPN Mode) و اتصال ۱ کلیک فوق‌العاده سریع',
                            0
                        ]);
                    }
                }
            } catch (Throwable $e) {}

            // 4. Seed Default Coupons if empty
            try {
                $couponCount = (int)$pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
                if ($couponCount === 0) {
                    $pdo->exec("INSERT INTO coupons (code, discount_percent, max_uses, used_count, is_active) VALUES ('WELCOME10', 10, 100, 0, 1)");
                    $pdo->exec("INSERT INTO coupons (code, discount_percent, max_uses, used_count, is_active) VALUES ('VIP20', 20, 50, 0, 1)");
                }
            } catch (Throwable $e) {}

            // 5. Ensure Referrals Table & Columns
            $isMysql = ($driver === 'mysql');
            $refTableSql = $isMysql
                ? "CREATE TABLE IF NOT EXISTS `referrals` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `referrer_id` INT NOT NULL,
                    `referred_id` INT NOT NULL,
                    `order_id` INT NULL,
                    `commission_amount` BIGINT DEFAULT 0,
                    `status` VARCHAR(32) DEFAULT 'completed',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
                : "CREATE TABLE IF NOT EXISTS referrals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    referrer_id INTEGER NOT NULL,
                    referred_id INTEGER NOT NULL,
                    order_id INTEGER NULL,
                    commission_amount INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'completed',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );";
            $pdo->exec($refTableSql);

            // Columns for Users
            $userCols = [
                'two_factor_secret' => 'VARCHAR(64) NULL',
                'two_factor_enabled' => 'TINYINT(1) DEFAULT 0',
                'referral_code' => 'VARCHAR(32) NULL',
                'referred_by' => 'INT NULL DEFAULT NULL'
            ];
            foreach ($userCols as $c => $d) {
                self::safeAddColumn($pdo, 'users', $c, $d);
            }

            // Columns for Transactions
            $txCols = [
                'receipt_image' => 'VARCHAR(255) NULL',
                'ocr_data' => 'TEXT NULL',
                'payment_method' => "VARCHAR(32) DEFAULT 'manual'",
                'txid' => 'VARCHAR(128) NULL',
                'crypto_amount' => 'VARCHAR(32) NULL',
                'crypto_currency' => 'VARCHAR(16) NULL'
            ];
            foreach ($txCols as $c => $d) {
                self::safeAddColumn($pdo, 'transactions', $c, $d);
            }

            // AI Assistant (v5.4.0): knowledge base, call logs, reseller charged subscriptions
            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (
                id $autoInc,
                title VARCHAR(191) NOT NULL,
                keywords VARCHAR(255) NULL,
                category VARCHAR(64) DEFAULT 'general',
                content TEXT NULL,
                image_url VARCHAR(512) NULL,
                images_json TEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_logs (
                id $autoInc,
                ticket_id INT NULL,
                stage VARCHAR(32) DEFAULT 'draft',
                provider VARCHAR(32) NULL,
                model VARCHAR(128) NULL,
                status VARCHAR(32) DEFAULT 'draft',
                category VARCHAR(64) NULL,
                confidence FLOAT DEFAULT 0,
                is_sensitive TINYINT(1) DEFAULT 0,
                needs_human TINYINT(1) DEFAULT 0,
                answer TEXT NULL,
                image_url VARCHAR(512) NULL,
                images_json TEXT NULL,
                latency_ms INT DEFAULT 0,
                error TEXT NULL,
                accepted TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            foreach (['idx_ai_logs_ticket', 'idx_ai_logs_created'] as $aiIdx) {
                $col = ($aiIdx === 'idx_ai_logs_ticket') ? 'ticket_id' : 'created_at';
                try {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS {$aiIdx} ON ai_logs ({$col})");
                } catch (Throwable $e) {
                    try { $pdo->exec("CREATE INDEX {$aiIdx} ON ai_logs ({$col})"); } catch (Throwable $e2) {}
                }
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_subscriptions (
                id $autoInc,
                reseller_id INT NOT NULL,
                status VARCHAR(32) DEFAULT 'active',
                activated_at DATETIME NULL,
                expires_at DATETIME NULL,
                price_paid BIGINT DEFAULT 0,
                last_price BIGINT DEFAULT 0,
                note VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (reseller_id)
            )");

            // AI-generated ticket messages (sender_id = 0)
            self::safeAddColumn($pdo, 'ticket_messages', 'is_ai', 'TINYINT(1) DEFAULT 0');

            // Reseller Custom Plans (v5.6.2 Hybrid): allow reseller to create own products
            $rpCols = [
                'is_custom' => 'TINYINT(1) DEFAULT 0',
                'traffic_gb' => 'FLOAT DEFAULT 0',
                'duration_days' => 'INT DEFAULT 30',
                'ip_limit' => 'INT DEFAULT 4',
                'server_id' => 'INT NULL',
                'base_cost' => 'BIGINT DEFAULT 0',
                'description' => 'TEXT NULL',
            ];
            foreach ($rpCols as $c => $d) {
                self::safeAddColumn($pdo, 'reseller_plans', $c, $d);
            }
            // AI Knowledge images (v5.7.1 - visual guide)
            $aiKnowCols = [
                'image_url' => 'VARCHAR(512) NULL',
                'images_json' => 'TEXT NULL',
            ];
            foreach ($aiKnowCols as $c => $d) {
                self::safeAddColumn($pdo, 'ai_knowledge', $c, $d);
            }
            $aiLogCols = [
                'image_url' => 'VARCHAR(512) NULL',
                'images_json' => 'TEXT NULL',
            ];
            foreach ($aiLogCols as $c => $d) {
                self::safeAddColumn($pdo, 'ai_logs', $c, $d);
            }
            self::safeAddColumn($pdo, 'ticket_messages', 'attachment_url', 'VARCHAR(512) NULL');
            self::safeAddColumn($pdo, 'ticket_messages', 'attachments_json', 'TEXT NULL');
            // Allow plan_id to be 0 for custom plans (MySQL strict)
            try {
                $pdo->exec("ALTER TABLE reseller_plans MODIFY plan_id INT NOT NULL DEFAULT 0");
            } catch (Throwable $e) {
                // SQLite or already ok
            }
            // bot_orders: link to custom reseller plan
            self::safeAddColumn($pdo, 'bot_orders', 'reseller_plan_id', 'INT NULL');
            self::safeAddColumn($pdo, 'bot_orders', 'custom_traffic_gb', 'FLOAT DEFAULT 0');
            self::safeAddColumn($pdo, 'bot_orders', 'custom_duration_days', 'INT DEFAULT 0');

            // Ensure Default Referral Codes for users
            try {
                $usersWithoutRef = $pdo->query("SELECT id, username FROM users WHERE referral_code IS NULL OR referral_code = ''")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($usersWithoutRef as $u) {
                    $code = 'REF' . strtoupper(substr(md5($u['username'] . $u['id']), 0, 6));
                    $pdo->exec("UPDATE users SET referral_code = '{$code}' WHERE id = {$u['id']}");
                }
            } catch (Throwable $e) {}

            // Performance: Create indexes for fast lookups (Phase 2)
            try {
                $indexes = [
                    // clients table - most queried
                    "CREATE INDEX IF NOT EXISTS idx_clients_username ON clients(username)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_sub_token ON clients(sub_token)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_reseller ON clients(reseller_id)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_server ON clients(server_id)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_plan ON clients(plan_id)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_expire ON clients(expire_at)",
                    "CREATE INDEX IF NOT EXISTS idx_clients_uuid ON clients(uuid)",
                    // users
                    "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
                    "CREATE INDEX IF NOT EXISTS idx_users_status ON users(status)",
                    "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
                    // transactions
                    "CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)",
                    "CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type)",
                    "CREATE INDEX IF NOT EXISTS idx_transactions_created ON transactions(created_at)",
                    // bot_orders
                    "CREATE INDEX IF NOT EXISTS idx_bot_orders_reseller ON bot_orders(reseller_id)",
                    "CREATE INDEX IF NOT EXISTS idx_bot_orders_status ON bot_orders(payment_status)",
                    "CREATE INDEX IF NOT EXISTS idx_bot_orders_tg ON bot_orders(user_tg_id)",
                    // bot_sessions
                    "CREATE INDEX IF NOT EXISTS idx_bot_sessions_tg ON bot_sessions(tg_id)",
                    // plans
                    "CREATE INDEX IF NOT EXISTS idx_plans_active ON plans(is_active)",
                    "CREATE INDEX IF NOT EXISTS idx_plans_group ON plans(server_group)",
                    // server_nodes
                    "CREATE INDEX IF NOT EXISTS idx_servers_active ON server_nodes(is_active)",
                    "CREATE INDEX IF NOT EXISTS idx_servers_group ON server_nodes(server_group)",
                    // tickets
                    "CREATE INDEX IF NOT EXISTS idx_tickets_user ON tickets(user_id)",
                    "CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)",
                    // system_settings
                    "CREATE INDEX IF NOT EXISTS idx_settings_key ON system_settings(setting_key)",
                ];
                foreach ($indexes as $sql) {
                    try {
                        $pdo->exec($sql);
                    } catch (Throwable $e) {
                        // MySQL syntax might differ for IF NOT EXISTS, try without
                        try {
                            $sql2 = str_replace('IF NOT EXISTS ', '', $sql);
                            $pdo->exec($sql2);
                        } catch (Throwable $e2) {}
                    }
                }
                // For MySQL, also try to add composite indexes
                if ($driver === 'mysql') {
                    try { $pdo->exec("CREATE INDEX idx_clients_reseller_status ON clients(reseller_id, status)"); } catch (Throwable $e) {}
                    try { $pdo->exec("CREATE INDEX idx_clients_expire_status ON clients(expire_at, status)"); } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {
                error_log("Index creation error: " . $e->getMessage());
            }

        } catch (Throwable $e) {
            // Safe failover
        }
    }

    public static function initializeSqliteSchema(?PDO $pdo = null): void {
        if ($pdo === null) {
            $pdo = self::$instance ?: self::getConnection();
        }
        $schema = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'reseller', -- 'admin' or 'reseller'
    full_name TEXT,
    email TEXT,
    wallet_balance INTEGER NOT NULL DEFAULT 0, -- in Tomans
    discount_percent INTEGER NOT NULL DEFAULT 0,
    allowed_groups TEXT DEFAULT 'all', -- 'all' or comma-separated groups
    api_token TEXT UNIQUE,
    status TEXT NOT NULL DEFAULT 'active', -- 'active', 'suspended'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS server_nodes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    driver TEXT NOT NULL, -- 'marzban', 'pasargad', '3xui', 'mock'
    api_url TEXT NOT NULL,
    api_username TEXT,
    api_password TEXT,
    api_token TEXT,
    server_group TEXT NOT NULL DEFAULT 'default', -- 'default', 'economic', 'iran_access', 'vip'
    sub_domain TEXT,
    inbound_tag TEXT,
    max_clients INTEGER DEFAULT 0, -- 0 = Unlimited
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    traffic_gb REAL NOT NULL,
    duration_days INTEGER NOT NULL,
    base_price INTEGER NOT NULL, -- Tomans
    reseller_price INTEGER NOT NULL, -- Tomans
    server_group TEXT NOT NULL DEFAULT 'default',
    server_id INTEGER NULL DEFAULT NULL,
    category_id INTEGER NULL DEFAULT NULL,
    category TEXT DEFAULT '۱ ماهه',
    show_in_bot INTEGER DEFAULT 1,
    ip_limit INTEGER DEFAULT 0,
    max_devices INTEGER DEFAULT 0,
    start_on_first_use INTEGER DEFAULT 0,
    is_free INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id INTEGER NOT NULL,
    server_id INTEGER NOT NULL,
    plan_id INTEGER,
    username TEXT UNIQUE NOT NULL,
    password TEXT,
    uuid TEXT UNIQUE NOT NULL,
    sub_token TEXT UNIQUE NOT NULL,
    traffic_limit_bytes INTEGER NOT NULL,
    traffic_used_bytes INTEGER DEFAULT 0,
    expire_at DATETIME,
    status TEXT NOT NULL DEFAULT 'active', -- 'active', 'expired', 'disabled', 'never_connected'
    last_connected_at DATETIME,
    custom_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(reseller_id) REFERENCES users(id),
    FOREIGN KEY(server_id) REFERENCES server_nodes(id),
    FOREIGN KEY(plan_id) REFERENCES plans(id)
);

CREATE TABLE IF NOT EXISTS reserved_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    plan_id INTEGER NOT NULL,
    traffic_gb REAL NOT NULL,
    duration_days INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued', -- 'queued', 'applied', 'cancelled'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    applied_at DATETIME,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY(plan_id) REFERENCES plans(id)
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount INTEGER NOT NULL, -- positive for credit, negative for purchase
    balance_after INTEGER NOT NULL,
    type TEXT NOT NULL, -- 'wallet_topup', 'plan_purchase', 'plan_renewal', 'refund'
    description TEXT,
    reference_id TEXT,
    status TEXT NOT NULL DEFAULT 'completed', -- 'completed', 'pending', 'failed'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS branding_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    brand_name TEXT DEFAULT 'Connectix VPN',
    theme_color TEXT DEFAULT 'violet', -- 'violet', 'blue', 'orange', 'green', 'black'
    logo_url TEXT,
    telegram_support TEXT,
    whatsapp_support TEXT,
    welcome_message TEXT,
    renewal_url TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    target_role TEXT DEFAULT 'all', -- 'all', 'reseller'
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL;
        $pdo->exec($schema);
        self::seedDefaultData($pdo);
    }

    public static function seedDefaultData(PDO $pdo): void {
        // Seed default Admin: admin / admin123
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, api_token) VALUES (1, 'admin', ?, 'admin', 'مدیر کل سیستم', 'admin@connectix.local', 0, 'admin_secret_token_123')");
        $stmt->execute([$adminPass]);

        // Seed default Branding
        $pdo->exec("INSERT OR IGNORE INTO branding_metadata (user_id, brand_name, theme_color, telegram_support, whatsapp_support, welcome_message) VALUES (1, 'Connectix Panel', 'violet', '@Connectix_Admin', '+989000000000', 'به پنل مدیریت اختصاصی کانکتیکس خوش آمدید.')");
    }

    /**
     * Restore database tables and records from SQL backup content
     */
    public static function restoreFromSql(string $sqlContent): array {
        $pdo = self::getConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        $sqlLines = explode("\n", $sqlContent);
        $cleanSql = '';
        foreach ($sqlLines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $cleanSql .= $line . "\n";
        }
        
        $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
        if (empty($statements)) {
            return ['success' => false, 'error' => 'هیچ دستور SQL معتبری در فایل بکاپ یافت نشد.'];
        }

        $executed = 0;
        $failed = 0;
        
        if ($driver === 'mysql') {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;"); } catch (Throwable $e) {}
        } else {
            try { $pdo->exec("PRAGMA foreign_keys = OFF;"); } catch (Throwable $e) {}
        }

        foreach ($statements as $stmt) {
            if (empty($stmt)) continue;
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (Throwable $e) {
                $failed++;
            }
        }

        if ($driver === 'mysql') {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch (Throwable $e) {}
        } else {
            try { $pdo->exec("PRAGMA foreign_keys = ON;"); } catch (Throwable $e) {}
        }

        // Re-run schema assurance in case new auxiliary columns are needed
        self::ensureExtendedTablesExist($pdo);

        return [
            'success' => true,
            'executed' => $executed,
            'failed' => $failed,
            'total' => count($statements)
        ];
    }
}

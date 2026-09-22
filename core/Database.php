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
                    self::$instance->exec('PRAGMA foreign_keys = ON;');
                    
                    if ($isNew || filesize(SQLITE_PATH) === 0) {
                        self::initializeSqliteSchema(self::$instance);
                    }
                } else {
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]);
                }
                self::ensureExtendedTablesExist(self::$instance);
            } catch (Throwable $e) {
                die("<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>خطای پایگاه داده</title><script src='https://cdn.tailwindcss.com'></script><style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap'); *{font-family:'Vazirmatn',sans-serif;}</style></head><body class='bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4'><div class='bg-slate-900 border border-rose-900/50 p-8 rounded-2xl max-w-md w-full text-center shadow-2xl space-y-4'><div class='w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 mx-auto flex items-center justify-center text-3xl'>⚠️</div><h2 class='text-lg font-bold text-white'>خطا در ارتباط با دیتابیس</h2><p class='text-xs text-rose-300 leading-relaxed'>" . htmlspecialchars($e->getMessage()) . "</p><div class='pt-2'><a href='install.php?reinstall=1' class='inline-block w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition-all shadow-md'>ورود به نصب‌کننده و تنظیم مجدد دیتابیس</a></div></div></body></html>");
            }
        }
        return self::$instance;
    }

    public static function ensureExtendedTablesExist(PDO $pdo): void {
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isSqlite = ($driver === 'sqlite');
            $autoInc = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';

            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TEXT NULL
            )");

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
                payment_method VARCHAR(32) DEFAULT 'card',
                payment_status VARCHAR(32) DEFAULT 'pending_receipt',
                receipt_photo_id VARCHAR(255) NULL,
                receipt_note TEXT NULL,
                client_id INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_sessions (
                tg_id VARCHAR(64) PRIMARY KEY,
                step VARCHAR(64) NULL,
                data TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Auto-migrate Users table columns for Multi-Tenant Reseller Platform
            if ($driver === 'mysql') {
                $cols = [
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
                    'credit_limit' => 'BIGINT DEFAULT 0'
                ];

                foreach ($cols as $col => $def) {
                    $check = $pdo->query("SHOW COLUMNS FROM `users` LIKE '{$col}'")->fetch();
                    if (!$check) {
                        $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
                    }
                }

                $orderCols = [
                    'reseller_id' => 'INT NULL DEFAULT 1',
                    'bot_token' => 'VARCHAR(255) NULL'
                ];
                foreach ($orderCols as $col => $def) {
                    $check = $pdo->query("SHOW COLUMNS FROM `bot_orders` LIKE '{$col}'")->fetch();
                    if (!$check) {
                        $pdo->exec("ALTER TABLE `bot_orders` ADD COLUMN `{$col}` {$def}");
                    }
                }

                $pdo->exec("CREATE TABLE IF NOT EXISTS `reseller_plans` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `reseller_id` INT NOT NULL,
                    `plan_id` INT NOT NULL,
                    `custom_title` VARCHAR(128) NULL,
                    `custom_category` VARCHAR(64) DEFAULT 'پیش‌فرض',
                    `retail_price` BIGINT NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_reseller_plan` (`reseller_id`, `plan_id`),
                    INDEX `idx_rp_reseller` (`reseller_id`),
                    INDEX `idx_rp_plan` (`plan_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `reseller_id` INT NULL DEFAULT 1,
                    `tg_id` VARCHAR(64) UNIQUE NOT NULL,
                    `first_name` VARCHAR(128) NULL,
                    `last_name` VARCHAR(128) NULL,
                    `username` VARCHAR(128) NULL,
                    `phone` VARCHAR(32) NULL,
                    `is_blocked` TINYINT(1) DEFAULT 0,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `last_active_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_bu_reseller` (`reseller_id`),
                    INDEX `idx_bu_tg` (`tg_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `reseller_applications` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_tg_id` VARCHAR(64) NOT NULL,
                    `user_tg_name` VARCHAR(128) NULL,
                    `user_tg_username` VARCHAR(128) NULL,
                    `brand_name` VARCHAR(128) NOT NULL,
                    `contact_info` VARCHAR(128) NOT NULL,
                    `preferred_username` VARCHAR(64) NOT NULL,
                    `estimated_sales` VARCHAR(64) NULL,
                    `experience_notes` TEXT NULL,
                    `status` VARCHAR(32) DEFAULT 'pending',
                    `approved_user_id` INT NULL,
                    `admin_notes` TEXT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_ra_tg` (`user_tg_id`),
                    INDEX `idx_ra_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            } else {
                // SQLite
                $cols = [
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
                    'credit_limit' => 'BIGINT DEFAULT 0'
                ];
                foreach ($cols as $col => $def) {
                    try {
                        $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
                    } catch (Throwable $e) {}
                }

                try {
                    $pdo->exec("ALTER TABLE bot_orders ADD COLUMN reseller_id INT NULL DEFAULT 1");
                } catch (Throwable $e) {}
                try {
                    $pdo->exec("ALTER TABLE bot_orders ADD COLUMN bot_token VARCHAR(255) NULL");
                } catch (Throwable $e) {}

                $pdo->exec("CREATE TABLE IF NOT EXISTS `reseller_plans` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `reseller_id` INT NOT NULL,
                    `plan_id` INT NOT NULL,
                    `custom_title` VARCHAR(128) NULL,
                    `custom_category` VARCHAR(64) DEFAULT 'پیش‌فرض',
                    `retail_price` BIGINT NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_users` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `reseller_id` INT NULL DEFAULT 1,
                    `tg_id` VARCHAR(64) UNIQUE NOT NULL,
                    `first_name` VARCHAR(128) NULL,
                    `last_name` VARCHAR(128) NULL,
                    `username` VARCHAR(128) NULL,
                    `phone` VARCHAR(32) NULL,
                    `is_blocked` TINYINT(1) DEFAULT 0,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `last_active_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                );");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `reseller_applications` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_tg_id` VARCHAR(64) NOT NULL,
                    `user_tg_name` VARCHAR(128) NULL,
                    `user_tg_username` VARCHAR(128) NULL,
                    `brand_name` VARCHAR(128) NOT NULL,
                    `contact_info` VARCHAR(128) NOT NULL,
                    `preferred_username` VARCHAR(64) NOT NULL,
                    `estimated_sales` VARCHAR(64) NULL,
                    `experience_notes` TEXT NULL,
                    `status` VARCHAR(32) DEFAULT 'pending',
                    `approved_user_id` INT NULL,
                    `admin_notes` TEXT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                );");
            }
        } catch (Throwable $e) {
            // Ignore if already created
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
    max_clients INTEGER DEFAULT 500,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    traffic_gb INTEGER NOT NULL,
    duration_days INTEGER NOT NULL,
    base_price INTEGER NOT NULL, -- Tomans
    reseller_price INTEGER NOT NULL, -- Tomans
    server_group TEXT NOT NULL DEFAULT 'default',
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
    traffic_gb INTEGER NOT NULL,
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

        // Seed default Reseller: reseller / 123456
        $resellerPass = password_hash('123456', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, password_hash, role, full_name, email, wallet_balance, discount_percent, api_token) VALUES (2, 'novinvpn', ?, 'reseller', 'نوین وی‌پی‌ان (نماینده نمونه)', 'novin@example.com', 500000, 15, 'reseller_novin_token_456')");
        $stmt->execute([$resellerPass]);

        // Seed default Branding for Reseller
        $pdo->exec("INSERT OR IGNORE INTO branding_metadata (user_id, brand_name, theme_color, telegram_support, whatsapp_support, welcome_message) VALUES (2, 'نوین وی‌پی‌ان', 'violet', '@NovinVPN_Support', '+989123456789', 'به نوین وی‌پی‌ان خوش آمدید. با بالاترین کیفیت و سرعت متصل شوید.')");
        $pdo->exec("INSERT OR IGNORE INTO branding_metadata (user_id, brand_name, theme_color, telegram_support, whatsapp_support, welcome_message) VALUES (1, 'Connectix Panel', 'violet', '@Connectix_Admin', '+989000000000', 'پنل مدیریت اختصاصی کانکتیکس')");

        // Seed default Server Nodes (Mock, Marzban, Pasargad, 3x-ui)
        $pdo->exec("INSERT OR IGNORE INTO server_nodes (id, name, driver, api_url, server_group, sub_domain, is_active) VALUES 
            (1, 'سرور فنلاند کلاود (Marzban Core)', 'mock', 'https://fi.marzban.example.com:8000', 'default', 'fi.connectix.space', 1),
            (2, 'سرور آلمان VIP (Pasargad Core)', 'mock', 'https://de.pasargad.example.com', 'vip', 'de-vip.connectix.space', 1),
            (3, 'سرور ملی ایران اکسس (3x-ui Core)', 'mock', 'https://ir.node.example.com:2053', 'iran_access', 'ir.connectix.space', 1),
            (4, 'سرور هلند اقتصادی (Economic Node)', 'mock', 'https://nl.node.example.com', 'economic', 'nl.connectix.space', 1)
        ");

        // Seed default Plans
        $pdo->exec("INSERT OR IGNORE INTO plans (id, title, traffic_gb, duration_days, base_price, reseller_price, server_group, is_free) VALUES 
            (1, 'پلن تست رایگان ۱ روزه (1GB)', 1, 1, 0, 0, 'default', 1),
            (2, 'یک‌ماهه ۳۰ گیگابایت (اقتصادی)', 30, 30, 95000, 75000, 'economic', 0),
            (3, 'یک‌ماهه ۵۰ گیگابایت (استاندارد)', 50, 30, 145000, 115000, 'default', 0),
            (4, 'دو‌ماهه ۱۰۰ گیگابایت (VIP تجاری)', 100, 60, 270000, 210000, 'vip', 0),
            (5, 'سه‌ماهه ۱۵۰ گیگابایت (ویژه)', 150, 90, 380000, 295000, 'default', 0),
            (6, 'یک‌ماهه نامحدود ایران اکسس', 200, 30, 190000, 150000, 'iran_access', 0)
        ");

        // Seed some sample Clients for demo analytics
        $now = date('Y-m-d H:i:s');
        $exp1 = date('Y-m-d H:i:s', strtotime('+25 days'));
        $exp2 = date('Y-m-d H:i:s', strtotime('+5 days'));
        $exp3 = date('Y-m-d H:i:s', strtotime('-2 days'));

        $pdo->exec("INSERT OR IGNORE INTO clients (id, reseller_id, server_id, plan_id, username, password, uuid, sub_token, traffic_limit_bytes, traffic_used_bytes, expire_at, status, last_connected_at) VALUES 
            (1, 2, 1, 3, 'user_arash_77', 'pass_9901', '7c3d2f5a-4b21-4f9e-8c3a-1d5e6f7a8b9c', 'sub_token_arash_77', 53687091200, 18253611008, '$exp1', 'active', '$now'),
            (2, 2, 2, 4, 'vip_sara_m', 'pass_4432', 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d', 'sub_token_sara_m', 107374182400, 89456123904, '$exp2', 'active', '$now'),
            (3, 2, 4, 2, 'eco_reza_k', 'pass_1123', 'f9e8d7c6-b5a4-4f3e-2d1c-0b9a8f7e6d5c', 'sub_token_reza_k', 32212254720, 32212254720, '$exp3', 'expired', NULL),
            (4, 2, 1, 1, 'test_user_demo', 'pass_demo', '88e8d7c6-b5a4-4f3e-2d1c-0b9a8f7e6999', 'sub_token_test_demo', 1073741824, 0, '$exp1', 'never_connected', NULL)
        ");

        // Seed a sample reserved plan for user_arash_77
        $pdo->exec("INSERT OR IGNORE INTO reserved_plans (client_id, plan_id, traffic_gb, duration_days, status) VALUES (1, 3, 50, 30, 'queued')");

        // Seed initial transactions
        $pdo->exec("INSERT OR IGNORE INTO transactions (user_id, amount, balance_after, type, description, reference_id, status) VALUES 
            (2, 1000000, 1000000, 'wallet_topup', 'شارژ اولیه کیف پول از طریق درگاه زرین‌پال', 'ZP-9823412', 'completed'),
            (2, -115000, 885000, 'plan_purchase', 'خرید پلن استاندارد ۵۰ گیگابایت برای مشتری user_arash_77', 'TX-1001', 'completed'),
            (2, -210000, 675000, 'plan_purchase', 'خرید پلن VIP ۱۰۰ گیگابایت برای مشتری vip_sara_m', 'TX-1002', 'completed'),
            (2, -75000, 600000, 'plan_purchase', 'خرید پلن اقتصادی ۳۰ گیگابایت برای مشتری eco_reza_k', 'TX-1003', 'completed'),
            (2, -100000, 500000, 'plan_renewal', 'رزرو پلن استاندارد ۵۰ گیگابایت برای مشتری user_arash_77', 'TX-1004', 'completed')
        ");

        // Seed announcement
        $pdo->exec("INSERT OR IGNORE INTO notifications (title, message, target_role, created_by) VALUES 
            ('بهینه‌سازی سرورهای آلمان و فنلاند', 'کلیه تانل‌های سرور فنلاند و آلمان به پروتکل‌های ضد فیلتر جدید مجهز شدند. سرعت و پایداری در بالاترین سطح قرار دارد.', 'all', 1)
        ");
    }
}

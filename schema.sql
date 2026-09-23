-- =====================================================================
-- Connectix-Style Multi-Panel VPN Reseller System
-- Complete MySQL Schema for cPanel (phpMyAdmin & Web Installer)
-- Charset: utf8mb4_unicode_ci
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Admins & Resellers)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'reseller') NOT NULL DEFAULT 'reseller',
    `full_name` VARCHAR(128) NULL,
    `email` VARCHAR(128) NULL,
    `wallet_balance` BIGINT NOT NULL DEFAULT 0 COMMENT 'Balance in Tomans',
    `discount_percent` INT NOT NULL DEFAULT 0,
    `allowed_groups` VARCHAR(255) DEFAULT 'all' COMMENT 'all or comma separated',
    `api_token` VARCHAR(128) UNIQUE NULL,
    `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `telegram_bot_token` VARCHAR(255) NULL,
    `telegram_bot_username` VARCHAR(128) NULL,
    `telegram_admin_chat_id` VARCHAR(64) NULL,
    `brand_name` VARCHAR(128) NULL,
    `logo_url` VARCHAR(255) NULL,
    `theme_color` VARCHAR(32) DEFAULT 'violet',
    `card_number` VARCHAR(32) NULL,
    `card_holder` VARCHAR(128) NULL,
    `card_shaba` VARCHAR(34) NULL,
    `zarinpal_merchant` VARCHAR(64) NULL,
    `support_username` VARCHAR(128) NULL,
    `welcome_message` TEXT NULL,
    `telegram_channel` VARCHAR(128) NULL,
    `custom_domain` VARCHAR(128) NULL,
    `credit_limit` BIGINT DEFAULT 0,
    `auto_tier_enabled` TINYINT(1) DEFAULT 1,
    `tier_level` VARCHAR(32) DEFAULT 'bronze',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories / Clusters Table (Locations & Groups)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(128) NOT NULL,
    `slug` VARCHAR(64) UNIQUE NOT NULL,
    `type` VARCHAR(32) NOT NULL DEFAULT 'both' COMMENT 'server, plan, or both',
    `icon` VARCHAR(64) DEFAULT 'fa-server',
    `badge_color` VARCHAR(32) DEFAULT 'purple',
    `description` TEXT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Server Nodes Table (Supports Marzban, Pasargad, 3x-ui, and Mock)
CREATE TABLE IF NOT EXISTS `server_nodes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(128) NOT NULL,
    `driver` VARCHAR(32) NOT NULL DEFAULT 'marzban',
    `api_url` VARCHAR(255) NOT NULL,
    `api_username` VARCHAR(128) NULL,
    `api_password` VARCHAR(255) NULL,
    `api_token` TEXT NULL,
    `server_group` VARCHAR(64) NOT NULL DEFAULT 'default',
    `category_id` INT NULL DEFAULT NULL,
    `sub_domain` VARCHAR(128) NULL,
    `inbound_tag` VARCHAR(64) NULL,
    `max_clients` INT DEFAULT 500,
    `is_active` TINYINT(1) DEFAULT 1,
    `health_status` VARCHAR(32) DEFAULT 'online',
    `latency_ms` INT DEFAULT 0,
    `last_checked_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_server_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Plans Table
CREATE TABLE IF NOT EXISTS `plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(128) NOT NULL,
    `traffic_gb` INT NOT NULL,
    `duration_days` INT NOT NULL,
    `base_price` BIGINT NOT NULL,
    `reseller_price` BIGINT NOT NULL,
    `server_group` VARCHAR(64) NOT NULL DEFAULT 'default',
    `server_id` INT NULL DEFAULT NULL,
    `category_id` INT NULL DEFAULT NULL,
    `category` VARCHAR(64) NOT NULL DEFAULT '۱ ماهه',
    `show_in_bot` TINYINT(1) DEFAULT 1,
    `ip_limit` INT DEFAULT 2,
    `is_free` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_plans_server` (`server_id`),
    INDEX `idx_plans_group` (`server_group`),
    INDEX `idx_plans_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Clients Table
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reseller_id` INT NOT NULL,
    `server_id` INT NOT NULL,
    `plan_id` INT NULL,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password` VARCHAR(64) NULL,
    `uuid` VARCHAR(64) NOT NULL UNIQUE,
    `sub_token` VARCHAR(64) NOT NULL UNIQUE,
    `traffic_limit_bytes` BIGINT NOT NULL,
    `traffic_used_bytes` BIGINT DEFAULT 0,
    `expire_at` DATETIME NULL,
    `ip_limit` INT DEFAULT 2,
    `status` ENUM('active', 'expired', 'disabled', 'never_connected') NOT NULL DEFAULT 'active',
    `last_connected_at` DATETIME NULL,
    `telegram_chat_id` VARCHAR(64) NULL,
    `alert_80_sent` TINYINT(1) DEFAULT 0,
    `alert_95_sent` TINYINT(1) DEFAULT 0,
    `alert_exp_sent` TINYINT(1) DEFAULT 0,
    `alert_final_sent` TINYINT(1) DEFAULT 0,
    `custom_note` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reseller` (`reseller_id`),
    INDEX `idx_server` (`server_id`),
    INDEX `idx_sub_token` (`sub_token`),
    CONSTRAINT `fk_client_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_client_server` FOREIGN KEY (`server_id`) REFERENCES `server_nodes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_client_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Reserved Subscriptions Queue Table
CREATE TABLE IF NOT EXISTS `reserved_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `traffic_gb` INT NOT NULL,
    `duration_days` INT NOT NULL,
    `status` ENUM('queued', 'applied', 'cancelled') NOT NULL DEFAULT 'queued',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `applied_at` DATETIME NULL,
    CONSTRAINT `fk_reserved_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reserved_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Transactions Table (Prepaid Ledger)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` BIGINT NOT NULL COMMENT 'Positive for top-up, negative for deduction',
    `balance_after` BIGINT NOT NULL,
    `type` VARCHAR(32) NOT NULL,
    `description` VARCHAR(255) NULL,
    `reference_id` VARCHAR(64) NULL,
    `status` ENUM('completed', 'pending', 'failed') NOT NULL DEFAULT 'completed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_trans` (`user_id`),
    CONSTRAINT `fk_trans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. White-Label Branding & Metadata Table
CREATE TABLE IF NOT EXISTS `branding_metadata` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `brand_name` VARCHAR(128) DEFAULT 'Connectix VPN',
    `theme_color` VARCHAR(32) DEFAULT 'violet',
    `logo_url` VARCHAR(255) NULL,
    `telegram_support` VARCHAR(128) NULL,
    `whatsapp_support` VARCHAR(64) NULL,
    `welcome_message` TEXT NULL,
    `renewal_url` VARCHAR(255) NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_branding_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `target_role` VARCHAR(32) DEFAULT 'all',
    `created_by` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. System & Bot Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(64) PRIMARY KEY,
    `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Telegram Bot Orders Table
CREATE TABLE IF NOT EXISTS `bot_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(32) UNIQUE,
    `reseller_id` INT NULL DEFAULT 1,
    `bot_token` VARCHAR(255) NULL,
    `user_tg_id` VARCHAR(64) NOT NULL,
    `user_tg_name` VARCHAR(128) NULL,
    `user_tg_username` VARCHAR(128) NULL,
    `order_type` VARCHAR(32) DEFAULT 'new',
    `plan_id` INT NULL,
    `server_id` INT NULL,
    `target_username` VARCHAR(64) NULL,
    `amount` BIGINT DEFAULT 0,
    `coupon_code` VARCHAR(64) NULL,
    `discount_amount` BIGINT DEFAULT 0,
    `payment_method` VARCHAR(32) DEFAULT 'card',
    `payment_status` VARCHAR(32) DEFAULT 'pending_receipt',
    `receipt_photo_id` VARCHAR(255) NULL,
    `receipt_note` TEXT NULL,
    `client_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_tg` (`user_tg_id`),
    INDEX `idx_order_reseller` (`reseller_id`),
    INDEX `idx_order_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Bot User Sessions
CREATE TABLE IF NOT EXISTS `bot_sessions` (
    `tg_id` VARCHAR(64) NOT NULL,
    `reseller_id` INT NOT NULL DEFAULT 1,
    `step` VARCHAR(64) NULL,
    `data` TEXT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tg_id`, `reseller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Reseller Custom Plans & Custom Pricing
CREATE TABLE IF NOT EXISTS `reseller_plans` (
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
    INDEX `idx_rp_plan` (`plan_id`),
    CONSTRAINT `fk_rp_user` FOREIGN KEY (`reseller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Activity & Audit Logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(64) NULL,
    `entity_id` VARCHAR(64) NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_user` (`user_id`),
    INDEX `idx_log_action` (`action`),
    INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Application Download Guides & Video Tutorials
CREATE TABLE IF NOT EXISTS `app_guides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(32) NOT NULL,
    `app_name` VARCHAR(128) NOT NULL,
    `download_url` VARCHAR(255) NOT NULL,
    `guide_url` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ag_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Discount Coupons
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(64) NOT NULL UNIQUE,
    `discount_percent` INT NOT NULL DEFAULT 10,
    `max_uses` INT DEFAULT 100,
    `used_count` INT DEFAULT 0,
    `expire_at` DATETIME NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cpn_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. In-Panel Tickets & Support Messages
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `department` VARCHAR(64) DEFAULT 'support',
    `priority` VARCHAR(32) DEFAULT 'medium',
    `status` VARCHAR(32) DEFAULT 'open',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ticket_user` (`user_id`),
    INDEX `idx_ticket_status` (`status`),
    CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tm_ticket` (`ticket_id`),
    CONSTRAINT `fk_tm_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Free Trial Logs Table
CREATE TABLE IF NOT EXISTS `trial_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `reseller_id` INT NULL DEFAULT 1,
    `telegram_id` VARCHAR(64) NULL,
    `ip_address` VARCHAR(64) NULL,
    `client_id` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tl_tg` (`telegram_id`),
    INDEX `idx_tl_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Crypto Payments Table (TON / USDT)
CREATE TABLE IF NOT EXISTS `crypto_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `reseller_id` INT NULL DEFAULT 1,
    `order_id` INT NULL,
    `currency` VARCHAR(16) DEFAULT 'USDT',
    `network` VARCHAR(16) DEFAULT 'TRC20',
    `expected_amount_usdt` DECIMAL(10,2) DEFAULT 0.00,
    `toman_amount` BIGINT DEFAULT 0,
    `wallet_address` VARCHAR(128) NOT NULL,
    `tx_hash` VARCHAR(128) NULL,
    `status` VARCHAR(32) DEFAULT 'pending',
    `admin_notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cp_status` (`status`),
    INDEX `idx_cp_tx` (`tx_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Bot Users Directory
CREATE TABLE IF NOT EXISTS `bot_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reseller_id` INT NULL DEFAULT 1,
    `tg_id` VARCHAR(64) UNIQUE NOT NULL,
    `first_name` VARCHAR(128) NULL,
    `last_name` VARCHAR(128) NULL,
    `username` VARCHAR(128) NULL,
    `phone` VARCHAR(32) NULL,
    `referred_by` VARCHAR(64) NULL,
    `referral_balance` BIGINT DEFAULT 0,
    `referral_count` INT DEFAULT 0,
    `is_blocked` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_active_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bu_reseller` (`reseller_id`),
    INDEX `idx_bu_tg` (`tg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Reseller Applications Table
CREATE TABLE IF NOT EXISTS `reseller_applications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed Default Categories / Clusters
INSERT INTO `categories` (`id`, `name`, `slug`, `type`, `icon`, `badge_color`, `description`, `sort_order`, `is_active`) VALUES
(1, 'پیش‌فرض (استاندارد)', 'default', 'both', 'fa-globe', 'purple', 'خوشه سرورها و پلن‌های استاندارد بین‌الملل', 1, 1),
(2, 'سرورهای VIP و پرسرعت', 'vip', 'both', 'fa-crown', 'amber', 'سرورهای بهینه‌شده با پهنای باند اختصاصی و پینگ پایین', 2, 1),
(3, 'سرورهای اقتصادی (Economic)', 'economic', 'both', 'fa-tag', 'blue', 'پلن‌های باصرفه و اقتصادی جهت وب‌گردی روزمره', 3, 1),
(4, 'ایران اکسس (ملی و نامحدود)', 'iran_access', 'both', 'fa-shield-halved', 'emerald', 'سرورهای با دسترسی به سایت‌های داخلی و ترافیک نامحدود', 4, 1),
(5, 'مخصوص بازی و گیمینگ (Gaming)', 'gaming', 'both', 'fa-gamepad', 'cyan', 'سرورهای تونل‌شده بدون نوسان و کمترین زمان پاسخگویی', 5, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Seed Default Server Nodes
INSERT INTO `server_nodes` (`id`, `name`, `driver`, `api_url`, `server_group`, `sub_domain`, `is_active`) VALUES 
(1, 'سرور فنلاند کلاود (Marzban Core)', 'mock', 'https://fi.marzban.example.com:8000', 'default', 'fi.connectix.space', 1),
(2, 'سرور آلمان VIP (Pasargad Core)', 'mock', 'https://de.pasargad.example.com', 'vip', 'de-vip.connectix.space', 1),
(3, 'سرور ملی ایران اکسس (3x-ui Core)', 'mock', 'https://ir.node.example.com:2053', 'iran_access', 'ir.connectix.space', 1),
(4, 'سرور هلند اقتصادی (Economic Node)', 'mock', 'https://nl.node.example.com', 'economic', 'nl.connectix.space', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Seed Default Plans
INSERT INTO `plans` (`id`, `title`, `traffic_gb`, `duration_days`, `base_price`, `reseller_price`, `server_group`, `is_free`, `is_active`) VALUES 
(1, 'پلن تست رایگان ۱ روزه (1GB)', 1, 1, 0, 0, 'default', 1, 1),
(2, 'یک‌ماهه ۳۰ گیگابایت (اقتصادی)', 30, 30, 95000, 75000, 'economic', 0, 1),
(3, 'یک‌ماهه ۵۰ گیگابایت (استاندارد)', 50, 30, 145000, 115000, 'default', 0, 1),
(4, 'دو‌ماهه ۱۰۰ گیگابایت (VIP تجاری)', 100, 60, 270000, 210000, 'vip', 0, 1),
(5, 'سه‌ماهه ۱۵۰ گیگابایت (ویژه)', 150, 90, 380000, 295000, 'default', 0, 1),
(6, 'یک‌ماهه نامحدود ایران اکسس', 200, 30, 190000, 150000, 'iran_access', 0, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- Seed Default App Guides
INSERT INTO `app_guides` (`platform`, `app_name`, `download_url`, `guide_url`, `description`, `sort_order`, `is_active`) VALUES
('android', 'v2rayNG (پیشنهادی اندروید)', 'https://github.com/2dust/v2rayNG/releases', 'https://t.me/connectix/79', 'پایدارترین کلاینت اندروید با قابلیت اتصال خودکار و پشتیبانی از همه پروتکل‌ها', 1, 1),
('android', 'NapsternetV (کلاینت دوم اندروید)', 'https://play.google.com/store/apps/details?id=com.napsternetlabs.napsternetv', '', 'نرم‌افزار کمکی برای اینترنت‌های با اختلال بالا', 2, 1),
('ios', 'Streisand (پیشنهادی آیفون و آیپد)', 'https://apps.apple.com/app/streisand/id6450534064', '', 'رایگان، بسیار سریع و سازگار با اینترنت‌های همراه اول و ایرانسل', 1, 1),
('ios', 'V2Box (کلاینت جایگزین iOS)', 'https://apps.apple.com/app/v2box-v2ray-client/id6446814042', '', 'پشتیبانی کامل از ساب‌لینک هوشمند و پینگ تست آنی', 2, 1),
('windows', 'NekoRay (پیشنهادی ویندوز)', 'https://github.com/MatsuriDayo/nekoray/releases', '', 'دارای حالت System Proxy و VPN Mode برای کل ترافیک ویندوز', 1, 1),
('windows', 'v2rayN (کلاینت کلاسیک ویندوز)', 'https://github.com/2dust/v2rayN/releases', '', 'پشتیبانی از Reality و Xray Core', 2, 1),
('macos', 'FoXray (مک‌بوک)', 'https://apps.apple.com/app/foxray/id6448898396', '', 'کلاینت رسمی و بسیار سبک سیستم‌عامل macOS', 1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Sample Coupons
INSERT INTO `coupons` (`code`, `discount_percent`, `max_uses`, `used_count`, `is_active`) VALUES
('WELCOME10', 10, 100, 0, 1),
('VIP20', 20, 50, 0, 1)
ON DUPLICATE KEY UPDATE `code`=`code`;

-- Seed Default Admin: admin / admin123
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `email`, `wallet_balance`, `api_token`) 
VALUES (1, 'admin', '$2y$10$7Z2v7v5uV2o6L5w2R3e1OeK3V5j7m6l5P2q8r7T4u1i9O2p3A4b5C', 'admin', 'مدیر ارشد سامانه', 'admin@connectix.local', 0, 'admin_secret_token_123')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- Seed Default Reseller: novinvpn / 123456
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `full_name`, `email`, `wallet_balance`, `discount_percent`, `api_token`) 
VALUES (2, 'novinvpn', '$2y$10$7Z2v7v5uV2o6L5w2R3e1OeK3V5j7m6l5P2q8r7T4u1i9O2p3A4b5C', 'reseller', 'نوین وی‌پی‌ان (نماینده نمونه)', 'novin@example.com', 500000, 15, 'reseller_novin_token_456')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- Seed Default Branding
INSERT INTO `branding_metadata` (`user_id`, `brand_name`, `theme_color`, `telegram_support`, `whatsapp_support`, `welcome_message`) VALUES
(1, 'Connectix Panel', 'violet', '@Connectix_Admin', '+989000000000', 'پنل مدیریت اختصاصی کانکتیکس'),
(2, 'نوین وی‌پی‌ان', 'violet', '@NovinVPN_Support', '+989123456789', 'به نوین وی‌پی‌ان خوش آمدید. با بالاترین کیفیت و سرعت متصل شوید.')
ON DUPLICATE KEY UPDATE `brand_name`=VALUES(`brand_name`);

-- Seed Default System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('github_repo', 'hojjatrad/panelconnectix'),
('github_branch', 'main'),
('github_webhook_secret', 'gh_hook_sec_vpbotn_2026'),
('brand_name', 'Connectix VPN'),
('current_version', '2.7.1')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

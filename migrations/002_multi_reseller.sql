-- Migration 002: Multi-Tenant Reseller Platform (Dedicated Bot, Branding, Banking, Custom Pricing)

-- Reseller Custom Plans & Pricing Table
CREATE TABLE IF NOT EXISTS `reseller_plans` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `reseller_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `custom_title` VARCHAR(128) NULL,
    `custom_category` VARCHAR(64) DEFAULT 'پیش‌فرض',
    `retail_price` BIGINT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS `idx_uk_reseller_plan` ON `reseller_plans` (`reseller_id`, `plan_id`);
CREATE INDEX IF NOT EXISTS `idx_rp_reseller` ON `reseller_plans` (`reseller_id`);
CREATE INDEX IF NOT EXISTS `idx_rp_plan` ON `reseller_plans` (`plan_id`);

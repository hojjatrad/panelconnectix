-- v4.0.71 Migration: Add missing indexes for performance
-- Run: php -r "require 'core/Database.php'; \$pdo=Database::getConnection(); echo 'ok';"

-- Check and add indexes if not exists (MySQL)
-- For SQLite, these will be created via safeAddIndex

-- This file is for reference, actual migration via PHP:
-- Database::safeAddIndex($pdo, 'clients', 'username', 'idx_client_username');
-- Database::safeAddIndex($pdo, 'clients', 'expire_at', 'idx_client_expire');
-- Database::safeAddIndex($pdo, 'clients', 'status', 'idx_client_status');
-- Database::safeAddIndex($pdo, 'clients', 'traffic_used_bytes', 'idx_client_traffic');
-- Database::safeAddIndex($pdo, 'clients', 'created_at', 'idx_client_created');

-- MySQL direct:
CREATE INDEX IF NOT EXISTS idx_client_username ON clients(username);
CREATE INDEX IF NOT EXISTS idx_client_expire ON clients(expire_at);
CREATE INDEX IF NOT EXISTS idx_client_status ON clients(status);
CREATE INDEX IF NOT EXISTS idx_client_traffic ON clients(traffic_used_bytes);
CREATE INDEX IF NOT EXISTS idx_client_created ON clients(created_at);

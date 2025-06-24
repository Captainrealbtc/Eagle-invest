-- Ensure reply and replied_at columns exist for upgrades
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS reply TEXT;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP NULL DEFAULT NULL;

-- Ensure details column exists for upgrades
ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS details TEXT;

-- Ensure active column exists for upgrades
ALTER TABLE users ADD COLUMN IF NOT EXISTS active TINYINT(1) DEFAULT 0;
-- Ensure wallet_address column exists for upgrades
ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_address VARCHAR(255) DEFAULT NULL;

-- Ensure blocked and balance columns exist (for upgrades)
ALTER TABLE users ADD COLUMN IF NOT EXISTS blocked TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS balance DECIMAL(18,2) DEFAULT 0.00;

-- SQL script to create user_auth_db and required tables
CREATE DATABASE IF NOT EXISTS user_auth_db;
USE user_auth_db;

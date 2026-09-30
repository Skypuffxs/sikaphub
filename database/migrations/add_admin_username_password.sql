-- Migration: Add username and password_hash to peso_admins table
-- Purpose: Enable direct username + password login for PESO Admins without email OTP

ALTER TABLE peso_admins 
ADD COLUMN IF NOT EXISTS username VARCHAR(50) NULL UNIQUE AFTER user_id,
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER username;

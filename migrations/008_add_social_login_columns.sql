-- Migration: Add social login columns to users table
-- Date: 2026-09-16

ALTER TABLE `users` 
ADD COLUMN `vk_id` BIGINT(20) DEFAULT NULL AFTER `telegram_id`,
ADD COLUMN `google_id` VARCHAR(50) DEFAULT NULL AFTER `vk_id`,
ADD COLUMN `auth_provider` VARCHAR(20) DEFAULT 'email' AFTER `google_id`;

-- Add indexes for faster lookups
CREATE INDEX `idx_vk_id` ON `users`(`vk_id`);
CREATE INDEX `idx_google_id` ON `users`(`google_id`);
CREATE INDEX `idx_auth_provider` ON `users`(`auth_provider`);

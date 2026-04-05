-- ============================================================
--  Instalegl Database Migration - OTP Status Updates
--  Run this script to update the database schema for OTP functionality
-- ============================================================

USE `instalegl_db`;

-- Add status column to contact_messages if not exists
ALTER TABLE `contact_messages` ADD COLUMN `status` ENUM('otp_pending','new','responded') NOT NULL DEFAULT 'otp_pending' AFTER `is_read`;

-- Update bookings status enum to include OTP pending state
ALTER TABLE `bookings` MODIFY `status` ENUM('otp_pending','new','in_progress','completed','cancelled') NOT NULL DEFAULT 'otp_pending';

-- Update advocate_applications status enum to include OTP pending state  
ALTER TABLE `advocate_applications` MODIFY `status` ENUM('otp_pending','pending','under_review','approved','rejected') NOT NULL DEFAULT 'otp_pending';

-- Add index for phone verification lookups
ALTER TABLE `bookings` ADD INDEX `idx_phone` (`phone`);
ALTER TABLE `contact_messages` ADD INDEX `idx_phone` (`phone`);
ALTER TABLE `advocate_applications` ADD INDEX `idx_phone` (`phone`);

-- Add index for status lookups
ALTER TABLE `contact_messages` ADD INDEX `idx_status` (`status`);

-- Success message
SELECT 'Migration completed successfully!' as message;

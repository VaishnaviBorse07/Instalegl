-- Migration: Add QR Code field to advocate_applications
-- Run this to add the doc_qr_code column to existing databases

ALTER TABLE `advocate_applications`
ADD COLUMN `doc_qr_code` VARCHAR(255) NULL COMMENT 'Payment QR code image' AFTER `doc_photo`;
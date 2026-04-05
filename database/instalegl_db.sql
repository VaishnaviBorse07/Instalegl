-- ============================================================
--  Instalegl Database Schema
--  Import: mysql -u root -p instalegl_db < instalegl_db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `instalegl_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `instalegl_db`;

-- ─── Admin Users ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)     NOT NULL UNIQUE,
  `password`   VARCHAR(255)    NOT NULL COMMENT 'bcrypt hash',
  `full_name`  VARCHAR(100)    NOT NULL DEFAULT '',
  `role`       ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
  `last_login` DATETIME        NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: username=admin  password=admin123
INSERT INTO `admin_users` (`username`,`password`,`full_name`,`role`)
VALUES ('admin', 'admin123', 'Site Admin', 'superadmin');


-- ─── Client Bookings ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ref_number`           VARCHAR(20)   NOT NULL UNIQUE COMMENT 'e.g. IGL-2025-00001',
  `full_name`            VARCHAR(120)  NOT NULL,
  `email`                VARCHAR(180)  NULL     COMMENT 'Client email — OTP sent here',
  `phone`                VARCHAR(20)   NOT NULL,
  `service`              VARCHAR(60)   NOT NULL,
  `status`               ENUM('otp_pending','new','payment_pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'otp_pending',
  `payment_screenshot`   VARCHAR(255)  NULL     COMMENT 'Relative path under uploads/',
  `payment_txn_id`       VARCHAR(60)   NULL     COMMENT 'Auto-generated TXN reference',
  `payment_amount`       DECIMAL(10,2) NULL     COMMENT 'Amount paid in INR',
  `admin_notes`          TEXT          NULL,
  `ip_address`           VARCHAR(45)   NULL,
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Migration: add/update columns if upgrading existing DB ───
-- Run these on your live server if the table already exists:
--   ALTER TABLE `bookings`
--     ADD COLUMN IF NOT EXISTS `email` VARCHAR(180) NULL AFTER `full_name`,
--     MODIFY COLUMN `status` ENUM('otp_pending','new','payment_pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'otp_pending',
--     ADD COLUMN IF NOT EXISTS `payment_screenshot` VARCHAR(255) NULL AFTER `status`,
--     ADD COLUMN IF NOT EXISTS `payment_txn_id`     VARCHAR(60)  NULL AFTER `payment_screenshot`,
--     ADD COLUMN IF NOT EXISTS `payment_amount`     DECIMAL(10,2) NULL AFTER `payment_txn_id`;




-- ─── Contact Messages ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(120)  NOT NULL,
  `email`       VARCHAR(180)  NOT NULL,
  `phone`       VARCHAR(20)   NULL,
  `subject`     VARCHAR(100)  NOT NULL,
  `message`     TEXT          NOT NULL,
  `is_read`     TINYINT(1)    NOT NULL DEFAULT 0,
  `status`      ENUM('otp_pending','new','responded') NOT NULL DEFAULT 'otp_pending',
  `admin_notes` TEXT          NULL,
  `ip_address`  VARCHAR(45)   NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_is_read` (`is_read`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── Advocate Applications ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `advocate_applications` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `app_id`          VARCHAR(20)   NOT NULL UNIQUE COMMENT 'e.g. ADV-2025-00001',
  -- Personal
  `full_name`       VARCHAR(120)  NOT NULL,
  `dob`             DATE          NULL,
  `email`           VARCHAR(180)  NOT NULL,
  `phone`           VARCHAR(20)   NOT NULL,
  `city`            VARCHAR(60)   NOT NULL,
  `address`         TEXT          NULL,
  -- Practice
  `bar_council`     VARCHAR(80)   NOT NULL,
  `enrollment_no`   VARCHAR(50)   NOT NULL,
  `enrollment_date` DATE          NULL,
  `years_practice`  VARCHAR(20)   NOT NULL,
  `practice_areas`  VARCHAR(255)  NOT NULL,
  `courts`          VARCHAR(255)  NULL,
  `languages`       VARCHAR(120)  NULL,
  `bio`             TEXT          NULL,
  -- Documents (relative paths under uploads/)
  `doc_bc_front`    VARCHAR(255)  NULL,
  `doc_bc_back`     VARCHAR(255)  NULL,
  `doc_cert`        VARCHAR(255)  NULL,
  `doc_govid`       VARCHAR(255)  NULL,
  `gov_id_type`     VARCHAR(30)   NULL,
  `doc_photo`       VARCHAR(255)  NULL,
  `doc_qr_code`     VARCHAR(255)  NULL COMMENT 'Payment QR code image',
  -- Status
  `status`          ENUM('otp_pending','pending','under_review','approved','rejected') NOT NULL DEFAULT 'otp_pending',
  `admin_notes`     TEXT          NULL,
  `reviewed_by`     INT UNSIGNED  NULL,
  `reviewed_at`     DATETIME      NULL,
  `ip_address`      VARCHAR(45)   NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── OTP Verification ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `phone`         VARCHAR(20)   NOT NULL,
  `email`         VARCHAR(180)  NULL,
  `otp_code`      VARCHAR(6)    NOT NULL,
  `is_verified`   TINYINT(1)    NOT NULL DEFAULT 0,
  `attempts`      INT           NOT NULL DEFAULT 0,
  `expires_at`    DATETIME      NOT NULL COMMENT 'OTP expires after 10 minutes',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

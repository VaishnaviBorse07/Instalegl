-- Migration: Add email column to bookings table
USE `u721128021_instalegl_db`;

ALTER TABLE `bookings`
  ADD COLUMN `email` VARCHAR(180) NULL AFTER `phone`;

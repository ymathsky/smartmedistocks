-- Migration 004: Add dedicated email column to suppliers table
-- Run this once on your database to enable the "Send PO to Supplier" feature.

ALTER TABLE `suppliers`
    ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Dedicated supplier email for sending Purchase Orders' AFTER `contact_info`;

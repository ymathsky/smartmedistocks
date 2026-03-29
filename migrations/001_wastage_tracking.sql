-- ====================================================
-- Wastage Tracking Migration
-- Run this SQL on your SmartMediStocks database.
-- Adds transaction_type and notes columns to transactions.
-- ====================================================

ALTER TABLE `transactions`
    ADD COLUMN `transaction_type` VARCHAR(50) NOT NULL DEFAULT 'Usage' AFTER `transaction_date`,
    ADD COLUMN `notes` TEXT NULL AFTER `transaction_type`;

-- Index for fast filtering by type (optional, recommended for large datasets)
ALTER TABLE `transactions`
    ADD INDEX `idx_transaction_type` (`transaction_type`);

-- ====================================================
-- Batch Quarantine Migration
-- Run this SQL on your SmartMediStocks database.
-- Adds status column to item_batches for quarantine tracking.
-- ====================================================

ALTER TABLE `item_batches`
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'Active'
        COMMENT 'Active | Quarantined | Written-Off'
        AFTER `quantity`;

-- Index so dashboard queries filtering on status are fast
ALTER TABLE `item_batches`
    ADD INDEX `idx_batch_status` (`status`);

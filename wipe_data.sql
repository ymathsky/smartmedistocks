-- ============================================================
-- SmediStocks - Wipe All Data (Preserve Users)
-- Generated: 2026-03-23
-- WARNING: This is IRREVERSIBLE. Back up your database first!
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Child tables first, then parent tables
DELETE FROM `chat_log`;
DELETE FROM `decision_log`;
DELETE FROM `notifications`;
DELETE FROM `item_batches`;
DELETE FROM `transactions`;
DELETE FROM `purchase_orders`;
DELETE FROM `items`;
DELETE FROM `suppliers`;
DELETE FROM `locations`;
DELETE FROM `settings`;

-- Reset auto-increment counters
ALTER TABLE `chat_log` AUTO_INCREMENT = 1;
ALTER TABLE `decision_log` AUTO_INCREMENT = 1;
ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `item_batches` AUTO_INCREMENT = 1;
ALTER TABLE `transactions` AUTO_INCREMENT = 1;
ALTER TABLE `purchase_orders` AUTO_INCREMENT = 1;
ALTER TABLE `items` AUTO_INCREMENT = 1;
ALTER TABLE `suppliers` AUTO_INCREMENT = 1;
ALTER TABLE `locations` AUTO_INCREMENT = 1;
ALTER TABLE `settings` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table is intentionally left untouched.
-- Run: SELECT * FROM users; to verify users are still present.

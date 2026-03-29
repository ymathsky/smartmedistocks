-- Migration 006: Controlled Substance Dual-Authorization
-- Run this on the live database via phpMyAdmin before deploying.

ALTER TABLE `items`
    ADD COLUMN `is_controlled` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Set to 1 to require dual-authorization when dispensing'
    AFTER `supplier_id`;

ALTER TABLE `transactions`
    ADD COLUMN `authorizer_user_id` INT NULL DEFAULT NULL
    COMMENT 'User ID of the second authorizer for controlled substance transactions'
    AFTER `transaction_date`;

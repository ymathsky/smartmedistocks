-- ====================================================
-- Delivery Date Tracking Migration
-- Run this SQL on your SmartMediStocks database.
-- Adds actual_delivery_date to purchase_orders.
-- ====================================================

ALTER TABLE `purchase_orders`
    ADD COLUMN `actual_delivery_date` DATE NULL
        COMMENT 'Set when stock is received via receive_stock_handler.php'
        AFTER `expected_delivery_date`;

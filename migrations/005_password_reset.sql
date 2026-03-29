-- Migration 005: Password reset support
-- Run this once on your database.

-- Add email column to users table for password reset
ALTER TABLE `users`
    ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL COMMENT 'User email address for password reset' AFTER `username`;

-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `token`      VARCHAR(128) NOT NULL,
    `user_id`    INT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`token`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

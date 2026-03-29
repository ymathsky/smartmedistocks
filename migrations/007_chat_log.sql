-- Migration 007: Create chat_log table for AI Assistant conversation history
-- Run this on your production database.

CREATE TABLE IF NOT EXISTS `chat_log` (
    `log_id`     INT(11)                         NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)                         NOT NULL,
    `sender`     ENUM('user', 'ai')              NOT NULL,
    `message`    TEXT                            NOT NULL,
    `created_at` TIMESTAMP                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_chat_log_user_id`   (`user_id`),
    KEY `idx_chat_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

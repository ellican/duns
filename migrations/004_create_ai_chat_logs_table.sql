-- Migration: 004 - Create AI Chat Logs Table
-- Purpose: Track all AI assistant interactions for security and auditing
-- Date: 2025-10-20

-- Create ai_chat_logs table
CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `session_id` VARCHAR(255) NOT NULL,
  `user_query` TEXT NOT NULL,
  `ai_response` TEXT,
  `sql_executed` TEXT,
  `sql_result_count` INT DEFAULT 0,
  `execution_time_ms` INT DEFAULT 0,
  `status` ENUM('success', 'error', 'blocked') DEFAULT 'success',
  `error_message` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_ai_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create index for faster session-based queries
CREATE INDEX `idx_user_session` ON `ai_chat_logs` (`user_id`, `session_id`, `created_at`);

-- Sadhana feature tables
-- Created for: 020-sadhana-tracking

-- Table: sadhana_cards (practice cards)
CREATE TABLE IF NOT EXISTS `sadhana_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('COUNT','DURATION') NOT NULL DEFAULT 'COUNT',
  `target_value` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(50) DEFAULT '',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_archived` (`user_id`, `is_archived`),
  CONSTRAINT `fk_sadhana_cards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sadhana_daily_logs (daily practice logs)
CREATE TABLE IF NOT EXISTS `sadhana_daily_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `actual_value` int(11) NOT NULL DEFAULT 0,
  `books` text DEFAULT NULL COMMENT 'JSON array of book names',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_card_date` (`user_id`, `card_id`, `date`),
  KEY `idx_user_date` (`user_id`, `date`),
  CONSTRAINT `fk_sadhana_logs_card` FOREIGN KEY (`card_id`) REFERENCES `sadhana_cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sadhana_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sadhana_sleep_logs (sleep tracking)
CREATE TABLE IF NOT EXISTS `sadhana_sleep_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `bed_time` time NOT NULL,
  `wake_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_sleep_date` (`user_id`, `date`),
  KEY `idx_user_date` (`user_id`, `date`),
  CONSTRAINT `fk_sadhana_sleep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

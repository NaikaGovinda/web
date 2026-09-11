-- Таблица уведомлений
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'application_approved, application_rejected, chat_message, group_removed, etc.',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `group_id` int(11) DEFAULT NULL COMMENT 'ID группы для группировки уведомлений',
  `target_page` varchar(100) DEFAULT NULL COMMENT 'Целевая страница для навигации (group.html, profile.html)',
  `target_params` text DEFAULT NULL COMMENT 'JSON параметры для навигации',
  `read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`read`),
  KEY `idx_created` (`created_at`),
  KEY `idx_group` (`group_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Миграция для существующих таблиц (если таблица уже есть)
-- ALTER TABLE `notifications`
-- ADD COLUMN `group_id` int(11) DEFAULT NULL AFTER `message`,
-- ADD COLUMN `target_page` varchar(100) DEFAULT NULL AFTER `group_id`,
-- ADD COLUMN `target_params` text DEFAULT NULL AFTER `target_page`;

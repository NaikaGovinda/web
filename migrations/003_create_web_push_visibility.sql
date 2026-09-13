-- ============================================
-- Таблица видимости страниц для Web Push
-- Используется для подавления уведомлений, 
-- когда пользователь уже смотрит страницу
-- ============================================

CREATE TABLE IF NOT EXISTS `user_web_push_visibility` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `page` VARCHAR(100) NOT NULL COMMENT 'Имя страницы: group.html, profile.html, etc.',
  `group_id` INT(11) DEFAULT NULL COMMENT 'ID группы (если применимо)',
  `is_visible` TINYINT(1) DEFAULT 1 COMMENT 'Видима ли страница (1=видима, 0=скрыта)',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_page` (`user_id`, `page`, `group_id`),
  KEY `idx_user_visible` (`user_id`, `is_visible`),
  CONSTRAINT `fk_visibility_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

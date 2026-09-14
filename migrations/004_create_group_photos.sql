-- Миграция: создание таблицы для фото группы
-- Дата: 2026-09-13

CREATE TABLE IF NOT EXISTS `group_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `photo_url` varchar(1024) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_photos_group` (`group_id`),
  CONSTRAINT `fk_group_photos_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

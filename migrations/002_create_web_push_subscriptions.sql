-- ============================================
-- Таблица подписок на Web Push уведомления
-- ============================================

CREATE TABLE IF NOT EXISTS `user_web_push_subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `endpoint` TEXT NOT NULL COMMENT 'URL push-сервера (FCM endpoint)',
  `p256dh` TEXT NOT NULL COMMENT 'P-256DH ключ подписки',
  `auth` TEXT NOT NULL COMMENT 'Auth secret ключа подписки',
  `browser` VARCHAR(50) DEFAULT NULL COMMENT 'Браузер: chrome, firefox, safari, edge',
  `active` TINYINT(1) DEFAULT 1 COMMENT 'Флаг активности подписки',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_endpoint` (`endpoint`(255)),
  KEY `idx_user_active` (`user_id`, `active`),
  CONSTRAINT `fk_web_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индекс для быстрого поиска активных подписок по пользователю
CREATE INDEX IF NOT EXISTS `idx_user_web_push` ON `user_web_push_subscriptions` (`user_id`, `active`);

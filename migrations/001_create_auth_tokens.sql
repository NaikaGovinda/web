-- ==========================================
-- МИГРАЦИЯ: Добавление таблицы auth_tokens
-- ==========================================
-- Выполните этот SQL в вашей базе данных!

CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- CRON JOB (опционально)
-- ==========================================
-- Запускать раз в час для очистки истёкших токенов:
-- php /path/to/api/cleanup_tokens.php
-- ==========================================

-- ==========================================
-- ПРОВЕРКА (для тестирования)
-- ==========================================
-- Посмотреть активные токены:
-- SELECT u.email, at.token_hash, at.expires_at, at.created_at
-- FROM auth_tokens at
-- JOIN users u ON at.user_id = u.id
-- WHERE at.expires_at > NOW()
-- ORDER BY at.created_at DESC;

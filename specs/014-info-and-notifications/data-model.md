# Модель данных: Инфо и Уведомления (014-info-and-notifications)

## 1. Схема Таблицы Уведомлений (SQL)

```sql
-- Таблица истории персональных уведомлений пользователя
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL, -- 'approved', 'rejected', 'message', 'event'
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `target_url` VARCHAR(255) DEFAULT NULL, -- Ссылка для перехода при клике
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация API Контрактов

### А. Список уведомлений (GET /api/get_notifications.php?userId=1)
**Ответ (JSON):**
```json
[
  {
    "id": 1,
    "type": "message",
    "title": "Новое сообщение",
    "text": "Иван написал: Харе Кришна!",
    "isRead": false,
    "time": "10:15"
  }
]
```

### Б. Контент Инфо-центра (GET /api/info_handler.php)
**Ответ (JSON):**
```json
{
  "articles": [
    {
      "id": "rules",
      "title": "Правила Нама-Хатты",
      "content": "Полный текст правил..."
    },
    {
      "id": "faq",
      "title": "Часто задаваемые вопросы",
      "content": "..."
    }
  ]
}
```
---

## 3. UI Модель Уведомления
- **Иконка:** Мапится по полю `type`.
- **Цвет:** Синий для сообщений, Зеленый для одобрений, Красный для отказов.

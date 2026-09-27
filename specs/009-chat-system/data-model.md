# Модель данных: Система чата (009-chat-system)

## 1. Схема Таблицы Сообщений (SQL)

```sql
-- Таблица истории сообщений в группах
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация API Контрактов

### А. Отправка сообщения (POST /api/send_message.php)
**Запрос (JSON):**
```json
{
  "userId": 1,
  "groupId": 125,
  "text": "Харе Кришна, дорогие преданные!"
}
```

### Б. Получение новых сообщений (GET /api/get_messages.php)
**Параметры:** `groupId`, `afterId` (опционально, для получения только новых).

**Ответ (JSON Массив):**
```json
[
  {
    "id": 101,
    "userId": 1,
    "userName": "Иван Иванов",
    "avatarUrl": "https://namahatta.ru/assets/avatars/u1.jpg",
    "text": "Харе Кришна!",
    "time": "18:45"
  }
]
```
---

## 3. UI Модель Сообщения
- `isOwn`: Boolean (свой/чужой) — для выравнивания в чате.
- `status`: String (sending, sent, error).

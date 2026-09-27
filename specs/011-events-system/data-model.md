# Модель данных: Система мероприятий (011-events-system)

## 1. Схема Таблиц (SQL)

```sql
-- Таблица мероприятий групп
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `event_date` DATETIME NOT NULL,
  `location` VARCHAR(255) DEFAULT 'Онлайн',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица участников мероприятий
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_event_user` (`event_id`, `user_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация API Контрактов

### А. Список мероприятий (GET /api/get_events.php?userId=1)
**Ответ (JSON):**
```json
[
  {
    "id": 1,
    "groupName": "Бхакти-врикша Центр",
    "title": "Изучение Бхагавад-Гиты",
    "date": "2026-04-01 18:30:00",
    "location": "ул. Мира, 10",
    "participantsCount": 12,
    "isJoined": true
  }
]
```

### Б. Создание мероприятия (POST /api/create_event.php)
**Запрос (JSON):**
```json
{
  "groupId": 1,
  "title": "Воскресная программа",
  "description": "Киртан и лекция",
  "date": "2026-04-05 12:00:00",
  "location": "Центральный парк"
}
```

### В. Запись на мероприятие (POST /api/join_event.php)
**Запрос (JSON):** `{"eventId": 1, "userId": 42}`

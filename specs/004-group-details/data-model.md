# Модель данных: Нативный экран деталей группы и REST API (004-group-details)

## 1. Реляционная схема Базы Данных (MySQL Schema)

Для выдачи полной информации о группе бэкенд использует реляционную структуру из 3 связанных таблиц:

```sql
-- А. Таблица Групп (groups)
CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `city` VARCHAR(100) NOT NULL,
  `leader_id` INT NOT NULL, -- Внешний ключ на users.id (Лидер группы)
  FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Б. Таблица Участников Группы (group_members)
CREATE TABLE IF NOT EXISTS `group_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` VARCHAR(50) DEFAULT 'участник', -- роль: участник, помощник, стажер
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- В. Таблица Фотографий Группы (group_photos)
CREATE TABLE IF NOT EXISTS `group_photos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `photo_url` VARCHAR(255) NOT NULL, -- Прямая публичная ссылка на изображение
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация JSON Пакета Данных Группы

### Эндпоинт: `GET /api/get_group_details.php?id={groupId}`

#### Формат ответа сервера (HTTP 200 OK — JSON):
```json
{
  "id": 1,
  "name": "Бхакти-врикша Центр",
  "description": "Еженедельные встречи, изучение философии, обсуждение духовных тем.",
  "city": "Москва",
  "leader": {
    "id": 1,
    "name": "Иван Иванов",
    "avatarUrl": "https://namahatta.ru/assets/avatars/leader1.jpg"
  },
  "photos": [
    "https://namahatta.ru/assets/photos/group1_1.jpg",
    "https://namahatta.ru/assets/photos/group1_2.jpg"
  ],
  "members": [
    {"id": 2, "name": "Алексей Петров", "role": "помощник"},
    {"id": 3, "name": "Сергей Сидоров", "role": "участник"},
    {"id": 4, "name": "Елена Кузнецова", "role": "участник"}
  ]
}
```
---

## 3. Нативная Модель Состояний Экрана (GroupDetailsUIState)

В Android-приложении состояние экрана типизируется запечатанным интерфейсом:

```kotlin
sealed interface GroupDetailsUIState {
    object Loading : GroupDetailsUIState
    data class Success(val groupData: String) : GroupDetailsUIState // Хранит распарсенную модель данных группы
    data class Error(val message: String) : GroupDetailsUIState
}
```

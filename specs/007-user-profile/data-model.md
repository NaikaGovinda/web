# Модель данных: Профиль пользователя (007-user-profile)

## 1. Схема Базы Данных (SQL)

```sql
-- Расширение таблицы пользователей под требования фичи 007 (FR-703)
ALTER TABLE `users` 
ADD COLUMN `spiritual_name` VARCHAR(150) DEFAULT NULL AFTER `name`,
ADD COLUMN `avatar_url` VARCHAR(255) DEFAULT 'assets/avatars/default.jpg' AFTER `spiritual_name`;
```

---

## 2. Спецификация API Контрактов

### А. Получение профиля (GET /api/get_profile.php?userId=1)
**Ответ (JSON):**
```json
{
  "id": 1,
  "name": "Иван Иванов",
  "spiritual_name": "Ишвара Дас",
  "email": "ivan@example.com",
  "avatarUrl": "https://namahatta.ru/assets/avatars/u1.jpg"
}
```

### Б. Обновление профиля (POST /api/update_profile.php)
**Запрос (JSON):**
```json
{
  "userId": 1,
  "name": "Иван Иванов",
  "spiritual_name": "Ишвара Дас",
  "avatarBase64": "data:image/jpeg;base64,...", 
  "deleteAvatar": false
}
```
**Успешный ответ (JSON):**
```json
{
  "status": "success",
  "newAvatarUrl": "https://namahatta.ru/assets/avatars/u1_171128.jpg"
}
```

---

## 3. Модель валидации Фронтенда
- `name`: String (not empty, max 100 chars).
- `spiritual_name`: String (optional, max 150 chars).
- `avatarBase64`: Валидная строка Data-URI.

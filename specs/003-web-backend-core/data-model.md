# Модель данных: Веб-ядро и Бэкенд API (003-web-backend-core)

## 1. Структура Таблиц Базы Данных (MySQL SQL Schema)

Для поддержки работы системы push-увеложений, спроектированной в фиче 002, серверная таблица пользователей расширяется полем для хранения токенов устройств. Один пользователь может иметь актуальный токен сессии.

```sql
-- Схема таблицы пользователей (users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `fcm_token` VARCHAR(255) DEFAULT NULL, -- Хранит актуальный пуш-токен устройства (Критерий FR-303)
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Спецификация REST API Контракта

### Эндпоинт: `POST /api/update_user_fcm_token.php`

Вызывается фронтенд-скриптом из WebView контейнера для синхронизации токенов (Критерий `FR-302`).

#### Входящие параметры запроса (Content-Type: application/json):
```json
{
  "userId": "42",
  "fcm_token": "fcm_token_string_received_from_firebase_messaging_service_container"
}
```

#### Успешный ответ сервера (HTTP 200 OK — JSON):
```json
{
  "status": "success",
  "message": "Token successfully synchronized"
}
```

#### Ответ при ошибке валидации/сессии (HTTP 400 Bad Request / 401 Unauthorized — JSON):
```json
{
  "status": "error",
  "message": "Invalid session or missing mandatory parameters"
}
```

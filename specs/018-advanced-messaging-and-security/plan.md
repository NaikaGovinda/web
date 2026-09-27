# Технический план: Расширенные сообщения и Безопасность (018-advanced-messaging-and-security)

## 1. Архитектура защиты

Защита реализуется на уровне PHP Middleware, который подключается в начале каждого API-запроса.

### Бэкенд (PHP 8.1+)
- **Rate Limiter:** Использование файлового кэша или СУБД для хранения счетчиков по IP.
- **Firewall:** Проверка `User-Agent` и блокировка SQL-инъекций в GET/POST параметрах.
- **Сообщения:** Добавление поля `is_edited` и `updated_at` в таблицу `messages`.

### СУБД (MySQL)
```sql
ALTER TABLE `messages` 
ADD COLUMN `is_edited` TINYINT(1) DEFAULT 0,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

## 2. Фазы реализации

### Фаза 1: СУБД и Безопасность
- Модификация таблицы `messages`.
- Реализация `backend/api/rate_limiter.php` и `backend/api/firewall.php`.

### Фаза 2: API Сообщений
- Реализация `update_message.php` и `delete_message.php` с проверкой прав (автор или админ).

### Фаза 3: Админ-функции
- Реализация `admin_assign_leader.php`.

### Фаза 4: Верификация
- Тест на брутфорс (Rate Limiting).
- Проверка удаления сообщений.

# Модель данных: Напоминания (012-event-reminders)

## 1. Схема Базы Данных (SQL)

Для предотвращения дублирования уведомлений расширяем таблицу участников мероприятия.

```sql
-- Расширение таблицы участников мероприятий (FR-1202)
ALTER TABLE `event_participants` 
ADD COLUMN `reminder_sent` TINYINT(1) DEFAULT 0 AFTER `user_id`;

-- Индекс для ускорения Cron-задачи
CREATE INDEX idx_reminder_status ON `event_participants` (`reminder_sent`);
```

---

## 2. Формат Push Payload для напоминания

Сервер отправляет данные в формате, совместимом с нашей нативной службой (Фича 002).

```json
{
  "to": "/topics/user_42",
  "data": {
    "title": "Скоро начнется встреча!",
    "body": "Программа 'Воскресный пир' начнется ровно через час.",
    "targetUrl": "https://namahatta.ru/events.html",
    "chatId": "event_reminder"
  }
}
```
---

## 3. SQL Запрос выборки участников для напоминания

```sql
SELECT 
    ep.id as participant_id,
    u.fcm_token,
    e.title,
    e.event_date
FROM `event_participants` ep
JOIN `events` e ON ep.event_id = e.id
JOIN `users` u ON ep.user_id = u.id
WHERE ep.reminder_sent = 0 
  AND u.fcm_token IS NOT NULL
  AND e.event_date BETWEEN NOW() + INTERVAL 55 MINUTE AND NOW() + INTERVAL 65 MINUTE;
```

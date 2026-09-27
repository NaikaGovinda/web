# Руководство по быстрой проверке Мероприятий (quickstart.md)

Методы верификации планировщика событий.

---

## Тест-кейс 1: Создание и получение события (Критерий SC-1302)

### Шаг 1: Создание через API
Выполните POST через curl:
```bash
curl -X POST http://localhost/api/create_event.php \
     -H "Content-Type: application/json" \
     -d '{"groupId": 1, "title": "Тестовая встреча", "date": "2026-12-31 18:00:00", "location": "Москва"}'
```

### Шаг 2: Получение списка участником
Выполните GET запрос:
```bash
curl -X GET http://localhost/api/get_events.php?userId=1
```
- **Ожидаемый результат:** В массиве должен присутствовать объект с заголовком "Тестовая встреча". Время отклика должно быть менее 300 мс.

---

## Тест-кейс 2: Запись на мероприятие

### Шаг 1: Выполнение записи
```bash
curl -X POST http://localhost/api/join_event.php \
     -H "Content-Type: application/json" \
     -d '{"eventId": 1, "userId": 1}'
```

### Шаг 2: Проверка счетчика
Повторно выполните GET запрос `get_events.php`.
- **Ожидаемый результат:** Поле `participantsCount` для события №1 увеличилось на 1, а `isJoined` стало `true`.

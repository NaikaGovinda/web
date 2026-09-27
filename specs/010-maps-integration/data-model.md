# Модель данных: Интеграция Карт (010-maps-integration)

## 1. Схема Базы Данных (SQL)

Для отображения меток на карте необходимо расширить таблицу групп географическими координатами.

```sql
-- Расширение таблицы групп под требования фичи 010 (FR-1003)
ALTER TABLE `groups` 
ADD COLUMN `latitude` DECIMAL(10, 8) DEFAULT NULL AFTER `city`,
ADD COLUMN `longitude` DECIMAL(11, 8) DEFAULT NULL AFTER `latitude`;

-- Обновление тестовых данных для карты
UPDATE `groups` SET `latitude` = 55.751244, `longitude` = 37.618423 WHERE `id` = 1; -- Москва, Центр
```

---

## 2. Спецификация API Контракта

### Эндпоинт: `GET /api/get_groups_coords.php`

Возвращает список всех групп с их координатами для рендеринга на карте.

#### Формат ответа сервера (JSON):
```json
[
  {
    "id": 1,
    "name": "Бхакти-врикша Центр",
    "leaderName": "Иван Иванов",
    "lat": 55.751244,
    "lng": 37.618423
  },
  {
    "id": 2,
    "name": "Группа Север",
    "leaderName": "Алексей Петров",
    "lat": 55.801200,
    "lng": 37.550000
  }
]
```

---

## 3. Модель Балуна (Frontend)
- `title`: `name` группы.
- `body`: "Лидер: `leaderName`".
- `footer`: Кнопка `<button onclick="openNativeGroupDetails(id)">Подробнее</button>`.

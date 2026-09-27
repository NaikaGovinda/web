# Список задач реализации: Фильтр по городам (013-city-filter)

Чек-лист для разработки глобальной системы поиска и фильтрации.

---

## Фаза 1: Бэкенд и API

- [ ] **TSK-1311 (API: Список городов):** Создать `backend/api/get_cities.php`. Реализовать SELECT DISTINCT по полю city.
  - **Файлы:** `backend/api/get_cities.php`

- [ ] **TSK-1312 (API: Фильтрация):** Обновить `get_groups_coords.php` и `get_events.php`, добавив обработку параметра `city`.
  - **Файлы:** `backend/api/get_groups_coords.php`, `backend/api/get_events.php`

---

## Фаза 2: Веб-Интерфейс

- [ ] **TSK-1321 (JS: Компонент выбора):** Написать функцию инициализации фильтра городов, загрузки списка из API и сохранения выбора в `localStorage`.
  - **Файлы:** `backend/js/android-hybrid-core.js`

- [ ] **TSK-1322 (HTML: Интеграция в UI):** Добавить селектор города в шапку страниц `index.html`, `map.html` и `events.html`.
  - **Файлы:** `backend/index.html`, `backend/map.html`, `backend/events.html`

---

## Фаза 3: Верификация

- [ ] **TSK-1331 (Тестирование):** Проверить сквозную работу фильтра: Карта -> Главная -> Мероприятия. Убедиться, что город сохраняется корректно.

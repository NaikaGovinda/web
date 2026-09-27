# Список задач реализации: Экран участников группы (008-group-members)

Этот документ содержит пошаговый атомарный план разработки нативного экрана участников и серверного API, увязанный с функциональными требованиями (`FR`) и критериями измеряемого успеха (`SC`).

---

## Фаза 1: Разработка Серверного REST API

- [ ] **TSK-181 (API Списка участников):** Написать серверный PHP-скрипт `backend/api/get_group_members.php`. Реализовать безопасный SQL запрос с JOIN к таблицам `group_members` и `users` для получения расширенных данных: `name`, `spiritual_name`, `role`, `avatar_url`.
  - **Файлы:** `backend/api/get_group_members.php`
  - **Критерий:** Эндпоинт возвращает валидный массив участников в формате JSON.

---

## Фаза 2: Инфраструктура Android и Модель данных

- [ ] **TSK-281 (Регистрация активности):** Создать файл `GroupMembersActivity.kt` и зарегистрировать его в `AndroidManifest.xml`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/GroupMembersActivity.kt`, `android/app/src/main/AndroidManifest.xml`
  - **Критерий:** Активность успешно запускается через Интент.

- [ ] **TSK-282 (ViewModel и Логика фильтрации):** Реализовать `MembersViewModel`. Добавить загрузку данных из API и логику мгновенной фильтрации списка в памяти по двум полям (`name`, `spiritual_name`).
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/ui/MembersViewModel.kt`
  - **Связь с требованиями:** `SC-1001`, `SC-1003`.

---

## Фаза 3: Разработка Jetpack Compose UI

- [ ] **TSK-381 (Верстка Поисковой строки):** Реализовать компонент `SearchBar` в верхней части экрана, связанный с поисковым запросом в ViewModel.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/GroupMembersActivity.kt`
  - **Связь с требованиями:** `FR-802`.

- [ ] **TSK-382 (Верстка Списка участников):** Создать `LazyColumn` для отображения карточек участников. Добавить иконки ролей (лидер, помощник) и асинхронную загрузку аватаров через Coil.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/GroupMembersActivity.kt`
  - **Связь с требованиями:** `FR-801`, `FR-804`, `SC-1002`.

---

## Фаза 4: Верификация

- [ ] **TSK-481 (Сквозное тестирование):** Провести проверку по гайду `quickstart.md`. Убедиться, что поиск работает мгновенно и API отдает корректные данные ролей.
  - **Файлы:** `backend/api/get_group_members.php`, `android/app/src/main/java/com/example/namahatta/GroupMembersActivity.kt`

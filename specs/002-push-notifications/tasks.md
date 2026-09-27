# Список задач реализации: Система Push-уведомлений и FCM (002-push-notifications)

Этот документ содержит пошаговый атомарный чек-лист задач для интеграции push-уведомлений, увязанный с функциональными требованиями (`FR`) и критериями измеряемого успеха (`SC`).

---

## Фаза 1: Подключение Firebase и обновление конфигурации сборки

- [ ] **TSK-111 (Интеграция Google Services в Gradle):** Модифицировать корневой файл `android/settings.gradle.kts` для подключения gradle-плагина Google Services. Добавить плагин и зависимость `firebase-messaging-ktx` в модульный `android/app/build.gradle.kts` в соответствии с `plan.md`.
  - **Файлы:** `android/settings.gradle.kts`, `android/app/build.gradle.kts`
  - **Критерий:** Проект успешно синхронизируется с Gradle-артефактами Firebase.

- [ ] **TSK-112 (Обновление Манифеста для FCM):** Декларировать фоновую службу `MyFirebaseMessagingService` в `android/app/src/main/AndroidManifest.xml`. Добавить метаданные для установки дефолтной иконки и имени канала уведомлений по умолчанию.
  - **Файлы:** `android/app/src/main/AndroidManifest.xml`
  - **Критерий:** Манифест успешно валидируется компилятором.

---

## Фаза 2: Доработка архитектуры стейта и JavaScript-моста

- [ ] **TSK-211 (Расширение стейта activeChatId):** Добавить в `MainViewModel` переменную `activeChatId: StateFlow<String?>` и методы для её обновления, обеспечивая фильтрацию входящих трансляций пушей.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/ui/MainViewModel.kt`
  - **Связь с требованиями:** `FR-201`, `SC-402` (Умное подавление).

- [ ] **TSK-212 (Обновление WebAppInterface контракта):** Расширить метод `setActiveChat(json)` в классе `WebAppInterface`, чтобы он парсил JSON контекста чата и передавал `activeChatId` во `MainViewModel`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/bridge/WebAppInterface.kt`
  - **Связь с требованиями:** `FR-202`, `push-bridge-contract.md`.

---

## Фаза 3: Реализация нативной службы MyFirebaseMessagingService

- [ ] **TSK-311 (Создание фоновой службы и генерация токенов):** Реализовать класс `MyFirebaseMessagingService`, унаследованный от `FirebaseMessagingService`. Прописать логику метода `onNewToken(token)`: сохранение токена в локальные настройки приложения и вызов асинхронной отправки токена через интерфейс моста `window.onFcmTokenUpdated`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/services/MyFirebaseMessagingService.kt`
  - **Связь с требованиями:** `FR-201`, `FR-202`, `SC-403`.

- [ ] **TSK-312 (Перехват Payload сообщений и фильтрация):** Реализовать метод `onMessageReceived(remoteMessage)`. Добавить парсинг полей `title`, `body`, `chatId`, `targetUrl` из блока данных `data`. Прописать условие: если `chatId` из пуша совпадает с `MainViewModel.activeChatId`, прервать выполнение и подавить пуш (выполнить логирование). В противном случае — передать пакет в системный менеджер уведомлений.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/services/MyFirebaseMessagingService.kt`
  - **Связь с требованиями:** `FR-201`, `FR-203`, `SC-402`.

---

## Фаза 4: Создание Системных Каналов и Логики Маршрутизации

- [ ] **TSK-411 (Реализация Менеджера Уведомлений):** Написать метод генерации системного всплывающего баннера через `NotificationCompat.Builder`. Настроить `NotificationChannel` с приоритетом `IMPORTANCE_HIGH`. Сформировать `PendingIntent`, инкапсулирующий `targetUrl`, направленный на запуск `MainActivity`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/services/NotificationDisplayManager.kt`
  - **Связь с требованиями:** `FR-201`, `FR-203`.

- [ ] **TSK-412 (Перехват клика пуша в MainActivity):** Модифицировать методы `onCreate` и `onNewIntent` в `MainActivity.kt`. Добавить извлечение строки `targetUrl` из интента пуша. Если `targetUrl` присутствует, вызвать `rootWebView?.loadUrl(targetUrl)` для мгновенного перехода пользователя в нужный раздел.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/MainActivity.kt`
  - **Связь с требованиями:** `FR-203`, `SC-401` (Скорость перехода < 300мс).

---

## Фаза 5: Реализация Запроса прав и Сквозное тестирование

- [ ] **TSK-511 (Runtime Запрос прав в Compose):** Добавить в `MainActivity` декларативную проверку и запрос runtime-разрешения `android.permission.POST_NOTIFICATIONS` для корректной работы пушей на Android 13+ при первом старте. Провести ручную отладку сценариев подавления и глубоких переходов по URL через adb-команды гайда `quickstart.md`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/MainActivity.kt`
  - **Связь с требованиями:** `FR-204`, `SC-401`, `SC-402`.

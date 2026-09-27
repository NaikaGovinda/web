# Список задач реализации: Гибридное ядро приложения (001-core-hybrid-shell)

Этот документ содержит пошаговый атомарный план разработки ядра. Каждая задача привязана к функциональным требованиям (`FR`) и измеряемым критериям успеха (`SC`), описанным в спецификации.

---

## Фаза 1: Инфраструктурная настройка (Setup)

- [ ] **TSK-101 (Настройка сборки Android):** Создать корневой файл настроек Gradle `android/settings.gradle.kts` и файл сборки модуля приложения `android/app/build.gradle.kts`. Подключить плагины Kotlin, Android Application и зависимости Jetpack Compose, WebKit, Activity Compose.
  - **Файлы:** `android/settings.gradle.kts`, `android/app/build.gradle.kts`
  - **Критерий:** Проект синхронизируется в IDE без ошибок.

- [ ] **TSK-102 (Конфигурация Манифеста):** Создать `android/app/src/main/AndroidManifest.xml`. Декларировать разрешения `android.permission.INTERNET`, настроить `HardwareAcceleration="true"` для корректного рендеринга WebView и зарегистрировать `MainActivity` как точку входа.
  - **Файлы:** `android/app/src/main/AndroidManifest.xml`
  - **Критерий:** Проект собирается в пустой APK.

---

## Фаза 2: Реализация экранов состояний и контейнера UI

- [ ] **TSK-201 (Модель нативного стейта UI):** Создать запечатанный интерфейс `AppUIState` и ViewModel для управления состояниями (Splash, Active WebView, Offline Error).
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/ui/AppUIState.kt`, `android/app/src/main/java/com/example/namahatta/core/ui/MainViewModel.kt`
  - **Связь с требованиями:** Поддержка логики состояний под требования спецификации.

- [ ] **TSK-202 (Компоненты Compose UI):** Реализовать нативные Compose-компоненты для экрана загрузки (`SplashLoadingScreen`) и экрана ошибки сети (`OfflineErrorScreen`) с кнопкой «Повторить попытку».
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/ui/components/SplashScreen.kt`, `android/app/src/main/java/com/example/namahatta/core/ui/components/OfflineScreen.kt`
  - **Связь с требованиями:** `FR-101`, `SC-301` (Отображение загрузки < 500мс), `SC-302` (Перехват системных ошибок).

---

## Фаза 3: Реализация WebView Container и логики перехвата навигации

- [ ] **TSK-301 (Интеграция WebView в Compose):** Создать Compose-компонент `HybridWebViewContainer`, который оборачивает `android.webkit.WebView` через `AndroidView`. Сконфигурировать `WebSettings`: включить `javaScriptEnabled`, отключить `allowFileAccess`, скрыть дефолтные элементы зума. Настроить кастомный `WebViewClient` для перехвата сетевых ошибок (`onReceivedError`) и переключения стейта в `OfflineError`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/ui/components/HybridWebViewContainer.kt`
  - **Связь с требованиями:** `FR-101`, `SC-302`.

- [ ] **TSK-302 (Управление кнопкой Назад):** Внедрить `OnBackPressedDispatcher` в `MainActivity`. Перехватывать нажатие кнопки «Назад», вызывать метод проверки истории в WebView. Если история переходов есть — выполнять `webView.goBack()`, если нет — сворачивать приложение.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/MainActivity.kt`
  - **Связь с требованиями:** `FR-104`.

---

## Фаза 4: Реализация JavaScript-моста и интеграция с Галереей

- [ ] **TSK-401 (Реализация класса WebAppInterface):** Создать класс `WebAppInterface` с аннотациями `@JavascriptInterface`. Реализовать методы `setUser(json)`, `setActiveChat(json)` и `openGallery()`. Настроить потокобезопасную передачу данных из фонового потока WebView в главный UI-поток приложения.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/core/bridge/WebAppInterface.kt`
  - **Связь с требованиями:** `FR-102`, `SC-303`.

- [ ] **TSK-402 (Интеграция Системного PhotoPicker):** Связать метод `openGallery()` интерфейса моста с вызовом `ActivityResultContracts.PickVisualMedia` в `MainActivity`. Реализовать чтение выбранного URI изображения, конвертацию его в Base64-строку и асинхронную отправку обратно в WebView через `webView.evaluateJavascript("window.onImageSelected(...)")`.
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/MainActivity.kt`, `android/app/src/main/java/com/example/namahatta/core/bridge/ImageSelectorHandler.kt`
  - **Связь с требованиями:** `FR-103`, `SC-303`.

---

## Фаза 5: Сборка, Сквозная верификация и Финализация

- [ ] **TSK-501 (Координация экранов в MainActivity):** Собрать все компоненты в единый граф состояний в `MainActivity`. Запустить приложение и выполнить сквозную ручную верификацию по сценариям из `quickstart.md` (Проверка таймаутов, имитация режима полета, вызов галереи).
  - **Файлы:** `android/app/src/main/java/com/example/namahatta/MainActivity.kt`
  - **Связь с требованиями:** `SC-301`, `SC-302`, `SC-303`.

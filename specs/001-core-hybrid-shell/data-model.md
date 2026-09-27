# Модель данных: Гибридное ядро приложения (001-core-hybrid-shell)

Документ описывает внутренние состояния нативного контейнера и структуры данных, циркулирующие между Android-оболочкой и веб-окружением.

## 1. Состояния нативного приложения (AppUIState)

Нативный контейнер управляет своим состоянием с помощью перечисления или запечатанного класса (Sealed Interface) для управления отображением Compose-компонентов:

```kotlin
sealed interface AppUIState {
    object SplashLoading : AppUIState      // Первоначальный запуск, показ сплэш-скрина
    object WebViewActive : AppUIState      // Веб-контент успешно загружен и активен
    data class OfflineError(               // Сетевая ошибка или таймаут загрузки страницы
        val errorCode: Int,
        val failingUrl: String
    ) : AppUIState
}
```

---

## 2. Структуры данных моста взаимодействия

Данные, передаваемые между JavaScript и Android, кодируются в формат JSON со строго определенной структурой.

### А. Структура авторизационной сессии (UserSession)
Передается из веб-среды в Android для хранения токена пуш-уведомлений и привязки сессии к устройству на низком уровне.

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "UserSession",
  "type": "object",
  "properties": {
    "userId": {
      "type": "string",
      "description": "Уникальный идентификатор пользователя в веб-системе"
    },
    "authToken": {
      "type": "string",
      "description": "JWT или сессионный токен для авторизации API-запросов"
    }
  },
  "required": ["userId", "authToken"]
}
```

### Б. Структура контекста чата (ChatContext)
Передается из веб-среды в Android при переходе пользователя в конкретный чат, чтобы нативный контейнер знал, куда перенаправлять входящие пуш-уведомления или в каком контексте выполняются системные операции.

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "ChatContext",
  "type": "object",
  "properties": {
    "activeChatId": {
      "type": ["string", "null"],
      "description": "ID открытого чата или null, если пользователь вышел из чата в общее меню"
    }
  },
  "required": ["activeChatId"]
}
```

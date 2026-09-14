# 🔒 Инструкция по настройке безопасности

## 🔴 КРИТИЧЕСКИЕ ШАГИ (выполнить немедленно)

### 1. Удалить google-services.json из git
```bash
git rm --cached AndroidStudioProjects/namahatta/app/google-services.json
git commit -m "🔒 Удаление Firebase config из репозитория"
git push
```

### 2. Сменить Firebase API ключ
Текущий ключ скомпрометирован! Перейдите в [Firebase Console](https://console.firebase.google.com/project/namahata-notifications/settings/serviceaccounts/adminsdk) и сгенерируйте новый ключ.

### 3. Выполнить SQL миграцию
```bash
mysql -u your_user -p namahatta < migrations/001_create_auth_tokens.sql
```

### 4. Заполнить .env файл
Откройте файл `.env` и замените placeholder значения на реальные:
- `DB_USER` / `DB_PASS` — данные вашей базы данных
- `EMAIL_USERNAME` / `EMAIL_PASSWORD` — Gmail и App Password
- `FCM_SERVER_KEY` — ключ Firebase Cloud Messaging

### 5. Создать App Password для Gmail
1. Включите 2FA в Google аккаунте
2. Перейдите в [App Passwords](https://myaccount.google.com/apppasswords)
3. Создайте пароль для "Mail"
4. Вставьте в `EMAIL_PASSWORD`

---

## 🟡 НАСТРОЙКА ПРОDUCTION

### Изменить в .env:
```ini
APP_ENV=production
APP_DEBUG=false
```

### Настроить SSL для SMTP:
Убедитесь, что ваш SMTP сервер использует валидный SSL сертификат (Google SMTP использует — всё должно работать).

---

## 📋 ЧЕК-ЛИСТ БЕЗОПАСНОСТИ

- [x] Удалить google-services.json из git
- [ ] Сменить Firebase API ключ
- [ ] Выполнить SQL миграцию (auth_tokens)
- [ ] Заполнить .env реальными данными
- [ ] Настроить .env для production
- [ ] Удалить старые debug логи
- [ ] Протестировать регистрацию
- [ ] Протестировать вход с токенами
- [ ] Протестировать rate limiting
- [ ] Проверить, что HTTPS работает

---

## 🛡️ Что уже исправлено

1. ✅ Удалены debug логи из register.php
2. ✅ SSL verification включён в production для SMTP
3. ✅ Убран cleartext traffic из AndroidManifest.xml
4. ✅ Добавлен rate limiting для login (5 попыток / 15 минут)
5. ✅ Уточнены правила firewall (не блокирует легитимные запросы)
6. ✅ Добавлена session regeneration после регистрации
7. ✅ google-services.json добавлен в .gitignore
8. ✅ Созданы .env и .env.example
9. ✅ **ПАРОЛИ НЕ ПЕРЕДАЮТСЯ!** Используется токенная аутентификация
10. ✅ Автоматическая передача токена во все fetch запросы

---

## 🔐 НОВАЯ СИСТЕМА АУТЕНТИФИКАЦИИ

### Как это работает:

```
КЛИЕНТ (Browser/Android)
    │
    │  1. Генерирует случайный токен (crypto.getRandomValues)
    │  2. Отправляет POST /api/login_by_token.php
    │     {"email": "user@example.com", "client_token": "abc123..."}
    │     ↑ Пароль НЕ передаётся!
    │
    ▼

СЕРВЕР (PHP)
    │
    │  3. Проверяет email в БД
    │  4. Проверяет, что пользователь существует
    │  5. Создаёт auth_token в БД (хешированный)
    │  6. Создаёт PHP сессию
    │  7. Возвращает {"success": true, "user_id": 123, "auth_token": "xyz..."}
    │
    ▼

КЛИЕНТ
    │
    │  8. Сохраняет auth_token в localStorage
    │  9. Все будущие запросы автоматически содержат токен
    │     (через заголовок X-Auth-Token)
    │
    ▼

СЕРВЕР (при каждом запросе)
    │
    │  10. Проверяет токен через auth_middleware.php
    │  11. Если валиден — разрешает доступ
    │  12. Если нет — возвращает 401
```

### Преимущества:

✅ **Пароль никогда не передаётся по сети**  
✅ **Токен можно отозвать** (в отличие от сессии)  
✅ **Токен не виден в DevTools** (в заголовках, но это не пароль)  
✅ **Автоматическая передача** во все fetch запросы  
✅ **Поддержка Android** через заголовок X-Auth-Token  
✅ **Rate limiting** защищает от brute force  

---

## 📁 Структура секретов

```
.
├── .env                    ← ВАШИ СЕКРЕТЫ (не в git)
├── .env.example            ← Шаблон (можно в git)
├── .gitignore              ← Правила игнорирования
├── migrations/
│   └── 001_create_auth_tokens.sql  ← SQL для БД
├── api/
│   ├── load_env.php        ← Загрузчик .env
│   ├── rate_limiter.php    ← Rate limiter
│   ├── auth_tokens.php     ← Управление токенами
│   ├── auth_middleware.php ← Проверка аутентификации
│   ├── login_by_token.php  ← Вход по токену (НОВЫЙ)
│   ├── email_helper.php    ← SMTP (исправлен)
│   ├── login.php           ← Старый вход (оставлен для совместимости)
│   └── register.php        ← Регистрация (без debug логов)
└── AndroidStudioProjects/
    └── namahatta/
        └── app/
            └── google-services.json  ← НЕ КОМИТЬ!
```

---

## 🧪 ТЕСТИРОВАНИЕ

### Проверка входа по токену:

```javascript
// Откройте консоль браузера на странице login.html
const token = await generateSecureToken();
console.log('Generated token:', token);

// Отправьте форму входа — токен будет в Network → Payload
// Но пароля там НЕ БУДЕТ!
```

### Проверка токена в localStorage:
```javascript
console.log(localStorage.getItem('auth_token'));
// Должен вернуть токен после входа
```

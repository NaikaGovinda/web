# Модель данных: Система Регистрации и Авторизации (005-auth-system)

## 1. Схемы JSON запросов и ответов

### А. Регистрация (POST /api/register.php)
**Запрос:**
```json
{
  "name": "Иван Иванов",
  "email": "ivan@example.com",
  "password": "strong_password_123"
}
```
**Успешный ответ (HTTP 201):**
```json
{
  "status": "success",
  "userId": 42,
  "message": "User registered and logged in"
}
```

### Б. Вход (POST /api/login.php)
**Запрос:**
```json
{
  "email": "ivan@example.com",
  "password": "strong_password_123"
}
```
**Успешный ответ (HTTP 200):**
```json
{
  "status": "success",
  "userId": 42,
  "userName": "Иван Иванов"
}
```

---

## 2. Структура Данных Моста (UserSession JSON)

Для метода `window.AndroidBridge.setUser(json)` используется следующий формат данных, согласно контрактам фичи 001:

```json
{
  "userId": "42",
  "userName": "Иван Иванов"
}
```
*Примечание: Поле `userName` добавлено для персонализации нативных логов и пушей.*
